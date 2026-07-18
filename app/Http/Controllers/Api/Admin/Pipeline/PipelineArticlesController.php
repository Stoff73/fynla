<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Pipeline;

use App\Http\Controllers\Controller;
use App\Models\Insights\InsightArticle;
use App\Models\Pipeline\ArticleSyncLog;
use App\Models\Pipeline\PublisherUser;
use App\Services\Pipeline\Content\ArticleImporter;
use App\Services\Pipeline\Content\ArticleSyncService;
use App\Services\Pipeline\Google\ArticlesFolderLocator;
use App\Services\Pipeline\Google\GoogleDriveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Backend for the Stage 5 CMS at /admin/pipeline/articles.
 *
 * State transitions (enforced here + mirrored in the Vue UI):
 *   - publish-local: article must currently be `draft`
 *   - push-to-dev:   article must be `published` (locally); marketing has
 *                    reviewed the local render
 *   - push-to-live:  must have dev_synced_at set; must be
 *                    >= config('pipeline.gate.dev_to_prod_min_hours') old;
 *                    caller must be in pipeline_publisher_users
 */
class PipelineArticlesController extends Controller
{
    public function __construct(private readonly ArticleSyncService $sync)
    {
        $this->middleware('permission:admin.access');
    }

    public function index(Request $request): JsonResponse
    {
        $query = InsightArticle::query()
            ->with(['pipelineCampaign'])
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->input('category'), fn ($q, $c) => $q->where('category', $c))
            ->when($request->input('campaign_id'), fn ($q, $id) => $q->where('pipeline_campaign_id', $id))
            ->when($request->boolean('has_docx'), fn ($q) => $q->whereNotNull('source_docx_drive_file_id'))
            ->when($request->input('search'), fn ($q, $term) => $q->where(function ($qq) use ($term) {
                $qq->where('title', 'like', "%{$term}%")->orWhere('slug', 'like', "%{$term}%");
            }))
            ->orderByDesc('updated_at');

        $paginated = $query->paginate(30);

        $paginated->getCollection()->transform(function (InsightArticle $article) use ($request) {
            return $this->summarise($article, $request);
        });

