<?php

declare(strict_types=1);

namespace App\Services\Tax\Strategies;

use App\DataTransferObjects\StrategyRecommendation;
use App\Enums\StrategyCategory;
use App\Models\Investment\InvestmentAccount;
use App\Services\Tax\Strategies\Contract\TaxStrategy;
use App\Services\Tax\TaxStrategyMath;
use App\Services\TaxConfigService;

/**
 * Asset-shifting strategies for couples: savings-to-spouse for every couple,
 * and, for a spouse who does not work, an ISA top-up in their name and
 * GIA-to-spouse for their Capital Gains Tax and dividend allowances. Marriage Allowance lives in MarriageAllowanceStrategy. Each emitted suggestion
 * surfaces as a separate recommendation card on the dashboard.
 */
final class AssetShiftingBundleStrategy implements TaxStrategy
{
    public function __construct(
        private readonly TaxStrategyMath $math,
        private readonly TaxConfigService $taxConfig,
    ) {}

    public function generate(TaxStrategyContext $context): array
    {
        if (! $this->math->isMarriedOrCivilPartner($context->user)) {
            return [];
        }

        $user = $context->user;
        $household = $context->household;
        $suggestions = [];
        $isaAmount = (float) $this->taxConfig->getISAAllowances()['annual_allowance'];
        $savings = $this->math->soleNonIsaSavings($user);
        $userSavingsTotal = $savings['balance'];
        $annualInterest = $savings['interest'];

        // 2. Savings → spouse, for every couple (CSJ 2026-09-30, TODO item 4):
        // the amount that saves the household the most, priced on both
        // partners' whole income (TaxStrategyMath::savingsMoveToPartner). It
        // waits when the spouse's income or savings are not known.
        $move = $this->savingsMove($context, $savings);
        if ($move !== null) {
            $suggestions[] = $move;
        }

        // The rest of the bundle is for a spouse who does not work, whose
        // ISA and investments the campaign asks about.
        if ($context->mode !== 'single_earner_couple') {
            return array_map(
                fn (array $arr) => StrategyRecommendation::fromArray(StrategyCategory::Household, $arr),
                $suggestions,
            );
        }

        $hasGia = InvestmentAccount::query()
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('joint_owner_id', $user->id))
            ->where(function ($q) {
                $q->whereNull('account_type')->orWhere('account_type', '!=', 'isa');
            })
            ->exists();

        // 3. ISA top-up in spouse's name — uses fresh £20k allowance. Funding
        // a spouse's ISA needs money to fund it with (ruling a).
        // It saves tax only when the user's own savings interest is taxed now
        // (priced by the tax engine) or they hold investments outside an ISA;
        // a couple with no income was shown it at £0 (ice-cube, PR 991).
        $userInterestTaxed = floor($this->math->interestRemovalSaving($user, $annualInterest, 0.0, $context->pensionPaidElsewhere)) >= 1;
        $hasFundsToGift = ($userSavingsTotal > 0 && $userInterestTaxed) || $hasGia;
        $spouseIsaBalance = $household?->spouse_existing_isa_balance;
        if ($hasFundsToGift && $spouseIsaBalance !== null && (float) $spouseIsaBalance === 0.0) {
            $suggestions[] = [
                'type' => 'isa_topup_spouse',
                'priority' => 'medium',
                'title' => "Open or top up an ISA in your spouse's name",
                'description' => 'They have no ISA balance on file and may have up to £'.number_format((int) $isaAmount).' of ISA allowance available this tax year. Confirm any subscriptions made elsewhere before contributing.',
                'available_allowance' => round($isaAmount, 2),
            ];
        }

        // 4. GIA → spouse for CGT + Dividend allowances (only if user has investment accounts)
        if ($hasGia) {
            $cgtAllowance = (float) ($this->taxConfig->getCapitalGainsTax()['annual_exempt_amount']);
            $div = $this->taxConfig->getDividendTax();
            $divAllowanceRaw = $div['allowance'];
            $divAllowance = is_array($divAllowanceRaw)
                ? (float) $divAllowanceRaw['amount']
                : (float) $divAllowanceRaw;

            $suggestions[] = [
                'type' => 'gia_to_spouse',
                'priority' => 'medium',
                'title' => 'Hold non-ISA investments in your spouse\'s name',
                'description' => sprintf(
                    'Your spouse may have allowance available against up to £%s of net gains and £%s of dividends. Confirm their gains, losses and dividends for this tax year before relying on either amount. A transfer between eligible spouses or civil partners can usually be made without an immediate Capital Gains Tax charge, but they inherit the original acquisition cost and may pay tax on a later disposal.',
                    number_format((int) $cgtAllowance),
                    number_format((int) $divAllowance),
                ),
                'requires_advice' => true,
            ];
        }

