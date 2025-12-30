import api from './api';

/**
 * Rebalancing API service
 * Handles portfolio rebalancing calculations with CGT optimization
 */
const rebalancingService = {
  /**
   * Calculate rebalancing actions from target weights
   *
   * @param {Object} data - Rebalancing parameters
   * @param {Array<number>} data.target_weights - Target allocation weights (must sum to 1.0)
   * @param {Array<number>} data.account_ids - Optional account IDs to filter
   * @param {number} data.min_trade_size - Minimum trade size in £ (default: 100)
   * @param {boolean} data.optimise_for_cgt - Enable CGT optimization (default: false)
   * @param {number} data.cgt_allowance - Annual CGT allowance (default: 12300)
   * @param {number} data.tax_rate - CGT tax rate (default: 0.20)
   * @param {number} data.loss_carryforward - Losses carried forward from previous years
   * @returns {Promise<Object>} Rebalancing result with actions and CGT analysis
   */
  async calculateRebalancing(data) {
    const response = await api.post('/investment/rebalancing/calculate', data);
    return response.data;
  },

  /**
   * Calculate rebalancing from optimization result
   *
   * @param {Object} data - Optimization result
   * @param {Array<number>} data.weights - Optimal portfolio weights from optimization
   * @param {Array<string>} data.labels - Security labels
   * @param {Array<number>} data.account_ids - Optional account IDs
   * @param {boolean} data.optimise_for_cgt - Enable CGT optimization
   * @param {number} data.cgt_allowance - Annual CGT allowance
   * @param {number} data.tax_rate - CGT tax rate
   * @returns {Promise<Object>} Rebalancing result
   */
  async calculateFromOptimization(data) {
    const response = await api.post('/investment/rebalancing/from-optimization', data);
    return response.data;
  },

  /**
   * Compare CGT liability between two rebalancing strategies
   *
   * @param {Object} data - Comparison parameters
   * @param {Array<number>} data.strategy_1_weights - First strategy weights
   * @param {Array<number>} data.strategy_2_weights - Second strategy weights
   * @param {Array<number>} data.account_ids - Optional account IDs
   * @param {number} data.cgt_allowance - Annual CGT allowance
   * @param {number} data.tax_rate - CGT tax rate
   * @returns {Promise<Object>} Comparison result with CGT difference
   */
  async compareCGTStrategies(data) {
    const response = await api.post('/investment/rebalancing/compare-cgt', data);
    return response.data;
  },

  /**
   * Calculate rebalancing constrained to CGT allowance
   *
   * @param {Object} data - Rebalancing parameters
   * @param {Array<number>} data.target_weights - Target weights
   * @param {Array<number>} data.account_ids - Optional account IDs
   * @param {number} data.cgt_allowance - Annual CGT allowance
   * @param {number} data.tax_rate - CGT tax rate
   * @returns {Promise<Object>} Modified rebalancing within CGT allowance
   */
  async rebalanceWithinCGTAllowance(data) {
    const response = await api.post('/investment/rebalancing/within-cgt-allowance', data);
    return response.data;
  },

  /**
   * Get user's rebalancing actions
   *
   * @param {Object} params - Query parameters
   * @param {string} params.status - Filter by status (pending, executed, cancelled, expired)
   * @param {string} params.action_type - Filter by action type (buy, sell)
   * @returns {Promise<Object>} List of rebalancing actions
   */
  async getRebalancingActions(params = {}) {
    const response = await api.get('/investment/rebalancing/actions', { params });
    return response.data;
  },

  /**
   * Save rebalancing actions to database
   *
   * @param {Array<Object>} actions - Array of rebalancing actions
   * @returns {Promise<Object>} Saved actions
   */
  async saveRebalancingActions(actions) {
    const response = await api.post('/investment/rebalancing/save', { actions });
    return response.data;
  },

  /**
   * Update rebalancing action status
   *
   * @param {number} id - Action ID
   * @param {Object} data - Update data
   * @param {string} data.status - New status
   * @param {string} data.executed_at - Execution timestamp
   * @param {number} data.executed_price - Actual execution price
   * @param {number} data.executed_shares - Actual shares traded
   * @param {string} data.notes - Notes
   * @returns {Promise<Object>} Updated action
   */
  async updateRebalancingAction(id, data) {
    const response = await api.put(`/investment/rebalancing/actions/${id}`, data);
    return response.data;
  },

  /**
   * Delete rebalancing action
   *
   * @param {number} id - Action ID
   * @returns {Promise<Object>} Success response
   */
  async deleteRebalancingAction(id) {
    const response = await api.delete(`/investment/rebalancing/actions/${id}`);
    return response.data;
  },

  /**
   * Get rebalancing analysis for a specific account
   *
   * @param {number} accountId - Investment account ID
   * @returns {Promise<Object>} Rebalancing analysis with drift, actions, and CGT
   */
  async getAccountRebalancing(accountId) {
    const response = await api.get(`/investment/accounts/${accountId}/rebalancing`);
    return response.data;
  },

  /**
   * Update rebalancing threshold for an account
   *
   * @param {number} accountId - Investment account ID
   * @param {number} thresholdPercent - New threshold percentage (1-50)
   * @returns {Promise<Object>} Updated threshold data
   */
  async updateRebalancingThreshold(accountId, thresholdPercent) {
    const response = await api.patch(`/investment/accounts/${accountId}/rebalancing-threshold`, {
      threshold_percent: thresholdPercent,
    });
    return response.data;
  },
};

export default rebalancingService;
