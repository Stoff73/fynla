import advisorService from '@/services/advisorService';

const state = {
    dashboardStats: null,
    clients: [],
    clientDetail: null,
    activities: [],
    reviewsDue: [],
    impersonating: false,
    impersonatedClient: null,
    loading: false,
    error: null,
};

const getters = {
    clients: (state) => state.clients,
    activeClients: (state) => state.clients.filter(c => c.status === 'active'),
    clientById: (state) => (id) => state.clients.find(c => c.client_id === id),
    overdueReviews: (state) => state.reviewsDue.filter(r => r.is_overdue),
    impersonating: (state) => state.impersonating,
    impersonatedClient: (state) => state.impersonatedClient,
    loading: (state) => state.loading,
    error: (state) => state.error,
};

const mutations = {
    setDashboardStats(state, stats) {
        state.dashboardStats = stats;
    },

    setClients(state, clients) {
        state.clients = clients;
    },

    setClientDetail(state, detail) {
        state.clientDetail = detail;
    },

    setActivities(state, activities) {
        state.activities = activities;
    },

    setReviewsDue(state, reviews) {
        state.reviewsDue = reviews;
    },

    setImpersonating(state, { impersonating, client }) {
        state.impersonating = impersonating;
        state.impersonatedClient = client || null;
    },

    setLoading(state, loading) {
        state.loading = loading;
    },

    setError(state, error) {
        state.error = error;
    },
};

const actions = {
    async fetchDashboard({ commit }) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await advisorService.getDashboard();
            const data = response.data || response;
            commit('setDashboardStats', data);
            return data;
        } catch (error) {
            const errorMessage = error.response?.data?.message || error.message || 'Failed to fetch advisor dashboard';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    async fetchClients({ commit }, filters = {}) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await advisorService.getClients(filters);
            const data = response.data || response;
            commit('setClients', data);
            return data;
        } catch (error) {
            const errorMessage = error.response?.data?.message || error.message || 'Failed to fetch clients';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    async fetchClientDetail({ commit }, id) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await advisorService.getClientDetail(id);
            const data = response.data || response;
            commit('setClientDetail', data);
            return data;
        } catch (error) {
            const errorMessage = error.response?.data?.message || error.message || 'Failed to fetch client detail';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    async fetchActivities({ commit }, filters = {}) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await advisorService.getActivities(filters);
            const data = response.data || response;
            commit('setActivities', data);
            return data;
        } catch (error) {
            const errorMessage = error.response?.data?.message || error.message || 'Failed to fetch activities';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    async fetchReviewsDue({ commit }) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await advisorService.getReviewsDue();
            const data = response.data || response;
            commit('setReviewsDue', data);
            return data;
        } catch (error) {
            const errorMessage = error.response?.data?.message || error.message || 'Failed to fetch reviews due';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    async enterClient({ commit }, clientId) {
        try {
            const response = await advisorService.enterClient(clientId);
            const data = response.data || response;
            commit('setImpersonating', { impersonating: true, client: data.client });
            return data;
        } catch (error) {
            const errorMessage = error.response?.data?.message || error.message || 'Failed to enter client context';
            commit('setError', errorMessage);
            throw error;
        }
    },

    async exitClient({ commit }) {
        try {
            await advisorService.exitClient();
            commit('setImpersonating', { impersonating: false, client: null });
        } catch (error) {
            const errorMessage = error.response?.data?.message || error.message || 'Failed to exit client context';
            commit('setError', errorMessage);
            throw error;
        }
    },

    async createActivity({ dispatch }, data) {
        const response = await advisorService.createActivity(data);
        await dispatch('fetchActivities');
        return response.data || response;
    },

    async updateActivity({ dispatch }, { id, data }) {
        const response = await advisorService.updateActivity(id, data);
        await dispatch('fetchActivities');
        return response.data || response;
    },
};

export default {
    namespaced: true,
    state,
    getters,
    mutations,
    actions,
};
