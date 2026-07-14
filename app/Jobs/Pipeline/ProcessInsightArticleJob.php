<?php

declare(strict_types=1);

namespace App\Jobs\Pipeline;

use App\Mail\Pipeline\ScriptReadyForReviewMail;
use App\Models\Pipeline\PipelineArticle;
use App\Models\Pipeline\PipelineRun;
use App\Services\Pipeline\Google\GoogleDriveService;
use App\Services\Pipeline\Google\GoogleSheetsService;
use App\Services\Pipeline\VideoScriptGeneratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Stage 2 job — generates a video script for one detected InsightArticle,
 * uploads it to Drive, appends a row to the tracker sheet, and emails the
 * marketing inbox with a link for review.
 *
 * State transitions on the PipelineArticle row:
 *   detected → scripting → scripted   (happy path)
 *   detected → scripting → failed     (any error; retry_count incremented)
 *
 * A PipelineRun record is written for the "script" stage. Errors are logged
 * to the pipeline channel with the full context so the marketing team can
 * see why a run failed.
 */
class ProcessInsightArticleJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public PipelineArticle $pipelineArticle)
    {
        $this->onQueue(config('pipeline.queue', 'pipeline'));
    }

    public function handle(
        VideoScriptGeneratorService $generator,
        GoogleDriveService $drive,
        GoogleSheetsService $sheets,
    ): void {
        $article = $this->pipelineArticle->fresh();

        if (! in_array($article->status, ['detected', 'failed'], true)) {
            Log::channel('pipeline')->info('Skipping — article not in a runnable state.', [
                'pipeline_article_id' => $article->id,
                'status' => $article->status,
            ]);

            return;
        }

        $insightArticle = $article->insightArticle;
        if ($insightArticle === null) {
            $this->fail($article, 'Insight article no longer exists.');

            return;
        }

        $article->update(['status' => 'scripting']);

        $run = PipelineRun::create([
            'pipeline_article_id' => $article->id,
            'stage' => 'script',
            'status' => 'running',
            'started_at' => now(),
            'metadata' => [
                'insight_article_id' => $insightArticle->id,
                'insight_article_slug' => $insightArticle->slug,
            ],
        ]);

        try {
            $result = $generator->generate($insightArticle);

            $markdown = $this->renderMarkdown($insightArticle, $result['data']);
            $docTitle = 'Script — '.$insightArticle->title;

            $uploaded = $drive->uploadMarkdownAsGoogleDoc($docTitle, $markdown);

            $sheetRow = null;
            $sheetId = config('pipeline.google.tracker_sheet_id');
            if (is_string($sheetId) && $sheetId !== '') {
                $sheetRow = $sheets->appendRow($sheetId, [
                    now()->toIso8601String(),
                    $insightArticle->slug,
                    $insightArticle->title,
                    $uploaded['webViewLink'],
                    'Script Ready',
                    '',
                    '',
                    '',
                ]);
            }

            $article->update([
                'status' => 'scripted',
                'script_drive_file_id' => $uploaded['id'],
                'script_drive_url' => $uploaded['webViewLink'],
                'tracking_sheet_row_id' => $sheetRow !== null ? (string) $sheetRow : null,
                'script_model' => $result['model'],
                'script_prompt_version' => $result['prompt_version'],
                'script_cost_gbp' => $result['cost_gbp'],
                'script_generated_at' => now(),
                'last_error' => null,
            ]);

            $run->update([
                'status' => 'success',
                'finished_at' => now(),
                'metadata' => array_merge($run->metadata ?? [], [
                    'cost_gbp' => $result['cost_gbp'],
                    'usage' => $result['usage'],
                    'drive_file_id' => $uploaded['id'],
                    'sheet_row' => $sheetRow,
                ]),
            ]);

            Mail::to((string) config('pipeline.notifications.script_ready_to'))
                ->queue(new ScriptReadyForReviewMail($article->fresh(), $insightArticle));

            Log::channel('pipeline')->info('Script generated and delivered.', [
                'pipeline_article_id' => $article->id,
                'insight_article_id' => $insightArticle->id,
                'cost_gbp' => $result['cost_gbp'],
            ]);
        } catch (Throwable $e) {
            $run->update([
                'status' => 'error',
                'error' => $e->getMessage(),
                'finished_at' => now(),
            ]);

            $article->increment('retry_count');
            $article->update([
                'status' => 'failed',
                'last_error' => $e->getMessage(),
            ]);

            Log::channel('pipeline')->error('Script generation failed.', [
                'pipeline_article_id' => $article->id,
                'insight_article_id' => $insightArticle->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function fail(PipelineArticle $article, string $reason): void
    {
        $article->update(['status' => 'failed', 'last_error' => $reason]);

        PipelineRun::create([
            'pipeline_article_id' => $article->id,
            'stage' => 'script',
            'status' => 'error',
            'error' => $reason,
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        Log::channel('pipeline')->error('Script generation aborted.', [
            'pipeline_article_id' => $article->id,
            'reason' => $reason,
        ]);
    }

    private function renderMarkdown(\App\Models\Insights\InsightArticle $article, array $script): string
    {
        return <<<MD
        # {$script['title']}

        _Draft video script generated from [{$article->title}]({$this->articleUrl($article->slug)}) on {$article->published_at?->toFormattedDateString()}._

        ---

        ## Hook (5–8s)

        {$script['hook']}

        ## Script (approx. {$script['estimated_duration_seconds']}s)

        {$script['script']}

        ## Analogy used

        {$script['analogy_used']}

        ## Key concept

        {$script['key_concept']}

        ## Call to action

        {$script['cta_text']}

        ---

        _Source article: {$this->articleUrl($article->slug)}_
        MD;
    }

    private function articleUrl(string $slug): string
    {
        return rtrim((string) config('app.url', 'https://fynla.org'), '/').'/insights/'.$slug;
    }
}
