import api from './api';

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
   * @param {string} [data.attitude_to_volatility] - Attitude towards volatility
   * @param {boolean} [data.esg_preference] - ESG investment preference
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
   * @param {string} level - The risk level value
   * @returns {string} Display name for the risk level
   */
  getDisplayName(level) {
    if (!level) return 'Medium';
    const names = {
      low: 'Low',
      lower_medium: 'Lower-Medium',
      medium: 'Medium',
      upper_medium: 'Upper-Medium',
      high: 'High',
      // Legacy values
      cautious: 'Cautious',
      balanced: 'Balanced',
      adventurous: 'Adventurous',
    };
    return names[level] || 'Medium';
  },

  /**
   * Helper: Get risk level color class for Tailwind
   * @param {string} level - The risk level value
   * @returns {Object} Color classes for bg and text
   */
  getRiskColor(level) {
    const colors = {
      low: { bg: 'bg-yellow-100', text: 'text-yellow-800', border: 'border-yellow-200' },
      lower_medium: { bg: 'bg-pink-100', text: 'text-pink-800', border: 'border-pink-200' },
      medium: { bg: 'bg-green-100', text: 'text-green-800', border: 'border-green-200' },
      upper_medium: { bg: 'bg-teal-100', text: 'text-teal-800', border: 'border-teal-200' },
      high: { bg: 'bg-blue-100', text: 'text-blue-800', border: 'border-blue-200' },
    };
    return colors[level] || { bg: 'bg-gray-100', text: 'text-gray-800', border: 'border-gray-200' };
  },

  /**
   * Helper: Normalize legacy risk tolerance to new system
   * @param {string} tolerance - Legacy tolerance value (cautious, balanced, adventurous)
   * @returns {string} Normalized risk level
   */
  normalizeLegacyTolerance(tolerance) {
    const mapping = {
      cautious: 'lower_medium',
      balanced: 'medium',
      adventurous: 'upper_medium',
    };
    return mapping[tolerance] || tolerance;
  },
};

export default riskService;
