import api from './api';

const API_BASE = '/net-worth';

export default {
    /**
     * Get net worth overview
     */
    async getOverview() {
        const response = await api.get(`${API_BASE}/overview`);
        return response.data;
    },

    /**
     * Get assets summary
     */
    async getAssetsSummary() {
        const response = await api.get(`${API_BASE}/assets-summary`);
        return response.data;
    },

    /**
     * Get assets summary with detailed individual account lists
     * Used for the Net Worth Overview cards
     */
    async getAssetsSummaryDetailed() {
        const response = await api.get(`${API_BASE}/assets-summary-detailed`);
        return response.data;
    },

    /**
     * Get joint assets
     */
    async getJointAssets() {
        const response = await api.get(`${API_BASE}/joint-assets`);
        return response.data;
    },

    /**
     * Refresh net worth (bypass cache)
     */
    async refresh() {
        const response = await api.post(`${API_BASE}/refresh`);
        return response.data;
    }
};
