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
        $giaAccounts = collect($context['portfolio']['accounts'] ?? [])
            ->where('type', 'gia');
        $totalGiaValue = $giaAccounts->sum('value');

        $trace = [];

        $trace[] = [
            'question' => 'Is there remaining ISA allowance this tax year?',
            'data_field' => 'isa_remaining',
            'data_value' => '£'.number_format($isaRemaining, 0),
            'threshold' => 'More than £0',
            'passed' => $isaRemaining > 0,
            'explanation' => $isaRemaining > 0
                ? '£'.number_format($isaRemaining, 0).' of ISA allowance available for Bed and ISA transfer.'
                : 'ISA allowance fully used — transfer not possible this tax year.',
        ];

        $trace[] = [
            'question' => 'Are there General Investment Account holdings to transfer?',
            'data_field' => 'gia_value',
            'data_value' => '£'.number_format($totalGiaValue, 0),
            'threshold' => 'More than £0',
            'passed' => $giaAccounts->isNotEmpty() && $totalGiaValue > 0,
            'explanation' => ($giaAccounts->isNotEmpty() && $totalGiaValue > 0)
                ? '£'.number_format($totalGiaValue, 0).' held across '.$giaAccounts->count().' General Investment Account(s).'
                : 'No General Investment Account holdings found.',
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

        $rec = $this->buildRecommendation(
            'bed_and_isa',
            'Transfer holdings from General Investment Account to ISA',
            sprintf(
                'You hold %s in General Investment Accounts and have %s of ISA allowance remaining. A Bed and ISA transfer moves holdings into a tax-free wrapper — future growth and income become exempt from Capital Gains Tax and income tax.',
                number_format($totalGiaValue, 0, '.', ','),
                number_format($isaRemaining, 0, '.', ',')
            ),
            sprintf('Potential transfer amount: %s.', number_format($transferAmount, 0, '.', ',')),
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

        $trace = [];

        $trace[] = [
            'question' => 'Is there excess cash above the emergency fund target and buffer?',
            'data_field' => 'excess_cash',
            'data_value' => '£'.number_format(max(0, $excessCash), 0),
            'threshold' => '£1,000',
            'passed' => $excessCash >= 1000,
            'explanation' => $excessCash >= 1000
                ? '£'.number_format($excessCash, 0).' excess after emergency target (£'.number_format($emergencyTarget, 0).') and '.$bufferMonths.'-month buffer (£'.number_format($buffer, 0).').'
                : 'Cash reserves are within the target and buffer range — no excess to deploy.',
        ];

        if ($excessCash < 1000) {
            return null;
        }

        $rec = $this->buildRecommendation(
            'excess_cash',
            'Deploy excess cash to investment accounts',
            sprintf(
                'You hold %s in cash after allowing for your emergency fund target and a %d-month buffer. This excess cash could be working harder in a tax-efficient investment wrapper.',
                number_format($excessCash, 0, '.', ','),
                $bufferMonths
            ),
            sprintf('Excess cash available for investment: %s.', number_format($excessCash, 0, '.', ',')),
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
        $psa = $context['allowances']['psa'] ?? 0;
        $totalSavings = $context['emergency_fund']['total_savings'] ?? 0;
        $estimatedInterest = $totalSavings * 0.04; // Assume 4% average rate

        $trace = [];

        $trace[] = [
            'question' => 'Is the user a taxpayer (Personal Savings Allowance applies)?',
            'data_field' => 'tax_band',
            'data_value' => $taxBand,
            'threshold' => 'Not non_taxpayer',
            'passed' => $taxBand !== 'non_taxpayer',
            'explanation' => $taxBand !== 'non_taxpayer'
                ? ucfirst($taxBand).' rate taxpayer — Personal Savings Allowance of £'.number_format($psa, 0).' applies.'
                : 'Non-taxpayer — unlimited Personal Savings Allowance via starting rate for savings.',
        ];

        $trace[] = [
            'question' => 'Does estimated savings interest exceed the Personal Savings Allowance?',
            'data_field' => 'estimated_interest',
            'data_value' => '£'.number_format($estimatedInterest, 0),
            'threshold' => '£'.number_format($psa, 0),
            'passed' => $estimatedInterest > $psa,
            'explanation' => $estimatedInterest > $psa
                ? 'Estimated interest of £'.number_format($estimatedInterest, 0).' exceeds the £'.number_format($psa, 0).' allowance by £'.number_format($estimatedInterest - $psa, 0).'.'
                : 'Estimated interest is within the Personal Savings Allowance.',
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
                'Your estimated savings interest of %s may exceed your %s Personal Savings Allowance of %s. Consider moving excess savings to a Cash ISA or Stocks and Shares ISA where interest and growth are tax-free.',
                number_format($estimatedInterest, 0, '.', ','),
                $taxBand.' rate',
                number_format($psa, 0, '.', ',')
            ),
            sprintf('Estimated excess: %s potentially subject to income tax.', number_format($excess, 0, '.', ',')),
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

        $trace = [];

        $trace[] = [
            'question' => 'Are there General Investment Account holdings generating dividends?',
            'data_field' => 'gia_value',
            'data_value' => '£'.number_format($giaValue, 0),
            'threshold' => 'More than £0',
            'passed' => $giaAccounts->isNotEmpty() && $giaValue > 0,
            'explanation' => ($giaAccounts->isNotEmpty() && $giaValue > 0)
                ? '£'.number_format($giaValue, 0).' in General Investment Account holdings may generate taxable dividends.'
                : 'No General Investment Account holdings — dividend allowance not relevant.',
        ];

        $trace[] = [
            'question' => 'Do estimated dividends exceed the annual dividend allowance?',
            'data_field' => 'estimated_dividends',
            'data_value' => '£'.number_format($estimatedDividends, 0),
            'threshold' => '£'.number_format($dividendAllowance, 0),
            'passed' => $estimatedDividends > $dividendAllowance,
            'explanation' => $estimatedDividends > $dividendAllowance
                ? 'Estimated dividends of £'.number_format($estimatedDividends, 0).' exceed the £'.number_format($dividendAllowance, 0).' allowance.'
                : 'Estimated dividends are within the dividend allowance.',
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
                'Your General Investment Account holdings of %s may generate dividends exceeding the %s annual dividend allowance. Consider Bed and ISA transfers or accumulation units to reduce taxable dividend income.',
                number_format($giaValue, 0, '.', ','),
                number_format($dividendAllowance, 0, '.', ',')
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

        $trace = [];

        $trace[] = [
            'question' => 'Is the investment horizon long enough for equity growth?',
            'data_field' => 'years_to_retirement',
            'data_value' => $yearsToRetirement !== null ? $yearsToRetirement.' years' : 'Not set',
            'threshold' => 'At least 5 years',
            'passed' => $yearsToRetirement === null || $yearsToRetirement >= 5,
            'explanation' => ($yearsToRetirement !== null && $yearsToRetirement < 5)
                ? 'Less than 5 years to retirement — cash may be more appropriate.'
                : 'Investment horizon supports equity exposure.',
        ];

        $trace[] = [
            'question' => 'Is the investor young enough to benefit from equity growth?',
            'data_field' => 'age',
            'data_value' => $age !== null ? (string) $age : 'Not set',
            'threshold' => '55 or under',
            'passed' => $age === null || $age <= 55,
            'explanation' => ($age !== null && $age > 55)
                ? 'Age '.$age.' — near retirement, cash ISA may be more suitable.'
                : 'Age profile supports long-term equity investment.',
        ];

        $trace[] = [
            'question' => 'Is the risk tolerance suitable for equity investment?',
            'data_field' => 'risk_level',
            'data_value' => $riskLevel,
            'threshold' => 'Not low',
            'passed' => ! in_array($riskLevel, ['low'], true),
            'explanation' => in_array($riskLevel, ['low'], true)
                ? 'Low risk tolerance — Cash ISA transfer not recommended.'
                : ucfirst($riskLevel).' risk tolerance supports equity exposure.',
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
            'If you hold significant balances in Cash ISAs and have a medium-to-long investment horizon, transferring to a Stocks and Shares ISA could improve growth potential while maintaining the tax-free wrapper.',
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

        $trace = [];

        $trace[] = [
            'question' => 'Are there multiple Defined Contribution pension schemes?',
            'data_field' => 'dc_pensions_count',
            'data_value' => (string) count($dcPensions),
            'threshold' => 'At least 2',
            'passed' => count($dcPensions) >= 2,
            'explanation' => count($dcPensions) >= 2
                ? count($dcPensions).' Defined Contribution pension(s) found.'
                : 'Fewer than 2 pensions — consolidation not applicable.',
        ];

        $trace[] = [
            'question' => 'Are there at least 2 pensions eligible for consolidation?',
            'data_field' => 'consolidatable_count',
            'data_value' => (string) $consolidatable->count(),
            'threshold' => 'At least 2',
            'passed' => $consolidatable->count() >= 2,
            'explanation' => $consolidatable->count() >= 2
                ? $consolidatable->count().' pension(s) eligible (excluding active workplace schemes with employer contributions). Total value: £'.number_format($totalValue, 0).'.'
                : 'Insufficient eligible pensions after excluding active workplace schemes.',
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
                'You have %d pension schemes that may benefit from consolidation. Combining pensions into a single Self-Invested Personal Pension can reduce fees, simplify management, and improve investment choice. Total value across consolidatable pensions: %s.',
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

        $trace = [];

        $trace[] = [
            'question' => 'Are there enough ISA accounts to warrant consolidation?',
            'data_field' => 'isa_accounts_count',
            'data_value' => (string) $isaAccounts->count(),
            'threshold' => (string) $trigger,
            'passed' => $isaAccounts->count() >= $trigger,
            'explanation' => $isaAccounts->count() >= $trigger
                ? $isaAccounts->count().' ISA account(s) found with total value of £'.number_format($totalValue, 0).' — consolidation could reduce fees.'
                : 'Fewer than '.$trigger.' ISA accounts — consolidation not applicable.',
        ];

        if ($isaAccounts->count() < $trigger) {
            return null;
        }

        $rec = $this->buildRecommendation(
            'isa_consolidation',
            'Consolidate ISA accounts',
            sprintf(
                'You have %d Stocks and Shares ISA accounts. Consolidating to a single platform can reduce fees and simplify portfolio management. Total ISA value: %s.',
                $isaAccounts->count(),
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

        $trace = [];

        $trace[] = [
            'question' => 'Are investments spread across too many platforms?',
            'data_field' => 'platform_count',
            'data_value' => (string) $platforms->count(),
            'threshold' => (string) $platformTrigger,
            'passed' => $platforms->count() >= $platformTrigger,
            'explanation' => $platforms->count() >= $platformTrigger
                ? 'Investments spread across '.$platforms->count().' platforms ('.$platforms->implode(', ').') — consolidation could reduce fees.'
                : 'Investments are on '.$platforms->count().' platform(s) — within acceptable range.',
        ];

        if ($platforms->count() < $platformTrigger) {
            return null;
        }

        $rec = $this->buildRecommendation(
            'platform_consolidation',
            'Consolidate investment platforms',
            sprintf(
                'Your investments are spread across %d platforms (%s). Consolidating to fewer platforms can reduce total platform fees and simplify management.',
                $platforms->count(),
                $platforms->implode(', ')
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

        $trace = [];

        $trace[] = [
            'question' => 'Are there investment accounts with small balances at risk of fee erosion?',
            'data_field' => 'small_balance_accounts',
            'data_value' => (string) $smallAccounts->count(),
            'threshold' => 'At least 1',
            'passed' => $smallAccounts->isNotEmpty(),
            'explanation' => $smallAccounts->isNotEmpty()
                ? $smallAccounts->count().' account(s) with balances below £1,000 — platform fees may erode the value.'
                : 'No accounts with small balances found.',
        ];

        if ($smallAccounts->isEmpty()) {
            return null;
        }

        $rec = $this->buildRecommendation(
            'small_balance_alert',
            'Review small investment account balances',
            sprintf(
                '%d investment %s with balances below %s. Small balances can be eroded by platform fees — consider consolidating into a larger account.',
                $smallAccounts->count(),
                $smallAccounts->count() === 1 ? 'account has a balance' : 'accounts have balances',
                number_format(1000, 0, '.', ',')
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

        // This is a reminder scan — CGT allowance is use-it-or-lose-it
        $now = now();
        $taxYearEnd = $now->copy()->month(4)->day(5);
        if ($now > $taxYearEnd) {
            $taxYearEnd->addYear();
        }
        $monthsToTaxYearEnd = max(0, (int) $now->diffInMonths($taxYearEnd));

        $trace = [];

        $trace[] = [
            'question' => 'Are there General Investment Account holdings with potential gains?',
            'data_field' => 'gia_accounts',
            'data_value' => (string) $giaAccounts->count(),
            'threshold' => 'At least 1',
            'passed' => $giaAccounts->isNotEmpty(),
            'explanation' => $giaAccounts->isNotEmpty()
                ? $giaAccounts->count().' General Investment Account(s) found — Capital Gains Tax exemption may apply.'
                : 'No General Investment Account holdings — Capital Gains Tax exemption not relevant.',
        ];

        $trace[] = [
            'question' => 'Is the tax year end approaching (within 6 months)?',
            'data_field' => 'months_to_tax_year_end',
            'data_value' => $monthsToTaxYearEnd.' months',
            'threshold' => '6 months or fewer',
            'passed' => $monthsToTaxYearEnd <= 6,
            'explanation' => $monthsToTaxYearEnd <= 6
                ? $monthsToTaxYearEnd.' months until tax year end — time to consider using the £'.number_format($cgtExempt, 0).' exemption.'
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
                'The tax year ends in %d months. Your %s annual Capital Gains Tax exemption cannot be carried forward. Consider crystallising gains within the exemption by selling and repurchasing in an ISA.',
                $monthsToTaxYearEnd,
                number_format($cgtExempt, 0, '.', ',')
            ),
            sprintf('Annual Capital Gains Tax exemption: %s. Months remaining: %d.', number_format($cgtExempt, 0, '.', ','), $monthsToTaxYearEnd),
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

        $trace = [];

        $trace[] = [
            'question' => 'Is the portfolio large enough for AIM share Inheritance Tax planning?',
            'data_field' => 'portfolio_value',
            'data_value' => '£'.number_format($portfolioValue, 0),
            'threshold' => '£100,000',
            'passed' => $portfolioValue >= 100000,
            'explanation' => $portfolioValue >= 100000
                ? 'Portfolio of £'.number_format($portfolioValue, 0).' is significant enough for AIM share planning.'
                : 'Portfolio below £100,000 — AIM share planning not appropriate at this stage.',
        ];

        $trace[] = [
            'question' => 'Is the investor old enough for Inheritance Tax planning to be relevant?',
            'data_field' => 'age',
            'data_value' => $age !== null ? (string) $age : 'Not set',
            'threshold' => '50 or over',
            'passed' => $age === null || $age >= 50,
            'explanation' => ($age !== null && $age < 50)
                ? 'Age '.$age.' — Inheritance Tax planning is premature.'
                : 'Age profile supports Inheritance Tax planning consideration.',
        ];

        // Only relevant for larger portfolios and older investors
        if ($portfolioValue < 100000 || ($age !== null && $age < 50)) {
            return null;
        }

        $rec = $this->buildRecommendation(
            'aim_share_iht',
            'Consider AIM shares for Inheritance Tax planning',
            'Shares listed on the Alternative Investment Market (AIM) can qualify for Business Relief after 2 years, making them exempt from Inheritance Tax. This can be an effective planning tool for investors with larger portfolios.',
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

        $trace = [];

        $trace[] = [
            'question' => 'Are there investment accounts that may hold uninvested cash?',
            'data_field' => 'accounts_count',
            'data_value' => (string) $accounts->count(),
            'threshold' => 'At least 1',
            'passed' => $accounts->isNotEmpty(),
            'explanation' => $accounts->isNotEmpty()
                ? $accounts->count().' investment account(s) found — may hold uninvested cash.'
                : 'No investment accounts to check for cash drag.',
        ];

        $trace[] = [
            'question' => 'Is the portfolio large enough for cash drag to matter?',
            'data_field' => 'total_value',
            'data_value' => '£'.number_format($totalValue, 0),
            'threshold' => '£10,000',
            'passed' => $totalValue >= 10000,
            'explanation' => $totalValue >= 10000
                ? 'Portfolio of £'.number_format($totalValue, 0).' — uninvested cash could meaningfully reduce returns.'
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
            'Investment accounts may hold uninvested cash that reduces long-term returns. Review each account for cash balances that could be deployed into your target asset allocation.',
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
