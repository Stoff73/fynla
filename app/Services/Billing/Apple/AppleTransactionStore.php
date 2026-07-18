<?php

declare(strict_types=1);

namespace App\Services\Billing\Apple;

use App\Data\Billing\Apple\VerifiedAppleTransaction;
use App\Exceptions\Billing\AppleVerificationException;
use App\Models\AppleTransaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class AppleTransactionStore
{
    private const MAX_SIGNED_TRANSACTION_BYTES = 65_536;

    public function __construct(
        private readonly AppleEntitlementProjector $entitlements,
    ) {}

    public function store(
        User $user,
        VerifiedAppleTransaction $verified,
        string $signedTransaction,
    ): AppleTransaction {
        if (
            $signedTransaction === ''
            || strlen($signedTransaction) > self::MAX_SIGNED_TRANSACTION_BYTES
        ) {
            throw new AppleVerificationException('invalid_signed_data');
        }

        return $this->storeVerifiedEvidence(
            $user,
            $verified,
            hash('sha256', $signedTransaction),
        );
    }

    public function storeVerifiedEvidence(
        User $user,
        VerifiedAppleTransaction $verified,
        string $signedPayloadSha256,
        ?CarbonImmutable $reconciledAt = null,
    ): AppleTransaction {
        if (preg_match('/\A[0-9a-f]{64}\z/D', $signedPayloadSha256) !== 1) {
            throw new AppleVerificationException('invalid_signed_data');
        }

        return DB::transaction(function () use (
            $user,
            $verified,
            $signedPayloadSha256,
            $reconciledAt,
        ): AppleTransaction {
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->first();

            if (
                ! $lockedUser instanceof User
                || $lockedUser->is_preview_user
                || ! is_string($lockedUser->apple_app_account_token)
                || $lockedUser->apple_app_account_token === ''
                || $verified->appAccountToken !== $lockedUser->apple_app_account_token
            ) {
                throw new AuthorizationException('Apple transaction ownership mismatch.');
            }

            $entitlement = $this->entitlements->project($lockedUser, $verified);
            $now = now();

            AppleTransaction::query()->insertOrIgnore([
                'user_id' => $lockedUser->getKey(),
                'premium_entitlement_id' => $entitlement->getKey(),
                'transaction_id' => $verified->transactionId,
                'original_transaction_id' => $verified->originalTransactionId,
                'app_account_token' => $verified->appAccountToken,
                'product_id' => $verified->productId,
                'environment' => $verified->environment,
                'purchased_at' => $this->databaseDate($verified->purchaseDate),
                'expires_at' => $this->databaseDate($verified->expiresDate),
                'revoked_at' => $this->databaseDate($verified->revocationDate),
                'ownership_type' => $verified->ownershipType,
                'transaction_reason' => $verified->transactionReason,
                'signed_payload_sha256' => $signedPayloadSha256,
                'received_at' => $now,
                'reconciled_at' => $this->databaseDate($reconciledAt),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $stored = AppleTransaction::query()
                ->where('transaction_id', $verified->transactionId)
                ->lockForUpdate()
                ->first();

            if (
                ! $stored instanceof AppleTransaction
                || ! $this->matches($stored, $lockedUser, $entitlement->getKey(), $verified)
                || $stored->signed_payload_sha256 !== $signedPayloadSha256
            ) {
                throw new AuthorizationException('Apple transaction ownership mismatch.');
            }

            return $stored;
        }, 3);
    }

    private function matches(
        AppleTransaction $stored,
        User $user,
        mixed $entitlementId,
        VerifiedAppleTransaction $verified,
    ): bool {
        return (string) $stored->user_id === (string) $user->getKey()
            && (string) $stored->premium_entitlement_id === (string) $entitlementId
            && $stored->original_transaction_id === $verified->originalTransactionId
            && $stored->app_account_token === $verified->appAccountToken
            && $stored->product_id === $verified->productId
            && $stored->environment === $verified->environment
            && $stored->purchased_at?->equalTo($verified->purchaseDate) === true
            && $this->sameDate($stored->expires_at, $verified->expiresDate)
            && $this->sameDate($stored->revoked_at, $verified->revocationDate)
            && $stored->ownership_type === $verified->ownershipType
            && $stored->transaction_reason === $verified->transactionReason;
    }

    private function sameDate(mixed $stored, mixed $verified): bool
    {
        if ($stored === null || $verified === null) {
            return $stored === null && $verified === null;
        }

        return $stored->equalTo($verified);
    }

    private function databaseDate(?CarbonImmutable $date): ?CarbonImmutable
    {
        return $date?->setTimezone((string) config('app.timezone'));
    }
}
