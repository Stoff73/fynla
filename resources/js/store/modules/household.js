import householdService from '../../services/householdService';

const state = {
    netWorth: null,
    optimisations: [],
    deathScenario: null,
    loading: false,
    error: null,
};

const mutations = {
    SET_NET_WORTH(state, data) {
        state.netWorth = data;
    },

    SET_OPTIMISATIONS(state, data) {
        state.optimisations = data;
    },

    SET_DEATH_SCENARIO(state, data) {
        state.deathScenario = data;
    },

    SET_LOADING(state, loading) {
        state.loading = loading;
    },

    SET_ERROR(state, error) {
        state.error = error;
    },

    CLEAR_ERROR(state) {
        state.error = null;
    },

    CLEAR_ALL(state) {
        state.netWorth = null;
        state.optimisations = [];
        state.deathScenario = null;
        state.loading = false;
        state.error = null;
    },
};

const actions = {
    async fetchNetWorth({ commit }) {
        commit('SET_LOADING', true);
        commit('CLEAR_ERROR');

        try {
            const response = await householdService.getNetWorth();
            if (response.success) {
                commit('SET_NET_WORTH', response.data);
            } else {
                throw new Error(response.message || 'Failed to fetch household net worth');
            }
        } catch (error) {
            const errorMessage = error.response?.data?.message || error.message || 'Failed to fetch household net worth';
            commit('SET_ERROR', errorMessage);
            throw error;
        } finally {
            commit('SET_LOADING', false);
        }
    },

    async fetchOptimisations({ commit }) {
        commit('CLEAR_ERROR');

        try {
            const response = await householdService.getOptimisations();
            if (response.success) {
                commit('SET_OPTIMISATIONS', response.data || []);
            } else {
                throw new Error(response.message || 'Failed to fetch optimisations');
            }
        } catch (error) {
            const errorMessage = error.response?.data?.message || error.message || 'Failed to fetch optimisations';
            commit('SET_ERROR', errorMessage);
            throw error;
        }
    },

    async fetchDeathScenario({ commit }, spouse = 'primary') {
        commit('CLEAR_ERROR');

        try {
            const response = await householdService.getDeathScenario(spouse);
            if (response.success) {
                commit('SET_DEATH_SCENARIO', response.data);
            } else {
                throw new Error(response.message || 'Failed to fetch death scenario');
            }
        } catch (error) {
            const errorMessage = error.response?.data?.message || error.message || 'Failed to fetch death scenario';
            commit('SET_ERROR', errorMessage);
            throw error;
        }
    },

    clearAll({ commit }) {
        commit('CLEAR_ALL');
    },
};

const getters = {
    householdNetWorth: (state) => state.netWorth,
    spousalOptimisations: (state) => state.optimisations,
    deathScenario: (state) => state.deathScenario,
    hasSpouse: (state) => state.netWorth?.has_spouse ?? false,
    isLoading: (state) => state.loading,
};

export default {
    namespaced: true,
    state,
    mutations,
    actions,
    getters,
};
