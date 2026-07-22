<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Native\StoreKit;

use App\Data\Billing\ResolvedEntitlement;
use App\Exceptions\Billing\AppleVerificationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Native\StoreKit\StoreAppleTransactionRequest;
use App\Models\User;
use App\Services\Billing\Apple\AppleSignedDataVerifier;
use App\Services\Billing\Apple\AppleTransactionStore;
use App\Services\Billing\PremiumEntitlementResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

final class AppleTransactionController extends Controller
{
    public function __construct(
        private readonly AppleSignedDataVerifier $verifier,
        private readonly AppleTransactionStore $transactions,
        private readonly PremiumEntitlementResolver $entitlements,
    ) {}

    public function __invoke(StoreAppleTransactionRequest $request): JsonResponse
    {
        $user = $request->user();

        if (
            ! $user instanceof User
            || $user->is_preview_user
            || ! is_string($user->apple_app_account_token)
            || $user->apple_app_account_token === ''
        ) {
            return $this->forbidden();
        }

        $signedTransaction = $request->validated('signed_transaction');

        try {
            $verified = $this->verifier->verifyTransaction(
                $signedTransaction,
                (string) config('apple_store.environment'),
                $user->apple_app_account_token,
            );
            $stored = $this->transactions->store(
                $user,
                $verified,
                $signedTransaction,
            );
        } catch (AuthorizationException) {
            return $this->forbidden();
        } catch (AppleVerificationException $exception) {
            return $this->verificationFailure($exception);
        }

        $this->entitlements->invalidate($user);
        $resolved = $this->entitlements->resolve($user->fresh());

        return response()->json([
            'success' => true,
            'data' => [
                'acknowledged' => true,
                'transaction_id' => $stored->transaction_id,
                'entitlement' => $this->entitlementResponse($resolved),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function entitlementResponse(ResolvedEntitlement $entitlement): array
    {
        return [
            'tier' => $entitlement->tier,
            'provider' => $entitlement->provider,
            'status' => $entitlement->status,
            'renews' => $entitlement->renews,
            'current_period_end' => $entitlement->periodEndsAt?->toISOString(),
        ];
    }

    private function forbidden(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => 'apple_transaction_forbidden',
            'message' => 'The verified StoreKit transaction is not associated with this account.',
        ], 403);
    }

    private function verificationFailure(
        AppleVerificationException $exception,
    ): JsonResponse {
        if (
            $exception->retryable
            || $exception->errorCode === 'invalid_configuration'
        ) {
            return response()->json([
                'success' => false,
                'error' => 'apple_verifier_unavailable',
                'message' => 'StoreKit verification is temporarily unavailable.',
            ], 503);
        }

        return response()->json([
            'success' => false,
            'error' => 'apple_transaction_invalid',
            'message' => 'The StoreKit transaction could not be verified.',
        ], 422);
    }
}
