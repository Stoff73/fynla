<?php

declare(strict_types=1);

namespace App\Services\Tax;

use App\Models\User;
use App\Services\Coordination\CompositePlanService;
use App\Services\Shared\CrossModuleAssetAggregator;
use App\Services\UserProfile\UserProfileService;

/**
 * The money a user can put into pensions this year: the one affordability
 * figure every pension suggestion is capped by (CSJ 2026-09-25: "what use is
 * knowing a limit you neither care about or can afford?"; 2026-09-30:
 * "affordability check always", and the user's own card capped by the same
 * money). Spec: docs/superpowers/specs/2026-09-30-partner-pension-affordability-design.md
 *
 * - Someone who earns: a year of the surplus the affordability calculator
 *   reports (CompositePlanService::financials, net income less spending,
 *   committed contributions and goal commitments). Only once spending is
 *   recorded: without it, net income would all count as spare (CSJ
 *   2026-09-30: "We ask for expenditure").
 * - Someone with no income at all to pay from (no earnings, pension, rent
 *   or dividends): their recorded cash (CSJ 2026-09-29, "a no-income
 *   arrival's pension is capped at their savings"). A retiree drawing a
 *   pension has income, and is funded from surplus like anyone else.
 *
 * Net money, what is actually paid; a relief-at-source payment is grossed up
 * by the provider (FA 2004 s192), so it buys money / (1 - basic rate) gross.
 */
class PensionAffordability
{
    public function __construct(
        private readonly TaxStrategyMath $math,
        private readonly CrossModuleAssetAggregator $assets,
    ) {}

    /** Net money for pension payments this year, or null when it cannot be known yet. */
    public function moneyThisYear(User $user): ?float
    {
        if ($this->fundedFromCash($user)) {
            return round(max(0.0, (float) $this->assets->calculateCashTotal($user->id)), 2);
        }
        if (! $this->spendingRecorded($user)) {
            return null;
        }

        // Resolved here, not injected: CompositePlanService and the profile
        // service sit above the tax plan they read.
        return round(max(0.0, 12 * (float) app(CompositePlanService::class)->financials($user)['effective_surplus']), 2);
    }

    /** No earnings and no other income to pay from: only savings can fund a payment. */
    public function fundedFromCash(User $user): bool
    {
        if (! $this->math->isDeclaredNonEarner($user)) {
            return false;
        }
        $parts = $this->math->incomePartsFor($user);

        return $parts['non_savings'] + $parts['dividends'] + $parts['trust'] <= 0;
    }

    /** The same test the profile uses for "expenditure recorded" (UserProfileService::expenditurePresentation). */
    public function spendingRecorded(User $user): bool
    {
        return (float) (app(UserProfileService::class)->getExpenditureBreakdown($user)['monthly_manual'] ?? 0) > 0;
    }
}
