<?php

declare(strict_types=1);

namespace App\Services\Actions;

use App\Models\FamilyMember;
use App\Models\Investment\InvestmentAccount;
use App\Models\User;
use App\Services\Retirement\PensionContributionRule;
use App\Services\Stores\PensionStore;
use App\Services\Stores\SavingsStore;
use App\Services\Tax\TaxStrategyMath;
use App\Services\TaxConfigService;
use Carbon\Carbon;

/**
 * What a how-to branches on and fills in: the figures the action's strategy
 * already published (never recomputed), the user's own accounts by name, and
 * tax-year facts from tax config (Rule 2). Raw values drive the conditions;
 * display text fills the placeholders.
 */
final class ActionHowToFacts
{
    /** Gross pension payment behind each pension action (the figure its strategy published). */
    private const PENSION_GROSS = [
        'pension_tax_relief' => 'suggested_contribution',
        'pa_taper_rescue' => 'suggested_contribution',
        'additional_rate_avoidance' => 'suggested_contribution',
        'pension_aa_carry_forward' => 'recommended_contribution',
    ];

    /** Whole numbers that are not money. */
    private const COUNTS = ['user_age', 'spouse_age', 'lookback_years', 'children_under_18'];

    public function __construct(
        private readonly TaxStrategyMath $math,
        private readonly TaxConfigService $taxConfig,
        private readonly PensionStore $pensions,
        private readonly SavingsStore $savings,
    ) {}

    /**
     * @param  array<string, mixed>|null  $item  the composed tax plan item, if any
     * @return array{facts: array<string, mixed>, text: array<string, string>}
     */
    public function for(User $user, ?array $item): array
    {
        $facts = [];
        $text = [];

        foreach ($item ?? [] as $key => $value) {
            if (is_bool($value) || is_string($value)) {
                $facts[$key] = $value;
            } elseif (is_numeric($value)) {
                $facts[$key] = (float) $value;
                $text[$key] = $this->display($key, (float) $value);
            }
        }
        if (! empty($item['target_accounts']) && is_array($item['target_accounts'])) {
            $text['target_accounts'] = self::listed($item['target_accounts']);
        }
        if (isset($item['children_under_18']) && (int) $item['children_under_18'] > 0) {
            $count = (int) $item['children_under_18'];
            $text['each_child'] = $count === 1 ? 'your child' : sprintf('each of your %d children', $count);
        }

        $basic = $this->math->bandRateForBand('basic');
        $band = (string) ($item['tax_band'] ?? $this->math->bandFromIncomeFor($user, $this->math->taxableIncomeFor($user)));
        $facts['band'] = $band;
        $facts['above_basic'] = $band !== 'basic';
        $text['basic_rate'] = self::percent($basic);

        $end = $this->taxConfig->getEffectiveTo();
        $monthsLeft = 1;
        if ($end !== '') {
            $endDate = Carbon::parse($end);
            $text['tax_year_end'] = $endDate->format('j F Y');
            // Whole payroll months before the tax year closes; at least one.
            $monthsLeft = max(1, (int) Carbon::today()->diffInMonths($endDate));
            $facts['months_left'] = $monthsLeft;
            $text['months_left'] = (string) $monthsLeft;
        }

        $this->pensionFacts($user, $facts, $text);
        $this->accountFacts($user, $facts, $text);
        $this->spouseFacts($user, $facts, $text);
        $this->configFacts($facts, $text);

        // The relief-at-source split of a pension payment (FA 2004 s192,
        // https://www.legislation.gov.uk/ukpga/2004/12/section/192): the member
        // pays net, the provider adds basic-rate relief, and relief above the
        // basic rate is what the member claims back.
        $grossKey = self::PENSION_GROSS[(string) ($item['type'] ?? '')] ?? null;
        $gross = $grossKey !== null ? (float) ($item[$grossKey] ?? 0) : 0.0;
        if ($gross > 0) {
            $providerRelief = round($gross * $basic);
            $extra = max(0.0, floor((float) ($item['estimated_annual_tax_saved'] ?? 0)) - $providerRelief);
            foreach ([
                'contribution' => $gross,
                // Up, not down: the monthly payments must add up to the whole amount.
                'contribution_per_month_left' => ceil($gross / $monthsLeft),
                'net_payment' => $gross - $providerRelief,
                'provider_relief' => $providerRelief,
                'extra_relief' => $extra,
            ] as $key => $value) {
                $facts[$key] = $value;
                $text[$key] = self::pounds($value);
            }
        }

        return ['facts' => $facts, 'text' => $text];
    }

