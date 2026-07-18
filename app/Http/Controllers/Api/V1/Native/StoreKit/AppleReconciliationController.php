<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Native\StoreKit;

use App\Data\Billing\ResolvedEntitlement;
use App\Exceptions\Billing\AppleVerificationException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Billing\Apple\AppleReconciliationService;
use App\Services\Billing\PremiumEntitlementResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AppleReconciliationController extends Controller
{
    public function __construct(
        private readonly AppleReconciliationService $reconciliation,
        private readonly PremiumEntitlementResolver $entitlements,
    ) {}

    public function reconcile(Request $request): JsonResponse
    {
        $user = $request->user();
        $payload = $request->json()->all();
        $originalTransactionId = $payload['original_transaction_id'] ?? null;
        if (
            ! $user instanceof User
            || $user->is_preview_user
            || count($payload) !== 1
            || ! is_string($originalTransactionId)
            || $originalTransactionId === ''
            || trim($originalTransactionId) !== $originalTransactionId
            || strlen($originalTransactionId) > 191
        ) {
            return ! $user instanceof User || $user->is_preview_user
                ? $this->forbiddenResponse()
                : $this->invalidResponse();
        }

        try {
            $resolved = $this->reconciliation->reconcile(
                $user,
                $originalTransactionId,
            );
        } catch (AuthorizationException) {
            return $this->forbiddenResponse();
        } catch (AppleVerificationException $exception) {
            return $exception->retryable
                || $exception->errorCode === 'invalid_configuration'
                ? $this->unavailableResponse()
                : $this->invalidResponse();
        }

        return $this->statusResponse($resolved);
    }

    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        if (
            ! $user instanceof User
            || $user->is_preview_user
            || ! is_string($user->apple_app_account_token)
            || $user->apple_app_account_token === ''
        ) {
            return $this->forbiddenResponse();
        }

        $this->entitlements->invalidate($user);

        return $this->statusResponse($this->entitlements->resolve($user->fresh()));
    }

    private function statusResponse(ResolvedEntitlement $entitlement): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'entitlement' => [
                    'tier' => $entitlement->tier,
                    'provider' => $entitlement->provider,
                    'status' => $entitlement->status,
                    'renews' => $entitlement->renews,
                    'current_period_end' => $entitlement->periodEndsAt?->toISOString(),
                ],
            ],
        ]);
    }

    private function forbiddenResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => 'apple_reconciliation_forbidden',
            'message' => 'App Store reconciliation is unavailable for this account.',
        ], 403);
    }

    private function invalidResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => 'apple_reconciliation_invalid',
            'message' => 'The App Store purchase history could not be verified.',
        ], 422);
    }

    private function unavailableResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => 'apple_reconciliation_unavailable',
            'message' => 'App Store reconciliation is temporarily unavailable.',
        ], 503);
    }
}
