<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Data\Billing\ResolvedEntitlement;
use App\Models\PremiumEntitlement;
use App\Models\Subscription;
use App\Models\User;

final class PremiumEntitlementResolver
{
    /** @var array<int, ResolvedEntitlement> */
    private array $memo = [];

    /** @var array<int, Subscription|null> */
    private array $selectedRevolutMemo = [];

    public function __construct(
        private readonly RevolutEntitlementAdapter $revolut,
    ) {}

    public function resolve(User $user): ResolvedEntitlement
    {
        $userId = $user->getKey();

        if (is_int($userId) && isset($this->memo[$userId])) {
            return $this->memo[$userId];
        }

        if ($user->is_preview_user) {
            $resolved = $this->free();
            $selectedRevolut = null;
        } else {
            [$resolved, $selectedRevolut] = $this->resolveLiveProviders($user);
        }

        if (is_int($userId)) {
            $this->memo[$userId] = $resolved;
            $this->selectedRevolutMemo[$userId] = $selectedRevolut;
        }

        return $resolved;
    }

    public function invalidate(User|int $user): void
    {
        $userId = $user instanceof User ? $user->getKey() : $user;

        if (is_int($userId)) {
            unset($this->memo[$userId], $this->selectedRevolutMemo[$userId]);
        }
    }

    public function selectedRevolutSubscriptionFor(User $user): ?Subscription
    {
        $userId = $user->getKey();
        $resolved = $this->resolve($user);

        if (! is_int($userId) || $resolved->provider !== 'revolut') {
            return null;
        }

        return $this->selectedRevolutMemo[$userId] ?? null;
    }

    /** @return array{ResolvedEntitlement, Subscription|null} */
    private function resolveLiveProviders(User $user): array
    {
        $candidates = array_merge(
            $this->revolut->liveCandidatesFor($user),
            array_map(fn (ResolvedEntitlement $entitlement): array => [
                'entitlement' => $entitlement,
                'subscription' => null,
            ], $this->liveAppleEntitlementsFor($user)),
        );

        if ($candidates === []) {
            return [$this->free(), null];
        }

        usort(
            $candidates,
            fn (array $left, array $right): int => $this->compare(
                $left['entitlement'],
                $right['entitlement'],
            ),
        );

        $resolved = $candidates[0]['entitlement'];
        $selectedRevolut = null;

        if ($resolved->provider === 'revolut') {
            $canonicalMatches = array_values(array_filter(
                $candidates,
                fn (array $candidate): bool => $candidate['subscription'] instanceof Subscription
                    && $this->compare($candidate['entitlement'], $resolved) === 0,
            ));

            if (count($canonicalMatches) === 1) {
                $selectedRevolut = $canonicalMatches[0]['subscription'];
            }
        }

        return [$resolved, $selectedRevolut];
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

        if ($provider !== 0) {
            return $provider;
        }

        $status = strcmp($left->status, $right->status);

        return $status !== 0 ? $status : $right->renews <=> $left->renews;
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
