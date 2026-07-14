<?php

declare(strict_types=1);

namespace App\Services\Marketing;

use App\Services\Onboarding\FunnelIncomeBand;
use App\Services\TaxConfigService;
use LogicException;

/**
 * SaveTaxEstimateService — drives the dynamic numbers on the /savetax funnel
 * result page (savetax-plan).
 *
 * Given the funnel answers (income band, spouse, spouse income band, assets) it
 * returns two things:
 *   - "savings": the estimated annual tax saving, broken into line items.
 *   - "allowances": the tax-free / tax-relievable allowances available, with a total.
 *
 * Every tax value is read from TaxConfigService (Rule #2 — never hard-coded).
 *
 * Income is collected as a BAND, not a precise figure; per CSJ we assume the
 * UPPER bound of each band. Pension relief is computed with an exact income-tax
 * engine (Personal Allowance taper + basic/higher band extension + 20% relief at
 * source), so the £100k–£125,140 "60% trap" emerges naturally.
 *
 * The math model is documented in June/June8Updates/savetax-math-spec.md.
 */
class SaveTaxEstimateService
{
    /** Stable funnel keys; their monetary boundaries come from TaxConfigService. */
    private const BAND_KEYS = [
        'zero',
        'upto_50270',
        '50271_100000',
        '100001_125140',
        'over_125140',
    ];

    /** The income band that sits inside the £100k Personal Allowance taper. */
    private const TRAP_BAND = '100001_125140';

    public function __construct(private readonly TaxConfigService $taxConfig) {}

