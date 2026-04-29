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

const getters = {
  userAllowances: (s) => s.dashboard?.user_allowances ?? [],
  spouseAllowances: (s) => s.dashboard?.spouse_allowances ?? null,
  assetShiftingSuggestions: (s) => s.dashboard?.asset_shifting_suggestions ?? [],
  crossSpouseSuggestions: (s) => s.dashboard?.cross_spouse_suggestions ?? [],
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
