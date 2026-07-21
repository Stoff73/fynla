<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\DocumentArticle;
use Illuminate\Support\Facades\Cache;

class DocumentArticleObserver
{
    public function saved(DocumentArticle $article): void
    {
        $this->bustCaches();
    }

    public function deleted(DocumentArticle $article): void
    {
        $this->bustCaches();
    }

    private function bustCaches(): void
    {
        Cache::forget('insights.featured');
        Cache::increment('insights.list_version');
    }
}
