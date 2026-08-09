<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Pipeline;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Pipeline\StorePipelineCampaignRequest;
use App\Http\Requests\Admin\Pipeline\UpdatePipelineCampaignRequest;
use App\Models\Pipeline\PipelineCampaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PipelineCampaignsController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:admin.access');
    }

    public function index(Request $request): JsonResponse
    {
        $query = PipelineCampaign::query()->orderByDesc('created_at');

        if ($request->boolean('active_only')) {
            $today = today();
            $query->where(function ($q) use ($today) {
                $q->whereNull('active_from')->orWhere('active_from', '<=', $today);
            })->where(function ($q) use ($today) {
                $q->whereNull('active_to')->orWhere('active_to', '>=', $today);
            });
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate(20),
        ]);
    }

    public function store(StorePipelineCampaignRequest $request): JsonResponse
    {
        $campaign = PipelineCampaign::create(
            $request->validated() + ['created_by' => $request->user()->id]
        );

        return response()->json([
            'success' => true,
            'data' => $campaign,
        ], 201);
    }

    public function show(PipelineCampaign $campaign): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $campaign->loadCount('posts'),
        ]);
    }

    public function update(UpdatePipelineCampaignRequest $request, PipelineCampaign $campaign): JsonResponse
    {
        $campaign->update($request->validated());

        return response()->json([
            'success' => true,
            'data' => $campaign->fresh(),
        ]);
    }

    public function destroy(PipelineCampaign $campaign): JsonResponse
    {
        $campaign->delete();

        return response()->json(['success' => true]);
    }
}
