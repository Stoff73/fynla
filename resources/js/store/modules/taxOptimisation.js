import taxOptimisationService from '@/services/taxOptimisationService';

const state = {
  optimisationAnalysis: null,
  strategies: [],
  loadingStrategies: false,
  error: null,
};

const mutations = {
  setOptimisationAnalysis(state, analysis) {
    state.optimisationAnalysis = analysis;
  },
  setStrategies(state, strategies) {
    state.strategies = strategies;
  },
  setLoadingStrategies(state, loading) {
    state.loadingStrategies = loading;
  },
  setError(state, error) {
    state.error = error;
  },
};

const actions = {
  async fetchOptimisationAnalysis({ commit }) {
    commit('setLoadingStrategies', true);
    commit('setError', null);
    try {
      const response = await taxOptimisationService.getAnalysis();
      const data = response.data?.data || response.data || {};
      commit('setOptimisationAnalysis', data);
      commit('setStrategies', data.strategies || []);
    } catch (error) {
      commit('setError', error.message || 'Failed to load tax optimisation analysis');
      throw error;
    } finally {
      commit('setLoadingStrategies', false);
    }
  },

  async fetchStrategies({ commit }) {
    commit('setLoadingStrategies', true);
    commit('setError', null);
    try {
      const response = await taxOptimisationService.getStrategies();
      const data = response.data?.data || response.data || {};
      commit('setStrategies', data.strategies || []);
    } catch (error) {
      commit('setError', error.message || 'Failed to load tax strategies');
      throw error;
    } finally {
      commit('setLoadingStrategies', false);
    }
  },
};

const getters = {
  optimisationAnalysis: (state) => state.optimisationAnalysis,
  strategies: (state) => state.strategies,
  totalEstimatedSaving: (state) => {
    if (state.optimisationAnalysis?.total_estimated_saving) {
      return state.optimisationAnalysis.total_estimated_saving;
    }
    return state.strategies.reduce(
      (sum, s) => sum + (s.estimated_annual_saving || 0),
      0
    );
  },
};

export default {
  namespaced: true,
  state,
  mutations,
  actions,
  getters,
};
