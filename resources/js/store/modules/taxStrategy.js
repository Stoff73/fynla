import taxStrategyService from '@/services/taxStrategyService';

/**
 * SaveTax campaign — Tax Strategy dashboard Vuex module.
 *
 * State:
 *   dashboard      — full payload from GET /api/tax-strategy
 *   overrides      — current slider/toggle state (sent on POST /calculate)
 *   loading        — true during initial fetchDashboard
 *   recalculating  — true during in-flight recalculate (debounced)
 *   error          — last error message
 */
const state = () => ({
  dashboard: null,
  overrides: {},
  loading: false,
  recalculating: false,
  error: null,
});

const mutations = {
  setLoading(state, val) { state.loading = val; },
  setRecalculating(state, val) { state.recalculating = val; },
  setDashboard(state, payload) { state.dashboard = payload; },
  setOverrides(state, overrides) { state.overrides = overrides; },
  setError(state, err) { state.error = err; },
};

const actions = {
  async fetchDashboard({ commit }) {
    commit('setLoading', true);
    commit('setError', null);
    try {
      const { data } = await taxStrategyService.fetchDashboard();
      commit('setDashboard', data.data);
    } catch (err) {
      commit('setError', err.message ?? 'Failed to load tax strategy.');
    } finally {
      commit('setLoading', false);
    }
  },
  async recalculate({ commit, state }, overrides) {
    const merged = { ...state.overrides, ...overrides };
    commit('setOverrides', merged);
    commit('setRecalculating', true);
    try {
      const { data } = await taxStrategyService.recalculate(merged);
      commit('setDashboard', { ...state.dashboard, ...data.data });
    } catch (err) {
      commit('setError', err.message ?? 'Recalculation failed.');
    } finally {
      commit('setRecalculating', false);
    }
  },
};

// Phase 2 (April30Updates) — `recommendations[]` is the canonical source of truth.
// Components consume `recommendations` via the helpers below; the legacy
// `asset_shifting_suggestions` / `cross_spouse_suggestions` arrays were dropped from
// TaxStrategyOutputDTO in the same change.
const CATEGORY_ORDER = ['warning', 'income_band', 'allowance', 'household', 'lifecycle'];

const CATEGORY_LABELS = {
  warning: 'Watch out',
  income_band: 'Reduce your tax band',
  allowance: 'Use your allowances',
  household: 'Coordinate as a household',
  lifecycle: 'Long-term opportunities',
};

const getters = {
  userAllowances: (s) => s.dashboard?.user_allowances ?? [],
  spouseAllowances: (s) => s.dashboard?.spouse_allowances ?? null,
  recommendations: (s) => s.dashboard?.recommendations ?? [],
  /**
   * Recommendations grouped by category, then ordered by the canonical
   * render sequence (warning → income_band → allowance → household → lifecycle).
   * Within each group the backend already orders by priority (high → low).
   * Returns: [{ key, label, items: [...] }] suitable for v-for in the dashboard.
   */
  recommendationsByCategory: (s, g) => {
    const buckets = {};
    for (const rec of g.recommendations) {
      const key = rec.category ?? 'household';
      if (!buckets[key]) buckets[key] = [];
      buckets[key].push(rec);
    }
    return CATEGORY_ORDER
      .filter((key) => buckets[key]?.length)
      .map((key) => ({
        key,
        label: CATEGORY_LABELS[key],
        items: buckets[key],
      }));
  },
  /** Non-household recommendations — rendered by StrategyRecommendationList alongside the slider. */
  individualRecommendations: (s, g) =>
    g.recommendations.filter((rec) => rec.category !== 'household'),
  /** Household recommendations — rendered by HouseholdView. */
  householdRecommendations: (s, g) =>
    g.recommendations.filter((rec) => rec.category === 'household'),
  calculationMode: (s) => s.dashboard?.calculation_mode ?? 'single',
  taxYear: (s) => s.dashboard?.tax_year ?? '',
  isHouseholdMode: (s) => ['dual_earner', 'single_earner_couple'].includes(s.dashboard?.calculation_mode),
};

export default {
  namespaced: true,
  state,
  mutations,
  actions,
  getters,
};
