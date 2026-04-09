<?php

declare(strict_types=1);

namespace App\Services;

/**
 * UK Tax and National Insurance Calculator
 * Uses active tax year rates from TaxConfigService
 */
class UKTaxCalculator
{
    public function __construct(
        private readonly TaxConfigService $taxConfig
    ) {}

    /**
     * Calculate detailed net income with per-income-type breakdowns.
     * Uses stack-order allocation: employment uses PA first, other income taxed at remaining band position.
     *
     * @param  float  $employmentIncome  Employment income (PAYE)
     * @param  float  $selfEmploymentIncome  Self-employment income
     * @param  float  $rentalIncome  Rental income (property)
     * @param  float  $pensionIncome  Pension income (DB/state)
     * @param  float  $trustIncome  Trust income (gross amount)
     * @param  float  $interestIncome  Interest income (savings)
     * @param  float  $dividendIncome  Dividend income
     * @param  string|null  $trustType  Type of trust: 'discretionary', 'interest_in_possession', 'bare', etc.
     * @param  float  $pensionContributions  Employee pension contributions (deducted before tax)
     * @return array Detailed breakdown per income type with tax bands and NI
     */
    public function calculateDetailedNetIncome(
        float $employmentIncome = 0,
        float $selfEmploymentIncome = 0,
        float $rentalIncome = 0,
        float $pensionIncome = 0,
        float $trustIncome = 0,
        float $interestIncome = 0,
        float $dividendIncome = 0,
        ?string $trustType = null,
        float $pensionContributions = 0,
        float $section24Credit = 0
    ): array {
        $incomeTaxConfig = $this->taxConfig->getIncomeTax();

        // Apply Personal Allowance taper for incomes above £100,000
        // Must be done BEFORE creating TaxBandTracker so band thresholds are correct
        $totalIncomePreRelief = $employmentIncome + $selfEmploymentIncome + $rentalIncome
            + $pensionIncome + $trustIncome + $interestIncome + $dividendIncome;
        $taxableIncomePreRelief = $totalIncomePreRelief - $pensionContributions;

        $taperThreshold = $incomeTaxConfig['personal_allowance_taper_threshold'] ?? 100000;
        $fullPA = $incomeTaxConfig['personal_allowance'];
        if ($taxableIncomePreRelief > $taperThreshold) {
            $excess = $taxableIncomePreRelief - $taperThreshold;
            $reduction = floor($excess / 2);
            $incomeTaxConfig['personal_allowance'] = max(0, $fullPA - $reduction);
        }

        $tracker = new TaxBandTracker($incomeTaxConfig);

        $incomeBreakdowns = [];
        $totalGross = 0;
        $totalTax = 0;
        $totalNI = 0;

        // Combined "Earned Income" card - employment, self-employment, rental, pension income
        // These all use the same tax bands (20%/40%/45%)
        $hasEarnedIncome = $employmentIncome > 0 || $selfEmploymentIncome > 0 || $rentalIncome > 0 || $pensionIncome > 0;

        if ($hasEarnedIncome) {
            // Calculate taxable employment income (after pension contributions)
            $taxableEmploymentIncome = max(0, $employmentIncome - $pensionContributions);

            // Total taxable earned income for tax calculation
            $totalTaxableEarnedIncome = $taxableEmploymentIncome + $selfEmploymentIncome + $rentalIncome + $pensionIncome;

            // Calculate tax on combined earned income
            $taxAllocation = $tracker->allocateIncome($totalTaxableEarnedIncome);

            // Calculate NI separately for employment and self-employment
            $class1NI = $employmentIncome > 0 ? $this->calculateClass1NIDetailed($employmentIncome) : null;
            $class4NI = $selfEmploymentIncome > 0 ? $this->calculateClass4NIDetailed($selfEmploymentIncome) : null;

            $totalNIAmount = ($class1NI['total_ni'] ?? 0) + ($class4NI['total_ni'] ?? 0);

            // Build income components for display
            $incomeComponents = [];

            if ($employmentIncome > 0) {
                $incomeComponents[] = [
                    'label' => 'Employment Income',
                    'amount' => round($employmentIncome, 2),
                ];

                if ($pensionContributions > 0) {
                    $incomeComponents[] = [
                        'label' => 'Pension Contributions',
                        'amount' => round(-$pensionContributions, 2),
                        'is_deduction' => true,
                    ];
                }
            }

            if ($selfEmploymentIncome > 0) {
                $incomeComponents[] = [
                    'label' => 'Self-Employment Income',
                    'amount' => round($selfEmploymentIncome, 2),
                ];
            }

            if ($rentalIncome > 0) {
                $incomeComponents[] = [
                    'label' => 'Rental Income',
                    'amount' => round($rentalIncome, 2),
                ];
            }

            if ($pensionIncome > 0) {
                $incomeComponents[] = [
                    'label' => 'Pension Income',
                    'amount' => round($pensionIncome, 2),
                ];
            }

            // Build NI breakdown combining both classes
            $niBreakdown = null;
            if ($class1NI || $class4NI) {
                $niBreakdown = [
                    'class_1' => $class1NI,
                    'class_4' => $class4NI,
                    'total_ni' => round($totalNIAmount, 2),
                ];
            }

            // Gross earned income (before pension deduction for display)
            $grossEarnedIncome = $employmentIncome + $selfEmploymentIncome + $rentalIncome + $pensionIncome;

            $incomeBreakdowns[] = [
                'income_type' => 'earned',
                'income_type_label' => 'Earned Income',
                'gross_amount' => round($grossEarnedIncome, 2),
                'income_components' => $incomeComponents,
                'taxable_income' => round($totalTaxableEarnedIncome, 2),
                'tax_breakdown' => $taxAllocation,
                'ni_breakdown' => $niBreakdown,
                'total_deductions' => round($taxAllocation['total_income_tax'] + $totalNIAmount, 2),
                'net_income' => round($grossEarnedIncome - $taxAllocation['total_income_tax'] - $totalNIAmount, 2),
            ];

            $totalGross += $grossEarnedIncome;
            $totalTax += $taxAllocation['total_income_tax'];
            $totalNI += $totalNIAmount;
        }

        // Interest income (uses same bands but has PSA - keep separate for clarity)
        if ($interestIncome > 0) {
            $interestBreakdown = $this->calculateInterestTaxDetailed($interestIncome, $tracker);

            $incomeBreakdowns[] = [
                'income_type' => 'interest',
                'income_type_label' => 'Interest Income',
                'gross_amount' => round($interestIncome, 2),
                'tax_breakdown' => $interestBreakdown,
                'ni_breakdown' => null,
                'total_deductions' => round($interestBreakdown['total_income_tax'], 2),
                'net_income' => round($interestIncome - $interestBreakdown['total_income_tax'], 2),
            ];

            $totalGross += $interestIncome;
            $totalTax += $interestBreakdown['total_income_tax'];
        }

        // Dividend income (special rates: 8.75%/33.75%/39.35%)
        if ($dividendIncome > 0) {
            $dividendBreakdown = $this->calculateDividendTaxDetailed($dividendIncome, $tracker);

            $incomeBreakdowns[] = [
                'income_type' => 'dividend',
                'income_type_label' => 'Dividend Income',
                'gross_amount' => round($dividendIncome, 2),
                'tax_breakdown' => $dividendBreakdown,
                'ni_breakdown' => null,
                'total_deductions' => round($dividendBreakdown['total_income_tax'], 2),
                'net_income' => round($dividendIncome - $dividendBreakdown['total_income_tax'], 2),
            ];

            $totalGross += $dividendIncome;
            $totalTax += $dividendBreakdown['total_income_tax'];
        }

        // Trust income (special taxation based on trust type)
        if ($trustIncome > 0) {
            $trustTaxBreakdown = $this->calculateTrustIncomeTax($trustIncome, $trustType, $tracker);

            $incomeBreakdowns[] = [
                'income_type' => 'trust',
                'income_type_label' => 'Trust Income',
                'gross_amount' => round($trustIncome, 2),
                'tax_breakdown' => $trustTaxBreakdown,
                'ni_breakdown' => null,
                'total_deductions' => round($trustTaxBreakdown['total_income_tax'], 2),
                'net_income' => round($trustIncome - $trustTaxBreakdown['total_income_tax'], 2),
            ];

            $totalGross += $trustIncome;
            $totalTax += $trustTaxBreakdown['total_income_tax'];
        }

        // Apply Section 24 tax credit (reduces tax bill, not income)
        $appliedSection24Credit = min($section24Credit, $totalTax);
        $totalTaxAfterCredit = $totalTax - $appliedSection24Credit;

        $totalDeductions = $totalTaxAfterCredit + $totalNI;
        $netIncome = $totalGross - $totalDeductions;

        return [
            'income_breakdowns' => $incomeBreakdowns,
            'section_24' => $section24Credit > 0 ? [
                'annual_credit' => round($section24Credit, 2),
                'applied_credit' => round($appliedSection24Credit, 2),
            ] : null,
            'summary' => [
                'total_gross_income' => round($totalGross, 2),
                'total_income_tax_before_credits' => round($totalTax, 2),
                'section_24_credit' => round($appliedSection24Credit, 2),
                'total_income_tax' => round($totalTaxAfterCredit, 2),
                'total_national_insurance' => round($totalNI, 2),
                'total_deductions' => round($totalDeductions, 2),
                'net_income' => round($netIncome, 2),
                'effective_tax_rate' => $totalGross > 0 ? round(($totalDeductions / $totalGross) * 100, 2) : 0,
                'monthly_net_income' => round($netIncome / 12, 2),
            ],
            'tax_year' => $this->taxConfig->getTaxYear(),
        ];
    }

