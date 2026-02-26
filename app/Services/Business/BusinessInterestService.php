<?php

declare(strict_types=1);

namespace App\Services\Business;

use App\Models\BusinessInterest;
use App\Models\User;
use App\Services\TaxConfigService;
use Carbon\Carbon;

class BusinessInterestService
{
    public function __construct(
        private readonly TaxConfigService $taxConfig
    ) {}

    /**
     * Get a comprehensive summary of a business interest.
     */
    public function getBusinessSummary(BusinessInterest $business): array
    {
        return [
            'id' => $business->id,
            'business_name' => $business->business_name,
            'business_type' => $business->business_type,
            'business_type_label' => $this->getBusinessTypeLabel($business->business_type),
            'company_number' => $business->company_number,
            'industry_sector' => $business->industry_sector,
            'country' => $business->country,

            'ownership' => [
                'type' => $business->ownership_type,
                'percentage' => (float) ($business->ownership_percentage ?? 100),
                'joint_owner_id' => $business->joint_owner_id,
            ],

            'valuation' => [
                'current_valuation' => (float) $business->current_valuation,
                'valuation_date' => $business->valuation_date?->format('Y-m-d'),
                'valuation_method' => $business->valuation_method,
            ],

            'financials' => [
                'annual_revenue' => (float) ($business->annual_revenue ?? 0),
                'annual_profit' => (float) ($business->annual_profit ?? 0),
                'annual_dividend_income' => (float) ($business->annual_dividend_income ?? 0),
            ],

            'tax_compliance' => [
                'vat_registered' => $business->vat_registered,
                'vat_number' => $business->vat_number,
                'utr_number' => $business->utr_number,
                'tax_year_end' => $business->tax_year_end?->format('Y-m-d'),
                'employee_count' => $business->employee_count ?? 0,
                'paye_reference' => $business->paye_reference,
                'trading_status' => $business->trading_status ?? 'trading',
            ],

            'exit_planning' => [
                'acquisition_date' => $business->acquisition_date?->format('Y-m-d'),
                'acquisition_cost' => (float) ($business->acquisition_cost ?? 0),
                'bpr_eligible' => $business->bpr_eligible ?? false,
                'badr_eligible' => $this->isBADREligible($business),
                'years_held' => $this->calculateYearsHeld($business),
            ],

            'description' => $business->description,
            'notes' => $business->notes,
        ];
    }

    /**
     * Calculate user's share of the business value.
     *
     * Business interests differ from other assets because:
     * - Individual ownership can still mean partial share (e.g., 60% of company shares)
     * - Joint ownership with spouse uses ownership_percentage to split between user_id and joint_owner_id
     *
     * Unlike properties/savings where 'individual' = 100%, business ownership_percentage
     * represents the user's actual shareholding regardless of ownership_type.
     */
    public function calculateUserShare(BusinessInterest $business, int $userId): float
    {
        $fullValue = (float) ($business->current_valuation ?? 0);
        $ownershipType = $business->ownership_type ?? 'individual';
        $percentage = (float) ($business->ownership_percentage ?? 100);

        // Trust ownership - trustee/business controlled by trust
        if ($ownershipType === 'trust') {
            return $business->user_id === $userId ? $fullValue : 0.0;
        }

        // Individual ownership - use ownership_percentage for shareholding
        // (e.g., owning 60% of a company individually)
        if ($ownershipType === 'individual') {
            return $business->user_id === $userId ? $fullValue * ($percentage / 100) : 0.0;
        }

        // Joint ownership - split between user and joint_owner based on percentage
        if ($business->user_id === $userId) {
            return $fullValue * ($percentage / 100);
        }

        if ($business->joint_owner_id === $userId) {
            return $fullValue * ((100 - $percentage) / 100);
        }

        return 0.0;
    }

    /**
     * Get tax deadlines based on business type and registration status.
     */
    public function getTaxDeadlines(BusinessInterest $business): array
    {
        $deadlines = [];
        $now = Carbon::now();
        $currentTaxYear = $this->getCurrentTaxYear();
        $nextTaxYear = $this->getNextTaxYear();

        // Add deadlines based on business type
        switch ($business->business_type) {
            case 'sole_trader':
            case 'partnership':
            case 'llp':
                $deadlines = array_merge($deadlines, $this->getSelfAssessmentDeadlines($currentTaxYear, $nextTaxYear));
                break;

            case 'limited_company':
                $deadlines = array_merge($deadlines, $this->getCompanyDeadlines($business));
                break;
        }

        // Add VAT deadlines if registered
        if ($business->vat_registered) {
            $deadlines = array_merge($deadlines, $this->getVATDeadlines());
        }

        // Add employer deadlines if has employees
        if (($business->employee_count ?? 0) > 0) {
            $deadlines = array_merge($deadlines, $this->getEmployerDeadlines());
        }

        // Sort by date
        usort($deadlines, fn ($a, $b) => strtotime($a['date']) <=> strtotime($b['date']));

        return $deadlines;
    }

