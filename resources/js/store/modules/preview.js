/**
 * Preview Mode Vuex Store Module
 *
 * Manages the preview mode feature where visitors can explore
 * the application with example persona data before registering.
 *
 * The preview mode uses the SAME dashboard, views, and components
 * as authenticated users - it simply loads persona data into the
 * existing stores and sets a flag to prevent API calls.
 */

// Pre-load all persona JSON files using Vite's glob import
// Use relative path from this file's location for reliable resolution
const personaModules = import.meta.glob('../../data/personas/*.json', { eager: true });

// Create a lookup map from persona ID to data
const personaDataMap = {};
for (const path in personaModules) {
    const match = path.match(/([^/]+)\.json$/);
    if (match) {
        personaDataMap[match[1]] = personaModules[path].default;
    }
}

// Debug: Log available personas at module load time
if (import.meta.env.DEV) {
    console.log('[Preview Store] Loaded personas:', Object.keys(personaDataMap));
}

// Session storage key for preview state persistence
const PREVIEW_STORAGE_KEY = 'fynla_preview_state';

// Persona metadata for the selector UI
const PERSONA_METADATA = {
    young_family: {
        id: 'young_family',
        name: 'Emily & James Carter',
        tagline: 'Young family building their future',
        netWorthRange: '£80k - £120k',
        focus: 'Protection gaps, emergency fund',
        description: 'A young married couple in their early 30s with two children, mortgage, and workplace pensions. Good earnings but gaps in protection coverage.',
    },
    peak_earners: {
        id: 'peak_earners',
        name: 'David & Sarah Mitchell',
        tagline: 'High earners approaching retirement',
        netWorthRange: '£1.5m - £2m',
        focus: 'Tax efficiency, pension allowances',
        description: 'A couple in their late 40s with high incomes, BTL property, substantial investments, and complex pension arrangements including DB schemes.',
    },
    widow: {
        id: 'widow',
        name: 'Margaret Thompson',
        tagline: 'Retired widow with estate concerns',
        netWorthRange: '£1.4m - £1.6m',
        focus: 'IHT planning, gifting strategy',
        description: 'A 68-year-old retired headteacher who was widowed. Multiple properties, substantial investments, and complex estate planning needs.',
    },
    entrepreneur: {
        id: 'entrepreneur',
        name: 'Alex Chen',
        tagline: 'Self-made tech entrepreneur',
        netWorthRange: '£800k - £1m',
        focus: 'Business protection, single estate',
        description: 'A 38-year-old single tech consultancy owner with business interests, SIPP, and key person insurance considerations.',
    },
};

const state = {
    // Core preview state
    isActive: false,
    currentPersonaId: null,
    personaData: null,

    // User edits to preview data (keyed by dot-notation paths)
    editedValues: {},

    // Track which personal info warnings user has acknowledged
    warningsAcknowledged: {},

    // UI state
    loading: false,
    error: null,
};

const getters = {
    isPreviewMode: (state) => state.isActive,
    currentPersonaId: (state) => state.currentPersonaId,
    currentPersona: (state) => state.currentPersonaId ? PERSONA_METADATA[state.currentPersonaId] : null,
    personaData: (state) => state.personaData,
    editedValues: (state) => state.editedValues,
    hasEdits: (state) => Object.keys(state.editedValues).length > 0,
    editCount: (state) => Object.keys(state.editedValues).length,
    availablePersonas: () => Object.values(PERSONA_METADATA),
    loading: (state) => state.loading,
    error: (state) => state.error,

    // Merge base persona data with user edits
    effectivePersonaData: (state) => {
        if (!state.personaData) return null;
        if (Object.keys(state.editedValues).length === 0) return state.personaData;

        // Deep clone and apply edits
        const result = JSON.parse(JSON.stringify(state.personaData));
        for (const [path, value] of Object.entries(state.editedValues)) {
            setNestedValue(result, path, value);
        }
        return result;
    },
};

// Helper function to set nested value by path
function setNestedValue(obj, path, value) {
    const parts = path.split('.');
    let current = obj;

    for (let i = 0; i < parts.length - 1; i++) {
        const key = isNaN(parts[i]) ? parts[i] : parseInt(parts[i]);
        if (current[key] === undefined) {
            current[key] = isNaN(parts[i + 1]) ? {} : [];
        }
        current = current[key];
    }

    const lastKey = isNaN(parts[parts.length - 1])
        ? parts[parts.length - 1]
        : parseInt(parts[parts.length - 1]);
    current[lastKey] = value;
}

