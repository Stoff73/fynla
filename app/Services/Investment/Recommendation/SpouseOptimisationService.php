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
        $giaValue = $giaAccounts->sum('value');
        $cgtExempt = $context['allowances']['cgt_annual_exempt'] ?? TaxDefaults::CGT_ANNUAL_EXEMPT;
        $combinedExempt = $cgtExempt * 2;

        $spouseName = $spouseContext['name'] ?? 'Partner';
        $userTaxBand = $context['financial']['tax_band'] ?? 'basic';
        $spouseTaxBand = $spouseContext['tax_band'] ?? 'basic';
        $maritalStatus = $context['personal']['marital_status'] ?? 'married';

        $giaAccountNames = $giaAccounts->map(fn ($a) => ($a['name'] ?? 'Unnamed').' at '.($a['provider'] ?? 'Unknown').' (£'.number_format($a['value'] ?? 0, 0).')')->implode('; ');

        $trace = [];

        $trace[] = [
            'question' => 'What is the couple\'s relationship and tax position?',
            'data_field' => 'personal.marital_status + financial.tax_band + spouse.tax_band',
            'data_value' => ucfirst(str_replace('_', ' ', $maritalStatus)).'. Primary: '.$userTaxBand.' rate. '.$spouseName.': '.$spouseTaxBand.' rate.',
            'threshold' => 'N/A',
            'passed' => true,
            'explanation' => ucfirst(str_replace('_', ' ', $maritalStatus)).' couple. Inter-spouse asset transfers are Capital Gains Tax-free, enabling both annual exemptions to be used.',
        ];

        $trace[] = [
            'question' => 'Are there General Investment Account holdings to share?',
            'data_field' => 'portfolio.accounts (type=gia)',
            'data_value' => $giaAccounts->count().' account(s), total £'.number_format($giaValue, 0),
            'threshold' => 'More than £0',
            'passed' => $giaAccounts->isNotEmpty(),
            'explanation' => $giaAccounts->isNotEmpty()
                ? 'General Investment Account holdings: '.$giaAccountNames.'. Total: £'.number_format($giaValue, 0).'.'
                : 'No General Investment Account holdings — Capital Gains Tax sharing not applicable.',
        ];

        $trace[] = [
            'question' => 'Are the holdings significant enough to benefit from sharing both exemptions?',
            'data_field' => 'gia_value vs cgt_annual_exempt',
            'data_value' => '£'.number_format($giaValue, 0).' held vs £'.number_format($cgtExempt, 0).' single exemption',
            'threshold' => 'Holdings must exceed one partner\'s exemption (£'.number_format($cgtExempt, 0).')',
            'passed' => $giaValue >= $cgtExempt,
            'explanation' => $giaValue >= $cgtExempt
                ? 'Holdings of £'.number_format($giaValue, 0).' exceed one partner\'s £'.number_format($cgtExempt, 0).' exemption — sharing doubles the tax-free amount to £'.number_format($combinedExempt, 0).'.'
                : 'Holdings of £'.number_format($giaValue, 0).' are below the single exemption of £'.number_format($cgtExempt, 0).' — sharing provides no additional benefit.',
        ];

        if ($giaAccounts->isEmpty()) {
            return null;
        }

        if ($giaValue < $cgtExempt) {
            return null;
        }

        $rec = $this->buildRecommendation(
            'cgt_sharing',
            'Share Capital Gains Tax exemptions between partners',
            sprintf(
                'Each partner has a £%s annual Capital Gains Tax exemption — £%s combined. With £%s in General Investment Accounts, transferring assets between spouses (Capital Gains Tax-free) allows both exemptions to be used. This enables crystallising up to £%s of gains tax-free each year.',
                number_format($cgtExempt, 0, '.', ','),
                number_format($combinedExempt, 0, '.', ','),
                number_format($giaValue, 0, '.', ','),
                number_format($combinedExempt, 0, '.', ',')
            ),
            sprintf('General Investment Account value: £%s. Combined annual exemption: £%s.', number_format($giaValue, 0, '.', ','), number_format($combinedExempt, 0, '.', ',')),
            'medium',
            (float) $combinedExempt
        );
        $rec['decision_trace'] = $trace;

        return $rec;
    }

    /**
     * Strategy 2: Maximise combined ISA usage.
     */
    private function strategyISACoordination(array $context, array $spouseContext): ?array
    {
        $userIsaRemaining = $context['allowances']['isa_remaining'] ?? 0;
        $userIsaUsed = $context['allowances']['isa_used'] ?? 0;
        $spouseIsaRemaining = $spouseContext['isa_remaining'] ?? 0;
        $combinedRemaining = $userIsaRemaining + $spouseIsaRemaining;
        $isaAllowance = $context['allowances']['isa_annual'] ?? TaxDefaults::ISA_ALLOWANCE;
        $combinedAllowance = $isaAllowance * 2;

        $spouseName = $spouseContext['name'] ?? 'Partner';
        $userTaxBand = $context['financial']['tax_band'] ?? 'basic';
        $spouseTaxBand = $spouseContext['tax_band'] ?? 'basic';

        $trace = [];

        $trace[] = [
            'question' => 'What is each partner\'s ISA allowance position this tax year?',
            'data_field' => 'allowances.isa_remaining + spouse.isa_remaining',
            'data_value' => 'Primary: £'.number_format($userIsaRemaining, 0).' remaining (£'.number_format($userIsaUsed, 0).' used). '.$spouseName.': £'.number_format($spouseIsaRemaining, 0).' remaining.',
            'threshold' => 'Combined remaining must be above £0',
            'passed' => $combinedRemaining > 0,
            'explanation' => $combinedRemaining > 0
                ? '£'.number_format($combinedRemaining, 0).' combined ISA allowance remaining out of £'.number_format($combinedAllowance, 0).' total household allowance (£'.number_format($isaAllowance, 0).' each).'
                : 'Both partners have fully used their ISA allowance this tax year — no further contributions possible.',
        ];

        $trace[] = [
            'question' => 'Which partner should be prioritised for ISA contributions?',
            'data_field' => 'financial.tax_band + spouse.tax_band',
            'data_value' => 'Primary: '.$userTaxBand.' rate. '.$spouseName.': '.$spouseTaxBand.' rate.',
            'threshold' => 'N/A',
            'passed' => true,
            'explanation' => $userTaxBand !== $spouseTaxBand
                ? 'Different tax bands — prioritise the higher-rate partner\'s ISA to shelter more tax.'
                : 'Same tax band — either partner\'s ISA provides equal tax benefit.',
        ];

        if ($combinedRemaining <= 0) {
            return null;
        }

        // Only suggest if either partner has unused allowance
        if ($userIsaRemaining <= 0 && $spouseIsaRemaining <= 0) {
            return null;
        }

        $rec = $this->buildRecommendation(
            'isa_coordination',
            'Coordinate ISA contributions between partners',
            sprintf(
                '£%s of combined ISA allowance remains this tax year (primary: £%s remaining, %s: £%s remaining). Coordinating contributions maximises the household\'s tax-free investment capacity of £%s per year.',
                number_format($combinedRemaining, 0, '.', ','),
                number_format($userIsaRemaining, 0, '.', ','),
                $spouseName,
                number_format($spouseIsaRemaining, 0, '.', ','),
                number_format($combinedAllowance, 0, '.', ',')
            ),
            'Prioritise the higher-earning partner\'s ISA if funds are limited — tax savings are greater.',
            'high',
            $combinedRemaining
        );
        $rec['decision_trace'] = $trace;

        return $rec;
    }

    /**
     * Strategy 3: Shift savings interest to lower-rate partner.
     */
    private function strategyPSAOptimisation(array $context, array $spouseContext): ?array
    {
        $userTaxBand = $context['financial']['tax_band'] ?? 'basic';
        $spouseTaxBand = $spouseContext['tax_band'] ?? 'basic';
        $userPsa = $context['allowances']['psa'] ?? 0;
        $spousePsa = $spouseContext['psa'] ?? 0;

        $spouseName = $spouseContext['name'] ?? 'Partner';
        $userGrossIncome = $context['financial']['gross_income'] ?? 0;
        $spouseGrossIncome = $spouseContext['gross_income'] ?? 0;

        $lowerBandPartner = $userPsa >= $spousePsa ? 'the primary holder' : $spouseName;
        $higherPsa = max($userPsa, $spousePsa);
        $lowerPsa = min($userPsa, $spousePsa);

        $trace = [];

        $trace[] = [
            'question' => 'Are the partners in different tax bands?',
            'data_field' => 'financial.tax_band + spouse.tax_band',
            'data_value' => 'Primary: '.$userTaxBand.' rate (£'.number_format($userGrossIncome, 0).' gross). '.$spouseName.': '.$spouseTaxBand.' rate (£'.number_format($spouseGrossIncome, 0).' gross).',
            'threshold' => 'Different bands',
            'passed' => $userTaxBand !== $spouseTaxBand,
            'explanation' => $userTaxBand !== $spouseTaxBand
                ? 'Different tax bands: '.$userTaxBand.' rate vs '.$spouseTaxBand.' rate. The lower-rate partner has a larger Personal Savings Allowance — optimisation is possible.'
                : 'Both partners are '.$userTaxBand.' rate taxpayers — same Personal Savings Allowance, no optimisation advantage.',
        ];

        $trace[] = [
            'question' => 'What are the Personal Savings Allowances for each partner?',
            'data_field' => 'allowances.psa + spouse.psa',
            'data_value' => 'Primary: £'.number_format($userPsa, 0).' ('.$userTaxBand.' rate). '.$spouseName.': £'.number_format($spousePsa, 0).' ('.$spouseTaxBand.' rate).',
            'threshold' => 'Higher must exceed lower',
            'passed' => $higherPsa > $lowerPsa,
            'explanation' => $higherPsa > $lowerPsa
                ? 'The lower-rate partner ('.$lowerBandPartner.') has a £'.number_format($higherPsa, 0).' Personal Savings Allowance vs £'.number_format($lowerPsa, 0).'. Holding more savings interest in '.$lowerBandPartner.'\'s name uses the larger allowance.'
                : 'Personal Savings Allowances are equal at £'.number_format($higherPsa, 0).' each — no optimisation benefit.',
        ];

        // Only relevant if partners are in different tax bands
        if ($userTaxBand === $spouseTaxBand) {
            return null;
        }

        if ($higherPsa <= $lowerPsa) {
            return null;
        }

        $rec = $this->buildRecommendation(
            'psa_optimisation',
            'Optimise Personal Savings Allowance between partners',
            sprintf(
                'The primary holder (%s rate, £%s Personal Savings Allowance) and %s (%s rate, £%s Personal Savings Allowance) have different allowances. Holding more savings interest-bearing accounts in the name of the lower-rate partner makes better use of their higher allowance.',
                $userTaxBand,
                number_format($userPsa, 0, '.', ','),
                $spouseName,
                $spouseTaxBand,
                number_format($spousePsa, 0, '.', ',')
            ),
            sprintf('Primary Personal Savings Allowance: £%s. %s\'s: £%s.', number_format($userPsa, 0, '.', ','), $spouseName, number_format($spousePsa, 0, '.', ',')),
            'low'
        );
        $rec['decision_trace'] = $trace;

        return $rec;
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

        $spouseName = $spouseContext['name'] ?? 'Partner';
        $userGrossIncome = $context['financial']['gross_income'] ?? 0;
        $spouseGrossIncome = $spouseContext['gross_income'] ?? 0;
        $pensionAnnualAllowance = $context['allowances']['pension_annual_allowance'] ?? TaxDefaults::PENSION_ANNUAL_ALLOWANCE;

        $taxBandOrder = ['non_taxpayer' => 0, 'basic' => 1, 'higher' => 2, 'additional' => 3];
        $userBandRank = $taxBandOrder[$userTaxBand] ?? 1;
        $spouseBandRank = $taxBandOrder[$spouseTaxBand] ?? 1;

        $higherRatePartner = $userBandRank > $spouseBandRank ? 'the primary holder' : $spouseName;
        $higherBand = $userBandRank > $spouseBandRank ? $userTaxBand : $spouseTaxBand;
        $higherRemaining = $userBandRank > $spouseBandRank ? $userPensionRemaining : $spousePensionRemaining;

        $reliefRate = match ($higherBand) {
            'additional' => 45,
            'higher' => 40,
            default => 20,
        };

        $lowerReliefRate = match ($userBandRank <= $spouseBandRank ? $userTaxBand : $spouseTaxBand) {
            'additional' => 45,
            'higher' => 40,
            default => 20,
        };

        $trace = [];

        $trace[] = [
            'question' => 'Does either partner have remaining pension allowance?',
            'data_field' => 'allowances.pension_remaining + spouse.pension_remaining',
            'data_value' => 'Primary: £'.number_format($userPensionRemaining, 0).' of £'.number_format($pensionAnnualAllowance, 0).' remaining. '.$spouseName.': £'.number_format($spousePensionRemaining, 0).' remaining.',
            'threshold' => 'At least one partner above £0',
            'passed' => $userPensionRemaining > 0 || $spousePensionRemaining > 0,
            'explanation' => ($userPensionRemaining > 0 || $spousePensionRemaining > 0)
                ? 'Pension allowance remaining — coordination opportunity exists to maximise tax relief.'
                : 'Both partners have fully used their pension Annual Allowance — no coordination opportunity.',
        ];

        $trace[] = [
            'question' => 'Are the partners in different tax bands for pension coordination?',
            'data_field' => 'financial.tax_band + spouse.tax_band',
            'data_value' => 'Primary: '.$userTaxBand.' rate (£'.number_format($userGrossIncome, 0).' gross). '.$spouseName.': '.$spouseTaxBand.' rate (£'.number_format($spouseGrossIncome, 0).' gross).',
            'threshold' => 'Different tax bands',
            'passed' => $userBandRank !== $spouseBandRank,
            'explanation' => $userBandRank !== $spouseBandRank
                ? 'Higher-rate partner ('.$higherRatePartner.') receives '.$reliefRate.'% tax relief vs '.$lowerReliefRate.'% for the lower-rate partner. Prioritising the higher-rate partner\'s pension saves '.($reliefRate - $lowerReliefRate).' pence per £1 contributed.'
                : 'Same tax band ('.$userTaxBand.' rate each at '.$reliefRate.'% relief) — no coordination advantage from tax rate difference.',
        ];

        // Only suggest if both have remaining pension allowance and different bands
        if ($userPensionRemaining <= 0 && $spousePensionRemaining <= 0) {
            return null;
        }

        if ($userBandRank === $spouseBandRank) {
            return null; // Same band — no coordination advantage
        }

        $rec = $this->buildRecommendation(
            'pension_coordination',
            'Prioritise pension contributions for higher-rate partner',
            sprintf(
                'Pension contributions for %s (%s rate taxpayer, £%s gross income) receive %d%% tax relief compared to %d%% for the lower-rate partner. %s has £%s pension allowance remaining. Prioritising the %s rate partner maximises the household tax benefit.',
                $higherRatePartner,
                $higherBand,
                number_format($userBandRank > $spouseBandRank ? $userGrossIncome : $spouseGrossIncome, 0, '.', ','),
                $reliefRate,
                $lowerReliefRate,
                $higherRatePartner,
                number_format($higherRemaining, 0, '.', ','),
                $higherBand
            ),
            sprintf('%s rate partner has £%s pension allowance remaining.', ucfirst($higherBand), number_format($higherRemaining, 0, '.', ',')),
            'high'
        );
        $rec['decision_trace'] = $trace;

        return $rec;
    }

    /**
     * Strategy 5: Non-earning spouse pension (£3,600 gross even with no income).
     */
    private function strategyNonEarningPension(array $context, array $spouseContext): ?array
    {
        $spouseIncome = $spouseContext['gross_income'] ?? 0;
        $userIncome = $context['financial']['gross_income'] ?? 0;

        $spouseName = $spouseContext['name'] ?? 'Partner';
        $spouseEmployment = $spouseContext['employment_status'] ?? 'unknown';
        $userEmployment = $context['personal']['employment_status'] ?? 'unknown';

        // Check if either partner is non-earning
        $nonEarningPartner = null;
        $nonEarningName = '';
        $earningPartnerIncome = 0;
        if ($spouseIncome === 0.0 && $userIncome > 0) {
            $nonEarningPartner = 'partner';
            $nonEarningName = $spouseName;
            $earningPartnerIncome = $userIncome;
        } elseif ($userIncome === 0.0 && $spouseIncome > 0) {
            $nonEarningPartner = 'you';
            $nonEarningName = 'the primary holder';
            $earningPartnerIncome = $spouseIncome;
        }

        $trace = [];

        $trace[] = [
            'question' => 'Is either partner a non-earner who could benefit from a pension contribution?',
            'data_field' => 'financial.gross_income + spouse.gross_income',
            'data_value' => 'Primary: £'.number_format($userIncome, 0).' ('.str_replace('_', ' ', $userEmployment).'). '.$spouseName.': £'.number_format($spouseIncome, 0).' ('.str_replace('_', ' ', $spouseEmployment).').',
            'threshold' => 'One partner earning £0',
            'passed' => $nonEarningPartner !== null,
            'explanation' => $nonEarningPartner !== null
                ? $nonEarningName.' has no income but can still receive pension contributions of up to £3,600 gross per year with government basic rate tax relief.'
                : 'Both partners have income — non-earning pension strategy does not apply.',
        ];

        if ($nonEarningPartner === null) {
            return null;
        }

        // Non-earning spouse can contribute up to £3,600 gross (£2,880 net)
        $grossContribution = 3600;
        $netCost = 2880;
        $freeRelief = $grossContribution - $netCost;

        $trace[] = [
            'question' => 'What is the maximum gross pension contribution for a non-earner?',
            'data_field' => 'pension rules',
            'data_value' => '£'.number_format($grossContribution, 0).' gross (£'.number_format($netCost, 0).' net + £'.number_format($freeRelief, 0).' government relief)',
            'threshold' => '£'.number_format($grossContribution, 0).' maximum',
            'passed' => true,
            'explanation' => 'A net contribution of £'.number_format($netCost, 0).' from the earning partner (income £'.number_format($earningPartnerIncome, 0).') becomes £'.number_format($grossContribution, 0).' in '.$nonEarningName.'\'s pension with £'.number_format($freeRelief, 0).' in government basic rate tax relief. This is effectively free money — a '.round(($freeRelief / $netCost) * 100).'% return.',
        ];

        $rec = $this->buildRecommendation(
            'non_earning_spouse_pension',
            'Pension contribution for non-earning partner',
            sprintf(
                'Even with no income, %s can receive pension contributions of up to £%s gross per year. The government adds £%s in basic rate tax relief on a net contribution of £%s. This is effectively free money.',
                $nonEarningPartner === 'you' ? 'the primary holder' : $spouseName,
                number_format($grossContribution, 0, '.', ','),
                number_format($freeRelief, 0, '.', ','),
                number_format($netCost, 0, '.', ',')
            ),
            sprintf('Net cost: £%s per year for £%s gross pension contribution.', number_format($netCost, 0, '.', ','), number_format($grossContribution, 0, '.', ',')),
            'high',
            (float) $netCost
        );
        $rec['decision_trace'] = $trace;

        return $rec;
    }

    /**
     * Strategy 6: Marriage Allowance (transfer 10% of Personal Allowance).
     */
    private function strategyMarriageAllowance(array $context, array $spouseContext): ?array
    {
        $userTaxBand = $context['financial']['tax_band'] ?? 'basic';
        $spouseTaxBand = $spouseContext['tax_band'] ?? 'basic';
        $userGrossIncome = $context['financial']['gross_income'] ?? 0;
        $spouseGrossIncome = $spouseContext['gross_income'] ?? 0;

        $spouseName = $spouseContext['name'] ?? 'Partner';

        // Marriage Allowance: non-taxpayer transfers 10% of PA to basic rate partner
        $personalAllowance = TaxDefaults::PERSONAL_ALLOWANCE;
        $transferable = (int) ($personalAllowance * 0.10);

        $eligible = false;
        $direction = '';

        if ($userTaxBand === 'non_taxpayer' && $spouseTaxBand === 'basic') {
            $eligible = true;
            $direction = sprintf('The primary holder (income £%s) transfers £%s of their unused Personal Allowance to %s (%s rate, income £%s).', number_format($userGrossIncome, 0, '.', ','), number_format($transferable, 0, '.', ','), $spouseName, $spouseTaxBand, number_format($spouseGrossIncome, 0, '.', ','));
        } elseif ($spouseTaxBand === 'non_taxpayer' && $userTaxBand === 'basic') {
            $eligible = true;
            $direction = sprintf('%s (income £%s) transfers £%s of their unused Personal Allowance to the primary holder (%s rate, income £%s).', $spouseName, number_format($spouseGrossIncome, 0, '.', ','), number_format($transferable, 0, '.', ','), $userTaxBand, number_format($userGrossIncome, 0, '.', ','));
        }

        $annualSaving = $transferable * 0.20; // 20% basic rate saving

        $trace = [];

        $trace[] = [
            'question' => 'Is one partner a non-taxpayer and the other a basic rate taxpayer?',
            'data_field' => 'financial.tax_band + spouse.tax_band',
            'data_value' => 'Primary: '.$userTaxBand.' rate (£'.number_format($userGrossIncome, 0).' gross). '.$spouseName.': '.$spouseTaxBand.' rate (£'.number_format($spouseGrossIncome, 0).' gross).',
            'threshold' => 'One non_taxpayer + one basic rate',
            'passed' => $eligible,
            'explanation' => $eligible
                ? $direction.' Annual tax saving: £'.number_format($annualSaving, 0).' (£'.number_format($transferable, 0).' × 20% basic rate).'
                : 'Marriage Allowance requires one non-taxpayer and one basic rate taxpayer. Current bands ('.$userTaxBand.' and '.$spouseTaxBand.') do not qualify.',
        ];

        if (! $eligible) {
            return null;
        }

        $rec = $this->buildRecommendation(
            'marriage_allowance',
            'Claim Marriage Allowance',
            sprintf(
                'Marriage Allowance lets a non-taxpayer transfer £%s of their Personal Allowance to a basic rate taxpayer partner. %s This saves £%s per year in income tax.',
                number_format($transferable, 0, '.', ','),
                $direction,
                number_format($annualSaving, 0, '.', ',')
            ),
            sprintf('Annual tax saving: £%s. Apply online at gov.uk — can be backdated up to 4 years (potential total saving: £%s).', number_format($annualSaving, 0, '.', ','), number_format($annualSaving * 4, 0, '.', ',')),
            'high',
            $annualSaving
        );
        $rec['decision_trace'] = $trace;

        return $rec;
    }

    /**
     * Strategy 7: Inheritance Tax planning (equalise estates).
     */
    private function strategyIHTPlan(array $context, array $spouseContext): ?array
    {
        $portfolioValue = $context['portfolio']['total_value'] ?? 0;

        $spouseName = $spouseContext['name'] ?? 'Partner';
        $userAge = $context['personal']['age'] ?? null;
        $spouseAge = $spouseContext['age'] ?? null;

        // Rough combined estate estimate
        $nrb = $this->taxConfig->getInheritanceTax()['nil_rate_band'] ?? TaxDefaults::NRB;
        $rnrb = $this->taxConfig->getInheritanceTax()['residence_nil_rate_band'] ?? TaxDefaults::RNRB;
        $combinedNilRate = ($nrb + $rnrb) * 2; // Both partners' allowances

        $trace = [];

        $trace[] = [
            'question' => 'What is the couple\'s investment portfolio and Inheritance Tax position?',
            'data_field' => 'portfolio.total_value',
            'data_value' => '£'.number_format($portfolioValue, 0).' investment portfolio',
            'threshold' => 'At least £'.number_format($nrb, 0).' (single Nil Rate Band)',
            'passed' => $portfolioValue >= $nrb,
            'explanation' => $portfolioValue >= $nrb
                ? 'Investment portfolio of £'.number_format($portfolioValue, 0).' exceeds the single Nil Rate Band of £'.number_format($nrb, 0).'. Combined available allowances: Nil Rate Band £'.number_format($nrb * 2, 0).' (£'.number_format($nrb, 0).' each) + Residence Nil Rate Band £'.number_format($rnrb * 2, 0).' (£'.number_format($rnrb, 0).' each) = £'.number_format($combinedNilRate, 0).' total. Estate equalisation should be considered to ensure both sets of allowances are fully utilised.'
                : 'Investment portfolio of £'.number_format($portfolioValue, 0).' is below the single Nil Rate Band of £'.number_format($nrb, 0).' — Inheritance Tax estate equalisation is premature.',
        ];

        $ageInfo = '';
        if ($userAge !== null || $spouseAge !== null) {
            $ageInfo = ' Ages: '.($userAge !== null ? 'primary '.$userAge : '').($userAge !== null && $spouseAge !== null ? ', ' : '').($spouseAge !== null ? $spouseName.' '.$spouseAge : '').'.';
        }

        $trace[] = [
            'question' => 'What are the couple\'s ages for Inheritance Tax planning horizon?',
            'data_field' => 'personal.age + spouse.age',
            'data_value' => ($userAge !== null ? 'Primary: age '.$userAge : 'Primary: age unknown').'. '.($spouseAge !== null ? $spouseName.': age '.$spouseAge : $spouseName.': age unknown').'.',
            'threshold' => 'N/A',
            'passed' => true,
            'explanation' => 'Inheritance Tax planning is relevant at any age but becomes more pressing as the estate grows.'.$ageInfo,
        ];

        // Only flag if portfolio alone is significant (property not counted here)
        if ($portfolioValue < $nrb) {
            return null;
        }

        $rec = $this->buildRecommendation(
            'iht_estate_equalisation',
            'Consider Inheritance Tax estate equalisation',
            sprintf(
                'With investment assets of £%s, consider how the combined estate compares to the combined Nil Rate Band of £%s (Nil Rate Band £%s + Residence Nil Rate Band £%s per partner). Equalising assets between partners ensures both Nil Rate Bands and Residence Nil Rate Bands are fully utilised.',
                number_format($portfolioValue, 0, '.', ','),
                number_format($combinedNilRate, 0, '.', ','),
                number_format($nrb, 0, '.', ','),
                number_format($rnrb, 0, '.', ',')
            ),
            'Review your estate planning in the Estate module for a comprehensive Inheritance Tax assessment.',
            'low'
        );
        $rec['decision_trace'] = $trace;

        return $rec;
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
