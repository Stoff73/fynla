import api from './api';

const authService = {
  /**
   * Register a new user
   * @param {Object} userData - User registration data
   * @returns {Promise}
   */
  async register(userData) {
    // Clear any existing auth data to prevent data leakage between users
    this.clearAuth();

    const response = await api.post('/auth/register', userData);
    if (response.data.success && response.data.data.access_token) {
      // ONLY store token - user data will be fetched fresh from API
      this.setToken(response.data.data.access_token);
    }
    return response.data;
  },

  /**
   * Login user
   * @param {Object} credentials - User credentials (email, password)
   * @returns {Promise}
   */
  async login(credentials) {
    // Clear any existing auth data to prevent data leakage between users
    this.clearAuth();

    const response = await api.post('/auth/login', credentials);
    if (response.data.success && response.data.data.access_token) {
      // ONLY store token - user data will be fetched fresh from API
      this.setToken(response.data.data.access_token);
    }
    return response.data;
  },

  /**
   * Logout user
   * @returns {Promise}
   */
  async logout() {
    try {
      await api.post('/auth/logout');
    } catch (error) {
      console.error('Logout API error:', error);
    } finally {
      this.clearAuth();
    }
  },

  /**
   * Get current authenticated user
   * @returns {Promise}
   */
  async getUser() {
    const response = await api.get('/auth/user');
    if (response.data.success) {
      // Return user data but DO NOT cache in localStorage
      return response.data.data.user;
    }
  },

  /**
   * Get user by ID (for viewing spouse/family member data)
   * @param {number} userId - User ID to fetch
   * @returns {Promise}
   */
  async getUserById(userId) {
    const response = await api.get(`/users/${userId}`);
    if (response.data.success) {
      return response.data.data.user;
    }
    return null;
  },

  /**
   * Set authentication token in localStorage
   * @param {string} token
   */
  setToken(token) {
    localStorage.setItem('auth_token', token);
  },

  /**
   * Get authentication token from localStorage
   * @returns {string|null}
   */
  getToken() {
    return localStorage.getItem('auth_token');
  },

  /**
   * Clear authentication data
   */
  clearAuth() {
    localStorage.removeItem('auth_token');
    // Remove any legacy cached user data
    localStorage.removeItem('user');
  },

  /**
   * Check if user is authenticated
   * @returns {boolean}
   */
  isAuthenticated() {
    return !!this.getToken();
  },

  /**
   * Change user password
   * @param {Object} passwordData - { current_password, new_password, new_password_confirmation }
   * @returns {Promise}
   */
  async changePassword(passwordData) {
    const response = await api.post('/auth/change-password', passwordData);
    return response.data;
  },

  /**
   * Verify email code and get auth token
   * @param {number} userId - User ID
   * @param {string} code - 6-digit verification code
   * @param {string} type - 'login' or 'registration'
   * @returns {Promise}
   */
  async verifyCode(userId, code, type) {
    const response = await api.post('/auth/verify-code', {
      user_id: userId,
      code,
      type,
    });
    return response.data;
  },

  /**
   * Resend verification code
   * @param {number} userId - User ID
   * @param {string} type - 'login' or 'registration'
   * @returns {Promise}
   */
  async resendCode(userId, type) {
    const response = await api.post('/auth/resend-code', {
      user_id: userId,
      type,
    });
    return response.data;
  },
};

export default authService;
