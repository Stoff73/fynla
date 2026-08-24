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
   * The financial position Part 2 of the letter states — every figure at this
   * user's share, from the same aggregator every other surface reads.
   *
   * The page used to assemble this itself from six module endpoints and total it
   * client-side at 100% of every record, which credited the estate with a
   * co-owner's money and printed it into the exported document.
   *
   * @returns {Promise}
   */
  async getFinancialPosition() {
    const response = await api.get('/user/letter-to-spouse/financial-position');
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