    /**
     * Calculate Class 1 NI with detailed breakdown
     */
    private function calculateClass1NIDetailed(float $employmentIncome): array
    {
        $niConfig = $this->taxConfig->getNationalInsurance();
        $class1Employee = $niConfig['class_1']['employee'];

        $primaryThreshold = $class1Employee['primary_threshold'];
        $upperEarningsLimit = $class1Employee['upper_earnings_limit'];
        $mainRate = $class1Employee['main_rate'];
        $additionalRate = $class1Employee['additional_rate'];

        $breakdown = [
            'class' => 'Class 1',
            'main_rate' => ['earnings' => 0, 'contribution' => 0, 'rate' => $mainRate],
            'additional_rate' => ['earnings' => 0, 'contribution' => 0, 'rate' => $additionalRate],
            'total_ni' => 0,
        ];

        if ($employmentIncome <= $primaryThreshold) {
            return $breakdown;
        }

        // Main rate: earnings between primary threshold and upper earnings limit
        if ($employmentIncome > $primaryThreshold) {
            $mainRateEarnings = min($employmentIncome - $primaryThreshold, $upperEarningsLimit - $primaryThreshold);
            $breakdown['main_rate']['earnings'] = round($mainRateEarnings, 2);
            $breakdown['main_rate']['contribution'] = round($mainRateEarnings * $mainRate, 2);
        }

        // Additional rate: earnings above upper earnings limit
        if ($employmentIncome > $upperEarningsLimit) {
            $additionalRateEarnings = $employmentIncome - $upperEarningsLimit;
            $breakdown['additional_rate']['earnings'] = round($additionalRateEarnings, 2);
            $breakdown['additional_rate']['contribution'] = round($additionalRateEarnings * $additionalRate, 2);
        }

        $breakdown['total_ni'] = $breakdown['main_rate']['contribution'] + $breakdown['additional_rate']['contribution'];

        return $breakdown;
    }

