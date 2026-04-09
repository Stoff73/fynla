import api from '../../services/api';

import logger from '@/utils/logger';
const state = {
  recommendations: [],
  topRecommendations: [],
  loading: false,
  error: null,
};

const mutations = {
  SET_RECOMMENDATIONS(state, recommendations) {
    state.recommendations = recommendations;
  },

  SET_TOP_RECOMMENDATIONS(state, recommendations) {
    state.topRecommendations = recommendations;
  },

  SET_LOADING(state, loading) {
    state.loading = loading;
  },

  SET_ERROR(state, error) {
    state.error = error;
  },
};

const actions = {
  async fetchRecommendations({ commit }, params = {}) {
    commit('SET_LOADING', true);
    commit('SET_ERROR', null);

    try {
      const response = await api.get('/recommendations', { params });
      commit('SET_RECOMMENDATIONS', response.data.data);
    } catch (error) {
      commit('SET_ERROR', error.response?.data?.message || 'Failed to fetch recommendations');
    } finally {
      commit('SET_LOADING', false);
    }
  },

  async fetchTopRecommendations({ commit }, limit = 5) {
    try {
      const response = await api.get('/recommendations/top', {
        params: { limit },
      });
      commit('SET_TOP_RECOMMENDATIONS', response.data.data);
    } catch (error) {
      logger.error('Failed to fetch top recommendations:', error);
    }
  },

};

const getters = {
  highPriorityRecommendations(state) {
    return state.recommendations.filter(r => r.impact === 'high');
  },

  recommendationsByModule: (state) => (module) => {
    return state.recommendations.filter(r => r.module === module);
  },

  pendingRecommendations(state) {
    return state.recommendations.filter(r => r.status === 'pending');
  },

  inProgressRecommendations(state) {
    return state.recommendations.filter(r => r.status === 'in_progress');
  },
};

export default {
  namespaced: true,
  state,
  mutations,
  actions,
  getters,
};