    /**
     * Calculate exit/sale scenario with CGT and BADR.
     */
    public function calculateExitScenario(BusinessInterest $business, ?User $user = null): array
    {
        $salePrice = (float) ($business->current_valuation ?? 0);
        $acquisitionCost = (float) ($business->acquisition_cost ?? 0);
        $ownershipPercentage = (float) ($business->ownership_percentage ?? 100) / 100;

        // Calculate user's share
        $userSaleProceeds = $salePrice * $ownershipPercentage;
        $userCostBasis = $acquisitionCost * $ownershipPercentage;
        $capitalGain = max(0, $userSaleProceeds - $userCostBasis);

        // Check BADR eligibility
        $badrEligible = $this->isBADREligible($business);
        $yearsHeld = $this->calculateYearsHeld($business);

        // Determine CGT rate (rates stored as decimals: 0.10 = 10%)
        $cgtConfig = $this->taxConfig->getCapitalGainsTax();
        $badrRate = $cgtConfig['business_asset_disposal_relief_rate'] ?? 0.10;
        $higherRate = $cgtConfig['higher_rate'] ?? 0.20;
        $basicRate = $cgtConfig['basic_rate'] ?? 0.10;

        // BADR lifetime limit
        $badrLimit = $cgtConfig['business_asset_disposal_relief_lifetime_limit'] ?? 1000000;

        $warnings = [];
        $cgtRate = $higherRate; // Default to higher rate

        if ($badrEligible) {
            if ($capitalGain <= $badrLimit) {
                $cgtRate = $badrRate;
            } else {
                $cgtRate = $badrRate;
                $warnings[] = 'Capital gain exceeds the £'.number_format($badrLimit).' lifetime Business Asset Disposal Relief limit. Gains above this threshold may be taxed at higher rates.';
            }
        } else {
            $warnings[] = 'Business Asset Disposal Relief may not apply. The 10% rate requires 2+ years ownership and trading business status.';
        }

        // Calculate CGT due (cgtRate is already a decimal, e.g., 0.10 for 10%)
        $cgtDue = $capitalGain * $cgtRate;
        $postTaxProceeds = $userSaleProceeds - $cgtDue;

        // Business Relief consideration
        $bprNote = null;
        if ($business->bpr_eligible && $yearsHeld >= 2) {
            $bprNote = 'This business may qualify for 100% Business Relief for Inheritance Tax purposes.';
        }

        return [
            'sale_price' => $salePrice,
            'ownership_percentage' => $ownershipPercentage * 100,
            'user_sale_proceeds' => round($userSaleProceeds, 2),
            'acquisition_cost' => $acquisitionCost,
            'user_cost_basis' => round($userCostBasis, 2),
            'capital_gain' => round($capitalGain, 2),
            'years_held' => $yearsHeld,
            'badr_eligible' => $badrEligible,
            'badr_reasons' => $this->getBADRReasons($business),
            'cgt_rate' => $cgtRate * 100,  // Convert decimal to percentage for display
            'cgt_due' => round($cgtDue, 2),
            'post_tax_proceeds' => round($postTaxProceeds, 2),
            'bpr_eligible' => $business->bpr_eligible,
            'bpr_note' => $bprNote,
            'warnings' => $warnings,
        ];
    }

    /**
     * Check if business qualifies for Business Asset Disposal Relief.
     */
    private function isBADREligible(BusinessInterest $business): bool
    {
        // Must be held for at least 2 years
        $yearsHeld = $this->calculateYearsHeld($business);
        if ($yearsHeld < 2) {
            return false;
        }

        // Must be a trading business (not dormant or investment company)
        if ($business->trading_status !== 'trading') {
            return false;
        }

        // Sole traders, partnerships, and LLPs are typically eligible
        // Limited companies need specific conditions
        $eligibleTypes = ['sole_trader', 'partnership', 'llp', 'limited_company'];
        if (! in_array($business->business_type, $eligibleTypes, true)) {
            return false;
        }

        return true;
    }

