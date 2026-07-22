<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Native\StoreKit;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Billing\AppAccountTokenService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AppAccountTokenController extends Controller
{
    public function __construct(private readonly AppAccountTokenService $tokens) {}

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User || $user->is_preview_user) {
            return $this->previewForbiddenResponse();
        }

        try {
            $token = $this->tokens->issue($user);
        } catch (AuthorizationException) {
            return $this->previewForbiddenResponse();
        }

        return response()->json([
            'success' => true,
            'data' => ['app_account_token' => $token],
        ]);
    }

    private function previewForbiddenResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => 'native_account_token_forbidden',
            'message' => 'StoreKit account tokens are unavailable for preview users.',
        ], 403);
    }
}
