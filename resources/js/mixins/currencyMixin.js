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

    /**
     * Format investment account type code to display name
     * @param {string} type - Account type code (e.g., 'isa', 'sipp', 'gia')
     * @returns {string} Human-readable account type name
     */
    formatAccountType(type) {
      const types = {
        'isa': 'ISA',
        'stocks_shares_isa': 'Stocks & Shares ISA',
        'cash_isa': 'Cash ISA',
        'lifetime_isa': 'Lifetime ISA',
        'junior_isa': 'Junior ISA',
        'sipp': 'Self-Invested Personal Pension',
        'gia': 'General Investment Account',
        'pension': 'Pension',
        'workplace_pension': 'Workplace Pension',
        'personal_pension': 'Personal Pension',
        'nsi': 'NS&I',
        'premium_bonds': 'Premium Bonds',
        'onshore_bond': 'Onshore Bond',
        'offshore_bond': 'Offshore Bond',
        'vct': 'Venture Capital Trust',
        'eis': 'Enterprise Investment Scheme',
        'easy_access': 'Easy Access',
        'notice': 'Notice Account',
        'fixed_term': 'Fixed Term',
        'regular_saver': 'Regular Saver',
        'current_account': 'Current Account',
        'other': 'Other',
      };
      return types[type] || type?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) || 'Unknown';
    },

    /**
     * Format ownership type code to display name
     * @param {string} type - Ownership type code (e.g., 'individual', 'joint')
     * @returns {string} Human-readable ownership type name
     */
    formatOwnershipType(type) {
      const types = {
        'individual': 'Individual',
        'joint': 'Joint',
        'tenants_in_common': 'Tenants in Common',
        'trust': 'Trust',
        'company': 'Company',
        'pension_scheme': 'Pension Scheme',
      };
      return types[type] || type?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) || 'Unknown';
    },

    /**
     * Format savings account type code to display name
     * @param {string} type - Savings account type code
     * @returns {string} Human-readable savings account type name
     */
    formatSavingsAccountType(type) {
      const types = {
        'easy_access': 'Easy Access',
        'notice': 'Notice Account',
        'fixed_term': 'Fixed Term',
        'regular_saver': 'Regular Saver',
        'cash_isa': 'Cash ISA',
        'lifetime_isa': 'Lifetime ISA',
        'junior_isa': 'Junior ISA',
        'help_to_buy_isa': 'Help to Buy ISA',
        'premium_bonds': 'Premium Bonds',
        'current_account': 'Current Account',
        'other': 'Other',
      };
      return types[type] || type?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) || 'Unknown';
    },

    /**
     * Format property type code to display name
     * @param {string} type - Property type code
     * @returns {string} Human-readable property type name
     */
    formatPropertyType(type) {
      const types = {
        'main_residence': 'Main Residence',
        'secondary_residence': 'Secondary Residence',
        'buy_to_let': 'Buy to Let',
        'holiday_let': 'Holiday Let',
        'commercial': 'Commercial',
        'land': 'Land',
        'other': 'Other',
      };
      return types[type] || type?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) || 'Unknown';
    },

    /**
     * Format mortgage type code to display name
     * @param {string} type - Mortgage type code
     * @returns {string} Human-readable mortgage type name
     */
    formatMortgageType(type) {
      const types = {
        'repayment': 'Repayment',
        'interest_only': 'Interest Only',
        'mixed': 'Mixed',
        'offset': 'Offset',
        'tracker': 'Tracker',
        'fixed': 'Fixed Rate',
        'variable': 'Variable Rate',
        'discount': 'Discount',
      };
      return types[type] || type?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) || 'Unknown';
    },

    /**
     * Format a plain number with commas (no currency symbol)
     * @param {number|null|undefined} value - The number to format
     * @returns {string} Formatted number string (e.g., "1,234")
     */
    formatNumber(value) {
      if (value == null || isNaN(value)) return '0';
      return Number(value).toLocaleString('en-GB');
    },

    /**
     * Format a liability value with negative sign prefix
     * @param {number|null|undefined} value - The liability amount to format
     * @returns {string} Formatted liability string (e.g., "-£1,234")
     */
    formatLiability(value) {
      const num = parseFloat(value) || 0;
      if (num === 0) return this.formatCurrency(0);
      return `-${this.formatCurrency(num)}`;
    },
  },
};

export default currencyMixin;
