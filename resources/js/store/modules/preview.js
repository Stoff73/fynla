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
import { getToken, removeToken, setToken as storageSetToken, setItem, getItem, removeItem, isNativePlatform } from '../../services/tokenStorage';
import logger from '../../utils/logger';

// Import full persona data from JSON files
import youngFamilyData from '../../data/personas/young_family.json';
import peakEarnersData from '../../data/personas/peak_earners.json';
import entrepreneurData from '../../data/personas/entrepreneur.json';
import youngSaverData from '../../data/personas/young_saver.json';
import retiredCoupleData from '../../data/personas/retired_couple.json';
import studentData from '../../data/personas/student.json';

// Full persona data for use in components that need detailed info
// JSON files are the single source of truth for all persona data
const PERSONA_DATA = {
    young_family: youngFamilyData,
    peak_earners: peakEarnersData,
    entrepreneur: entrepreneurData,
    young_saver: youngSaverData,
    retired_couple: retiredCoupleData,
    student: studentData,
};

// Persona display order for the selector UI
// Order: Carters, Mitchells, Chen, Morgan, then others
const PERSONA_ORDER = [
    'young_family',    // James & Emily Carter
    'peak_earners',    // David & Sarah Mitchell
    'entrepreneur',    // Alex Chen
    'young_saver',     // John Morgan
    'student',         // Janice Taylor
    'retired_couple',  // Patricia & Harold Bennett
];

/**
 * Get persona metadata from JSON data
 * Single source of truth - no duplicate data
 */
function getPersonaMetadata(personaId) {
    const data = PERSONA_DATA[personaId];
    if (!data) return null;
    return {
        id: data.id,
        name: data.name,
        tagline: data.tagline,
        description: data.description,
        netWorthRange: data.netWorthRange,
        focus: data.focus,
    };
}

const state = {
    loading: false,
    error: null,
    previewReferrer: null,
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
        return personaId ? getPersonaMetadata(personaId) : null;
    },

    /**
     * Get all available personas for the selector
     * Uses PERSONA_ORDER for consistent display order
     */
    availablePersonas: () => PERSONA_ORDER.map(id => getPersonaMetadata(id)).filter(Boolean),

    /**
     * Get the full persona data (properties, savings, pensions, etc.)
     * Used by KeepDataOrFreshModal to display data summary
     */
    effectivePersonaData: (state, getters) => {
        const personaId = getters.currentPersonaId;
        // For spouse personas, get the base persona data
        const baseId = getters.basePersonaId;
        return baseId ? PERSONA_DATA[baseId] : null;
    },

    loading: (state) => state.loading,
    error: (state) => state.error,

    // =========================================
    // Spouse View Toggle Getters
    // =========================================

    /**
     * Check if the current persona has a spouse (for toggle visibility)
     * Only young_family, peak_earners, and retired_couple have spouse accounts
     */
    hasSpouse: (state, getters) => {
        const baseId = getters.basePersonaId;
        const personasWithSpouses = ['young_family', 'peak_earners', 'retired_couple'];
        return personasWithSpouses.includes(baseId);
    },

    /**
     * Check if currently viewing as the spouse
     */
    isViewingAsSpouse: (state, getters) => {
        const personaId = getters.currentPersonaId;
        return personaId ? personaId.endsWith('_spouse') : false;
    },

    /**
     * Get the base persona ID (without _spouse suffix)
     */
    basePersonaId: (state, getters) => {
        const personaId = getters.currentPersonaId;
        if (!personaId) return null;
        return personaId.replace('_spouse', '');
    },

    /**
     * Get the spouse's first name from the persona JSON
     */
    spouseFirstName: (state, getters) => {
        const baseId = getters.basePersonaId;
        const data = baseId ? PERSONA_DATA[baseId] : null;
        return data?.spouse?.first_name || null;
    },

    /**
     * Get the primary user's first name from the persona JSON
     */
    primaryFirstName: (state, getters) => {
        const baseId = getters.basePersonaId;
        const data = baseId ? PERSONA_DATA[baseId] : null;
        return data?.user?.first_name || null;
    },

    /**
     * Get the name to display on the toggle button (the OTHER person's name)
     */
    toggleTargetName: (state, getters) => {
        if (getters.isViewingAsSpouse) {
            return getters.primaryFirstName;
        }
        return getters.spouseFirstName;
    },

    /**
     * Get the current viewer's first name (for the banner indicator)
     */
    currentViewerName: (state, getters) => {
        if (getters.isViewingAsSpouse) {
            return getters.spouseFirstName;
        }
        return getters.primaryFirstName;
    },
};

