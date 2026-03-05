import api from '../../services/api';

const state = {
  recommendations: [],
  summary: {
    total_count: 0,
    by_priority: {
      high: 0,
      medium: 0,
      low: 0,
    },
    by_module: {},
    by_timeline: {},
    total_potential_benefit: 0,
    total_estimated_cost: 0,
  },
  topRecommendations: [],
  completedRecommendations: [],
  loading: false,
  error: null,
};

const mutations = {
  SET_RECOMMENDATIONS(state, recommendations) {
    state.recommendations = recommendations;
  },

  SET_SUMMARY(state, summary) {
    state.summary = summary;
  },

  SET_TOP_RECOMMENDATIONS(state, recommendations) {
    state.topRecommendations = recommendations;
  },

  SET_COMPLETED_RECOMMENDATIONS(state, recommendations) {
    state.completedRecommendations = recommendations;
  },

  SET_LOADING(state, loading) {
    state.loading = loading;
  },

  SET_ERROR(state, error) {
    state.error = error;
  },

  UPDATE_RECOMMENDATION_STATUS(state, { recommendationId, status }) {
    const rec = state.recommendations.find(r => r.recommendation_id === recommendationId);
    if (rec) {
      rec.status = status;
    }
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

  async fetchSummary({ commit }) {
    try {
      const response = await api.get('/recommendations/summary');
      commit('SET_SUMMARY', response.data.data);
    } catch (error) {
      console.error('Failed to fetch summary:', error);
    }
  },

  async fetchTopRecommendations({ commit }, limit = 5) {
    try {
      const response = await api.get('/recommendations/top', {
        params: { limit },
      });
      commit('SET_TOP_RECOMMENDATIONS', response.data.data);
    } catch (error) {
      console.error('Failed to fetch top recommendations:', error);
    }
  },

  async fetchCompletedRecommendations({ commit }) {
    try {
      const response = await api.get('/recommendations/completed');
      commit('SET_COMPLETED_RECOMMENDATIONS', response.data.data);
    } catch (error) {
      console.error('Failed to fetch completed recommendations:', error);
    }
  },

  async markRecommendationDone({ commit }, recommendation) {
    try {
      await api.post(`/recommendations/${recommendation.recommendation_id}/mark-done`, {
        module: recommendation.module,
        recommendation_text: recommendation.recommendation_text,
        priority_score: recommendation.priority_score,
        timeline: recommendation.timeline,
      });
      commit('UPDATE_RECOMMENDATION_STATUS', {
        recommendationId: recommendation.recommendation_id,
        status: 'completed',
      });
    } catch (error) {
      throw new Error(error.response?.data?.message || 'Failed to mark recommendation as done');
    }
  },

  async markRecommendationInProgress({ commit }, recommendation) {
    try {
      await api.post(`/recommendations/${recommendation.recommendation_id}/in-progress`, {
        module: recommendation.module,
        recommendation_text: recommendation.recommendation_text,
        priority_score: recommendation.priority_score,
        timeline: recommendation.timeline,
      });
      commit('UPDATE_RECOMMENDATION_STATUS', {
        recommendationId: recommendation.recommendation_id,
        status: 'in_progress',
      });
    } catch (error) {
      throw new Error(error.response?.data?.message || 'Failed to mark recommendation as in progress');
    }
  },

  async dismissRecommendation({ commit }, recommendation) {
    try {
      await api.post(`/recommendations/${recommendation.recommendation_id}/dismiss`, {
        module: recommendation.module,
        recommendation_text: recommendation.recommendation_text,
        priority_score: recommendation.priority_score,
        timeline: recommendation.timeline,
      });
      commit('UPDATE_RECOMMENDATION_STATUS', {
        recommendationId: recommendation.recommendation_id,
        status: 'dismissed',
      });
    } catch (error) {
      throw new Error(error.response?.data?.message || 'Failed to dismiss recommendation');
    }
  },

  async updateRecommendationNotes({ state }, { recommendationId, notes }) {
    try {
      await api.patch(`/recommendations/${recommendationId}/notes`, { notes });
    } catch (error) {
      throw new Error(error.response?.data?.message || 'Failed to update notes');
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
