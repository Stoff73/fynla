<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\News\NewsSubscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsSubscriberController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:admin.access');
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'nullable|in:all,confirmed,pending,unsubscribed',
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);

        $status = $validated['status'] ?? 'all';
        $perPage = $validated['per_page'] ?? 50;

        $query = NewsSubscriber::query();

        match ($status) {
            'confirmed' => $query->confirmed(),
            'pending' => $query->pending(),
            'unsubscribed' => $query->unsubscribed(),
            default => null,
        };

        if (! empty($validated['search'])) {
            $query->where('email', 'like', '%'.$validated['search'].'%');
        }

        $paginated = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'data' => $paginated->getCollection()->map(fn ($s) => [
                'id' => $s->id,
                'email' => $s->email,
                'status' => $this->statusOf($s),
                'source' => $s->source,
                'ip_address' => $s->ip_address,
                'created_at' => $s->created_at?->toIso8601String(),
                'confirmed_at' => $s->confirmed_at?->toIso8601String(),
                'unsubscribed_at' => $s->unsubscribed_at?->toIso8601String(),
            ])->values(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    private function statusOf(NewsSubscriber $s): string
    {
        if ($s->unsubscribed_at !== null) {
            return 'unsubscribed';
        }

        return $s->confirmed_at !== null ? 'confirmed' : 'pending';
    }
}