const mutations = {
    SET_ACTIVE(state, isActive) {
        state.isActive = isActive;
    },

    SET_PERSONA_DATA(state, { personaId, data }) {
        state.currentPersonaId = personaId;
        state.personaData = data;
    },

    SET_LOADING(state, loading) {
        state.loading = loading;
    },

    SET_ERROR(state, error) {
        state.error = error;
    },

    CLEAR_PREVIEW(state) {
        state.isActive = false;
        state.currentPersonaId = null;
        state.personaData = null;
        state.editedValues = {};
        state.warningsAcknowledged = {};
        state.loading = false;
        state.error = null;
    },

    SET_EDITED_VALUE(state, { path, value }) {
        state.editedValues = {
            ...state.editedValues,
            [path]: value,
        };
    },

    CLEAR_EDITED_VALUES(state) {
        state.editedValues = {};
    },

    ACKNOWLEDGE_WARNING(state, fieldPath) {
        state.warningsAcknowledged = {
            ...state.warningsAcknowledged,
            [fieldPath]: true,
        };
    },
};

const actions = {
    /**
     * Initialize preview mode from sessionStorage on app startup
     * This allows preview mode to survive page reloads
     * Will NOT restore preview mode if user is already authenticated
     */
    async initFromStorage({ state, dispatch, rootGetters }) {
        try {
            // Don't restore preview mode if user is already authenticated
            if (rootGetters['auth/isAuthenticated']) {
                console.log('[Preview] User is authenticated, clearing any stored preview state');
                sessionStorage.removeItem(PREVIEW_STORAGE_KEY);
                return false;
            }

            const stored = sessionStorage.getItem(PREVIEW_STORAGE_KEY);
            if (stored) {
                const { personaId, isActive } = JSON.parse(stored);
                if (isActive && personaId && personaDataMap[personaId]) {
                    console.log('[Preview] Restoring preview mode from storage:', personaId);
                    await dispatch('loadPersona', personaId);
                    return true;
                }
            }
        } catch (error) {
            console.warn('[Preview] Failed to restore from storage:', error);
            sessionStorage.removeItem(PREVIEW_STORAGE_KEY);
        }
        return false;
    },

    /**
     * Save preview state to sessionStorage
     */
    saveToStorage({ state }) {
        if (state.isActive && state.currentPersonaId) {
            sessionStorage.setItem(PREVIEW_STORAGE_KEY, JSON.stringify({
                personaId: state.currentPersonaId,
                isActive: true,
            }));
        } else {
            sessionStorage.removeItem(PREVIEW_STORAGE_KEY);
        }
    },

    /**
     * Load a persona's data and activate preview mode
     * This loads persona data into all module stores and enables preview mode
     * @param {string} personaId - ID of persona to load (e.g., 'young_family')
     */
    async loadPersona({ commit, dispatch }, personaId) {
        console.log('[Preview] loadPersona called with:', personaId);
        commit('SET_LOADING', true);
        commit('SET_ERROR', null);

        try {
            // Get persona data from pre-loaded map
            const data = personaDataMap[personaId];
            console.log('[Preview] Persona data found:', !!data);

            if (!data) {
                const available = Object.keys(personaDataMap);
                console.error('[Preview Store] Persona not found:', {
                    requested: personaId,
                    available,
                });
                throw new Error(`Persona '${personaId}' not found. Available: ${available.length ? available.join(', ') : 'none (glob import may have failed)'}`);
            }

            commit('SET_PERSONA_DATA', { personaId, data });
            console.log('[Preview] SET_PERSONA_DATA committed');
            commit('SET_ACTIVE', true);
            console.log('[Preview] SET_ACTIVE committed, isActive should be true now');

            // Activate preview mode in all module stores - this must happen BEFORE navigation
            // so that components don't try to make API calls when they mount
            await dispatch('activatePreviewInModules', data);
            console.log('[Preview] Module stores activated with preview data');

            // Persist to sessionStorage so preview survives page reload
            // Use await to ensure storage is set before any page reload
            await dispatch('saveToStorage');
            console.log('[Preview] State saved to sessionStorage');

            return data;
        } catch (error) {
            console.error('Failed to load persona:', error);
            commit('SET_ERROR', `Failed to load persona: ${error.message}`);
            throw error;
        } finally {
            commit('SET_LOADING', false);
            console.log('[Preview] loadPersona complete');
        }
    },

    /**
     * Activate preview mode in all module stores
     * Each store's setPreviewMode action will load the relevant data
     */
    async activatePreviewInModules({ commit, dispatch }, personaData) {
        if (!personaData) return;

        // Set auth user with persona user data (many components use auth/currentUser)
        if (personaData.user) {
            commit('auth/setUser', personaData.user, { root: true });
            console.log('[Preview] Auth user set from persona data');
        }

        // Dispatch to each module store to set preview mode
        const modules = ['netWorth', 'protection', 'estate', 'retirement', 'savings', 'investment', 'userProfile'];

        await Promise.all(
            modules.map(module =>
                dispatch(`${module}/setPreviewMode`, { isPreview: true, data: personaData }, { root: true })
                    .catch(e => console.warn(`[Preview] Module ${module} preview not implemented:`, e.message))
            )
        );
    },

    /**
     * Switch to a different persona
     */
    async switchPersona({ state, dispatch }, newPersonaId) {
        if (state.currentPersonaId === newPersonaId) return;
        await dispatch('loadPersona', newPersonaId);
    },

    /**
     * Exit preview mode and clear all preview state
     */
    async exitPreview({ commit, dispatch }) {
        // Clear sessionStorage first
        sessionStorage.removeItem(PREVIEW_STORAGE_KEY);

        // Clear auth user (was set from persona data)
        commit('auth/setUser', null, { root: true });
        console.log('[Preview] Auth user cleared');

        // Clear preview mode in all module stores
        const modules = ['netWorth', 'protection', 'estate', 'retirement', 'savings', 'investment', 'userProfile'];

        await Promise.all(
            modules.map(module =>
                dispatch(`${module}/setPreviewMode`, { isPreview: false, data: null }, { root: true })
                    .catch(e => console.warn(`[Preview] Module ${module} preview clear failed:`, e.message))
            )
        );

        commit('CLEAR_PREVIEW');
    },

    /**
     * Update a value in the preview data and trigger recalculations
     * @param {string} path - Dot-notation path like 'properties.0.current_value'
     * @param {*} value - New value
     */
    async updateValue({ commit, dispatch, getters }, { path, value }) {
        console.log('[Preview] updateValue:', path, value);

        // Store the edit
        commit('SET_EDITED_VALUE', { path, value });

        // Determine which modules are affected and trigger recalculation
        const affectedModules = getAffectedModulesForPath(path);
        console.log('[Preview] Affected modules:', affectedModules);

        // Get the merged data with edits
        const effectiveData = getters.effectivePersonaData;

        // Update each affected module store with new data
        for (const moduleName of affectedModules) {
            try {
                await dispatch(`${moduleName}/setPreviewMode`, {
                    isPreview: true,
                    data: effectiveData,
                }, { root: true });
            } catch (e) {
                console.warn(`[Preview] Failed to update ${moduleName}:`, e.message);
            }
        }
    },

    /**
     * Clear all user edits and restore original persona data
     */
    async clearEdits({ commit, dispatch, state }) {
        commit('CLEAR_EDITED_VALUES');

        // Re-activate preview mode with original data
        if (state.personaData) {
            await dispatch('activatePreviewInModules', state.personaData);
        }
    },

    /**
     * Acknowledge a personal info warning for a field
     */
    acknowledgeWarning({ commit }, fieldPath) {
        commit('ACKNOWLEDGE_WARNING', fieldPath);
    },
};

