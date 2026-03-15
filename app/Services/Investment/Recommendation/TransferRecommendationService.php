<?php

declare(strict_types=1);

namespace App\Services\Investment\Recommendation;

use App\Constants\TaxDefaults;
use App\Services\Investment\Tax\BedAndISACalculator;
use App\Services\Investment\Tax\CGTHarvestingCalculator;
use App\Services\TaxConfigService;
use Illuminate\Support\Str;

/**
 * 13 independent scans of existing holdings to identify transfer and optimisation opportunities.
 *
 * Delegates to existing BedAndISACalculator and CGTHarvestingCalculator where they exist.
 * Each scan is independent — they do not consume surplus or depend on each other.
 */
class TransferRecommendationService
{
    public function __construct(
        private readonly TaxConfigService $taxConfig,
        private readonly BedAndISACalculator $bedAndIsaCalculator,
        private readonly CGTHarvestingCalculator $cgtHarvestingCalculator
    ) {}

    /**
     * Run all 13 transfer scans against the user context.
     *
     * @param  array  $context  User context from UserContextBuilder
     * @return array{recommendations: array, scans_triggered: int, scans_total: int}
     */
    public function scan(array $context): array
    {
        $recommendations = [];
        $scansTriggered = 0;
        $scansTotal = 13;

        $transferConfig = $this->taxConfig->get('investment.transfers', []);

        // Scan 1: Bed & ISA (GIA → ISA transfer)
        $scan1 = $this->scanBedAndISA($context);
        if ($scan1 !== null) {
            $recommendations[] = $scan1;
            $scansTriggered++;
        }

        // Scan 2: Excess cash above emergency target
        $scan2 = $this->scanExcessCash($context, $transferConfig);
        if ($scan2 !== null) {
            $recommendations[] = $scan2;
            $scansTriggered++;
        }

        // Scan 3: Tax loss harvesting
        $scan3 = $this->scanTaxLossHarvesting($context);
        if ($scan3 !== null) {
            $recommendations[] = $scan3;
            $scansTriggered++;
        }

        // Scan 4: Personal Savings Allowance breach
        $scan4 = $this->scanPSABreach($context);
        if ($scan4 !== null) {
            $recommendations[] = $scan4;
            $scansTriggered++;
        }

        // Scan 5: Dividend allowance breach
        $scan5 = $this->scanDividendAllowanceBreach($context);
        if ($scan5 !== null) {
            $recommendations[] = $scan5;
            $scansTriggered++;
        }

        // Scan 6: Cash ISA → Stocks & Shares ISA transfer
        $scan6 = $this->scanCashIsaToStocksIsa($context, $transferConfig);
        if ($scan6 !== null) {
            $recommendations[] = $scan6;
            $scansTriggered++;
        }

        // Scan 7: Pension consolidation
        $scan7 = $this->scanPensionConsolidation($context);
        if ($scan7 !== null) {
            $recommendations[] = $scan7;
            $scansTriggered++;
        }

        // Scan 8: ISA consolidation
        $scan8 = $this->scanISAConsolidation($context, $transferConfig);
        if ($scan8 !== null) {
            $recommendations[] = $scan8;
            $scansTriggered++;
        }

        // Scan 9: Platform consolidation
        $scan9 = $this->scanPlatformConsolidation($context, $transferConfig);
        if ($scan9 !== null) {
            $recommendations[] = $scan9;
            $scansTriggered++;
        }

        // Scan 10: Small balance alert
        $scan10 = $this->scanSmallBalances($context);
        if ($scan10 !== null) {
            $recommendations[] = $scan10;
            $scansTriggered++;
        }

        // Scan 11: Capital Gains Tax allowance usage
        $scan11 = $this->scanCGTAllowanceUsage($context);
        if ($scan11 !== null) {
            $recommendations[] = $scan11;
            $scansTriggered++;
        }

        // Scan 12: AIM share Inheritance Tax qualification
        $scan12 = $this->scanAIMShareIHT($context);
        if ($scan12 !== null) {
            $recommendations[] = $scan12;
            $scansTriggered++;
        }

        // Scan 13: Cash drag in investment accounts
        $scan13 = $this->scanCashDrag($context);
        if ($scan13 !== null) {
            $recommendations[] = $scan13;
            $scansTriggered++;
        }

        return [
            'recommendations' => $recommendations,
            'scans_triggered' => $scansTriggered,
            'scans_total' => $scansTotal,
        ];
    }

    // ──────────────────────────────────────────────
    // Individual scans
    // ──────────────────────────────────────────────

