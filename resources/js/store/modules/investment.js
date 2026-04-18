import investmentService from '@/services/investmentService';
import { pollMonteCarloJob } from '@/utils/poller';
import { ISA_ANNUAL_ALLOWANCE } from '@/constants/taxConfig';

const state = {
    accounts: [],
    riskProfile: null,
    analysis: null,
    recommendations: null,  // { recommendation_count, recommendations: [] }
    monteCarloResults: {},      // Keyed by jobId
    monteCarloStatus: {},        // Keyed by jobId
    monteCarloResultsByGoal: {},
    optimizationResult: null,    // Portfolio optimization result
    scenarios: null,
    portfolioProjections: null,        // Performance tab projections
    projectionsLoading: false,
    projectionsError: null,
    selectedProjectionPeriod: 10,      // Default to 10 years
    lifeEvents: [],
    lifeEventImpact: null,
    goalStrategies: [],
    goalsSummary: null,
    canProceed: true,
    readinessChecks: null,
    loading: false,
    error: null,
};

/**
 * Vuex getters for investment module.
 *
 * @typedef {Object} InvestmentState
 * @property {Array<Object>} accounts - User's investment accounts
 * @property {Array<Object>} goals - User's investment goals
 * @property {Object|null} riskProfile - User's risk profile
 * @property {Object|null} analysis - Portfolio analysis data
 */
