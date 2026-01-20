import authService from '@/services/authService';

const state = {
  token: authService.getToken(),
  user: null, // NEVER cache user in state - always fetch fresh from API
  loading: false,
  error: null,
};

const getters = {
  isAuthenticated: (state) => !!state.token,
  currentUser: (state) => state.user,
  user: (state) => state.user, // Alias for currentUser
  isAdmin: (state) => state.user?.is_admin === true || state.user?.is_admin === 1,
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

    // CRITICAL: Reset userProfile state to prevent data leakage between users
    commit('userProfile/resetState', null, { root: true });

    // If was in preview mode, just clear localStorage (don't redirect via exitPreview)
    if (wasInPreviewMode) {
      localStorage.removeItem('auth_token');
    }

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

    // CRITICAL: Reset userProfile state to prevent data leakage between users
    commit('userProfile/resetState', null, { root: true });

    // If was in preview mode, just clear localStorage (don't redirect via exitPreview)
    if (wasInPreviewMode) {
      localStorage.removeItem('auth_token');
    }

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
    } catch (error) {
      console.error('Logout error:', error);
      commit('clearAuth');
    } finally {
      commit('setLoading', false);
    }
  },

  async fetchUser({ commit, state }) {
    commit('setLoading', true);
    commit('setError', null);

    try {
      const user = await authService.getUser();
      commit('setUser', user);
      return user;
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
      console.error('Failed to fetch user by ID:', error);
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

  clearAuth(state) {
    state.token = null;
    state.user = null;
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
