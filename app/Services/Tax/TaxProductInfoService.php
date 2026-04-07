<?php

declare(strict_types=1);

namespace App\Services\Tax;

use App\Constants\TaxDefaults;
use App\Models\TaxProductReference;
use App\Services\TaxConfigService;
use Illuminate\Support\Collection;

/**
 * Tax Product Info Service
 *
 * Retrieves and enriches tax reference data for investment and savings products.
 * Combines static reference data with current tax rates from TaxConfigService.
 */
class TaxProductInfoService
{
    public function __construct(
        private readonly TaxConfigService $taxConfig
    ) {}

    /**
     * Get tax information for an investment account type.
     *
     * @param  string  $accountType  Account type (isa, gia, onshore_bond, etc.)
     * @return array Structured tax information with current rates
     */
    public function getInvestmentTaxInfo(string $accountType): array
    {
        // Map account types to tax product types
        $productType = $this->mapInvestmentProductType($accountType);

        $references = TaxProductReference::getForProductType(
            TaxProductReference::CATEGORY_INVESTMENT,
            $productType
        );

        return $this->buildTaxInfoResponse($references, $productType);
    }

    /**
     * Map investment account type to the appropriate tax product type.
     *
     * @param  string  $accountType  Account type from database
     * @return string Tax product type
     */
    private function mapInvestmentProductType(string $accountType): string
    {
        return match ($accountType) {
            'stocks_and_shares_isa', 'isa' => 'isa',
            'general_investment_account', 'gia' => 'gia',
            'onshore_bond', 'investment_bond' => 'onshore_bond',
            'offshore_bond' => 'offshore_bond',
            'vct', 'venture_capital_trust' => 'vct',
            'eis', 'enterprise_investment_scheme' => 'eis',
            'seis', 'seed_enterprise_investment_scheme' => 'eis',  // Use EIS tax rules for SEIS
            'nsi', 'premium_bonds', 'ns_i' => 'nsi',
            default => 'other',
        };
    }

    /**
     * Get tax information for a savings account type.
     *
     * @param  string  $accountType  Account type (easy_access, notice, etc.)
     * @param  bool  $isIsa  Whether the account is an ISA
     * @return array Structured tax information with current rates
     */
    public function getSavingsTaxInfo(string $accountType, bool $isIsa = false): array
    {
        // Map to the correct product type based on ISA status
        $productType = $this->mapSavingsProductType($accountType, $isIsa);

        $references = TaxProductReference::getForProductType(
            TaxProductReference::CATEGORY_SAVINGS,
            $productType
        );

        return $this->buildTaxInfoResponse($references, $productType);
    }

    /**
     * Get tax info summary for quick display (e.g., badges, tooltips).
     *
     * @param  string  $category  Product category
     * @param  string  $productType  Product type
     * @return array Summary with counts by status
     */
    public function getTaxSummary(string $category, string $productType): array
    {
        $summary = TaxProductReference::getTaxStatusSummary($category, $productType);
        $hasTaxFree = TaxProductReference::hasTaxExemptAspects($category, $productType);

        return [
            'product_type' => $productType,
            'is_tax_advantaged' => $hasTaxFree,
            'status_counts' => $summary,
            'primary_status' => $this->determinePrimaryStatus($summary),
        ];
    }

    /**
     * Build the structured tax info response.
     *
     * @param  Collection  $references  Tax reference data
     * @param  string  $productType  Product type
     * @return array Structured response
     */
    private function buildTaxInfoResponse(Collection $references, string $productType): array
    {
        $currentRates = $this->getCurrentRates();

        return [
            'product_type' => $productType,
            'product_type_label' => $this->getProductTypeLabel($productType),
            'tax_year' => $this->taxConfig->getTaxYear(),
            'tax_items' => $references->map(function ($ref) use ($currentRates) {
                return [
                    'aspect' => $ref->tax_aspect,
                    'title' => $ref->title,
                    'summary' => $this->interpolateRates($ref->summary, $currentRates),
                    'status' => $ref->status,
                    'status_icon' => $ref->status_icon,
                ];
            })->toArray(),
            'current_rates' => $currentRates,
        ];
    }

