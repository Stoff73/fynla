<?php

declare(strict_types=1);

namespace App\Services\Investment;

use App\Models\Investment\InvestmentAccount;
use App\Traits\CalculatesOwnershipShare;
use Illuminate\Support\Carbon;

/**
 * An investment bond's deferred tax position (item 8, CSJ 2026-10-06): the
 * gain building up inside it and the 5% a year that can still be taken with
 * the tax deferred. The one home for the bond card and its how-to.
 *
 * - Gain on the bond ending: what it is worth plus what has been taken, less
 *   what was paid in (Income Tax (Trading and Other Income) Act 2005 s491).
 * - The 5%: for each policy year begun, 5% of what was paid in, building up
 *   if unused, to no more than the full amount paid in (ITTOIA 2005 s507).
 *
 * A joint bond counts at the user's share (Rule 6).
 */
final class BondPositionService
{
    use CalculatesOwnershipShare;

    public const BOND_TYPES = ['onshore_bond', 'offshore_bond'];

    /** Each policy year begun adds this share of what was paid in (ITTOIA 2005 s507). */
    private const YEARLY_ALLOWANCE = 0.05;

    /**
     * @return array{account_id: int, account_name: string, is_offshore: bool, paid_in_known: bool,
     *     value: float, paid_in: float|null, withdrawn: float, gain: float|null,
     *     allowance_left: float|null, policy_years: int|null}
     */
    public function forAccount(InvestmentAccount $account, int $userId): array
    {
        $share = $this->userShareFraction($account, $userId);
        $value = (float) ($account->current_value ?? 0) * $share;
        $withdrawn = (float) ($account->bond_withdrawal_taken ?? 0) * $share;
        $paidIn = $account->investment_amount !== null && (float) $account->investment_amount > 0
            ? (float) $account->investment_amount * $share
            : null;

        $policyYears = null;
        $allowanceLeft = null;
        if ($paidIn !== null && $account->bond_purchase_date !== null) {
            $policyYears = (int) floor(Carbon::parse($account->bond_purchase_date)->diffInYears(now())) + 1;
            $built = min($paidIn, $paidIn * self::YEARLY_ALLOWANCE * $policyYears);
            $allowanceLeft = max(0.0, $built - $withdrawn);
        }

        return [
            'account_id' => (int) $account->id,
            'account_name' => (string) ($account->account_name ?? $account->provider ?? 'Your bond'),
            'is_offshore' => $account->account_type === 'offshore_bond',
            'paid_in_known' => $paidIn !== null,
            'value' => round($value, 2),
            'paid_in' => $paidIn === null ? null : round($paidIn, 2),
            'withdrawn' => round($withdrawn, 2),
            'gain' => $paidIn === null ? null : round($value + $withdrawn - $paidIn, 2),
            'allowance_left' => $allowanceLeft === null ? null : round($allowanceLeft, 2),
            'policy_years' => $policyYears,
        ];
    }
}
