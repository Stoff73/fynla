<?php

declare(strict_types=1);

namespace App\Services\Investment\Recommendation;

use App\Constants\TaxDefaults;
use App\Services\TaxConfigService;
use Illuminate\Support\Str;

/**
 * 7 spouse optimisation strategies for married/civil partnership users.
 *
 * Gate: user must be married/civil_partnership AND have a linked spouse.
 * All thresholds from TaxConfigService.
 */
class SpouseOptimisationService
{
    public function __construct(
        private readonly TaxConfigService $taxConfig
    ) {}

    /**
     * Assess all spouse optimisation opportunities.
     *
     * @param  array  $context  User context from UserContextBuilder
     * @return array{recommendations: array, strategies_triggered: int, strategies_total: int}
     */
    public function optimise(array $context): array
    {
        $spouseContext = $context['spouse'] ?? null;

        // Gate: must have linked spouse
        if ($spouseContext === null) {
            return [
                'recommendations' => [],
                'strategies_triggered' => 0,
                'strategies_total' => 7,
            ];
        }

        $recommendations = [];
        $triggered = 0;

        // Strategy 1: Capital Gains Tax sharing
        $s1 = $this->strategyCGTSharing($context, $spouseContext);
        if ($s1 !== null) {
            $recommendations[] = $s1;
            $triggered++;
        }

        // Strategy 2: ISA coordination
        $s2 = $this->strategyISACoordination($context, $spouseContext);
        if ($s2 !== null) {
            $recommendations[] = $s2;
            $triggered++;
        }

        // Strategy 3: Personal Savings Allowance optimisation
        $s3 = $this->strategyPSAOptimisation($context, $spouseContext);
        if ($s3 !== null) {
            $recommendations[] = $s3;
            $triggered++;
        }

        // Strategy 4: Pension coordination
        $s4 = $this->strategyPensionCoordination($context, $spouseContext);
        if ($s4 !== null) {
            $recommendations[] = $s4;
            $triggered++;
        }

        // Strategy 5: Non-earning spouse pension
        $s5 = $this->strategyNonEarningPension($context, $spouseContext);
        if ($s5 !== null) {
            $recommendations[] = $s5;
            $triggered++;
        }

        // Strategy 6: Marriage Allowance
        $s6 = $this->strategyMarriageAllowance($context, $spouseContext);
        if ($s6 !== null) {
            $recommendations[] = $s6;
            $triggered++;
        }

        // Strategy 7: Inheritance Tax planning
        $s7 = $this->strategyIHTPlan($context, $spouseContext);
        if ($s7 !== null) {
            $recommendations[] = $s7;
            $triggered++;
        }

        return [
            'recommendations' => $recommendations,
            'strategies_triggered' => $triggered,
            'strategies_total' => 7,
        ];
    }

    // ──────────────────────────────────────────────
    // Individual strategies
    // ──────────────────────────────────────────────

    /**
     * Strategy 1: Use both partners' Capital Gains Tax annual exemptions.
     */
    private function strategyCGTSharing(array $context, array $spouseContext): ?array
    {
        $giaAccounts = collect($context['portfolio']['accounts'] ?? [])
            ->where('type', 'gia');

        if ($giaAccounts->isEmpty()) {
            return null;
        }

        $cgtExempt = $context['allowances']['cgt_annual_exempt'] ?? TaxDefaults::CGT_ANNUAL_EXEMPT;
        $combinedExempt = $cgtExempt * 2;
        $giaValue = $giaAccounts->sum('value');

        // Only suggest if GIA holdings are significant
        if ($giaValue < $cgtExempt) {
            return null;
        }

        return $this->buildRecommendation(
            'cgt_sharing',
            'Share Capital Gains Tax exemptions between partners',
            sprintf(
                'You and your partner each have a %s annual Capital Gains Tax exemption — %s combined. Transferring assets between spouses is Capital Gains Tax-free. By spreading disposals across both exemptions, you can crystallise up to %s of gains tax-free each year.',
                number_format($cgtExempt, 0, '.', ','),
                number_format($combinedExempt, 0, '.', ','),
                number_format($combinedExempt, 0, '.', ',')
            ),
            sprintf('General Investment Account value: %s. Combined annual exemption: %s.', number_format($giaValue, 0, '.', ','), number_format($combinedExempt, 0, '.', ',')),
            'medium',
            (float) $combinedExempt
        );
    }