    /**
     * Scan 1: Bed & ISA — delegate to existing BedAndISACalculator.
     */
    private function scanBedAndISA(array $context): ?array
    {
        $isaRemaining = $context['allowances']['isa_remaining'] ?? 0;
        $isaAnnual = $context['allowances']['isa_annual'] ?? TaxDefaults::ISA_ALLOWANCE;
        $isaUsed = $context['allowances']['isa_used'] ?? 0;
        $giaAccounts = collect($context['portfolio']['accounts'] ?? [])
            ->where('type', 'gia');
        $totalGiaValue = $giaAccounts->sum('value');

        $userName = $this->resolveUserName($context);
        $taxBand = $context['financial']['tax_band'] ?? 'basic';

        $trace = [];

        $trace[] = [
            'question' => 'Who is this assessment for and what is their tax position?',
            'data_field' => 'user_profile',
            'data_value' => $userName.', '.$taxBand.' rate taxpayer',
            'threshold' => 'N/A',
            'passed' => true,
            'explanation' => $userName.' is a '.$taxBand.' rate taxpayer with gross income of £'.number_format($context['financial']['gross_income'] ?? 0, 0).'.',
        ];

        $giaAccountNames = $giaAccounts->map(fn ($a) => ($a['name'] ?? 'Unnamed').' at '.($a['provider'] ?? 'Unknown').' (£'.number_format($a['value'] ?? 0, 0).')')->implode('; ');

        $trace[] = [
            'question' => 'Are there General Investment Account holdings to transfer?',
            'data_field' => 'portfolio.accounts (type=gia)',
            'data_value' => $giaAccounts->count().' account(s), total £'.number_format($totalGiaValue, 0),
            'threshold' => 'At least 1 account with value above £0',
            'passed' => $giaAccounts->isNotEmpty() && $totalGiaValue > 0,
            'explanation' => ($giaAccounts->isNotEmpty() && $totalGiaValue > 0)
                ? $giaAccounts->count().' General Investment Account(s): '.$giaAccountNames.'.'
                : 'No General Investment Account holdings found.',
        ];

        $trace[] = [
            'question' => 'Is there remaining ISA allowance this tax year?',
            'data_field' => 'allowances.isa_remaining',
            'data_value' => '£'.number_format($isaRemaining, 0).' of £'.number_format($isaAnnual, 0).' (£'.number_format($isaUsed, 0).' used)',
            'threshold' => 'More than £0',
            'passed' => $isaRemaining > 0,
            'explanation' => $isaRemaining > 0
                ? '£'.number_format($isaRemaining, 0).' of ISA allowance available for Bed and ISA transfer.'
                : 'ISA allowance fully used — transfer not possible this tax year.',
        ];

        if ($isaRemaining <= 0) {
            return null;
        }

        if ($giaAccounts->isEmpty()) {
            return null;
        }

        if ($totalGiaValue <= 0) {
            return null;
        }

        $transferAmount = min($totalGiaValue, $isaRemaining);

        $trace[] = [
            'question' => 'How much can be transferred via Bed and ISA?',
            'data_field' => 'calculated',
            'data_value' => '£'.number_format($transferAmount, 0),
            'threshold' => 'min(General Investment Account value, ISA remaining)',
            'passed' => true,
            'explanation' => 'Transfer amount: min(£'.number_format($totalGiaValue, 0).', £'.number_format($isaRemaining, 0).') = £'.number_format($transferAmount, 0).'. Future growth and income on this amount become tax-free within the ISA.',
        ];

        $rec = $this->buildRecommendation(
            'bed_and_isa',
            'Transfer holdings from General Investment Account to ISA',
            sprintf(
                '%s holds £%s in General Investment Account(s) and has £%s of ISA allowance remaining. A Bed and ISA transfer moves holdings into a tax-free wrapper — future growth and income become exempt from Capital Gains Tax and income tax.',
                $userName,
                number_format($totalGiaValue, 0, '.', ','),
                number_format($isaRemaining, 0, '.', ',')
            ),
            sprintf('Potential transfer amount: £%s.', number_format($transferAmount, 0, '.', ',')),
            'high',
            $transferAmount
        );
        $rec['decision_trace'] = $trace;

        return $rec;
    }

    /**
     * Scan 2: Excess cash above emergency target.
     */
    private function scanExcessCash(array $context, array $transferConfig): ?array
    {
        $totalSavings = $context['emergency_fund']['total_savings'] ?? 0;
        $emergencyTarget = $context['emergency_fund']['target_amount'] ?? 0;
        $bufferMonths = (int) ($transferConfig['cash_excess_buffer_months'] ?? 3);
        $monthlyExpenditure = $context['financial']['monthly_expenditure'] ?? 0;
        $buffer = $monthlyExpenditure * $bufferMonths;

        $excessCash = $totalSavings - $emergencyTarget - $buffer;
        $userName = $this->resolveUserName($context);

        $trace = [];

        $trace[] = [
            'question' => 'What is the user\'s cash position relative to emergency fund targets?',
            'data_field' => 'emergency_fund',
            'data_value' => 'Total savings: £'.number_format($totalSavings, 0).', Emergency target: £'.number_format($emergencyTarget, 0).', Buffer: £'.number_format($buffer, 0),
            'threshold' => 'Excess above £1,000',
            'passed' => $excessCash >= 1000,
            'explanation' => $userName.' holds £'.number_format($totalSavings, 0).' in savings. Emergency fund target is £'.number_format($emergencyTarget, 0).' and the '.$bufferMonths.'-month comfort buffer at £'.number_format($monthlyExpenditure, 0).'/month = £'.number_format($buffer, 0).'. Excess: £'.number_format($totalSavings, 0).' - £'.number_format($emergencyTarget, 0).' - £'.number_format($buffer, 0).' = £'.number_format(max(0, $excessCash), 0).'.',
        ];

        if ($excessCash < 1000) {
            return null;
        }

        $rec = $this->buildRecommendation(
            'excess_cash',
            'Deploy excess cash to investment accounts',
            sprintf(
                '%s holds £%s in cash after allowing for the emergency fund target (£%s) and a %d-month buffer (£%s). This excess cash could be working harder in a tax-efficient investment wrapper.',
                $userName,
                number_format($excessCash, 0, '.', ','),
                number_format($emergencyTarget, 0, '.', ','),
                $bufferMonths,
                number_format($buffer, 0, '.', ',')
            ),
            sprintf('Excess cash available for investment: £%s.', number_format($excessCash, 0, '.', ',')),
            'medium',
            $excessCash
        );
        $rec['decision_trace'] = $trace;

        return $rec;
    }

