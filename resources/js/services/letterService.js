import api from './api';

const letterService = {
  /**
   * Get the letter to spouse / expression of wishes
   * @returns {Promise}
   */
  async getLetter() {
    const response = await api.get('/user/letter-to-spouse');
    return response.data;
  },

  /**
   * Save/update the letter to spouse / expression of wishes
   * @param {Object} data - Letter form data
   * @returns {Promise}
   */
  async saveLetter(data) {
    const response = await api.put('/user/letter-to-spouse', data);
    return response.data;
  },

  /**
   * Get basic will data (executor info, will status)
   * @returns {Promise}
   */
  async getWillData() {
    const response = await api.get('/estate/will');
    return response.data;
  },
};

export default letterService;
