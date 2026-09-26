<?php

declare(strict_types=1);

namespace App\Services\Plans;

use App\Models\Investment\InvestmentAccount;
use App\Models\User;
use App\Services\Stores\SavingsStore;
use App\Traits\CalculatesOwnershipShare;
use App\Traits\ResolvesExpenditure;

/**
 * The accounts a user could fund an action from — the ONE list. The plans pages
 * ("Fund from" on contribution actions), the goal funding source and the action
 * detail card all read it (it replaced three copies, one with 6 months
 * hardcoded and all three leaving out the easy-access accounts Fyn records).
 *
 * Liquid, non-ISA cash first, then general investment accounts. A joint account
 * counts at the user's own share (CLAUDE.md Rule 6). A cash account that would
 * leave the emergency fund short carries a warning; the target months come from
 * plan config.
 */
final class FundingAccounts
{
    use CalculatesOwnershipShare;
    use ResolvesExpenditure;

    /**
     * Instant-withdrawal cash. `easy_access` is what Fyn's capture writes for
     * "instant access" (CoordinatingAgent account-type map); both are the same
     * kind of account (SavingsAccount type labels).
     */
    public const CASH_ACCOUNT_TYPES = [
        'current_account',
        'instant_access',
        'easy_access',
        'business_current',
        'business_savings',
    ];

    public function __construct(private readonly PlanConfigService $planConfig) {}

    /**
     * @return list<array{id: int, type: string, name: string, balance: float, warning: string|null}>
     */
    public function eligibleFor(User $user): array
    {
        $months = $this->planConfig->getEmergencyFundTargetMonths();
        $threshold = (float) $this->resolveMonthlyExpenditure($user)['amount'] * $months;

        $cash = app(SavingsStore::class)->forUser($user)
            ->where('is_isa', false)
            ->whereIn('account_type', self::CASH_ACCOUNT_TYPES)
            ->map(fn ($account) => [
                'id' => (int) $account->id,
                'type' => 'savings',
                'name' => (string) ($account->account_name ?: $account->institution ?: 'Cash account'),
                'balance' => round($this->calculateUserShare($account, $user->id), 2),
                'monthly_saving' => (float) ($account->additional_monthly_savings ?? 0),
            ])
            ->filter(fn (array $a) => $a['balance'] > 0)
            ->sortByDesc('balance')
            ->map(fn (array $a) => [
                'id' => $a['id'],
                'type' => $a['type'],
                'name' => $a['name'],
                'balance' => $a['balance'],
                'warning' => $threshold > $a['balance'] - $a['monthly_saving'] * 12
                    ? "Withdrawing would reduce your emergency fund below {$months} months of expenditure."
                    : null,
            ]);

        $gia = InvestmentAccount::where(fn ($q) => $q->where('user_id', $user->id)->orWhere('joint_owner_id', $user->id))
            ->where('account_type', 'gia')
            ->get()
            ->map(fn (InvestmentAccount $account) => [
                'id' => (int) $account->id,
                'type' => 'investment',
                'name' => (string) ($account->account_name ?: $account->provider ?: 'General Investment Account'),
                'balance' => round($this->calculateUserShare($account, $user->id), 2),
                'warning' => 'Using this account may trigger a Capital Gains Tax event.',
            ])
            ->filter(fn (array $a) => $a['balance'] > 0)
            ->sortByDesc('balance');

        return array_values([...$cash->values()->all(), ...$gia->values()->all()]);
    }

    /**
     * Safe cash first, then cash with a warning, then an investment account.
     *
     * @param  list<array{id: int, type: string, name: string, balance: float, warning: string|null}>  $accounts
     * @return array{id: int, type: string, name: string, balance: float, warning: string|null}|null
     */
    public function recommend(array $accounts): ?array
    {
        return collect($accounts)->first(fn ($a) => $a['type'] === 'savings' && $a['warning'] === null)
            ?? collect($accounts)->first(fn ($a) => $a['type'] === 'savings')
            ?? collect($accounts)->first();
    }
}
