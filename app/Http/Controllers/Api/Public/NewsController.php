<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\News\NewsArticleListResource;
use App\Http\Resources\News\NewsArticleResource;
use App\Models\News\NewsArticle;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NewsController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $articles = NewsArticle::published()
            ->orderByDesc('published_at')
            ->paginate(24);

        return NewsArticleListResource::collection($articles);
    }

    public function show(string $slug): NewsArticleResource
    {
        $article = NewsArticle::published()
            ->where('slug', $slug)
            ->firstOrFail();

        return new NewsArticleResource($article);
    }
}
