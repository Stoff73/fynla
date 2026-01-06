<?php

declare(strict_types=1);

namespace App\Constants;

use App\Services\TaxConfigService;

/**
 * Validation limits for financial data.
 *
 * These constants define the maximum values used in validation rules.
 * For tax-related limits, use TaxConfigService methods instead of hardcoding.
 *
 * @see App\Services\TaxConfigService for dynamic tax limits
 */
class ValidationLimits
{
    // Currency limits
    public const MAX_CURRENCY_VALUE = 999999999.99;

    public const MIN_CURRENCY_VALUE = 0;

    // Property limits
    public const MAX_PROPERTY_VALUE = 50000000;

    public const MIN_PROPERTY_VALUE = 0;

    public const MAX_MORTGAGE_VALUE = 10000000;

    // Investment limits
    public const MAX_INVESTMENT_VALUE = 100000000;

    public const MAX_HOLDING_VALUE = 50000000;

    public const MAX_HOLDING_UNITS = 999999999;

    // Percentage limits
    public const MAX_PERCENTAGE = 100;

    public const MIN_PERCENTAGE = 0;

    public const MAX_INTEREST_RATE = 50; // 50% max interest rate

    public const MAX_GROWTH_RATE = 25;   // 25% max growth rate

    // Age limits
    public const MIN_AGE = 0;

    public const MAX_AGE = 125;

    public const MIN_RETIREMENT_AGE = 50;

    public const MAX_RETIREMENT_AGE = 100;

    // Year limits
    public const MIN_POLICY_TERM_YEARS = 1;

    public const MAX_POLICY_TERM_YEARS = 50;

    public const MAX_YEARS_TO_RETIREMENT = 60;

    // String lengths
    public const MAX_NAME_LENGTH = 255;

    public const MAX_DESCRIPTION_LENGTH = 1000;

    public const MAX_NOTES_LENGTH = 5000;

    public const MAX_PROVIDER_NAME_LENGTH = 255;

    // Count limits
    public const MAX_DEPENDENTS = 20;

    public const MAX_POLICIES_PER_TYPE = 50;

    public const MAX_ACCOUNTS = 100;

    public const MAX_HOLDINGS_PER_ACCOUNT = 500;

    /**
     * Get ISA subscription limit from TaxConfigService.
     * Falls back to default if service unavailable.
     */
    public static function getISALimit(?TaxConfigService $taxConfig = null): float
    {
        if ($taxConfig) {
            try {
                $isaConfig = $taxConfig->getISAAllowances();

                return (float) ($isaConfig['annual_allowance'] ?? 20000);
            } catch (\Exception $e) {
                // Fall through to default
            }
        }

        return 20000.0;
    }

    /**
     * Get pension annual allowance from TaxConfigService.
     * Falls back to default if service unavailable.
     */
    public static function getPensionAnnualAllowance(?TaxConfigService $taxConfig = null): float
    {
        if ($taxConfig) {
            try {
                $pensionConfig = $taxConfig->getPensionLimits();

                return (float) ($pensionConfig['annual_allowance'] ?? 60000);
            } catch (\Exception $e) {
                // Fall through to default
            }
        }

        return 60000.0;
    }

    /**
     * Get validation rules array for common currency fields.
     */
    public static function currencyRules(bool $required = false): string
    {
        $rules = ['numeric', 'min:'.self::MIN_CURRENCY_VALUE, 'max:'.self::MAX_CURRENCY_VALUE];
        if ($required) {
            array_unshift($rules, 'required');
        } else {
            array_unshift($rules, 'nullable');
        }

        return implode('|', $rules);
    }

    /**
     * Get validation rules array for percentage fields.
     */
    public static function percentageRules(bool $required = false): string
    {
        $rules = ['numeric', 'min:'.self::MIN_PERCENTAGE, 'max:'.self::MAX_PERCENTAGE];
        if ($required) {
            array_unshift($rules, 'required');
        } else {
            array_unshift($rules, 'nullable');
        }

        return implode('|', $rules);
    }
}
