<?php

declare(strict_types=1);

namespace App\Services\Protection;

use App\Models\ProtectionProfile;
use App\Models\User;
use App\Services\TaxConfigService;

/**
 * The ONE calculation of where a user stands on each kind of cover (CSJ
 * 2026-09-29): what they need, what their own policies and their job give
 * them, and whether they are short or over. The Protection page and the
 * protection cards both read it, so their figures cannot drift (Rule 20).
 *
 * Needs are the Protection page's own (CoverageGapAnalyzer): life is the total
 * need; critical illness is gross earned income times
 * protection.income_multipliers.critical_illness; income protection is the
 * configured share of gross earned income. Income protection is monthly: the
 * analyser holds it and group income protection as annual amounts, divided by
 * 12 here, once.
 */
final class ProtectionCoverPosition
{
    public const TYPES = ['life', 'critical_illness', 'income_protection'];

    public function __construct(
        private readonly TaxConfigService $taxConfig,
        private readonly CoverageGapAnalyzer $gapAnalyzer,
        private readonly LifeCoverReach $lifeCoverReach,
    ) {}

    /** @return array<string, array<string, mixed>> */
    public function forUser(User $user): array
    {
        $profile = ProtectionProfile::where('user_id', $user->id)->first();
        if ($profile === null) {
            return [];
        }
        $user->loadMissing(['criticalIllnessPolicies', 'incomeProtectionPolicies', 'disabilityPolicies', 'sicknessIllnessPolicies']);
        $coverage = $this->gapAnalyzer->calculateTotalCoverage(
            $this->lifeCoverReach->policiesCovering($user),
            $user->criticalIllnessPolicies,
            $user->incomeProtectionPolicies,
            $user->disabilityPolicies,
            $user->sicknessIllnessPolicies,
            $profile,
            $user,
        );

        return $this->fromAnalysis($this->gapAnalyzer->calculateProtectionNeeds($profile), $coverage);
    }

    /**
     * @param  array<string, mixed>  $needs  CoverageGapAnalyzer::calculateProtectionNeeds
     * @param  array<string, mixed>  $coverage  CoverageGapAnalyzer::calculateTotalCoverage
     * @return array<string, array<string, mixed>>
     */
    public function fromAnalysis(array $needs, array $coverage): array
    {
        $employer = (array) ($coverage['employer_benefits'] ?? []);
        $gross = (float) ($needs['gross_income'] ?? 0);
        $incomeCover = (float) ($coverage['income_protection_coverage'] ?? 0)
            + (float) ($coverage['disability_coverage'] ?? 0)
            + (float) ($coverage['sickness_illness_coverage'] ?? 0);

        return [
            'life' => $this->position(
                (float) ($needs['total_need'] ?? 0),
                (float) ($coverage['life_coverage'] ?? 0),
                (float) ($employer['death_in_service'] ?? 0),
                'lump_sum',
            ),
            'critical_illness' => $this->position(
                $gross * (float) $this->taxConfig->get('protection.income_multipliers.critical_illness'),
                (float) ($coverage['critical_illness_coverage'] ?? 0),
                (float) ($employer['group_critical_illness'] ?? 0),
                'lump_sum',
            ),
            'income_protection' => $this->position(
                (float) ($needs['income_protection_need'] ?? 0) / 12,
                $incomeCover / 12,
                (float) ($employer['group_income_protection'] ?? 0) / 12,
                'monthly',
            ),
        ];
    }

    /** @return array<string, mixed> */
    private function position(float $need, float $total, float $employer, string $unit): array
    {
        $threshold = (float) $this->taxConfig->get('protection.dis_reliance_percent');
        $share = $total > 0 ? $employer / $total : 0.0;
        $short = max(0.0, $need - $total);
        // An unknown need (no income recorded) is not a reason to call cover excess.
        $over = $need > 0 ? max(0.0, $total - $need) : 0.0;

        return [
            'need' => round($need, 2),
            'own_cover' => round(max(0.0, $total - $employer), 2),
            'employer_cover' => round($employer, 2),
            'total_cover' => round($total, 2),
            'short_by' => round($short, 2),
            'over_by' => round($over, 2),
            'employer_share' => round($share, 4),
            'depends_on_job' => $employer > 0 && $share > $threshold,
            'status' => $short > 0 ? 'short' : ($over > 0 ? 'over' : 'covered'),
            'unit' => $unit,
        ];
    }
}
