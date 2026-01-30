import retirementService from '../../services/retirementService';
import dcPensionHoldingsService from '../../services/dcPensionHoldingsService';

// Track ongoing requests to prevent duplicates
const ongoingRequests = {
    fetchRecommendations: null,
    fetchAnnualAllowance: null,
    fetchPortfolioAnalysis: null,
};

const state = {
    dcPensions: [],
    dbPensions: [],
    statePension: null,
    profile: null,
    analysis: null,
    recommendations: [],
    annualAllowance: null,
    scenarios: null,
    portfolioAnalysis: null, // Portfolio optimization data
    projections: null, // Monte Carlo projections for Future Value tab
    projectionsLoading: false,
    strategies: null, // Retirement strategies for Strategies tab
    strategiesLoading: false,
    strategyImpact: null, // Impact calculation for slider interaction
    // Required Capital state (centralised for dashboard + detail view)
    requiredCapital: null, // Full required capital data from API
    requiredCapitalLoading: false,
    includedInvestmentIds: [], // Investment account IDs included in capital calculation
    includedCashIds: [], // Cash account IDs included in capital calculation
    // Retirement Income (Decumulation) state
    retirementIncome: null, // Full income configuration from API
    retirementIncomeLoading: false,
    incomeAccounts: [], // Available accounts for income selection
    incomeAllocations: [], // User's current allocations from sliders
    includeSpouseAssets: false, // Toggle for spouse's assets
    customTargetIncome: null, // Custom target income override
    activeTab: 'current', // Current active tab in PensionList
    loading: false,
    error: null,
};

const mutations = {
    SET_DC_PENSIONS(state, pensions) {
        state.dcPensions = pensions;
    },
    SET_DB_PENSIONS(state, pensions) {
        state.dbPensions = pensions;
    },
    SET_STATE_PENSION(state, pension) {
        state.statePension = pension;
    },
    SET_PROFILE(state, profile) {
        state.profile = profile;
    },
    SET_ANALYSIS(state, analysis) {
        state.analysis = analysis;
    },
    SET_RECOMMENDATIONS(state, recommendations) {
        state.recommendations = recommendations;
    },
    SET_ANNUAL_ALLOWANCE(state, allowance) {
        state.annualAllowance = allowance;
    },
    SET_SCENARIOS(state, scenarios) {
        state.scenarios = scenarios;
    },
    SET_LOADING(state, loading) {
        // Guard to prevent unnecessary mutations if value hasn't changed
        if (state.loading !== loading) {
            state.loading = loading;
        }
    },
    SET_ERROR(state, error) {
        state.error = error;
    },
    ADD_DC_PENSION(state, pension) {
        state.dcPensions.push(pension);
    },
    UPDATE_DC_PENSION(state, updatedPension) {
        const index = state.dcPensions.findIndex(p => p.id === updatedPension.id);
        if (index !== -1) {
            state.dcPensions.splice(index, 1, updatedPension);
        }
    },
    REMOVE_DC_PENSION(state, id) {
        state.dcPensions = state.dcPensions.filter(p => p.id !== id);
    },
    ADD_DB_PENSION(state, pension) {
        state.dbPensions.push(pension);
    },
    UPDATE_DB_PENSION(state, updatedPension) {
        const index = state.dbPensions.findIndex(p => p.id === updatedPension.id);
        if (index !== -1) {
            state.dbPensions.splice(index, 1, updatedPension);
        }
    },
    REMOVE_DB_PENSION(state, id) {
        state.dbPensions = state.dbPensions.filter(p => p.id !== id);
    },
    SET_PORTFOLIO_ANALYSIS(state, analysis) {
        state.portfolioAnalysis = analysis;
    },
    SET_PROJECTIONS(state, projections) {
        state.projections = projections;
    },
    SET_PROJECTIONS_LOADING(state, loading) {
        state.projectionsLoading = loading;
    },
    SET_STRATEGIES(state, strategies) {
        state.strategies = strategies;
    },
    SET_STRATEGIES_LOADING(state, loading) {
        state.strategiesLoading = loading;
    },
    SET_STRATEGY_IMPACT(state, impact) {
        state.strategyImpact = impact;
    },
    // Required Capital mutations
    SET_REQUIRED_CAPITAL(state, data) {
        state.requiredCapital = data;
    },
    SET_REQUIRED_CAPITAL_LOADING(state, loading) {
        state.requiredCapitalLoading = loading;
    },
    SET_INCLUDED_INVESTMENT_IDS(state, ids) {
        state.includedInvestmentIds = ids;
    },
    SET_INCLUDED_CASH_IDS(state, ids) {
        state.includedCashIds = ids;
    },
    TOGGLE_INCLUDED_INVESTMENT(state, id) {
        const index = state.includedInvestmentIds.indexOf(id);
        if (index === -1) {
            state.includedInvestmentIds.push(id);
        } else {
            state.includedInvestmentIds.splice(index, 1);
        }
    },
    TOGGLE_INCLUDED_CASH(state, id) {
        const index = state.includedCashIds.indexOf(id);
        if (index === -1) {
            state.includedCashIds.push(id);
        } else {
            state.includedCashIds.splice(index, 1);
        }
    },
    // Retirement Income (Decumulation) mutations
    SET_RETIREMENT_INCOME(state, data) {
        state.retirementIncome = data;
    },
    SET_RETIREMENT_INCOME_LOADING(state, loading) {
        state.retirementIncomeLoading = loading;
    },
    SET_INCOME_ACCOUNTS(state, accounts) {
        state.incomeAccounts = accounts;
    },
    SET_INCOME_ALLOCATIONS(state, allocations) {
        state.incomeAllocations = allocations;
    },
    UPDATE_INCOME_ALLOCATION(state, { sourceType, sourceId, amount }) {
        const index = state.incomeAllocations.findIndex(
            a => a.source_type === sourceType && a.source_id === sourceId
        );
        if (index !== -1) {
            state.incomeAllocations[index].annual_amount = amount;
        }
    },
    SET_INCLUDE_SPOUSE_ASSETS(state, include) {
        state.includeSpouseAssets = include;
    },
    SET_CUSTOM_TARGET_INCOME(state, amount) {
        state.customTargetIncome = amount;
    },
    SET_ACTIVE_TAB(state, tab) {
        state.activeTab = tab;
    },
};

