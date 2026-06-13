import api from './api';

/**
 * Advisor Dashboard API Service
 * Handles all API calls related to advisor client management, activities, and reporting
 */
const advisorService = {
    /**
     * Get advisor dashboard summary stats
     * @returns {Promise} Dashboard statistics
     */
    async getDashboard() {
        const response = await api.get('/advisor/dashboard');
        return response.data;
    },

    /**
     * Get list of advisor's clients with optional filters
     * @param {Object} filters - Filter parameters (status, search, etc.)
     * @returns {Promise} Paginated client list
     */
    async getClients(filters = {}) {
        const response = await api.get('/advisor/clients', { params: filters });
        return response.data;
    },

    /**
     * Get detailed view of a specific client
     * @param {Number} id - Client ID
     * @returns {Promise} Client detail data
     */
    async getClientDetail(id) {
        const response = await api.get(`/advisor/clients/${id}`);
        return response.data;
    },

    /**
     * Enter a client context (impersonation)
     * @param {Number} id - Client ID
     * @returns {Promise} Impersonation session data
     */
    async enterClient(id) {
        const response = await api.post(`/advisor/clients/${id}/enter`);
        return response.data;
    },

    /**
     * Exit client context and return to advisor view
     * @returns {Promise} Exit confirmation
     */
    async exitClient() {
        const response = await api.post('/advisor/exit');
        return response.data;
    },

    /**
     * Get activity log with optional filters
     * @param {Object} filters - Filter parameters (client_id, type, date range, etc.)
     * @returns {Promise} Activity list
     */
    async getActivities(filters = {}) {
        const response = await api.get('/advisor/activities', { params: filters });
        return response.data;
    },

    /**
     * Create a new activity log entry
     * @param {Object} data - Activity data
     * @returns {Promise} Created activity
     */
    async createActivity(data) {
        const response = await api.post('/advisor/activities', data);
        return response.data;
    },

    /**
     * Update an existing activity log entry
     * @param {Number} id - Activity ID
     * @param {Object} data - Updated activity data
     * @returns {Promise} Updated activity
     */
    async updateActivity(id, data) {
        const response = await api.put(`/advisor/activities/${id}`, data);
        return response.data;
    },

    /**
     * Get list of clients with reviews due
     * @returns {Promise} Reviews due list
     */
    async getReviewsDue() {
        const response = await api.get('/advisor/reviews-due');
        return response.data;
    },

    /**
     * Get paginated episodic advice sessions for a client.
     * @param {Number} clientId - Client ID
     * @param {Object} params - Query params (page)
     * @returns {Promise} Paginated episode list
     */
    async getClientEpisodes(clientId, params = {}) {
        const response = await api.get(`/advisor/clients/${clientId}/episodes`, { params });
        return response.data;
    },

    /**
     * Get the full forensic detail (blob body) for a single episode.
     * @param {Number} clientId - Client ID
     * @param {Number} episodeId - Episode (AiMessage) ID
     * @returns {Promise} Episode detail
     */
    async getClientEpisode(clientId, episodeId) {
        const response = await api.get(`/advisor/clients/${clientId}/episodes/${episodeId}`);
        return response.data;
    },

    /**
     * Run an on-demand chain-verify / attestation check on a single episode.
     * @param {Number} clientId - Client ID
     * @param {Number} episodeId - Episode (AiMessage) ID
     * @returns {Promise} Verification result ({ verified, state })
     */
    async verifyClientEpisode(clientId, episodeId) {
        const response = await api.post(`/advisor/clients/${clientId}/episodes/${episodeId}/verify`);
        return response.data;
    },

};

export default advisorService;
