<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Insights\StoreInsightArticleRequest;
use App\Http\Requests\Admin\Insights\UpdateInsightArticleRequest;
use App\Http\Resources\Insights\InsightArticleListResource;
use App\Http\Resources\Insights\InsightArticleResource;
use App\Models\Insights\InsightArticle;
use App\Models\Insights\InsightArticleRevision;
use App\Services\Insights\InsightArticleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InsightArticleController extends Controller
{
    public function __construct(private readonly InsightArticleService $articles)
    {
        $this->middleware('permission:admin.access');
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = InsightArticle::query()
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->input('category'), fn ($q, $c) => $q->where('category', $c))
            ->when($request->boolean('featured'), fn ($q) => $q->featured())
            ->orderByDesc('updated_at');

        return InsightArticleListResource::collection($query->paginate(20));
    }

    public function store(StoreInsightArticleRequest $request): JsonResponse
    {
        $article = $this->articles->create($request->validated(), $request->user());

        return (new InsightArticleResource($article))
            ->response()
            ->setStatusCode(201);
    }

    public function show(InsightArticle $article): InsightArticleResource
    {
        return new InsightArticleResource($article->load('author'));
    }

    public function update(UpdateInsightArticleRequest $request, InsightArticle $article): InsightArticleResource
    {
        $updated = $this->articles->update($article, $request->validated(), $request->user());

        return new InsightArticleResource($updated);
    }

    public function destroy(InsightArticle $article): JsonResponse
    {
        $article->delete();

        return response()->json(['message' => 'Deleted'], 200);
    }

    public function publish(InsightArticle $article): InsightArticleResource
    {
        return new InsightArticleResource($this->articles->publish($article));
    }

    public function archive(InsightArticle $article): InsightArticleResource
    {
        return new InsightArticleResource($this->articles->archive($article));
    }

    public function unarchive(InsightArticle $article): InsightArticleResource
    {
        return new InsightArticleResource($this->articles->unarchive($article));
    }

    public function feature(InsightArticle $article): InsightArticleResource
    {
        return new InsightArticleResource($this->articles->setFeatured($article));
    }

    public function unfeature(InsightArticle $article): InsightArticleResource
    {
        return new InsightArticleResource($this->articles->unsetFeatured($article));
    }

    public function resyncFromTemplate(Request $request, InsightArticle $article): InsightArticleResource
    {
        $updated = $this->articles->resyncFromTemplate($article, $request->user());

        return new InsightArticleResource($updated);
    }

    public function revisions(InsightArticle $article): JsonResponse
    {
        return response()->json([
            'data' => $article->revisions()->with('savedBy:id,first_name,surname')->get(),
        ]);
    }

    public function restoreRevision(Request $request, InsightArticle $article, InsightArticleRevision $revision): InsightArticleResource
    {
        abort_unless($revision->article_id === $article->id, 404);

        $updated = $this->articles->restoreRevision($article, $revision, $request->user());

        return new InsightArticleResource($updated);
    }
}