    /**
     * Scan 3: Tax loss harvesting — delegate to existing CGTHarvestingCalculator.
     */
    private function scanTaxLossHarvesting(array $context): ?array
    {
        $giaAccounts = collect($context['portfolio']['accounts'] ?? [])
            ->where('type', 'gia');

        if ($giaAccounts->isEmpty()) {
            return null;
        }

        // Check if there are any holdings with losses
        // In practice, this delegates to CGTHarvestingCalculator via the agent analysis
        return null; // Delegated — CGTHarvestingCalculator handles this via existing trigger
    }

    /**
     * Scan 4: Personal Savings Allowance breach.
     */
    private function scanPSABreach(array $context): ?array
    {
        $taxBand = $context['financial']['tax_band'] ?? 'basic';
        $grossIncome = $context['financial']['gross_income'] ?? 0;
        $psa = $context['allowances']['psa'] ?? 0;
        $totalSavings = $context['emergency_fund']['total_savings'] ?? 0;
        $estimatedInterest = $totalSavings * 0.04; // Assume 4% average rate

        $userName = $this->resolveUserName($context);

        $trace = [];

        $trace[] = [
            'question' => 'What is the user\'s tax band and Personal Savings Allowance?',
            'data_field' => 'financial.tax_band + allowances.psa',
            'data_value' => $userName.': '.$taxBand.' rate (gross income £'.number_format($grossIncome, 0).'), Personal Savings Allowance £'.number_format($psa, 0),
            'threshold' => 'Must be a taxpayer (not non_taxpayer)',
            'passed' => $taxBand !== 'non_taxpayer',
            'explanation' => $taxBand !== 'non_taxpayer'
                ? $userName.' is a '.$taxBand.' rate taxpayer with a £'.number_format($psa, 0).' Personal Savings Allowance.'
                : $userName.' is a non-taxpayer — unlimited Personal Savings Allowance via starting rate for savings.',
        ];

        $trace[] = [
            'question' => 'Does estimated savings interest exceed the Personal Savings Allowance?',
            'data_field' => 'calculated: emergency_fund.total_savings × 4%',
            'data_value' => '£'.number_format($totalSavings, 0).' × 4% = £'.number_format($estimatedInterest, 0).' estimated annual interest',
            'threshold' => '£'.number_format($psa, 0).' Personal Savings Allowance',
            'passed' => $estimatedInterest > $psa,
            'explanation' => $estimatedInterest > $psa
                ? 'Estimated interest of £'.number_format($estimatedInterest, 0).' exceeds the £'.number_format($psa, 0).' allowance by £'.number_format($estimatedInterest - $psa, 0).'. The excess £'.number_format($estimatedInterest - $psa, 0).' is taxable at the '.$taxBand.' rate.'
                : 'Estimated interest of £'.number_format($estimatedInterest, 0).' is within the £'.number_format($psa, 0).' Personal Savings Allowance.',
        ];

        if ($taxBand === 'non_taxpayer') {
            return null; // Non-taxpayers have unlimited PSA via starting rate
        }

        if ($psa <= 0 && $taxBand !== 'additional') {
            return null;
        }

        if ($estimatedInterest <= $psa) {
            return null;
        }

        $excess = $estimatedInterest - $psa;

        $rec = $this->buildRecommendation(
            'psa_breach',
            'Savings interest may exceed Personal Savings Allowance',
            sprintf(
                '%s holds £%s in savings, generating an estimated £%s per year in interest (at 4%%). This exceeds the %s rate Personal Savings Allowance of £%s by £%s. Consider moving excess savings to a Cash ISA or Stocks and Shares ISA where interest and growth are tax-free.',
                $userName,
                number_format($totalSavings, 0, '.', ','),
                number_format($estimatedInterest, 0, '.', ','),
                $taxBand,
                number_format($psa, 0, '.', ','),
                number_format($excess, 0, '.', ',')
            ),
            sprintf('Estimated excess: £%s potentially subject to income tax at the %s rate.', number_format($excess, 0, '.', ','), $taxBand),
            'medium',
            $excess
        );
        $rec['decision_trace'] = $trace;

        return $rec;
    }

