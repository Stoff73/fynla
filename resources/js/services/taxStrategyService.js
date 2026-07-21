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
  // WP-2 — completion write shared with the dashboard actions (stable
  // aggregator id: 'tax_' + strategy type).
  markRecommendationDone: (recommendationId, recommendationText) => api.post(
    `/recommendations/${recommendationId}/mark-done`,
    { module: 'tax', recommendation_text: recommendationText },
  ),
};