    /**
     * Strategy 2: Maximise combined ISA usage.
     */
    private function strategyISACoordination(array $context, array $spouseContext): ?array
    {
        $userIsaRemaining = $context['allowances']['isa_remaining'] ?? 0;
        $spouseIsaRemaining = $spouseContext['isa_remaining'] ?? 0;
        $combinedRemaining = $userIsaRemaining + $spouseIsaRemaining;
        $isaAllowance = $context['allowances']['isa_annual'] ?? TaxDefaults::ISA_ALLOWANCE;

        if ($combinedRemaining <= 0) {
            return null;
        }

        // Only suggest if either partner has unused allowance
        if ($userIsaRemaining <= 0 && $spouseIsaRemaining <= 0) {
            return null;
        }

        return $this->buildRecommendation(
            'isa_coordination',
            'Coordinate ISA contributions between partners',
            sprintf(
                'Between you and your partner, you have %s of combined ISA allowance remaining this tax year (you: %s, partner: %s). Coordinating contributions maximises your household\'s tax-free investment capacity of %s per year.',
                number_format($combinedRemaining, 0, '.', ','),
                number_format($userIsaRemaining, 0, '.', ','),
                number_format($spouseIsaRemaining, 0, '.', ','),
                number_format($isaAllowance * 2, 0, '.', ',')
            ),
            'Prioritise the higher-earning partner\'s ISA if funds are limited — tax savings are greater.',
            'high',
            $combinedRemaining
        );
    }

    /**
     * Strategy 3: Shift savings interest to lower-rate partner.
     */
    private function strategyPSAOptimisation(array $context, array $spouseContext): ?array
    {
        $userTaxBand = $context['financial']['tax_band'] ?? 'basic';
        $spouseTaxBand = $spouseContext['tax_band'] ?? 'basic';

        // Only relevant if partners are in different tax bands
        if ($userTaxBand === $spouseTaxBand) {
            return null;
        }

        $userPsa = $context['allowances']['psa'] ?? 0;
        $spousePsa = $spouseContext['psa'] ?? 0;

        // Determine who has the higher PSA (lower tax band)
        $lowerBandPartner = $userPsa >= $spousePsa ? 'you' : 'your partner';
        $higherPsa = max($userPsa, $spousePsa);
        $lowerPsa = min($userPsa, $spousePsa);

        if ($higherPsa <= $lowerPsa) {
            return null;
        }

        return $this->buildRecommendation(
            'psa_optimisation',
            'Optimise Personal Savings Allowance between partners',
            sprintf(
                'You and your partner are in different tax bands (%s rate vs %s rate). Holding more savings interest-bearing accounts in the name of the lower-rate partner (%s) makes better use of their higher %s Personal Savings Allowance.',
                $userTaxBand,
                $spouseTaxBand,
                $lowerBandPartner,
                number_format($higherPsa, 0, '.', ',')
            ),
            sprintf('Your Personal Savings Allowance: %s. Partner\'s: %s.', number_format($userPsa, 0, '.', ','), number_format($spousePsa, 0, '.', ',')),
            'low'
        );
    }

    /**
     * Strategy 4: Pension coordination — higher-rate partner gets priority.
     */
    private function strategyPensionCoordination(array $context, array $spouseContext): ?array
    {
        $userTaxBand = $context['financial']['tax_band'] ?? 'basic';
        $spouseTaxBand = $spouseContext['tax_band'] ?? 'basic';
        $userPensionRemaining = $context['allowances']['pension_remaining'] ?? 0;
        $spousePensionRemaining = $spouseContext['pension_remaining'] ?? 0;

        // Only suggest if both have remaining pension allowance and different bands
        if ($userPensionRemaining <= 0 && $spousePensionRemaining <= 0) {
            return null;
        }

        $taxBandOrder = ['non_taxpayer' => 0, 'basic' => 1, 'higher' => 2, 'additional' => 3];
        $userBandRank = $taxBandOrder[$userTaxBand] ?? 1;
        $spouseBandRank = $taxBandOrder[$spouseTaxBand] ?? 1;

        if ($userBandRank === $spouseBandRank) {
            return null; // Same band — no coordination advantage
        }

        $higherRatePartner = $userBandRank > $spouseBandRank ? 'you' : 'your partner';
        $higherBand = $userBandRank > $spouseBandRank ? $userTaxBand : $spouseTaxBand;
        $higherRemaining = $userBandRank > $spouseBandRank ? $userPensionRemaining : $spousePensionRemaining;

        $reliefRate = match ($higherBand) {
            'additional' => 45,
            'higher' => 40,
            default => 20,
        };

        return $this->buildRecommendation(
            'pension_coordination',
            'Prioritise pension contributions for higher-rate partner',
            sprintf(
                'Pension contributions for %s (%s rate taxpayer) receive %d%% tax relief compared to %d%% for the lower-rate partner. Prioritising contributions to the %s rate partner maximises the household tax benefit.',
                $higherRatePartner,
                $higherBand,
                $reliefRate,
                20,
                $higherBand
            ),
            sprintf('%s rate partner has %s pension allowance remaining.', ucfirst($higherBand), number_format($higherRemaining, 0, '.', ',')),
            'high'
        );
    }