const actions = {
    async fetchRetirementData({ commit }) {
        commit('SET_LOADING', true);
        commit('SET_ERROR', null);
        try {
            const response = await retirementService.getRetirementData();
            commit('SET_DC_PENSIONS', response.data.dc_pensions || []);
            commit('SET_DB_PENSIONS', response.data.db_pensions || []);
            commit('SET_STATE_PENSION', response.data.state_pension);
            commit('SET_PROFILE', response.data.profile);
        } catch (error) {
            commit('SET_ERROR', error.response?.data?.message || 'Failed to fetch retirement data');
            throw error;
        } finally {
            commit('SET_LOADING', false);
        }
    },

    async analyseRetirement({ commit }, data) {
        commit('SET_LOADING', true);
        commit('SET_ERROR', null);
        try {
            const response = await retirementService.analyzeRetirement(data);
            commit('SET_ANALYSIS', response.data);
            return response.data;
        } catch (error) {
            commit('SET_ERROR', error.response?.data?.message || 'Failed to analyse retirement');
            throw error;
        } finally {
            commit('SET_LOADING', false);
        }
    },

    async fetchRecommendations({ commit }) {
        // If request is already ongoing, return that promise
        if (ongoingRequests.fetchRecommendations) {
            return ongoingRequests.fetchRecommendations;
        }

        // DO NOT set loading - causes infinite loop
        commit('SET_ERROR', null);

        ongoingRequests.fetchRecommendations = retirementService.getRecommendations()
            .then(response => {
                commit('SET_RECOMMENDATIONS', response.data);
                return response;
            })
            .catch(error => {
                commit('SET_ERROR', error.response?.data?.message || 'Failed to fetch recommendations');
                throw error;
            })
            .finally(() => {
                ongoingRequests.fetchRecommendations = null;
            });

        return ongoingRequests.fetchRecommendations;
    },

    async fetchProjections({ commit }) {
        commit('SET_PROJECTIONS_LOADING', true);
        commit('SET_ERROR', null);
        try {
            const response = await retirementService.getProjections();
            commit('SET_PROJECTIONS', response.data);
            return response.data;
        } catch (error) {
            commit('SET_ERROR', error.response?.data?.message || 'Failed to fetch projections');
            throw error;
        } finally {
            commit('SET_PROJECTIONS_LOADING', false);
        }
    },

    async fetchStrategies({ commit }) {
        commit('SET_STRATEGIES_LOADING', true);
        commit('SET_ERROR', null);
        try {
            const response = await retirementService.getStrategies();
            commit('SET_STRATEGIES', response.data);
            return response.data;
        } catch (error) {
            commit('SET_ERROR', error.response?.data?.message || 'Failed to fetch strategies');
            throw error;
        } finally {
            commit('SET_STRATEGIES_LOADING', false);
        }
    },

    async calculateStrategyImpact({ commit }, { strategyType, newValue, priorAdditionalMonthly, priorAdditionalIncome, priorProbability }) {
        try {
            const response = await retirementService.calculateStrategyImpact(
                strategyType,
                newValue,
                {
                    priorAdditionalMonthly: priorAdditionalMonthly || 0,
                    priorAdditionalIncome: priorAdditionalIncome || 0,
                    priorProbability: priorProbability,
                }
            );
            commit('SET_STRATEGY_IMPACT', response.data);
            return response.data;
        } catch (error) {
            commit('SET_ERROR', error.response?.data?.message || 'Failed to calculate strategy impact');
            throw error;
        }
    },

    // Required Capital actions
    async fetchRequiredCapital({ commit }) {
        commit('SET_REQUIRED_CAPITAL_LOADING', true);
        commit('SET_ERROR', null);
        try {
            const response = await retirementService.getRequiredCapital();
            if (response.success) {
                commit('SET_REQUIRED_CAPITAL', response.data);
            }
            return response.data;
        } catch (error) {
            commit('SET_ERROR', error.response?.data?.message || 'Failed to fetch required capital');
            throw error;
        } finally {
            commit('SET_REQUIRED_CAPITAL_LOADING', false);
        }
    },

    toggleIncludedInvestment({ commit }, id) {
        commit('TOGGLE_INCLUDED_INVESTMENT', id);
    },

    toggleIncludedCash({ commit }, id) {
        commit('TOGGLE_INCLUDED_CASH', id);
    },

    setIncludedInvestmentIds({ commit }, ids) {
        commit('SET_INCLUDED_INVESTMENT_IDS', ids);
    },

    setIncludedCashIds({ commit }, ids) {
        commit('SET_INCLUDED_CASH_IDS', ids);
    },

    async runScenario({ commit }, scenarioData) {
        commit('SET_LOADING', true);
        commit('SET_ERROR', null);
        try {
            const response = await retirementService.runScenario(scenarioData);
            commit('SET_SCENARIOS', response.data);
            return response.data;
        } catch (error) {
            commit('SET_ERROR', error.response?.data?.message || 'Failed to run scenario');
            throw error;
        } finally {
            commit('SET_LOADING', false);
        }
    },

    async fetchAnnualAllowance({ commit }, taxYear) {
        // If request is already ongoing for this tax year, return that promise
        const requestKey = `fetchAnnualAllowance_${taxYear}`;
        if (ongoingRequests[requestKey]) {
            return ongoingRequests[requestKey];
        }

        // DO NOT set loading - causes infinite loop
        commit('SET_ERROR', null);

        ongoingRequests[requestKey] = retirementService.getAnnualAllowance(taxYear)
            .then(response => {
                commit('SET_ANNUAL_ALLOWANCE', response.data);
                return response;
            })
            .catch(error => {
                commit('SET_ERROR', error.response?.data?.message || 'Failed to fetch annual allowance');
                throw error;
            })
            .finally(() => {
                ongoingRequests[requestKey] = null;
            });

        return ongoingRequests[requestKey];
    },

    async createDCPension({ commit, dispatch }, pensionData) {
        commit('SET_LOADING', true);
        commit('SET_ERROR', null);
        try {
            const response = await retirementService.createDCPension(pensionData);
            commit('ADD_DC_PENSION', response.data);
            await dispatch('analyseRetirement');
            // Refresh net worth to update wealth summary
            await dispatch('netWorth/refreshNetWorth', null, { root: true });
            return response.data;
        } catch (error) {
            commit('SET_ERROR', error.response?.data?.message || 'Failed to create DC pension');
            throw error;
        } finally {
            commit('SET_LOADING', false);
        }
    },

    async updateDCPension({ commit, dispatch }, { id, data }) {
        commit('SET_LOADING', true);
        commit('SET_ERROR', null);
        try {
            const response = await retirementService.updateDCPension(id, data);
            commit('UPDATE_DC_PENSION', response.data);
            await dispatch('analyseRetirement');
            // Refresh net worth to update wealth summary
            await dispatch('netWorth/refreshNetWorth', null, { root: true });
            return response.data;
        } catch (error) {
            commit('SET_ERROR', error.response?.data?.message || 'Failed to update DC pension');
            throw error;
        } finally {
            commit('SET_LOADING', false);
        }
    },

    async deleteDCPension({ commit, dispatch }, id) {
        commit('SET_LOADING', true);
        commit('SET_ERROR', null);
        try {
            await retirementService.deleteDCPension(id);
            commit('REMOVE_DC_PENSION', id);
            await dispatch('analyseRetirement');
            // Refresh net worth to update wealth summary
            await dispatch('netWorth/refreshNetWorth', null, { root: true });
        } catch (error) {
            commit('SET_ERROR', error.response?.data?.message || 'Failed to delete DC pension');
            throw error;
        } finally {
            commit('SET_LOADING', false);
        }
    },

    async createDBPension({ commit, dispatch }, pensionData) {
        commit('SET_LOADING', true);
        commit('SET_ERROR', null);
        try {
            const response = await retirementService.createDBPension(pensionData);
            commit('ADD_DB_PENSION', response.data);
            await dispatch('analyseRetirement');
            // Refresh net worth to update wealth summary
            await dispatch('netWorth/refreshNetWorth', null, { root: true });
            return response.data;
        } catch (error) {
            commit('SET_ERROR', error.response?.data?.message || 'Failed to create DB pension');
            throw error;
        } finally {
            commit('SET_LOADING', false);
        }
    },

    async updateDBPension({ commit, dispatch }, { id, data }) {
        commit('SET_LOADING', true);
        commit('SET_ERROR', null);
        try {
            const response = await retirementService.updateDBPension(id, data);
            commit('UPDATE_DB_PENSION', response.data);
            await dispatch('analyseRetirement');
            // Refresh net worth to update wealth summary
            await dispatch('netWorth/refreshNetWorth', null, { root: true });
            return response.data;
        } catch (error) {
            commit('SET_ERROR', error.response?.data?.message || 'Failed to update DB pension');
            throw error;
        } finally {
            commit('SET_LOADING', false);
        }
    },

    async deleteDBPension({ commit, dispatch }, id) {
        commit('SET_LOADING', true);
        commit('SET_ERROR', null);
        try {
            await retirementService.deleteDBPension(id);
            commit('REMOVE_DB_PENSION', id);
            await dispatch('analyseRetirement');
            // Refresh net worth to update wealth summary
            await dispatch('netWorth/refreshNetWorth', null, { root: true });
        } catch (error) {
            commit('SET_ERROR', error.response?.data?.message || 'Failed to delete DB pension');
            throw error;
        } finally {
            commit('SET_LOADING', false);
        }
    },

    async updateStatePension({ commit, dispatch }, data) {
        commit('SET_LOADING', true);
        commit('SET_ERROR', null);
        try {
            const response = await retirementService.updateStatePension(data);
            commit('SET_STATE_PENSION', response.data);
            await dispatch('analyseRetirement');
            // Refresh net worth to update wealth summary
            await dispatch('netWorth/refreshNetWorth', null, { root: true });
            return response.data;
        } catch (error) {
            commit('SET_ERROR', error.response?.data?.message || 'Failed to update state pension');
            throw error;
        } finally {
            commit('SET_LOADING', false);
        }
    },

    // Portfolio Analysis Actions
    async fetchPortfolioAnalysis({ commit }, dcPensionId = null) {
        // If request is already ongoing, return that promise
        const requestKey = dcPensionId ? `fetchPortfolioAnalysis_${dcPensionId}` : 'fetchPortfolioAnalysis';
        if (ongoingRequests[requestKey]) {
            return ongoingRequests[requestKey];
        }

        // DO NOT set loading - causes infinite loop
        commit('SET_ERROR', null);

        const apiCall = dcPensionId
            ? dcPensionHoldingsService.getPensionPortfolioAnalysis(dcPensionId)
            : dcPensionHoldingsService.getPortfolioAnalysis();

        ongoingRequests[requestKey] = apiCall
            .then(response => {
                commit('SET_PORTFOLIO_ANALYSIS', response.data);
                return response;
            })
            .catch(error => {
                commit('SET_ERROR', error.response?.data?.message || 'Failed to fetch portfolio analysis');
                throw error;
            })
            .finally(() => {
                ongoingRequests[requestKey] = null;
            });

        return ongoingRequests[requestKey];
    },

    async createDCPensionHolding({ dispatch }, { dcPensionId, holdingData }) {
        try {
            const response = await dcPensionHoldingsService.createHolding(dcPensionId, holdingData);
            // Refresh portfolio analysis after adding a holding
            await dispatch('fetchPortfolioAnalysis');
            return response;
        } catch (error) {
            throw error;
        }
    },

    async updateDCPensionHolding({ dispatch }, { dcPensionId, holdingId, holdingData }) {
        try {
            const response = await dcPensionHoldingsService.updateHolding(dcPensionId, holdingId, holdingData);
            // Refresh portfolio analysis after updating a holding
            await dispatch('fetchPortfolioAnalysis');
            return response;
        } catch (error) {
            throw error;
        }
    },

    async deleteDCPensionHolding({ dispatch }, { dcPensionId, holdingId }) {
        try {
            const response = await dcPensionHoldingsService.deleteHolding(dcPensionId, holdingId);
            // Refresh portfolio analysis after deleting a holding
            await dispatch('fetchPortfolioAnalysis');
            return response;
        } catch (error) {
            throw error;
        }
    },

    async bulkUpdateDCPensionHoldings({ dispatch }, { dcPensionId, holdings }) {
        try {
            const response = await dcPensionHoldingsService.bulkUpdateHoldings(dcPensionId, holdings);
            // Refresh portfolio analysis after bulk update
            await dispatch('fetchPortfolioAnalysis');
            return response;
        } catch (error) {
            throw error;
        }
    },

    // Retirement Income (Decumulation) Actions
    async fetchRetirementIncome({ commit, state }) {
        commit('SET_RETIREMENT_INCOME_LOADING', true);
        commit('SET_ERROR', null);
        try {
            const response = await retirementService.getRetirementIncome(state.includeSpouseAssets);
            commit('SET_RETIREMENT_INCOME', response.data);
            // Initialize allocations from API response
            if (response.data?.allocations && response.data.allocations.length > 0) {
                commit('SET_INCOME_ALLOCATIONS', response.data.allocations);
            }
            return response.data;
        } catch (error) {
            commit('SET_ERROR', error.response?.data?.message || 'Failed to fetch retirement income');
            throw error;
        } finally {
            commit('SET_RETIREMENT_INCOME_LOADING', false);
        }
    },

    async calculateRetirementIncome({ commit, state }) {
        commit('SET_RETIREMENT_INCOME_LOADING', true);
        commit('SET_ERROR', null);
        try {
            const response = await retirementService.calculateRetirementIncome(
                state.incomeAllocations,
                state.includeSpouseAssets,
                state.customTargetIncome
            );
            commit('SET_RETIREMENT_INCOME', response.data);
            return response.data;
        } catch (error) {
            commit('SET_ERROR', error.response?.data?.message || 'Failed to calculate retirement income');
            throw error;
        } finally {
            commit('SET_RETIREMENT_INCOME_LOADING', false);
        }
    },

    async fetchIncomeAccounts({ commit, state }) {
        commit('SET_ERROR', null);
        try {
            const response = await retirementService.getIncomeAccounts(state.includeSpouseAssets);
            commit('SET_INCOME_ACCOUNTS', response.data?.accounts || []);
            return response.data;
        } catch (error) {
            commit('SET_ERROR', error.response?.data?.message || 'Failed to fetch income accounts');
            throw error;
        }
    },

    updateIncomeAllocation({ commit, dispatch }, { sourceType, sourceId, amount }) {
        commit('UPDATE_INCOME_ALLOCATION', { sourceType, sourceId, amount });
        // Debounced recalculation will be handled by the component
    },

    async toggleSpouseAssets({ commit, dispatch }, include) {
        commit('SET_INCLUDE_SPOUSE_ASSETS', include);
        // Reload accounts and income config with new setting
        await Promise.all([
            dispatch('fetchIncomeAccounts'),
            dispatch('fetchRetirementIncome'),
        ]);
    },

    setCustomTargetIncome({ commit }, amount) {
        commit('SET_CUSTOM_TARGET_INCOME', amount);
    },

    setActiveTab({ commit }, tab) {
        commit('SET_ACTIVE_TAB', tab);
    },

    resetToMainDashboard({ commit }) {
        commit('SET_ACTIVE_TAB', 'current');
    },
};

