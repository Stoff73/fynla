/**
 * Preview Mode Vuex Store Module (Simplified)
 *
 * Manages preview mode authentication. Preview users are actual database users
 * with is_preview_user=true. They use the same code paths as real users - data
 * is loaded via normal APIs.
 *
 * The only differences for preview users:
 * - Write operations are intercepted by middleware (return fake success)
 * - Preview banner is shown with persona selector
 * - Session changes are lost on refresh (by design)
 */

import api from '../../services/api';

// Import full persona data from JSON files
import youngFamilyData from '../../data/personas/young_family.json';
import peakEarnersData from '../../data/personas/peak_earners.json';
import widowData from '../../data/personas/widow.json';
import entrepreneurData from '../../data/personas/entrepreneur.json';
import youngSaverData from '../../data/personas/young_saver.json';
import retiredCoupleData from '../../data/personas/retired_couple.json';

// Full persona data for use in components that need detailed info
const PERSONA_DATA = {
    young_family: youngFamilyData,
    peak_earners: peakEarnersData,
    widow: widowData,
    entrepreneur: entrepreneurData,
    young_saver: youngSaverData,
    retired_couple: retiredCoupleData,
};

// Persona metadata for the selector UI (order determines display order)
const PERSONA_METADATA = {
    young_saver: {
        id: 'young_saver',
        name: 'Alex Morgan',
        tagline: 'Young professional building savings',
        netWorthRange: '-£35k to -£30k',
        focus: 'First home, emergency fund',
        description: 'A 24-year-old junior data analyst, renting and saving for a house deposit with a Lifetime ISA.',
    },
    young_family: {
        id: 'young_family',
        name: 'Emily & James Carter',
        tagline: 'Young family building their future',
        netWorthRange: '£80k - £120k',
        focus: 'Protection gaps, emergency fund',
        description: 'A young married couple in their early 30s with two children, mortgage, and workplace pensions.',
    },
    entrepreneur: {
        id: 'entrepreneur',
        name: 'Alex Chen',
        tagline: 'Self-made tech entrepreneur',
        netWorthRange: '£800k - £1m',
        focus: 'Business protection, single estate',
        description: 'A 38-year-old single tech consultancy owner with business interests and SIPP.',
    },
    peak_earners: {
        id: 'peak_earners',
        name: 'David & Sarah Mitchell',
        tagline: 'High earners approaching retirement',
        netWorthRange: '£1.5m - £2m',
        focus: 'Tax efficiency, pension allowances',
        description: 'A couple in their late 40s with high incomes, BTL property, and complex pension arrangements.',
    },
    retired_couple: {
        id: 'retired_couple',
        name: 'Patricia & Harold Bennett',
        tagline: 'Retired couple with estate focus',
        netWorthRange: '£800k - £900k',
        focus: 'IHT planning, gifting strategy',
        description: 'A retired couple in their early 70s drawing DB pensions, focusing on IHT planning and gifting.',
    },
    widow: {
        id: 'widow',
        name: 'Margaret Thompson',
        tagline: 'Retired widow with estate concerns',
        netWorthRange: '£1.4m - £1.6m',
        focus: 'IHT planning, gifting strategy',
        description: 'A 68-year-old retired headteacher who was widowed, with complex estate planning needs.',
    },
};

const state = {
    loading: false,
    error: null,
};

const getters = {
    /**
     * Check if currently in preview mode by looking at the authenticated user
     */
    isPreviewMode: (state, getters, rootState) => {
        return rootState.auth?.user?.is_preview_user === true;
    },

    /**
     * Get the current persona ID from the authenticated user
     */
    currentPersonaId: (state, getters, rootState) => {
        return rootState.auth?.user?.preview_persona_id || null;
    },

    /**
     * Get metadata for the current persona
     */
    currentPersona: (state, getters) => {
        const personaId = getters.currentPersonaId;
        return personaId ? PERSONA_METADATA[personaId] : null;
    },

    /**
     * Get all available personas for the selector
     */
    availablePersonas: () => Object.values(PERSONA_METADATA),

    /**
     * Get the full persona data (properties, savings, pensions, etc.)
     * Used by KeepDataOrFreshModal to display data summary
     */
    effectivePersonaData: (state, getters) => {
        const personaId = getters.currentPersonaId;
        return personaId ? PERSONA_DATA[personaId] : null;
    },

    /**
     * Check if there are unsaved edits (always false in new architecture)
     * Edits are session-only and not tracked in store
     */
    hasEdits: () => false,

    /**
     * Get count of unsaved edits (always 0 in new architecture)
     */
    editCount: () => 0,

    loading: (state) => state.loading,
    error: (state) => state.error,
};

