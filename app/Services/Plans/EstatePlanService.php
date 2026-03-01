<?php

declare(strict_types=1);

namespace App\Services\Plans;

use App\Agents\EstateAgent;
use App\Models\Estate\Will;
use App\Models\LifeInsurancePolicy;
use App\Models\User;
use App\Services\Estate\IHTCalculationService;
use App\Services\TaxConfigService;

class EstatePlanService extends BasePlanService
{
    public function __construct(
        private readonly EstateAgent $estateAgent,
        private readonly IHTCalculationService $ihtCalculator,
        private readonly TaxConfigService $taxConfig,
        private readonly PlanConfigService $planConfig
    ) {}

    public function generatePlan(int $userId, array $options = []): array
    {
        $user = User::with(['spouse'])->findOrFail($userId);
        $completeness = $this->checkDataCompleteness($userId);

        // Gate check: age >= 35
        $currentAge = $user->date_of_birth
            ? (int) $user->date_of_birth->diffInYears(now())
            : null;

        if ($currentAge !== null && $currentAge < $this->planConfig->getEstateAgeGate()) {
            return [
                'metadata' => $this->buildPlanMetadata($user, 'estate', $completeness),
                'not_applicable' => true,
                'not_applicable_reason' => 'Estate planning typically becomes relevant from age 35 onwards. As you build assets and your financial situation evolves, this plan will help you manage your Inheritance Tax position.',
            ];
        }

        // Gate check: IHT liability > 0
        $ihtLiability = 0;
        try {
            $ihtResult = $this->ihtCalculator->calculate($user);
            $ihtLiability = $ihtResult['iht_liability'] ?? 0;
        } catch (\Exception $e) {
            // Continue with zero
        }

        if ($ihtLiability <= 0) {
            return [
                'metadata' => $this->buildPlanMetadata($user, 'estate', $completeness),
                'not_applicable' => true,
                'not_applicable_reason' => 'Based on your current estate position, there is no projected Inheritance Tax liability. If your circumstances change, this plan will provide mitigation strategies to protect your estate.',
            ];
        }

        // Run full analysis
        $analysis = $this->estateAgent->analyze($userId);
        $data = $analysis['data'] ?? [];

        if (! ($analysis['success'] ?? false)) {
            return [
                'metadata' => $this->buildPlanMetadata($user, 'estate', $completeness),
                'completeness_warning' => $this->buildCompletenessWarning($completeness),
                'executive_summary' => $this->buildEmptyExecutiveSummary(),
                'current_situation' => [],
                'actions' => [],
                'what_if' => [],
                'conclusion' => $this->generateDynamicConclusion([], [], 'estate'),
                'linked_goals' => [],
                'unlinked_goals' => [],
                'error' => $analysis['message'] ?? 'Unable to generate estate analysis.',
            ];
        }

        $currentSituation = $this->buildCurrentSituation($data);
        $recommendations = $this->getRecommendations($userId);
        $goals = $this->getGoalsForPlan($userId, 'estate');
        $goalRecommendations = $this->buildGoalRecommendations($goals['linked']);
        $allRecs = array_merge($goalRecommendations, $recommendations);
        $actions = $this->structureActions($allRecs, 'estate');
        $actions = $this->applyActionFilter($actions, $options);
        $enabledActions = collect($actions)->where('enabled', true)->values()->toArray();

        $whatIf = $this->buildWhatIfData($data, $enabledActions);
        $conclusion = $this->generateDynamicConclusion($currentSituation, $enabledActions, 'estate');

        return [
            'metadata' => $this->buildPlanMetadata($user, 'estate', $completeness),
            'completeness_warning' => $this->buildCompletenessWarning($completeness),
            'executive_summary' => $this->buildExecutiveSummary($user, $data),
            'current_situation' => $currentSituation,
            'actions' => $actions,
            'what_if' => $whatIf,
            'conclusion' => $conclusion,
            'linked_goals' => $goals['linked'],
            'unlinked_goals' => $goals['unlinked'],
        ];
    }

    public function getRecommendations(int $userId): array
    {
        $analysis = $this->estateAgent->analyze($userId);

        if (empty($analysis['data'] ?? [])) {
            return [];
        }

        $result = $this->estateAgent->generateRecommendations($analysis);

        return $result['data']['recommendations'] ?? $result['recommendations'] ?? [];
    }

