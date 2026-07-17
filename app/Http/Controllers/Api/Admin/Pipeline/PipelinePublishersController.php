<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Pipeline;

use App\Http\Controllers\Controller;
use App\Models\Pipeline\PublisherUser;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin UI for managing the pipeline_publisher_users whitelist. Any admin
 * can grant / revoke pipeline.publish-to-live to any user. Whitelist
 * existence is checked by PipelineArticlesController::assertCanPushToLive.
 */
class PipelinePublishersController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:admin.access');
    }

    public function index(): JsonResponse
    {
        $rows = PublisherUser::with(['user:id,name,email', 'grantedBy:id,name,email'])
            ->orderByDesc('granted_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id'), Rule::unique('pipeline_publisher_users', 'user_id')],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $row = PublisherUser::create([
            'user_id' => $validated['user_id'],
            'granted_by' => $request->user()->id,
            'granted_at' => now(),
            'note' => $validated['note'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data' => $row->load(['user:id,name,email', 'grantedBy:id,name,email']),
        ], 201);
    }

    public function destroy(int $userId): JsonResponse
    {
        PublisherUser::where('user_id', $userId)->delete();

        return response()->json(['success' => true]);
    }

    public function searchUsers(Request $request): JsonResponse
    {
        $term = (string) $request->input('q', '');
        if (strlen($term) < 2) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $users = User::where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%");
        })
            ->limit(15)
            ->get(['id', 'name', 'email']);

        return response()->json(['success' => true, 'data' => $users]);
    }
}
