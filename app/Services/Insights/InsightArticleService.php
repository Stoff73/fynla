<?php

declare(strict_types=1);

namespace App\Services\Insights;

use App\Models\Insights\InsightArticle;
use App\Models\Insights\InsightArticleRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InsightArticleService
{
    public function create(array $data, User $author): InsightArticle
    {
        $data['slug'] = $this->uniqueSlug($data['slug'] ?? Str::slug($data['title']));
        $data['author_id'] = $author->id;
        $data['status'] = $data['status'] ?? 'draft';
        $data['body_blocks'] = $data['body_blocks'] ?? [];

        return InsightArticle::create($data);
    }

    public function update(InsightArticle $article, array $data, User $editor): InsightArticle
    {
        if (isset($data['slug']) && $data['slug'] !== $article->slug) {
            $data['slug'] = $this->uniqueSlug($data['slug'], $article->id);
        }

        // Make the editor available to the InsightArticleObserver, which owns the revision write.
        auth()->setUser($editor);
        $article->update($data);

        return $article->fresh();
    }

    public function publish(InsightArticle $article): InsightArticle
    {
        $article->update([
            'status' => 'published',
            'published_at' => $article->published_at ?? now(),
            'scheduled_at' => null,
        ]);

        return $article->fresh();
    }

    public function archive(InsightArticle $article): InsightArticle
    {
        $article->update(['status' => 'archived', 'is_featured' => false]);

        return $article->fresh();
    }

    public function unarchive(InsightArticle $article): InsightArticle
    {
        $article->update(['status' => $article->published_at ? 'published' : 'draft']);

        return $article->fresh();
    }

    public function setFeatured(InsightArticle $article): InsightArticle
    {
        return DB::transaction(function () use ($article) {
            InsightArticle::where('is_featured', true)
                ->where('id', '!=', $article->id)
                ->update(['is_featured' => false]);

            $article->update(['is_featured' => true]);

            return $article->fresh();
        });
    }

    public function unsetFeatured(InsightArticle $article): InsightArticle
    {
        $article->update(['is_featured' => false]);

        return $article->fresh();
    }

    public function resyncFromTemplate(InsightArticle $article, User $editor): InsightArticle
    {
        if (! $article->template) {
            return $article;
        }

        return $this->update($article, [
            'body_blocks' => $article->template->body_blocks,
        ], $editor);
    }

    public function getFeatured(): ?InsightArticle
    {
        return InsightArticle::published()->featured()->first();
    }

    public function restoreRevision(InsightArticle $article, InsightArticleRevision $revision, User $editor): InsightArticle
    {
        return $this->update($article, [
            'title' => $revision->title,
            'subtitle' => $revision->subtitle,
            'summary' => $revision->summary,
            'body_blocks' => $revision->body_blocks,
        ], $editor);
    }

    private function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $slug = Str::slug($base);
        $candidate = $slug;
        $n = 2;

        while (InsightArticle::where('slug', $candidate)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()
        ) {
            $candidate = "{$slug}-{$n}";
            $n++;
        }

        return $candidate;
    }
}