    public function checkDataCompleteness(int $userId): array
    {
        $missing = [];

        $hasWill = Will::where('user_id', $userId)->exists();
        if (! $hasWill) {
            $missing[] = [
                'field' => 'will',
                'label' => 'Will',
                'description' => 'Add your will details for charitable bequest analysis.',
                'link' => '/estate',
            ];
        }

        $user = User::find($userId);
        $hasAssets = $user && (
            $user->properties()->exists() ||
            $user->investmentAccounts()->exists() ||
            $user->savingsAccounts()->exists()
        );
        if (! $hasAssets) {
            $missing[] = [
                'field' => 'estate_assets',
                'label' => 'Estate assets',
                'description' => 'Add your properties, savings, and other assets.',
                'link' => '/estate',
            ];
        }

        $hasLifeInsurance = LifeInsurancePolicy::where('user_id', $userId)->exists();
        if (! $hasLifeInsurance) {
            $missing[] = [
                'field' => 'life_insurance',
                'label' => 'Life insurance policies',
                'description' => 'Add your life insurance policies to analyse trust placement opportunities.',
                'link' => '/protection',
            ];
        }

        $total = 3;
        $present = $total - count($missing);

        return [
            'percentage' => (int) round(($present / $total) * 100),
            'missing' => $missing,
            'complete' => empty($missing),
        ];
    }

    private function buildExecutiveSummary(User $user, array $data): array
    {
        $firstName = $user->first_name ?? explode(' ', $user->name)[0] ?? 'there';
        $summary = $data['summary'] ?? [];
        $ihtCalc = $data['iht_calculation'] ?? [];
        $lifeCover = $data['life_cover'] ?? [];
        $profile = $data['profile'] ?? [];

        $grossEstate = $summary['gross_estate'] ?? 0;
        $netEstate = $summary['net_estate'] ?? 0;
        $ihtLiability = $summary['iht_liability'] ?? 0;
        $effectiveRate = $summary['effective_tax_rate'] ?? 0;

        $nrb = $ihtCalc['nrb_available'] ?? 0;
        $rnrb = $ihtCalc['rnrb_available'] ?? 0;
        $spouseExemption = $ihtCalc['spouse_net_estate'] ?? 0;

        $lines = [];
        $lines[] = "Dear {$firstName},";
        $lines[] = '';
        $lines[] = 'Thank you for using Fynla. Here is your personalised Estate Plan based on your assets, liabilities, and Inheritance Tax position.';
        $lines[] = '';

        // Estate overview
        $lines[] = sprintf(
            'Your gross estate is valued at %s with total liabilities of %s, giving a net estate of %s.',
            $this->formatCurrency($grossEstate),
            $this->formatCurrency($grossEstate - $netEstate),
            $this->formatCurrency($netEstate)
        );

        // IHT position
        $lines[] = sprintf(
            'Based on your current position, your estimated Inheritance Tax liability is %s, representing an effective tax rate of %s%% on your gross estate.',
            $this->formatCurrency($ihtLiability),
            number_format($effectiveRate, 1)
        );

        // Allowances
        $allowanceParts = [];
        if ($nrb > 0) {
            $allowanceParts[] = sprintf('a Nil Rate Band of %s', $this->formatCurrency($nrb));
        }
        if ($rnrb > 0) {
            $allowanceParts[] = sprintf('a Residence Nil Rate Band of %s', $this->formatCurrency($rnrb));
        }
        if (! empty($allowanceParts)) {
            $lines[] = sprintf('Your estate benefits from %s.', implode(' and ', $allowanceParts));
        }

        // Spouse exemption
        if ($spouseExemption > 0) {
            $lines[] = sprintf(
                'Spouse exemption of %s applies to assets passing to your spouse.',
                $this->formatCurrency($spouseExemption)
            );
        } elseif ($profile['has_spouse'] ?? false) {
            $lines[] = 'As a married individual, assets passing to your spouse are exempt from Inheritance Tax.';
        }

        // Life cover
        $coverInTrust = $lifeCover['total_cover_in_trust'] ?? 0;
        if ($coverInTrust > 0) {
            $lines[] = sprintf(
                'You have %s in life cover written in trust, which can help provide liquidity for any Inheritance Tax liability.',
                $this->formatCurrency($coverInTrust)
            );
        }

        // Recommendations count
        $analysis = $this->estateAgent->analyze($user->id);
        $recs = $this->estateAgent->generateRecommendations($analysis);
        $recCount = count($recs['data']['recommendations'] ?? []);
        if ($recCount > 0) {
            $lines[] = '';
            $lines[] = sprintf(
                'We have identified %d mitigation %s to help reduce your Inheritance Tax liability. The sections below detail your current estate position and specific actions you can take.',
                $recCount,
                $recCount === 1 ? 'strategy' : 'strategies'
            );
        }

        return [
            'narrative' => implode("\n", $lines),
            'key_metrics' => [],
        ];
    }

    private function buildEmptyExecutiveSummary(): array
    {
        return [
            'narrative' => 'Set up your Inheritance Tax profile and add your estate assets to receive a personalised estate plan.',
            'key_metrics' => [],
        ];
    }

