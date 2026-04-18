import api from './api';

/**
 * Estate Planning Module API Service
 * Handles all API calls related to estate planning, IHT, assets, liabilities, and gifts
 */
const estateService = {
    /**
     * Get all estate data for the authenticated user
     * @returns {Promise} Estate data including assets, liabilities, gifts, and IHT profile
     */
    async getEstateData() {
        const response = await api.get('/estate');
        return response.data;
    },

    /**
     * Analyse estate position and calculate IHT liability
     * @param {Object} data - Analysis parameters
     * @returns {Promise} Analysis results with IHT calculation and recommendations
     */
    async analyzeEstate(data = {}) {
        const response = await api.post('/estate/analyze', data);
        return response.data;
    },

    /**
     * Get estate planning recommendations
     * @returns {Promise} Prioritized recommendations
     */
    async getRecommendations() {
        const response = await api.get('/estate/recommendations');
        return response.data;
    },

    /**
     * Run a what-if scenario
     * @param {Object} scenarioData - Scenario parameters
     * @returns {Promise} Scenario analysis results
     */
    async runScenario(scenarioData) {
        const response = await api.post('/estate/scenarios', scenarioData);
        return response.data;
    },

    /**
     * Calculate IHT liability
     * @param {Object} data - IHT calculation parameters
     * @returns {Promise} IHT calculation breakdown
     */
    async calculateIHT(data) {
        const response = await api.post('/estate/calculate-iht', data);
        return response.data;
    },

    /**
     * Calculate comprehensive IHT planning (covers both single and married couples)
     * @returns {Promise} IHT analysis with gifting strategy, life cover, and mitigation strategies
     */
    async calculateIHTPlanning() {
        // Add cache-busting timestamp to force fresh calculation
        const response = await api.post('/estate/calculate-iht', {
            _timestamp: Date.now()
        });
        return response.data;
    },

    /**
     * Get net worth summary
     * @returns {Promise} Net worth breakdown
     */
    async getNetWorth() {
        const response = await api.get('/estate/net-worth');
        return response.data;
    },

    /**
     * Get cash flow for a specific tax year
     * @param {String} taxYear - Tax year (e.g., '2024/25')
     * @returns {Promise} Cash flow statement
     */
    async getCashFlow(taxYear) {
        const response = await api.get('/estate/cash-flow', {
            params: { taxYear }
        });
        return response.data;
    },

    // IHT Profile Methods
    /**
     * Create or update IHT profile
     * @param {Object} profileData - IHT profile data
     * @returns {Promise} Created/updated profile
     */
    async storeOrUpdateProfile(profileData) {
        const response = await api.post('/estate/profile', profileData);
        return response.data;
    },

    // Asset Methods
    /**
     * Create a new asset
     * @param {Object} assetData - Asset data
     * @returns {Promise} Created asset
     */
    async createAsset(assetData) {
        const response = await api.post('/estate/assets', assetData);
        return response.data;
    },

    /**
     * Update an asset
     * @param {Number} id - Asset ID
     * @param {Object} assetData - Updated asset data
     * @returns {Promise} Updated asset
     */
    async updateAsset(id, assetData) {
        const response = await api.put(`/estate/assets/${id}`, assetData);
        return response.data;
    },

    /**
     * Delete an asset
     * @param {Number} id - Asset ID
     * @returns {Promise} Deletion confirmation
     */
    async deleteAsset(id) {
        const response = await api.delete(`/estate/assets/${id}`);
        return response.data;
    },

    // Liability Methods
    /**
     * Create a new liability
     * @param {Object} liabilityData - Liability data
     * @returns {Promise} Created liability
     */
    async createLiability(liabilityData) {
        const response = await api.post('/estate/liabilities', liabilityData);
        return response.data;
    },

    /**
     * Update a liability
     * @param {Number} id - Liability ID
     * @param {Object} liabilityData - Updated liability data
     * @returns {Promise} Updated liability
     */
    async updateLiability(id, liabilityData) {
        const response = await api.put(`/estate/liabilities/${id}`, liabilityData);
        return response.data;
    },

    /**
     * Delete a liability
     * @param {Number} id - Liability ID
     * @returns {Promise} Deletion confirmation
     */
    async deleteLiability(id) {
        const response = await api.delete(`/estate/liabilities/${id}`);
        return response.data;
    },

    // Gift Methods
    /**
     * Create a new gift record
     * @param {Object} giftData - Gift data
     * @returns {Promise} Created gift
     */
    async createGift(giftData) {
        const response = await api.post('/estate/gifts', giftData);
        return response.data;
    },

    /**
     * Update a gift record
     * @param {Number} id - Gift ID
     * @param {Object} giftData - Updated gift data
     * @returns {Promise} Updated gift
     */
    async updateGift(id, giftData) {
        const response = await api.put(`/estate/gifts/${id}`, giftData);
        return response.data;
    },

    /**
     * Delete a gift record
     * @param {Number} id - Gift ID
     * @returns {Promise} Deletion confirmation
     */
    async deleteGift(id) {
        const response = await api.delete(`/estate/gifts/${id}`);
        return response.data;
    },

    /**
     * Get planned gifting strategy based on life expectancy
     * @returns {Promise} Planned gifting strategy with PET cycles and timeline
     */
    async getPlannedGiftingStrategy() {
        const response = await api.get('/estate/gifts/planned-strategy');
        return response.data;
    },

    /**
     * Get personalized asset-based gifting strategy
     * Analyses user's actual assets and their liquidity to provide tailored gifting recommendations
     * @returns {Promise} Personalized gifting strategy with asset-specific guidance
     */
    async getPersonalizedGiftingStrategy() {
        const response = await api.get('/estate/gifts/personalized-strategy');
        return response.data;
    },

    /**
     * Get personalized trust planning strategy with CLT taxation
     * Analyses user's assets for trust planning with proper CLT taxation rules:
     * - 20% lifetime charge on amounts exceeding £325,000 NRB
     * - Additional charge to 40% if death within 7 years (with taper relief)
     * - 7-year rolling window for cumulative CLTs
     * @returns {Promise} Personalized trust strategy with CLT scenarios and taxation
     */
    async getPersonalizedTrustStrategy() {
        const response = await api.get('/estate/gifts/trust-strategy');
        return response.data;
    },

    /**
     * Get life policy strategy (Whole of Life vs. Self-Insurance)
     * @returns {Promise} Life policy strategy comparison with premiums and future value calculations
     */
    async getLifePolicyStrategy() {
        const response = await api.get('/estate/life-policy-strategy');
        return response.data;
    },

    // ==================== TRUSTS ====================

    /**
     * Get all trusts for the authenticated user
     * @returns {Promise} List of trusts
     */
    async getTrusts() {
        const response = await api.get('/estate/trusts');
        return response.data;
    },

    /**
     * Create a new trust
     * @param {Object} trustData - Trust data
     * @returns {Promise} Created trust
     */
    async createTrust(trustData) {
        const response = await api.post('/estate/trusts', trustData);
        return response.data;
    },

    /**
     * Update an existing trust
     * @param {Number} id - Trust ID
     * @param {Object} trustData - Updated trust data
     * @returns {Promise} Updated trust
     */
    async updateTrust(id, trustData) {
        const response = await api.put(`/estate/trusts/${id}`, trustData);
        return response.data;
    },

    /**
     * Delete a trust
     * @param {Number} id - Trust ID
     * @returns {Promise} Deletion confirmation
     */
    async deleteTrust(id) {
        const response = await api.delete(`/estate/trusts/${id}`);
        return response.data;
    },

    /**
     * Get trust analysis
     * @param {Number} id - Trust ID
     * @returns {Promise} Trust analysis and efficiency metrics
     */
    async analyzeTrust(id) {
        const response = await api.get(`/estate/trusts/${id}/analyze`);
        return response.data;
    },

    /**
     * Get trust recommendations
     * @param {Object} params - Parameters (has_children, needs_flexibility)
     * @returns {Promise} Trust recommendations based on estate
     */
    async getTrustRecommendations(params = {}) {
        const response = await api.get('/estate/trust-recommendations', { params });
        return response.data;
    },

    /**
     * Calculate discounted gift trust discount estimate
     * @param {Object} data - Age, gift value, annual income
     * @returns {Promise} Discount calculation
     */
    async calculateDiscountedGiftDiscount(data) {
        const response = await api.post('/estate/calculate-discount', data);
        return response.data;
    },

    /**
     * Save or update will information
     * @param {Object} willData - Will data (has_will, will_last_updated, executor_name)
     * @returns {Promise} Saved will
     */
    async saveWill(willData) {
        const response = await api.post('/estate/will', willData);
        return response.data;
    },

    // ==================== LASTING POWERS OF ATTORNEY ====================

    async getLpas() {
        const response = await api.get('/estate/lpa');
        return response.data;
    },

    async getLpa(id) {
        const response = await api.get(`/estate/lpa/${id}`);
        return response.data;
    },

    async createLpa(data) {
        const response = await api.post('/estate/lpa', data);
        return response.data;
    },

    async updateLpa(id, data) {
        const response = await api.put(`/estate/lpa/${id}`, data);
        return response.data;
    },

    async deleteLpa(id) {
        const response = await api.delete(`/estate/lpa/${id}`);
        return response.data;
    },

    async uploadLpa(formData) {
        const response = await api.post('/estate/lpa/upload', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        return response.data;
    },

    async getLpaCompliance(id) {
        const response = await api.get(`/estate/lpa/${id}/compliance`);
        return response.data;
    },

    async markLpaRegistered(id, data = {}) {
        const response = await api.post(`/estate/lpa/${id}/register`, data);
        return response.data;
    },

    async getLpaDonorDefaults() {
        const response = await api.get('/estate/lpa/donor-defaults');
        return response.data;
    },

    // Will Builder
    async getWillBuilderPrePopulate() {
        const response = await api.get('/estate/will-builder/pre-populate');
        return response.data;
    },

    async getWillBuilderDraft() {
        const response = await api.get('/estate/will-builder');
        return response.data;
    },

    async createWillDocument(data) {
        const response = await api.post('/estate/will-builder', data);
        return response.data;
    },

    async getWillDocument(id) {
        const response = await api.get(`/estate/will-builder/${id}`);
        return response.data;
    },

    async updateWillDocument(id, data) {
        const response = await api.put(`/estate/will-builder/${id}`, data);
        return response.data;
    },

    async completeWillDocument(id) {
        const response = await api.post(`/estate/will-builder/${id}/complete`);
        return response.data;
    },

    async generateMirrorWill(id) {
        const response = await api.post(`/estate/will-builder/${id}/mirror`);
        return response.data;
    },

    async validateWillDocument(id) {
        const response = await api.get(`/estate/will-builder/${id}/validate`);
        return response.data;
    },

    async deleteWillDocument(id) {
        const response = await api.delete(`/estate/will-builder/${id}`);
        return response.data;
    },
};

export default estateService;
