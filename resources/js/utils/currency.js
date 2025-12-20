/**
 * Currency Formatting Utilities
 *
 * Centralized currency formatting functions for the FPS application.
 * Uses British locale (en-GB) and GBP currency formatting.
 *
 * @module utils/currency
 */

/**
 * Format a number as GBP currency
 *
 * @param {number|null|undefined} amount - The amount to format
 * @param {Object} options - Formatting options
 * @param {number} options.minimumFractionDigits - Minimum decimal places (default: 0)
 * @param {number} options.maximumFractionDigits - Maximum decimal places (default: 0)
 * @returns {string} Formatted currency string (e.g., "£1,234")
 *
 * @example
 * formatCurrency(1234.56)        // "£1,235" (rounds)
 * formatCurrency(1234.56, { maximumFractionDigits: 2 })  // "£1,234.56"
 * formatCurrency(0)              // "£0"
 * formatCurrency(null)           // "£0"
 * formatCurrency(undefined)      // "£0"
 */
export function formatCurrency(amount, options = {}) {
  const {
    minimumFractionDigits = 0,
    maximumFractionDigits = 0,
  } = options;

  return new Intl.NumberFormat('en-GB', {
    style: 'currency',
    currency: 'GBP',
    minimumFractionDigits,
    maximumFractionDigits,
  }).format(amount || 0);
}

/**
 * Format a number as GBP currency with 2 decimal places
 *
 * @param {number|null|undefined} amount - The amount to format
 * @returns {string} Formatted currency string (e.g., "£1,234.56")
 *
 * @example
 * formatCurrencyWithPence(1234.56)    // "£1,234.56"
 * formatCurrencyWithPence(1234)       // "£1,234.00"
 */
export function formatCurrencyWithPence(amount) {
  return formatCurrency(amount, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

/**
 * Format a number as a compact currency string (e.g., "£1.2M")
 *
 * @param {number|null|undefined} amount - The amount to format
 * @returns {string} Compact formatted currency string
 *
 * @example
 * formatCurrencyCompact(1234567)     // "£1.2M"
 * formatCurrencyCompact(12345)       // "£12.3K"
 * formatCurrencyCompact(123)         // "£123"
 */
export function formatCurrencyCompact(amount) {
  if (!amount) return '£0';

  const absAmount = Math.abs(amount);

  if (absAmount >= 1000000) {
    return `£${(amount / 1000000).toFixed(1)}M`;
  }

  if (absAmount >= 1000) {
    return `£${(amount / 1000).toFixed(1)}K`;
  }

  return formatCurrency(amount);
}

/**
 * Parse a currency string to a number
 *
 * @param {string} currencyString - The currency string to parse (e.g., "£1,234.56")
 * @returns {number} The parsed number
 *
 * @example
 * parseCurrency("£1,234.56")    // 1234.56
 * parseCurrency("£1,234")       // 1234
 * parseCurrency("1234")         // 1234
 */
export function parseCurrency(currencyString) {
  if (typeof currencyString === 'number') {
    return currencyString;
  }

  if (!currencyString) {
    return 0;
  }

  // Remove currency symbol, commas, and spaces
  const cleaned = currencyString.toString().replace(/[£,\s]/g, '');
  const parsed = parseFloat(cleaned);

  return isNaN(parsed) ? 0 : parsed;
}

/**
 * Format a number as a percentage string
 *
 * @param {number|null|undefined} value - The value to format
 * @param {Object} options - Formatting options
 * @param {boolean} options.isDecimal - Whether value is in decimal format (0.05 vs 5). Default: false
 * @param {number} options.decimals - Number of decimal places (default: 2)
 * @returns {string} Formatted percentage string (e.g., "5.00%")
 *
 * @example
 * formatPercentage(5)                    // "5.00%"
 * formatPercentage(0.05, { isDecimal: true })  // "5.00%"
 * formatPercentage(5.5, { decimals: 1 })       // "5.5%"
 */
export function formatPercentage(value, options = {}) {
  const { isDecimal = false, decimals = 2 } = options;

  if (value === null || value === undefined || value === '') {
    return '0%';
  }

  const numValue = typeof value === 'string' ? parseFloat(value) : value;

  if (isNaN(numValue)) {
    return '0%';
  }

  const percentValue = isDecimal ? numValue * 100 : numValue;
  return `${percentValue.toFixed(decimals)}%`;
}