const getters = {
    totalPensionWealth: (state) => {
        const dcTotal = state.dcPensions.reduce((sum, p) => sum + parseFloat(p.current_fund_value || 0), 0);
        // DB pensions don't have a "value" - they're income streams
        // State pension also doesn't have a fund value
        return dcTotal;
    },

    // Calculate retirement score from income gap (for FinancialHealthScore compatibility)
    retirementReadinessScore: (state, getters) => {
        const gap = getters.incomeGap;
        // Score: 100 if no gap, decreases as gap increases
        // Every £500 gap reduces score by 1 point
        return Math.max(0, Math.min(100, 100 - (gap / 500)));
    },

    projectedIncome: (state) => {
        return state.analysis?.projected_income || 0;
    },

    targetIncome: (state) => {
        return state.analysis?.target_income || 0;
    },

    incomeGap: (state) => {
        const projected = state.analysis?.projected_income || 0;
        const target = state.analysis?.target_income || 0;
        return target - projected;
    },

    yearsToRetirement: (state) => {
        if (!state.profile?.target_retirement_age || !state.profile?.current_age) {
            return 0;
        }
        return Math.max(0, state.profile.target_retirement_age - state.profile.current_age);
    },

    hasIncomeSurplus: (state, getters) => {
        return getters.incomeGap < 0;
    },

    hasIncomeGap: (state, getters) => {
        return getters.incomeGap > 0;
    },

    // Portfolio Analysis Getters
    hasPortfolioData: (state) => {
        return state.portfolioAnalysis?.has_portfolio_data || false;
    },

    portfolioTotalValue: (state) => {
        return state.portfolioAnalysis?.portfolio_summary?.total_value || 0;
    },

    portfolioRiskMetrics: (state) => {
        return state.portfolioAnalysis?.risk_metrics || null;
    },

    portfolioAssetAllocation: (state) => {
        return state.portfolioAnalysis?.asset_allocation || null;
    },

    portfolioDiversificationScore: (state) => {
        return state.portfolioAnalysis?.diversification_score || 0;
    },

    portfolioFeeAnalysis: (state) => {
        return state.portfolioAnalysis?.fee_analysis || null;
    },

    pensionsWithHoldings: (state) => {
        return state.portfolioAnalysis?.pensions_breakdown || [];
    },

    // Direct state accessors for risk profile page
    dcPensions: (state) => state.dcPensions,
    dbPensions: (state) => state.dbPensions,

    // Required Capital Getters
    requiredCapitalData: (state) => state.requiredCapital,
    requiredCapitalLoading: (state) => state.requiredCapitalLoading,
    includedInvestmentIds: (state) => state.includedInvestmentIds,
    includedCashIds: (state) => state.includedCashIds,
    targetRetirementIncome: (state) => state.requiredCapital?.required_income || 0,
    requiredCapitalAtRetirement: (state) => state.requiredCapital?.required_capital_at_retirement || 0,
    requiredCapitalToday: (state) => state.requiredCapital?.required_capital_today || 0,

    // Retirement Income (Decumulation) Getters
    retirementIncomeData: (state) => state.retirementIncome,
    retirementIncomeLoading: (state) => state.retirementIncomeLoading,
    incomeAccounts: (state) => state.incomeAccounts,
    incomeAllocations: (state) => state.incomeAllocations,
    includeSpouseAssets: (state) => state.includeSpouseAssets,
    customTargetIncome: (state) => state.customTargetIncome,

    // Computed income values from API response
    retirementIncomeTargetIncome: (state) => state.retirementIncome?.target_income || 0,
    retirementIncomeNetIncome: (state) => state.retirementIncome?.tax_breakdown?.net_income || 0,
    retirementIncomeTaxBreakdown: (state) => state.retirementIncome?.tax_breakdown || null,
    retirementIncomeFundProjections: (state) => state.retirementIncome?.fund_projections || [],
    retirementIncomeDepletionAges: (state) => state.retirementIncome?.depletion_ages || {},
    retirementIncomeAvailableAccounts: (state) => state.retirementIncome?.available_accounts || [],
};

export default {
    namespaced: true,
    state,
    mutations,
    actions,
    getters,
};
