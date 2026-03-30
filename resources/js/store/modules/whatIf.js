import whatIfService from '@/services/whatIfService';
import logger from '@/utils/logger';

const state = {
  scenarios: [],
  activeScenarioId: null,
  comparisonData: null,
  loading: false,
  error: null,
};

const getters = {
  activeScenario: (state) => {
    if (!state.activeScenarioId) return null;
    return state.scenarios.find(s => s.id === state.activeScenarioId) || null;
  },
};

const mutations = {
  SET_SCENARIOS(state, scenarios) {
    state.scenarios = scenarios;
  },
  SET_ACTIVE_SCENARIO_ID(state, id) {
    state.activeScenarioId = id;
  },
  SET_COMPARISON_DATA(state, data) {
    state.comparisonData = data;
  },
  SET_LOADING(state, loading) {
    state.loading = loading;
  },
  SET_ERROR(state, error) {
    state.error = error;
  },
  REMOVE_SCENARIO(state, id) {
    state.scenarios = state.scenarios.filter(s => s.id !== id);
    if (state.activeScenarioId === id) {
      state.activeScenarioId = null;
      state.comparisonData = null;
    }
  },
};

const actions = {
  async fetchScenarios({ commit }) {
    commit('SET_LOADING', true);
    commit('SET_ERROR', null);
    try {
      const response = await whatIfService.getScenarios();
      commit('SET_SCENARIOS', response.data?.scenarios || response.data || []);
    } catch (error) {
      commit('SET_ERROR', error.message);
      throw error;
    } finally {
      commit('SET_LOADING', false);
    }
  },

  async fetchScenarioComparison({ commit }, scenarioId) {
    commit('SET_ACTIVE_SCENARIO_ID', scenarioId);
    commit('SET_LOADING', true);
    commit('SET_ERROR', null);
    try {
      const response = await whatIfService.getScenarioComparison(scenarioId);
      // API returns { success, data: { scenario, comparison } }
      // Service unwraps axios .data, so response = { success, data: { scenario, comparison } }
      const comparison = response?.data?.comparison || response?.comparison || response?.data || response;
      logger.debug('WhatIf', 'Comparison data:', JSON.stringify(comparison).substring(0, 200));
      commit('SET_COMPARISON_DATA', comparison);
    } catch (error) {
      logger.error('WhatIf', 'fetchScenarioComparison error:', error);
      commit('SET_ERROR', error.message);
    } finally {
      commit('SET_LOADING', false);
    }
  },

  async deleteScenario({ commit }, scenarioId) {
    try {
      await whatIfService.deleteScenario(scenarioId);
      commit('REMOVE_SCENARIO', scenarioId);
    } catch (error) {
      commit('SET_ERROR', error.message);
      throw error;
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