    /**
     * Calculate Class 4 NI with detailed breakdown
     */
    private function calculateClass4NIDetailed(float $selfEmploymentIncome): array
    {
        $niConfig = $this->taxConfig->getNationalInsurance();
        $class4 = $niConfig['class_4'];

        $lowerProfitsLimit = $class4['lower_profits_limit'];
        $upperProfitsLimit = $class4['upper_profits_limit'];
        $mainRate = $class4['main_rate'];
        $additionalRate = $class4['additional_rate'];

        $breakdown = [
            'class' => 'Class 4',
            'main_rate' => ['earnings' => 0, 'contribution' => 0, 'rate' => $mainRate],
            'additional_rate' => ['earnings' => 0, 'contribution' => 0, 'rate' => $additionalRate],
            'total_ni' => 0,
        ];

        if ($selfEmploymentIncome <= $lowerProfitsLimit) {
            return $breakdown;
        }

        // Main rate
        if ($selfEmploymentIncome > $lowerProfitsLimit) {
            $mainRateEarnings = min($selfEmploymentIncome - $lowerProfitsLimit, $upperProfitsLimit - $lowerProfitsLimit);
            $breakdown['main_rate']['earnings'] = round($mainRateEarnings, 2);
            $breakdown['main_rate']['contribution'] = round($mainRateEarnings * $mainRate, 2);
        }

        // Additional rate
        if ($selfEmploymentIncome > $upperProfitsLimit) {
            $additionalRateEarnings = $selfEmploymentIncome - $upperProfitsLimit;
            $breakdown['additional_rate']['earnings'] = round($additionalRateEarnings, 2);
            $breakdown['additional_rate']['contribution'] = round($additionalRateEarnings * $additionalRate, 2);
        }

        $breakdown['total_ni'] = $breakdown['main_rate']['contribution'] + $breakdown['additional_rate']['contribution'];

        return $breakdown;
    }