const getters = {
    /**
     * Get all investment accounts.
     * @param {InvestmentState} state - Vuex state
     * @returns {Array<Object>} Array of investment account objects
     */
    accounts: (state) => state.accounts,

    /**
     * Get total portfolio value across all accounts (user's share only for joint accounts).
     * Joint accounts are weighted by ownership_percentage.
     * @param {InvestmentState} state - Vuex state
     * @returns {number} Total portfolio value in GBP
     */
    totalPortfolioValue: (state) => {
        return state.accounts.reduce((sum, account) => {
            const fullValue = parseFloat(account.current_value || 0);
            // For joint accounts, only count the user's share
            if (account.ownership_type === 'joint') {
                const percentage = parseFloat(account.ownership_percentage || 50) / 100;
                return sum + (fullValue * percentage);
            }
            return sum + fullValue;
        }, 0);
    },

    /**
     * Get year-to-date return percentage.
     * @param {InvestmentState} state - Vuex state
     * @returns {number} YTD return as percentage
     */
    ytdReturn: (state) => {
        return state.analysis?.returns?.ytd_return || 0;
    },

    /**
     * Get asset allocation breakdown.
     * @param {InvestmentState} state - Vuex state
     * @returns {Array<{asset_type: string, percentage: number, value: number}>}
     */
    assetAllocation: (state) => {
        return state.analysis?.asset_allocation || [];
    },

    /**
     * Get total annual fees across all accounts.
     * @param {InvestmentState} state - Vuex state
     * @returns {number} Total fees in GBP
     */
    totalFees: (state) => {
        return state.analysis?.fee_analysis?.total_annual_fees || 0;
    },

    /**
     * Get fee drag as percentage of portfolio.
     * @param {InvestmentState} state - Vuex state
     * @returns {number} Fee drag percentage
     */
    feeDragPercent: (state) => {
        return state.analysis?.fee_analysis?.fee_drag_percent || 0;
    },

    /**
     * Get total unrealised gains across holdings.
     * @param {InvestmentState} state - Vuex state
     * @returns {number} Unrealised gains in GBP
     */
    unrealisedGains: (state) => {
        return state.analysis?.tax_efficiency?.unrealised_gains?.total_unrealised_gains || 0;
    },

    /**
     * Get tax efficiency score (0-100).
     * @param {InvestmentState} state - Vuex state
     * @returns {number} Score from 0-100
     */
    taxEfficiencyScore: (state) => {
        return state.analysis?.tax_efficiency?.efficiency_score || 0;
    },

    /**
     * Get diversification score (0-100).
     * @param {InvestmentState} state - Vuex state
     * @returns {number} Score from 0-100
     */
    diversificationScore: (state) => {
        return state.analysis?.diversification_score || 0;
    },

    /**
     * Get portfolio risk level from analysis.
     * @param {InvestmentState} state - Vuex state
     * @returns {'low'|'medium'|'high'} Risk level
     */
    riskLevel: (state) => {
        return state.analysis?.risk_metrics?.risk_level || 'medium';
    },

    // Get main risk level from profile (5-level system)
    mainRiskLevel: (state) => {
        return state.riskProfile?.risk_level || null;
    },

    // Check if user has set a risk profile
    hasRiskProfile: (state) => {
        return !!state.riskProfile?.risk_level;
    },

    // Get products with custom risk settings
    productsWithCustomRisk: (state) => {
        return state.accounts.filter(a => a.has_custom_risk && a.risk_preference);
    },

    // Get all holdings across all accounts
    allHoldings: (state) => {
        return state.accounts.flatMap(account => account.holdings || []);
    },

    // Get holdings count
    holdingsCount: (state, getters) => {
        return getters.allHoldings.length;
    },

    // Get accounts count
    accountsCount: (state) => {
        return state.accounts.length;
    },

    // Get ISA accounts
    isaAccounts: (state) => {
        return state.accounts.filter(account => account.account_type === 'isa');
    },

    // Get total ISA value (user's share only for joint accounts, though ISAs should be individual)
    totalISAValue: (state, getters) => {
        return getters.isaAccounts.reduce((sum, account) => {
            const fullValue = parseFloat(account.current_value || 0);
            if (account.ownership_type === 'joint') {
                const percentage = parseFloat(account.ownership_percentage || 50) / 100;
                return sum + (fullValue * percentage);
            }
            return sum + fullValue;
        }, 0);
    },

    // Get ISA percentage of total portfolio
    isaPercentage: (state, getters) => {
        const totalValue = getters.totalPortfolioValue;
        if (totalValue === 0) return 0;
        return Math.round((getters.totalISAValue / totalValue) * 100);
    },

    // Get current year ISA contributions (for allowance tracking)
    // Uses isa_subscription_current_year which tracks ISA-specific contributions
    totalISAContributions: (state, getters) => {
        return getters.isaAccounts.reduce((sum, account) => {
            return sum + parseFloat(account.isa_subscription_current_year || 0);
        }, 0);
    },

    // Get ISA allowance percentage used (based on contributions, not value)
    // Uses ISA allowance from savings store (fetched from TaxConfigService API)
    isaAllowancePercentage: (state, getters, rootState) => {
        // Get ISA allowance from savings store (API-backed) or use default
        const isaAllowance = rootState.savings?.isaAllowance?.total_allowance || ISA_ANNUAL_ALLOWANCE;
        const contributions = getters.totalISAContributions;
        return (contributions / isaAllowance) * 100;
    },

    // Get current year ISA subscription (S&S ISA) from ISA accounts
    investmentISASubscription: (state, getters) => {
        return getters.isaAccounts.reduce((sum, account) => {
            return sum + parseFloat(account.isa_subscription_current_year || 0);
        }, 0);
    },

    // Get Monte Carlo result by job ID
    getMonteCarloResult: (state) => (jobId) => {
        return state.monteCarloResults[jobId] || null;
    },

    // Get Monte Carlo status by job ID
    getMonteCarloStatus: (state) => (jobId) => {
        return state.monteCarloStatus[jobId] || 'unknown';
    },

    // Check if needs rebalancing
    needsRebalancing: (state) => {
        return state.analysis?.allocation_deviation?.needs_rebalancing || false;
    },

    // Portfolio projections getters
    portfolioProjections: (state) => state.portfolioProjections,
    projectionsLoading: (state) => state.projectionsLoading,
    projectionsError: (state) => state.projectionsError,
    selectedProjectionPeriod: (state) => state.selectedProjectionPeriod,

    // Get portfolio projection for selected period
    selectedPortfolioProjection: (state) => {
        if (!state.portfolioProjections?.portfolio?.projections) return null;
        return state.portfolioProjections.portfolio.projections[state.selectedProjectionPeriod];
    },

    // Raw state accessors for mapGetters usage
    analysis: (state) => state.analysis,
    recommendations: (state) => state.recommendations,

    canProceed: (state) => state.canProceed,
    readinessChecks: (state) => state.readinessChecks,

    loading: (state) => state.loading,
    // Life events relevant to investment module
    upcomingLifeEvents: (state) => state.lifeEvents,
    lifeEventNetImpact: (state) => state.lifeEventImpact?.net_impact || 0,

    // Goal strategies for investment module
    activeGoalStrategies: (state) => state.goalStrategies,
    totalGoalCommitment: (state) => state.goalsSummary?.total_monthly_commitment || 0,

    error: (state) => state.error,
};