    /**
     * @param  array{income?:string,spouse?:string,spouseIncome?:?string,assets?:array<int,string>}  $answers
     * @return array<string,mixed>
     */
    public function estimate(array $answers): array
    {
        $incomeBand = $this->normaliseBand($answers['income'] ?? null, 'upto_50270');
        $married = ($answers['spouse'] ?? null) === 'yes';
        $spouseBand = $married ? $this->normaliseBand($answers['spouseIncome'] ?? null, null) : null;
        $assets = array_values(array_filter((array) ($answers['assets'] ?? [])));

        $income = $this->incomeForBand($incomeBand);
        $rate = $this->marginalRate($income);

        $has = fn (string ...$keys): bool => (bool) array_intersect($keys, $assets);
        $hasFinancial = $has('isa', 'savings', 'investments', 'bank');

        $savings = [];

        // --- Pension / 60% tax trap -----------------------------------------
        // Only when the user has no pension yet. In the trap band the
        // contribution clears income down to £100k (reclaiming the Personal
        // Allowance, ~60% effective relief) and is surfaced as its own "60% Tax
        // Trap" line; other bands assume 10% of upper income.
        $isTrap = $incomeBand === self::TRAP_BAND;
        if (! $has('pension')) {
            $contribution = $isTrap
                ? max(0, $income - $this->taperThreshold())
                : (int) round($income * 0.10);

            if ($contribution > 0) {
                $relief = (int) round($this->pensionRelief($income, $contribution));
                $savings[] = [
                    'key' => $isTrap ? 'tax_trap_60' : 'pension',
                    'label' => $isTrap ? '60% Tax Trap' : 'Pension contribution',
                    'amount' => $relief,
                    'reason' => $isTrap
                        ? "You're in the 60% tax trap. Contributing ".$this->money($contribution).' to a pension reclaims your Personal Allowance — relief of up to 60% on that contribution.'
                        : 'A pension contribution of '.$this->money($contribution).' attracts '.$this->pct($rate).' tax relief.',
                ];
            }
        }

        // --- ISA -------------------------------------------------------------
        if ($hasFinancial) {
            $isaAssumed = (int) round($income * 0.10);
            $savings[] = [
                'key' => 'isa',
                'label' => 'ISA allowance',
                'amount' => (int) round($isaAssumed * $rate),
                'reason' => 'Sheltering '.$this->money($isaAssumed).' in an ISA keeps the growth and income tax-free.',
            ];
        }

        // --- Personal Savings Allowance (own) --------------------------------
        $psa = $this->personalSavingsAllowance($income);
        if ($has('savings', 'bank') && $psa > 0) {
            $savings[] = [
                'key' => 'psa',
                'label' => 'Personal Savings Allowance',
                'amount' => (int) round($psa * $rate),
                'reason' => 'Your first '.$this->money($psa).' of savings interest is tax-free.',
            ];
        }

        // --- Dividend allowance ----------------------------------------------
        if ($has('investments')) {
            $divAllowance = $this->taxInt('dividend_tax.allowance');
            $savings[] = [
                'key' => 'dividend',
                'label' => 'Dividend allowance',
                'amount' => (int) round($divAllowance * $this->dividendRate($income)),
                'reason' => 'Your first '.$this->money($divAllowance).' of dividends is tax-free.',
            ];
        }

        // --- Capital Gains Tax allowance -------------------------------------
        if ($has('investments', 'property')) {
            $cgtAllowance = $this->taxInt('capital_gains_tax.annual_exempt_amount');
            $savings[] = [
                'key' => 'cgt',
                'label' => 'Capital Gains Tax allowance',
                'amount' => (int) round($cgtAllowance * $this->cgtRate($income)),
                'reason' => 'The first '.$this->money($cgtAllowance).' of gains each year is tax-free.',
            ];
        }

        // --- Spouse transfer levers (spouse earns £0) ------------------------
        if ($married && $spouseBand === 'zero') {
            $pa = $this->personalAllowanceBase();
            $spousePsa = $this->personalSavingsAllowance(0); // basic band → full PSA
            $startingRate = $this->taxInt('income_tax.starting_rate_for_savings.band');
            $marriage = $this->taxInt('income_tax.marriage_allowance.amount');

            $savings[] = [
                'key' => 'spouse_pa',
                'label' => "Use your spouse's Personal Allowance",
                'amount' => (int) round($pa * $rate),
                'reason' => 'Your spouse earns nothing, so moving income or savings to them uses their '.$this->money($pa).' tax-free allowance.',
            ];
            $savings[] = [
                'key' => 'spouse_psa',
                'label' => "Spouse's Savings Allowance",
                'amount' => (int) round($spousePsa * $rate),
                'reason' => 'Savings interest of up to '.$this->money($spousePsa).' in your spouse\'s name is tax-free.',
            ];
            $savings[] = [
                'key' => 'spouse_starting_rate',
                'label' => "Spouse's starting rate for savings",
                'amount' => (int) round($startingRate * $rate),
                'reason' => 'With no other income, your spouse can earn up to '.$this->money($startingRate).' of savings interest tax-free.',
            ];
            // Formal Marriage Allowance only applies when the recipient is a
            // basic-rate taxpayer — higher/additional earners are not eligible.
            // (The full-PA transfer lever above works at any rate.)
            if ($this->isBasicRate($income)) {
                $savings[] = [
                    'key' => 'marriage_allowance',
                    'label' => 'Marriage Allowance',
                    'amount' => (int) round($marriage * $this->bandRates()['basic']),
                    'reason' => 'As a basic-rate taxpayer you qualify: your spouse can transfer '.$this->money($marriage).' of their Personal Allowance to you.',
                ];
            }
        }

        $savingsTotal = array_sum(array_column($savings, 'amount'));

        return [
            'tax_year' => $this->taxConfig->getTaxYear(),
            'assumed_income' => $income,
            'marginal_rate' => $rate,
            'savings' => $savings,
            'savings_total' => $savingsTotal,
            'allowances' => $this->allowances($income, $married, $spouseBand, $assets),
        ];
    }

