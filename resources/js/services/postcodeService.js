import api from './api';

/**
 * UK Postcode Lookup Service
 *
 * Provides address lookup functionality using our Laravel proxy
 * which calls the GetAddress.io API server-side.
 */
export default {
  /**
   * UK postcode regex pattern
   * Matches formats like: SW1A 1AA, SW1A1AA, sw1a 1aa
   */
  POSTCODE_PATTERN: /^([A-Z]{1,2}[0-9][0-9A-Z]?\s?[0-9][A-Z]{2})$/i,

  /**
   * Look up addresses for a UK postcode
   *
   * @param {string} postcode - UK postcode to look up
   * @returns {Promise<{success: boolean, addresses: Array, postcode: string, error?: string, message?: string}>}
   */
  async findAddresses(postcode) {
    try {
      // Normalise before sending to API
      const normalised = this.normalise(postcode);

      if (!this.isValidFormat(normalised)) {
        return {
          success: false,
          error: 'invalid_format',
          message: 'Please enter a valid UK postcode (e.g., SW1A 1AA)',
          addresses: [],
        };
      }

      const response = await api.get(`/postcode-lookup/${encodeURIComponent(normalised)}`);
      return response.data;
    } catch (error) {
      // Handle specific error responses from our API
      if (error.response?.data) {
        return error.response.data;
      }

      // Graceful degradation for network/other errors
      return {
        success: false,
        error: 'service_error',
        message: 'Address lookup unavailable. Please enter address manually.',
        addresses: [],
      };
    }
  },

  /**
   * Check if a postcode matches valid UK format
   *
   * @param {string} postcode - Postcode to validate
   * @returns {boolean}
   */
  isValidFormat(postcode) {
    if (!postcode || typeof postcode !== 'string') {
      return false;
    }
    return this.POSTCODE_PATTERN.test(postcode.trim());
  },

  /**
   * Normalise a postcode to uppercase with proper spacing
   *
   * @param {string} postcode - Postcode to normalise
   * @returns {string} Normalised postcode (e.g., "SW1A 1AA")
   */
  normalise(postcode) {
    if (!postcode || typeof postcode !== 'string') {
      return '';
    }

    // Remove all spaces and convert to uppercase
    let normalised = postcode.toUpperCase().replace(/\s+/g, '');

    // Insert space before last 3 characters (outward code)
    if (normalised.length >= 5) {
      normalised = normalised.slice(0, -3) + ' ' + normalised.slice(-3);
    }

    return normalised;
  },
};
