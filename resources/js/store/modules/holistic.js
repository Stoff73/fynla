import holisticService from '../../services/holisticService';

const state = {
    analysis: null,
    plan: null,
    recommendations: [],
    cashFlowAnalysis: null,
    completedRecommendations: [],
    loading: false,
    error: null,
};

const getters = {
    hasAnalysis: (state) => state.analysis !== null,
    hasPlan: (state) => state.plan !== null,

    activeRecommendations: (state) => {
        return state.recommendations.filter(r => r.status === 'pending' || r.status === 'in_progress');
    },

    pendingRecommendations: (state) => {
        return state.recommendations.filter(r => r.status === 'pending');
    },

    inProgressRecommendations: (state) => {
        return state.recommendations.filter(r => r.status === 'in_progress');
    },

    recommendationsByTimeline: (state) => {
        const grouped = {
            immediate: [],
            short_term: [],
            medium_term: [],
            long_term: [],
        };

        state.recommendations.forEach(rec => {
            if (grouped[rec.timeline]) {
                grouped[rec.timeline].push(rec);
            }
        });

        return grouped;
    },

    recommendationsByModule: (state) => {
        const grouped = {};

        state.recommendations.forEach(rec => {
            if (!grouped[rec.module]) {
                grouped[rec.module] = [];
            }
            grouped[rec.module].push(rec);
        });

        return grouped;
    },

    topPriorities: (state) => (limit = 5) => {
        return [...state.recommendations]
            .sort((a, b) => b.priority_score - a.priority_score)
            .slice(0, limit);
    },

    availableSurplus: (state) => {
        return state.cashFlowAnalysis?.available_surplus || 0;
    },

    hasShortfall: (state) => {
        return state.cashFlowAnalysis?.shortfall_analysis?.has_shortfall || false;
    },

    executiveSummary: (state) => {
        return state.plan?.executive_summary || null;
    },

    netWorthProjection: (state) => {
        return state.plan?.net_worth_projection || null;
    },

    riskAssessment: (state) => {
        return state.plan?.risk_assessment || null;
    },

    actionPlan: (state) => {
        return state.plan?.action_plan || null;
    },
};

const mutations = {
    SET_ANALYSIS(state, analysis) {
        state.analysis = analysis;
    },

    SET_PLAN(state, plan) {
        state.plan = plan;
    },

    SET_RECOMMENDATIONS(state, recommendations) {
        state.recommendations = recommendations;
    },

    SET_CASH_FLOW_ANALYSIS(state, cashFlowAnalysis) {
        state.cashFlowAnalysis = cashFlowAnalysis;
    },

    SET_COMPLETED_RECOMMENDATIONS(state, completedRecommendations) {
        state.completedRecommendations = completedRecommendations;
    },

    SET_LOADING(state, loading) {
        state.loading = loading;
    },

    SET_ERROR(state, error) {
        state.error = error;
    },

    UPDATE_RECOMMENDATION(state, updatedRecommendation) {
        const index = state.recommendations.findIndex(r => r.id === updatedRecommendation.id);
        if (index !== -1) {
            state.recommendations.splice(index, 1, updatedRecommendation);
        }
    },

    REMOVE_RECOMMENDATION(state, id) {
        state.recommendations = state.recommendations.filter(r => r.id !== id);
    },

    CLEAR_ERROR(state) {
        state.error = null;
    },

    CLEAR_ALL(state) {
        state.analysis = null;
        state.plan = null;
        state.recommendations = [];
        state.cashFlowAnalysis = null;
        state.completedRecommendations = [];
        state.error = null;
    },
};

const actions = {
    async fetchAnalysis({ commit }) {
        commit('SET_LOADING', true);
        commit('CLEAR_ERROR');

        try {
            const response = await holisticService.analyzeHolistic();
            commit('SET_ANALYSIS', response.data);
            return response.data;
        } catch (error) {
            commit('SET_ERROR', error.response?.data?.message || 'Failed to fetch holistic analysis');
            throw error;
        } finally {
            commit('SET_LOADING', false);
        }
    },

    async fetchPlan({ commit }) {
        commit('SET_LOADING', true);
        commit('CLEAR_ERROR');

        try {
            const response = await holisticService.getPlan();
            commit('SET_PLAN', response.data);

            // Also fetch recommendations
            const recsResponse = await holisticService.getRecommendations();
            commit('SET_RECOMMENDATIONS', recsResponse.data);

            return response.data;
        } catch (error) {
            commit('SET_ERROR', error.response?.data?.message || 'Failed to fetch holistic plan');
            throw error;
        } finally {
            commit('SET_LOADING', false);
        }
    },

    async fetchRecommendations({ commit }) {
        commit('SET_LOADING', true);
        commit('CLEAR_ERROR');

        try {
            const response = await holisticService.getRecommendations();
            commit('SET_RECOMMENDATIONS', response.data);
            return response.data;
        } catch (error) {
            commit('SET_ERROR', error.response?.data?.message || 'Failed to fetch recommendations');
            throw error;
        } finally {
            commit('SET_LOADING', false);
        }
    },

    async fetchCashFlowAnalysis({ commit }) {
        commit('SET_LOADING', true);
        commit('CLEAR_ERROR');

        try {
            const response = await holisticService.getCashFlowAnalysis();
            commit('SET_CASH_FLOW_ANALYSIS', response.data);
            return response.data;
        } catch (error) {
            commit('SET_ERROR', error.response?.data?.message || 'Failed to fetch cashflow analysis');
            throw error;
        } finally {
            commit('SET_LOADING', false);
        }
    },

    async markRecommendationDone({ commit }, id) {
        try {
            const response = await holisticService.markRecommendationDone(id);
            commit('UPDATE_RECOMMENDATION', response.data);
            return response.data;
        } catch (error) {
            commit('SET_ERROR', error.response?.data?.message || 'Failed to mark recommendation as done');
            throw error;
        }
    },

    async markRecommendationInProgress({ commit }, id) {
        try {
            const response = await holisticService.markRecommendationInProgress(id);
            commit('UPDATE_RECOMMENDATION', response.data);
            return response.data;
        } catch (error) {
            commit('SET_ERROR', error.response?.data?.message || 'Failed to mark recommendation as in progress');
            throw error;
        }
    },

    async dismissRecommendation({ commit }, id) {
        try {
            const response = await holisticService.dismissRecommendation(id);
            commit('REMOVE_RECOMMENDATION', id);
            return response.data;
        } catch (error) {
            commit('SET_ERROR', error.response?.data?.message || 'Failed to dismiss recommendation');
            throw error;
        }
    },

    async fetchCompletedRecommendations({ commit }) {
        try {
            const response = await holisticService.getCompletedRecommendations();
            commit('SET_COMPLETED_RECOMMENDATIONS', response.data);
            return response.data;
        } catch (error) {
            commit('SET_ERROR', error.response?.data?.message || 'Failed to fetch completed recommendations');
            throw error;
        }
    },

    async updateRecommendationNotes({ commit }, { id, notes }) {
        try {
            const response = await holisticService.updateRecommendationNotes(id, notes);
            commit('UPDATE_RECOMMENDATION', response.data);
            return response.data;
        } catch (error) {
            commit('SET_ERROR', error.response?.data?.message || 'Failed to update notes');
            throw error;
        }
    },

    clearError({ commit }) {
        commit('CLEAR_ERROR');
    },

    clearAll({ commit }) {
        commit('CLEAR_ALL');
    },
};

export default {
    namespaced: true,
    state,
    getters,
    mutations,
    actions,
};