const mutations = {
    SET_LOADING(state, loading) {
        state.loading = loading;
    },

    SET_ERROR(state, error) {
        state.error = error;
    },

    SET_PREVIEW_REFERRER(state, path) {
        state.previewReferrer = path;
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
        // The auth store handles restoring the token from token storage
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
        commit('SET_PREVIEW_REFERRER', window.location.pathname + window.location.search);
        logger.info('[Preview] Entering preview mode for:', personaId);

        try {
            // Save real user's token if they're signed in
            const existingToken = await getToken();
            if (existingToken) {
                await setItem('fynla_real_user_token', existingToken);
                logger.info('[Preview] Saved real user token for restoration on exit');
            }

            logger.info('[Preview] Removing token...');
            await removeToken();
            logger.info('[Preview] Token removed, clearing auth...');
            commit('auth/clearAuth', null, { root: true });

            logger.info('[Preview] Resetting module states...');
            commit('userProfile/resetState', null, { root: true });
            dispatch('netWorth/resetState', null, { root: true }).catch(() => {});

            logger.info('[Preview] Calling API /preview/login/', personaId);
            const response = await api.post(`/preview/login/${personaId}`);
            logger.info('[Preview] API response success:', response.data?.success);

            if (response.data.success) {
                const token = response.data.token;
                logger.info('[Preview] Storing token');
                await storageSetToken(token);
                commit('auth/setToken', token, { root: true });
                commit('auth/setUser', response.data.user, { root: true });

                // Set the life stage from the persona mapping
                dispatch('lifeStage/setStageFromPersona', personaId, { root: true });

                // Use SPA navigation to preserve in-memory state (token, user)
                const router = window.__appRouter;
                if (router && isNativePlatform()) {
                    logger.info('[Preview] SPA navigate to /m/home');
                    router.push('/m/home');
                } else if (router) {
                    logger.info('[Preview] SPA navigate to /dashboard');
                    router.push('/dashboard');
                } else {
                    logger.info('[Preview] Fallback navigate to /dashboard');
                    window.location.href = '/dashboard';
                }

                return response.data;
            } else {
                throw new Error(response.data.message || 'Failed to enter preview mode');
            }
        } catch (error) {
            logger.error('[Preview] Enter preview mode failed', error?.message);
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
                await storageSetToken(token);

                // Update auth state with the new preview user
                commit('auth/setUser', response.data.user, { root: true });
                commit('auth/setToken', token, { root: true });

                // Set the life stage from the persona mapping
                dispatch('lifeStage/setStageFromPersona', personaId, { root: true });

                // Use SPA navigation to preserve in-memory state
                const router = window.__appRouter;
                if (router && isNativePlatform()) {
                    router.replace({ path: '/m/home', query: { _t: Date.now() } });
                } else if (router) {
                    router.replace({ path: '/dashboard', query: { _t: Date.now() } });
                } else {
                    window.location.reload();
                }

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
     * Switch to viewing as the spouse
     * Only works for personas that have spouse accounts
     */
    async switchToSpouse({ dispatch, getters }) {
        if (!getters.hasSpouse || getters.isViewingAsSpouse) return;
        const spousePersonaId = `${getters.basePersonaId}_spouse`;
        return dispatch('switchPersona', spousePersonaId);
    },

    /**
     * Switch back to viewing as the primary user
     */
    async switchToPrimary({ dispatch, getters }) {
        if (!getters.isViewingAsSpouse) return;
        return dispatch('switchPersona', getters.basePersonaId);
    },

    /**
     * Toggle between primary and spouse views
     */
    async toggleSpouseView({ dispatch, getters }) {
        if (getters.isViewingAsSpouse) {
            return dispatch('switchToPrimary');
        } else {
            return dispatch('switchToSpouse');
        }
    },

    /**
     * Exit preview mode
     */
    async exitPreview({ commit, state }) {
        const referrer = state.previewReferrer || '/';

        // Clear preview auth state FIRST to prevent 401 interceptor redirects
        await removeToken();
        commit('auth/setUser', null, { root: true });
        commit('auth/setToken', null, { root: true });
        commit('SET_PREVIEW_REFERRER', null);

        // Call the preview exit endpoint (after clearing local state)
        try {
            await api.post('/preview/exit');
        } catch (error) {
            // Ignore errors - token already cleared locally
            logger.error('[Preview] Exit error', error.message);
        }

        // Restore real user's token if they were signed in before demo
        const realToken = await getItem('fynla_real_user_token');
        if (realToken) {
            await storageSetToken(realToken);
            await removeItem('fynla_real_user_token');
            logger.info('[Preview] Restored real user token');
        }

        // On native, use SPA navigation; on web, redirect to referrer
        const router = window.__appRouter;
        if (router && isNativePlatform()) {
            router.push('/m/login');
        } else {
            window.location.href = referrer;
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
