/**
 * Mobile Dashboard Store Module
 *
 * Manages the mobile dashboard state including net worth summary,
 * module overviews, alerts, and daily insights. Supports staleness
 * checking to avoid unnecessary API calls.
 */

import api from '@/services/api';

const state = {
    summary: null,
    netWorth: null,
    modules: [],
    alerts: [],
    insight: null,
    loading: false,
    error: null,
    lastFetched: null,
};

const getters = {
    summary: (state) => state.summary,
    netWorth: (state) => state.netWorth,
    modules: (state) => state.modules,
    alerts: (state) => state.alerts,
    insight: (state) => state.insight,
    loading: (state) => state.loading,
    error: (state) => state.error,
    lastFetched: (state) => state.lastFetched,

    isStale: (state) => {
        if (!state.lastFetched) return true;
        const fiveMinutes = 5 * 60 * 1000;
        return Date.now() - state.lastFetched > fiveMinutes;
    },
};

const mutations = {
    SET_DASHBOARD(state, data) {
        state.summary = data.summary || null;
        state.netWorth = data.net_worth || null;
        state.modules = data.modules || [];
        state.alerts = data.alerts || [];
        state.insight = data.insight || null;
        state.lastFetched = Date.now();
    },

    SET_LOADING(state, loading) {
        state.loading = loading;
    },

    SET_ERROR(state, error) {
        state.error = error;
    },

    CLEAR_CACHE(state) {
        state.summary = null;
        state.netWorth = null;
        state.modules = [];
        state.alerts = [];
        state.insight = null;
        state.lastFetched = null;
        state.error = null;
    },
};

const actions = {
    /**
     * Fetch mobile dashboard data (skips if not stale).
     */
    async fetchDashboard({ commit, getters }) {
        if (!getters.isStale) return;

        commit('SET_LOADING', true);
        commit('SET_ERROR', null);

        try {
            const response = await api.get('/v1/mobile/dashboard');
            commit('SET_DASHBOARD', response.data.data);
        } catch (error) {
            commit('SET_ERROR', error.message || 'Failed to load dashboard');
        } finally {
            commit('SET_LOADING', false);
        }
    },

    /**
     * Force refresh mobile dashboard data (ignores staleness).
     */
    async refreshDashboard({ commit }) {
        commit('SET_LOADING', true);
        commit('SET_ERROR', null);

        try {
            const response = await api.get('/v1/mobile/dashboard');
            commit('SET_DASHBOARD', response.data.data);
        } catch (error) {
            commit('SET_ERROR', error.message || 'Failed to refresh dashboard');
        } finally {
            commit('SET_LOADING', false);
        }
    },

    /**
     * Clear cached dashboard data.
     */
    clearCache({ commit }) {
        commit('CLEAR_CACHE');
    },
};

export default {
    namespaced: true,
    state,
    getters,
    mutations,
    actions,
};