    /**
     * Get current tax rates from TaxConfigService.
     *
     * @return array Current rates for display
     */
    private function getCurrentRates(): array
    {
        $isaAllowances = $this->taxConfig->getISAAllowances();
        $cgtConfig = $this->taxConfig->getCapitalGainsTax();
        $dividendConfig = $this->taxConfig->getDividendTax();
        $incomeTaxConfig = $this->taxConfig->getIncomeTax();

        // Fallback values are 2025/26 UK tax year defaults if TaxConfigService unavailable
        return [
            'isa_allowance' => $isaAllowances['annual_allowance'] ?? TaxDefaults::ISA_ALLOWANCE,
            'junior_isa_allowance' => $isaAllowances['junior_isa']['annual_allowance'] ?? 9000,
            'lifetime_isa_limit' => $isaAllowances['lifetime_isa']['annual_allowance'] ?? 4000,
            'cgt_allowance' => $cgtConfig['annual_exempt_amount'] ?? 3000,
            'dividend_allowance' => $dividendConfig['allowance'] ?? 500,
            'psa_basic' => $this->taxConfig->getPersonalSavingsAllowance('basic'),
            'psa_higher' => $this->taxConfig->getPersonalSavingsAllowance('higher'),
            'personal_allowance' => $incomeTaxConfig['personal_allowance'],
        ];
    }

    /**
     * Interpolate rate values into summary text.
     *
     * @param  string  $summary  Summary text with placeholders
     * @param  array  $rates  Current rates
     * @return string Summary with interpolated values
     */
    private function interpolateRates(string $summary, array $rates): string
    {
        $replacements = [
            '{isa_allowance}' => '£'.number_format($rates['isa_allowance']),
            '{junior_isa_allowance}' => '£'.number_format($rates['junior_isa_allowance']),
            '{lifetime_isa_limit}' => '£'.number_format($rates['lifetime_isa_limit']),
            '{cgt_allowance}' => '£'.number_format($rates['cgt_allowance']),
            '{dividend_allowance}' => '£'.number_format($rates['dividend_allowance']),
            '{psa_basic}' => '£'.number_format($rates['psa_basic']),
            '{psa_higher}' => '£'.number_format($rates['psa_higher']),
            '{personal_allowance}' => '£'.number_format($rates['personal_allowance']),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $summary);
    }

    /**
     * Map savings account type to the appropriate tax product type.
     *
     * @param  string  $accountType  Account type from database
     * @param  bool  $isIsa  Whether it's an ISA
     * @return string Tax product type
     */
    private function mapSavingsProductType(string $accountType, bool $isIsa): string
    {
        // If it's an ISA, return the ISA product type
        if ($isIsa) {
            return match ($accountType) {
                'junior_isa' => 'junior_isa',
                'lifetime_isa', 'lisa' => 'lifetime_isa',
                default => 'cash_isa',
            };
        }

        // Map account types to tax product types
        return match ($accountType) {
            'premium_bonds' => 'premium_bonds',
            'nsi', 'nsi_savings' => 'nsi',
            'notice' => 'notice',
            'fixed', 'fixed_rate' => 'fixed_rate',
            default => 'easy_access',
        };
    }

    /**
     * Get human-readable label for product type.
     *
     * @param  string  $productType  Product type
     * @return string Human-readable label
     */
    private function getProductTypeLabel(string $productType): string
    {
        return match ($productType) {
            'isa' => 'Stocks & Shares ISA',
            'gia' => 'General Investment Account',
            'onshore_bond' => 'Onshore Investment Bond',
            'offshore_bond' => 'Offshore Investment Bond',
            'vct' => 'Venture Capital Trust',
            'eis' => 'Enterprise Investment Scheme',
            'nsi' => 'NS&I',
            'cash_isa' => 'Cash ISA',
            'junior_isa' => 'Junior ISA',
            'lifetime_isa' => 'Lifetime ISA',
            'easy_access' => 'Easy Access Savings',
            'notice' => 'Notice Account',
            'fixed_rate' => 'Fixed Rate Savings',
            'premium_bonds' => 'Premium Bonds',
            default => ucwords(str_replace('_', ' ', $productType)),
        };
    }

    /**
     * Determine the primary tax status for summary display.
     *
     * @param  array  $statusCounts  Count of items by status
     * @return string Primary status
     */
    private function determinePrimaryStatus(array $statusCounts): string
    {
        // If any exempt aspects, it's tax-advantaged
        if (($statusCounts['exempt'] ?? 0) > 0) {
            return 'tax_advantaged';
        }

        // If deferred aspects exist, it's tax-deferred
        if (($statusCounts['deferred'] ?? 0) > 0) {
            return 'tax_deferred';
        }

        // If relief aspects exist, has relief available
        if (($statusCounts['relief'] ?? 0) > 0) {
            return 'relief_available';
        }

        // Otherwise fully taxable
        return 'fully_taxable';
    }
}