    /** @param  array<string, mixed>  $facts  @param  array<string, string>  $text */
    private function pensionFacts(User $user, array &$facts, array &$text): void
    {
        $dc = $this->pensions->dcPensionsFor($user);
        $workplace = $dc->filter(fn ($p) => PensionContributionRule::isWorkplace($p));
        $personal = $dc->reject(fn ($p) => PensionContributionRule::isWorkplace($p));
        $name = static fn ($p, string $fallback): string => trim((string) ($p->scheme_name ?: $p->provider)) ?: $fallback;

        $facts['has_workplace_pension'] = $workplace->isNotEmpty();
        $facts['has_salary_sacrifice'] = $workplace->contains(fn ($p) => ! empty($p->salary_sacrifice));
        $facts['has_personal_pension'] = $personal->isNotEmpty();
        $db = $this->pensions->dbPensionsFor($user);
        $facts['has_db_pension_only'] = $dc->isEmpty() && $db->isNotEmpty();
        $facts['has_no_pension'] = $dc->isEmpty() && $db->isEmpty();
        if ($db->isNotEmpty()) {
            $text['db_pension'] = trim((string) ($db->first()->scheme_name ?? '')) ?: 'your defined benefit pension';
        }
        if ($workplace->isNotEmpty()) {
            $text['workplace_pension'] = $name($workplace->first(), 'your workplace pension');
        }
        if ($personal->isNotEmpty()) {
            $text['personal_pension'] = $name($personal->first(), 'your personal pension');
        }
    }

    /** @param  array<string, mixed>  $facts  @param  array<string, string>  $text */
    private function accountFacts(User $user, array &$facts, array &$text): void
    {
        $savings = $this->savings->forUser($user)->where('user_id', $user->id);
        $cashIsa = $savings->first(fn ($a) => $a->is_isa && $a->isa_type !== 'lifetime');
        $lifetime = $savings->first(fn ($a) => $a->is_isa && $a->isa_type === 'lifetime');

        $investments = InvestmentAccount::query()
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('joint_owner_id', $user->id))
            ->get();
        $stocksIsa = $investments->first(fn ($a) => $a->account_type === 'isa' && $a->isa_type !== 'lifetime');
        $lifetime ??= $investments->first(fn ($a) => $a->isa_type === 'lifetime');
        $gia = $investments->first(fn ($a) => $a->account_type === 'gia');

