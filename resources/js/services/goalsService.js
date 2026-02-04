import api from './api';

const goalsService = {
    /**
     * Get all goals for the authenticated user.
     */
    async getGoals(filters = {}) {
        const params = new URLSearchParams();
        if (filters.module) params.append('module', filters.module);
        if (filters.status) params.append('status', filters.status);
        if (filters.priority) params.append('priority', filters.priority);

        const response = await api.get(`/goals?${params.toString()}`);
        return response.data;
    },

    /**
     * Get comprehensive goals analysis.
     */
    async getAnalysis() {
        const response = await api.get('/goals/analysis');
        return response.data;
    },

    /**
     * Get dashboard overview for goals card.
     */
    async getDashboardOverview() {
        const response = await api.get('/goals/dashboard-overview');
        return response.data;
    },

    /**
     * Get available goal types.
     */
    async getGoalTypes() {
        const response = await api.get('/goals/types');
        return response.data;
    },

    /**
     * Get available risk levels.
     */
    async getRiskLevels() {
        const response = await api.get('/goals/risk-levels');
        return response.data;
    },

    /**
     * Create a new goal.
     */
    async createGoal(goalData) {
        const response = await api.post('/goals', goalData);
        return response.data;
    },

    /**
     * Get a specific goal by ID.
     */
    async getGoal(goalId) {
        const response = await api.get(`/goals/${goalId}`);
        return response.data;
    },

    /**
     * Update a goal.
     */
    async updateGoal(goalId, goalData) {
        const response = await api.put(`/goals/${goalId}`, goalData);
        return response.data;
    },

    /**
     * Delete a goal.
     */
    async deleteGoal(goalId) {
        const response = await api.delete(`/goals/${goalId}`);
        return response.data;
    },

    /**
     * Record a contribution to a goal.
     */
    async recordContribution(goalId, contributionData) {
        const response = await api.post(`/goals/${goalId}/contribution`, contributionData);
        return response.data;
    },

    /**
     * Get projections for a goal.
     */
    async getProjections(goalId) {
        const response = await api.get(`/goals/${goalId}/projections`);
        return response.data;
    },

    /**
     * Get scenarios for a goal.
     */
    async getScenarios(goalId) {
        const response = await api.get(`/goals/${goalId}/scenarios`);
        return response.data;
    },

    /**
     * Get contribution history for a goal.
     */
    async getContributionHistory(goalId, limit = 12) {
        const response = await api.get(`/goals/${goalId}/contributions?limit=${limit}`);
        return response.data;
    },

    /**
     * Calculate property purchase costs.
     */
    async calculatePropertyCosts(propertyData) {
        const response = await api.post('/goals/calculate-property-costs', propertyData);
        return response.data;
    },

    // =========================================
    // Life Events API
    // =========================================

    /**
     * Get all life events for the user.
     */
    async getLifeEvents(filters = {}) {
        const params = new URLSearchParams();
        if (filters.household) params.append('household', 'true');
        if (filters.impact_type) params.append('impact_type', filters.impact_type);
        if (filters.status) params.append('status', filters.status);

        const queryString = params.toString();
        const response = await api.get(`/life-events${queryString ? `?${queryString}` : ''}`);
        return response.data;
    },

    /**
     * Get available event types.
     */
    async getEventTypes() {
        const response = await api.get('/life-events/types');
        return response.data;
    },

    /**
     * Create a new life event.
     */
    async createLifeEvent(eventData) {
        const response = await api.post('/life-events', eventData);
        return response.data;
    },

    /**
     * Get a specific life event by ID.
     */
    async getLifeEvent(eventId) {
        const response = await api.get(`/life-events/${eventId}`);
        return response.data;
    },

    /**
     * Update a life event.
     */
    async updateLifeEvent(eventId, eventData) {
        const response = await api.put(`/life-events/${eventId}`, eventData);
        return response.data;
    },

    /**
     * Delete a life event.
     */
    async deleteLifeEvent(eventId) {
        const response = await api.delete(`/life-events/${eventId}`);
        return response.data;
    },

    /**
     * Mark a life event as completed.
     */
    async markLifeEventCompleted(eventId) {
        const response = await api.post(`/life-events/${eventId}/complete`);
        return response.data;
    },

    /**
     * Get life events grouped by age.
     */
    async getLifeEventsByAge(filters = {}) {
        const params = new URLSearchParams();
        if (filters.household) params.append('household', 'true');

        const queryString = params.toString();
        const response = await api.get(`/life-events/by-age${queryString ? `?${queryString}` : ''}`);
        return response.data;
    },

    // =========================================
    // Projection API
    // =========================================

    /**
     * Get financial projection data.
     */
    async getProjection(options = {}) {
        const params = new URLSearchParams();
        if (options.household) params.append('household', 'true');

        const queryString = params.toString();
        const response = await api.get(`/goals/projection${queryString ? `?${queryString}` : ''}`);
        return response.data;
    },

    /**
     * Get household summary data.
     */
    async getHouseholdSummary() {
        const response = await api.get('/goals/household-summary');
        return response.data;
    },
};

export default goalsService;
