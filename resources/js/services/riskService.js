import api from './api';
import {
  getRiskClasses,
  getRiskDisplayName,
  normalizeRiskLevel,
} from '@/constants/designSystem';

/**
 * Risk Profile API Service
 * Handles all API calls related to risk preferences and profile management
 */
const riskService = {
  /**
   * Get all available risk levels with their configurations
   * @returns {Promise} Array of risk level configurations
   */
  async getLevels() {
    const response = await api.get('/investment/risk/levels');
    return response.data;
  },

  /**
   * Get the current user's risk profile
   * @returns {Promise} Risk profile data including risk_level, time_horizon_years, etc.
   */
  async getProfile() {
    const response = await api.get('/investment/risk/profile');
    return response.data;
  },

  /**
   * Set or update the user's main risk profile
   * @param {Object} data - Risk profile data
   * @param {string} data.risk_level - The selected risk level (low, lower_medium, medium, upper_medium, high)
   * @param {boolean} [data.is_self_assessed] - Whether this is a self-assessment (default: true)
   * @param {number} [data.time_horizon_years] - Investment time horizon in years
   * @param {number} [data.capacity_for_loss_percent] - Capacity for loss percentage
   * @param {string} [data.knowledge_level] - Investment knowledge level
   * @returns {Promise} Updated risk profile
   */
  async setProfile(data) {
    const response = await api.post('/investment/risk/profile', data);
    return response.data;
  },

  /**
   * Get allowed risk levels for product-level overrides
   * Based on user's main risk level (±1 level constraint)
   * @returns {Promise} Array of allowed risk level values
   */
  async getAllowedLevels() {
    const response = await api.get('/investment/risk/allowed-levels');
    return response.data;
  },

  /**
   * Validate if a specific risk level is allowed for a product
   * @param {string} riskLevel - The risk level to validate
   * @returns {Promise} Validation result with is_valid and message
   */
  async validateProductLevel(riskLevel) {
    const response = await api.post('/investment/risk/validate-product-level', {
      risk_level: riskLevel,
    });
    return response.data;
  },

  /**
   * Get detailed configuration for a specific risk level
   * @param {string} level - The risk level to get config for
   * @returns {Promise} Detailed risk level configuration including allocation and returns
   */
  async getRiskConfig(level) {
    const response = await api.get(`/investment/risk/config/${level}`);
    return response.data;
  },

  /**
   * Recalculate risk profile based on financial factors
   * @returns {Promise} Updated risk profile with factor breakdown
   */
  async recalculate() {
    const response = await api.post('/investment/risk/recalculate');
    return response.data;
  },

  /**
   * Helper: Get risk level display name
   * Delegates to designSystem.js for single source of truth
   * @param {string} level - The risk level value
   * @returns {string} Display name for the risk level
   */
  getDisplayName(level) {
    if (!level) return 'Medium';
    return getRiskDisplayName(level);
  },

  /**
   * Helper: Get risk level color class for Tailwind
   * Delegates to designSystem.js for single source of truth
   * @param {string} level - The risk level value
   * @returns {Object} Color classes for bg, text, and border
   */
  getRiskColor(level) {
    return getRiskClasses(level);
  },

  /**
   * Helper: Normalize legacy risk tolerance to new system
   * Delegates to designSystem.js for single source of truth
   * @param {string} tolerance - Legacy tolerance value (cautious, balanced, adventurous)
   * @returns {string} Normalized risk level
   */
  normalizeLegacyTolerance(tolerance) {
    return normalizeRiskLevel(tolerance);
  },
};

export default riskService;