    /**
     * Calculate interest tax with PSA consideration
     */
    private function calculateInterestTaxDetailed(float $interestIncome, TaxBandTracker $tracker): array
    {
        $config = $tracker->getConfig();
        $bandPosition = $tracker->getCurrentBandPosition();

        // Determine PSA based on current band position (from TaxConfigService)
        $psaConfig = $this->taxConfig->getPersonalSavingsAllowance();
        $psa = match ($bandPosition) {
            'personal_allowance', 'basic' => (int) ($psaConfig['basic'] ?? 1000),
            'higher' => (int) ($psaConfig['higher'] ?? 500),
            default => 0,
        };

        $taxableInterest = max(0, $interestIncome - $psa);
        $taxAllocation = $tracker->allocateIncome($taxableInterest);

        // Add PSA info to breakdown
        $taxAllocation['personal_savings_allowance'] = $psa;
        $taxAllocation['taxable_after_psa'] = $taxableInterest;

        return $taxAllocation;
    }

    /**
     * Calculate dividend tax with allowance and special rates
     */
    private function calculateDividendTaxDetailed(float $dividendIncome, TaxBandTracker $tracker): array
    {
        $dividendTax = $this->taxConfig->getDividendTax();
        $config = $tracker->getConfig();

        $allowance = $dividendTax['allowance'];
        $basicRate = $dividendTax['basic_rate'];
        $higherRate = $dividendTax['higher_rate'];
        $additionalRate = $dividendTax['additional_rate'];

        $taxableDividends = max(0, $dividendIncome - $allowance);

        $breakdown = [
            'dividend_allowance' => $allowance,
            'taxable_after_allowance' => $taxableDividends,
            'basic_rate' => ['taxable' => 0, 'tax' => 0, 'rate' => $basicRate],
            'higher_rate' => ['taxable' => 0, 'tax' => 0, 'rate' => $higherRate],
            'additional_rate' => ['taxable' => 0, 'tax' => 0, 'rate' => $additionalRate],
            'total_income_tax' => 0,
        ];

        if ($taxableDividends <= 0) {
            return $breakdown;
        }

        // Allocate dividends to remaining bands
        $remaining = $taxableDividends;

        // Basic rate band
        $basicAvailable = $tracker->getRemainingBasicBand();
        if ($basicAvailable > 0 && $remaining > 0) {
            $basicUsed = min($remaining, $basicAvailable);
            $breakdown['basic_rate']['taxable'] = $basicUsed;
            $breakdown['basic_rate']['tax'] = round($basicUsed * $basicRate, 2);
            $remaining -= $basicUsed;
        }

        // Higher rate band
        $higherAvailable = $tracker->getRemainingHigherBand();
        if ($higherAvailable > 0 && $remaining > 0) {
            $higherUsed = min($remaining, $higherAvailable);
            $breakdown['higher_rate']['taxable'] = $higherUsed;
            $breakdown['higher_rate']['tax'] = round($higherUsed * $higherRate, 2);
            $remaining -= $higherUsed;
        }

        // Additional rate
        if ($remaining > 0) {
            $breakdown['additional_rate']['taxable'] = $remaining;
            $breakdown['additional_rate']['tax'] = round($remaining * $additionalRate, 2);
        }

        $breakdown['total_income_tax'] = $breakdown['basic_rate']['tax']
            + $breakdown['higher_rate']['tax']
            + $breakdown['additional_rate']['tax'];

        return $breakdown;
    }