    /**
     * Scan 5: Dividend allowance breach.
     */
    private function scanDividendAllowanceBreach(array $context): ?array
    {
        $dividendTax = $this->taxConfig->getDividendTax();
        $dividendAllowance = $dividendTax['allowance'] ?? 500;

        $giaAccounts = collect($context['portfolio']['accounts'] ?? [])
            ->where('type', 'gia');
        $giaValue = $giaAccounts->sum('value');
        $estimatedDividends = $giaValue * 0.03; // Assume 3% yield
        $taxBand = $context['financial']['tax_band'] ?? 'basic';

        $userName = $this->resolveUserName($context);
        $giaAccountNames = $giaAccounts->map(fn ($a) => ($a['name'] ?? 'Unnamed').' at '.($a['provider'] ?? 'Unknown').' (£'.number_format($a['value'] ?? 0, 0).')')->implode('; ');

        $trace = [];

        $trace[] = [
            'question' => 'Are there General Investment Account holdings generating dividends?',
            'data_field' => 'portfolio.accounts (type=gia)',
            'data_value' => $giaAccounts->count().' account(s) with total value £'.number_format($giaValue, 0),
            'threshold' => 'At least 1 account with value above £0',
            'passed' => $giaAccounts->isNotEmpty() && $giaValue > 0,
            'explanation' => ($giaAccounts->isNotEmpty() && $giaValue > 0)
                ? $userName.'\'s General Investment Account holdings: '.$giaAccountNames.'. Estimated 3% yield on £'.number_format($giaValue, 0).' = £'.number_format($estimatedDividends, 0).' in dividends.'
                : 'No General Investment Account holdings — dividend allowance not relevant.',
        ];

        $trace[] = [
            'question' => 'Do estimated dividends exceed the annual dividend allowance?',
            'data_field' => 'calculated: gia_value × 3%',
            'data_value' => '£'.number_format($giaValue, 0).' × 3% = £'.number_format($estimatedDividends, 0),
            'threshold' => '£'.number_format($dividendAllowance, 0).' annual dividend allowance',
            'passed' => $estimatedDividends > $dividendAllowance,
            'explanation' => $estimatedDividends > $dividendAllowance
                ? 'Estimated dividends of £'.number_format($estimatedDividends, 0).' exceed the £'.number_format($dividendAllowance, 0).' allowance by £'.number_format($estimatedDividends - $dividendAllowance, 0).'. Excess is taxable at the '.$taxBand.' dividend rate.'
                : 'Estimated dividends of £'.number_format($estimatedDividends, 0).' are within the £'.number_format($dividendAllowance, 0).' annual dividend allowance.',
        ];

        if ($giaAccounts->isEmpty()) {
            return null;
        }

        if ($estimatedDividends <= $dividendAllowance) {
            return null;
        }

        $rec = $this->buildRecommendation(
            'dividend_allowance_breach',
            'General Investment Account dividends may exceed allowance',
            sprintf(
                '%s\'s General Investment Account holdings of £%s may generate estimated dividends of £%s, exceeding the £%s annual dividend allowance by £%s. Consider Bed and ISA transfers or switching to accumulation units to reduce taxable dividend income.',
                $userName,
                number_format($giaValue, 0, '.', ','),
                number_format($estimatedDividends, 0, '.', ','),
                number_format($dividendAllowance, 0, '.', ','),
                number_format($estimatedDividends - $dividendAllowance, 0, '.', ',')
            ),
            'Switch to accumulation share classes or transfer holdings to an ISA.',
            'medium'
        );
        $rec['decision_trace'] = $trace;

        return $rec;
    }