    /**
     * Get reasons for BADR eligibility/ineligibility.
     */
    private function getBADRReasons(BusinessInterest $business): array
    {
        $reasons = [];
        $yearsHeld = $this->calculateYearsHeld($business);

        if ($yearsHeld >= 2) {
            $reasons[] = 'Held for '.number_format($yearsHeld, 1).' years (2+ years required)';
        } else {
            $reasons[] = 'Only held for '.number_format($yearsHeld, 1).' years (2+ years required)';
        }

        if ($business->trading_status === 'trading') {
            $reasons[] = 'Business is actively trading';
        } else {
            $reasons[] = 'Business is not actively trading (status: '.($business->trading_status ?? 'unknown').')';
        }

        return $reasons;
    }

    /**
     * Calculate how many years the business has been held.
     */
    private function calculateYearsHeld(BusinessInterest $business): float
    {
        if (! $business->acquisition_date) {
            return 0;
        }

        return $business->acquisition_date->diffInYears(Carbon::now(), true);
    }

    /**
     * Get Self Assessment deadlines.
     */
    private function getSelfAssessmentDeadlines(string $currentTaxYear, string $nextTaxYear): array
    {
        $deadlines = [];
        $now = Carbon::now();

        // Tax year end (5 April)
        $taxYearEnd = Carbon::createFromFormat('Y-m-d', substr($currentTaxYear, 5, 4).'-04-05');

        // Paper return deadline (31 October)
        $paperDeadline = Carbon::createFromFormat('Y-m-d', substr($nextTaxYear, 0, 4).'-10-31');
        if ($paperDeadline->gt($now)) {
            $deadlines[] = [
                'name' => 'Self Assessment Paper Return',
                'date' => $paperDeadline->format('Y-m-d'),
                'description' => "Paper tax return deadline for {$currentTaxYear} tax year",
                'type' => 'self_assessment',
                'days_until' => $now->diffInDays($paperDeadline, false),
            ];
        }

        // Online return deadline (31 January)
        $onlineDeadline = Carbon::createFromFormat('Y-m-d', substr($nextTaxYear, 0, 4).'-01-31')->addYear();
        if ($onlineDeadline->gt($now)) {
            $deadlines[] = [
                'name' => 'Self Assessment Online Return & Payment',
                'date' => $onlineDeadline->format('Y-m-d'),
                'description' => "Online tax return and payment deadline for {$currentTaxYear} tax year",
                'type' => 'self_assessment',
                'days_until' => $now->diffInDays($onlineDeadline, false),
            ];
        }

        // Payment on Account - 31 July
        $poaJuly = Carbon::createFromFormat('Y-m-d', $now->year.'-07-31');
        if ($poaJuly->lt($now)) {
            $poaJuly->addYear();
        }
        $deadlines[] = [
            'name' => 'Payment on Account',
            'date' => $poaJuly->format('Y-m-d'),
            'description' => "Second payment on account for {$currentTaxYear} tax year",
            'type' => 'payment',
            'days_until' => $now->diffInDays($poaJuly, false),
        ];

        return $deadlines;
    }

    /**
     * Get Limited Company deadlines based on year-end.
     */
    private function getCompanyDeadlines(BusinessInterest $business): array
    {
        $deadlines = [];
        $now = Carbon::now();

        // Use tax_year_end or default to 31 March
        $yearEnd = $business->tax_year_end ?? Carbon::createFromFormat('m-d', '03-31');
        if ($yearEnd instanceof Carbon) {
            // If past, get next occurrence
            if ($yearEnd->lt($now)) {
                $yearEnd = $yearEnd->copy()->year($now->year);
                if ($yearEnd->lt($now)) {
                    $yearEnd->addYear();
                }
            }
        } else {
            $yearEnd = Carbon::createFromFormat('Y-m-d', $now->year.'-03-31');
            if ($yearEnd->lt($now)) {
                $yearEnd->addYear();
            }
        }

        // Corporation Tax - 9 months + 1 day after year end
        $ctDeadline = $yearEnd->copy()->addMonths(9)->addDay();
        $deadlines[] = [
            'name' => 'Corporation Tax Payment',
            'date' => $ctDeadline->format('Y-m-d'),
            'description' => 'Corporation Tax payment deadline (9 months + 1 day after year-end)',
            'type' => 'corporation_tax',
            'days_until' => $now->diffInDays($ctDeadline, false),
        ];

        // Company Accounts - 9 months after year end
        $accountsDeadline = $yearEnd->copy()->addMonths(9);
        $deadlines[] = [
            'name' => 'File Company Accounts',
            'date' => $accountsDeadline->format('Y-m-d'),
            'description' => 'Companies House accounts filing deadline (9 months after year-end)',
            'type' => 'accounts',
            'days_until' => $now->diffInDays($accountsDeadline, false),
        ];

        // CT600 Return - 12 months after year end
        $ct600Deadline = $yearEnd->copy()->addMonths(12);
        $deadlines[] = [
            'name' => 'Company Tax Return (CT600)',
            'date' => $ct600Deadline->format('Y-m-d'),
            'description' => 'HMRC Company Tax Return deadline (12 months after year-end)',
            'type' => 'tax_return',
            'days_until' => $now->diffInDays($ct600Deadline, false),
        ];

        // Confirmation Statement - annually
        $confirmationDeadline = $now->copy()->addDays(14);
        $deadlines[] = [
            'name' => 'Confirmation Statement',
            'date' => $confirmationDeadline->format('Y-m-d'),
            'description' => 'Annual confirmation statement due within 14 days of review period end',
            'type' => 'confirmation',
            'days_until' => 14,
            'note' => 'Check your specific confirmation statement deadline on Companies House',
        ];

        return $deadlines;
    }

