<?php

declare(strict_types=1);

namespace App\Jobs\Pipeline;

use App\Mail\Pipeline\ScriptReadyForReviewMail;
use App\Models\Pipeline\PipelineArticle;
use App\Models\Pipeline\PipelineRun;
use App\Services\Pipeline\Google\GoogleDriveService;
use App\Services\Pipeline\Google\GoogleSheetsService;
use App\Services\Pipeline\Google\ScriptsFolderLocator;
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
        ScriptsFolderLocator $scriptsFolder,
    ): void {
        $article = $this->pipelineArticle->fresh();

        if (! in_array($article->status, ['detected', 'failed'], true)) {
            Log::channel('pipeline')->info('Skipping — article not in a runnable state.', [
                'pipeline_article_id' => $article->id,
                'status' => $article->status,
            ]);

            return;
        }

        // The article may be sourced from a native InsightArticle or from the
        // DocumentArticle CMS. Resolve a common shape for the script step.
        $insightArticle = $article->insightArticle;
        $documentArticle = $article->documentArticle;

        if ($insightArticle !== null) {
            $title = (string) $insightArticle->title;
            $slug = (string) $insightArticle->slug;
            $publishedAt = $insightArticle->published_at;
            $bodyText = null; // generator flattens body_blocks itself
        } elseif ($documentArticle !== null) {
            $title = (string) $documentArticle->title;
            $slug = (string) $documentArticle->slug;
            $publishedAt = $documentArticle->published_at;
            $bodyText = $generator->plainTextFromHtml((string) $documentArticle->html_body);
        } else {
            $this->fail($article, 'Source article no longer exists.');

            return;
        }

        $article->update(['status' => 'scripting']);

        $run = PipelineRun::create([
            'pipeline_article_id' => $article->id,
            'stage' => 'script',
            'status' => 'running',
            'started_at' => now(),
            'metadata' => [
                'source_type' => $insightArticle !== null ? 'insight' : 'document',
                'source_slug' => $slug,
            ],
        ]);

        try {
            $result = $insightArticle !== null
                ? $generator->generate($insightArticle)
                : $generator->generateFromContent($title, $publishedAt, (string) $bodyText);

            $markdown = $this->renderMarkdown($title, $slug, $publishedAt, $result['data']);
            $docTitle = $this->datePrefix($slug, $publishedAt).' — '.$title;

            // Scripts live in their own "Scripts" subfolder, not the Marketing
            // Automation root alongside the Articles/Videos input folders.
            $uploaded = $drive->uploadMarkdownAsGoogleDoc($docTitle, $markdown, $scriptsFolder->resolve());

            // Tracker-sheet logging is best-effort: the script is already in
            // Drive, so a Sheets outage/timeout must not fail the pipeline.
            $sheetRow = null;
            $sheetId = config('pipeline.google.tracker_sheet_id');
            if (is_string($sheetId) && $sheetId !== '') {
                try {
                    $sheetRow = $sheets->appendRow($sheetId, [
                        now()->toIso8601String(),
                        $slug,
                        $title,
                        $uploaded['webViewLink'],
                        'Script Ready',
                        '',
                        '',
                        '',
                    ]);
                } catch (Throwable $e) {
                    Log::channel('pipeline')->warning('Tracker sheet append failed (non-fatal).', [
                        'pipeline_article_id' => $article->id,
                        'error' => $e->getMessage(),
                    ]);
                }
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

            // Notify marketing: the script is ready (no approval needed — it's
            // already in Drive) and the article is waiting in the admin panel for
            // formatting review + push to dev/live. Non-fatal: a mail failure
            // must not fail the pipeline.
            try {
                Mail::to((string) config('pipeline.notifications.script_ready_to'))
                    ->queue(new ScriptReadyForReviewMail($article->fresh()));
            } catch (Throwable $e) {
                Log::channel('pipeline')->warning('Script-ready notification failed (non-fatal).', [
                    'pipeline_article_id' => $article->id,
                    'error' => $e->getMessage(),
                ]);
            }

            Log::channel('pipeline')->info('Script generated and delivered to Drive.', [
                'pipeline_article_id' => $article->id,
                'source_slug' => $slug,
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
                'source_slug' => $slug,
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

    /**
     * YYYYMMDD prefix (UK date, reversed) so scripts sort chronologically in
     * Drive and share a date stem with the video for the same article.
     *
     * Prefers the date already embedded in the article slug (e.g.
     * "20260719-01-the-tax-break…") so the script and its video line up exactly;
     * falls back to the published date, then today.
     */
    private function datePrefix(?string $slug, ?\DateTimeInterface $publishedAt): string
    {
        if (is_string($slug) && preg_match('/^(\d{8})/', $slug, $matches) === 1) {
            return $matches[1];
        }

        return $publishedAt !== null
            ? $publishedAt->format('Ymd')
            : now()->format('Ymd');
    }

    private function renderMarkdown(string $title, string $slug, ?\DateTimeInterface $publishedAt, array $script): string
    {
        $publishedLabel = $publishedAt !== null
            ? \Illuminate\Support\Carbon::instance(\Illuminate\Support\Carbon::parse($publishedAt))->toFormattedDateString()
            : 'unknown date';
        $articleUrl = $this->articleUrl($slug);

        return <<<MD
        # {$script['title']}

        _Draft video script generated from [{$title}]({$articleUrl}) on {$publishedLabel}._

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

        _Source article: {$articleUrl}_
        MD;
    }

    private function articleUrl(string $slug): string
    {
        return rtrim((string) config('app.url', 'https://fynla.org'), '/').'/insights/'.$slug;
    }
}
