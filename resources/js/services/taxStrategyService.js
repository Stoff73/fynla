import api from './api';

/**
 * SaveTax campaign — Tax Strategy dashboard API wrapper.
 *
 * Endpoints:
 *   GET  /api/tax-strategy            → initial dashboard payload
 *   POST /api/tax-strategy/calculate  → in-memory recalc with overrides
 */
export default {
  fetchDashboard: () => api.get('/tax-strategy'),
  recalculate: (overrides) => api.post('/tax-strategy/calculate', overrides),
};
