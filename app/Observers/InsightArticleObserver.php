<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Insights\InsightArticle;
use App\Models\Insights\InsightArticleRevision;
use Illuminate\Support\Facades\Cache;

class InsightArticleObserver
{
    public function created(InsightArticle $article): void
    {
        $this->writeRevision($article);
    }

    public function updated(InsightArticle $article): void
    {
        $this->writeRevision($article);
        $this->bustCaches();
    }

    public function saved(InsightArticle $article): void
    {
        $this->bustCaches();
    }

    public function deleted(InsightArticle $article): void
    {
        $this->bustCaches();
    }

    private function writeRevision(InsightArticle $article): void
    {
        if ($article->is_bespoke) {
            return;
        }

        InsightArticleRevision::create([
            'article_id' => $article->id,
            'title' => $article->title,
            'subtitle' => $article->subtitle,
            'summary' => $article->summary,
            'body_blocks' => $article->body_blocks,
            'saved_by' => auth()->id() ?? $article->author_id,
            'saved_at' => now(),
        ]);
    }

    private function bustCaches(): void
    {
        Cache::forget('insights.featured');
        Cache::increment('insights.list_version');
    }
}