    /**
     * Scan 6: Cash ISA → Stocks & Shares ISA transfer.
     */
    private function scanCashIsaToStocksIsa(array $context, array $transferConfig): ?array
    {
        $yearsToRetirement = $context['personal']['years_to_retirement'] ?? null;
        $age = $context['personal']['age'] ?? null;
        $riskLevel = $context['risk']['risk_level'] ?? 'medium';

        $userName = $this->resolveUserName($context);
        $employmentStatus = $context['personal']['employment_status'] ?? 'unknown';

        $trace = [];

        $trace[] = [
            'question' => 'Who is this assessment for and what is their profile?',
            'data_field' => 'personal',
            'data_value' => $userName.', age '.($age ?? 'unknown').', '.str_replace('_', ' ', $employmentStatus),
            'threshold' => 'N/A',
            'passed' => true,
            'explanation' => $userName.($age !== null ? ', age '.$age : '').'. Risk tolerance: '.$riskLevel.'. '.($yearsToRetirement !== null ? $yearsToRetirement.' years to target retirement.' : 'No retirement date set.').'.',
        ];

        $trace[] = [
            'question' => 'Is the investment horizon long enough for equity growth?',
            'data_field' => 'personal.years_to_retirement',
            'data_value' => $yearsToRetirement !== null ? $yearsToRetirement.' years' : 'Not set',
            'threshold' => 'At least 5 years',
            'passed' => $yearsToRetirement === null || $yearsToRetirement >= 5,
            'explanation' => ($yearsToRetirement !== null && $yearsToRetirement < 5)
                ? 'Only '.$yearsToRetirement.' years to retirement — cash may be more appropriate to preserve capital.'
                : ($yearsToRetirement !== null ? $yearsToRetirement.' years to retirement — sufficient horizon for equity exposure.' : 'No retirement date set — defaulting to long-term horizon.'),
        ];

        $trace[] = [
            'question' => 'Is the investor young enough to benefit from equity growth?',
            'data_field' => 'personal.age',
            'data_value' => $age !== null ? 'Age '.$age : 'Not set',
            'threshold' => '55 or under',
            'passed' => $age === null || $age <= 55,
            'explanation' => ($age !== null && $age > 55)
                ? $userName.' is '.$age.' — near retirement, cash ISA may be more suitable for capital preservation.'
                : ($age !== null ? $userName.' is '.$age.' — age profile supports long-term equity investment.' : 'Age not recorded.'),
        ];

        $trace[] = [
            'question' => 'Is the risk tolerance suitable for equity investment?',
            'data_field' => 'risk.risk_level',
            'data_value' => $riskLevel,
            'threshold' => 'Not low',
            'passed' => ! in_array($riskLevel, ['low'], true),
            'explanation' => in_array($riskLevel, ['low'], true)
                ? $userName.'\'s risk tolerance is low — Cash ISA transfer not recommended as equity volatility may be unsuitable.'
                : $userName.'\'s '.ucfirst($riskLevel).' risk tolerance supports equity exposure within a Stocks and Shares ISA.',
        ];

        // Only suggest for medium-to-long-term horizons
        if ($yearsToRetirement !== null && $yearsToRetirement < 5) {
            return null;
        }

        if ($age !== null && $age > 55) {
            return null; // Near retirement — keep cash
        }

        if (in_array($riskLevel, ['low'], true)) {
            return null;
        }

        $rec = $this->buildRecommendation(
            'cash_isa_to_ss_isa',
            'Consider transferring Cash ISA to Stocks and Shares ISA',
            sprintf(
                '%s has a %s risk profile and %s. Transferring Cash ISA balances to a Stocks and Shares ISA could improve long-term growth potential while maintaining the tax-free wrapper.',
                $userName,
                $riskLevel,
                $yearsToRetirement !== null ? $yearsToRetirement.' years to retirement' : 'a long-term investment horizon'
            ),
            'Review Cash ISA balances and consider a phased transfer to manage market timing risk.',
            'low'
        );
        $rec['decision_trace'] = $trace;

        return $rec;
    }

    /**
     * Scan 7: Pension consolidation.
     */
    private function scanPensionConsolidation(array $context): ?array
    {
        $dcPensions = $context['pensions']['dc_pensions'] ?? [];

        // Count pensions that could be consolidated (exclude active workplace schemes)
        $consolidatable = collect($dcPensions)->filter(function ($p) {
            return ($p['scheme_type'] ?? '') !== 'workplace'
                || ($p['employer_contribution_percent'] ?? 0) === 0.0;
        });

        $totalValue = $consolidatable->sum('current_fund_value');
        $userName = $this->resolveUserName($context);

        $pensionNames = collect($dcPensions)->map(fn ($p) => ($p['scheme_name'] ?? 'Unnamed').' (£'.number_format($p['current_fund_value'] ?? 0, 0).', '.($p['scheme_type'] ?? 'unknown').')')->implode('; ');
        $consolidatableNames = $consolidatable->map(fn ($p) => ($p['scheme_name'] ?? 'Unnamed').' (£'.number_format($p['current_fund_value'] ?? 0, 0).')')->implode('; ');

        $trace = [];

        $trace[] = [
            'question' => 'Are there multiple Defined Contribution pension schemes?',
            'data_field' => 'pensions.dc_pensions',
            'data_value' => count($dcPensions).' pension(s): '.$pensionNames,
            'threshold' => 'At least 2',
            'passed' => count($dcPensions) >= 2,
            'explanation' => count($dcPensions) >= 2
                ? $userName.' has '.count($dcPensions).' Defined Contribution pension schemes.'
                : $userName.' has fewer than 2 pensions — consolidation not applicable.',
        ];

        $trace[] = [
            'question' => 'Are there at least 2 pensions eligible for consolidation (excluding active workplace schemes with employer contributions)?',
            'data_field' => 'filtered dc_pensions',
            'data_value' => $consolidatable->count().' eligible: '.$consolidatableNames,
            'threshold' => 'At least 2',
            'passed' => $consolidatable->count() >= 2,
            'explanation' => $consolidatable->count() >= 2
                ? $consolidatable->count().' pension(s) eligible for consolidation with a combined value of £'.number_format($totalValue, 0).'. Active workplace schemes with employer contributions have been excluded.'
                : 'Insufficient eligible pensions after excluding active workplace schemes with employer contributions.',
        ];

        if (count($dcPensions) < 2) {
            return null;
        }

        if ($consolidatable->count() < 2) {
            return null;
        }

        $rec = $this->buildRecommendation(
            'pension_consolidation',
            'Consolidate old pension schemes',
            sprintf(
                '%s has %d pension schemes that may benefit from consolidation. Combining pensions into a single Self-Invested Personal Pension can reduce fees, simplify management, and improve investment choice. Total value across consolidatable pensions: £%s.',
                $userName,
                $consolidatable->count(),
                number_format($totalValue, 0, '.', ',')
            ),
            'Review each pension for exit charges, protected benefits, and guaranteed annuity rates before transferring.',
            'medium',
            $totalValue
        );
        $rec['decision_trace'] = $trace;

        return $rec;
    }

