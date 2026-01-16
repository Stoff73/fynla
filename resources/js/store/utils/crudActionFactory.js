/**
 * Factory for creating standardized Vuex CRUD actions.
 *
 * Reduces boilerplate in store modules by providing consistent
 * create, update, and delete action patterns.
 */

/**
 * Create standardized CRUD actions for a Vuex store module.
 *
 * @param {Object} service - The API service object with CRUD methods
 * @param {string} entityName - Name of the entity (e.g., 'Account', 'Policy')
 * @param {Object} mutations - Mutation names for add, update, remove operations
 * @param {string} mutations.add - Mutation name for adding an item
 * @param {string} mutations.update - Mutation name for updating an item
 * @param {string} mutations.remove - Mutation name for removing an item
 * @returns {Object} Object containing create, update, and delete actions
 *
 * @example
 * // In your store module:
 * import { createCRUDActions } from '@/store/utils/crudActionFactory';
 * import savingsService from '@/services/savingsService';
 *
 * const crudActions = createCRUDActions(
 *   savingsService,
 *   'Account',
 *   { add: 'ADD_ACCOUNT', update: 'UPDATE_ACCOUNT', remove: 'REMOVE_ACCOUNT' }
 * );
 *
 * export default {
 *   actions: {
 *     ...crudActions,
 *     // other actions
 *   }
 * };
 */
export function createCRUDActions(service, entityName, mutations) {
  const methodNames = {
    create: `create${entityName}`,
    update: `update${entityName}`,
    delete: `delete${entityName}`,
  };

  return {
    /**
     * Create a new entity.
     */
    async [`create${entityName}`]({ commit }, data) {
      commit('SET_LOADING', true);
      try {
        const response = await service[methodNames.create](data);
        const item = response.data?.data || response.data || response;
        if (mutations.add) {
          commit(mutations.add, item);
        }
        return item;
      } catch (error) {
        commit('SET_ERROR', error.response?.data?.message || error.message);
        throw error;
      } finally {
        commit('SET_LOADING', false);
      }
    },

    /**
     * Update an existing entity.
     */
    async [`update${entityName}`]({ commit }, { id, data }) {
      commit('SET_LOADING', true);
      try {
        const response = await service[methodNames.update](id, data);
        const item = response.data?.data || response.data || response;
        if (mutations.update) {
          commit(mutations.update, item);
        }
        return item;
      } catch (error) {
        commit('SET_ERROR', error.response?.data?.message || error.message);
        throw error;
      } finally {
        commit('SET_LOADING', false);
      }
    },

    /**
     * Delete an entity.
     */
    async [`delete${entityName}`]({ commit }, id) {
      commit('SET_LOADING', true);
      try {
        await service[methodNames.delete](id);
        if (mutations.remove) {
          commit(mutations.remove, id);
        }
        return true;
      } catch (error) {
        commit('SET_ERROR', error.response?.data?.message || error.message);
        throw error;
      } finally {
        commit('SET_LOADING', false);
      }
    },
  };
}

/**
 * Create loading and error state mutations.
 *
 * @returns {Object} Standard loading/error mutations
 */
export function createStateMutations() {
  return {
    SET_LOADING(state, loading) {
      state.loading = loading;
    },
    SET_ERROR(state, error) {
      state.error = error;
    },
    CLEAR_ERROR(state) {
      state.error = null;
    },
  };
}

/**
 * Create initial state with loading and error properties.
 *
 * @param {Object} additionalState - Additional state properties
 * @returns {Object} State object with loading and error
 */
export function createInitialState(additionalState = {}) {
  return {
    loading: false,
    error: null,
    ...additionalState,
  };
}