    /**
     * The allowances available (capacity, not saving). Doubles per-person
     * allowances when married and the spouse qualifies.
     *
     * @param  array<int,string>  $assets
     * @return array<string,mixed>
     */
    private function allowances(int $income, bool $married, ?string $spouseBand, array $assets): array
    {
        $has = fn (string ...$keys): bool => (bool) array_intersect($keys, $assets);
        $isa = $this->taxInt('isa.annual_allowance');
        $items = [];

        // Personal Allowance — always shown. In the £100k–£125,140 taper band it
        // carries the 60% tax trap explanation in its note (the standalone trap
        // card is removed; the saving callout attaches to this card in the JS).
        $items[] = $this->personalAllowanceItem('personal_allowance', 'Personal Allowance', $income);

        $items[] = $this->allowanceItem(
            'isa',
            'ISA Allowance',
            $isa,
            'available',
            'Available to shelter eligible savings and investments from tax on income and growth.'
        );
        $items[] = $this->pensionAaItem('pension_aa', 'Pension Annual Allowance', $income);

        $psa = $this->personalSavingsAllowance($income);
        $psaAvailable = $has('savings', 'bank') && $psa > 0;
        $items[] = $this->allowanceItem(
            'psa',
            'Personal Savings Allowance',
            $psa,
            $psaAvailable ? 'available' : 'not_applicable',
            $psaAvailable
                ? 'Available for eligible savings interest within the allowance.'
                : ($psa === 0
                    ? 'Not applicable at the assumed additional-rate income band.'
                    : 'Not applicable because no bank or savings account was selected.')
        );

        $divAllowance = $this->taxInt('dividend_tax.allowance');
        $hasInvestments = $has('investments');
        $items[] = $this->allowanceItem(
            'dividend',
            'Dividend Allowance',
            $divAllowance,
            $hasInvestments ? 'available' : 'not_applicable',
            $hasInvestments
                ? 'Available for eligible dividend income within the allowance.'
                : 'Not applicable because no investments were selected.'
        );

        $cgt = $this->taxInt('capital_gains_tax.annual_exempt_amount');
        $hasChargeableAssets = $has('investments', 'property');
        $items[] = $this->allowanceItem(
            'cgt',
            'Capital Gains Tax Allowance',
            $cgt,
            $hasChargeableAssets ? 'available' : 'not_applicable',
            $hasChargeableAssets
                ? 'Available against eligible gains realised during the tax year.'
                : 'Not applicable because no investments or property were selected.'
        );

        if ($married && $spouseBand !== null) {
            $marriage = $this->taxInt('income_tax.marriage_allowance.amount');
            // Eligible only when the non-earning spouse (£0) pairs with a
            // basic-rate recipient. Shown greyed for ineligible married couples.
            $marriageEligible = $spouseBand === 'zero' && $this->isBasicRate($income);
            $items[] = $this->allowanceItem(
                'marriage_allowance',
                'Marriage Allowance',
                $marriage,
                $marriageEligible ? 'available' : 'not_applicable',
                $marriageEligible
                    ? 'Available because the assumed circumstances pair a non-earning spouse with a basic-rate taxpayer.'
                    : 'Not applicable to the income bands supplied for this estimate.'
            );

            // Spouse's own per-person allowances (household view).
            $spouseIncome = $this->incomeForBand($spouseBand);
            $items[] = $this->personalAllowanceItem('spouse_pa', "Spouse's Personal Allowance", $spouseIncome, isSpouse: true);

            // Starting Rate for Savings — a non-earning spouse can receive up to
            // £5,000 of savings interest tax-free. Greyed unless the spouse has
            // no income.
            $startingRate = $this->taxInt('income_tax.starting_rate_for_savings.band');
            $spouseNoIncome = $spouseBand === 'zero';
            $items[] = $this->allowanceItem(
                'spouse_starting_rate',
                "Spouse's Starting Rate for Savings",
                $startingRate,
                $spouseNoIncome ? 'available' : 'not_applicable',
                $spouseNoIncome
                    ? 'With no income, your spouse can receive up to '.$this->money($startingRate).' of savings interest tax-free.'
                    : 'Not applicable because the supplied spouse income is above the starting-rate conditions.'
            );

            // Personal Savings Allowance — per person. A non-earning spouse still
            // gets the basic-rate amount; an earning spouse gets their band amount
            // (the same rule as your own card). Greyed without household savings.
            $spousePsa = $this->personalSavingsAllowance($spouseIncome);
            $spousePsaAvailable = $has('savings', 'bank') && $spousePsa > 0;
            $items[] = $this->allowanceItem(
                'spouse_psa',
                "Spouse's Personal Savings Allowance",
                $spousePsa,
                $spousePsaAvailable ? 'available' : 'not_applicable',
                $spousePsaAvailable
                    ? ($spouseNoIncome
                        ? 'A non-earning spouse still gets the basic-rate '.$this->money($spousePsa).' of savings interest tax-free.'
                        : 'Available for your spouse\'s eligible savings interest within the allowance.')
                    : ($spousePsa === 0
                        ? 'Not applicable at the spouse\'s assumed additional-rate income band.'
                        : 'Not applicable because no bank or savings account was selected.')
            );

            $items[] = $this->allowanceItem(
                'spouse_isa',
                "Spouse's ISA Allowance",
                $isa,
                'available',
                'Available in your spouse\'s own individual ISA for eligible savings and investments.'
            );
            $items[] = $this->pensionAaItem('spouse_pension_aa', "Spouse's Pension Annual Allowance", $spouseIncome);

            // Dividend and CGT allowances are also per person — held in the
            // spouse's name they double the household's tax-free headroom. Same
            // gating as your own cards.
            $items[] = $this->allowanceItem(
                'spouse_dividend',
                "Spouse's Dividend Allowance",
                $divAllowance,
                $hasInvestments ? 'available' : 'not_applicable',
                $hasInvestments
                    ? 'Available for eligible dividend income held in your spouse\'s name.'
                    : 'Not applicable because no investments were selected.'
            );
            $items[] = $this->allowanceItem(
                'spouse_cgt',
                "Spouse's Capital Gains Tax Allowance",
                $cgt,
                $hasChargeableAssets ? 'available' : 'not_applicable',
                $hasChargeableAssets
                    ? 'Available against eligible gains realised on assets held in your spouse\'s name.'
                    : 'Not applicable because no investments or property were selected.'
            );
        }

        $total = 0;
        foreach ($items as $item) {
            if ($item['state'] === 'available') {
                $total += $item['amount'];
            }
        }

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Build a Personal Allowance allowance-row, flagging the £100k taper so the
     * page can show "tapered to £X" instead of a bare figure.
     *
     * @return array<string,mixed>
     */
    private function personalAllowanceItem(string $key, string $label, int $income, bool $isSpouse = false): array
    {
        $amount = (int) round($this->personalAllowance($income));
        $taper = $this->taperThreshold();
        $taperEnd = (int) round($taper + ($this->personalAllowanceBase() / $this->taxNumber('income_tax.personal_allowance_taper_rate')));
        $inTrap = $income > $taper && $income <= $taperEnd;

        if ($inTrap) {
            // At the exact taper boundary the allowance is £0 today, but it is
            // still actionable because an eligible pension contribution can
            // restore it.
            $state = $amount > 0 ? 'used_automatically' : 'available';
            $explanation = $amount > 0
                ? 'The remaining allowance is used automatically against income. Income over '.$this->money($taper).' reduces it at £1 for every £2; an eligible pension contribution may restore some or all of the tapered amount.'
                : 'The allowance is currently tapered away. An eligible pension contribution may restore some or all of it, subject to pension contribution and allowance rules.';
        } elseif ($income > $taperEnd) {
            $state = 'not_applicable';
            $explanation = 'Not applicable because income over '.$this->money($taperEnd).' has tapered the Personal Allowance away entirely.';
        } elseif ($income > 0) {
            $state = 'used_automatically';
            $explanation = $isSpouse
                ? 'Used automatically against the spouse income assumed for this estimate.'
                : 'Automatically used against your income.';
        } else {
            $state = 'available';
            $explanation = 'Not yet used — available to set against eligible income or savings.';
        }

        return $this->allowanceItem(
            $key,
            $income > $taper ? $label.' (tapered)' : $label,
            $amount,
            $state,
            $explanation
        );
    }

    /**
     * Build a Pension Annual Allowance allowance-row. The Pension Annual
     * Allowance is for pensions, not income: a working earner gets the full
     * £60,000 and it is always shown. A non-earner's figure is capped at the
     * £3,600 relevant-earnings minimum (they can only contribute up to that).
     *
     * @return array<string,mixed>
     */
    private function pensionAaItem(string $key, string $label, int $income): array
    {
        $pension = $this->taxConfig->getPensionAllowances();
        $aa = $this->requiredArrayInt($pension, 'annual_allowance', 'pension.annual_allowance');
        $nonEarnerLimit = $this->requiredArrayInt($pension, 'relevant_earnings_minimum', 'pension.relevant_earnings_minimum');
        $isNonEarner = $income === 0;

        if ($isNonEarner) {
            $net = (int) round($nonEarnerLimit * (1 - $this->bandRates()['basic']));
            $explanation = 'The Pension Annual Allowance is '.$this->money($aa).'. With no earnings, contributions receiving tax relief are normally limited to '.$this->money($nonEarnerLimit).' gross ('.$this->money($net).' net after basic-rate relief); the outcome also depends on allowance rules and personal circumstances.';
        } else {
            $explanation = 'You can open or contribute to a pension. Tax relief is limited by earnings, allowance rules and personal circumstances.';
        }

        return $this->allowanceItem(
            $key,
            $label,
            $aa,
            'available',
            $explanation
        );
    }

    /**
     * @return array{key:string,label:string,amount:int,state:string,explanation:string,on:bool,note:string}
     */
    private function allowanceItem(string $key, string $label, int $amount, string $state, string $explanation): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'amount' => $amount,
            'state' => $state,
            'explanation' => $explanation,
            // Transitional response compatibility for consumers outside the
            // public page. New rendering is driven only by the semantic state.
            'on' => $state === 'available',
            'note' => $explanation,
        ];
    }

    // --- Tax engine ----------------------------------------------------------

    /**
     * Income tax due on a gross income, optionally with a gross pension
     * contribution that extends the basic/additional bands and restores the
     * tapered Personal Allowance.
     */
    private function incomeTax(int $gross, int $pension = 0): float
    {
        $basicLimit = $this->taxNumber('income_tax.bands.0.max');
        $additionalThreshold = $this->taxNumber('income_tax.additional_rate_threshold');
        $rates = $this->bandRates();

        $pa = $this->personalAllowance(max(0, $gross - $pension));
        $taxable = max(0.0, $gross - $pa);

        $basicCeiling = $basicLimit + $pension;
        $additionalCeiling = $additionalThreshold + $pension;

        $tax = $rates['basic'] * min($taxable, $basicCeiling);
        $tax += $rates['higher'] * max(0.0, min($taxable, $additionalCeiling) - $basicCeiling);
        $tax += $rates['additional'] * max(0.0, $taxable - $additionalCeiling);

        return $tax;
    }

    /** Total tax relief on a pension contribution = 20% at source + band/PA effect. */
    private function pensionRelief(int $gross, int $contribution): float
    {
        $atSource = $contribution * $this->bandRates()['basic'];
        $taxSaved = $this->incomeTax($gross, 0) - $this->incomeTax($gross, $contribution);

        return $atSource + $taxSaved;
    }

    // --- Lookups -------------------------------------------------------------

    private function personalAllowanceBase(): int
    {
        return $this->taxInt('income_tax.personal_allowance');
    }

    private function taperThreshold(): int
    {
        return $this->taxInt('income_tax.personal_allowance_taper_threshold');
    }

    /** Personal Allowance after the £1-per-£2 taper above £100k. */
    private function personalAllowance(int $income): float
    {
        $base = $this->personalAllowanceBase();
        $threshold = $this->taperThreshold();
        $taperRate = $this->taxNumber('income_tax.personal_allowance_taper_rate');

        if ($income <= $threshold) {
            return (float) $base;
        }

        return max(0.0, $base - ($income - $threshold) * $taperRate);
    }

    /** @return array{basic:float,higher:float,additional:float} */
    private function bandRates(): array
    {
        $bands = $this->taxConfig->getIncomeTax()['bands'] ?? [];
        $rate = function (string $name) use ($bands): float {
            foreach ($bands as $b) {
                if (stripos((string) ($b['name'] ?? ''), $name) !== false && is_numeric($b['rate'] ?? null)) {
                    return (float) $b['rate'];
                }
            }

            throw new LogicException("Missing required tax configuration: income_tax.bands.{$name}.rate");
        };

        return [
            'basic' => $rate('Basic'),
            'higher' => $rate('Higher'),
            'additional' => $rate('Additional'),
        ];
    }

    /** True when the assumed income falls in the basic-rate band. */
    private function isBasicRate(int $income): bool
    {
        return $income <= $this->taxInt('income_tax.higher_rate_threshold');
    }

    /** Marginal income-tax rate at the assumed income. */
    private function marginalRate(int $income): float
    {
        $rates = $this->bandRates();
        $higherStart = $this->taxInt('income_tax.higher_rate_threshold');
        $additionalStart = $this->taxInt('income_tax.additional_rate_threshold');

        if ($income > $additionalStart) {
            return $rates['additional'];
        }
        if ($income > $higherStart) {
            return $rates['higher'];
        }

        return $rates['basic'];
    }

    private function personalSavingsAllowance(int $income): int
    {
        $psa = $this->taxConfig->get('income_tax.personal_savings_allowance');
        if (! is_array($psa)) {
            throw new LogicException('Missing required tax configuration: income_tax.personal_savings_allowance');
        }
        $higherStart = $this->taxInt('income_tax.higher_rate_threshold');
        $additionalStart = $this->taxInt('income_tax.additional_rate_threshold');

        if ($income > $additionalStart) {
            return $this->requiredArrayInt($psa, 'additional', 'income_tax.personal_savings_allowance.additional');
        }
        if ($income > $higherStart) {
            return $this->requiredArrayInt($psa, 'higher', 'income_tax.personal_savings_allowance.higher');
        }

        return $this->requiredArrayInt($psa, 'basic', 'income_tax.personal_savings_allowance.basic');
    }

    private function dividendRate(int $income): float
    {
        $higherStart = $this->taxInt('income_tax.higher_rate_threshold');
        $additionalStart = $this->taxInt('income_tax.additional_rate_threshold');

        if ($income > $additionalStart) {
            return $this->taxNumber('dividend_tax.additional_rate');
        }
        if ($income > $higherStart) {
            return $this->taxNumber('dividend_tax.higher_rate');
        }

        return $this->taxNumber('dividend_tax.basic_rate');
    }

    private function cgtRate(int $income): float
    {
        $higherStart = $this->taxInt('income_tax.higher_rate_threshold');

        return $income > $higherStart
            ? $this->taxNumber('capital_gains_tax.higher_rate')
            : $this->taxNumber('capital_gains_tax.basic_rate');
    }

    private function normaliseBand(?string $band, ?string $default): ?string
    {
        return in_array($band, self::BAND_KEYS, true) ? $band : $default;
    }

    private function incomeForBand(string $band): int
    {
        return FunnelIncomeBand::assumedIncome(
            $band,
            config('onboarding.savetax_over_band_assumed_income')
        );
    }

    private function taxNumber(string $path): float
    {
        $value = $this->taxConfig->get($path);
        if (! is_numeric($value)) {
            throw new LogicException("Missing required tax configuration: {$path}");
        }

        return (float) $value;
    }

    private function taxInt(string $path): int
    {
        return (int) round($this->taxNumber($path));
    }

    private function requiredArrayInt(array $values, string $key, string $path): int
    {
        if (! is_numeric($values[$key] ?? null)) {
            throw new LogicException("Missing required tax configuration: {$path}");
        }

        return (int) round((float) $values[$key]);
    }

    private function money(int $n): string
    {
        return '£'.number_format($n);
    }

    private function pct(float $rate): string
    {
        return rtrim(rtrim(number_format($rate * 100, 1), '0'), '.').'%';
    }
}
