import api from './api';

const API_BASE = '/retirement';

export default {
    /**
     * Get all retirement data for the authenticated user
     */
    async getRetirementData() {
        const response = await api.get(API_BASE);
        return response.data;
    },

    /**
     * Run retirement analysis
     */
    async analyzeRetirement(data = {}) {
        const response = await api.post(`${API_BASE}/analyze`, data);
        return response.data;
    },

    /**
     * Get retirement recommendations
     */
    async getRecommendations() {
        const response = await api.get(`${API_BASE}/recommendations`);
        return response.data;
    },

    /**
     * Run what-if scenario
     */
    async runScenario(scenarioData) {
        const response = await api.post(`${API_BASE}/scenarios`, scenarioData);
        return response.data;
    },

    /**
     * Get annual allowance status for a tax year
     */
    async getAnnualAllowance(taxYear) {
        const response = await api.get(`${API_BASE}/annual-allowance/${taxYear}`);
        return response.data;
    },

    /**
     * Get retirement projections (Monte Carlo + income drawdown)
     */
    async getProjections() {
        const response = await api.get(`${API_BASE}/projections`);
        return response.data;
    },

    /**
     * Get required capital calculations with present value breakdown
     */
    async getRequiredCapital() {
        const response = await api.get(`${API_BASE}/required-capital`);
        return response.data;
    },

    /**
     * Get Monte Carlo projections for a specific DC pension
     */
    async getDCPensionProjection(pensionId) {
        const response = await api.get(`${API_BASE}/dc-pensions/${pensionId}/projections`);
        return response.data;
    },

    /**
     * Get retirement strategies analysis
     */
    async getStrategies() {
        const response = await api.get(`${API_BASE}/strategies`);
        return response.data;
    },

    /**
     * Calculate impact of a strategy change
     *
     * @param {string} strategyType - Type of strategy (employer_match, increase_contribution, etc.)
     * @param {number} newValue - New value for the strategy slider
     * @param {Object} cumulativeContext - Cumulative values from prior strategies
     * @param {number} cumulativeContext.priorAdditionalMonthly - Monthly contributions from prior strategies
     * @param {number} cumulativeContext.priorAdditionalIncome - Annual income from prior strategies
     * @param {number|null} cumulativeContext.priorProbability - Probability after prior strategies
     */
    async calculateStrategyImpact(strategyType, newValue, cumulativeContext = {}) {
        const params = {
            strategy_type: strategyType,
            new_value: newValue,
        };

        // Add cumulative context if provided
        if (cumulativeContext.priorAdditionalMonthly) {
            params.prior_additional_monthly = cumulativeContext.priorAdditionalMonthly;
        }
        if (cumulativeContext.priorAdditionalIncome) {
            params.prior_additional_income = cumulativeContext.priorAdditionalIncome;
        }
        if (cumulativeContext.priorProbability !== null && cumulativeContext.priorProbability !== undefined) {
            params.prior_probability = cumulativeContext.priorProbability;
        }

        const response = await api.get(`${API_BASE}/strategies/impact`, { params });
        return response.data;
    },

    // DC Pension CRUD operations
    async createDCPension(pensionData) {
        const response = await api.post(`${API_BASE}/pensions/dc`, pensionData);
        return response.data;
    },

    async updateDCPension(id, pensionData) {
        const response = await api.put(`${API_BASE}/pensions/dc/${id}`, pensionData);
        return response.data;
    },

    async deleteDCPension(id) {
        const response = await api.delete(`${API_BASE}/pensions/dc/${id}`);
        return response.data;
    },

    // DB Pension CRUD operations
    async createDBPension(pensionData) {
        const response = await api.post(`${API_BASE}/pensions/db`, pensionData);
        return response.data;
    },

    async updateDBPension(id, pensionData) {
        const response = await api.put(`${API_BASE}/pensions/db/${id}`, pensionData);
        return response.data;
    },

    async deleteDBPension(id) {
        const response = await api.delete(`${API_BASE}/pensions/db/${id}`);
        return response.data;
    },

    // State Pension
    async updateStatePension(data) {
        const response = await api.post(`${API_BASE}/state-pension`, data);
        return response.data;
    },

    // Decumulation analysis (drawdown strategies)

    /**
     * Get decumulation analysis including withdrawal rates, annuity comparison, and income phasing
     */
    async getDecumulationAnalysis() {
        const response = await api.get(`${API_BASE}/decumulation-analysis`);
        return response.data;
    },

    // Retirement Income (Decumulation) endpoints

    /**
     * Get retirement income configuration with default allocations
     * @param {boolean} includeSpouse - Include spouse's assets
     */
    async getRetirementIncome(includeSpouse = false) {
        const response = await api.get(`${API_BASE}/income`, {
            params: { include_spouse: includeSpouse },
        });
        return response.data;
    },

    /**
     * Calculate retirement income based on user allocations
     * @param {Array} allocations - Income allocations from sliders
     * @param {boolean} includeSpouse - Include spouse's assets
     * @param {number|null} customTargetIncome - Custom target income override
     */
    async calculateRetirementIncome(allocations, includeSpouse = false, customTargetIncome = null) {
        const response = await api.post(`${API_BASE}/income/calculate`, {
            income_allocations: allocations,
            include_spouse: includeSpouse,
            custom_target_income: customTargetIncome,
        });
        return response.data;
    },

    /**
     * Get all accounts eligible for retirement income
     * @param {boolean} includeSpouse - Include spouse's assets
     */
    async getIncomeAccounts(includeSpouse = false) {
        const response = await api.get(`${API_BASE}/income/accounts`, {
            params: { include_spouse: includeSpouse },
        });
        return response.data;
    },
};
