<?php

declare(strict_types=1);

namespace App\Traits;

/**
 * Trait for formatting currency values.
 *
 * Use this trait in any service or class that needs to format monetary values.
 * This provides a centralized, consistent currency formatting implementation.
 */
trait FormatsCurrency
{
    /**
     * Format currency value to GBP without pence.
     *
     * @param  float  $amount  The amount to format
     * @return string Formatted string (e.g., "£1,234")
     */
    protected function formatCurrency(float $amount): string
    {
        return '£'.number_format($amount, 0);
    }

    /**
     * Format currency value to GBP with pence.
     *
     * @param  float  $amount  The amount to format
     * @return string Formatted string (e.g., "£1,234.56")
     */
    protected function formatCurrencyWithPence(float $amount): string
    {
        return '£'.number_format($amount, 2);
    }

    /**
     * Format currency value to GBP with custom decimal places.
     *
     * @param  float  $amount  The amount to format
     * @param  int  $decimals  Number of decimal places
     * @return string Formatted string
     */
    protected function formatCurrencyPrecise(float $amount, int $decimals): string
    {
        return '£'.number_format($amount, $decimals);
    }

    /**
     * Format currency in compact notation for large values.
     *
     * @param  float  $amount  The amount to format
     * @return string Formatted string (e.g., "£1.2M", "£500K")
     */
    protected function formatCurrencyCompact(float $amount): string
    {
        if (abs($amount) >= 1000000) {
            return '£'.number_format($amount / 1000000, 1).'M';
        }
        if (abs($amount) >= 1000) {
            return '£'.number_format($amount / 1000, 0).'K';
        }

        return '£'.number_format($amount, 0);
    }

    /**
     * Format percentage value.
     *
     * @param  float  $value  The percentage value (0.05 = 5%)
     * @param  int  $decimals  Number of decimal places
     * @param  bool  $asDecimal  If true, value is already in decimal form (0.05), if false it's already a percentage (5)
     * @return string Formatted string (e.g., "5.00%")
     */
    protected function formatPercentage(float $value, int $decimals = 2, bool $asDecimal = false): string
    {
        $displayValue = $asDecimal ? $value * 100 : $value;

        return number_format($displayValue, $decimals).'%';
    }
}
