import dashboardService from '@/services/dashboardService';

const state = {
    overviewData: null,
    alerts: [],
    loading: false,
    error: null,
    // Preview mode state
    isPreviewMode: false,
    previewData: null,
};

const getters = {
    overviewData: (state) => state.overviewData,
    alerts: (state) => state.alerts,

    // Get alerts by severity
    criticalAlerts: (state) => {
        return state.alerts.filter(alert => alert.severity === 'critical');
    },

    importantAlerts: (state) => {
        return state.alerts.filter(alert => alert.severity === 'important');
    },

    infoAlerts: (state) => {
        return state.alerts.filter(alert => alert.severity === 'info');
    },

    // Get total alert count
    totalAlerts: (state) => state.alerts.length,

    loading: (state) => state.loading,
    error: (state) => state.error,
};

const actions = {
    // Fetch all dashboard data
    async fetchDashboardData({ commit, state }) {
        // Skip API call if in preview mode
        if (state.isPreviewMode) {
            return;
        }
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await dashboardService.getDashboardData();
            commit('setOverviewData', response.data);
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to fetch dashboard data';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    // Fetch alerts
    async fetchAlerts({ commit, state }) {
        // Skip API call if in preview mode
        if (state.isPreviewMode) {
            return;
        }
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await dashboardService.getAlerts();
            commit('setAlerts', response.data);
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to fetch alerts';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    // Dismiss an alert
    async dismissAlert({ commit }, alertId) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await dashboardService.dismissAlert(alertId);
            commit('removeAlert', alertId);
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to dismiss alert';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    // Fetch all dashboard data in parallel with graceful error handling
    async fetchAllDashboardData({ commit, state }) {
        // Skip API call if in preview mode
        if (state.isPreviewMode) {
            return;
        }
        commit('setLoading', true);
        commit('setError', null);

        try {
            const result = await dashboardService.fetchAllDashboardData();

            // Set data for successful requests
            if (result.overview) {
                commit('setOverviewData', result.overview.data);
            }
            if (result.alerts) {
                commit('setAlerts', result.alerts.data);
            }

            // Collect any errors
            const errors = [];
            if (result.errors.overview) errors.push('Overview data failed to load');
            if (result.errors.alerts) errors.push('Alerts failed to load');

            if (errors.length > 0) {
                commit('setError', errors.join('; '));
            }

            return result;
        } catch (error) {
            const errorMessage = error.message || 'Failed to fetch dashboard data';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },
};

const mutations = {
    setOverviewData(state, data) {
        state.overviewData = data;
    },

    setAlerts(state, alerts) {
        state.alerts = alerts;
    },

    removeAlert(state, alertId) {
        state.alerts = state.alerts.filter(alert => alert.id !== alertId);
    },

    setLoading(state, loading) {
        state.loading = loading;
    },

    setError(state, error) {
        state.error = error;
    },

    // Preview mode mutation
    SET_PREVIEW_MODE(state, { isPreview, data }) {
        state.isPreviewMode = isPreview;
        state.previewData = data;

        if (isPreview && data) {
            // Set dashboard data from preview if available
            if (data.dashboard_overview) {
                state.overviewData = data.dashboard_overview;
            }
        } else if (!isPreview) {
            state.isPreviewMode = false;
            state.previewData = null;
        }
    },
};

export default {
    namespaced: true,
    state,
    getters,
    actions,
    mutations,
};
