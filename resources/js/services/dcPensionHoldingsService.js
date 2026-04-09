import api from './api';

/**
 * DC Pension Holdings Service
 * Handles API calls for managing holdings within DC pension pots
 */
const dcPensionHoldingsService = {
  /**
   * Create a new holding for a DC pension
   * @param {number} dcPensionId - DC Pension ID
   * @param {object} holdingData - Holding data
   * @returns {Promise} API response
   */
  async createHolding(dcPensionId, holdingData) {
    const response = await api.post(`/retirement/pensions/dc/${dcPensionId}/holdings`, holdingData);
    return response.data;
  },

  /**
   * Update a DC pension holding
   * @param {number} dcPensionId - DC Pension ID
   * @param {number} holdingId - Holding ID
   * @param {object} holdingData - Updated holding data
   * @returns {Promise} API response
   */
  async updateHolding(dcPensionId, holdingId, holdingData) {
    const response = await api.put(`/retirement/pensions/dc/${dcPensionId}/holdings/${holdingId}`, holdingData);
    return response.data;
  },

  /**
   * Delete a DC pension holding
   * @param {number} dcPensionId - DC Pension ID
   * @param {number} holdingId - Holding ID
   * @returns {Promise} API response
   */
  async deleteHolding(dcPensionId, holdingId) {
    const response = await api.delete(`/retirement/pensions/dc/${dcPensionId}/holdings/${holdingId}`);
    return response.data;
  },

  /**
   * Bulk update holdings (for rebalancing)
   * @param {number} dcPensionId - DC Pension ID
   * @param {array} holdings - Array of holdings with updated values
   * @returns {Promise} API response
   */
  async bulkUpdateHoldings(dcPensionId, holdings) {
    const response = await api.post(`/retirement/pensions/dc/${dcPensionId}/holdings/bulk-update`, {
      holdings,
    });
    return response.data;
  },

  /**
   * Get portfolio analysis for all DC pensions
   * @returns {Promise} API response with portfolio analysis data
   */
  async getPortfolioAnalysis() {
    const response = await api.get('/retirement/portfolio-analysis');
    return response.data;
  },

  /**
   * Get portfolio analysis for a specific DC pension
   * @param {number} dcPensionId - DC Pension ID
   * @returns {Promise} API response with portfolio analysis data
   */
  async getPensionPortfolioAnalysis(dcPensionId) {
    const response = await api.get(`/retirement/portfolio-analysis/${dcPensionId}`);
    return response.data;
  },
};

export default dcPensionHoldingsService;