        return array_map(
            fn (array $arr) => StrategyRecommendation::fromArray(StrategyCategory::Household, $arr),
            $suggestions,
        );
    }

    /**
     * Gift savings to the spouse: the amount that saves most, rounded down to
     * £100 of savings and re-priced at that amount, so the card's two figures
     * agree. Not offered for £1,000 or less of savings, or under £1 a year.
     *
     * @param  array{balance: float, interest: float}  $savings
     * @return array<string, mixed>|null
     */
    private function savingsMove(TaxStrategyContext $context, array $savings): ?array
    {
        if ($savings['balance'] <= 0 || $savings['interest'] <= 0) {
            return null;
        }
        $user = $context->user;
        $best = $this->math->savingsMoveToPartner($user, $context->mode, $context->household, $savings['interest'], $context->pensionPaidElsewhere);
        if ($best === null) {
            return null;
        }
        // Sized from the highest-rate savings first, then rounded down to £100
        // (the epsilon absorbs float noise: £2,250 at 4.5% is £50,000).
        $transfer = min($savings['balance'], floor($this->balanceEarning($savings['accounts'], $best['interest_moved']) / 100 + 1e-9) * 100);
        if ($transfer <= 1000) {
            return null;
        }
        $move = $this->math->savingsMoveToPartner($user, $context->mode, $context->household, $savings['interest'], $context->pensionPaidElsewhere, 0.0, $this->interestOn($savings['accounts'], $transfer));
        if ($move === null || floor($move['saving']) < 1) {
            return null;
        }
        $saving = floor($move['saving']);

        return [
            'type' => 'savings_to_spouse',
            'priority' => 'high',
            'title' => sprintf(
                'Gift £%s of savings to your spouse and save £%s in tax a year',
                number_format((int) $transfer),
                number_format((int) $saving),
            ),
            'description' => sprintf(
                'Interest on savings you give your spouse outright is theirs for tax. Moving £%s moves about £%s of interest a year to them: you pay about £%s less tax on it, and %s. A cash gift between eligible spouses or civil partners normally has no immediate Capital Gains Tax charge and may qualify for Inheritance Tax spouse exemption; ownership changes and conditions apply.',
                number_format((int) $transfer),
                // Down, as the how-to's figures are (ActionHowToFacts::pounds).
                number_format((int) floor($move['interest_moved'])),
                number_format((int) floor($move['user_tax_saved'])),
                $move['partner_extra_tax'] >= 0.01
                    ? 'they pay about £'.number_format((int) ceil($move['partner_extra_tax'])).' more at their own rates'
                    : 'they pay no tax on it',
            ),
            'suggested_transfer_amount' => $transfer,
            'estimated_annual_tax_saved' => $saving,
            'annual_interest_moved' => $move['interest_moved'],
            // All the moved interest leaves the user's income, including the
            // slice their own Savings Allowance covered: that slice still
            // counted towards adjusted net income (ITA 2007 s58).
            'interest_removed_from_income' => $move['interest_moved'],
            'pension_paid_first' => round($context->pensionPaidElsewhere, 2),
            'user_tax_saved' => $move['user_tax_saved'],
            'partner_extra_tax' => $move['partner_extra_tax'],
            'requires_advice' => true,
        ];
    }

    /**
     * The savings, taken from the highest rate down, that earn $interest a year.
     *
     * @param  list<array{balance: float, rate: float}>  $accounts
     */
    private function balanceEarning(array $accounts, float $interest): float
    {
        $balance = 0.0;
        foreach ($accounts as $account) {
            if ($interest <= 0 || $account['rate'] <= 0) {
                break;
            }
            $take = min($account['balance'], $interest / $account['rate']);
            $balance += $take;
            $interest -= $take * $account['rate'];
        }

        return $balance;
    }

    /**
     * The interest on $balance taken from the highest rate down.
     *
     * @param  list<array{balance: float, rate: float}>  $accounts
     */
    private function interestOn(array $accounts, float $balance): float
    {
        $interest = 0.0;
        foreach ($accounts as $account) {
            $take = min($account['balance'], max(0.0, $balance));
            $interest += $take * $account['rate'];
            $balance -= $take;
        }

        return $interest;
    }
}
