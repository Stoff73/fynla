import api from './api';

/**
 * Plans Service
 * Handles API calls for comprehensive cross-module plans
 */
const plansService = {
  /**
   * Generate a plan for the given type (investment, protection, retirement, estate).
   * @param {string} type - Plan type
   * @returns {Promise} API response with plan data
   */
  async generatePlan(type) {
    const response = await api.get(`/plans/${type}`);
    return response.data;
  },

  /**
   * Generate a goal-specific plan.
   * @param {number} goalId - Goal ID
   * @returns {Promise} API response with plan data
   */
  async generateGoalPlan(goalId) {
    const response = await api.get(`/plans/goal/${goalId}`);
    return response.data;
  },

  /**
   * Recalculate what-if scenario with specific enabled actions.
   * @param {string} type - Plan type
   * @param {string[]} enabledActionIds - Array of enabled action IDs
   * @returns {Promise} API response with recalculated plan
   */
  async recalculateScenario(type, enabledActionIds) {
    const response = await api.post(`/plans/${type}/recalculate`, {
      enabled_action_ids: enabledActionIds,
    });
    return response.data;
  },

  /**
   * Recalculate what-if scenario for a goal plan.
   * @param {number} goalId - Goal ID
   * @param {string[]} enabledActionIds - Array of enabled action IDs
   * @returns {Promise} API response with recalculated plan
   */
  async recalculateGoalScenario(goalId, enabledActionIds) {
    const response = await api.post(`/plans/goal/${goalId}/recalculate`, {
      enabled_action_ids: enabledActionIds,
    });
    return response.data;
  },

  /**
   * Get dashboard plan readiness statuses.
   * @returns {Promise} API response with status per plan type
   */
  async getDashboardStatuses() {
    const response = await api.get('/plans/statuses');
    return response.data;
  },

  /**
   * Clear plan cache for a given type.
   * @param {string} type - Plan type
   * @returns {Promise} API response
   */
  async clearPlanCache(type) {
    const response = await api.delete(`/plans/${type}/clear-cache`);
    return response.data;
  },

  /**
   * Update the funding source for a plan action.
   * @param {string} type - Plan type (e.g. 'retirement')
   * @param {Object} payload - { action_category, target_account_id, funding_source_type, funding_source_id }
   * @returns {Promise} API response
   */
  async updateFundingSource(type, payload) {
    const response = await api.put(`/plans/${type}/funding-source`, payload);
    return response.data;
  },
};

export default plansService;
