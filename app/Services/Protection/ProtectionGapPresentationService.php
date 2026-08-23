<?php

declare(strict_types=1);

namespace App\Services\Protection;

use App\Models\ProtectionProfile;
use App\Models\User;
use App\Services\TaxConfigService;
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
                [
                    'income_that_stops' => round((float) ($needs['income_that_stops'] ?? 0), 2),
                    'income_that_continues' => round((float) ($needs['income_that_continues'] ?? 0), 2),
                    'net_income_difference' => round((float) ($needs['net_income_difference'] ?? 0), 2),
                ],
                [[
                    'key' => 'sustainable_withdrawal_rate',
                    'value' => round((float) $this->taxConfig->get('protection.withdrawal_rates.human_capital', 0.047) * 100, 2),
                    'unit' => 'percent',
                ], [
                    'key' => 'allocation_priority',
                    'value' => 'Life cover remaining after debt protection',
                    'unit' => null,
                ]],
                'This estimates the capital needed to replace earned income that would stop, after continuing household income, using the configured sustainable withdrawal rate.',
                $lifePolicies,
            ),
            $this->category(
                'debt_protection',
                'Debt protection',
                (float) ($needs['debt_protection'] ?? 0),
                (float) ($gaps['coverage_allocated']['debt_covered'] ?? 0),
                (float) ($gaps['gaps_by_category']['debt_protection_gap'] ?? 0),
                [
                    'mortgage_balance' => round((float) ($profile->mortgage_balance ?? 0), 2),
                    'other_debts' => round((float) ($profile->other_debts ?? 0), 2),
                    'calculated_debt_need' => round((float) ($needs['debt_protection'] ?? 0), 2),
                ],
                [[
                    'key' => 'allocation_priority',
                    'value' => 'Life cover is allocated to debt first',
                    'unit' => null,
                ]],
                'This gap compares the canonical mortgage and other debt need with the life cover allocated to debt first.',
                $lifePolicies,
            ),
            $this->category(
                'final_expenses',
                'Final expenses',
                (float) ($needs['final_expenses'] ?? 0),
                (float) ($gaps['coverage_allocated']['final_expenses_covered'] ?? 0),
                (float) ($gaps['gaps_by_category']['final_expenses_gap'] ?? 0),
                ['configured_need' => round((float) ($needs['final_expenses'] ?? 0), 2)],
                [[
                    'key' => 'configured_final_expenses',
                    'value' => round((float) $this->taxConfig->get('protection.final_expenses', 7500), 2),
                    'unit' => 'GBP',
                ], [
                    'key' => 'allocation_priority',
                    'value' => 'After debt and income replacement capital',
                    'unit' => null,
                ]],
                'This allows for the configured final-expense amount and applies life cover left after higher-priority needs.',
                $lifePolicies,
            ),
            $this->category(
                'education_funding',
                'Education funding',
                (float) ($needs['education_funding'] ?? 0),
                (float) ($gaps['coverage_allocated']['education_covered'] ?? 0),
                (float) ($gaps['gaps_by_category']['education_funding_gap'] ?? 0),
                [
                    'number_of_dependants' => (int) ($profile->number_of_dependents ?? 0),
                    'dependant_ages' => $profile->dependents_ages ?? [],
                ],
                [[
                    'key' => 'annual_cost_per_child',
                    'value' => (float) $this->taxConfig->get('protection.education_cost_per_year', 9000),
                    'unit' => 'GBP',
                ], [
                    'key' => 'education_end_age',
                    'value' => 21,
                    'unit' => 'years',
                ]],
                'This estimates education funding to age 21 for each recorded dependant and applies any life cover left after earlier needs.',
                $lifePolicies,
            ),
            $this->category(
                'income_protection',
                'Income protection',
                (float) ($needs['income_protection_need'] ?? 0),
                (float) ($gaps['income_replacement_coverage'] ?? 0),
                (float) ($gaps['gaps_by_category']['income_protection_gap'] ?? 0),
                [
                    'gross_income' => round((float) ($needs['gross_income'] ?? 0), 2),
                    'state_benefits' => $needs['state_benefits'] ?? [],
                ],
                [[
                    'key' => 'maximum_benefit_ratio',
                    'value' => round((float) $this->taxConfig->get('protection.income_multipliers.income_protection_max_benefit', 0.60) * 100, 2),
                    'unit' => 'percent',
                ], [
                    'key' => 'coverage_basis',
                    'value' => 'Annualised recorded income, disability and sickness benefits',
                    'unit' => null,
                ]],
                'This compares the configured share of gross income with annualised recorded income-protection, disability and sickness benefits.',
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