const actions = {
    // Fetch all investment data
    async fetchInvestmentData({ commit }) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await investmentService.getInvestmentData();
            commit('setAccounts', response.data.accounts);
            commit('setRiskProfile', response.data.risk_profile);
            commit('setLifeEvents', response.data.life_events || []);
            commit('setLifeEventImpact', response.data.life_event_impact || null);
            commit('setGoalStrategies', response.data.goal_strategies || []);
            commit('setGoalsSummary', response.data.goals_summary || null);
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to fetch investment data';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    // Analyse investment portfolio
    async analyseInvestment({ commit }) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await investmentService.analyzeInvestment();
            const responseData = response.data || response;

            // Guard: handle can_proceed: false
            if (responseData?.can_proceed === false) {
                commit('SET_CAN_PROCEED', false);
                commit('SET_READINESS_CHECKS', responseData?.readiness_checks || null);
                commit('setAnalysis', null);
                commit('setRecommendations', null);
                return response;
            }

            commit('SET_CAN_PROCEED', true);
            commit('SET_READINESS_CHECKS', null);
            commit('setAnalysis', responseData.analysis);
            commit('setRecommendations', responseData.recommendations);
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Analysis failed';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    // Fetch recommendations (uses analyze endpoint which returns recommendations)
    async fetchRecommendations({ commit }) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            // Use analyzeInvestment endpoint which returns { success, data: { analysis, recommendations } }
            // Service already returns response.data, so we access .data.analysis and .data.recommendations
            const response = await investmentService.analyzeInvestment();
            const responseData = response.data || response;

            // Guard: handle can_proceed: false
            if (responseData?.can_proceed === false) {
                commit('SET_CAN_PROCEED', false);
                commit('SET_READINESS_CHECKS', responseData?.readiness_checks || null);
                commit('setAnalysis', null);
                commit('setRecommendations', null);
                return response;
            }

            commit('SET_CAN_PROCEED', true);
            commit('SET_READINESS_CHECKS', null);
            commit('setAnalysis', responseData?.analysis);
            // Store full recommendations object { recommendation_count, recommendations: [] }
            commit('setRecommendations', responseData?.recommendations);
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to fetch recommendations';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    // Run scenario analysis
    async runScenario({ commit }, scenarioData) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await investmentService.runScenario(scenarioData);
            commit('setScenarios', response.data);
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Scenario analysis failed';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    // Start Monte Carlo simulation
    async startMonteCarlo({ commit }, params) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await investmentService.startMonteCarlo(params);
            const jobId = response.data.job_id;
            commit('setMonteCarloStatus', { jobId, status: 'queued' });
            return { jobId, response };
        } catch (error) {
            const errorMessage = error.message || 'Failed to start Monte Carlo simulation';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    // Poll for Monte Carlo results
    async pollMonteCarloResults({ commit }, jobId) {
        commit('setMonteCarloStatus', { jobId, status: 'running' });
        commit('setError', null);

        try {
            const response = await pollMonteCarloJob(
                () => investmentService.getMonteCarloResults(jobId),
                {
                    onProgress: (attempt, res) => {
                        const status = res?.data?.data?.status;
                        if (status) {
                            commit('setMonteCarloStatus', { jobId, status });
                        }
                    }
                }
            );

            const results = response.data.data.results;
            commit('setMonteCarloResults', { jobId, results });
            commit('setMonteCarloStatus', { jobId, status: 'completed' });
            return results;
        } catch (error) {
            const errorMessage = error.message || 'Failed to retrieve Monte Carlo results';
            commit('setMonteCarloStatus', { jobId, status: 'failed' });
            commit('setError', errorMessage);
            throw error;
        }
    },

    // Fetch accounts only (lightweight alternative to fetchInvestmentData)
    async fetchAccounts({ commit }) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await investmentService.getInvestmentData();
            commit('setAccounts', response.data.accounts);
            return response.data.accounts;
        } catch (error) {
            const errorMessage = error.message || 'Failed to fetch accounts';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    // Account actions
    async createAccount({ commit, dispatch }, accountData) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await investmentService.createAccount(accountData);
            commit('addAccount', response.data);
            // Refresh analysis after adding account
            await dispatch('analyseInvestment');
            // Refresh net worth and recommendations
            await dispatch('netWorth/refreshNetWorth', null, { root: true });
            dispatch('recommendations/fetchRecommendations', {}, { root: true });
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to create account';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    async updateAccount({ commit, dispatch }, { id, accountData }) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await investmentService.updateAccount(id, accountData);
            commit('updateAccount', response.data);
            await dispatch('analyseInvestment');
            // Refresh net worth and recommendations
            await dispatch('netWorth/refreshNetWorth', null, { root: true });
            dispatch('recommendations/fetchRecommendations', {}, { root: true });
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to update account';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    async deleteAccount({ commit, dispatch }, id) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await investmentService.deleteAccount(id);
            commit('removeAccount', id);
            await dispatch('analyseInvestment');
            // Refresh net worth and recommendations
            await dispatch('netWorth/refreshNetWorth', null, { root: true });
            dispatch('recommendations/fetchRecommendations', {}, { root: true });
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to delete account';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    // Holdings actions
    async createHolding({ commit, dispatch }, holdingData) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await investmentService.createHolding(holdingData);
            commit('addHolding', {
                accountId: holdingData.investment_account_id,
                holding: response.data
            });
            await dispatch('analyseInvestment');
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to create holding';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    async updateHolding({ commit, dispatch }, { id, holdingData }) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await investmentService.updateHolding(id, holdingData);
            commit('updateHolding', response.data);
            await dispatch('analyseInvestment');
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to update holding';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    async deleteHolding({ commit, dispatch }, id) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await investmentService.deleteHolding(id);
            commit('removeHolding', id);
            await dispatch('analyseInvestment');
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to delete holding';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    // Risk profile action
    async saveRiskProfile({ commit, dispatch }, profileData) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await investmentService.saveRiskProfile(profileData);
            commit('setRiskProfile', response.data);
            await dispatch('analyseInvestment');
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to save risk profile';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    async updateKnowledgeLevel({ commit }, level) {
        try {
            const response = await investmentService.saveRiskProfile({
                knowledge_level: level,
            });
            commit('setRiskProfile', response.data);
            return response;
        } catch (error) {
            throw error;
        }
    },

    // Portfolio Projections (Performance tab)
    async fetchPortfolioProjections({ commit, state }, params = {}) {
        commit('setProjectionsLoading', true);
        commit('setProjectionsError', null);

        try {
            const response = await investmentService.getPortfolioProjections({
                projection_periods: params.periods || [5, 10, 20, 30],
                selected_period: params.selectedPeriod || state.selectedProjectionPeriod,
                contribution_overrides: params.contributionOverrides || null,
            });

            if (response.success && response.data) {
                commit('setPortfolioProjections', response.data);
                if (params.selectedPeriod) {
                    commit('setSelectedProjectionPeriod', params.selectedPeriod);
                }
            }
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to fetch portfolio projections';
            commit('setProjectionsError', errorMessage);
            throw error;
        } finally {
            commit('setProjectionsLoading', false);
        }
    },

    setSelectedProjectionPeriod({ commit }, period) {
        commit('setSelectedProjectionPeriod', period);
    },
};