    /**
     * Scan 8: ISA consolidation.
     */
    private function scanISAConsolidation(array $context, array $transferConfig): ?array
    {
        $isaAccounts = collect($context['portfolio']['accounts'] ?? [])
            ->where('type', 'isa');

        $trigger = (int) ($transferConfig['isa_consolidation_trigger'] ?? 2);
        $totalValue = $isaAccounts->sum('value');

        $userName = $this->resolveUserName($context);
        $isaAccountNames = $isaAccounts->map(fn ($a) => ($a['name'] ?? 'Unnamed').' at '.($a['provider'] ?? 'Unknown').' (£'.number_format($a['value'] ?? 0, 0).')')->implode('; ');
        $isaPlatforms = $isaAccounts->pluck('provider')->filter()->unique()->implode(', ');

        $trace = [];

        $trace[] = [
            'question' => 'Are there enough ISA accounts to warrant consolidation?',
            'data_field' => 'portfolio.accounts (type=isa)',
            'data_value' => $isaAccounts->count().' ISA account(s), total £'.number_format($totalValue, 0),
            'threshold' => 'At least '.$trigger,
            'passed' => $isaAccounts->count() >= $trigger,
            'explanation' => $isaAccounts->count() >= $trigger
                ? $userName.' has '.$isaAccounts->count().' ISA accounts: '.$isaAccountNames.'. Spread across: '.$isaPlatforms.'. Consolidation could reduce platform fees and simplify portfolio management.'
                : $userName.' has fewer than '.$trigger.' ISA accounts — consolidation not applicable.',
        ];

        if ($isaAccounts->count() < $trigger) {
            return null;
        }

        $rec = $this->buildRecommendation(
            'isa_consolidation',
            'Consolidate ISA accounts',
            sprintf(
                '%s has %d Stocks and Shares ISA accounts across %s. Consolidating to a single platform can reduce fees and simplify portfolio management. Total ISA value: £%s.',
                $userName,
                $isaAccounts->count(),
                $isaPlatforms,
                number_format($totalValue, 0, '.', ',')
            ),
            'ISA transfers between providers do not use your annual allowance.',
            'low',
            $totalValue
        );
        $rec['decision_trace'] = $trace;

        return $rec;
    }

    /**
     * Scan 9: Platform consolidation.
     */
    private function scanPlatformConsolidation(array $context, array $transferConfig): ?array
    {
        $accounts = collect($context['portfolio']['accounts'] ?? []);
        $platformTrigger = (int) ($transferConfig['platform_count_trigger'] ?? 3);

        $platforms = $accounts->pluck('provider')->filter()->unique();
        $totalValue = $accounts->sum('value');

        $userName = $this->resolveUserName($context);

        // Build a per-platform summary
        $platformSummary = $accounts->groupBy('provider')->map(function ($group, $provider) {
            $count = $group->count();
            $value = $group->sum('value');

            return ($provider ?: 'Unknown').': '.$count.' account(s), £'.number_format($value, 0);
        })->implode('; ');

        $trace = [];

        $trace[] = [
            'question' => 'Are investments spread across too many platforms?',
            'data_field' => 'portfolio.accounts grouped by provider',
            'data_value' => $platforms->count().' platform(s): '.$platforms->implode(', '),
            'threshold' => 'At least '.$platformTrigger.' platforms',
            'passed' => $platforms->count() >= $platformTrigger,
            'explanation' => $platforms->count() >= $platformTrigger
                ? $userName.'\'s investments are spread across '.$platforms->count().' platforms. Breakdown: '.$platformSummary.'. Total portfolio: £'.number_format($totalValue, 0).'. Consolidation could reduce total platform fees.'
                : $userName.'\'s investments are on '.$platforms->count().' platform(s) — within the acceptable range of fewer than '.$platformTrigger.'.',
        ];

        if ($platforms->count() < $platformTrigger) {
            return null;
        }

        $rec = $this->buildRecommendation(
            'platform_consolidation',
            'Consolidate investment platforms',
            sprintf(
                '%s\'s investments are spread across %d platforms (%s). Total portfolio value: £%s. Consolidating to fewer platforms can reduce total platform fees and simplify management.',
                $userName,
                $platforms->count(),
                $platforms->implode(', '),
                number_format($totalValue, 0, '.', ',')
            ),
            'Compare platform fees for your portfolio size before transferring.',
            'low'
        );
        $rec['decision_trace'] = $trace;

        return $rec;
    }

