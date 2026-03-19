import api from './api';

const API_BASE_URL = '/api/dashboard';

/**
 * Dashboard Service
 *
 * Handles API calls for dashboard aggregated data
 */
const dashboardService = {
    /**
     * Get dashboard overview data from all modules
     * @returns {Promise} Aggregated dashboard data
     */
    async getDashboardData() {
        try {
            const response = await api.get(API_BASE_URL);
            return response.data;
        } catch (error) {
            console.error('Failed to fetch dashboard data:', error);
            throw error;
        }
    },

    /**
     * Get alerts from all modules, prioritized by severity
     * @returns {Promise} Array of alerts
     */
    async getAlerts() {
        try {
            const response = await api.get(`${API_BASE_URL}/alerts`);
            return response.data;
        } catch (error) {
            console.error('Failed to fetch alerts:', error);
            throw error;
        }
    },

    /**
     * Dismiss an alert
     * @param {number} alertId - ID of the alert to dismiss
     * @returns {Promise} Success response
     */
    async dismissAlert(alertId) {
        try {
            const response = await api.post(`${API_BASE_URL}/alerts/${alertId}/dismiss`);
            return response.data;
        } catch (error) {
            console.error('Failed to dismiss alert:', error);
            throw error;
        }
    },

    /**
     * Fetch all dashboard data in parallel using Promise.allSettled
     * Handles partial failures gracefully
     * @returns {Promise} Object with results from all endpoints
     */
    async fetchAllDashboardData() {
        try {
            const promises = [
                this.getDashboardData(),
                this.getAlerts(),
            ];

            const results = await Promise.allSettled(promises);

            return {
                overview: results[0].status === 'fulfilled' ? results[0].value : null,
                alerts: results[1].status === 'fulfilled' ? results[1].value : null,
                errors: {
                    overview: results[0].status === 'rejected' ? results[0].reason : null,
                    alerts: results[1].status === 'rejected' ? results[1].reason : null,
                },
            };
        } catch (error) {
            console.error('Failed to fetch all dashboard data:', error);
            throw error;
        }
    },
};

export default dashboardService;