    /**
     * Calculate trust income tax based on trust type.
     *
     * Trust taxation rules:
     * - Discretionary/Accumulation trusts: Trust pays 45% at source (39.35% for dividends)
     * - Interest in Possession trusts: Trust pays 20% at source (8.75% for dividends)
     * - Bare trusts: Beneficiary pays at their marginal rate (not handled here)
     *
     * For most trusts, the TRUST pays tax at source and the beneficiary receives
     * income net of this tax. The beneficiary may be able to reclaim tax if their
     * marginal rate is lower than the trust rate.
     */
    private function calculateTrustIncomeTax(float $trustIncome, ?string $trustType, TaxBandTracker $tracker): array
    {
        $trustsConfig = $this->taxConfig->getTrusts();

        // Default to discretionary rate from config; fall back to additional income rate band
        $incomeTaxBands = $this->taxConfig->getIncomeTax();
        $additionalRateFallback = (float) ($incomeTaxBands['bands'][2]['rate'] ?? 0.45);
        $taxRate = (float) ($trustsConfig['income_tax']['discretionary']['standard_rate'] ?? $additionalRateFallback);
        $trustTypeLabel = 'Discretionary Trust';
        $taxDescription = 'Tax paid by trust at '.number_format($taxRate * 100, 0).'%';

        $basicRateFallback = (float) ($incomeTaxBands['bands'][0]['rate'] ?? 0.20);

        switch ($trustType) {
            case 'discretionary':
            case 'accumulation_maintenance':
                $taxRate = (float) ($trustsConfig['income_tax']['discretionary']['standard_rate'] ?? $additionalRateFallback);
                $trustTypeLabel = $trustType === 'discretionary' ? 'Discretionary Trust' : 'Accumulation & Maintenance Trust';
                $taxDescription = 'Tax paid by trust at '.number_format($taxRate * 100, 0).'%';
                break;

            case 'interest_in_possession':
                $taxRate = (float) ($trustsConfig['income_tax']['interest_in_possession']['standard_rate'] ?? $basicRateFallback);
                $trustTypeLabel = 'Interest in Possession Trust';
                $taxDescription = 'Tax paid by trust at '.number_format($taxRate * 100, 0).'%';
                break;

            case 'bare':
                // Bare trusts - beneficiary pays at their marginal rate
                $taxRate = 0;
                $trustTypeLabel = 'Bare Trust';
                $taxDescription = 'Taxed as beneficiary\'s income';
                break;

            case 'settlor_interested':
                // Settlor-interested trusts - settlor pays at their marginal rate
                $taxRate = 0;
                $trustTypeLabel = 'Settlor-Interested Trust';
                $taxDescription = 'Taxed as settlor\'s income';
                break;

            case 'life_insurance':
            case 'loan':
            case 'discounted_gift':
                // These don't typically generate regular income
                $taxRate = 0;
                $trustTypeLabel = ucwords(str_replace('_', ' ', $trustType ?? 'Trust'));
                $taxDescription = 'No regular income tax applies';
                break;

            default:
                // Default to discretionary rates for unknown types
                $taxRate = (float) ($trustsConfig['income_tax']['discretionary']['standard_rate'] ?? $additionalRateFallback);
                $taxDescription = 'Tax paid by trust at '.number_format($taxRate * 100, 0).'%';
        }

        $taxPaidByTrust = round($trustIncome * $taxRate, 2);

        // Calculate personalized reclaim based on beneficiary's marginal rate
        $beneficiaryMarginalRate = $this->getBeneficiaryMarginalRate($tracker);
        $beneficiaryMarginalRateLabel = $this->getMarginalRateLabel($beneficiaryMarginalRate);
        $taxAtMarginalRate = round($trustIncome * $beneficiaryMarginalRate, 2);

        $reclaimInfo = null;
        if ($taxRate > 0) {
            $difference = $taxPaidByTrust - $taxAtMarginalRate;
            if ($difference > 0) {
                // Can reclaim
                $reclaimInfo = [
                    'type' => 'reclaim',
                    'amount' => round($difference, 2),
                    'message' => 'You can reclaim £'.number_format($difference, 0)." as you are a {$beneficiaryMarginalRateLabel} taxpayer (".round($beneficiaryMarginalRate * 100).'%) but the trust paid '.round($taxRate * 100).'% tax.',
                ];
            } elseif ($difference < 0) {
                // Owes additional tax
                $reclaimInfo = [
                    'type' => 'owe',
                    'amount' => round(abs($difference), 2),
                    'message' => 'You owe an additional £'.number_format(abs($difference), 0)." as you are a {$beneficiaryMarginalRateLabel} taxpayer (".round($beneficiaryMarginalRate * 100).'%) but the trust only paid '.round($taxRate * 100).'% tax.',
                ];
            } else {
                // No difference
                $reclaimInfo = [
                    'type' => 'none',
                    'amount' => 0,
                    'message' => "No additional tax due - trust rate matches your {$beneficiaryMarginalRateLabel} rate.",
                ];
            }
        }

        return [
            'trust_type' => $trustType,
            'trust_type_label' => $trustTypeLabel,
            'tax_rate' => $taxRate,
            'tax_description' => $taxDescription,
            'tax_paid_by_trust' => $taxPaidByTrust,
            'total_income_tax' => $taxPaidByTrust,
            'net_to_beneficiary' => round($trustIncome - $taxPaidByTrust, 2),
            'beneficiary_marginal_rate' => $beneficiaryMarginalRate,
            'beneficiary_marginal_rate_label' => $beneficiaryMarginalRateLabel,
            'tax_at_marginal_rate' => $taxAtMarginalRate,
            'reclaim_info' => $reclaimInfo,
        ];
    }

