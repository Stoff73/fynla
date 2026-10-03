<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Tax\IncomeTaxBands;

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
     * @param  float  $otherIncome  Other taxable non-savings income (no National Insurance)
     * @param  float  $shareVestIncome  Share-scheme vests this year: employment income for
     *                                  Income Tax (ITEPA 2003); National Insurance on
     *                                  them is not charged here until its source is settled
     * @param  float  $class1Share  The share of the year's pay still liable to Class 1: none is
     *                              due on pay received on or after State Pension age
     *                              (SSCBA 1992 s6(3)), so 1 before it, 0 after, part in between
     * @param  bool  $class4Applies  False from the 6 April after State Pension age
     * @param  float  $salarySacrifice  Pay given up for a pension under salary sacrifice:
     *                                  $employmentIncome is the pay before it, and it comes
     *                                  off before Income Tax and Class 1 National Insurance
     *                                  (it is an employer contribution, FA 2004 s228ZA(3))
     * @param  float  $bandExtension  Gift Aid and relief-at-source pension payments, gross:
     *                                they reduce adjusted net income (ITA 2007 s58) and
     *                                extend the basic and higher rate limits (s414,
     *                                FA 2004 s192(4)) rather than coming off income
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
        float $section24Credit = 0,
        float $blindPersonsAllowance = 0,
        float $otherIncome = 0,
        float $shareVestIncome = 0,
        float $class1Share = 1.0,
        bool $class4Applies = true,
        float $bandExtension = 0,
        float $salarySacrifice = 0
    ): array {
        $incomeTaxConfig = $this->taxConfig->getIncomeTax();
        $salarySacrifice = min(max(0.0, $salarySacrifice), $employmentIncome);
        // The pay actually received: the gross less the sacrifice.
        $payAfterSacrifice = $employmentIncome - $salarySacrifice;

        // Personal Allowance taper base, adjusted net income (ITA 2007 s58): total
        // income less net-pay pension relief, Gift Aid and relief-at-source payments.
        $totalIncomePreRelief = $employmentIncome + $selfEmploymentIncome + $rentalIncome
            + $pensionIncome + $trustIncome + $interestIncome + $dividendIncome
            + $otherIncome + $shareVestIncome - $salarySacrifice;
        $taxableIncomePreRelief = $totalIncomePreRelief - $pensionContributions - max(0.0, $bandExtension);

        // The tapered allowance is handed to the tracker separately rather than
        // written back over the config: the basic-rate band width is derived from
        // the FULL allowance, so overwriting it destroyed the only record of it
        // and left the 20% band at £50,270 wide (W-0174).
        $personalAllowance = IncomeTaxBands::taperedPersonalAllowance($incomeTaxConfig, $taxableIncomePreRelief);

        // W-0511 — the Blind Person's Allowance is added AFTER the taper and never
        // before it. ITA 2007 s38 gives it and s23 Step 3 deducts it; s58 does not
        // (W-0485), so the taper base above must not see it. Like the Personal
        // Allowance it lifts both limits, as calculateIncomeTax does, and Gift Aid
        // and relief-at-source payments extend them.
        $tracker = new TaxBandTracker($incomeTaxConfig, $personalAllowance, max(0.0, $blindPersonsAllowance), max(0.0, $bandExtension));

        $incomeBreakdowns = [];
        $totalGross = 0;
        $totalTax = 0;
        $totalNI = 0;

        // One card for employment, self-employment, rental profit and pension
        // income in payment: they share the same tax bands (20%/40%/45%). It is
        // NOT four earned incomes, which is what calling it the "Earned Income"
        // card led the header to assert (W-0423) — `combinedIncomeLabel` names
        // whichever of them is present.
        // Other income and share vests are non-savings income too (ITA 2007
        // s16), so they share the card and its bands.
        $hasEarnedIncome = $employmentIncome > 0 || $selfEmploymentIncome > 0 || $rentalIncome > 0 || $pensionIncome > 0
            || $otherIncome > 0 || $shareVestIncome > 0;

        if ($hasEarnedIncome) {
            // Calculate taxable employment income (after pension contributions)
            $taxableEmploymentIncome = max(0, $payAfterSacrifice - $pensionContributions);

            // Total taxable earned income for tax calculation
            $totalTaxableEarnedIncome = $taxableEmploymentIncome + $selfEmploymentIncome + $rentalIncome + $pensionIncome
                + $otherIncome + $shareVestIncome;

            // Calculate tax on combined earned income
            $taxAllocation = $tracker->allocateIncome($totalTaxableEarnedIncome);

            // Calculate NI separately for employment and self-employment, and
            // none past State Pension age: Class 1 stops once it is reached
            // (SSCBA 1992 s6(3), https://www.legislation.gov.uk/ukpga/1992/4/section/6),
            // Class 4 from the 6 April after it
            // (https://www.gov.uk/national-insurance/what-national-insurance-is).
            $class1Share = min(1.0, max(0.0, $class1Share));
            $class1NI = $payAfterSacrifice > 0 && $class1Share > 0 ? $this->calculateClass1NIDetailed($payAfterSacrifice, $class1Share) : null;
            $class4NI = $selfEmploymentIncome > 0 && $class4Applies ? $this->calculateClass4NIDetailed($selfEmploymentIncome) : null;

            $totalNIAmount = ($class1NI['total_ni'] ?? 0) + ($class4NI['total_ni'] ?? 0);

            // Build income components for display. Each carries a stable `key` —
            // the client keys its per-property rental drill-down off that rather
            // than off the display label, so the label stays free to change.
            $incomeComponents = [];

            if ($employmentIncome > 0) {
                $incomeComponents[] = [
                    'key' => 'employment',
                    'label' => 'Employment Income',
                    'amount' => round($employmentIncome, 2),
                ];

                if ($salarySacrifice > 0) {
                    $incomeComponents[] = [
                        'key' => 'salary_sacrifice',
                        'label' => 'Salary Sacrifice',
                        'amount' => round(-$salarySacrifice, 2),
                        'is_deduction' => true,
                    ];
                }

                if ($pensionContributions > 0) {
                    $incomeComponents[] = [
                        'key' => 'pension_contributions',
                        'label' => 'Pension Contributions',
                        'amount' => round(-$pensionContributions, 2),
                        'is_deduction' => true,
                    ];
                }
            }

            if ($selfEmploymentIncome > 0) {
                $incomeComponents[] = [
                    'key' => 'self_employment',
                    'label' => 'Self-Employment Income',
                    'amount' => round($selfEmploymentIncome, 2),
                ];
            }

            if ($rentalIncome > 0) {
                // "Rental Profit", not "Rental Income": this is rent less allowable
                // letting expenses at the user's ownership share, and labelling it
                // as income left a figure nobody could reconcile against their own
                // property records (W-0175).
                $incomeComponents[] = [
                    'key' => 'rental',
                    'label' => 'Rental Profit',
                    'amount' => round($rentalIncome, 2),
                ];
            }

            if ($pensionIncome > 0) {
                $incomeComponents[] = [
                    'key' => 'pension_income',
                    'label' => 'Pension Income',
                    'amount' => round($pensionIncome, 2),
                ];
            }

            if ($shareVestIncome > 0) {
                $incomeComponents[] = [
                    'key' => 'vesting',
                    'label' => 'Share Scheme Vests',
                    'amount' => round($shareVestIncome, 2),
                ];
            }

            if ($otherIncome > 0) {
                $incomeComponents[] = [
                    'key' => 'other',
                    'label' => 'Other Income',
                    'amount' => round($otherIncome, 2),
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

            // Total income from these sources: sacrificed pay was never income.
            $grossEarnedIncome = $payAfterSacrifice + $selfEmploymentIncome + $rentalIncome + $pensionIncome
                + $otherIncome + $shareVestIncome;

            $incomeBreakdowns[] = [
                'income_type' => 'earned',
                'income_type_label' => $this->combinedIncomeLabel($employmentIncome + $shareVestIncome, $selfEmploymentIncome, $rentalIncome, $pensionIncome, $otherIncome),
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
            $interestBreakdown = $this->calculateInterestTaxDetailed($interestIncome, $dividendIncome, $tracker);

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
                // The tapered Personal Allowance this calculation actually used, and
                // the Blind Person's Allowance given on top of it. Published because
                // `IncomeDefinitionsService` shows the user a Personal Allowance and
                // the two services must be holdable to the same figure — asserting
                // agreement against a hand-written literal proves nothing (W-0485).
                'personal_allowance' => round($personalAllowance, 2),
                'blind_persons_allowance' => round(max(0.0, $blindPersonsAllowance), 2),
            ],
            'tax_year' => $this->taxConfig->getTaxYear(),
        ];
    }

    /**
     * What to call the card that holds every income taxed at the main rates.
     *
     * It was called "Earned Income" whatever was in it, and it holds four things:
     * employment, self-employment, **rental profit** and **pension income in
     * payment**. The last two are not earned income, so a landlord read a header
     * saying "Earned Income £159,290" over a figure that was £145,000 of salary
     * and £14,290 of rent — a mislabelled header over a right number, on the one
     * page whose whole value is that the reader can check it (W-0423).
     *
     * The label names what is present rather than asserting a category, so it
     * stays true as the mix changes and cannot drift back.
     */
    private function combinedIncomeLabel(
        float $employmentIncome,
        float $selfEmploymentIncome,
        float $rentalIncome,
        float $pensionIncome,
        float $otherIncome = 0
    ): string {
        $kinds = [];

        if ($employmentIncome > 0 || $selfEmploymentIncome > 0) {
            $kinds[] = 'Earned';
        }
        if ($rentalIncome > 0) {
            $kinds[] = 'Rental';
        }
        if ($pensionIncome > 0) {
            $kinds[] = 'Pension';
        }
        if ($otherIncome > 0) {
            $kinds[] = 'Other';
        }

        if ($kinds === []) {
            return 'Earned Income';
        }

        if (count($kinds) === 1) {
            return $kinds[0].' Income';
        }

        $last = array_pop($kinds);

        return implode(', ', $kinds).' and '.$last.' Income';
    }

    /**
     * Calculate Class 1 NI with detailed breakdown
     */
    private function calculateClass1NIDetailed(float $employmentIncome, float $share = 1.0): array
    {
        $niConfig = $this->taxConfig->getNationalInsurance();
        $class1Employee = $niConfig['class_1']['employee'];

        $primaryThreshold = $class1Employee['primary_threshold'];
        $upperEarningsLimit = $class1Employee['upper_earnings_limit'];
        $mainRate = $class1Employee['main_rate'];
        $additionalRate = $class1Employee['additional_rate'];

        $breakdown = [
            'class' => 'Class 1',
            // What National Insurance is actually charged on. The card header
            // used to sit a flat "NI Applies" badge beside the COMBINED earned
            // figure, which on a landlord with a workplace salary asserted
            // National Insurance over rental profit — neither earned income nor
            // liable to it (W-0423). The bands below cannot answer this: they
            // start at the primary threshold, so they sum to less than the pay.
            'base' => round($employmentIncome, 2),
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

        // Only the pay received before State Pension age is liable (SSCBA 1992 s6(3)).
        if ($share < 1.0) {
            $breakdown['main_rate']['contribution'] = round($breakdown['main_rate']['contribution'] * $share, 2);
            $breakdown['additional_rate']['contribution'] = round($breakdown['additional_rate']['contribution'] * $share, 2);
            $breakdown['share_of_year_before_state_pension_age'] = round($share, 4);
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
            'base' => round($selfEmploymentIncome, 2),
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
     * Tax on interest, in the order ITA 2007 s16 and s12 set: any Personal
     * Allowance left after non-savings income, then the 0% starting rate for
     * savings (£5,000 in config, less non-savings income above the allowance),
     * then the Personal Savings Allowance, then the band rates. The 0% slices
     * still occupy band space. The allowance is sized by the band the
     * individual's whole income reaches, dividends included (s12B(3)):
     * https://www.legislation.gov.uk/ukpga/2007/3/section/12B
     */
    private function calculateInterestTaxDetailed(float $interestIncome, float $dividendIncome, TaxBandTracker $tracker): array
    {
        $config = $tracker->getConfig();
        $allocatedBefore = $tracker->getTotalAllocated();

        $wholeIncome = $allocatedBefore + $interestIncome + $dividendIncome;
        $band = match (true) {
            $wholeIncome <= $config['basic_rate_limit'] => 'basic',
            $wholeIncome <= $config['higher_rate_limit'] => 'higher',
            default => 'additional',
        };
        $psa = (float) $this->taxConfig->getPersonalSavingsAllowance($band);

        $remaining = $interestIncome;
        $inAllowance = min($remaining, $tracker->getRemainingPersonalAllowance());
        $tracker->allocateZeroRated($inAllowance);
        $remaining -= $inAllowance;

        $srsBand = (float) ($this->taxConfig->getIncomeTax()['starting_rate_for_savings']['band'] ?? 0);
        $nonSavingsAboveAllowance = max(0.0, $allocatedBefore - $config['personal_allowance']);
        $startingRate = min($remaining, max(0.0, $srsBand - $nonSavingsAboveAllowance));
        $tracker->allocateZeroRated($startingRate);
        $remaining -= $startingRate;

        $psaUsed = min($remaining, $psa);
        $tracker->allocateZeroRated($psaUsed);
        $remaining -= $psaUsed;

        $taxAllocation = $tracker->allocateIncome($remaining);
        $taxAllocation['personal_allowance_used'] = $inAllowance;
        $taxAllocation['starting_rate_for_savings_used'] = $startingRate;
        $taxAllocation['personal_savings_allowance'] = $psa;
        $taxAllocation['taxable_after_psa'] = $remaining;

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

        // Any Personal Allowance still unused covers dividends (ITA 2007 s25),
        // and the dividend allowance is a 0% rate that still occupies band
        // space (s13A): https://www.legislation.gov.uk/ukpga/2007/3/section/13A
        $inPersonalAllowance = min($dividendIncome, $tracker->getRemainingPersonalAllowance());
        $tracker->allocateZeroRated($inPersonalAllowance);
        $allowanceUsed = min($dividendIncome - $inPersonalAllowance, (float) $allowance);
        $tracker->allocateZeroRated($allowanceUsed);
        $taxableDividends = max(0, $dividendIncome - $inPersonalAllowance - $allowanceUsed);

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
     * Employee Class 1 National Insurance on a year's employment pay — the ONE
     * lookup every salary sacrifice figure uses (the card, the affordability
     * check, the analyser and the threshold line all asked this separately).
     */
    public function employeeClass1Ni(float $pay): float
    {
        return (float) ($this->calculateNetIncome(max(0.0, $pay))['breakdown']['class_1_ni'] ?? 0);
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
     * @param  float  $pensionContributions  Gross employee pension contributions (relief-at-source).
     *                                       Deducted from taxable earned income AND from ANI for PA taper.
     * @param  float  $giftAidGross  Grossed-up Gift Aid donations.
     *                               Deducted from ANI for PA taper only — taxable income is not
     *                               reduced (basic-rate relief is given to the charity at source).
     * @return array Net income breakdown with tax and NI details
     */
    public function calculateNetIncome(
        float $employmentIncome = 0,
        float $selfEmploymentIncome = 0,
        float $rentalIncome = 0,
        float $dividendIncome = 0,
        float $interestIncome = 0,
        float $otherIncome = 0,
        float $pensionContributions = 0,
        float $giftAidGross = 0,
        float $blindPersonsAllowance = 0
    ): array {
        $grossIncome = $employmentIncome + $selfEmploymentIncome + $rentalIncome + $dividendIncome + $interestIncome + $otherIncome;

        // Calculate Income Tax — pension is deducted from earned income before band
        // allocation (net-pay model), and both pension + Gift Aid reduce ANI for the
        // PA taper. See calculateIncomeTax for the ANI-aware taper logic.
        $nonDividendNonInterestIncome = $employmentIncome + $selfEmploymentIncome + $rentalIncome + $otherIncome;
        $incomeTax = $this->calculateIncomeTax(
            $nonDividendNonInterestIncome,
            $interestIncome,
            $dividendIncome,
            $pensionContributions,
            $giftAidGross,
            $blindPersonsAllowance
        );

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
                // W-0511 — published so a caller can show that the allowance was
                // actually given, rather than inferring it from a tax figure.
                'blind_persons_allowance' => round($blindPersonsAllowance, 2),
            ],
        ];
    }

    /**
     * Calculate UK Income Tax using active tax year rates from TaxConfigService.
     * Supports:
     * - Income tax bands (basic, higher, additional)
     * - Personal allowance with Adjusted-Net-Income taper (HMRC ITA 2007 s35-s37)
     * - Personal Savings Allowance (£1,000 basic rate, £500 higher rate, £0 additional rate)
     * - Dividend allowance and dividend-specific rates
     */
    private function calculateIncomeTax(
        float $nonDividendNonInterestIncome,
        float $interestIncome,
        float $dividendIncome,
        float $pensionContributions = 0,
        float $giftAidGross = 0,
        float $blindPersonsAllowance = 0
    ): float {
        // Get tax configuration from service
        $incomeTax = $this->taxConfig->getIncomeTax();
        $dividendTax = $this->taxConfig->getDividendTax();

        $dividendAllowance = $dividendTax['allowance'];

        // Deduct pension contributions from taxable earned income (net-pay model — caller
        // is expected to pass gross earned income; pension is excluded from the tax base).
        $nonDividendNonInterestIncome = max(0.0, $nonDividendNonInterestIncome - $pensionContributions);

        // Apply Personal Allowance taper using Adjusted Net Income (NOT gross). ANI =
        // (gross total income) − pension − Gift Aid. Since pension has already been
        // subtracted from $nonDividendNonInterestIncome above, only Gift Aid remains.
        $totalIncomePre = $nonDividendNonInterestIncome + $interestIncome + $dividendIncome;
        $adjustedNetIncome = max(0.0, $totalIncomePre - $giftAidGross);

        // Band geometry from the one home (W-0174). The tapered allowance moves the
        // basic-rate LIMIT down with it; the £37,700 band width is what stays fixed.
        //
        // Gift Aid band extension (ITA 2007 s414): grossed-up donations extend both
        // limits by the gross donation. That is the mechanism delivering higher- and
        // additional-rate relief — more income falls in a lower band. Basic-rate
        // relief already went to the charity at source, so Gift Aid does NOT reduce
        // taxable income.
        // W-0511 — the Blind Person's Allowance is given HERE and nowhere else. ITA 2007
        // s38 grants it and s23 Step 3 deducts it, downstream of the taper, so it raises
        // the allowance and carries both limits with it. It must not reach the adjusted
        // net income above: that was W-0485, and it produced relief nobody was entitled
        // to instead of the relief they were.
        $taxBands = IncomeTaxBands::forAdjustedNetIncome($incomeTax, $adjustedNetIncome)
            ->extendedBy($giftAidGross)
            ->withBlindPersonsAllowance($blindPersonsAllowance);

        $personalAllowance = $taxBands->personalAllowance;
        $basicRateLimit = $taxBands->basicRateLimit;
        $higherRateLimit = $taxBands->higherRateLimit;
        $basicRate = $taxBands->basicRate;
        $higherRate = $taxBands->higherRate;
        $additionalRate = $taxBands->additionalRate;

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

        // Steps 2 and 3: savings then dividends stack on top of non-savings
        // income (ITA 2007 s16). Each slice starts where the last one ended; any
        // part below the Personal Allowance is 0% (s25), and the 0% slices
        // (starting rate for savings s12, Personal Savings Allowance s12B,
        // dividend allowance s13A) still occupy band space.
        $slice = static function (float $from, float $amount, array $rates) use ($personalAllowance, $basicRateLimit, $higherRateLimit): float {
            $to = $from + $amount;
            $in = static fn (float $lo, float $hi): float => max(0.0, min($to, $hi) - max($from, $lo));

            return $in($personalAllowance, $basicRateLimit) * $rates[0]
                + $in($basicRateLimit, $higherRateLimit) * $rates[1]
                + $in($higherRateLimit, PHP_FLOAT_MAX) * $rates[2];
        };
        $position = $nonDividendNonInterestIncome;

        if ($interestIncome > 0) {
            $inAllowance = min($interestIncome, max(0.0, $personalAllowance - $position));
            $srsBand = (float) ($incomeTax['starting_rate_for_savings']['band'] ?? 0);
            $startingRate = min($interestIncome - $inAllowance, max(0.0, $srsBand - max(0.0, $nonDividendNonInterestIncome - $personalAllowance)));
            $psaBand = match (true) {
                $totalIncome <= $basicRateLimit => 'basic',
                $totalIncome <= $higherRateLimit => 'higher',
                default => 'additional',
            };
            $psaUsed = min($interestIncome - $inAllowance - $startingRate, (float) $this->taxConfig->getPersonalSavingsAllowance($psaBand));
            $position += $inAllowance + $startingRate + $psaUsed;
            $taxableInterest = $interestIncome - $inAllowance - $startingRate - $psaUsed;
            $tax += $slice($position, $taxableInterest, [$basicRate, $higherRate, $additionalRate]);
            $position += $taxableInterest;
        }

        if ($dividendIncome > 0) {
            $inAllowance = min($dividendIncome, max(0.0, $personalAllowance - $position));
            $allowanceUsed = min($dividendIncome - $inAllowance, (float) $dividendAllowance);
            $position += $inAllowance + $allowanceUsed;
            $tax += $slice($position, $dividendIncome - $inAllowance - $allowanceUsed, [$basicDividendRate, $higherDividendRate, $additionalDividendRate]);
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
