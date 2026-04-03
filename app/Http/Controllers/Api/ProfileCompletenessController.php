<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Services\UserProfile\ProfileCompletenessChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ProfileCompletenessController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private ProfileCompletenessChecker $completenessChecker
    ) {}

    /**
     * Check user profile completeness
     *
     * GET /api/user/profile/completeness
     */
    public function check(Request $request): JsonResponse
    {
        $user = $request->user();

        $completenessData = Cache::remember(
            "profile_completeness_{$user->id}",
            86400,
            fn () => $this->completenessChecker->checkCompleteness($user)
        );

        return response()->json([
            'success' => true,
            'data' => $completenessData,
        ]);
    }
}
