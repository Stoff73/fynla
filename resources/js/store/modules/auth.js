import authService from '@/services/authService';
import { removeToken, isNativePlatform } from '@/services/tokenStorage';

import logger from '@/utils/logger';
const state = {
  token: authService.getToken(),
  user: null, // NEVER cache user in state - always fetch fresh from API
  role: null,
  permissions: [],
  loading: false,
  error: null,
};

const getters = {
  isAuthenticated: (state) => !!state.token,
  currentUser: (state) => state.user,
  user: (state) => state.user, // Alias for currentUser
  isAdmin: (state) => state.role === 'admin' || state.user?.is_admin === true,
  isSupport: (state) => state.role === 'support',
  isAdvisor: (state) => state.user?.is_advisor === true,
  role: (state) => state.role,
  permissions: (state) => state.permissions,
  hasPermission: (state) => (perm) => state.permissions.includes(perm),
  loading: (state) => state.loading,
  error: (state) => state.error,
};

const actions = {
  async register({ commit, dispatch, rootState }, userData) {
    commit('setLoading', true);
    commit('setError', null);

    // Check if in preview mode BEFORE clearing auth
    const wasInPreviewMode = rootState.auth?.user?.is_preview_user === true;

    // Clear any existing auth state to prevent data leakage
    commit('clearAuth');

    // CRITICAL: Reset ALL module states to prevent data leakage between users
    commit('userProfile/resetState', null, { root: true });
    commit('lifeStage/resetState', null, { root: true });
    commit('onboarding/RESET_STATE', null, { root: true });
    dispatch('netWorth/resetState', null, { root: true }).catch(() => {});
    dispatch('aiChat/reset', null, { root: true }).catch(() => {});

    // ALWAYS clear the stored token to prevent previous user's session leaking
    await removeToken();

    try {
      const response = await authService.register(userData);
      // Store ONLY the token
      commit('setToken', response.data.access_token);

      // Fetch user data fresh from API (not from registration response)
      await dispatch('fetchUser');

      return response;
    } catch (error) {
      const errorMessage = error.message || 'Registration failed';
      commit('setError', errorMessage);
      throw error;
    } finally {
      commit('setLoading', false);
    }
  },

  async login({ commit, dispatch, rootState }, credentials) {
    commit('setLoading', true);
    commit('setError', null);

    // Check if in preview mode BEFORE clearing auth
    const wasInPreviewMode = rootState.auth?.user?.is_preview_user === true;

    // Clear any existing auth state to prevent data leakage
    commit('clearAuth');

    // CRITICAL: Reset ALL module states to prevent data leakage between users
    commit('userProfile/resetState', null, { root: true });
    commit('lifeStage/resetState', null, { root: true });
    commit('onboarding/RESET_STATE', null, { root: true });
    dispatch('netWorth/resetState', null, { root: true }).catch(() => {});
    dispatch('aiChat/reset', null, { root: true }).catch(() => {});

    // ALWAYS clear the stored token to prevent previous user's session leaking
    await removeToken();

    try {
      const response = await authService.login(credentials);
      // Store ONLY the token
      commit('setToken', response.data.access_token);

      // Fetch user data fresh from API (not from login response)
      await dispatch('fetchUser');

      return response;
    } catch (error) {
      const errorMessage = error.message || 'Login failed';
      commit('setError', errorMessage);
      throw error;
    } finally {
      commit('setLoading', false);
    }
  },

  async logout({ commit, dispatch }) {
    commit('setLoading', true);

    try {
      await authService.logout();
      commit('clearAuth');

      // Reset all module states on logout to prevent data leakage
      commit('userProfile/resetState', null, { root: true });
      dispatch('netWorth/resetState', null, { root: true }).catch(() => {});
      dispatch('taxConfig/clear', null, { root: true }).catch(() => {});
      dispatch('aiChat/reset', null, { root: true }).catch(() => {});
    } catch (error) {
      logger.error('Logout error:', error);
      commit('clearAuth');
    } finally {
      commit('setLoading', false);
    }
  },

  /**
   * Mobile-specific logout: clears local state but does NOT revoke the
   * server token. This keeps the biometric credential (stored in iOS
   * Keychain) valid so Face ID can auto-login on next launch.
   */
  async mobileLogout({ commit, dispatch }) {
    commit('setLoading', true);
    try {
      // Clear local token storage (Preferences) but skip server revocation
      await removeToken();
      commit('clearAuth');

      // Reset module states to prevent data leakage
      commit('userProfile/resetState', null, { root: true });
      dispatch('netWorth/resetState', null, { root: true }).catch(() => {});
      dispatch('mobileDashboard/clearCache', null, { root: true }).catch(() => {});
      dispatch('aiChat/reset', null, { root: true }).catch(() => {});
    } finally {
      commit('setLoading', false);
    }
  },

  async fetchUser({ commit, dispatch, state }) {
    commit('setLoading', true);
    commit('setError', null);

    try {
      const data = await authService.getUser();
      commit('setUser', data.user);
      commit('setRole', data.role);
      commit('setPermissions', data.permissions || []);

      // Always sync life stage from the authenticated user's data.
      // This ensures stale state from a previous user is cleared on login,
      // while preserving the correct stage for returning users.
      commit('lifeStage/setCurrentStage', data.user?.life_stage || null, { root: true });
      commit('lifeStage/setDataCompletedSteps', data.data_completed_steps || [], { root: true });

      // Load the active tax year so every allowance/tax-year label across
      // the app reflects the admin-selected year (not the calendar year).
      dispatch('taxConfig/fetchActive', null, { root: true }).catch(() => {});

      return data.user;
    } catch (error) {
      const errorMessage = error.message || 'Failed to fetch user';
      commit('setError', errorMessage);
      // Only clear auth if we don't have a valid token
      // This prevents logout on transient network errors during normal operations
      if (!state.token) {
        commit('clearAuth');
      }
      throw error;
    } finally {
      commit('setLoading', false);
    }
  },

  async fetchUserById({ commit }, userId) {
    try {
      const user = await authService.getUserById(userId);
      return user;
    } catch (error) {
      logger.error('Failed to fetch user by ID:', error);
      throw error;
    }
  },
};

const mutations = {
  setToken(state, token) {
    state.token = token;
  },

  setUser(state, user) {
    state.user = user;
  },

  setRole(state, role) {
    state.role = role;
  },

  setPermissions(state, permissions) {
    state.permissions = permissions;
  },

  clearAuth(state) {
    state.token = null;
    state.user = null;
    state.role = null;
    state.permissions = [];
  },

  setLoading(state, loading) {
    state.loading = loading;
  },

  setError(state, error) {
    state.error = error;
  },
};

export default {
  namespaced: true,
  state,
  getters,
  actions,
  mutations,
};
