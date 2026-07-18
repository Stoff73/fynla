<?php

declare(strict_types=1);

namespace App\Services\Billing\Apple;

use App\Data\Billing\Apple\VerifiedAppleTransaction;
use App\Exceptions\Billing\AppleVerificationException;
use App\Models\PremiumEntitlement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;

final class AppleEntitlementProjector
{
    public function project(
        User $user,
        VerifiedAppleTransaction $transaction,
    ): PremiumEntitlement {
        $this->validate($user, $transaction);

        $status = match (true) {
            $transaction->revocationDate !== null => PremiumEntitlement::STATUS_REVOKED,
            ! $transaction->expiresDate->isFuture() => PremiumEntitlement::STATUS_EXPIRED,
            default => PremiumEntitlement::STATUS_ACTIVE,
        };
        $now = now();

        PremiumEntitlement::query()->insertOrIgnore([
            'user_id' => $user->getKey(),
            'provider' => PremiumEntitlement::PROVIDER_APPLE,
            'provider_reference' => $transaction->originalTransactionId,
            'product_id' => $transaction->productId,
            'status' => $status,
            'will_renew' => $status === PremiumEntitlement::STATUS_ACTIVE,
            'period_start' => $this->databaseDate($transaction->purchaseDate),
            'period_end' => $this->databaseDate($transaction->expiresDate),
            'grace_period_ends_at' => null,
            'revoked_at' => $this->databaseDate($transaction->revocationDate),
            'last_verified_at' => $this->databaseDate($transaction->signedDate),
            'provider_metadata' => json_encode(
                $this->metadata($transaction),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
            ),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $entitlement = PremiumEntitlement::query()
            ->where('provider', PremiumEntitlement::PROVIDER_APPLE)
            ->where('provider_reference', $transaction->originalTransactionId)
            ->lockForUpdate()
            ->first();

        if (
            ! $entitlement instanceof PremiumEntitlement
            || (string) $entitlement->user_id !== (string) $user->getKey()
        ) {
            throw new AuthorizationException('Apple entitlement ownership mismatch.');
        }

        if (
            $entitlement->last_verified_at !== null
            && $entitlement->last_verified_at->greaterThan($transaction->signedDate)
        ) {
            return $entitlement;
        }

        $entitlement->forceFill([
            'product_id' => $transaction->productId,
            'status' => $status,
            'will_renew' => $status === PremiumEntitlement::STATUS_ACTIVE,
            'period_start' => $this->databaseDate($transaction->purchaseDate),
            'period_end' => $this->databaseDate($transaction->expiresDate),
            'grace_period_ends_at' => null,
            'revoked_at' => $this->databaseDate($transaction->revocationDate),
            'last_verified_at' => $this->databaseDate($transaction->signedDate),
            'provider_metadata' => $this->metadata($transaction),
        ])->save();

        return $entitlement->fresh();
    }

    private function validate(
        User $user,
        VerifiedAppleTransaction $transaction,
    ): void {
        $products = config('apple_store.allowed_product_ids');
        $environment = config('apple_store.environment');
        $bundleId = config('apple_store.bundle_id');
        $userToken = $user->apple_app_account_token;

        if (
            $user->is_preview_user
            || ! is_string($userToken)
            || $userToken === ''
            || $transaction->appAccountToken !== $userToken
        ) {
            throw new AuthorizationException('Apple transaction ownership mismatch.');
        }

        if (
            ! is_array($products)
            || ! in_array($transaction->productId, $products, true)
            || $transaction->environment !== $environment
            || $transaction->bundleId !== $bundleId
            || $transaction->purchaseDate === null
            || $transaction->expiresDate === null
            || $transaction->signedDate === null
            || $transaction->expiresDate->lessThan($transaction->purchaseDate)
            || $transaction->signedDate->lessThan($transaction->purchaseDate)
            || ($transaction->revocationDate !== null
                && ($transaction->revocationDate->lessThan($transaction->purchaseDate)
                    || $transaction->revocationDate->greaterThan($transaction->signedDate)))
            || ! $this->fits($transaction->transactionId, 191)
            || ! $this->fits($transaction->originalTransactionId, 191)
            || ! $this->fits($transaction->productId, 100)
            || ! $this->fitsNullable($transaction->ownershipType, 32)
            || ! $this->fitsNullable($transaction->transactionReason, 32)
        ) {
            throw new AppleVerificationException('invalid_signed_data');
        }
    }

    /** @return array<string, string|null> */
    private function metadata(VerifiedAppleTransaction $transaction): array
    {
        return [
            'environment' => $transaction->environment,
            'last_transaction_id' => $transaction->transactionId,
            'ownership_type' => $transaction->ownershipType,
            'transaction_reason' => $transaction->transactionReason,
        ];
    }

    private function fits(string $value, int $maximumBytes): bool
    {
        return $value !== ''
            && trim($value) === $value
            && strlen($value) <= $maximumBytes;
    }

    private function fitsNullable(?string $value, int $maximumBytes): bool
    {
        return $value === null || $this->fits($value, $maximumBytes);
    }

    private function databaseDate(?CarbonImmutable $date): ?CarbonImmutable
    {
        return $date?->setTimezone((string) config('app.timezone'));
    }
}
