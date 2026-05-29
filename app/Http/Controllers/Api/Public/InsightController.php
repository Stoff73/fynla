<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Insights\DocumentArticleAsInsightListResource;
use App\Http\Resources\Insights\DocumentArticleAsInsightResource;
use App\Http\Resources\Insights\InsightArticleListResource;
use App\Http\Resources\Insights\InsightArticleResource;
use App\Models\DocumentArticle;
use App\Models\Insights\InsightArticle;
use App\Services\Insights\InsightArticleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Cache;

class InsightController extends Controller
{
    public function __construct(private readonly InsightArticleService $articles) {}

    public function index(Request $request): JsonResponse
    {
        $category = $request->input('category');
        $page = max(1, (int) $request->input('page', 1));
        $version = (int) Cache::get('insights.list_version', 1);
        $cacheKey = "insights.list.v{$version}.cat-".($category ?: 'all').".page.{$page}";

        [$data, $meta] = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($category, $page) {
            $insights = InsightArticle::published()
                ->when($category, fn ($q) => $q->where('category', $category))
                ->orderByDesc('published_at')
                ->paginate(24, ['*'], 'page', $page);

            $items = InsightArticleListResource::collection($insights)->resolve();

            // CMS-imported document articles have no category, so they only
            // surface when no category filter is active. Merged onto page 1
            // ahead of native insights and re-sorted by published_at desc.
            $docTotal = 0;
            if (! $category && $page === 1) {
                $docs = DocumentArticle::published()->orderByDesc('published_at')->get();
                $docTotal = $docs->count();
                $docItems = DocumentArticleAsInsightListResource::collection($docs)->resolve();
                $items = collect($docItems)->merge($items)
                    ->sortByDesc('published_at')
                    ->values()
                    ->all();
            }

            return [$items, [
                'current_page' => $insights->currentPage(),
                'last_page' => max($insights->lastPage(), 1),
                'total' => $insights->total() + $docTotal,
            ]];
        });

        return response()->json(['data' => $data, 'meta' => $meta]);
    }

    public function featured(): JsonResponse
    {
        // Prefer an explicitly-flagged article; fall back to the most recently
        // published one so the homepage always has a featured article.
        $featured = $this->articles->getFeatured()
            ?? InsightArticle::published()->orderByDesc('published_at')->first();

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

    public function show(Request $request, string $slug): JsonResource
    {
        // Route is public, so the default guard ("web") does not resolve the
        // SPA's Sanctum session or Bearer token. Ask Sanctum explicitly.
        $user = $request->user('sanctum') ?? $request->user();
        $allowDraft = $request->boolean('preview') && $user?->is_admin;

        // Native insight articles take precedence on slug collision.
        $insightQuery = InsightArticle::where('slug', $slug);
        if (! $allowDraft) {
            $insightQuery->published();
        }
        if ($article = $insightQuery->first()) {
            return new InsightArticleResource($article->load('author'));
        }

        // Fall back to CMS-imported document articles.
        $docQuery = DocumentArticle::where('slug', $slug);
        if (! $allowDraft) {
            $docQuery->published();
        }

        return new DocumentArticleAsInsightResource($docQuery->firstOrFail());
    }
}