    /**
     * Strategy 5: Non-earning spouse pension (£3,600 gross even with no income).
     */
    private function strategyNonEarningPension(array $context, array $spouseContext): ?array
    {
        $spouseIncome = $spouseContext['gross_income'] ?? 0;
        $userIncome = $context['financial']['gross_income'] ?? 0;

        // Check if either partner is non-earning
        $nonEarningPartner = null;
        if ($spouseIncome === 0.0 && $userIncome > 0) {
            $nonEarningPartner = 'partner';
        } elseif ($userIncome === 0.0 && $spouseIncome > 0) {
            $nonEarningPartner = 'you';
        }

        if ($nonEarningPartner === null) {
            return null;
        }

        // Non-earning spouse can contribute up to £3,600 gross (£2,880 net)
        $grossContribution = 3600;
        $netCost = 2880;
        $freeRelief = $grossContribution - $netCost;

        return $this->buildRecommendation(
            'non_earning_spouse_pension',
            'Pension contribution for non-earning partner',
            sprintf(
                'Even with no income, %s can receive pension contributions of up to %s gross per year. The government adds %s in basic rate tax relief on a net contribution of %s. This is effectively free money.',
                $nonEarningPartner === 'you' ? 'you' : 'your partner',
                number_format($grossContribution, 0, '.', ','),
                number_format($freeRelief, 0, '.', ','),
                number_format($netCost, 0, '.', ',')
            ),
            sprintf('Net cost: %s per year for %s gross pension contribution.', number_format($netCost, 0, '.', ','), number_format($grossContribution, 0, '.', ',')),
            'high',
            (float) $netCost
        );
    }

    /**
     * Strategy 6: Marriage Allowance (transfer 10% of Personal Allowance).
     */
    private function strategyMarriageAllowance(array $context, array $spouseContext): ?array
    {
        $userTaxBand = $context['financial']['tax_band'] ?? 'basic';
        $spouseTaxBand = $spouseContext['tax_band'] ?? 'basic';
        $userIncome = $context['financial']['gross_income'] ?? 0;
        $spouseIncome = $spouseContext['gross_income'] ?? 0;

        // Marriage Allowance: non-taxpayer transfers 10% of PA to basic rate partner
        $personalAllowance = TaxDefaults::PERSONAL_ALLOWANCE;
        $transferable = (int) ($personalAllowance * 0.10);

        $eligible = false;
        $direction = '';

        if ($userTaxBand === 'non_taxpayer' && $spouseTaxBand === 'basic') {
            $eligible = true;
            $direction = sprintf('You transfer %s of your Personal Allowance to your partner.', number_format($transferable, 0, '.', ','));
        } elseif ($spouseTaxBand === 'non_taxpayer' && $userTaxBand === 'basic') {
            $eligible = true;
            $direction = sprintf('Your partner transfers %s of their Personal Allowance to you.', number_format($transferable, 0, '.', ','));
        }

        if (! $eligible) {
            return null;
        }

        $annualSaving = $transferable * 0.20; // 20% basic rate saving

        return $this->buildRecommendation(
            'marriage_allowance',
            'Claim Marriage Allowance',
            sprintf(
                'Marriage Allowance lets a non-taxpayer transfer %s of their Personal Allowance to a basic rate taxpayer partner. %s This saves %s per year in income tax.',
                number_format($transferable, 0, '.', ','),
                $direction,
                number_format($annualSaving, 0, '.', ',')
            ),
            sprintf('Annual tax saving: %s. Apply online at gov.uk — can be backdated up to 4 years.', number_format($annualSaving, 0, '.', ',')),
            'high',
            $annualSaving
        );
    }

    /**
     * Strategy 7: Inheritance Tax planning (equalise estates).
     */
    private function strategyIHTPlan(array $context, array $spouseContext): ?array
    {
        $portfolioValue = $context['portfolio']['total_value'] ?? 0;

        // Rough combined estate estimate
        $nrb = $this->taxConfig->getInheritanceTax()['nil_rate_band'] ?? TaxDefaults::NRB;
        $rnrb = $this->taxConfig->getInheritanceTax()['residence_nil_rate_band'] ?? TaxDefaults::RNRB;
        $combinedNilRate = ($nrb + $rnrb) * 2; // Both partners' allowances

        // Only flag if portfolio alone is significant (property not counted here)
        if ($portfolioValue < $nrb) {
            return null;
        }

        return $this->buildRecommendation(
            'iht_estate_equalisation',
            'Consider Inheritance Tax estate equalisation',
            sprintf(
                'With investment assets of %s, consider how your combined estate compares to the combined Nil Rate Band of %s. Equalising assets between partners ensures both Nil Rate Bands and Residence Nil Rate Bands are fully utilised.',
                number_format($portfolioValue, 0, '.', ','),
                number_format($combinedNilRate, 0, '.', ',')
            ),
            'Review your estate planning in the Estate module for a comprehensive Inheritance Tax assessment.',
            'low'
        );
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    /**
     * Build a standard spouse optimisation recommendation.
     */
    private function buildRecommendation(
        string $strategyType,
        string $headline,
        string $explanation,
        string $personalContext,
        string $priority,
        ?float $amount = null
    ): array {
        $rec = [
            'id' => (string) Str::uuid(),
            'source' => 'spouse',
            'strategy_type' => $strategyType,
            'headline' => $headline,
            'explanation' => $explanation,
            'personal_context' => $personalContext,
            'priority' => $priority,
        ];

        if ($amount !== null) {
            $rec['amount'] = round($amount, 2);
        }

        return $rec;
    }
}
