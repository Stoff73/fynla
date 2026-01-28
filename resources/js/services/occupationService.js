import api from './api';

/**
 * Service for searching ONS SOC 2020 occupation codes.
 */
export default {
  /**
   * Search for occupations matching the query.
   * Requires minimum 3 characters.
   *
   * @param {string} query - Search term
   * @returns {Promise<Array>} Array of occupation objects
   */
  async search(query) {
    if (!query || query.length < 3) {
      return [];
    }

    try {
      const response = await api.get('/occupations/search', {
        params: { q: query },
      });

      return response.data.data || [];
    } catch (error) {
      console.error('Occupation search failed:', error);
      return [];
    }
  },
};
