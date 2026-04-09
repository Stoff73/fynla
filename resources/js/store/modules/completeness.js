import lifeStageService from '@/services/lifeStageService';

/**
 * Completeness store — single source of truth for per-module information completeness.
 *
 * Two tiers per module:
 *   - has_data (display level): enough to show a dashboard card
 *   - can_advise (advice level): enough for Agent regulated advice
 *
 * Driven by PrerequisiteGateService + DataReadinessService checks on the backend.
 * Frontend components should use these getters instead of ad-hoc data checks.
 */

const state = {
  modules: {},
  lifeStage: null,
  loading: false,
  error: null,
  lastFetched: null,
};

const getters = {
  /**
   * Display level: does the user have ANY data for this module?
   * Use for dashboard card visibility.
   */
  hasModuleData: (state) => (module) => {
    return state.modules[module]?.has_data ?? false;
  },

  /**
   * Advice level: does the user have ENOUGH data for the Agent to advise?
   * Use for enabling/disabling analysis features.
   */
  canAdviseModule: (state) => (module) => {
    return state.modules[module]?.can_advise ?? false;
  },

  /**
   * What specific fields are missing for advice level?
   * Use for "complete your profile" guidance.
   */
  moduleMissing: (state) => (module) => {
    return state.modules[module]?.missing ?? [];
  },

  /**
   * All module statuses.
   */
  allModules: (state) => state.modules,

  /**
   * Field-level completeness percentage for a module (from DataReadiness).
   */
  moduleCompleteness: (state) => (module) => {
    return state.modules[module]?.completeness_percent ?? 0;
  },

  /**
   * Overall data completeness across all modules (average of DataReadiness percentages).
   */
  overallCompleteness: (state) => {
    const modules = Object.values(state.modules).filter(m => m.completeness_percent !== null);
    if (modules.length === 0) return 0;
    const total = modules.reduce((sum, m) => sum + (m.completeness_percent ?? 0), 0);
    return Math.round(total / modules.length);
  },

  isLoading: (state) => state.loading,
};

const mutations = {
  setModules(state, modules) {
    state.modules = modules;
  },
  setLifeStage(state, stage) {
    state.lifeStage = stage;
  },
  setLoading(state, loading) {
    state.loading = loading;
  },
  setError(state, error) {
    state.error = error;
  },
  setLastFetched(state, timestamp) {
    state.lastFetched = timestamp;
  },
};

const actions = {
  /**
   * Fetch completeness data from the backend.
   * This calls PrerequisiteGateService checks on the server.
   */
  async fetchCompleteness({ commit, state }) {
    // Debounce: don't re-fetch if fetched within last 5 seconds
    if (state.lastFetched && Date.now() - state.lastFetched < 5000) {
      return;
    }

    commit('setLoading', true);
    try {
      const response = await lifeStageService.getCompleteness();
      if (response && response.success) {
        commit('setModules', response.data.modules || {});
        commit('setLifeStage', response.data.life_stage || null);
        commit('setLastFetched', Date.now());
      }
    } catch (error) {
      commit('setError', error.message);
    } finally {
      commit('setLoading', false);
    }
  },

  /**
   * Force refresh — bypasses debounce.
   * Call after data-saving actions (profile update, account creation, etc.).
   */
  async refreshCompleteness({ commit }) {
    commit('setLastFetched', null);
    commit('setLoading', true);
    try {
      const response = await lifeStageService.getCompleteness();
      if (response && response.success) {
        commit('setModules', response.data.modules || {});
        commit('setLifeStage', response.data.life_stage || null);
        commit('setLastFetched', Date.now());
      }
    } catch (error) {
      commit('setError', error.message);
    } finally {
      commit('setLoading', false);
    }
  },
};

export default {
  namespaced: true,
  state,
  getters,
  mutations,
  actions,
};
