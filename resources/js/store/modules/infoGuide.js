/**
 * Information Guide Store Module
 *
 * Manages the floating help panel that shows users what data
 * is needed for each module and what they're missing.
 */

import api from '@/services/api';

import logger from '@/utils/logger';
const state = {
    isOpen: false,
    isEnabled: true,
    currentModule: 'dashboard',
    requirements: null,
    loading: false,
    error: null,
    initialized: false,
};

const getters = {
    isOpen: (state) => state.isOpen,
    isEnabled: (state) => state.isEnabled,
    currentModule: (state) => state.currentModule,
    requirements: (state) => state.requirements,
    loading: (state) => state.loading,

    // Get all requirements
    allRequirements: (state) => state.requirements?.all_requirements || [],

    // Get filled items
    filledItems: (state) => state.requirements?.filled || [],

    // Get missing items
    missingItems: (state) => state.requirements?.missing || [],

    // Get missing count (for badge)
    missingCount: (state) => state.requirements?.missing?.length || 0,

    // Get completion percentage
    completionPercentage: (state) => state.requirements?.completion_percentage || 0,

    // Get module description
    moduleDescription: (state) => state.requirements?.description || '',

    // Check if guide should be visible
    shouldShowGuide: (state, getters, rootState, rootGetters) => {
        // Always show for preview users (educational purposes)
        if (rootGetters['preview/isPreviewMode']) {
            return true;
        }
        return state.isEnabled;
    },
};

const mutations = {
    SET_OPEN(state, isOpen) {
        state.isOpen = isOpen;
    },

    SET_ENABLED(state, isEnabled) {
        state.isEnabled = isEnabled;
    },

    SET_CURRENT_MODULE(state, module) {
        state.currentModule = module;
    },

    SET_REQUIREMENTS(state, requirements) {
        state.requirements = requirements;
    },

    SET_LOADING(state, loading) {
        state.loading = loading;
    },

    SET_ERROR(state, error) {
        state.error = error;
    },

    SET_INITIALIZED(state, initialized) {
        state.initialized = initialized;
    },

    RESET(state) {
        state.isOpen = false;
        state.currentModule = 'dashboard';
        state.requirements = null;
        state.loading = false;
        state.error = null;
        state.initialized = false;
    },
};

const actions = {
    /**
     * Toggle the panel open/closed
     */
    toggle({ commit, state }) {
        commit('SET_OPEN', !state.isOpen);
    },

    /**
     * Open the panel
     */
    open({ commit }) {
        commit('SET_OPEN', true);
    },

    /**
     * Close the panel
     */
    close({ commit }) {
        commit('SET_OPEN', false);
    },

    /**
     * Fetch requirements for a specific module
     */
    async fetchRequirements({ commit, state }, module) {
        // Skip if same module and already loaded (with valid requirements)
        if (state.currentModule === module && state.requirements && state.requirements.module === module) {
            return;
        }

        // Update module immediately to prevent race conditions
        commit('SET_CURRENT_MODULE', module);
        commit('SET_LOADING', true);
        commit('SET_ERROR', null);

        try {
            const response = await api.get('/info-guide/requirements', {
                params: { module },
            });

            // Only set requirements if module hasn't changed during fetch
            if (state.currentModule === module) {
                commit('SET_REQUIREMENTS', response.data.data);
            }
        } catch (error) {
            logger.error('Failed to fetch info guide requirements:', error);
            commit('SET_ERROR', 'Failed to load requirements');
        } finally {
            commit('SET_LOADING', false);
        }
    },

    /**
     * Force refresh requirements for current module
     */
    async refreshRequirements({ commit, state }) {
        commit('SET_LOADING', true);
        commit('SET_ERROR', null);

        try {
            const response = await api.get('/info-guide/requirements', {
                params: { module: state.currentModule },
            });

            commit('SET_REQUIREMENTS', response.data.data);
        } catch (error) {
            logger.error('Failed to refresh info guide requirements:', error);
            commit('SET_ERROR', 'Failed to refresh requirements');
        } finally {
            commit('SET_LOADING', false);
        }
    },

    /**
     * Fetch user's guide preference
     */
    async fetchPreference({ commit, state }) {
        if (state.initialized) {
            return;
        }

        try {
            const response = await api.get('/info-guide/preference');
            commit('SET_ENABLED', response.data.data.enabled);
            commit('SET_INITIALIZED', true);
        } catch (error) {
            logger.error('Failed to fetch info guide preference:', error);
            // Default to enabled on error
            commit('SET_ENABLED', true);
            commit('SET_INITIALIZED', true);
        }
    },

    /**
     * Update user's guide preference
     */
    async updatePreference({ commit }, enabled) {
        commit('SET_ENABLED', enabled);

        try {
            await api.put('/info-guide/preference', { enabled });
        } catch (error) {
            logger.error('Failed to update info guide preference:', error);
            // Revert on error
            commit('SET_ENABLED', !enabled);
        }
    },

    /**
     * Initialize from user data (called after login)
     */
    initFromUser({ commit }, user) {
        if (!user) return;

        commit('SET_ENABLED', user.info_guide_enabled ?? true);
        commit('SET_INITIALIZED', true);
    },

    /**
     * Reset state (for logout)
     */
    reset({ commit }) {
        commit('RESET');
    },
};

export default {
    namespaced: true,
    state,
    getters,
    mutations,
    actions,
};