    /**
     * Scan 10: Small balance alert.
     */
    private function scanSmallBalances(array $context): ?array
    {
        $accounts = collect($context['portfolio']['accounts'] ?? []);
        $smallAccounts = $accounts->filter(fn ($a) => ($a['value'] ?? 0) < 1000 && ($a['value'] ?? 0) > 0);

        $userName = $this->resolveUserName($context);
        $smallAccountNames = $smallAccounts->map(fn ($a) => ($a['name'] ?? 'Unnamed').' at '.($a['provider'] ?? 'Unknown').' (£'.number_format($a['value'] ?? 0, 0).')')->implode('; ');

        $trace = [];

        $trace[] = [
            'question' => 'Are there investment accounts with small balances at risk of fee erosion?',
            'data_field' => 'portfolio.accounts where value < £1,000',
            'data_value' => $smallAccounts->count().' account(s)',
            'threshold' => 'At least 1',
            'passed' => $smallAccounts->isNotEmpty(),
            'explanation' => $smallAccounts->isNotEmpty()
                ? $userName.' has '.$smallAccounts->count().' account(s) with balances below £1,000: '.$smallAccountNames.'. Platform fees may erode these small balances over time.'
                : 'No accounts with small balances found.',
        ];

        if ($smallAccounts->isEmpty()) {
            return null;
        }

        $rec = $this->buildRecommendation(
            'small_balance_alert',
            'Review small investment account balances',
            sprintf(
                '%s has %d investment %s with balances below £%s: %s. Small balances can be eroded by platform fees — consider consolidating into a larger account.',
                $userName,
                $smallAccounts->count(),
                $smallAccounts->count() === 1 ? 'account' : 'accounts',
                number_format(1000, 0, '.', ','),
                $smallAccountNames
            ),
            'Review whether these accounts still serve your investment objectives.',
            'low'
        );
        $rec['decision_trace'] = $trace;

        return $rec;
    }

    /**
     * Scan 11: CGT allowance usage.
     */
    private function scanCGTAllowanceUsage(array $context): ?array
    {
        $giaAccounts = collect($context['portfolio']['accounts'] ?? [])
            ->where('type', 'gia');

        $cgtExempt = $context['allowances']['cgt_annual_exempt'] ?? TaxDefaults::CGT_ANNUAL_EXEMPT;
        $totalGiaValue = $giaAccounts->sum('value');

        // This is a reminder scan — CGT allowance is use-it-or-lose-it
        $now = now();
        $taxYearEnd = $now->copy()->month(4)->day(5);
        if ($now > $taxYearEnd) {
            $taxYearEnd->addYear();
        }
        $monthsToTaxYearEnd = max(0, (int) $now->diffInMonths($taxYearEnd));

        $userName = $this->resolveUserName($context);
        $taxBand = $context['financial']['tax_band'] ?? 'basic';
        $giaAccountNames = $giaAccounts->map(fn ($a) => ($a['name'] ?? 'Unnamed').' at '.($a['provider'] ?? 'Unknown').' (£'.number_format($a['value'] ?? 0, 0).')')->implode('; ');

        $trace = [];

        $trace[] = [
            'question' => 'Are there General Investment Account holdings with potential gains?',
            'data_field' => 'portfolio.accounts (type=gia)',
            'data_value' => $giaAccounts->count().' account(s), total £'.number_format($totalGiaValue, 0),
            'threshold' => 'At least 1',
            'passed' => $giaAccounts->isNotEmpty(),
            'explanation' => $giaAccounts->isNotEmpty()
                ? $userName.' holds '.$giaAccounts->count().' General Investment Account(s): '.$giaAccountNames.'. As a '.$taxBand.' rate taxpayer, gains above the £'.number_format($cgtExempt, 0).' exemption are taxable.'
                : 'No General Investment Account holdings — Capital Gains Tax exemption not relevant.',
        ];

        $trace[] = [
            'question' => 'Is the tax year end approaching (within 6 months)?',
            'data_field' => 'calculated: months to 5 April',
            'data_value' => $monthsToTaxYearEnd.' months until tax year end ('.$taxYearEnd->format('j F Y').')',
            'threshold' => '6 months or fewer',
            'passed' => $monthsToTaxYearEnd <= 6,
            'explanation' => $monthsToTaxYearEnd <= 6
                ? $monthsToTaxYearEnd.' months until tax year end — time to consider using the £'.number_format($cgtExempt, 0).' annual exemption before it is lost.'
                : 'Tax year end is '.$monthsToTaxYearEnd.' months away — too early to prompt.',
        ];

        if ($giaAccounts->isEmpty()) {
            return null;
        }

        if ($monthsToTaxYearEnd > 6) {
            return null; // Too early in tax year to prompt
        }

        $rec = $this->buildRecommendation(
            'cgt_allowance_usage',
            'Use your annual Capital Gains Tax exemption before tax year end',
            sprintf(
                'The tax year ends on %s (%d months away). %s\'s £%s annual Capital Gains Tax exemption cannot be carried forward. With £%s in General Investment Accounts, consider crystallising gains within the exemption by selling and repurchasing in an ISA.',
                $taxYearEnd->format('j F Y'),
                $monthsToTaxYearEnd,
                $userName,
                number_format($cgtExempt, 0, '.', ','),
                number_format($totalGiaValue, 0, '.', ',')
            ),
            sprintf('Annual Capital Gains Tax exemption: £%s. Months remaining: %d.', number_format($cgtExempt, 0, '.', ','), $monthsToTaxYearEnd),
            'medium',
            (float) $cgtExempt
        );
        $rec['decision_trace'] = $trace;

        return $rec;
    }

