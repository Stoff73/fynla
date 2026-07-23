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
        // An explicitly-flagged native article wins the featured slot.
        $featuredModel = $this->articles->getFeatured();
        $featured = $featuredModel
            ? (new InsightArticleListResource($featuredModel))->resolve()
            : null;

        // Pool the most-recent published articles from BOTH sources — native
        // insights AND CMS/pipeline document articles — so document articles
        // surface on the homepage strip, not just the /insights listing.
        $insights = InsightArticle::published()->orderByDesc('published_at')->take(6)->get();
        $docs = DocumentArticle::published()->orderByDesc('published_at')->take(6)->get();
        $pool = collect(DocumentArticleAsInsightListResource::collection($docs)->resolve())
            ->merge(InsightArticleListResource::collection($insights)->resolve())
            ->sortByDesc('published_at')
            ->values();

        // No explicit feature → the newest across the pool leads.
        if ($featured === null) {
            $featured = $pool->first();
            $pool = $pool->slice(1)->values();
        }

        $featuredSlug = $featured['slug'] ?? null;
        $supporting = $pool
            ->reject(fn ($a) => $featuredSlug !== null && ($a['slug'] ?? null) === $featuredSlug)
            ->take(2)
            ->values()
            ->all();

        return response()->json([
            'data' => [
                'featured' => $featured,
                'supporting' => $supporting,
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

        return new DocumentArticleAsInsightResource($docQuery->with('campaign')->firstOrFail());
    }
}
