import api from './api';

/**
 * Investment Module API Service
 * Handles all API calls related to investment accounts, holdings, portfolio analysis, and Monte Carlo simulations
 */
const investmentService = {
    /**
     * Get all investment data for the authenticated user
     * @returns {Promise} Investment data including accounts, holdings, goals, and risk profile
     */
    async getInvestmentData() {
        const response = await api.get('/investment');
        return response.data;
    },

    /**
     * Run comprehensive portfolio analysis
     * @returns {Promise} Analysis results with recommendations
     */
    async analyzeInvestment() {
        const response = await api.post('/investment/analyze');
        return response.data;
    },

    /**
     * Get investment recommendations
     * @returns {Promise} Prioritized recommendations
     */
    async getRecommendations() {
        const response = await api.get('/investment/recommendations');
        return response.data;
    },

    /**
     * Build what-if scenarios
     * @param {Object} scenarioData - Scenario parameters (monthly_contribution, etc.)
     * @returns {Promise} Scenario analysis results
     */
    async runScenario(scenarioData) {
        const response = await api.post('/investment/scenarios', scenarioData);
        return response.data;
    },

    /**
     * Start Monte Carlo simulation
     * @param {Object} params - Simulation parameters
     * @param {Number} params.start_value - Starting portfolio value
     * @param {Number} params.monthly_contribution - Monthly contribution amount
     * @param {Number} params.expected_return - Expected annual return (0-0.5)
     * @param {Number} params.volatility - Volatility (0-1)
     * @param {Number} params.years - Number of years to project
     * @param {Number} params.iterations - Number of simulations (100-10000)
     * @param {Number} params.goal_amount - Optional goal amount for probability calculation
     * @returns {Promise} Job ID for polling
     */
    async startMonteCarlo(params) {
        const response = await api.post('/investment/monte-carlo', params);
        return response.data;
    },

    /**
     * Get Monte Carlo simulation results
     * @param {String} jobId - Job ID from startMonteCarlo
     * @returns {Promise} Simulation results or status
     */
    async getMonteCarloResults(jobId) {
        const response = await api.get(`/investment/monte-carlo/${jobId}`);
        return response.data;
    },

    // Investment Account Methods
    /**
     * Create a new investment account
     * @param {Object} accountData - Account data
     * @param {String} accountData.account_type - Account type (isa, gia, etc.)
     * @param {String} accountData.provider - Provider name
     * @param {String} accountData.platform - Platform name
     * @param {Number} accountData.current_value - Current account value
     * @param {Number} accountData.contributions_ytd - Contributions year to date
     * @param {String} accountData.tax_year - Tax year
     * @param {Number} accountData.platform_fee_percent - Platform fee percentage
     * @returns {Promise} Created account
     */
    async createAccount(accountData) {
        try {
            const response = await api.post('/investment/accounts', accountData);
            return response.data;
        } catch (error) {
            console.error('Account creation failed:', error.response?.data);
            throw error;
        }
    },

    /**
     * Update an investment account
     * @param {Number} id - Account ID
     * @param {Object} accountData - Updated account data
     * @returns {Promise} Updated account
     */
    async updateAccount(id, accountData) {
        const response = await api.put(`/investment/accounts/${id}`, accountData);
        return response.data;
    },

    /**
     * Delete an investment account
     * @param {Number} id - Account ID
     * @returns {Promise} Deletion confirmation
     */
    async deleteAccount(id) {
        const response = await api.delete(`/investment/accounts/${id}`);
        return response.data;
    },

    /**
     * Toggle include_in_retirement flag for an investment account
     * @param {Number} id - Account ID
     * @returns {Promise} Updated account with new include_in_retirement value
     */
    async toggleRetirementInclusion(id) {
        const response = await api.patch(`/investment/accounts/${id}/toggle-retirement`);
        return response.data;
    },

    // Holdings Methods
    /**
     * Create a new holding
     * @param {Object} holdingData - Holding data
     * @param {Number} holdingData.investment_account_id - Account ID
     * @param {String} holdingData.asset_type - Asset type (equity, bond, etc.)
     * @param {String} holdingData.security_name - Security name
     * @param {String} holdingData.ticker - Ticker symbol
     * @param {Number} holdingData.quantity - Quantity held
     * @param {Number} holdingData.purchase_price - Purchase price per unit
     * @param {String} holdingData.purchase_date - Purchase date
     * @param {Number} holdingData.current_price - Current price per unit
     * @param {Number} holdingData.current_value - Current total value
     * @param {Number} holdingData.cost_basis - Cost basis
     * @param {Number} holdingData.ocf_percent - Ongoing charges figure percentage
     * @returns {Promise} Created holding
     */
    async createHolding(holdingData) {
        try {
            const response = await api.post('/investment/holdings', holdingData);
            return response.data;
        } catch (error) {
            console.error('Holding creation failed:', error.response?.data);
            throw error;
        }
    },

    /**
     * Update a holding
     * @param {Number} id - Holding ID
     * @param {Object} holdingData - Updated holding data
     * @returns {Promise} Updated holding
     */
    async updateHolding(id, holdingData) {
        const response = await api.put(`/investment/holdings/${id}`, holdingData);
        return response.data;
    },

    /**
     * Delete a holding
     * @param {Number} id - Holding ID
     * @returns {Promise} Deletion confirmation
     */
    async deleteHolding(id) {
        const response = await api.delete(`/investment/holdings/${id}`);
        return response.data;
    },

    // Risk Profile Methods
    /**
     * Create or update risk profile
     * @param {Object} profileData - Risk profile data
     * @param {String} profileData.risk_tolerance - Risk tolerance (cautious, balanced, adventurous)
     * @param {Number} profileData.capacity_for_loss_percent - Capacity for loss percentage
     * @param {Number} profileData.time_horizon_years - Time horizon in years
     * @param {String} profileData.knowledge_level - Knowledge level (novice, intermediate, experienced)
     * @returns {Promise} Risk profile
     */
    async saveRiskProfile(profileData) {
        const response = await api.post('/investment/risk-profile', profileData);
        return response.data;
    },

    // Tax Optimization Methods
    /**
     * Get comprehensive tax optimization analysis
     * @param {Object} params - Optional parameters
     * @param {String} params.tax_year - Tax year (e.g., '2024/25')
     * @returns {Promise} Complete tax analysis with opportunities and efficiency score
     */
    async analyzeTaxPosition(params = {}) {
        const response = await api.get('/investment/tax-optimization/analyze', { params });
        return response.data;
    },

    /**
     * Get ISA allowance optimization strategy
     * @param {Object} params - Optional parameters
     * @param {Number} params.available_funds - Available funds for ISA contribution
     * @param {Number} params.monthly_contribution - Monthly contribution amount
     * @param {Number} params.expected_return - Expected annual return (0-1)
     * @param {Number} params.dividend_yield - Expected dividend yield (0-1)
     * @param {Number} params.tax_rate - Tax rate (0-1)
     * @returns {Promise} ISA strategy with recommendations
     */
    async getISAStrategy(params = {}) {
        const response = await api.get('/investment/tax-optimization/isa-strategy', { params });
        return response.data;
    },

    /**
     * Get CGT tax-loss harvesting opportunities
     * @param {Object} params - Optional parameters
     * @param {Number} params.cgt_allowance - CGT annual allowance
     * @param {Number} params.expected_gains - Expected realised gains this tax year
     * @param {Number} params.tax_rate - CGT tax rate (0-1)
     * @param {Number} params.loss_carryforward - Existing loss carryforward
     * @returns {Promise} Loss harvesting opportunities and strategy
     */
    async getCGTHarvestingOpportunities(params = {}) {
        const response = await api.get('/investment/tax-optimization/cgt-harvesting', { params });
        return response.data;
    },

    /**
     * Get Bed and ISA transfer opportunities
     * @param {Object} params - Optional parameters
     * @param {Number} params.cgt_allowance - CGT annual allowance
     * @param {Number} params.isa_allowance_remaining - Remaining ISA allowance
     * @param {Number} params.tax_rate - CGT tax rate (0-1)
     * @returns {Promise} Bed and ISA opportunities and execution plan
     */
    async getBedAndISAOpportunities(params = {}) {
        const response = await api.get('/investment/tax-optimization/bed-and-isa', { params });
        return response.data;
    },

    /**
     * Get tax optimization recommendations
     * @param {Object} params - Optional filters
     * @param {String} params.priority - Filter by priority (high, medium, low)
     * @param {String} params.type - Filter by type (isa, cgt, bed_and_isa, dividend)
     * @returns {Promise} Filtered recommendations
     */
    async getTaxRecommendations(params = {}) {
        const response = await api.get('/investment/tax-optimization/recommendations', { params });
        return response.data;
    },

    // Asset Location Methods
    /**
     * Get comprehensive asset location analysis
     * @param {Object} params - Optional parameters
     * @param {Number} params.isa_allowance_used - ISA allowance already used this tax year
     * @param {Number} params.cgt_allowance_used - CGT allowance already used
     * @param {Number} params.expected_return - Expected annual return (0-1)
     * @param {Boolean} params.prefer_pension - Prefer pension over ISA recommendations
     * @returns {Promise} Complete asset location analysis
     */
    async analyzeAssetLocation(params = {}) {
        const response = await api.get('/investment/asset-location/analyze', { params });
        return response.data;
    },

    // ===================================================================
    // Phase 2: Advanced Investment Planning
    // ===================================================================

    // =========================================================================
    // Portfolio Strategy Methods
    // =========================================================================

    /**
     * Get comprehensive portfolio strategy recommendations
     * Aggregates recommendations from tax, fee, and rebalancing services
     * GET /api/investment/portfolio-strategy
     * @returns {Promise} Strategy recommendations with summary and per-account breakdown
     */
    async getPortfolioStrategy() {
        const response = await api.get('/investment/portfolio-strategy');
        return response.data;
    },

    /**
     * Get portfolio projections for the Performance tab.
     * POST /api/investment/projections
     * @param {Object} params - projection_periods, selected_period, contribution_overrides
     * @returns {Promise} Projection data with growth scenarios
     */
    async getPortfolioProjections(params = {}) {
        const response = await api.post('/investment/projections', params);
        return response.data;
    },

};

export default investmentService;
