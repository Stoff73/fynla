<?php

declare(strict_types=1);

namespace App\Services\Tax;

use App\Models\Investment\Holding;
use App\Models\Investment\InvestmentAccount;
use App\Models\User;
use App\Traits\CalculatesOwnershipShare;
use Illuminate\Support\Collection;

/**
 * The gains and losses a user would realise for capital gains tax by selling
 * investments today: the one home for Bed & ISA (Tax plan), the investment ISA
 * cards and the losses card (item 8, CSJ 2026-10-06).
 *
 * Only a General Investment Account counts: it holds listed investments whose
 * gains and losses are chargeable and which can be sold and bought back inside
 * an ISA. Every other account type is left out (tax review 2026-10-06):
 * - ISAs: no Capital Gains Tax (https://www.gov.uk/individual-savings-accounts);
 * - Venture Capital Trust shares: neither gains nor losses are chargeable
 *   (Taxation of Chargeable Gains Act 1992 s151A, within its limits);
 * - EIS and SEIS shares: gains exempt once the conditions are met, losses
 *   reduced by the relief kept (TCGA 1992 s150A, s150E);
 * - investment bonds: not a chargeable disposal for the original owner
 *   (TCGA 1992 s210); gains are chargeable event gains under income tax
 *   (Income Tax (Trading and Other Income) Act 2005 s461);
 * - trusts: the trustees' gains, not the user's (TCGA 1992 s69);
 * - private company and crowdfunding shares: not qualifying ISA investments
 *   (Individual Savings Account Regulations 1998, reg 7), so not a Bed & ISA;
 * - employee share schemes and options (SAYE, CSOP, EMI, unapproved options,
 *   RSUs): taxed as employment income on exercise or vesting (Income Tax
 *   (Earnings and Pensions) Act 2003 Part 7), and the recorded price is not a
 *   reliable CGT base cost (TCGA 1992 s17);
 * - National Savings & Investments and "other": their contents are not known.
 *
 * A joint account counts at the user's share (Rule 6): the other owner's half
 * of a gain or loss is theirs to realise.
 */
final class ChargeableGains
{
    use CalculatesOwnershipShare;

    /** The account types whose disposals count (see the class note). */
    public const CHARGEABLE_ACCOUNT_TYPES = ['gia'];

    /**
     * Each chargeable account the user owns or co-owns, with the user's share.
     *
     * @return array<int, float> account id => share fraction
     */
    public function accountSharesFor(User $user): array
    {
        return InvestmentAccount::query()
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('joint_owner_id', $user->id))
            ->whereIn('account_type', self::CHARGEABLE_ACCOUNT_TYPES)
            ->get()
            ->mapWithKeys(fn (InvestmentAccount $account) => [$account->id => $this->userShareFraction($account, $user->id)])
            ->all();
    }

    /**
     * Every chargeable holding with its value, cost and gain at the user's share.
     * A holding without a value or a cost is left out: no gain can be stated.
     *
     * @return Collection<int, array{holding: Holding, account_id: int, value: float, cost: float, gain: float}>
     */
    public function holdingsFor(User $user): Collection
    {
        $shareById = $this->accountSharesFor($user);
        if ($shareById === []) {
            return collect();
        }

        return Holding::query()
            ->where('holdable_type', InvestmentAccount::class)
            ->whereIn('holdable_id', array_keys($shareById))
            ->get()
            ->map(function (Holding $h) use ($shareById): ?array {
                $current = (float) ($h->current_value ?? 0);
                if ($current <= 0 && $h->quantity && $h->current_price) {
                    $current = (float) $h->quantity * (float) $h->current_price;
                }
                $cost = (float) ($h->cost_basis ?? 0);
                if ($cost <= 0 && $h->quantity && $h->purchase_price) {
                    $cost = (float) $h->quantity * (float) $h->purchase_price;
                }
                if ($current <= 0 || $cost <= 0) {
                    return null;
                }
                $share = (float) ($shareById[$h->holdable_id] ?? 0);

                return [
                    'holding' => $h,
                    'account_id' => (int) $h->holdable_id,
                    'value' => $current * $share,
                    'cost' => $cost * $share,
                    'gain' => ($current - $cost) * $share,
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * The user's unrealised gains, and the value of the holdings that carry them.
     *
     * @return array{gain: float, value_with_gain: float}
     */
    public function unrealisedGainsFor(User $user): array
    {
        $withGain = $this->holdingsFor($user)->filter(fn (array $row) => $row['gain'] > 0);

        return [
            'gain' => (float) $withGain->sum('gain'),
            'value_with_gain' => (float) $withGain->sum('value'),
        ];
    }
}