        $label = static fn ($a): string => trim((string) ($a->institution ?? $a->provider ?? $a->platform ?? $a->account_name ?? ''));
        foreach (['cash_isa' => $cashIsa, 'stocks_isa' => $stocksIsa, 'lifetime_isa_account' => $lifetime, 'gia' => $gia] as $key => $account) {
            $facts['has_'.$key] = $account !== null;
            if ($account !== null && $label($account) !== '') {
                $text[$key] = $label($account);
            }
        }
        $facts['isa_with_gia_provider'] = $gia !== null && $stocksIsa !== null
            && $label($gia) !== '' && strcasecmp($label($gia), $label($stocksIsa)) === 0;
    }

    /** @param  array<string, mixed>  $facts  @param  array<string, string>  $text */
    private function spouseFacts(User $user, array &$facts, array &$text): void
    {
        $facts['has_spouse'] = $this->math->isMarriedOrCivilPartner($user);
        if (! $facts['has_spouse']) {
            return;
        }
        $first = $user->spouse?->first_name
            ?? FamilyMember::query()->where('user_id', $user->id)->where('relationship', 'spouse')->value('first_name');
        $text['spouse'] = trim((string) $first) ?: 'your spouse or civil partner';
    }

    /** @param  array<string, mixed>  $facts  @param  array<string, string>  $text */
    private function configFacts(array &$facts, array &$text): void
    {
        $isa = $this->taxConfig->getISAAllowances();
        foreach (['isa_allowance' => $isa['annual_allowance'] ?? null, 'junior_isa_allowance' => $isa['junior_isa']['annual_allowance'] ?? null] as $key => $value) {
            if (is_numeric($value)) {
                $text[$key] = self::pounds((float) $value);
            }
        }
        $pension = $this->taxConfig->getPensionAllowances();
        if (is_numeric($pension['carry_forward_years'] ?? null)) {
            $text['carry_forward_years'] = (string) (int) $pension['carry_forward_years'];
        }

        $income = $this->taxConfig->getIncomeTax();
        if (is_numeric($income['personal_allowance_taper_threshold'] ?? null)) {
            $text['taper_threshold'] = self::pounds((float) $income['personal_allowance_taper_threshold']);
        }
        // "£1 for every £2": the income that takes away £1 of allowance.
        if ((float) ($income['personal_allowance_taper_rate'] ?? 0) > 0) {
            $text['taper_per_pound'] = self::pounds(1 / (float) $income['personal_allowance_taper_rate']);
        }

        $lisa = $isa['lifetime_isa'] ?? [];
        foreach ([
            'lisa_first_payment_before' => isset($lisa['max_age_to_open']) ? (int) $lisa['max_age_to_open'] + 1 : null,
            'lisa_pay_in_until' => isset($lisa['max_age_to_contribute']) ? (int) $lisa['max_age_to_contribute'] + 1 : null,
            'lisa_free_withdrawal_age' => $lisa['penalty_free_withdrawal_age'] ?? null,
            'jisa_control_age' => $isa['junior_isa']['manage_from_age'] ?? null,
            'jisa_withdraw_age' => isset($isa['junior_isa']['max_age']) ? (int) $isa['junior_isa']['max_age'] + 1 : null,
        ] as $key => $age) {
            if (is_numeric($age)) {
                $text[$key] = (string) (int) $age;
            }
        }
        if (is_numeric($lisa['withdrawal_penalty'] ?? null)) {
            $text['lisa_withdrawal_charge'] = self::percent((float) $lisa['withdrawal_penalty']);
        }

        // National Insurance Contributions (Employer Pensions Contributions)
        // Act 2026: the NI saving on salary sacrifice is capped from its date.
        $sacrifice = $pension['salary_sacrifice'] ?? [];
        if (is_numeric($sacrifice['nic_exemption_cap'] ?? null) && ! empty($sacrifice['nic_exemption_cap_effective_date'])) {
            $text['sacrifice_cap'] = self::pounds((float) $sacrifice['nic_exemption_cap']);
            $text['sacrifice_cap_date'] = Carbon::parse($sacrifice['nic_exemption_cap_effective_date'])->format('j F Y');
            $facts['over_sacrifice_cap'] = (float) ($facts['annual_contribution'] ?? 0) > (float) $sacrifice['nic_exemption_cap'];
        }
    }

    private function display(string $key, float $value): string
    {
        return match (true) {
            in_array($key, self::COUNTS, true) => (string) (int) $value,
            // Rates, factors and *_pct are stored as fractions (0.5 = 50%).
            // Matched on the key's ending: "additional_rate_slice" is money.
            str_ends_with($key, 'rate') || str_ends_with($key, 'rate_delta') || str_ends_with($key, '_factor') || str_ends_with($key, '_pct') => self::percent($value),
            default => self::pounds($value),
        };
    }

    private static function pounds(float $value): string
    {
        // Down, never up, as on the card's key figure; the epsilon absorbs float
        // noise in figures already rounded to pence.
        return '£'.number_format(floor($value + 0.001));
    }

    private static function percent(float $rate): string
    {
        return rtrim(rtrim(number_format($rate * 100, 2), '0'), '.').'%';
    }

    /** @param  list<string>  $names */
    private static function listed(array $names): string
    {
        $names = array_values(array_filter(array_map('strval', $names)));
        if (count($names) < 2) {
            return $names[0] ?? '';
        }

        return implode(', ', array_slice($names, 0, -1)).' and '.end($names);
    }
}
