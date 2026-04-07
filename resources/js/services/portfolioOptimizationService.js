import api from './api';

/**
 * Portfolio Optimization API Service
 * Handles all API calls for Modern Portfolio Theory (MPT) and portfolio optimization
 */
const portfolioOptimizationService = {
    /**
     * Calculate efficient frontier for user's portfolio
     * @param {Object} params - Frontier parameters
     * @param {Number} params.risk_free_rate - Risk-free rate (default: 0.045 for UK Gilts)
     * @param {Number} params.num_points - Number of points on frontier (default: 50)
     * @param {Array} params.account_ids - Optional array of account IDs to include
     * @returns {Promise} Efficient frontier data with current position
     */
    async calculateEfficientFrontier(params = {}) {
        const response = await api.post('/investment/optimization/efficient-frontier', params);
        return response.data;
    },

    /**
     * Get current portfolio position relative to efficient frontier
     * @returns {Promise} Current portfolio metrics and improvement opportunities
     */
    async getCurrentPosition() {
        const response = await api.get('/investment/optimization/current-position');
        return response.data;
    },

    /**
     * Optimise portfolio for minimum variance
     * @param {Object} constraints - Optional constraints
     * @param {Number} constraints.min_weight - Minimum weight per asset (0-1)
     * @param {Number} constraints.max_weight - Maximum weight per asset (0-1)
     * @returns {Promise} Minimum variance portfolio allocation
     */
    async optimiseMinimumVariance(constraints = {}) {
        const response = await api.post('/investment/optimization/minimize-variance', constraints);
        return response.data;
    },

    /**
     * Optimise portfolio for maximum Sharpe ratio (tangency portfolio)
     * @param {Object} params - Optimization parameters
     * @param {Number} params.risk_free_rate - Risk-free rate (default: 0.045)
     * @param {Number} params.min_weight - Minimum weight per asset
     * @param {Number} params.max_weight - Maximum weight per asset
     * @returns {Promise} Tangency portfolio allocation
     */
    async optimiseMaximumSharpe(params = {}) {
        const response = await api.post('/investment/optimization/maximize-sharpe', params);
        return response.data;
    },

    /**
     * Optimise portfolio for a target return
     * @param {Object} params - Optimization parameters
     * @param {Number} params.target_return - Desired return (e.g., 0.08 for 8%)
     * @param {Number} params.min_weight - Minimum weight per asset
     * @param {Number} params.max_weight - Maximum weight per asset
     * @returns {Promise} Target return portfolio with minimum risk
     */
    async optimiseTargetReturn(params) {
        if (!params.target_return) {
            throw new Error('target_return is required');
        }
        const response = await api.post('/investment/optimization/target-return', params);
        return response.data;
    },

    /**
     * Calculate risk parity portfolio
     * Equal risk contribution from each asset
     * @returns {Promise} Risk parity portfolio allocation
     */
    async optimiseRiskParity() {
        const response = await api.post('/investment/optimization/risk-parity');
        return response.data;
    },

    /**
     * Unified optimization method
     * @param {Object} params - Optimization parameters
     * @param {String} params.optimization_type - Type: 'min_variance', 'max_sharpe', 'target_return', 'risk_parity'
     * @param {Number} params.target_return - Required if optimization_type is 'target_return'
     * @param {Number} params.risk_free_rate - Risk-free rate (optional)
     * @param {Object} params.constraints - Constraints object
     * @param {Number} params.constraints.min_weight - Minimum weight per asset
     * @param {Number} params.constraints.max_weight - Maximum weight per asset
     * @param {Array} params.constraints.sector_limits - Sector allocation limits
     * @returns {Promise} Optimised portfolio
     */
    async optimise(params) {
        const { optimization_type } = params;

        switch (optimization_type) {
            case 'min_variance':
                return this.optimiseMinimumVariance(params.constraints || {});

            case 'max_sharpe':
                return this.optimiseMaximumSharpe({
                    risk_free_rate: params.risk_free_rate,
                    ...params.constraints
                });

            case 'target_return':
                return this.optimiseTargetReturn({
                    target_return: params.target_return,
                    ...params.constraints
                });

            case 'risk_parity':
                return this.optimiseRiskParity();

            default:
                throw new Error(`Invalid optimization_type: ${optimization_type}`);
        }
    },

    /**
     * Clear cached efficient frontier calculations
     * Use this after portfolio changes to force recalculation
     * @returns {Promise} Success message
     */
    async clearCache() {
        const response = await api.delete('/investment/optimization/clear-cache');
        return response.data;
    },

    /**
     * Calculate correlation matrix for portfolio holdings
     * @returns {Promise} Correlation matrix with statistics
     */
    async getCorrelationMatrix() {
        // This endpoint will be added when we build correlation analysis
        const response = await api.get('/investment/optimization/correlation-matrix');
        return response.data;
    },

    /**
     * Get portfolio diversification metrics
     * @returns {Promise} Diversification score and analysis
     */
    async getDiversificationMetrics() {
        // This endpoint will be added later
        const response = await api.get('/investment/optimization/diversification');
        return response.data;
    },

    /**
     * Compare current portfolio to optimal allocation
     * Returns specific rebalancing trades needed
     * @param {String} optimizationType - 'min_variance', 'max_sharpe', etc.
     * @returns {Promise} Rebalancing recommendations
     */
    async getRebalancingRecommendations(optimizationType = 'max_sharpe') {
        const response = await api.post('/investment/optimization/rebalancing-actions', {
            optimization_type: optimizationType
        });
        return response.data;
    },

    /**
     * Helper: Format portfolio weights for display
     * @param {Object} portfolio - Portfolio object from optimization
     * @param {Array} holdings - Holdings array with asset names
     * @returns {Array} Formatted allocation [{name, weight, percentage}]
     */
    formatAllocation(portfolio, holdings) {
        if (!portfolio.weights || !holdings) {
            return [];
        }

        return portfolio.weights.map((weight, index) => ({
            name: holdings[index]?.asset_name || holdings[index]?.ticker_symbol || `Asset ${index + 1}`,
            weight: weight,
            percentage: (weight * 100).toFixed(2),
            holding: holdings[index]
        })).filter(item => item.weight > 0.001); // Filter out tiny allocations
    },

    /**
     * Helper: Calculate portfolio metrics
     * @param {Array} weights - Portfolio weights
     * @param {Array} expectedReturns - Expected returns for each asset
     * @param {Array} covarianceMatrix - Covariance matrix
     * @returns {Object} Portfolio metrics {return, risk, sharpe}
     */
    calculatePortfolioMetrics(weights, expectedReturns, covarianceMatrix) {
        // Portfolio return = sum(weight * return)
        const portfolioReturn = weights.reduce((sum, w, i) => sum + w * expectedReturns[i], 0);

        // Portfolio variance = w^T * Cov * w
        let portfolioVariance = 0;
        for (let i = 0; i < weights.length; i++) {
            for (let j = 0; j < weights.length; j++) {
                portfolioVariance += weights[i] * weights[j] * covarianceMatrix[i][j];
            }
        }
        const portfolioRisk = Math.sqrt(portfolioVariance);

        // Sharpe ratio (assuming risk-free rate of 4.5%)
        const riskFreeRate = 0.045;
        const sharpe = portfolioRisk > 0 ? (portfolioReturn - riskFreeRate) / portfolioRisk : 0;

        return {
            expected_return: portfolioReturn,
            expected_risk: portfolioRisk,
            sharpe_ratio: sharpe
        };
    },

};

export default portfolioOptimizationService;
