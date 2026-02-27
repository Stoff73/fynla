import api from './api';

const holisticService = {
    /**
     * Perform holistic analysis across all modules
     */
    async analyzeHolistic() {
        const response = await api.post('/holistic/analyze');
        return response.data;
    },

    /**
     * Generate complete holistic plan
     */
    async getPlan() {
        const response = await api.post('/holistic/plan');
        return response.data;
    },

    /**
     * Get all ranked recommendations
     */
    async getRecommendations() {
        const response = await api.get('/holistic/recommendations');
        return response.data;
    },

    /**
     * Get cashflow analysis
     */
    async getCashFlowAnalysis() {
        const response = await api.get('/holistic/cash-flow-analysis');
        return response.data;
    },

    /**
     * Mark recommendation as done
     * @param {number} id - Recommendation ID
     */
    async markRecommendationDone(id) {
        const response = await api.post(`/holistic/recommendations/${id}/mark-done`);
        return response.data;
    },

    /**
     * Mark recommendation as in progress
     * @param {number} id - Recommendation ID
     */
    async markRecommendationInProgress(id) {
        const response = await api.post(`/holistic/recommendations/${id}/in-progress`);
        return response.data;
    },

    /**
     * Dismiss recommendation
     * @param {number} id - Recommendation ID
     */
    async dismissRecommendation(id) {
        const response = await api.post(`/holistic/recommendations/${id}/dismiss`);
        return response.data;
    },

    /**
     * Get completed recommendations
     */
    async getCompletedRecommendations() {
        const response = await api.get('/holistic/recommendations/completed');
        return response.data;
    },

    /**
     * Update recommendation notes
     * @param {number} id - Recommendation ID
     * @param {string} notes - Notes text
     */
    async updateRecommendationNotes(id, notes) {
        const response = await api.patch(`/holistic/recommendations/${id}/notes`, { notes });
        return response.data;
    },
};

export default holisticService;
