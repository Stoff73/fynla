import spousePermissionService from '@/services/spousePermissionService';

const state = {
  hasSpouse: false,
  spouse: null,
  permission: null,
  canViewSpouseData: false,
  requiresAccountLink: false,
  message: '',
  loading: false,
  error: null,
};

const getters = {
  /**
   * Check if user has an active spouse relationship.
   * Divorced users revert to non-spouse state (Expression of Wishes).
   * Widowed users keep spouse state (Letter to Spouse remains).
   */
  hasSpouse: (state, getters, rootState, rootGetters) => {
    if (!state.hasSpouse) return false;

    const user = rootGetters['auth/currentUser'];
    if (user && user.marital_status === 'divorced') {
      return false;
    }

    return state.hasSpouse;
  },
  spouse: (state) => state.spouse,
  permission: (state) => state.permission,
  canViewSpouseData: (state) => state.canViewSpouseData,
  permissionStatus: (state) => state.permission?.status || null,
  isPending: (state) => state.permission?.status === 'pending',
  isAccepted: (state) => state.permission?.status === 'accepted',
  isRejected: (state) => state.permission?.status === 'rejected',
  loading: (state) => state.loading,
  error: (state) => state.error,
};

const mutations = {
  setPermissionStatus(state, data) {
    state.hasSpouse = data.has_spouse;
    state.spouse = data.spouse || null;
    state.permission = data.permission || null;
    state.canViewSpouseData = data.can_view_spouse_data || false;
    state.requiresAccountLink = data.requires_account_link || false;
    state.message = data.message || '';
  },

  setLoading(state, loading) {
    state.loading = loading;
  },

  setError(state, error) {
    state.error = error;
  },

  clearError(state) {
    state.error = null;
  },
};

const actions = {
  /**
   * Fetch current permission status
   */
  async fetchPermissionStatus({ commit }) {
    commit('setLoading', true);
    commit('setError', null);

    try {
      const response = await spousePermissionService.getPermissionStatus();

      if (response.success) {
        commit('setPermissionStatus', response.data);
      }

      return response;
    } catch (error) {
      const errorMessage = error.response?.data?.message || error.message || 'Failed to fetch permission status';
      commit('setError', errorMessage);
      throw error;
    } finally {
      commit('setLoading', false);
    }
  },

  /**
   * Request permission from spouse
   */
  async requestPermission({ commit, dispatch }) {
    commit('setLoading', true);
    commit('setError', null);

    try {
      const response = await spousePermissionService.requestPermission();

      if (response.success) {
        // Refresh permission status
        await dispatch('fetchPermissionStatus');
      }

      return response;
    } catch (error) {
      const errorMessage = error.response?.data?.message || error.message || 'Failed to request permission';
      commit('setError', errorMessage);
      throw error;
    } finally {
      commit('setLoading', false);
    }
  },

  /**
   * Accept permission request
   */
  async acceptPermission({ commit, dispatch }) {
    commit('setLoading', true);
    commit('setError', null);

    try {
      const response = await spousePermissionService.acceptPermission();

      if (response.success) {
        // Refresh permission status
        await dispatch('fetchPermissionStatus');
      }

      return response;
    } catch (error) {
      const errorMessage = error.response?.data?.message || error.message || 'Failed to accept permission';
      commit('setError', errorMessage);
      throw error;
    } finally {
      commit('setLoading', false);
    }
  },

  /**
   * Reject permission request
   */
  async rejectPermission({ commit, dispatch }) {
    commit('setLoading', true);
    commit('setError', null);

    try {
      const response = await spousePermissionService.rejectPermission();

      if (response.success) {
        // Refresh permission status
        await dispatch('fetchPermissionStatus');
      }

      return response;
    } catch (error) {
      const errorMessage = error.response?.data?.message || error.message || 'Failed to reject permission';
      commit('setError', errorMessage);
      throw error;
    } finally {
      commit('setLoading', false);
    }
  },

  /**
   * Revoke permission
   */
  async revokePermission({ commit, dispatch }) {
    commit('setLoading', true);
    commit('setError', null);

    try {
      const response = await spousePermissionService.revokePermission();

      if (response.success) {
        // Refresh permission status
        await dispatch('fetchPermissionStatus');
      }

      return response;
    } catch (error) {
      const errorMessage = error.response?.data?.message || error.message || 'Failed to revoke permission';
      commit('setError', errorMessage);
      throw error;
    } finally {
      commit('setLoading', false);
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