    private function buildCurrentSituation(array $data): array
    {
        $summary = $data['summary'] ?? [];
        $ihtCalc = $data['iht_calculation'] ?? [];
        $assetBreakdown = $data['asset_breakdown'] ?? [];
        $lifeCover = $data['life_cover'] ?? [];
        $charitableAnalysis = $data['charitable_analysis'] ?? [];

        return [
            'estate_value' => [
                'gross' => $this->roundToPenny((float) ($summary['gross_estate'] ?? 0)),
                'net' => $this->roundToPenny((float) ($summary['net_estate'] ?? 0)),
                'liabilities' => $this->roundToPenny((float) ($summary['total_liabilities'] ?? 0)),
            ],
            'iht_calculation' => [
                'liability' => $this->roundToPenny((float) ($summary['iht_liability'] ?? 0)),
                'nil_rate_band' => $this->roundToPenny((float) ($ihtCalc['nrb_available'] ?? 0)),
                'residence_nil_rate_band' => $this->roundToPenny((float) ($ihtCalc['rnrb_available'] ?? 0)),
                'spouse_exemption' => $this->roundToPenny((float) ($ihtCalc['spouse_net_estate'] ?? 0)),
                'effective_rate' => round((float) ($summary['effective_tax_rate'] ?? 0), 1),
            ],
            'asset_breakdown' => [
                'liquid' => $this->roundToPenny((float) ($assetBreakdown['liquid'] ?? 0)),
                'semi_liquid' => $this->roundToPenny((float) ($assetBreakdown['semi_liquid'] ?? 0)),
                'illiquid' => $this->roundToPenny((float) ($assetBreakdown['illiquid'] ?? 0)),
            ],
            'life_cover' => [
                'cover_in_trust' => $this->roundToPenny((float) ($lifeCover['total_cover_in_trust'] ?? 0)),
                'cover_not_in_trust' => $this->roundToPenny((float) ($lifeCover['total_cover_not_in_trust'] ?? 0)),
                'policy_count' => ($lifeCover['policy_count'] ?? 0) + ($lifeCover['policies_not_in_trust_count'] ?? 0),
                'policies_in_trust' => $lifeCover['policy_count'] ?? 0,
                'policies_not_in_trust' => $lifeCover['policies_not_in_trust_count'] ?? 0,
            ],
            'charitable_giving' => [
                'status' => $charitableAnalysis['status'] ?? 'none',
                'current_percentage' => round((float) ($charitableAnalysis['current_percentage'] ?? 0), 1),
                'threshold' => $this->planConfig->getCharitableGivingThreshold(),
                'shortfall' => $this->roundToPenny((float) ($charitableAnalysis['shortfall'] ?? 0)),
                'potential_saving' => $this->roundToPenny((float) ($charitableAnalysis['potential_saving'] ?? 0)),
            ],
        ];
    }

    private function buildWhatIfData(array $data, array $enabledActions): array
    {
        $summary = $data['summary'] ?? [];
        $ihtLiability = (float) ($summary['iht_liability'] ?? 0);
        $netEstate = (float) ($summary['net_estate'] ?? 0);
        $grossEstate = (float) ($summary['gross_estate'] ?? 0);

        $currentToBeneficiaries = $netEstate - $ihtLiability;
        $currentEffectiveRate = $grossEstate > 0 ? ($ihtLiability / $grossEstate) * 100 : 0;

        // Calculate total mitigation from enabled actions
        $totalSavings = 0;
        $savingsMap = [];

        foreach ($enabledActions as $action) {
            $saving = (float) ($action['estimated_impact'] ?? 0);
            $savingsMap[$action['id']] = $saving;
            $totalSavings += $saving;
        }

        $projectedLiability = max(0, $ihtLiability - $totalSavings);
        $projectedToBeneficiaries = $netEstate - $projectedLiability;
        $projectedEffectiveRate = $grossEstate > 0 ? ($projectedLiability / $grossEstate) * 100 : 0;

        return [
            'current_scenario' => [
                'iht_liability' => $this->roundToPenny($ihtLiability),
                'effective_tax_rate' => round($currentEffectiveRate, 1),
                'estate_to_beneficiaries' => $this->roundToPenny($currentToBeneficiaries),
            ],
            'projected_scenario' => [
                'iht_liability' => $this->roundToPenny($projectedLiability),
                'effective_tax_rate' => round($projectedEffectiveRate, 1),
                'estate_to_beneficiaries' => $this->roundToPenny($projectedToBeneficiaries),
                'total_mitigation_savings' => $this->roundToPenny($totalSavings),
            ],
            'is_approximate' => true,
            'frontend_calc_params' => [
                'current_iht_liability' => $ihtLiability,
                'net_estate' => $netEstate,
                'gross_estate' => $grossEstate,
                'savings_map' => $savingsMap,
            ],
        ];
    }
}
