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
     * Get tax efficiency score
     * @returns {Promise} Tax efficiency score (0-100) with grade and interpretation
     */
    async getTaxEfficiencyScore() {
        const response = await api.get('/investment/tax-optimization/efficiency-score');
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

    /**
     * Calculate potential tax savings from proposed actions
     * @param {Object} savingsData - Proposed actions
     * @param {Number} savingsData.isa_contribution - ISA contribution amount
     * @param {Number} savingsData.harvest_losses - Losses to harvest
     * @param {Number} savingsData.bed_and_isa_transfer - Bed and ISA transfer amount
     * @param {Number} savingsData.expected_return - Expected annual return (0-1)
     * @param {Number} savingsData.tax_rate - Tax rate (0-1)
     * @returns {Promise} Calculated savings (annual, 5-year, 10-year)
     */
    async calculatePotentialSavings(savingsData) {
        const response = await api.post('/investment/tax-optimization/calculate-savings', savingsData);
        return response.data;
    },

    /**
     * Clear tax optimization caches
     * @returns {Promise} Cache clearing confirmation
     */
    async clearTaxCache() {
        const response = await api.delete('/investment/tax-optimization/clear-cache');
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

    /**
     * Get placement recommendations for all holdings
     * @param {Object} params - Optional filters
     * @param {String} params.priority - Filter by priority (high, medium, low)
     * @param {Number} params.min_saving - Minimum annual saving to include
     * @returns {Promise} Placement recommendations
     */
    async getAssetLocationRecommendations(params = {}) {
        const response = await api.get('/investment/asset-location/recommendations', { params });
        return response.data;
    },

    /**
     * Calculate portfolio tax drag
     * @returns {Promise} Tax drag analysis by account
     */
    async calculatePortfolioTaxDrag() {
        const response = await api.get('/investment/asset-location/tax-drag');
        return response.data;
    },

    /**
     * Get asset location optimization score
     * @returns {Promise} Optimization score (0-100) with grade
     */
    async getAssetLocationScore() {
        const response = await api.get('/investment/asset-location/optimization-score');
        return response.data;
    },

    /**
     * Compare account types for a specific holding
     * @param {Number} holdingId - Holding ID
     * @returns {Promise} Tax comparison across ISA, GIA, Pension
     */
    async compareAccountTypesForHolding(holdingId) {
        const response = await api.post('/investment/asset-location/compare-accounts', {
            holding_id: holdingId,
        });
        return response.data;
    },

    /**
     * Clear asset location caches
     * @returns {Promise} Cache clearing confirmation
     */
    async clearAssetLocationCache() {
        const response = await api.delete('/investment/asset-location/clear-cache');
        return response.data;
    },

    // Performance Attribution Methods
    /**
     * Get comprehensive performance attribution analysis
     * @param {String} period - Period (1y, 3y, 5y, 10y, max)
     * @returns {Promise} Performance attribution by asset class, sector, geography
     */
    async analyzePerformance(period = '1y') {
        const response = await api.get('/investment/performance/analyze', { params: { period } });
        return response.data;
    },

    /**
     * Compare portfolio with benchmark
     * @param {String} benchmark - Benchmark code (ftse_all_share, sp500, etc.)
     * @param {String} period - Period (1y, 3y, 5y, 10y)
     * @returns {Promise} Benchmark comparison with alpha/beta
     */
    async compareWithBenchmark(benchmark = 'ftse_all_share', period = '1y') {
        const response = await api.get('/investment/performance/benchmark', {
            params: { benchmark, period },
        });
        return response.data;
    },

    /**
     * Compare portfolio with multiple benchmarks
     * @param {String} period - Period (1y, 3y, 5y, 10y)
     * @returns {Promise} Multi-benchmark comparison
     */
    async compareWithMultipleBenchmarks(period = '1y') {
        const response = await api.get('/investment/performance/multi-benchmark', { params: { period } });
        return response.data;
    },

    /**
     * Get risk metrics (alpha, beta, Sharpe, Treynor)
     * @returns {Promise} Risk metrics
     */
    async getRiskMetrics() {
        const response = await api.get('/investment/performance/risk-metrics');
        return response.data;
    },

    /**
     * Clear performance caches
     * @returns {Promise} Cache clearing confirmation
     */
    async clearPerformanceCache() {
        const response = await api.delete('/investment/performance/clear-cache');
        return response.data;
    },

    // ===== Goal Progress & Tracking Methods =====

    /**
     * Analyse progress for a specific goal
     * @param {number} goalId - Goal ID
     * @returns {Promise} Goal progress analysis
     */
    async analyzeGoalProgress(goalId) {
        const response = await api.get(`/investment/goals/${goalId}/progress`);
        return response.data;
    },

    /**
     * Analyse progress for all user goals
     * @returns {Promise} All goals progress summary
     */
    async analyzeAllGoals() {
        const response = await api.get('/investment/goals/progress/all');
        return response.data;
    },

    /**
     * Analyse goal shortfall and get mitigation strategies
     * @param {number} goalId - Goal ID
     * @returns {Promise} Shortfall analysis and strategies
     */
    async analyzeShortfall(goalId) {
        const response = await api.get(`/investment/goals/${goalId}/shortfall`);
        return response.data;
    },

    /**
     * Generate what-if scenarios for a goal
     * @param {number} goalId - Goal ID
     * @param {Array} scenarios - Array of scenario parameters (optional)
     * @returns {Promise} What-if analysis results
     */
    async generateWhatIfScenarios(goalId, scenarios = null) {
        const response = await api.post(`/investment/goals/${goalId}/what-if`, {
            scenarios: scenarios,
        });
        return response.data;
    },

    /**
     * Calculate goal success probability using Monte Carlo
     * @param {Object} params - Probability calculation parameters
     * @param {number} params.current_value - Current portfolio value
     * @param {number} params.target_value - Target goal value
     * @param {number} params.monthly_contribution - Monthly contribution
     * @param {number} params.expected_return - Expected annual return (0.06 = 6%)
     * @param {number} params.volatility - Annual volatility (optional, default 0.15)
     * @param {number} params.years_to_goal - Years until goal date
     * @param {number} params.iterations - Simulation iterations (optional, default 1000)
     * @returns {Promise} Probability analysis
     */
    async calculateGoalProbability(params) {
        const response = await api.post('/investment/goals/calculate-probability', params);
        return response.data;
    },

    /**
     * Calculate required monthly contribution for target probability
     * @param {Object} params - Required contribution parameters
     * @param {number} params.current_value - Current portfolio value
     * @param {number} params.target_value - Target goal value
     * @param {number} params.current_contribution - Current monthly contribution
     * @param {number} params.expected_return - Expected annual return
     * @param {number} params.volatility - Annual volatility (optional)
     * @param {number} params.years_to_goal - Years until goal date
     * @param {number} params.target_probability - Target probability (optional, default 0.85)
     * @returns {Promise} Required contribution analysis
     */
    async calculateRequiredContribution(params) {
        const response = await api.post('/investment/goals/required-contribution', params);
        return response.data;
    },

    /**
     * Get glide path recommendation (risk reduction as goal approaches)
     * @param {number} yearsToGoal - Years remaining to goal
     * @param {number} currentEquityPercent - Current equity allocation percentage
     * @returns {Promise} Glide path recommendation
     */
    async getGlidePath(yearsToGoal, currentEquityPercent) {
        const response = await api.get('/investment/goals/glide-path', {
            params: {
                years_to_goal: yearsToGoal,
                current_equity_percent: currentEquityPercent,
            },
        });
        return response.data;
    },

    /**
     * Clear goal progress cache
     * @returns {Promise} Cache clearing confirmation
     */
    async clearGoalProgressCache() {
        const response = await api.delete('/investment/goals/clear-cache');
        return response.data;
    },

    // ===== Fee Impact Analysis Methods =====

    /**
     * Analyse portfolio fees (platform, OCF, transaction costs)
     * @returns {Promise} Fee analysis with breakdown by account
     */
    async analyzePortfolioFees() {
        const response = await api.get('/investment/fees/analyze');
        return response.data;
    },

    /**
     * Analyse fees by holding
     * @returns {Promise} Fee breakdown for each holding
     */
    async analyzeHoldingFees() {
        const response = await api.get('/investment/fees/holdings');
        return response.data;
    },

    /**
     * Calculate OCF impact over time
     * @param {Object} params - OCF calculation parameters
     * @param {number} params.years - Projection years (optional, default 20)
     * @param {number} params.expected_return - Expected return (optional, default 0.06)
     * @returns {Promise} OCF drag analysis with year-by-year projection
     */
    async calculateOCFImpact(params = {}) {
        const response = await api.post('/investment/fees/ocf-impact', params);
        return response.data;
    },

    /**
     * Compare active vs passive fund costs
     * @returns {Promise} Active vs passive comparison with savings potential
     */
    async compareActiveVsPassive() {
        const response = await api.get('/investment/fees/active-vs-passive');
        return response.data;
    },

    /**
     * Find low-cost alternatives for a holding
     * @param {number} holdingId - Holding ID
     * @returns {Promise} Alternative fund suggestions
     */
    async findLowCostAlternatives(holdingId) {
        const response = await api.get(`/investment/fees/alternatives/${holdingId}`);
        return response.data;
    },

    /**
     * Compare investment platforms
     * @param {number} portfolioValue - Portfolio value
     * @param {string} accountType - Account type (isa, sipp, gia) (optional)
     * @param {number} tradesPerYear - Trades per year (optional, default 4)
     * @returns {Promise} Platform comparison
     */
    async comparePlatforms(portfolioValue, accountType = 'isa', tradesPerYear = 4) {
        const response = await api.get('/investment/fees/compare-platforms', {
            params: {
                portfolio_value: portfolioValue,
                account_type: accountType,
                trades_per_year: tradesPerYear,
            },
        });
        return response.data;
    },

    /**
     * Compare specific platforms
     * @param {Array} platforms - Array of platform codes
     * @param {number} portfolioValue - Portfolio value
     * @param {string} accountType - Account type (optional)
     * @param {number} tradesPerYear - Trades per year (optional)
     * @returns {Promise} Specific platform comparison
     */
    async compareSpecificPlatforms(platforms, portfolioValue, accountType = 'isa', tradesPerYear = 4) {
        const response = await api.post('/investment/fees/compare-specific', {
            platforms: platforms,
            portfolio_value: portfolioValue,
            account_type: accountType,
            trades_per_year: tradesPerYear,
        });
        return response.data;
    },

    /**
     * Clear fee analysis cache
     * @returns {Promise} Cache clearing confirmation
     */
    async clearFeeCache() {
        const response = await api.delete('/investment/fees/clear-cache');
        return response.data;
    },

    // ==========================================
    // Model Portfolio Builder (Phase 3.2)
    // ==========================================

    /**
     * Get model portfolio by risk level
     * @param {number} riskLevel - Risk level (1-5)
     * @returns {Promise} Model portfolio with asset allocation and fund recommendations
     */
    async getModelPortfolio(riskLevel) {
        const response = await api.get(`/investment/model-portfolio/${riskLevel}`);
        return response.data;
    },

    /**
     * Get all model portfolios (1-5)
     * @returns {Promise} All 5 model portfolios
     */
    async getAllModelPortfolios() {
        const response = await api.get('/investment/model-portfolio/all');
        return response.data;
    },

    /**
     * Compare current allocation with model portfolio
     * @param {number} riskLevel - Target risk level (1-5)
     * @returns {Promise} Comparison analysis with deviation and recommendations
     */
    async compareWithModelPortfolio(riskLevel) {
        const response = await api.post('/investment/model-portfolio/compare', {
            risk_level: riskLevel,
        });
        return response.data;
    },

    /**
     * Optimise allocation by age
     * @param {number} age - User age
     * @param {string} rule - Age-based rule ('100_minus_age', '110_minus_age', '120_minus_age')
     * @returns {Promise} Optimised asset allocation
     */
    async optimiseAllocationByAge(age, rule = '110_minus_age') {
        const response = await api.get('/investment/model-portfolio/optimize-by-age', {
            params: { age, rule },
        });
        return response.data;
    },

    /**
     * Optimise allocation by time horizon
     * @param {number} years - Years to goal
     * @param {number} targetValue - Target portfolio value
     * @param {number} currentValue - Current portfolio value
     * @returns {Promise} Optimised allocation and required return
     */
    async optimiseAllocationByTimeHorizon(years, targetValue, currentValue) {
        const response = await api.post('/investment/model-portfolio/optimize-by-horizon', {
            years,
            target_value: targetValue,
            current_value: currentValue,
        });
        return response.data;
    },

    /**
     * Get glide path allocation
     * @param {number} yearsToRetirement - Years until retirement
     * @returns {Promise} Lifecycle glide path allocation
     */
    async getGlidePathAllocation(yearsToRetirement) {
        const response = await api.get('/investment/model-portfolio/glide-path', {
            params: { years_to_retirement: yearsToRetirement },
        });
        return response.data;
    },

    /**
     * Get fund recommendations for asset allocation
     * @param {Object} assetAllocation - Asset allocation percentages
     * @param {number} assetAllocation.equities - Equity percentage
     * @param {number} assetAllocation.bonds - Bond percentage
     * @param {number} assetAllocation.cash - Cash percentage
     * @param {number} assetAllocation.alternatives - Alternatives percentage
     * @returns {Promise} Fund recommendations with tickers and OCF
     */
    async getFundRecommendations(assetAllocation) {
        const response = await api.post('/investment/model-portfolio/funds', assetAllocation);
        return response.data;
    },

    // ==========================================
    // Rebalancing Strategies (Phase 3.4)
    // ==========================================

    /**
     * Analyse portfolio drift from target allocation
     * @param {Object} targetAllocation - Target asset allocation percentages
     * @param {number} targetAllocation.equities - Equity percentage
     * @param {number} targetAllocation.bonds - Bond percentage
     * @param {number} targetAllocation.cash - Cash percentage
     * @param {number} targetAllocation.alternatives - Alternatives percentage
     * @param {Array} accountIds - Optional investment account IDs to analyse
     * @returns {Promise} Drift analysis with metrics and urgency
     */
    async analyzeDrift(targetAllocation, accountIds = null) {
        const response = await api.post('/investment/rebalancing/analyse-drift', {
            target_allocation: targetAllocation,
            account_ids: accountIds,
        });
        return response.data;
    },

    /**
     * Evaluate multiple rebalancing strategies
     * @param {Object} targetAllocation - Target allocation
     * @param {Object} options - Strategy options
     * @param {number} options.thresholdPercent - Threshold drift percent
     * @param {number} options.toleranceBandPercent - Tolerance band percent
     * @param {string} options.lastRebalanceDate - Last rebalance date (YYYY-MM-DD)
     * @param {string} options.frequency - Rebalancing frequency
     * @param {Array} options.accountIds - Optional account IDs
     * @returns {Promise} Comparison of all strategies
     */
    async evaluateRebalancingStrategies(targetAllocation, options = {}) {
        const response = await api.post('/investment/rebalancing/evaluate-strategies', {
            target_allocation: targetAllocation,
            threshold_percent: options.thresholdPercent,
            tolerance_band_percent: options.toleranceBandPercent,
            last_rebalance_date: options.lastRebalanceDate,
            frequency: options.frequency,
            account_ids: options.accountIds,
        });
        return response.data;
    },

    /**
     * Evaluate threshold-based rebalancing strategy
     * @param {Object} targetAllocation - Target allocation
     * @param {number} thresholdPercent - Drift threshold percentage
     * @param {Array} accountIds - Optional account IDs
     * @returns {Promise} Threshold strategy evaluation
     */
    async evaluateThresholdStrategy(targetAllocation, thresholdPercent, accountIds = null) {
        const response = await api.post('/investment/rebalancing/threshold-strategy', {
            target_allocation: targetAllocation,
            threshold_percent: thresholdPercent,
            account_ids: accountIds,
        });
        return response.data;
    },

    /**
     * Evaluate calendar-based rebalancing strategy
     * @param {string} lastRebalanceDate - Last rebalance date (YYYY-MM-DD)
     * @param {string} frequency - Frequency (quarterly, semi_annual, annual, biennial)
     * @returns {Promise} Calendar strategy evaluation
     */
    async evaluateCalendarStrategy(lastRebalanceDate, frequency) {
        const response = await api.post('/investment/rebalancing/calendar-strategy', {
            last_rebalance_date: lastRebalanceDate,
            frequency: frequency,
        });
        return response.data;
    },

    /**
     * Evaluate opportunistic rebalancing with cash flow
     * @param {Object} targetAllocation - Target allocation
     * @param {number} newCashFlow - New contribution or withdrawal amount
     * @param {Array} accountIds - Optional account IDs
     * @returns {Promise} Opportunistic strategy evaluation
     */
    async evaluateOpportunisticStrategy(targetAllocation, newCashFlow, accountIds = null) {
        const response = await api.post('/investment/rebalancing/opportunistic-strategy', {
            target_allocation: targetAllocation,
            new_cash_flow: newCashFlow,
            account_ids: accountIds,
        });
        return response.data;
    },

    /**
     * Get recommended rebalancing frequency
     * @param {number} portfolioValue - Current portfolio value
     * @param {number} riskLevel - Risk level (1-5)
     * @param {number} expectedVolatility - Expected portfolio volatility
     * @param {boolean} isTaxable - Whether account is taxable
     * @returns {Promise} Frequency recommendation with rationale
     */
    async recommendRebalancingFrequency(portfolioValue, riskLevel, expectedVolatility, isTaxable = true) {
        const response = await api.post('/investment/rebalancing/recommend-frequency', {
            portfolio_value: portfolioValue,
            risk_level: riskLevel,
            expected_volatility: expectedVolatility,
            is_taxable: isTaxable,
        });
        return response.data;
    },

    // ==========================================
    // Efficient Frontier / MPT (Phase 3.3)
    // ==========================================

    /**
     * Calculate efficient frontier
     * @param {Object} assetClasses - Asset class data with returns, volatility, correlations
     * @param {number} numPortfolios - Number of portfolios to generate (50-1000)
     * @param {number} riskFreeRate - Risk-free rate (decimal, e.g., 0.04 for 4%)
     * @returns {Promise} Efficient frontier with all portfolios and key portfolios
     */
    async calculateEfficientFrontier(assetClasses, numPortfolios = 200, riskFreeRate = 0.04) {
        const response = await api.post('/investment/efficient-frontier/calculate', {
            asset_classes: assetClasses,
            num_portfolios: numPortfolios,
            risk_free_rate: riskFreeRate,
        });
        return response.data;
    },

    /**
     * Calculate efficient frontier with default UK assumptions
     * @param {number} numPortfolios - Number of portfolios to generate
     * @param {number} riskFreeRate - Risk-free rate
     * @returns {Promise} Efficient frontier with default assumptions
     */
    async calculateEfficientFrontierWithDefaults(numPortfolios = 200, riskFreeRate = 0.04) {
        const response = await api.get('/investment/efficient-frontier/default', {
            params: {
                num_portfolios: numPortfolios,
                risk_free_rate: riskFreeRate,
            },
        });
        return response.data;
    },

    /**
     * Find optimal portfolio for target return
     * @param {number} targetReturn - Target annual return (decimal, e.g., 0.08 for 8%)
     * @param {Object} assetClasses - Optional custom asset class assumptions
     * @param {number} riskFreeRate - Risk-free rate
     * @returns {Promise} Optimal portfolio allocation
     */
    async findOptimalPortfolioByReturn(targetReturn, assetClasses = null, riskFreeRate = 0.04) {
        const response = await api.post('/investment/efficient-frontier/optimal-by-return', {
            target_return: targetReturn,
            asset_classes: assetClasses,
            risk_free_rate: riskFreeRate,
        });
        return response.data;
    },

    /**
     * Find optimal portfolio for target risk level
     * @param {number} targetVolatility - Target volatility (decimal, e.g., 0.15 for 15%)
     * @param {Object} assetClasses - Optional custom asset class assumptions
     * @param {number} riskFreeRate - Risk-free rate
     * @returns {Promise} Optimal portfolio allocation
     */
    async findOptimalPortfolioByRisk(targetVolatility, assetClasses = null, riskFreeRate = 0.04) {
        const response = await api.post('/investment/efficient-frontier/optimal-by-risk', {
            target_volatility: targetVolatility,
            asset_classes: assetClasses,
            risk_free_rate: riskFreeRate,
        });
        return response.data;
    },

    /**
     * Compare portfolio allocation with efficient frontier
     * @param {Object} allocation - Portfolio allocation (weights summing to 1.0)
     * @param {number} allocation.equities - Equity weight
     * @param {number} allocation.bonds - Bond weight
     * @param {number} allocation.cash - Cash weight
     * @param {number} allocation.alternatives - Alternatives weight
     * @param {Object} assetClasses - Optional custom asset class assumptions
     * @param {number} riskFreeRate - Risk-free rate
     * @returns {Promise} Comparison with efficiency score and improvement potential
     */
    async compareWithEfficientFrontier(allocation, assetClasses = null, riskFreeRate = 0.04) {
        const response = await api.post('/investment/efficient-frontier/compare', {
            allocation: allocation,
            asset_classes: assetClasses,
            risk_free_rate: riskFreeRate,
        });
        return response.data;
    },

    /**
     * Calculate comprehensive portfolio statistics
     * @param {Object} allocation - Portfolio allocation (weights summing to 1.0)
     * @param {Object} assetClasses - Optional custom asset class assumptions
     * @param {number} riskFreeRate - Risk-free rate
     * @returns {Promise} Statistics including Sharpe, Sortino, VaR, etc.
     */
    async calculatePortfolioStatistics(allocation, assetClasses = null, riskFreeRate = 0.04) {
        const response = await api.post('/investment/efficient-frontier/statistics', {
            allocation: allocation,
            asset_classes: assetClasses,
            risk_free_rate: riskFreeRate,
        });
        return response.data;
    },

    /**
     * Analyse user's current portfolio efficiency
     * @param {Array} accountIds - Optional investment account IDs to analyse
     * @param {number} riskFreeRate - Risk-free rate
     * @returns {Promise} Current portfolio analysis with efficiency metrics
     */
    async analyzeCurrentPortfolioEfficiency(accountIds = null, riskFreeRate = 0.04) {
        const response = await api.get('/investment/efficient-frontier/analyse-current', {
            params: {
                account_ids: accountIds,
                risk_free_rate: riskFreeRate,
            },
        });
        return response.data;
    },

    /**
     * Get default UK asset class assumptions
     * @returns {Promise} Default assumptions with expected returns, volatility, correlations
     */
    async getDefaultAssetClassAssumptions() {
        const response = await api.get('/investment/efficient-frontier/default-assumptions');
        return response.data;
    },

    // ===================================================================
    // Phase 2: Advanced Investment Planning
    // ===================================================================

    // ===================================================================
    // Phase 2.1: Contribution Planning & Optimization
    // ===================================================================

    /**
     * Optimise contribution strategy across ISA, GIA, and Pension wrappers
     * POST /api/investment/contribution/optimise
     * @param {Object} inputs Contribution optimization inputs
     * @param {number} inputs.monthly_investable_income Monthly investable income
     * @param {number} inputs.lump_sum_amount One-time lump sum amount (optional)
     * @param {number} inputs.time_horizon Time horizon in years
     * @param {string} inputs.risk_tolerance Risk tolerance (cautious, balanced, adventurous)
     * @param {string} inputs.income_tax_band Income tax band (basic, higher, additional)
     * @param {number} inputs.expected_return Expected annual return (decimal)
     * @returns {Promise} Wrapper allocation, projections, and tax efficiency score
     */
    async optimiseContributions(inputs) {
        const response = await api.post('/investment/contribution/optimize', inputs);
        return response.data;
    },

    /**
     * Calculate contribution affordability
     * POST /api/investment/contribution/affordability
     * @param {Object} inputs Affordability calculation inputs
     * @param {number} inputs.gross_income Monthly gross income
     * @param {number} inputs.fixed_expenses Monthly fixed expenses
     * @param {number} inputs.discretionary_spending Monthly discretionary spending
     * @param {number} inputs.existing_contributions Existing investment contributions
     * @returns {Promise} Affordability analysis with recommendations
     */
    async calculateAffordability(inputs) {
        const response = await api.post('/investment/contribution/affordability', inputs);
        return response.data;
    },

    /**
     * Analyse lump sum vs dollar-cost averaging (DCA)
     * POST /api/investment/contribution/lump-sum-vs-dca
     * @param {Object} inputs Analysis inputs
     * @param {number} inputs.lump_sum_amount Lump sum amount
     * @param {number} inputs.time_horizon Investment time horizon in years
     * @param {number} inputs.expected_return Expected annual return (decimal)
     * @param {number} inputs.volatility Expected volatility (decimal)
     * @param {string} inputs.dca_period DCA period (monthly, quarterly)
     * @returns {Promise} Comparison analysis with recommendations
     */
    async analyzeLumpSumVsDCA(inputs) {
        const response = await api.post('/investment/contribution/lump-sum-vs-dca', inputs);
        return response.data;
    },

    // ===================================================================
    // Phase 2.5: Fee Analysis (Enhanced)
    // ===================================================================

    /**
     * Analyse portfolio fees in detail
     * GET /api/investment/fees/analyse
     * @returns {Promise} Comprehensive fee analysis with breakdown by type and holdings
     */
    async analyzeFees() {
        const response = await api.get('/investment/fees/analyze');
        return response.data;
    },

    /**
     * Calculate fee savings potential
     * POST /api/investment/fees/calculate-savings
     * @param {Object} params Savings calculation parameters
     * @param {number} params.portfolio_value Current portfolio value
     * @param {number} params.monthly_contribution Monthly contribution
     * @param {number} params.current_fee_percent Current fee percentage (decimal)
     * @param {number} params.alternative_fee_percent Alternative fee percentage (decimal)
     * @param {number} params.expected_return Expected return (decimal)
     * @param {number} params.years Time horizon in years
     * @returns {Promise} Fee savings analysis over time
     */
    async calculateFeeSavings(params) {
        const response = await api.post('/investment/fees/calculate-savings', params);
        return response.data;
    },

    // ===================================================================
    // Phase 2.6: Asset Location & Wrapper Optimization (Enhanced)
    // ===================================================================

    /**
     * Analyse asset location optimization
     * GET /api/investment/asset-location/analyse
     * @returns {Promise} Asset location analysis with tax drag and recommendations
     */
    async analyzeAssetLocation() {
        const response = await api.get('/investment/asset-location/analyze');
        return response.data;
    },

    /**
     * Compare wrapper types (ISA, GIA, Pension)
     * GET /api/investment/wrappers/compare
     * @param {Object} params Comparison parameters
     * @param {number} params.investment_amount Investment amount
     * @param {number} params.time_horizon Time horizon in years
     * @param {number} params.expected_return Expected return (decimal)
     * @param {string} params.tax_band Tax band (basic, higher, additional)
     * @returns {Promise} Wrapper comparison with tax implications
     */
    async compareWrappers(params) {
        const response = await api.get('/investment/wrappers/compare', { params });
        return response.data;
    },

    // ===================================================================
    // Phase 2.7: Performance Attribution (Enhanced)
    // ===================================================================

    /**
     * Analyse performance attribution
     * GET /api/investment/performance/attribution
     * @param {Object} params Attribution parameters
     * @param {string} params.period Period (1y, 3y, 5y, 10y)
     * @param {string} params.benchmark Benchmark code (optional)
     * @returns {Promise} Performance attribution by allocation, selection, interaction
     */
    async analyzePerformanceAttribution(params = {}) {
        const response = await api.get('/investment/performance/attribution', { params });
        return response.data;
    },

    /**
     * Compare portfolio with multiple benchmarks
     * POST /api/investment/performance/compare-benchmarks
     * @param {Array} benchmarkIds Array of benchmark codes
     * @param {Object} params Additional parameters
     * @param {string} params.period Period (1y, 3y, 5y)
     * @returns {Promise} Multi-benchmark comparison
     */
    async compareBenchmarks(benchmarkIds, params = {}) {
        const response = await api.post('/investment/performance/compare-benchmarks', {
            benchmark_ids: benchmarkIds,
            ...params,
        });
        return response.data;
    },

    /**
     * Get available benchmarks
     * GET /api/investment/performance/benchmarks
     * @returns {Promise} List of available benchmarks
     */
    async getAvailableBenchmarks() {
        const response = await api.get('/investment/performance/benchmarks');
        return response.data;
    },

    /**
     * Get portfolio projections with Monte Carlo simulation
     * POST /api/investment/projections
     * @param {Object} params - Projection parameters
     * @param {Array} params.projection_periods - Years to project (e.g., [5, 10, 20, 30])
     * @param {Number} params.selected_period - Currently selected period for display
     * @param {Object} params.contribution_overrides - Account ID -> monthly contribution overrides
     * @returns {Promise} Projections for portfolio and individual accounts
     */
    async getPortfolioProjections(params = {}) {
        const response = await api.post('/investment/projections', params);
        return response.data;
    },

    /**
     * Get Monte Carlo projections for a specific investment account
     * GET /api/investment/accounts/{id}/projections
     * @param {Number} accountId - Investment account ID
     * @param {String} riskLevel - Optional risk level override (low, lower_medium, medium, upper_medium, high)
     * @returns {Promise} Account projections with year-by-year probability bands
     */
    async getAccountProjections(accountId, riskLevel = null) {
        const params = riskLevel ? { risk_level: riskLevel } : {};
        const response = await api.get(`/investment/accounts/${accountId}/projections`, { params });
        return response.data;
    },

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
     * Get strategy recommendations for a specific account
     * GET /api/investment/portfolio-strategy/account/{accountId}
     * @param {Number} accountId - Investment account ID
     * @returns {Promise} Account-specific strategy recommendations
     */
    async getAccountStrategy(accountId) {
        const response = await api.get(`/investment/portfolio-strategy/account/${accountId}`);
        return response.data;
    },
};

export default investmentService;
