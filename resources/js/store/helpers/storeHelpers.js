/**
 * Vuex Store Helper Utilities
 *
 * Provides common patterns used across multiple Vuex store modules to reduce code duplication.
 * These utilities handle:
 * - Loading state management
 * - Error handling
 * - CRUD mutations
 * - Preview mode management
 */

/**
 * Creates standard state properties for a Vuex module
 *
 * @param {Object} additionalState - Module-specific state properties
 * @returns {Object} Combined state object with common properties
 */
export function createBaseState(additionalState = {}) {
    return {
        loading: false,
        error: null,
        isPreviewMode: false,
        previewData: null,
        ...additionalState,
    };
}

/**
 * Creates standard getters for loading and error state
 *
 * @param {Object} additionalGetters - Module-specific getters
 * @returns {Object} Combined getters object
 */
export function createBaseGetters(additionalGetters = {}) {
    return {
        loading: (state) => state.loading,
        error: (state) => state.error,
        isPreviewMode: (state) => state.isPreviewMode,
        previewData: (state) => state.previewData,
        ...additionalGetters,
    };
}

/**
 * Creates standard mutations for loading, error, and preview mode
 *
 * @param {Object} additionalMutations - Module-specific mutations
 * @returns {Object} Combined mutations object
 */
export function createBaseMutations(additionalMutations = {}) {
    return {
        setLoading(state, loading) {
            state.loading = loading;
        },

        setError(state, error) {
            state.error = error;
        },

        SET_PREVIEW_MODE(state, { isPreview, data }) {
            state.isPreviewMode = isPreview;
            state.previewData = data;
        },

        ...additionalMutations,
    };
}

/**
 * Creates CRUD mutations for a collection
 *
 * @param {string} collectionName - Name of the state property (e.g., 'accounts', 'policies')
 * @param {string} idField - Name of the ID field (default: 'id')
 * @returns {Object} Object with add, update, remove, and set mutations
 */
export function createCrudMutations(collectionName, idField = 'id') {
    const capitalizedName = collectionName.charAt(0).toUpperCase() + collectionName.slice(1);

    return {
        [`set${capitalizedName}`](state, items) {
            state[collectionName] = items;
        },

        [`add${capitalizedName.slice(0, -1)}`](state, item) {
            // Handle singular form (e.g., 'accounts' -> 'addAccount')
            state[collectionName].push(item);
        },

        [`update${capitalizedName.slice(0, -1)}`](state, item) {
            const index = state[collectionName].findIndex((i) => i[idField] === item[idField]);
            if (index !== -1) {
                state[collectionName].splice(index, 1, item);
            }
        },

        [`remove${capitalizedName.slice(0, -1)}`](state, id) {
            const index = state[collectionName].findIndex((i) => i[idField] === id);
            if (index !== -1) {
                state[collectionName].splice(index, 1);
            }
        },
    };
}

/**
 * Wraps an async action with loading state management and error handling
 *
 * @param {Function} commit - Vuex commit function
 * @param {Function} asyncFn - Async function to execute
 * @param {Object} options - Optional configuration
 * @param {string} options.errorMessage - Custom error message prefix
 * @param {boolean} options.throwError - Whether to re-throw the error (default: true)
 * @returns {Promise<any>} Result of the async function
 */
export async function withLoading(commit, asyncFn, options = {}) {
    const { errorMessage = 'Operation failed', throwError = true } = options;

    commit('setLoading', true);
    commit('setError', null);

    try {
        const result = await asyncFn();
        return result;
    } catch (error) {
        const message = error.response?.data?.message || error.message || errorMessage;
        commit('setError', message);
        console.error(`${errorMessage}:`, error);

        if (throwError) {
            throw error;
        }
        return null;
    } finally {
        commit('setLoading', false);
    }
}

/**
 * Creates a standard async action with loading state management
 *
 * @param {Function} serviceFn - Service function that returns a promise
 * @param {Object} options - Configuration options
 * @param {string} options.errorMessage - Error message to display on failure
 * @param {Function} options.onSuccess - Callback function on success (receives response)
 * @param {boolean} options.skipIfPreviewMode - Skip API call if in preview mode
 * @returns {Function} Vuex action function
 */
export function createAsyncAction(serviceFn, options = {}) {
    const {
        errorMessage = 'Operation failed',
        onSuccess = null,
        skipIfPreviewMode = false,
    } = options;

    return async ({ commit, state }, payload) => {
        // Skip API call if in preview mode
        if (skipIfPreviewMode && state.isPreviewMode) {
            console.log(`[store] Skipping action - preview mode active`);
            return;
        }

        return withLoading(
            commit,
            async () => {
                const response = await serviceFn(payload);

                if (onSuccess) {
                    onSuccess(commit, response, payload);
                }

                return response;
            },
            { errorMessage }
        );
    };
}

/**
 * Extracts data from API response, handling both wrapped and unwrapped formats
 *
 * @param {Object} response - API response object
 * @param {string} dataKey - Optional key to extract from response data
 * @returns {any} Extracted data
 */
export function extractResponseData(response, dataKey = null) {
    const data = response.data || response;

    if (dataKey && data[dataKey] !== undefined) {
        return data[dataKey];
    }

    return data;
}

/**
 * Format currency for display
 *
 * @param {number|string} value - Value to format
 * @param {Object} options - Intl.NumberFormat options
 * @returns {string} Formatted currency string
 */
export function formatCurrency(value, options = {}) {
    if (value === null || value === undefined) return '0';

    const defaultOptions = {
        style: 'currency',
        currency: 'GBP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    };

    return new Intl.NumberFormat('en-GB', { ...defaultOptions, ...options }).format(value);
}

/**
 * Calculate total from a collection of items
 *
 * @param {Array} items - Array of items
 * @param {string} field - Field name to sum
 * @param {Function} transform - Optional transform function for each value
 * @returns {number} Total sum
 */
export function calculateTotal(items, field, transform = null) {
    if (!Array.isArray(items)) return 0;

    return items.reduce((sum, item) => {
        let value = parseFloat(item[field] || 0);

        if (transform) {
            value = transform(value, item);
        }

        return sum + value;
    }, 0);
}

export default {
    createBaseState,
    createBaseGetters,
    createBaseMutations,
    createCrudMutations,
    withLoading,
    createAsyncAction,
    extractResponseData,
    formatCurrency,
    calculateTotal,
};
