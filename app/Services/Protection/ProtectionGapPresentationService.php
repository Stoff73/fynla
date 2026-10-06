<?php

declare(strict_types=1);

namespace App\Services\Protection;

use App\Models\ProtectionProfile;
use App\Models\User;
use App\Services\TaxConfigService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ProtectionGapPresentationService
{
    public const CONTRACT_VERSION = 'protection_gap_v1';

    public function __construct(
        private readonly CoverageGapAnalyzer $gapAnalyzer,
        private readonly TaxConfigService $taxConfig,
        private readonly LifeCoverReach $lifeCoverReach,
    ) {}

    public function forUser(User $user, ProtectionProfile $profile): array
    {
        $user->loadMissing([
            'lifeInsurancePolicies',
            'criticalIllnessPolicies',
            'incomeProtectionPolicies',
            'disabilityPolicies',
            'sicknessIllnessPolicies',
        ]);

        // The policies covering this user's LIFE — the per-life question, which is
        // not the same set as the policies they own. A joint-life policy covers both
        // spouses and is recorded once, on the account that entered it, so reading
        // the `user_id` hasMany showed the other life assured **£0 of cover directly
        // above the £500,000 policy the same card counted** (W-0384). W-0186 routed
        // the policy LIST to the reach and left this total behind it, which is how a
        // total and its own count came to disagree on one card for a second time.
        //
        // `LifeCoverReach` is the one home for the question (Rule 20); this reads it
        // ONCE and both halves of the card are built from that single read, so they
        // cannot drift apart again.
        //
        // **Critical illness stays the plain relation, deliberately.**
        // `critical_illness_policies` carries no `joint_life`, no `joint_owner_id`
        // and no ownership columns at all (verified with `SHOW COLUMNS`, not inferred
        // from the model), so a critical illness policy covers only its owner and
        // there is nothing to reach with.
        $lifePoliciesCovering = $this->lifeCoverReach->policiesCovering($user);

        $needs = $this->gapAnalyzer->calculateProtectionNeeds($profile);
        $coverage = $this->gapAnalyzer->calculateTotalCoverage(
            $lifePoliciesCovering,
            $user->criticalIllnessPolicies,
            $user->incomeProtectionPolicies,
            $user->disabilityPolicies,
            $user->sicknessIllnessPolicies,
            $profile,
            $user,
        );
        $gaps = $this->gapAnalyzer->calculateCoverageGap($needs, $coverage);
        $replacement = $needs['income_replacement'];

        // W-0227 — the inputs disclosed on the debt panel must be the inputs the
        // figure was actually built from. This published `profile->mortgage_balance`
        // and `profile->other_debts` beside a need computed from the user's LIABILITY
        // RECORDS, so the panel stated `£0` and `£0` above a need of £182,500: a user
        // could not reconcile the figure to anything, because the two numbers offered
        // as its inputs had contributed nothing to it.
        $debtBasis = $this->gapAnalyzer->debtProtectionBasis($profile);
        $lifePolicies = $this->lifePolicyReferences($lifePoliciesCovering);
        $incomePolicies = $this->incomePolicyReferences(
            $user->incomeProtectionPolicies,
            $user->disabilityPolicies,
            $user->sicknessIllnessPolicies,
        );

        $categories = [
            $this->category(
                'human_capital',
                'Income replacement capital',
                (float) ($needs['human_capital'] ?? 0),
                (float) ($gaps['coverage_allocated']['human_capital_covered'] ?? 0),
                (float) ($gaps['gaps_by_category']['human_capital_gap'] ?? 0),
                $replacement['spending_recorded']
                    ? [
                        'household_living_costs_a_year' => $replacement['household_living_costs'],
                        'income_that_continues_a_year' => $replacement['income_that_continues'],
                        'income_gap_a_year' => $replacement['income_gap'],
                    ]
                    : [],
                [[
                    'key' => 'years_until_state_pension_age',
                    'value' => $replacement['term_years'],
                    'unit' => 'years',
                ], [
                    'key' => 'personal_injury_discount_rate',
                    'value' => round($replacement['discount_rate'] * 100, 2),
                    'unit' => 'percent',
                ], [
                    'key' => 'allocation_priority',
                    'value' => 'Life cover remaining after debt protection',
                    'unit' => null,
                ]],
                $this->incomeReplacementExplanation($replacement, (float) ($needs['human_capital'] ?? 0)),
                $lifePolicies,
            ),
            $this->category(
                'debt_protection',
                'Debt protection',
                (float) ($needs['debt_protection'] ?? 0),
                (float) ($gaps['coverage_allocated']['debt_covered'] ?? 0),
                (float) ($gaps['gaps_by_category']['debt_protection_gap'] ?? 0),
                [
                    'mortgage_balance' => round((float) ($debtBasis['components']['mortgages'] ?? 0), 2),
                    'other_debts' => round((float) ($debtBasis['components']['other_debts'] ?? 0), 2),
                    'calculated_debt_need' => round((float) ($needs['debt_protection'] ?? 0), 2),
                ],
                [[
                    'key' => 'allocation_priority',
                    'value' => 'Life cover is allocated to debt first',
                    'unit' => null,
                ], [
                    // W-0227 acceptance 3 — a figure whose provenance is invisible
                    // cannot be checked by the person it is being sold to. The two
                    // sources produce the same shape, so without this the reader
                    // cannot tell which one answered.
                    'key' => 'debt_source',
                    'value' => $debtBasis['source'] === 'records'
                        ? 'Your mortgage and liability records, at your share of each'
                        : 'The summary figures on your protection profile — you have no mortgage or liability records',
                    'unit' => null,
                ]],
                $debtBasis['source'] === 'records'
                    ? 'This compares the mortgages and other debts on your records, counted at your own share, with the life cover allocated to debt first.'
                    : 'This compares the debt figures recorded on your protection profile with the life cover allocated to debt first.',
                $lifePolicies,
            ),
            $this->category(
                'final_expenses',
                'Final expenses',
                (float) ($needs['final_expenses'] ?? 0),
                (float) ($gaps['coverage_allocated']['final_expenses_covered'] ?? 0),
                (float) ($gaps['gaps_by_category']['final_expenses_gap'] ?? 0),
                ['cost_of_dying' => round((float) ($needs['final_expenses'] ?? 0), 2)],
                [[
                    'key' => 'allocation_priority',
                    'value' => 'After debt and income replacement capital',
                    'unit' => null,
                ]],
                'The average cost of dying in the UK: the funeral, professional fees and send-off (SunLife Cost of Dying Report 2025). Life cover left after the higher priorities pays it.',
                $lifePolicies,
            ),
            $this->category(
                'income_protection',
                'Income protection',
                (float) ($needs['income_protection_need'] ?? 0),
                (float) ($gaps['income_replacement_coverage'] ?? 0),
                (float) ($gaps['gaps_by_category']['income_protection_gap'] ?? 0),
                // Only what the gap is worked out from. Statutory Sick Pay is not
                // part of it (the cover is recorded policies), so it is not listed:
                // the raw state_benefits array showed as "Ssp Max Weeks: £28"
                // (fynla.org, 2026-09-29). The income card's reason states it.
                [
                    'gross_income' => round((float) ($needs['gross_income'] ?? 0), 2),
                ],
                [[
                    'key' => 'most_an_insurer_pays',
                    'value' => $this->gapAnalyzer->benefitTiersInWords(),
                    'unit' => null,
                ], [
                    'key' => 'coverage_basis',
                    'value' => 'Annualised recorded income, disability and sickness benefits',
                    'unit' => null,
                ]],
                sprintf(
                    'This compares the most an insurer pays on your gross earned income (%s, Legal & General\'s limit) with the income protection, disability and sickness benefits you have recorded, as yearly amounts.',
                    $this->gapAnalyzer->benefitTiersInWords()
                ),
                $incomePolicies,
            ),
        ];

        return [
            'contract_version' => self::CONTRACT_VERSION,
            'totals' => [
                'need' => round((float) ($gaps['total_need'] ?? 0), 2),
                'cover' => round((float) ($gaps['total_coverage'] ?? 0), 2),
                'shortfall' => round((float) ($gaps['total_gap'] ?? 0), 2),
                'coverage_percentage' => round((float) ($gaps['coverage_percentage'] ?? 0), 2),
            ],
            'categories' => $categories,
            'calculated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * The income replacement working in words, from the figures the analyser
     * used (item 8b). No spending recorded: say so, never guess (D7).
     */
    public function incomeReplacementExplanation(array $replacement, float $capital): string
    {
        if (! $replacement['spending_recorded']) {
            return 'Add your monthly spending to include your family\'s income in this need. Until then it covers your debts and final expenses only.';
        }
        if ($replacement['term_years'] <= 0) {
            return 'You have reached State Pension age, so no earnings would be lost; this need covers your debts and final expenses.';
        }
        if ($replacement['income_gap'] <= 0) {
            return sprintf(
                'The income that continues, £%s a year, covers your household\'s living costs of £%s a year, so no income replacement is needed; this need covers your debts and final expenses.',
                number_format($replacement['income_that_continues']),
                number_format($replacement['household_living_costs']),
            );
        }

        return sprintf(
            'Your household\'s living costs of £%s a year, less £%s a year of income that continues, leave a gap of £%s a year. Paying that until your State Pension age%s, %s years, needs £%s today, at the Personal Injury Discount Rate of %s%% (the rate the law uses to turn a future income into a lump sum).',
            number_format($replacement['household_living_costs']),
            number_format($replacement['income_that_continues']),
            number_format($replacement['income_gap']),
            $replacement['state_pension_date'] ? ' ('.Carbon::parse($replacement['state_pension_date'])->format('F Y').')' : '',
            rtrim(rtrim(number_format($replacement['term_years'], 1), '0'), '.'),
            number_format($capital),
            rtrim(rtrim(number_format($replacement['discount_rate'] * 100, 2), '0'), '.'),
        );
    }

    private function category(
        string $key,
        string $label,
        float $need,
        float $cover,
        float $shortfall,
        array $inputs,
        array $assumptions,
        string $explanation,
        array $policies,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'need' => round($need, 2),
            'cover' => round($cover, 2),
            'shortfall' => round($shortfall, 2),
            'status' => $shortfall > 0 ? 'gap' : 'covered',
            'severity' => $this->severity($need, $shortfall),
            'inputs' => $inputs,
            'assumptions' => $assumptions,
            'explanation' => $explanation,
            'relevant_policies' => $policies,
        ];
    }

    private function severity(float $need, float $shortfall): string
    {
        if ($shortfall <= 0 || $need <= 0) {
            return 'none';
        }

        $ratio = $shortfall / $need;

        return match (true) {
            $ratio >= 0.50 => 'high',
            $ratio >= 0.20 => 'medium',
            default => 'low',
        };
    }

    private function lifePolicyReferences(Collection $policies): array
    {
        return $policies->map(fn ($policy) => [
            'id' => $policy->id,
            'type' => 'life_insurance',
            'provider' => $policy->provider,
            'name' => $policy->policy_type,
            'cover' => round((float) $policy->sum_assured, 2),
        ])->values()->all();
    }

    private function incomePolicyReferences(Collection ...$policyGroups): array
    {
        $types = ['income_protection', 'disability', 'sickness_illness'];

        return collect($policyGroups)->flatMap(function (Collection $policies, int $index) use ($types) {
            return $policies->map(function ($policy) use ($types, $index) {
                $multiplier = match ($policy->benefit_frequency) {
                    'monthly' => 12,
                    'weekly' => 52,
                    default => 1,
                };

                return [
                    'id' => $policy->id,
                    'type' => $types[$index],
                    'provider' => $policy->provider,
                    'name' => $policy->policy_type ?? $policy->coverage_type ?? null,
                    'cover' => round((float) $policy->benefit_amount * $multiplier, 2),
                ];
            });
        })->values()->all();
    }
}
