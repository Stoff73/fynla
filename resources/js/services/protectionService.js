import api from './api';

/**
 * Protection Module API Service
 * Handles all API calls related to protection insurance policies and analysis
 */
const protectionService = {
    /**
     * Get all protection data for the authenticated user
     * @returns {Promise} Protection data including profile, policies, and analysis
     */
    async getProtectionData() {
        const response = await api.get('/protection');
        return response.data;
    },

    /**
     * Save or update protection profile
     * @param {Object} profileData - Protection profile data
     * @returns {Promise} Saved profile
     */
    async saveProfile(profileData) {
        const response = await api.post('/protection/profile', profileData);
        return response.data;
    },

    /**
     * Update the has_no_policies flag
     * @param {Boolean} hasNoPolicies - Whether user has no policies
     * @returns {Promise} Updated profile
     */
    async updateHasNoPolicies(hasNoPolicies) {
        const response = await api.patch('/protection/profile/has-no-policies', {
            has_no_policies: hasNoPolicies,
        });
        return response.data;
    },

    /**
     * Analyse protection coverage and gaps
     * @param {Object} data - Analysis parameters
     * @returns {Promise} Analysis results with gaps and recommendations
     */
    async analyzeProtection(data) {
        const response = await api.post('/protection/analyze', data);
        return response.data;
    },

    /**
     * Get protection recommendations
     * @returns {Promise} Prioritized recommendations
     */
    async getRecommendations() {
        const response = await api.get('/protection/recommendations');
        return response.data;
    },

    /**
     * Run a what-if scenario
     * @param {Object} scenarioData - Scenario parameters
     * @returns {Promise} Scenario analysis results
     */
    async runScenario(scenarioData) {
        const response = await api.post('/protection/scenarios', scenarioData);
        return response.data;
    },

    // Life Insurance Policy Methods
    /**
     * Create a new life insurance policy
     * @param {Object} policyData - Life insurance policy data
     * @returns {Promise} Created policy
     */
    async createLifePolicy(policyData) {
        const response = await api.post('/protection/policies/life', policyData);
        return response.data;
    },

    /**
     * Update a life insurance policy
     * @param {Number} id - Policy ID
     * @param {Object} policyData - Updated policy data
     * @returns {Promise} Updated policy
     */
    async updateLifePolicy(id, policyData) {
        const response = await api.put(`/protection/policies/life/${id}`, policyData);
        return response.data;
    },

    /**
     * Delete a life insurance policy
     * @param {Number} id - Policy ID
     * @returns {Promise} Deletion confirmation
     */
    async deleteLifePolicy(id) {
        const response = await api.delete(`/protection/policies/life/${id}`);
        return response.data;
    },

    // Critical Illness Policy Methods
    /**
     * Create a new critical illness policy
     * @param {Object} policyData - Critical illness policy data
     * @returns {Promise} Created policy
     */
    async createCriticalIllnessPolicy(policyData) {
        const response = await api.post('/protection/policies/critical-illness', policyData);
        return response.data;
    },

    /**
     * Update a critical illness policy
     * @param {Number} id - Policy ID
     * @param {Object} policyData - Updated policy data
     * @returns {Promise} Updated policy
     */
    async updateCriticalIllnessPolicy(id, policyData) {
        const response = await api.put(`/protection/policies/critical-illness/${id}`, policyData);
        return response.data;
    },

    /**
     * Delete a critical illness policy
     * @param {Number} id - Policy ID
     * @returns {Promise} Deletion confirmation
     */
    async deleteCriticalIllnessPolicy(id) {
        const response = await api.delete(`/protection/policies/critical-illness/${id}`);
        return response.data;
    },

    // Income Protection Policy Methods
    /**
     * Create a new income protection policy
     * @param {Object} policyData - Income protection policy data
     * @returns {Promise} Created policy
     */
    async createIncomeProtectionPolicy(policyData) {
        const response = await api.post('/protection/policies/income-protection', policyData);
        return response.data;
    },

    /**
     * Update an income protection policy
     * @param {Number} id - Policy ID
     * @param {Object} policyData - Updated policy data
     * @returns {Promise} Updated policy
     */
    async updateIncomeProtectionPolicy(id, policyData) {
        const response = await api.put(`/protection/policies/income-protection/${id}`, policyData);
        return response.data;
    },

    /**
     * Delete an income protection policy
     * @param {Number} id - Policy ID
     * @returns {Promise} Deletion confirmation
     */
    async deleteIncomeProtectionPolicy(id) {
        const response = await api.delete(`/protection/policies/income-protection/${id}`);
        return response.data;
    },

    // Disability Policy Methods
    /**
     * Create a new disability policy
     * @param {Object} policyData - Disability policy data
     * @returns {Promise} Created policy
     */
    async createDisabilityPolicy(policyData) {
        const response = await api.post('/protection/policies/disability', policyData);
        return response.data;
    },

    /**
     * Update a disability policy
     * @param {Number} id - Policy ID
     * @param {Object} policyData - Updated policy data
     * @returns {Promise} Updated policy
     */
    async updateDisabilityPolicy(id, policyData) {
        const response = await api.put(`/protection/policies/disability/${id}`, policyData);
        return response.data;
    },

    /**
     * Delete a disability policy
     * @param {Number} id - Policy ID
     * @returns {Promise} Deletion confirmation
     */
    async deleteDisabilityPolicy(id) {
        const response = await api.delete(`/protection/policies/disability/${id}`);
        return response.data;
    },

    // Sickness/Illness Policy Methods
    /**
     * Create a new sickness/illness policy
     * @param {Object} policyData - Sickness/illness policy data
     * @returns {Promise} Created policy
     */
    async createSicknessIllnessPolicy(policyData) {
        const response = await api.post('/protection/policies/sickness-illness', policyData);
        return response.data;
    },

    /**
     * Update a sickness/illness policy
     * @param {Number} id - Policy ID
     * @param {Object} policyData - Updated policy data
     * @returns {Promise} Updated policy
     */
    async updateSicknessIllnessPolicy(id, policyData) {
        const response = await api.put(`/protection/policies/sickness-illness/${id}`, policyData);
        return response.data;
    },

    /**
     * Delete a sickness/illness policy
     * @param {Number} id - Policy ID
     * @returns {Promise} Deletion confirmation
     */
    async deleteSicknessIllnessPolicy(id) {
        const response = await api.delete(`/protection/policies/sickness-illness/${id}`);
        return response.data;
    },
};

export default protectionService;
