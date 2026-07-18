<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Data\Billing\ResolvedEntitlement;
use App\Models\PremiumEntitlement;
use App\Models\User;

final class PremiumEntitlementResolver
{
    /** @var array<int, ResolvedEntitlement> */
    private array $memo = [];

    public function __construct(
        private readonly RevolutEntitlementAdapter $revolut,
    ) {}

    public function resolve(User $user): ResolvedEntitlement
    {
        $userId = $user->getKey();

        if (is_int($userId) && isset($this->memo[$userId])) {
            return $this->memo[$userId];
        }

        $resolved = $user->is_preview_user
            ? $this->free()
            : $this->resolveLiveProviders($user);

        if (is_int($userId)) {
            $this->memo[$userId] = $resolved;
        }

        return $resolved;
    }

    public function invalidate(User|int $user): void
    {
        $userId = $user instanceof User ? $user->getKey() : $user;

        if (is_int($userId)) {
            unset($this->memo[$userId]);
        }
    }

    private function resolveLiveProviders(User $user): ResolvedEntitlement
    {
        $candidates = array_merge(
            $this->revolut->liveEntitlementsFor($user),
            $this->liveAppleEntitlementsFor($user),
        );

        if ($candidates === []) {
            return $this->free();
        }

        usort($candidates, fn (ResolvedEntitlement $left, ResolvedEntitlement $right): int => $this->compare($left, $right));

        return $candidates[0];
    }

    /** @return list<ResolvedEntitlement> */
    private function liveAppleEntitlementsFor(User $user): array
    {
        return $user->premiumEntitlements()
            ->where('provider', PremiumEntitlement::PROVIDER_APPLE)
            ->get()
            ->map(fn (PremiumEntitlement $entitlement): ?ResolvedEntitlement => $this->mapLiveApple($entitlement))
            ->filter()
            ->values()
            ->all();
    }

    private function mapLiveApple(PremiumEntitlement $entitlement): ?ResolvedEntitlement
    {
        if ($entitlement->revoked_at !== null
            || in_array($entitlement->status, [
                PremiumEntitlement::STATUS_EXPIRED,
                PremiumEntitlement::STATUS_REVOKED,
            ], true)) {
            return null;
        }

        $accessEndsAt = match ($entitlement->status) {
            PremiumEntitlement::STATUS_GRACE_PERIOD,
            PremiumEntitlement::STATUS_BILLING_RETRY => $entitlement->grace_period_ends_at,
            PremiumEntitlement::STATUS_ACTIVE,
            PremiumEntitlement::STATUS_CANCELLED => $entitlement->period_end,
            default => null,
        };

        if ($accessEndsAt === null || ! $accessEndsAt->isFuture()) {
            return null;
        }

        return new ResolvedEntitlement(
            tier: 'premium',
            provider: 'apple',
            status: $entitlement->status,
            renews: (bool) $entitlement->will_renew,
            periodEndsAt: $accessEndsAt->toImmutable(),
        );
    }

    private function compare(ResolvedEntitlement $left, ResolvedEntitlement $right): int
    {
        $leftEnd = $left->periodEndsAt?->getTimestamp();
        $rightEnd = $right->periodEndsAt?->getTimestamp();

        if ($leftEnd !== $rightEnd) {
            if ($leftEnd === null) {
                return 1;
            }
            if ($rightEnd === null) {
                return -1;
            }

            return $rightEnd <=> $leftEnd;
        }

        $provider = strcmp($left->provider ?? '', $right->provider ?? '');

        return $provider !== 0 ? $provider : strcmp($left->status, $right->status);
    }

    private function free(): ResolvedEntitlement
    {
        return new ResolvedEntitlement(
            tier: 'free',
            provider: null,
            status: 'free',
            renews: false,
            periodEndsAt: null,
        );
    }
}