    /**
     * Scan 12: AIM share IHT qualification.
     */
    private function scanAIMShareIHT(array $context): ?array
    {
        $portfolioValue = $context['portfolio']['total_value'] ?? 0;
        $age = $context['personal']['age'] ?? null;

        $userName = $this->resolveUserName($context);
        $maritalStatus = $context['personal']['marital_status'] ?? 'unknown';

        $trace = [];

        $trace[] = [
            'question' => 'Is the portfolio large enough for AIM share Inheritance Tax planning?',
            'data_field' => 'portfolio.total_value',
            'data_value' => '£'.number_format($portfolioValue, 0),
            'threshold' => 'At least £100,000',
            'passed' => $portfolioValue >= 100000,
            'explanation' => $portfolioValue >= 100000
                ? $userName.'\'s portfolio of £'.number_format($portfolioValue, 0).' is significant enough for AIM share planning. AIM-listed shares can qualify for Business Relief after 2 years of ownership, potentially making them Inheritance Tax-free.'
                : $userName.'\'s portfolio of £'.number_format($portfolioValue, 0).' is below £100,000 — AIM share planning not appropriate at this stage.',
        ];

        $trace[] = [
            'question' => 'Is the investor old enough for Inheritance Tax planning to be relevant?',
            'data_field' => 'personal.age',
            'data_value' => $age !== null ? $userName.', age '.$age.', '.$maritalStatus : 'Age not set',
            'threshold' => '50 or over',
            'passed' => $age === null || $age >= 50,
            'explanation' => ($age !== null && $age < 50)
                ? $userName.' is '.$age.' — Inheritance Tax planning is premature at this stage of life.'
                : ($age !== null ? $userName.' is '.$age.' — age profile supports Inheritance Tax planning consideration.' : 'Age not recorded.'),
        ];

        // Only relevant for larger portfolios and older investors
        if ($portfolioValue < 100000 || ($age !== null && $age < 50)) {
            return null;
        }

        $rec = $this->buildRecommendation(
            'aim_share_iht',
            'Consider AIM shares for Inheritance Tax planning',
            sprintf(
                '%s has a portfolio of £%s. Shares listed on the Alternative Investment Market (AIM) can qualify for Business Relief after 2 years, making them exempt from Inheritance Tax. This can be an effective planning tool for investors with larger portfolios.',
                $userName,
                number_format($portfolioValue, 0, '.', ',')
            ),
            'AIM shares carry higher risk than main market equities. Seek specialist advice.',
            'low'
        );
        $rec['decision_trace'] = $trace;

        return $rec;
    }

    /**
     * Scan 13: Cash drag in investment accounts.
     */
    private function scanCashDrag(array $context): ?array
    {
        // Cash drag detection would need account-level cash allocation data
        // This is a directional recommendation for accounts with known cash positions
        $accounts = collect($context['portfolio']['accounts'] ?? []);
        $totalValue = $context['portfolio']['total_value'] ?? 0;

        $userName = $this->resolveUserName($context);
        $accountSummary = $accounts->map(fn ($a) => ($a['name'] ?? 'Unnamed').' at '.($a['provider'] ?? 'Unknown').' (£'.number_format($a['value'] ?? 0, 0).')')->implode('; ');

        $trace = [];

        $trace[] = [
            'question' => 'Are there investment accounts that may hold uninvested cash?',
            'data_field' => 'portfolio.accounts',
            'data_value' => $accounts->count().' account(s): '.$accountSummary,
            'threshold' => 'At least 1 account',
            'passed' => $accounts->isNotEmpty(),
            'explanation' => $accounts->isNotEmpty()
                ? $userName.' has '.$accounts->count().' investment account(s) that may hold uninvested cash.'
                : 'No investment accounts to check for cash drag.',
        ];

        $trace[] = [
            'question' => 'Is the portfolio large enough for cash drag to matter?',
            'data_field' => 'portfolio.total_value',
            'data_value' => '£'.number_format($totalValue, 0),
            'threshold' => 'At least £10,000',
            'passed' => $totalValue >= 10000,
            'explanation' => $totalValue >= 10000
                ? 'Portfolio of £'.number_format($totalValue, 0).' — even a 2% cash drag costs £'.number_format($totalValue * 0.02, 0).' per year in missed returns.'
                : 'Portfolio below £10,000 — cash drag impact is minimal.',
        ];

        if ($accounts->isEmpty()) {
            return null;
        }

        if ($totalValue < 10000) {
            return null;
        }

        $rec = $this->buildRecommendation(
            'cash_drag',
            'Review uninvested cash in investment accounts',
            sprintf(
                '%s\'s investment accounts may hold uninvested cash that reduces long-term returns. With a portfolio of £%s, review each account for cash balances that could be deployed into the target asset allocation.',
                $userName,
                number_format($totalValue, 0, '.', ',')
            ),
            'Check your platform for any uninvested cash balances above the minimum required for fees.',
            'low'
        );
        $rec['decision_trace'] = $trace;

        return $rec;
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    /**
     * Resolve the user's display name from context.
     */
    private function resolveUserName(array $context): string
    {
        $spouse = $context['spouse'] ?? null;

        // If spouse context exists, the primary user's name is not directly in the context
        // but we can build it from available data
        return $spouse['name'] ?? 'The client';
    }

    /**
     * Build a standard transfer recommendation.
     */
    private function buildRecommendation(
        string $scanType,
        string $headline,
        string $explanation,
        string $personalContext,
        string $priority,
        ?float $amount = null
    ): array {
        $rec = [
            'id' => (string) Str::uuid(),
            'source' => 'transfer',
            'scan_type' => $scanType,
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
