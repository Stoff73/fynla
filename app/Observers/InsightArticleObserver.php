<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\Pipeline\ComposePostsJob;
use App\Models\Insights\InsightArticle;
use App\Models\Insights\InsightArticleRevision;
use App\Models\Pipeline\ClipApproval;
use App\Models\Pipeline\PipelineArticle;
use App\Services\Pipeline\ClipApprovalService;
use Illuminate\Support\Facades\Cache;

class InsightArticleObserver
{
    /**
     * Set while an automated process is writing articles, so its changes are
     * still versioned even though no person is behind them.
     */
    private static ?string $systemSource = null;

    /**
     * Attribute every article write inside $callback to an automated source
     * rather than a user. Without this a console-driven write records no
     * revision at all, because there is no authenticated actor to credit.
     *
     * Deliberately opt-in: the cross-environment sync also runs without an
     * actor and does not want revisions (its own sync log is the audit), so it
     * simply does not call this.
     */
    public static function actingAsSystem(string $source, callable $callback): mixed
    {
        $previous = self::$systemSource;
        self::$systemSource = $source;

        try {
            return $callback();
        } finally {
            self::$systemSource = $previous;
        }
    }

    public function created(InsightArticle $article): void
    {
        $this->writeRevision($article);
    }

    public function updated(InsightArticle $article): void
    {
        $this->writeRevision($article);
        $this->bustCaches();
        $this->resumeHeldComposition($article);
    }

    public function saved(InsightArticle $article): void
    {
        $this->bustCaches();
    }

    public function deleted(InsightArticle $article): void
    {
        $this->bustCaches();
    }

    /**
     * The marketing pipeline now runs from the Drive upload, so by the time a
     * founder publishes, the clips are usually already cut and composition is
     * sitting in ComposePostsJob's publish hold (every post links to
     * /insights/{slug}, which 404s until the article is live). Publishing is
     * the only thing that hold waits for, and nothing else re-runs it — so the
     * nudge belongs here, on the one write every publish path goes through.
     */
    private function resumeHeldComposition(InsightArticle $article): void
    {
        if (! $article->wasChanged('status') || $article->status !== 'published') {
            return;
        }

        PipelineArticle::where('insight_article_id', $article->id)
            ->where('status', 'rendered')
            ->get()
            ->each(function (PipelineArticle $pipelineArticle): void {
                // Clip approval owns the "are the clips settled?" question
                // whenever it is on; without it, ProcessVideoJob's own dispatch
                // is what the guard held, so re-dispatch that.
                ClipApproval::where('pipeline_article_id', $pipelineArticle->id)->exists()
                    ? app(ClipApprovalService::class)->evaluateDownstream($pipelineArticle)
                    : ComposePostsJob::dispatch($pipelineArticle);
            });
    }

    private function writeRevision(InsightArticle $article): void
    {
        if ($article->is_bespoke) {
            return;
        }

        $source = self::$systemSource;
        $savedBy = $source === null ? (auth()->id() ?? $article->author_id) : null;

        if ($source === null && $savedBy === null) {
            // Cross-env sync inserts (from ArticleSyncService::receive) have
            // no local actor and declare no source — skip the revision. The
            // article itself is the audit; the sync log records who pushed it.
            return;
        }

        InsightArticleRevision::create([
            'article_id' => $article->id,
            'title' => $article->title,
            'subtitle' => $article->subtitle,
            'summary' => $article->summary,
            'body_blocks' => $article->body_blocks,
            'saved_by' => $savedBy,
            'source' => $source ?? InsightArticleRevision::SOURCE_CMS,
            'saved_at' => now(),
        ]);
    }

    private function bustCaches(): void
    {
        Cache::forget('insights.featured');
        Cache::increment('insights.list_version');
    }
}
