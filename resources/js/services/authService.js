import api from './api';
import { getTokenSync, setToken as storageSetToken, removeToken } from './tokenStorage';

const authService = {
  /**
   * Register a new user
   * @param {Object} userData - User registration data
   * @returns {Promise}
   */
  async register(userData) {
    // Clear any existing auth data to prevent data leakage between users
    await this.clearAuth();

    const response = await api.post('/auth/register', userData);
    if (response.data.success && response.data.data.access_token) {
      // ONLY store token - user data will be fetched fresh from API
      await this.setToken(response.data.data.access_token);
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
    await this.clearAuth();

    const response = await api.post('/auth/login', credentials);
    if (response.data.success && response.data.data.access_token) {
      // ONLY store token - user data will be fetched fresh from API
      await this.setToken(response.data.data.access_token);
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
      await this.clearAuth();
    }
  },

  /**
   * Get current authenticated user
   * @returns {Promise}
   */
  async getUser() {
    const response = await api.get('/auth/user');
    if (response.data.success) {
      // Return full data object (user, role, permissions) but DO NOT cache in localStorage
      return response.data.data;
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
   * Set authentication token in storage
   * @param {string} token
   */
  async setToken(token) {
    await storageSetToken(token);
  },

  /**
   * Get authentication token from storage (synchronous for backward compatibility)
   * @returns {string|null}
   */
  getToken() {
    return getTokenSync();
  },

  /**
   * Clear all authentication data from token storage and localStorage
   * Comprehensive cleanup to prevent any data leakage between sessions
   */
  async clearAuth() {
    // Clear token via tokenStorage abstraction layer (handles web + native)
    await removeToken();

    // Clear any legacy localStorage data (non-auth keys)
    localStorage.removeItem('user');

    // Clear ALL user-specific localStorage keys (financial data, accounts, etc.)
    const keysToRemove = [];
    for (let i = 0; i < localStorage.length; i++) {
      const key = localStorage.key(i);
      if (key && (
        key.includes('_user_') ||
        key.includes('personalAccounts') ||
        key.includes('spouseAccounts') ||
        key.includes('_data')
      )) {
        keysToRemove.push(key);
      }
    }
    keysToRemove.forEach(key => localStorage.removeItem(key));
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
   * @param {string} challengeToken - Challenge token from login response
   * @param {string} code - 6-digit verification code
   * @param {string} type - 'login' or 'registration'
   * @returns {Promise}
   */
  async verifyCode(challengeToken, code, type) {
    const response = await api.post('/auth/verify-code', {
      challenge_token: challengeToken,
      code,
      type,
    });
    return response.data;
  },

  /**
   * Resend verification code
   * @param {string} challengeToken - Challenge token from login response
   * @param {string} type - 'login' or 'registration'
   * @returns {Promise}
   */
  async resendCode(challengeToken, type) {
    const response = await api.post('/auth/resend-code', {
      challenge_token: challengeToken,
      type,
    });
    return response.data;
  },

  /**
   * Request password reset
   * @param {string} email - User email address
   * @returns {Promise}
   */
  async requestPasswordReset(email) {
    const response = await api.post('/auth/password-reset/request', { email });
    return response.data;
  },

  /**
   * Verify password reset email code
   * @param {string} token - Reset session token
   * @param {string} code - 6-digit verification code
   * @returns {Promise}
   */
  async verifyPasswordResetEmail(token, code) {
    const response = await api.post('/auth/password-reset/verify-email', {
      token,
      code,
    });
    return response.data;
  },

  /**
   * Resend password reset code
   * @param {string} token - Reset session token
   * @returns {Promise}
   */
  async resendPasswordResetCode(token) {
    const response = await api.post('/auth/password-reset/resend-code', {
      token,
    });
    return response.data;
  },

  /**
   * Verify password reset MFA code
   * @param {string} token - Reset session token
   * @param {string} code - 6-digit MFA code
   * @returns {Promise}
   */
  async verifyPasswordResetMfa(token, code) {
    const response = await api.post('/auth/password-reset/verify-mfa', {
      token,
      code,
    });
    return response.data;
  },

  /**
   * Use recovery code for password reset MFA
   * @param {string} token - Reset session token
   * @param {string} recoveryCode - Recovery code
   * @returns {Promise}
   */
  async usePasswordResetRecoveryCode(token, recoveryCode) {
    const response = await api.post('/auth/password-reset/mfa-recovery', {
      token,
      recovery_code: recoveryCode,
    });
    return response.data;
  },

  /**
   * Reset password
   * @param {string} token - Reset session token
   * @param {string} password - New password
   * @param {string} passwordConfirmation - Password confirmation
   * @returns {Promise}
   */
  async resetPassword(token, password, passwordConfirmation) {
    const response = await api.post('/auth/password-reset/reset', {
      token,
      password,
      password_confirmation: passwordConfirmation,
    });
    return response.data;
  },
};

export default authService;