        return response()->json([
            'success' => true,
            'data' => $paginated,
        ]);
    }

    public function show(Request $request, InsightArticle $article): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => array_merge(
                $this->summarise($article->load('pipelineCampaign'), $request),
                ['body_blocks' => $article->body_blocks ?? []],
            ),
        ]);
    }

    public function update(Request $request, InsightArticle $article): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'subtitle' => ['sometimes', 'nullable', 'string', 'max:500'],
            'summary' => ['sometimes', 'string', 'max:1000'],
            'category' => ['sometimes', 'string', 'max:100'],
            'tags' => ['sometimes', 'array'],
            'meta_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'meta_description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'pipeline_campaign_id' => ['sometimes', 'nullable', 'exists:pipeline_campaigns,id'],
            'is_featured' => ['sometimes', 'boolean'],
            'body_blocks' => ['sometimes', 'array'],
        ]);

        $article->update($validated);

        return response()->json([
            'success' => true,
            'data' => $this->summarise($article->fresh()->load('pipelineCampaign'), $request),
        ]);
    }

    public function publishLocal(Request $request, InsightArticle $article): JsonResponse
    {
        if ($article->status !== 'draft') {
            throw new HttpException(422, "Article is not a draft (current status: {$article->status}).");
        }

        $article->update([
            'status' => 'published',
            'published_at' => now(),
        ]);

        ArticleSyncLog::create([
            'insight_article_id' => $article->id,
            'actor_id' => $request->user()->id,
            'target_env' => 'local',
            'action' => 'publish',
            'status' => 'success',
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->summarise($article->fresh()->load('pipelineCampaign'), $request),
        ]);
    }

    public function pushToDev(Request $request, InsightArticle $article): JsonResponse
    {
        if ($article->status !== 'published' || $article->published_at === null) {
            throw new HttpException(422, 'Article must be published locally first.');
        }

        try {
            $this->sync->push($article->fresh()->load('pipelineCampaign'), 'dev', $request->user()->id);
        } catch (Throwable $e) {
            throw new HttpException(502, 'Push to dev failed: '.$e->getMessage());
        }

        return response()->json([
            'success' => true,
            'data' => $this->summarise($article->fresh()->load('pipelineCampaign'), $request),
        ]);
    }

    public function pushToLive(Request $request, InsightArticle $article): JsonResponse
    {
        $this->assertCanPushToLive($request, $article);

        try {
            $this->sync->push($article->fresh()->load('pipelineCampaign'), 'prod', $request->user()->id);
        } catch (Throwable $e) {
            throw new HttpException(502, 'Push to live failed: '.$e->getMessage());
        }

        return response()->json([
            'success' => true,
            'data' => $this->summarise($article->fresh()->load('pipelineCampaign'), $request),
        ]);
    }

    public function reimport(
        Request $request,
        InsightArticle $article,
        ArticleImporter $importer,
        GoogleDriveService $drive,
        ArticlesFolderLocator $locator,
    ): JsonResponse {
        if (! $article->source_docx_drive_file_id) {
            throw new HttpException(422, 'Article has no source Word doc — nothing to re-import.');
        }

        $articlesFolderId = $locator->resolve();
        $files = array_merge(
            $drive->listFiles($articlesFolderId, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            $drive->listFiles($articlesFolderId, 'application/vnd.google-apps.document'),
        );
        $target = collect($files)->firstWhere('id', $article->source_docx_drive_file_id);

        if ($target === null) {
            throw new HttpException(404, 'Source Word doc no longer in the Articles folder.');
        }

        try {
            $result = $importer->import($target);
        } catch (Throwable $e) {
            throw new HttpException(502, 'Re-import failed: '.$e->getMessage());
        }

        return response()->json([
            'success' => true,
            'data' => $this->summarise($result['article']->fresh()->load('pipelineCampaign'), $request),
            'action' => $result['action'],
        ]);
    }

    public function destroy(Request $request, InsightArticle $article): JsonResponse
    {
        DB::transaction(function () use ($article, $request) {
            ArticleSyncLog::create([
                'insight_article_id' => $article->id,
                'actor_id' => $request->user()->id,
                'target_env' => 'local',
                'action' => 'delete',
                'status' => 'success',
            ]);
            $article->delete();
        });

        return response()->json(['success' => true]);
    }

    private function assertCanPushToLive(Request $request, InsightArticle $article): void
    {
        if ($article->dev_synced_at === null) {
            throw new HttpException(422, 'Article must be on dev first.');
        }

        $minHours = (int) config('pipeline.gate.dev_to_prod_min_hours', 1);
        if ($article->dev_synced_at->addHours($minHours)->isFuture()) {
            throw new HttpException(
                422,
                "Article must be on dev for at least {$minHours} hour(s) before pushing to live."
            );
        }

        if (! PublisherUser::isPublisher($request->user()->id)) {
            throw new HttpException(403, 'You do not have permission to push articles to live.');
        }
    }

    private function summarise(InsightArticle $article, Request $request): array
    {
        $canPublishLocal = $article->status === 'draft';
        $localPublished = $article->status === 'published' && $article->published_at !== null;
        $canPushToDev = $localPublished;
        $devSyncedLongEnough = $article->dev_synced_at?->addHours(
            (int) config('pipeline.gate.dev_to_prod_min_hours', 1)
        )?->isPast() ?? false;
        $userCanPublishLive = PublisherUser::isPublisher($request->user()?->id ?? 0);
        $canPushToLive = $article->dev_synced_at !== null && $devSyncedLongEnough && $userCanPublishLive;

        return [
            'id' => $article->id,
            'slug' => $article->slug,
            'title' => $article->title,
            'subtitle' => $article->subtitle,
            'summary' => $article->summary,
            'category' => $article->category,
            'tags' => $article->tags,
            'meta_title' => $article->meta_title,
            'meta_description' => $article->meta_description,
            'is_featured' => (bool) $article->is_featured,
            'status' => $article->status,
            'published_at' => optional($article->published_at)->toIso8601String(),
            'updated_at' => optional($article->updated_at)->toIso8601String(),
            'source_docx' => [
                'drive_file_id' => $article->source_docx_drive_file_id,
                'drive_url' => $article->source_docx_drive_url,
                'imported_at' => optional($article->source_docx_imported_at)->toIso8601String(),
            ],
            'env_state' => [
                'local_status' => $article->status === 'published' ? 'live' : ($article->status === 'draft' ? 'draft' : $article->status),
                'dev_synced_at' => optional($article->dev_synced_at)->toIso8601String(),
                'prod_synced_at' => optional($article->prod_synced_at)->toIso8601String(),
                'gate_hours_remaining' => $article->dev_synced_at !== null
                    ? max(0, (int) round($article->dev_synced_at->addHours((int) config('pipeline.gate.dev_to_prod_min_hours', 1))->diffInMinutes(now(), false) * -1 / 60))
                    : null,
            ],
            'actions' => [
                'can_publish_local' => $canPublishLocal,
                'can_push_to_dev' => $canPushToDev,
                'can_push_to_live' => $canPushToLive,
                'user_is_publisher' => $userCanPublishLive,
            ],
            'campaign' => $article->pipelineCampaign ? [
                'id' => $article->pipelineCampaign->id,
                'slug' => $article->pipelineCampaign->slug,
                'name' => $article->pipelineCampaign->name,
            ] : null,
        ];
    }
}
