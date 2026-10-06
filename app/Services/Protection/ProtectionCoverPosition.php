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
 * need; critical illness and income protection are the needs the analyser
 * works out from protection.needs_calculation (item 8b). Income protection is monthly: the
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
        $incomeCover = (float) ($coverage['income_protection_coverage'] ?? 0)
            + (float) ($coverage['disability_coverage'] ?? 0)
            + (float) ($coverage['sickness_illness_coverage'] ?? 0);

        $needsConfig = $this->taxConfig->getProtectionNeeds();
        $multiple = rtrim(rtrim(number_format((float) $needsConfig['critical_illness']['income_multiple'], 2), '0'), '.');

        return [
            'life' => $this->position(
                (float) ($needs['total_need'] ?? 0),
                (float) ($coverage['life_coverage'] ?? 0),
                (float) ($employer['death_in_service'] ?? 0),
                'lump_sum',
                'Life cover',
                // D7: with no spending recorded the family's income is left out,
                // never guessed; say so where the need is shown.
                ($needs['income_replacement']['spending_recorded'] ?? true)
                    ? null
                    : 'Your monthly spending is not recorded, so this need covers your debts and final expenses only. Add your spending to include your family\'s income.',
            ),
            'critical_illness' => $this->position(
                (float) ($needs['critical_illness_need'] ?? 0),
                (float) ($coverage['critical_illness_coverage'] ?? 0),
                (float) ($employer['group_critical_illness'] ?? 0),
                'lump_sum',
                'Critical illness cover',
                // CSJ 2026-10-06 (D3): the user must know this need is a rule of thumb.
                'This need is a rule of thumb, not a set amount: '.$multiple.' times your gross earned income. Critical illness cover is usually set by what you can afford.',
            ),
            'income_protection' => $this->position(
                (float) ($needs['income_protection_need'] ?? 0) / 12,
                $incomeCover / 12,
                (float) ($employer['group_income_protection'] ?? 0) / 12,
                'monthly',
                'Income protection',
            ),
        ];
    }

    /** @return array<string, mixed> */
    private function position(float $need, float $total, float $employer, string $unit, string $label, ?string $basis = null): array
    {
        $threshold = (float) $this->taxConfig->getProtectionNeeds()['employer_cover']['reliance_share'];
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
            // The words every surface prints (web, /m and iOS each built these;
            // CSJ 2026-10-01: one figure, every surface).
            'label' => $label,
            // How the need is set, where the user must know it (critical illness).
            'basis' => $basis,
            'status_label' => $this->statusLabel($short, $over, $employer > 0 && $share > $threshold, $unit),
            // short: raspberry; attention (over, or relies on the job): violet; covered: spring.
            'tone' => $short > 0 ? 'short' : ($over > 0 || ($employer > 0 && $share > $threshold) ? 'attention' : 'covered'),
            'need_label' => $this->money($need, $unit),
            'own_cover_label' => $this->money(max(0.0, $total - $employer), $unit),
            'employer_cover_label' => $this->money($employer, $unit),
        ];
    }

    private function statusLabel(float $short, float $over, bool $dependsOnJob, string $unit): string
    {
        $parts = [];
        if ($short > 0) {
            $parts[] = 'Short by '.$this->money($short, $unit);
        }
        if ($over > 0) {
            $parts[] = 'Over by '.$this->money($over, $unit);
        }
        if ($dependsOnJob) {
            $parts[] = 'Depends on your job';
        }

        return $parts === [] ? 'Covered' : implode(', ', $parts);
    }

    private function money(float $value, string $unit): string
    {
        return '£'.number_format(round($value), 0).($unit === 'monthly' ? ' a month' : '');
    }
}
