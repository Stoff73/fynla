import api from './api';

/**
 * Diversification Analysis API Service
 * Handles API calls for diversification analysis of investment accounts and DC pensions
 */
const diversificationService = {
    /**
     * Get diversification analysis for an investment account
     * @param {Number} accountId - The investment account ID
     * @returns {Promise} Diversification analysis data
     */
    async getAccountDiversification(accountId) {
        const response = await api.get(`/investment/accounts/${accountId}/diversification`);
        return response.data;
    },

    /**
     * Get diversification analysis for a DC pension
     * @param {Number} pensionId - The DC pension ID
     * @returns {Promise} Diversification analysis data
     */
    async getPensionDiversification(pensionId) {
        const response = await api.get(`/retirement/pensions/dc/${pensionId}/diversification`);
        return response.data;
    },
};

export default diversificationService;