const mutations = {
    SET_LOADING(state, loading) {
        state.loading = loading;
    },

    SET_ERROR(state, error) {
        state.error = error;
    },
};

const actions = {
    /**
     * Initialize preview state from storage (called on app startup)
     * With the new architecture, preview users are authenticated via Sanctum tokens,
     * so this just returns false - the auth store handles restoring the session.
     */
    async initFromStorage() {
        // Preview mode is now determined by auth.user.is_preview_user
        // The auth store handles restoring the token from localStorage
        return false;
    },

    /**
     * Load a persona (alias for enterPreviewMode for backward compatibility)
     * @param {string} personaId - ID of persona to use
     */
    async loadPersona({ dispatch }, personaId) {
        return dispatch('enterPreviewMode', personaId);
    },

    /**
     * Enter preview mode by logging in as a preview user
     * @param {string} personaId - ID of persona to use (e.g., 'young_family')
     */
    async enterPreviewMode({ commit, dispatch }, personaId) {
        commit('SET_LOADING', true);
        commit('SET_ERROR', null);

        try {
            // CRITICAL: Clear ALL auth and data state to prevent data leakage
            localStorage.removeItem('auth_token');
            commit('auth/clearAuth', null, { root: true });

            // CRITICAL: Reset all module states to prevent previous user's data from showing
            commit('userProfile/resetState', null, { root: true });
            dispatch('netWorth/resetState', null, { root: true }).catch(() => {});

            const response = await api.post(`/preview/login/${personaId}`);

            if (response.data.success) {
                const token = response.data.token;
                localStorage.setItem('auth_token', token);
                commit('auth/setToken', token, { root: true });
                commit('auth/setUser', response.data.user, { root: true });

                // CRITICAL: Reload the page to ensure ALL state is fresh
                // This prevents any possibility of previous user data showing
                window.location.href = '/dashboard';

                return response.data;
            } else {
                throw new Error(response.data.message || 'Failed to enter preview mode');
            }
        } catch (error) {
            const message = error.response?.data?.message || error.message;
            commit('SET_ERROR', message);
            throw error;
        } finally {
            commit('SET_LOADING', false);
        }
    },

    /**
     * Switch to a different preview persona
     * @param {string} personaId - ID of persona to switch to
     */
    async switchPersona({ commit, dispatch, getters }, personaId) {
        if (getters.currentPersonaId === personaId) return;

        commit('SET_LOADING', true);
        commit('SET_ERROR', null);

        try {
            // CRITICAL: Reset module states before switching to prevent data leakage
            commit('userProfile/resetState', null, { root: true });
            dispatch('netWorth/resetState', null, { root: true }).catch(() => {});

            const response = await api.post(`/preview/switch/${personaId}`);

            if (response.data.success) {
                // Store the new token
                const token = response.data.token;
                localStorage.setItem('auth_token', token);

                // Update auth state with the new preview user
                commit('auth/setUser', response.data.user, { root: true });
                commit('auth/setToken', token, { root: true });

                // Reload the page to fetch fresh data for the new persona
                window.location.reload();

                return response.data;
            } else {
                throw new Error(response.data.message || 'Failed to switch persona');
            }
        } catch (error) {
            const message = error.response?.data?.message || error.message;
            commit('SET_ERROR', message);
            throw error;
        } finally {
            commit('SET_LOADING', false);
        }
    },

    /**
     * Clear edits (no-op in new architecture - edits are session-only)
     */
    async clearEdits() {
        // No-op: With the new architecture, edits are handled by the backend
        // interceptor and are session-only, so there's nothing to clear in the store
        return;
    },

    /**
     * Exit preview mode
     */
    async exitPreview({ commit }) {
        try {
            // Call the preview exit endpoint
            await api.post('/preview/exit');
        } catch (error) {
            // Ignore errors - we're logging out anyway
            console.warn('[Preview] Exit error:', error.message);
        }

        // Clear auth state
        localStorage.removeItem('auth_token');
        commit('auth/setUser', null, { root: true });
        commit('auth/setToken', null, { root: true });

        // Redirect to landing page
        window.location.href = '/';
    },
};

export default {
    namespaced: true,
    state,
    getters,
    mutations,
    actions,
};
