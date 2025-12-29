/**
 * Currency Formatting Mixin
 *
 * Provides currency formatting methods to Vue components.
 * Import and use in components instead of defining local formatCurrency methods.
 *
 * @example
 * import { currencyMixin } from '@/mixins/currencyMixin';
 * export default {
 *   mixins: [currencyMixin],
 *   // Now this.formatCurrency() is available in template and methods
 * }
 */

import {
  formatCurrency,
  formatCurrencyWithPence,
  formatCurrencyCompact,
  parseCurrency,
  formatPercentage,
} from '@/utils/currency';

export const currencyMixin = {
  methods: {
    /**
     * Format a number as GBP currency (no decimals by default)
     * @param {number|null|undefined} value - The amount to format
     * @returns {string} Formatted currency string (e.g., "£1,234")
     */
    formatCurrency(value) {
      return formatCurrency(value);
    },

    /**
     * Format a number as GBP currency with 2 decimal places
     * @param {number|null|undefined} value - The amount to format
     * @returns {string} Formatted currency string (e.g., "£1,234.56")
     */
    formatCurrencyWithPence(value) {
      return formatCurrencyWithPence(value);
    },

    /**
     * Format a number as compact currency (e.g., "£1.2M", "£12.3K")
     * @param {number|null|undefined} value - The amount to format
     * @returns {string} Compact formatted currency string
     */
    formatCurrencyCompact(value) {
      return formatCurrencyCompact(value);
    },

    /**
     * Parse a currency string to a number
     * @param {string} currencyString - The currency string to parse
     * @returns {number} The parsed number
     */
    parseCurrency(currencyString) {
      return parseCurrency(currencyString);
    },

    /**
     * Format a number as a percentage string
     * @param {number|null|undefined} value - The value to format
     * @param {Object} options - Formatting options
     * @returns {string} Formatted percentage string (e.g., "5.00%")
     */
    formatPercentage(value, options = {}) {
      return formatPercentage(value, options);
    },
  },
};

export default currencyMixin;