const mutations = {
    setAccounts(state, accounts) {
        state.accounts = accounts;
    },

    setRiskProfile(state, profile) {
        state.riskProfile = profile;
    },

    setAnalysis(state, analysis) {
        state.analysis = analysis;
    },

    setRecommendations(state, recommendations) {
        state.recommendations = recommendations;
    },

    setLifeEvents(state, events) {
        state.lifeEvents = events;
    },

    setLifeEventImpact(state, impact) {
        state.lifeEventImpact = impact;
    },

    setGoalStrategies(state, strategies) {
        state.goalStrategies = strategies;
    },

    setGoalsSummary(state, summary) {
        state.goalsSummary = summary;
    },

    setScenarios(state, scenarios) {
        state.scenarios = scenarios;
    },

    setOptimizationResult(state, result) {
        state.optimizationResult = result;
    },

    setMonteCarloResults(state, { jobId, results }) {
        state.monteCarloResults = {
            ...state.monteCarloResults,
            [jobId]: results
        };
    },

    setMonteCarloStatus(state, { jobId, status }) {
        state.monteCarloStatus = {
            ...state.monteCarloStatus,
            [jobId]: status
        };
    },

    addAccount(state, account) {
        state.accounts.push(account);
    },

    updateAccount(state, account) {
        const index = state.accounts.findIndex(a => a.id === account.id);
        if (index !== -1) {
            state.accounts.splice(index, 1, account);
        }
    },

    removeAccount(state, id) {
        const index = state.accounts.findIndex(a => a.id === id);
        if (index !== -1) {
            state.accounts.splice(index, 1);
        }
    },

    addHolding(state, { accountId, holding }) {
        const account = state.accounts.find(a => a.id === accountId);
        if (account) {
            if (!account.holdings) {
                account.holdings = [];
            }
            account.holdings.push(holding);
        }
    },

    updateHolding(state, holding) {
        for (const account of state.accounts) {
            if (account.holdings) {
                const index = account.holdings.findIndex(h => h.id === holding.id);
                if (index !== -1) {
                    account.holdings.splice(index, 1, holding);
                    break;
                }
            }
        }
    },

    removeHolding(state, id) {
        for (const account of state.accounts) {
            if (account.holdings) {
                const index = account.holdings.findIndex(h => h.id === id);
                if (index !== -1) {
                    account.holdings.splice(index, 1);
                    break;
                }
            }
        }
    },

    SET_CAN_PROCEED(state, canProceed) {
        state.canProceed = canProceed;
    },

    SET_READINESS_CHECKS(state, checks) {
        state.readinessChecks = checks;
    },

    setLoading(state, loading) {
        state.loading = loading;
    },

    setError(state, error) {
        state.error = error;
    },

    // Portfolio projections mutations
    setPortfolioProjections(state, projections) {
        state.portfolioProjections = projections;
    },

    setProjectionsLoading(state, loading) {
        state.projectionsLoading = loading;
    },

    setProjectionsError(state, error) {
        state.projectionsError = error;
    },

    setSelectedProjectionPeriod(state, period) {
        state.selectedProjectionPeriod = period;
    },
};

export default {
    namespaced: true,
    state,
    getters,
    actions,
    mutations,
};