    /**
     * Get the beneficiary's marginal tax rate based on current band position
     */
    private function getBeneficiaryMarginalRate(TaxBandTracker $tracker): float
    {
        $bandPosition = $tracker->getCurrentBandPosition();

        return match ($bandPosition) {
            'personal_allowance' => 0.0,
            'basic' => 0.20,
            'higher' => 0.40,
            'additional' => 0.45,
            default => 0.20,
        };
    }

    /**
     * Get a human-readable label for the marginal rate
     */
    private function getMarginalRateLabel(float $rate): string
    {
        return match (true) {
            $rate === 0.0 => 'non',
            $rate <= 0.20 => 'basic rate',
            $rate <= 0.40 => 'higher rate',
            default => 'additional rate',
        };
    }

    /**
     * Calculate net income after income tax and National Insurance.
     *
     * @param  float  $employmentIncome  Employment income (PAYE)
     * @param  float  $selfEmploymentIncome  Self-employment income
     * @param  float  $rentalIncome  Rental income (property)
     * @param  float  $dividendIncome  Dividend income
     * @param  float  $interestIncome  Interest income (savings)
     * @param  float  $otherIncome  Other taxable income
     * @return array Net income breakdown with tax and NI details
     */
    public function calculateNetIncome(
        float $employmentIncome = 0,
        float $selfEmploymentIncome = 0,
        float $rentalIncome = 0,
        float $dividendIncome = 0,
        float $interestIncome = 0,
        float $otherIncome = 0
    ): array {
        $grossIncome = $employmentIncome + $selfEmploymentIncome + $rentalIncome + $dividendIncome + $interestIncome + $otherIncome;

        // Calculate Income Tax (including interest and dividend income)
        $nonDividendNonInterestIncome = $employmentIncome + $selfEmploymentIncome + $rentalIncome + $otherIncome;
        $incomeTax = $this->calculateIncomeTax($nonDividendNonInterestIncome, $interestIncome, $dividendIncome);

        // Calculate National Insurance
        $class1NI = $this->calculateClass1NI($employmentIncome); // Employees
        $class4NI = $this->calculateClass4NI($selfEmploymentIncome); // Self-employed
        $totalNI = $class1NI + $class4NI;

        $totalDeductions = $incomeTax + $totalNI;
        $netIncome = $grossIncome - $totalDeductions;

        return [
            'gross_income' => round($grossIncome, 2),
            'income_tax' => round($incomeTax, 2),
            'national_insurance' => round($totalNI, 2),
            'total_deductions' => round($totalDeductions, 2),
            'net_income' => round($netIncome, 2),
            'effective_tax_rate' => $grossIncome > 0 ? round(($totalDeductions / $grossIncome) * 100, 2) : 0,
            'breakdown' => [
                'employment_income' => round($employmentIncome, 2),
                'self_employment_income' => round($selfEmploymentIncome, 2),
                'rental_income' => round($rentalIncome, 2),
                'dividend_income' => round($dividendIncome, 2),
                'interest_income' => round($interestIncome, 2),
                'other_income' => round($otherIncome, 2),
                'class_1_ni' => round($class1NI, 2),
                'class_4_ni' => round($class4NI, 2),
            ],
        ];
    }

