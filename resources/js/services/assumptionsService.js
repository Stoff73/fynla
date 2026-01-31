import api from './api';

export default {
  /**
   * Get all assumptions (pensions and investments)
   */
  getAssumptions() {
    return api.get('/settings/assumptions');
  },

  /**
   * Update assumptions for a specific type
   * @param {string} type - 'pensions' or 'investments'
   * @param {Object} data - { inflation_rate, return_rate, compound_periods }
   */
  updateAssumptions(type, data) {
    return api.put(`/settings/assumptions/${type}`, data);
  },

  /**
   * Reset assumptions for a specific type back to defaults
   * @param {string} type - 'pensions' or 'investments'
   */
  resetAssumptions(type) {
    return api.put(`/settings/assumptions/${type}`, { reset: true });
  },
};
