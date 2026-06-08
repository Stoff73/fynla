<?php

declare(strict_types=1);

namespace App\Services\Marketing;

use App\Services\TaxConfigService;

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
    /** Income band key => assumed (upper-bound) income. 'zero' is spouse-only. */
    private const BAND_INCOME = [
        'zero' => 0,
        'upto_50270' => 50270,
        '50271_100000' => 100000,
        '100001_125140' => 125140,
        'over_125140' => 150000,
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

        $income = self::BAND_INCOME[$incomeBand];
        $rate = $this->marginalRate($income);

        $has = fn (string ...$keys): bool => (bool) array_intersect($keys, $assets);
        $hasFinancial = $has('isa', 'savings', 'investments', 'bank');

        $savings = [];

        // --- Pension ---------------------------------------------------------
        // Only when the user has no pension yet. Band C clears the 60% trap
        // (contribute down to £100k); other bands assume 10% of upper income.
        if (! $has('pension')) {
            $contribution = $incomeBand === self::TRAP_BAND
                ? max(0, $income - $this->taperThreshold())
                : (int) round($income * 0.10);

            if ($contribution > 0) {
                $relief = (int) round($this->pensionRelief($income, $contribution));
                $savings[] = [
                    'key' => 'pension',
                    'label' => 'Pension contribution',
                    'amount' => $relief,
                    'reason' => $incomeBand === self::TRAP_BAND
                        ? 'Contributing '.$this->money($contribution).' to a pension reclaims your Personal Allowance — relief of up to 60% in this band.'
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
            $divAllowance = (int) $this->taxConfig->get('dividend_tax.allowance', 500);
            $savings[] = [
                'key' => 'dividend',
                'label' => 'Dividend allowance',
                'amount' => (int) round($divAllowance * $this->dividendRate($income)),
                'reason' => 'Your first '.$this->money($divAllowance).' of dividends is tax-free.',
            ];
        }

        // --- Capital Gains Tax allowance -------------------------------------
        if ($has('investments', 'property')) {
            $cgtAllowance = (int) $this->taxConfig->get('capital_gains_tax.annual_exempt_amount', 3000);
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
            $startingRate = (int) $this->taxConfig->get('income_tax.starting_rate_for_savings.band', 5000);
            $marriage = (int) $this->taxConfig->get('income_tax.marriage_allowance.amount', 1260);

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
            $savings[] = [
                'key' => 'marriage_allowance',
                'label' => 'Marriage Allowance',
                'amount' => (int) round($marriage * 0.20),
                'reason' => 'A separate route: your spouse can transfer '.$this->money($marriage).' of their Personal Allowance to you.',
            ];
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
        $pa = $this->personalAllowanceBase();
        $isa = (int) $this->taxConfig->getISAAllowances()['annual_allowance'];
        $aa = (int) $this->taxConfig->getPensionAllowances()['annual_allowance'];
        $items = [];

        $items[] = $this->personalAllowanceItem('personal_allowance', 'Personal Allowance', $income);
        $items[] = ['key' => 'isa', 'label' => 'ISA Allowance', 'amount' => $isa, 'on' => true];
        $items[] = ['key' => 'pension_aa', 'label' => 'Pension Annual Allowance', 'amount' => $aa, 'on' => true];

        $psa = $this->personalSavingsAllowance($income);
        $items[] = ['key' => 'psa', 'label' => 'Personal Savings Allowance', 'amount' => $psa, 'on' => $has('savings', 'bank') && $psa > 0];

        $divAllowance = (int) $this->taxConfig->get('dividend_tax.allowance', 500);
        $items[] = ['key' => 'dividend', 'label' => 'Dividend Allowance', 'amount' => $divAllowance, 'on' => $has('investments')];

        $cgt = (int) $this->taxConfig->get('capital_gains_tax.annual_exempt_amount', 3000);
        $items[] = ['key' => 'cgt', 'label' => 'Capital Gains Tax Allowance', 'amount' => $cgt, 'on' => $has('investments', 'property')];

        if ($married) {
            $marriage = (int) $this->taxConfig->get('income_tax.marriage_allowance.amount', 1260);
            $items[] = ['key' => 'marriage_allowance', 'label' => 'Marriage Allowance', 'amount' => $marriage, 'on' => true];

            // Spouse's own per-person allowances (household view).
            $spouseIncome = $spouseBand !== null ? self::BAND_INCOME[$spouseBand] : 0;
            $items[] = $this->personalAllowanceItem('spouse_pa', "Spouse's Personal Allowance", $spouseIncome);
            $items[] = ['key' => 'spouse_isa', 'label' => "Spouse's ISA Allowance", 'amount' => $isa, 'on' => true];
            $items[] = ['key' => 'spouse_pension_aa', 'label' => "Spouse's Pension Annual Allowance", 'amount' => $aa, 'on' => true];
        }

        $total = 0;
        foreach ($items as $item) {
            if ($item['on']) {
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
    private function personalAllowanceItem(string $key, string $label, int $income): array
    {
        $amount = (int) round($this->personalAllowance($income));
        $item = ['key' => $key, 'label' => $label, 'amount' => $amount, 'on' => true];

        if ($income > $this->taperThreshold()) {
            $item['label'] = $label.' (tapered)';
            $item['note'] = $amount > 0
                ? 'Tapered down from '.$this->money($this->personalAllowanceBase()).' because income is over '.$this->money($this->taperThreshold()).'. A pension contribution can restore it.'
                : 'Tapered to £0 because income is over '.$this->money($this->taperThreshold()).'. A pension contribution can restore it.';
        }

        return $item;
    }

    // --- Tax engine ----------------------------------------------------------

    /**
     * Income tax due on a gross income, optionally with a gross pension
     * contribution that extends the basic/additional bands and restores the
     * tapered Personal Allowance.
     */
    private function incomeTax(int $gross, int $pension = 0): float
    {
        $basicLimit = 37700.0; // taxable-income width of the basic-rate band
        $additionalThreshold = (float) $this->taxConfig->get('income_tax.additional_rate_threshold', 125140);
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
        return (int) $this->taxConfig->get('income_tax.personal_allowance', 12570);
    }

    private function taperThreshold(): int
    {
        return (int) $this->taxConfig->get('income_tax.personal_allowance_taper_threshold', 100000);
    }

    /** Personal Allowance after the £1-per-£2 taper above £100k. */
    private function personalAllowance(int $income): float
    {
        $base = $this->personalAllowanceBase();
        $threshold = $this->taperThreshold();
        $taperRate = (float) $this->taxConfig->get('income_tax.personal_allowance_taper_rate', 0.5);

        if ($income <= $threshold) {
            return (float) $base;
        }

        return max(0.0, $base - ($income - $threshold) * $taperRate);
    }

    /** @return array{basic:float,higher:float,additional:float} */
    private function bandRates(): array
    {
        $bands = $this->taxConfig->getIncomeTax()['bands'] ?? [];
        $rate = function (string $name, float $default) use ($bands): float {
            foreach ($bands as $b) {
                if (stripos($b['name'] ?? '', $name) !== false) {
                    return (float) $b['rate'];
                }
            }

            return $default;
        };

        return [
            'basic' => $rate('Basic', 0.20),
            'higher' => $rate('Higher', 0.40),
            'additional' => $rate('Additional', 0.45),
        ];
    }

    /** Marginal income-tax rate at the assumed income. */
    private function marginalRate(int $income): float
    {
        $rates = $this->bandRates();
        $higherStart = (int) $this->taxConfig->get('income_tax.higher_rate_threshold', 50270);
        $additionalStart = (int) $this->taxConfig->get('income_tax.additional_rate_threshold', 125140);

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
        $psa = $this->taxConfig->get('income_tax.personal_savings_allowance', ['basic' => 1000, 'higher' => 500, 'additional' => 0]);
        $higherStart = (int) $this->taxConfig->get('income_tax.higher_rate_threshold', 50270);
        $additionalStart = (int) $this->taxConfig->get('income_tax.additional_rate_threshold', 125140);

        if ($income > $additionalStart) {
            return (int) $psa['additional'];
        }
        if ($income > $higherStart) {
            return (int) $psa['higher'];
        }

        return (int) $psa['basic'];
    }

    private function dividendRate(int $income): float
    {
        $higherStart = (int) $this->taxConfig->get('income_tax.higher_rate_threshold', 50270);
        $additionalStart = (int) $this->taxConfig->get('income_tax.additional_rate_threshold', 125140);

        if ($income > $additionalStart) {
            return (float) $this->taxConfig->get('dividend_tax.additional_rate', 0.3935);
        }
        if ($income > $higherStart) {
            return (float) $this->taxConfig->get('dividend_tax.higher_rate', 0.3575);
        }

        return (float) $this->taxConfig->get('dividend_tax.basic_rate', 0.1075);
    }

    private function cgtRate(int $income): float
    {
        $higherStart = (int) $this->taxConfig->get('income_tax.higher_rate_threshold', 50270);

        return $income > $higherStart
            ? (float) $this->taxConfig->get('capital_gains_tax.higher_rate', 0.24)
            : (float) $this->taxConfig->get('capital_gains_tax.basic_rate', 0.18);
    }

    private function normaliseBand(?string $band, ?string $default): ?string
    {
        return isset(self::BAND_INCOME[$band]) ? $band : $default;
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