    /**
     * Get VAT return deadlines.
     */
    private function getVATDeadlines(): array
    {
        $deadlines = [];
        $now = Carbon::now();

        // VAT quarters end on last day of March, June, September, December
        $quarterEnds = [
            Carbon::createFromFormat('Y-m-d', $now->year.'-03-31'),
            Carbon::createFromFormat('Y-m-d', $now->year.'-06-30'),
            Carbon::createFromFormat('Y-m-d', $now->year.'-09-30'),
            Carbon::createFromFormat('Y-m-d', $now->year.'-12-31'),
        ];

        foreach ($quarterEnds as $quarterEnd) {
            // VAT return due 1 month + 7 days after quarter end
            $vatDeadline = $quarterEnd->copy()->addMonth()->addDays(7);

            if ($vatDeadline->gt($now)) {
                $deadlines[] = [
                    'name' => 'VAT Return & Payment',
                    'date' => $vatDeadline->format('Y-m-d'),
                    'description' => 'VAT return and payment for quarter ending '.$quarterEnd->format('d M Y'),
                    'type' => 'vat',
                    'days_until' => $now->diffInDays($vatDeadline, false),
                ];
                break; // Only show next deadline
            }
        }

        return $deadlines;
    }

    /**
     * Get employer (PAYE) deadlines.
     */
    private function getEmployerDeadlines(): array
    {
        $now = Carbon::now();

        // PAYE due 22nd of following month
        $payeDeadline = Carbon::createFromFormat('Y-m-d', $now->format('Y-m').'-22');
        if ($payeDeadline->lt($now)) {
            $payeDeadline->addMonth();
        }

        return [
            [
                'name' => 'PAYE & NIC Payment',
                'date' => $payeDeadline->format('Y-m-d'),
                'description' => 'Monthly PAYE and National Insurance contributions due by 22nd',
                'type' => 'paye',
                'days_until' => $now->diffInDays($payeDeadline, false),
            ],
            [
                'name' => 'Auto-Enrolment Pension Contributions',
                'date' => $payeDeadline->format('Y-m-d'),
                'description' => 'Workplace pension contributions due by 22nd of following month',
                'type' => 'pension',
                'days_until' => $now->diffInDays($payeDeadline, false),
            ],
        ];
    }

    /**
     * Get label for business type.
     */
    private function getBusinessTypeLabel(string $type): string
    {
        return match ($type) {
            'sole_trader' => 'Sole Trader',
            'partnership' => 'Partnership',
            'limited_company' => 'Limited Company',
            'llp' => 'LLP',
            'other' => 'Other',
            default => ucfirst(str_replace('_', ' ', $type)),
        };
    }

    /**
     * Get current UK tax year (6 Apr to 5 Apr).
     */
    private function getCurrentTaxYear(): string
    {
        return $this->taxConfig->getTaxYear();
    }

    /**
     * Get next UK tax year.
     */
    private function getNextTaxYear(): string
    {
        $currentYear = $this->getCurrentTaxYear();
        // Parse "2025/26" format and advance by one year
        $startYear = (int) substr($currentYear, 0, 4);

        return ($startYear + 1).'/'.substr((string) ($startYear + 2), 2);
    }
}
