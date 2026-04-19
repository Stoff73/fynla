<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Insights\InsightArticleListResource;
use App\Http\Resources\Insights\InsightArticleResource;
use App\Models\Insights\InsightArticle;
use App\Services\Insights\InsightArticleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;

class InsightController extends Controller
{
    public function __construct(private readonly InsightArticleService $articles) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $category = $request->input('category');
        $page = max(1, (int) $request->input('page', 1));
        $version = (int) Cache::get('insights.list_version', 1);
        $cacheKey = "insights.list.v{$version}.cat-".($category ?: 'all').".page.{$page}";

        $articles = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($category) {
            return InsightArticle::published()
                ->when($category, fn ($q) => $q->where('category', $category))
                ->orderByDesc('published_at')
                ->paginate(24);
        });

        return InsightArticleListResource::collection($articles);
    }

    public function featured(): JsonResponse
    {
        // Only surface an article as "featured" when it has been explicitly
        // flagged is_featured=true from the admin. Previously this fell back
        // to the most-recently-published article, which made authors think
        // new articles auto-promoted themselves.
        $featured = $this->articles->getFeatured();

        $supporting = InsightArticle::published()
            ->when($featured, fn ($q) => $q->where('id', '!=', $featured->id))
            ->orderByDesc('published_at')
            ->take(2)
            ->get();

        return response()->json([
            'data' => [
                'featured' => $featured
                    ? (new InsightArticleListResource($featured))->resolve()
                    : null,
                'supporting' => InsightArticleListResource::collection($supporting)->resolve(),
            ],
        ]);
    }

    public function show(Request $request, string $slug): InsightArticleResource
    {
        $query = InsightArticle::where('slug', $slug);

        // Route is public, so the default guard ("web") does not resolve the
        // SPA's Sanctum session or Bearer token. Ask Sanctum explicitly.
        $user = $request->user('sanctum') ?? $request->user();
        if (! ($request->boolean('preview') && $user?->is_admin)) {
            $query->published();
        }

        $article = $query->firstOrFail();

        return new InsightArticleResource($article->load('author'));
    }
}
