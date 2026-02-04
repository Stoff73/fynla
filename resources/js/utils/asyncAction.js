/**
 * Vuex Async Action Helper
 *
 * Reduces boilerplate code in Vuex store actions by providing a standardised
 * pattern for async operations with loading state, error handling, and commits.
 *
 * Usage:
 * ```javascript
 * import { createAsyncAction } from '@/utils/asyncAction';
 *
 * const actions = {
 *   fetchData: createAsyncAction(
 *     (payload) => myService.getData(payload),
 *     'setData'
 *   ),
 *
 *   // With options
 *   fetchWithOptions: createAsyncAction(
 *     (payload) => myService.getData(payload),
 *     'setData',
 *     {
 *       loadingMutation: 'setCustomLoading',
 *       errorMutation: 'setCustomError',
 *       extractData: (response) => response.data.items,
 *       onSuccess: ({ commit, dispatch }, data) => dispatch('refreshRelated'),
 *     }
 *   ),
 * };
 * ```
 */

/**
 * Creates an async Vuex action with standardised loading/error handling.
 *
 * @param {Function} serviceCall - Async function that makes the API call.
 *                                 Receives (payload, context) parameters.
 * @param {string|null} commitMutation - Mutation name to commit response data to.
 *                                       Pass null to skip committing.
 * @param {Object} options - Optional configuration.
 * @param {string} options.loadingMutation - Custom loading mutation name (default: 'setLoading').
 * @param {string} options.errorMutation - Custom error mutation name (default: 'setError').
 * @param {Function} options.extractData - Function to extract data from response (default: (r) => r.data).
 * @param {Function} options.onSuccess - Callback after successful commit, receives ({ commit, dispatch, state }, data).
 * @param {Function} options.onError - Callback after error, receives ({ commit, dispatch }, error).
 * @param {boolean} options.skipLoading - If true, don't set loading state (default: false).
 * @param {boolean} options.rethrowError - If true, rethrow error after handling (default: true).
 * @returns {Function} Vuex action function.
 */
export function createAsyncAction(serviceCall, commitMutation, options = {}) {
    const {
        loadingMutation = 'setLoading',
        errorMutation = 'setError',
        extractData = (response) => response.data,
        onSuccess = null,
        onError = null,
        skipLoading = false,
        rethrowError = true,
    } = options;

    return async function asyncAction({ commit, dispatch, state }, payload) {
        if (!skipLoading) {
            commit(loadingMutation, true);
        }
        commit(errorMutation, null);

        try {
            const response = await serviceCall(payload, { commit, dispatch, state });
            const data = extractData(response);

            if (commitMutation) {
                commit(commitMutation, data);
            }

            if (onSuccess) {
                await onSuccess({ commit, dispatch, state }, data, response);
            }

            return response;
        } catch (error) {
            const errorMessage = error.response?.data?.message || error.message || 'An error occurred';
            commit(errorMutation, errorMessage);

            if (onError) {
                await onError({ commit, dispatch, state }, error);
            }

            if (rethrowError) {
                throw error;
            }

            return null;
        } finally {
            if (!skipLoading) {
                commit(loadingMutation, false);
            }
        }
    };
}

/**
 * Creates an async action that also refreshes related data after success.
 *
 * @param {Function} serviceCall - Async function that makes the API call.
 * @param {string|null} commitMutation - Mutation to commit response to.
 * @param {string|Array<string>} refreshActions - Action(s) to dispatch after success.
 * @param {Object} options - Additional options (same as createAsyncAction).
 * @returns {Function} Vuex action function.
 */
export function createAsyncActionWithRefresh(serviceCall, commitMutation, refreshActions, options = {}) {
    const actionsToRefresh = Array.isArray(refreshActions) ? refreshActions : [refreshActions];

    return createAsyncAction(serviceCall, commitMutation, {
        ...options,
        onSuccess: async ({ commit, dispatch, state }, data, response) => {
            // Run original onSuccess if provided
            if (options.onSuccess) {
                await options.onSuccess({ commit, dispatch, state }, data, response);
            }

            // Dispatch refresh actions
            for (const action of actionsToRefresh) {
                if (action.includes('/')) {
                    // Cross-module action (e.g., 'netWorth/refreshNetWorth')
                    await dispatch(action, null, { root: true });
                } else {
                    await dispatch(action);
                }
            }
        },
    });
}

/**
 * Creates an async action that handles CRUD operations with optimistic updates.
 *
 * @param {Function} serviceCall - Async function that makes the API call.
 * @param {Object} mutations - Object with mutation names for different operations.
 * @param {string} mutations.add - Mutation for adding item (for create).
 * @param {string} mutations.update - Mutation for updating item.
 * @param {string} mutations.remove - Mutation for removing item (for delete).
 * @param {Object} options - Additional options.
 * @returns {Function} Vuex action function.
 */
export function createCrudAction(serviceCall, mutations, options = {}) {
    const { operation = 'update' } = options;

    return createAsyncAction(
        serviceCall,
        mutations[operation] || mutations.update,
        options
    );
}

export default {
    createAsyncAction,
    createAsyncActionWithRefresh,
    createCrudAction,
};
