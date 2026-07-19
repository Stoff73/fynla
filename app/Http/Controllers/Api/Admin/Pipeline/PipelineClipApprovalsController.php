<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Pipeline;

use App\Http\Controllers\Controller;
use App\Models\Pipeline\ClipApproval;
use App\Models\Pipeline\PipelineArticle;
use App\Services\Pipeline\ClipApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Admin API for /admin/pipeline/clips. Reviewer sees pending clips
 * grouped by article, previews each via signed download URL, approves
 * or rejects individually or via the article-level Approve All.
 */
class PipelineClipApprovalsController extends Controller
{
    public function __construct(private readonly ClipApprovalService $service)
    {
        $this->middleware('permission:admin.access');
    }

    public function index(Request $request): JsonResponse
    {
        $query = ClipApproval::query()
            ->with(['pipelineArticle.insightArticle'])
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->input('article_id'), fn ($q, $id) => $q->where('pipeline_article_id', $id))
            ->orderBy('scheduled_at');

        $items = $query->get()->map(fn (ClipApproval $a) => $this->summarise($a));

        $grouped = $items->groupBy('article_id')->map(function ($group) {
            $first = $group->first();

            return [
                'article_id' => $first['article_id'],
                'article_title' => $first['article_title'],
                'article_slug' => $first['article_slug'],
                'clips' => $group->values(),
                'total_clips' => $group->count(),
                'pending_count' => $group->where('status', 'pending')->count(),
                'earliest_scheduled_at' => $group->min('scheduled_at'),
            ];
        })->values();

        return response()->json(['success' => true, 'data' => $grouped]);
    }

    public function show(ClipApproval $approval): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->summarise($approval->load(['pipelineArticle.insightArticle'])),
        ]);
    }

    public function approve(Request $request, ClipApproval $approval): JsonResponse
    {
        try {
            $this->service->approve($approval, actorId: $request->user()->id);
        } catch (Throwable $e) {
            throw new HttpException(422, $e->getMessage());
        }

        return response()->json(['success' => true, 'data' => $this->summarise($approval->fresh()->load(['pipelineArticle.insightArticle']))]);
    }

    public function reject(Request $request, ClipApproval $approval): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $this->service->reject($approval, reason: $validated['reason'], actorId: $request->user()->id);
        } catch (Throwable $e) {
            throw new HttpException(422, $e->getMessage());
        }

        return response()->json(['success' => true, 'data' => $this->summarise($approval->fresh()->load(['pipelineArticle.insightArticle']))]);
    }

    public function approveAll(Request $request, int $pipelineArticleId): JsonResponse
    {
        $article = PipelineArticle::findOrFail($pipelineArticleId);
        $decided = $this->service->approveAllForArticle($article, actorId: $request->user()->id);

        return response()->json(['success' => true, 'decided_count' => count($decided)]);
    }

    private function summarise(ClipApproval $approval): array
    {
        $article = $approval->pipelineArticle;
        $insight = $article?->insightArticle;
        $slug = $insight?->slug ?? '';
        $filename = basename((string) $approval->clip_path);

        $previewUrl = ($slug !== '' && preg_match('/^clip-\d{1,3}\.mp4$/', $filename))
            ? URL::temporarySignedRoute(
                'pipeline.clip.download',
                now()->addMinutes(30),
                ['slug' => $slug, 'filename' => $filename],
            )
            : null;

        return [
            'id' => $approval->id,
            'article_id' => $article?->id,
            'article_title' => $insight?->title,
            'article_slug' => $slug,
            'clip_index' => $approval->clip_index,
            'clip_path' => $approval->clip_path,
            'preview_url' => $previewUrl,
            'status' => $approval->status,
            'scheduled_at' => optional($approval->scheduled_at)->toIso8601String(),
            'scheduled_day' => optional($approval->scheduled_at)->format('l'),
            'scheduled_date_human' => optional($approval->scheduled_at)->format('j M Y'),
            'scheduled_time' => optional($approval->scheduled_at)->format('H:i'),
            'auto_approve_at' => optional($approval->scheduled_at?->subMinutes(
                (int) config('pipeline.clip_approval.auto_approve_minutes_before_post', 10)
            ))->toIso8601String(),
            'approved_at' => optional($approval->approved_at)->toIso8601String(),
            'approved_via_email' => (bool) $approval->approved_via_email,
            'rejection_reason' => $approval->rejection_reason,
        ];
    }
}