    /**
     * Calculate UK Income Tax using active tax year rates from TaxConfigService.
     * Supports:
     * - Income tax bands (basic, higher, additional)
     * - Personal allowance
     * - Personal Savings Allowance (£1,000 basic rate, £500 higher rate, £0 additional rate)
     * - Dividend allowance and dividend-specific rates
     */
    private function calculateIncomeTax(float $nonDividendNonInterestIncome, float $interestIncome, float $dividendIncome): float
    {
        // Get tax configuration from service
        $incomeTax = $this->taxConfig->getIncomeTax();
        $dividendTax = $this->taxConfig->getDividendTax();

        $personalAllowance = $incomeTax['personal_allowance'];
        $dividendAllowance = $dividendTax['allowance'];

        // Apply Personal Allowance taper for incomes above £100,000
        $totalIncomePre = $nonDividendNonInterestIncome + $interestIncome + $dividendIncome;
        $taperThreshold = $incomeTax['personal_allowance_taper_threshold'] ?? 100000;
        if ($totalIncomePre > $taperThreshold) {
            $excess = $totalIncomePre - $taperThreshold;
            $reduction = floor($excess / 2);
            $personalAllowance = max(0, $personalAllowance - $reduction);
        }

        // Get income tax bands (stored as array in seeder)
        $bands = $incomeTax['bands'];

        // Calculate absolute thresholds
        // Basic rate band ends at personal_allowance + band max
        $basicRateLimit = $personalAllowance + $bands[0]['max']; // £12,570 + £37,700 = £50,270
        // Higher rate band ends at personal_allowance + band max
        $higherRateLimit = $personalAllowance + $bands[1]['max']; // £12,570 + £150,000 = £162,570 (for historical)

        // Tax rates are stored as decimals (0.20 for 20%)
        $basicRate = $bands[0]['rate'];
        $higherRate = $bands[1]['rate'];
        $additionalRate = $bands[2]['rate'];

        // Dividend tax rates (stored as decimals)
        $basicDividendRate = $dividendTax['basic_rate'];           // 0.0875 (8.75%)
        $higherDividendRate = $dividendTax['higher_rate'];         // 0.3375 (33.75%)
        $additionalDividendRate = $dividendTax['additional_rate']; // 0.3935 (39.35%)

        $tax = 0;

        // Total income to determine tax bands
        $totalIncome = $nonDividendNonInterestIncome + $interestIncome + $dividendIncome;

        // Step 1: Calculate tax on non-dividend, non-interest income (employment, self-employment, rental, other)
        if ($nonDividendNonInterestIncome > $personalAllowance) {
            $taxableIncome = $nonDividendNonInterestIncome - $personalAllowance;

            // Basic rate
            if ($taxableIncome > 0) {
                $basicRateTaxable = min($taxableIncome, $basicRateLimit - $personalAllowance);
                $tax += $basicRateTaxable * $basicRate;
            }

            // Higher rate
            if ($taxableIncome > ($basicRateLimit - $personalAllowance)) {
                $higherRateTaxable = min(
                    $taxableIncome - ($basicRateLimit - $personalAllowance),
                    $higherRateLimit - $basicRateLimit
                );
                $tax += $higherRateTaxable * $higherRate;
            }

            // Additional rate
            if ($taxableIncome > ($higherRateLimit - $personalAllowance)) {
                $additionalRateTaxable = $taxableIncome - ($higherRateLimit - $personalAllowance);
                $tax += $additionalRateTaxable * $additionalRate;
            }
        }

        // Step 2: Calculate tax on interest income with Personal Savings Allowance
        // PSA rates sourced from TaxConfigService (basic: £1,000, higher: £500, additional: £0)
        if ($interestIncome > 0) {
            // Determine PSA based on total income band
            $psaBand = match (true) {
                $totalIncome <= $basicRateLimit => 'basic',
                $totalIncome <= $higherRateLimit => 'higher',
                default => 'additional',
            };
            $personalSavingsAllowance = $this->taxConfig->getPersonalSavingsAllowance($psaBand);

            $taxableInterest = max(0, $interestIncome - $personalSavingsAllowance);

            if ($taxableInterest > 0) {
                // Interest is taxed at standard income tax rates based on total income
                $incomeBeforeInterest = $nonDividendNonInterestIncome;

                // Tax interest at appropriate rate(s)
                if ($incomeBeforeInterest + $taxableInterest <= $basicRateLimit) {
                    // All interest in basic rate band
                    $tax += $taxableInterest * $basicRate;
                } elseif ($incomeBeforeInterest >= $basicRateLimit && $incomeBeforeInterest + $taxableInterest <= $higherRateLimit) {
                    // All interest in higher rate band
                    $tax += $taxableInterest * $higherRate;
                } elseif ($incomeBeforeInterest >= $higherRateLimit) {
                    // All interest in additional rate band
                    $tax += $taxableInterest * $additionalRate;
                } else {
                    // Interest spans multiple bands
                    $remaining = $taxableInterest;

                    // Basic rate portion
                    if ($incomeBeforeInterest < $basicRateLimit) {
                        $basicPortion = min($remaining, $basicRateLimit - $incomeBeforeInterest);
                        $tax += $basicPortion * $basicRate;
                        $remaining -= $basicPortion;
                        $incomeBeforeInterest += $basicPortion;
                    }

                    // Higher rate portion
                    if ($remaining > 0 && $incomeBeforeInterest < $higherRateLimit) {
                        $higherPortion = min($remaining, $higherRateLimit - $incomeBeforeInterest);
                        $tax += $higherPortion * $higherRate;
                        $remaining -= $higherPortion;
                        $incomeBeforeInterest += $higherPortion;
                    }

                    // Additional rate portion
                    if ($remaining > 0) {
                        $tax += $remaining * $additionalRate;
                    }
                }
            }
        }

        // Step 3: Calculate dividend tax
        if ($dividendIncome > $dividendAllowance) {
            $taxableDividends = $dividendIncome - $dividendAllowance;
            $incomeBeforeDividends = $nonDividendNonInterestIncome + $interestIncome;

            // Determine dividend tax rate based on total income band
            if ($totalIncome <= $basicRateLimit) {
                // Basic rate dividend tax
                $tax += $taxableDividends * $basicDividendRate;
            } elseif ($totalIncome <= $higherRateLimit) {
                // Dividends may span basic and higher rate
                $basicRateDividends = max(0, $basicRateLimit - $incomeBeforeDividends);
                $higherRateDividends = $taxableDividends - $basicRateDividends;

                $tax += $basicRateDividends * $basicDividendRate;
                $tax += max(0, $higherRateDividends) * $higherDividendRate;
            } else {
                // Dividends may span all three bands
                $basicRateDividends = max(0, $basicRateLimit - $incomeBeforeDividends);
                $higherRateDividends = max(0, min($taxableDividends - $basicRateDividends, $higherRateLimit - max($incomeBeforeDividends, $basicRateLimit)));
                $additionalRateDividends = $taxableDividends - $basicRateDividends - $higherRateDividends;

                $tax += $basicRateDividends * $basicDividendRate;
                $tax += $higherRateDividends * $higherDividendRate;
                $tax += max(0, $additionalRateDividends) * $additionalDividendRate;
            }
        }

        return $tax;
    }

