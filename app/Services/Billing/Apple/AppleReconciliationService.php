<?php

declare(strict_types=1);

namespace App\Services\Billing\Apple;

use App\Data\Billing\Apple\VerifiedAppleRenewal;
use App\Data\Billing\ResolvedEntitlement;
use App\Exceptions\Billing\AppleVerificationException;
use App\Models\User;
use App\Services\Billing\PremiumEntitlementResolver;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class AppleReconciliationService
{
    public function __construct(
        private readonly AppleStoreServerClient $client,
        private readonly AppleTransactionStore $transactions,
        private readonly AppleEntitlementProjector $entitlements,
        private readonly PremiumEntitlementResolver $resolver,
    ) {}

    public function reconcile(
        User $user,
        string $originalTransactionId,
    ): ResolvedEntitlement {
        $token = $user->apple_app_account_token;
        if (
            $user->is_preview_user
            || ! is_string($token)
            || preg_match(
                '/\A[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/D',
                $token,
            ) !== 1
        ) {
            throw new AuthorizationException('Apple reconciliation is unavailable for this account.');
        }
        if (
            $originalTransactionId === ''
            || trim($originalTransactionId) !== $originalTransactionId
            || strlen($originalTransactionId) > 191
        ) {
            throw new AppleVerificationException('invalid_signed_data');
        }

        $batch = $this->client->reconcile($originalTransactionId, $token);
        if (
            $batch->originalTransactionId !== $originalTransactionId
            || $batch->transactions === []
        ) {
            throw new AppleVerificationException('invalid_signed_data');
        }

        DB::transaction(function () use (
            $batch,
            $originalTransactionId,
            $token,
            $user,
        ): void {
            $reconciledAt = CarbonImmutable::now();
            $transactionEvidence = $batch->transactions;
            usort($transactionEvidence, fn ($left, $right): int => ($left->transaction->signedDate?->getTimestampMs() ?? 0)
                <=> ($right->transaction->signedDate?->getTimestampMs() ?? 0));

            foreach ($transactionEvidence as $evidence) {
                if (
                    $evidence->transaction->originalTransactionId !== $originalTransactionId
                    || $evidence->transaction->appAccountToken !== $token
                    || ! $this->isSha256($evidence->signedPayloadSha256)
                ) {
                    throw new AppleVerificationException('invalid_signed_data');
                }

                $stored = $this->transactions->storeVerifiedEvidence(
                    $user,
                    $evidence->transaction,
                    $evidence->signedPayloadSha256,
                    $reconciledAt,
                );
                $stored->forceFill(['reconciled_at' => $reconciledAt])->save();
            }

            $renewalEvidence = $batch->renewals;
            usort($renewalEvidence, fn ($left, $right): int => ($left->renewal->signedDate?->getTimestampMs() ?? 0)
                <=> ($right->renewal->signedDate?->getTimestampMs() ?? 0));

            foreach ($renewalEvidence as $evidence) {
                if (
                    $evidence->renewal->originalTransactionId !== $originalTransactionId
                    || ! $this->isSha256($evidence->signedPayloadSha256)
                ) {
                    throw new AppleVerificationException('invalid_signed_data');
                }

                [$type, $subtype] = $this->renewalTransition($evidence->renewal);
                $this->entitlements->projectRenewal(
                    $user,
                    $evidence->renewal,
                    $type,
                    $subtype,
                );
            }
        }, 3);

        $this->resolver->invalidate($user);

        return $this->resolver->resolve($user->fresh());
    }

    /** @return array{string, string|null} */
    private function renewalTransition(VerifiedAppleRenewal $renewal): array
    {
        if (
            $renewal->gracePeriodExpiresDate !== null
            && $renewal->gracePeriodExpiresDate->isFuture()
        ) {
            return ['DID_FAIL_TO_RENEW', 'GRACE_PERIOD'];
        }
        if ($renewal->isInBillingRetryPeriod === true) {
            return ['DID_FAIL_TO_RENEW', null];
        }
        if ($renewal->autoRenewStatus === 0) {
            return ['DID_CHANGE_RENEWAL_STATUS', 'AUTO_RENEW_DISABLED'];
        }

        return ['DID_RENEW', null];
    }

    private function isSha256(string $value): bool
    {
        return preg_match('/\A[0-9a-f]{64}\z/D', $value) === 1;
    }
}
