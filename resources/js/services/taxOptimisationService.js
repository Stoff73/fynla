import api from './api';

export default {
  /**
   * Get comprehensive tax optimisation analysis (allowance usage + strategies).
   */
  getAnalysis() {
    return api.get('/tax/optimisation-analysis');
  },

  /**
   * Get prioritised tax-saving strategies only.
   */
  getStrategies() {
    return api.get('/tax/strategies');
  },
};
