/**
 * Tax Information Service
 *
 * Provides API methods for retrieving tax treatment information
 * for investment and savings products.
 */
import api from './api';

const taxInfoService = {
  /**
   * Get tax information for an investment account type.
   *
   * @param {string} accountType - Account type (isa, gia, onshore_bond, etc.)
   * @returns {Promise<Object>} Tax information
   */
  async getInvestmentTaxInfo(accountType) {
    const response = await api.get(`/tax-info/investment/${accountType}`);
    return response.data;
  },

  /**
   * Get tax information for a savings account type.
   *
   * @param {string} accountType - Account type (easy_access, notice, etc.)
   * @param {boolean} isIsa - Whether the account is an ISA
   * @returns {Promise<Object>} Tax information
   */
  async getSavingsTaxInfo(accountType, isIsa = false) {
    const response = await api.get(`/tax-info/savings/${accountType}`, {
      params: { is_isa: isIsa },
    });
    return response.data;
  },

  /**
   * Get tax summary for quick display (badges, tooltips).
   *
   * @param {string} category - Product category ('investment' or 'savings')
   * @param {string} productType - Product type
   * @returns {Promise<Object>} Tax summary
   */
  async getTaxSummary(category, productType) {
    const response = await api.get('/tax-info/summary', {
      params: { category, product_type: productType },
    });
    return response.data;
  },
};

export default taxInfoService;