// Helper to determine which modules need recalculation
function getAffectedModulesForPath(path) {
    const modules = [];

    // Property changes affect net worth and estate (IHT)
    if (path.startsWith('properties') || path.startsWith('mortgages')) {
        modules.push('netWorth', 'estate');
    }

    // Savings affect net worth and savings module
    if (path.startsWith('savings')) {
        modules.push('netWorth', 'savings');
    }

    // Investments affect net worth and investment module
    if (path.startsWith('investment')) {
        modules.push('netWorth', 'investment', 'estate');
    }

    // Pensions affect retirement and net worth
    if (path.match(/^(dc_pensions|db_pensions|state_pension)/)) {
        modules.push('retirement', 'netWorth', 'estate');
    }

    // Protection policies
    if (path.match(/^(life_insurance|critical_illness|income_protection|disability|sickness)/)) {
        modules.push('protection');
    }

    // Liabilities affect net worth and estate
    if (path.startsWith('liabilities')) {
        modules.push('netWorth', 'estate');
    }

    // IHT-specific items
    if (path.match(/^(gifts|trusts|iht_profile)/)) {
        modules.push('estate');
    }

    // User profile changes
    if (path.startsWith('user')) {
        if (path.match(/salary|income/)) {
            modules.push('protection', 'retirement');
        }
        if (path.match(/date_of_birth|age/)) {
            modules.push('protection', 'retirement', 'estate');
        }
    }

    // Expenditure affects emergency fund and protection
    if (path.startsWith('expenditure')) {
        modules.push('savings', 'protection');
    }

    // Family members affect protection needs
    if (path.startsWith('family_members')) {
        modules.push('protection');
    }

    // If no specific module identified, update all main modules
    if (modules.length === 0) {
        modules.push('netWorth');
    }

    return [...new Set(modules)]; // Remove duplicates
}

export default {
    namespaced: true,
    state,
    getters,
    mutations,
    actions,
};
