import api from './api';

const API_BASE = '/household';

export default {
    /**
     * Get household net worth breakdown
     */
    async getNetWorth() {
        const response = await api.get(`${API_BASE}/net-worth`);
        return response.data;
    },

    /**
     * Get spousal optimisation recommendations
     */
    async getOptimisations() {
        const response = await api.get(`${API_BASE}/optimisations`);
        return response.data;
    },

    /**
     * Get death-of-spouse scenario analysis
     * @param {string} spouse - 'primary' or 'partner'
     */
    async getDeathScenario(spouse = 'primary') {
        const response = await api.get(`${API_BASE}/death-scenario`, {
            params: { spouse }
        });
        return response.data;
    },
};