    /**
     * Calculate Class 1 National Insurance (Employees).
     * Uses active tax year rates from TaxConfigService.
     */
    private function calculateClass1NI(float $employmentIncome): float
    {
        // Get National Insurance configuration
        $niConfig = $this->taxConfig->getNationalInsurance();
        $class1Employee = $niConfig['class_1']['employee'];

        $primaryThreshold = $class1Employee['primary_threshold'];
        $upperEarningsLimit = $class1Employee['upper_earnings_limit'];
        $mainRate = $class1Employee['main_rate'];
        $additionalRate = $class1Employee['additional_rate'];

        if ($employmentIncome <= $primaryThreshold) {
            return 0;
        }

        $ni = 0;

        // Main rate
        if ($employmentIncome > $primaryThreshold) {
            $mainRateEarnings = min($employmentIncome - $primaryThreshold, $upperEarningsLimit - $primaryThreshold);
            $ni += $mainRateEarnings * $mainRate;
        }

        // Additional rate
        if ($employmentIncome > $upperEarningsLimit) {
            $additionalRateEarnings = $employmentIncome - $upperEarningsLimit;
            $ni += $additionalRateEarnings * $additionalRate;
        }

        return $ni;
    }

    /**
     * Calculate Class 4 National Insurance (Self-Employed).
     * Uses active tax year rates from TaxConfigService.
     */
    private function calculateClass4NI(float $selfEmploymentIncome): float
    {
        // Get National Insurance configuration
        $niConfig = $this->taxConfig->getNationalInsurance();
        $class4 = $niConfig['class_4'];

        $lowerProfitsLimit = $class4['lower_profits_limit'];
        $upperProfitsLimit = $class4['upper_profits_limit'];
        $mainRate = $class4['main_rate'];
        $additionalRate = $class4['additional_rate'];

        if ($selfEmploymentIncome <= $lowerProfitsLimit) {
            return 0;
        }

        $ni = 0;

        // Main rate
        if ($selfEmploymentIncome > $lowerProfitsLimit) {
            $mainRateEarnings = min($selfEmploymentIncome - $lowerProfitsLimit, $upperProfitsLimit - $lowerProfitsLimit);
            $ni += $mainRateEarnings * $mainRate;
        }

        // Additional rate
        if ($selfEmploymentIncome > $upperProfitsLimit) {
            $additionalRateEarnings = $selfEmploymentIncome - $upperProfitsLimit;
            $ni += $additionalRateEarnings * $additionalRate;
        }

        return $ni;
    }
}
