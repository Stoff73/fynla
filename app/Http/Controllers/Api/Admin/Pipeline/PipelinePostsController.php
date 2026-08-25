<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Pipeline;

use App\Http\Controllers\Controller;
use App\Models\Pipeline\PipelineCampaign;
use App\Models\Pipeline\PipelinePost;
use App\Services\Pipeline\Social\UtmLinkBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin approval queue for Stage 4 posts. Backend for /admin/pipeline/posts.
 *
 * Reviewer actions:
 *   - GET index — list awaiting_approval posts grouped by article
 *   - PATCH update — edit caption, hashtags, destination
 *   - POST approve — transition to approved (and populate destination_url_final)
 *   - POST reject — transition to rejected with a reason
 *
 * Destination override rules (from social-media-posts skill):
 *   destination_type='article'  → default article URL with UTMs
 *   destination_type='custom'   → editor supplies a URL, UTMs re-computed
 *   destination_type='campaign' → editor picks a PipelineCampaign, UTMs
 *                                   use campaign->slug + landing_url
 */
class PipelinePostsController extends Controller
{
    public function __construct(private readonly UtmLinkBuilder $links)
    {
        $this->middleware('permission:admin.access');
    }

    public function index(Request $request): JsonResponse
    {
        $query = PipelinePost::query()
            ->with(['pipelineArticle.insightArticle', 'pipelineCampaign'])
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->input('platform'), fn ($q, $p) => $q->where('platform', $p))
            ->when($request->input('article_id'), fn ($q, $id) => $q->where('pipeline_article_id', $id))
            ->orderByDesc('created_at');

        return response()->json([
            'success' => true,
            'data' => $query->paginate(30),
        ]);
    }

    public function show(PipelinePost $post): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $post->load(['pipelineArticle.insightArticle', 'pipelineCampaign']),
        ]);
    }

    public function update(Request $request, PipelinePost $post): JsonResponse
    {
        $validated = $request->validate([
            'caption' => ['sometimes', 'string', 'max:2500'],
            'hashtags' => ['sometimes', 'array', 'min:2', 'max:5'],
            'hashtags.*' => ['string', 'regex:/^#[A-Za-z0-9_]{1,39}$/'],
            'destination_type' => ['sometimes', 'in:article,custom,campaign'],
            'destination_url_final' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'pipeline_campaign_id' => ['sometimes', 'nullable', 'exists:pipeline_campaigns,id'],
        ]);

        $post->update($validated);

        return response()->json([
            'success' => true,
            'data' => $post->fresh(),
        ]);
    }

    public function approve(Request $request, PipelinePost $post): JsonResponse
    {
        if ($post->status !== 'awaiting_approval') {
            return response()->json([
                'success' => false,
                'message' => 'Post is not awaiting approval (current status: '.$post->status.').',
            ], 422);
        }

        $article = $post->pipelineArticle->insightArticle;
        $finalUrl = $this->resolveFinalUrl($post, $article);

        $post->update([
            'destination_url_final' => $finalUrl,
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $post->fresh(),
        ]);
    }

    public function reject(Request $request, PipelinePost $post): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        if ($post->status !== 'awaiting_approval') {
            return response()->json([
                'success' => false,
                'message' => 'Post is not awaiting approval.',
            ], 422);
        }

        $post->update([
            'status' => 'rejected',
            'rejection_reason' => $validated['reason'],
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return response()->json(['success' => true, 'data' => $post->fresh()]);
    }

    private function resolveFinalUrl(PipelinePost $post, $article): string
    {
        return match ($post->destination_type) {
            'campaign' => $this->campaignUrl($post, $article),
            'custom' => $this->customUrl($post),
            default => $this->links->forArticle($article, $post->platform, $post->clip_index),
        };
    }

    private function campaignUrl(PipelinePost $post, $article): string
    {
        $campaign = $post->pipelineCampaign ?? PipelineCampaign::find($post->pipeline_campaign_id);
        if ($campaign === null) {
            return $this->links->forArticle($article, $post->platform, $post->clip_index);
        }

        return $this->links->forCampaign($campaign, $post->platform, $post->clip_index);
    }

    private function customUrl(PipelinePost $post): string
    {
        $url = $post->destination_url_final;
        if (! is_string($url) || $url === '') {
            $url = $post->destination_url_default;
        }

        $article = $post->pipelineArticle->insightArticle;

        return $this->links->forCustomUrl($url, $post->platform, $article->slug, $post->clip_index);
    }
}
