import investmentService from '@/services/investmentService';
import { pollMonteCarloJob } from '@/utils/poller';

const state = {
    accounts: [],
    goals: [],
    riskProfile: null,
    analysis: null,
    recommendations: [],
    monteCarloResults: {},      // Keyed by jobId
    monteCarloStatus: {},        // Keyed by jobId
    monteCarloResultsByGoal: {}, // Keyed by goalId
    optimizationResult: null,    // Portfolio optimization result
    scenarios: null,
    investmentPlan: null,        // Latest investment plan
    investmentPlans: [],         // Historical plans
    investmentRecommendations: [],    // Phase 1.2: Tracked recommendations
    recommendationStats: null,         // Phase 1.2: Recommendation statistics
    recommendationsDashboard: null,    // Phase 1.2: Dashboard data
    scenarioTemplates: [],             // Phase 1.3: Pre-built scenario templates
    investmentScenarios: [],           // Phase 1.3: User's scenarios
    scenarioStats: null,               // Phase 1.3: Scenario statistics
    scenarioComparison: null,          // Phase 1.3: Comparison data
    contributionOptimization: null,    // Phase 2.1: Contribution optimization results
    assetLocationAnalysis: null,       // Phase 2.6: Asset location optimization
    performanceAttribution: null,      // Phase 2.7: Performance attribution
    benchmarkComparison: null,         // Phase 2.7: Benchmark comparison
    goalProjections: {},               // Phase 2.3: Goal projections by goal ID
    feeAnalysis: null,                 // Phase 2.5: Detailed fee analysis
    loading: false,
    error: null,
};

const getters = {
    // Get accounts
    accounts: (state) => state.accounts,

    // Get goals
    goals: (state) => state.goals,

    // Get total portfolio value across all accounts
    totalPortfolioValue: (state) => {
        if (state.analysis?.portfolio_summary?.total_value) {
            return state.analysis.portfolio_summary.total_value;
        }
        return state.accounts.reduce((sum, account) => {
            return sum + parseFloat(account.current_value || 0);
        }, 0);
    },

    // Get YTD return percentage
    ytdReturn: (state) => {
        return state.analysis?.returns?.ytd_return || 0;
    },

    // Get asset allocation data
    assetAllocation: (state) => {
        return state.analysis?.asset_allocation || [];
    },

    // Get total annual fees
    totalFees: (state) => {
        return state.analysis?.fee_analysis?.total_annual_fees || 0;
    },

    // Get fee drag percentage
    feeDragPercent: (state) => {
        return state.analysis?.fee_analysis?.fee_drag_percent || 0;
    },

    // Get unrealised gains
    unrealisedGains: (state) => {
        return state.analysis?.tax_efficiency?.unrealised_gains?.total_unrealised_gains || 0;
    },

    // Get tax efficiency score
    taxEfficiencyScore: (state) => {
        return state.analysis?.tax_efficiency?.efficiency_score || 0;
    },

    // Get diversification score
    diversificationScore: (state) => {
        return state.analysis?.diversification_score || 0;
    },

    // Get risk level
    riskLevel: (state) => {
        return state.analysis?.risk_metrics?.risk_level || 'medium';
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

    // Get total ISA value
    totalISAValue: (state, getters) => {
        return getters.isaAccounts.reduce((sum, account) => {
            return sum + parseFloat(account.current_value || 0);
        }, 0);
    },

    // Get ISA percentage of total portfolio
    isaPercentage: (state, getters) => {
        const totalValue = getters.totalPortfolioValue;
        if (totalValue === 0) return 0;
        return Math.round((getters.totalISAValue / totalValue) * 100);
    },

    // Get current year ISA contributions (for allowance tracking)
    totalISAContributions: (state, getters) => {
        return getters.isaAccounts.reduce((sum, account) => {
            return sum + parseFloat(account.contributions_ytd || 0);
        }, 0);
    },

    // Get ISA allowance percentage used (based on contributions, not value)
    isaAllowancePercentage: (state, getters) => {
        const isaAllowance = 20000; // 2024/25 allowance
        const contributions = getters.totalISAContributions;
        return (contributions / isaAllowance) * 100;
    },

    // Get current year ISA subscription (S&S ISA) from ISA accounts
    investmentISASubscription: (state, getters) => {
        return getters.isaAccounts.reduce((sum, account) => {
            return sum + parseFloat(account.isa_subscription_current_year || 0);
        }, 0);
    },

    // Get goals that are on track
    goalsOnTrack: (state) => {
        return state.goals.filter(goal => {
            const progress = (goal.current_value / goal.target_amount) * 100;
            // Simplified - in real app would calculate based on time elapsed
            return progress >= 50;
        });
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

    // Investment Recommendations getters (Phase 1.2)
    investmentRecommendations: (state) => state.investmentRecommendations,

    recommendationStats: (state) => state.recommendationStats,

    recommendationsDashboard: (state) => state.recommendationsDashboard,

    // Get recommendations by status
    pendingRecommendations: (state) => {
        return state.investmentRecommendations.filter(r => r.status === 'pending');
    },

    inProgressRecommendations: (state) => {
        return state.investmentRecommendations.filter(r => r.status === 'in_progress');
    },

    completedRecommendations: (state) => {
        return state.investmentRecommendations.filter(r => r.status === 'completed');
    },

    // Get high priority recommendations
    highPriorityRecommendations: (state) => {
        return state.investmentRecommendations.filter(r => r.priority <= 3);
    },

    // Get recommendations by category
    getRecommendationsByCategory: (state) => (category) => {
        return state.investmentRecommendations.filter(r => r.category === category);
    },

    // Investment Scenarios getters (Phase 1.3)
    scenarioTemplates: (state) => state.scenarioTemplates,

    investmentScenarios: (state) => state.investmentScenarios,

    scenarioStats: (state) => state.scenarioStats,

    scenarioComparison: (state) => state.scenarioComparison,

    // Get scenarios by status
    draftScenarios: (state) => {
        return state.investmentScenarios.filter(s => s.status === 'draft');
    },

    runningScenarios: (state) => {
        return state.investmentScenarios.filter(s => s.status === 'running');
    },

    completedScenarios: (state) => {
        return state.investmentScenarios.filter(s => s.status === 'completed');
    },

    savedScenarios: (state) => {
        return state.investmentScenarios.filter(s => s.is_saved);
    },

    // Get scenarios by type
    getScenariosByType: (state) => (type) => {
        return state.investmentScenarios.filter(s => s.scenario_type === type);
    },

    // Phase 2 getters
    contributionOptimization: (state) => state.contributionOptimization,

    assetLocationAnalysis: (state) => state.assetLocationAnalysis,

    performanceAttribution: (state) => state.performanceAttribution,

    benchmarkComparison: (state) => state.benchmarkComparison,

    getGoalProjection: (state) => (goalId) => {
        return state.goalProjections[goalId] || null;
    },

    feeAnalysis: (state) => state.feeAnalysis,

    loading: (state) => state.loading,
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
            commit('setGoals', response.data.goals);
            commit('setRiskProfile', response.data.risk_profile);
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
            commit('setAnalysis', response.data.analysis);
            commit('setRecommendations', response.data.recommendations);
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Analysis failed';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    // Fetch recommendations
    async fetchRecommendations({ commit }) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await investmentService.getRecommendations();
            commit('setRecommendations', response.data.recommendations);
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

    // Account actions
    async createAccount({ commit, dispatch }, accountData) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await investmentService.createAccount(accountData);
            commit('addAccount', response.data);
            // Refresh analysis after adding account
            await dispatch('analyseInvestment');
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

    // Goal actions
    async createGoal({ commit }, goalData) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await investmentService.createGoal(goalData);
            commit('addGoal', response.data);
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to create goal';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    async updateGoal({ commit }, { id, goalData }) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await investmentService.updateGoal(id, goalData);
            commit('updateGoal', response.data);
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to update goal';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    async deleteGoal({ commit }, id) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await investmentService.deleteGoal(id);
            commit('removeGoal', id);
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to delete goal';
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

    // Investment Plan actions (Phase 1.1)
    async generateInvestmentPlan({ commit }) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await investmentService.generateInvestmentPlan();
            if (response.success && response.data) {
                commit('setInvestmentPlan', response.data.plan);
                commit('addInvestmentPlan', {
                    id: response.data.plan_id,
                    plan_version: '1.0',
                    portfolio_health_score: response.data.portfolio_health_score,
                    generated_at: new Date().toISOString(),
                });
            }
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to generate investment plan';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    async getLatestInvestmentPlan({ commit }) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await investmentService.getLatestInvestmentPlan();
            if (response.success && response.data) {
                commit('setInvestmentPlan', response.data);
            }
            return response;
        } catch (error) {
            // 404 is expected if no plan exists yet
            if (error.response && error.response.status !== 404) {
                const errorMessage = error.message || 'Failed to load investment plan';
                commit('setError', errorMessage);
            }
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    async getAllInvestmentPlans({ commit }) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await investmentService.getAllInvestmentPlans();
            if (response.success && response.data) {
                commit('setInvestmentPlans', response.data);
            }
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to load investment plans';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    async getInvestmentPlanById({ commit }, planId) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await investmentService.getInvestmentPlanById(planId);
            if (response.success && response.data) {
                commit('setInvestmentPlan', response.data);
            }
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to load investment plan';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    async deleteInvestmentPlan({ commit }, planId) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await investmentService.deleteInvestmentPlan(planId);
            commit('removeInvestmentPlan', planId);
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to delete investment plan';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    // Investment Recommendations actions (Phase 1.2)
    async fetchRecommendationsDashboard({ commit }) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await investmentService.getRecommendationsDashboard();
            if (response.success && response.data) {
                commit('setRecommendationsDashboard', response.data);
                commit('setRecommendationStats', response.data.stats);
            }
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to fetch recommendations dashboard';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    async fetchInvestmentRecommendations({ commit }, filters = {}) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await investmentService.getRecommendations(filters);
            if (response.success && response.data) {
                commit('setInvestmentRecommendations', response.data.recommendations);
                commit('setRecommendationStats', response.data.stats);
            }
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to fetch recommendations';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    async createInvestmentRecommendation({ commit }, data) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await investmentService.createRecommendation(data);
            if (response.success && response.data) {
                commit('addInvestmentRecommendation', response.data);
            }
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to create recommendation';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    async updateInvestmentRecommendation({ commit }, { id, data }) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await investmentService.updateRecommendation(id, data);
            if (response.success && response.data) {
                commit('updateInvestmentRecommendation', response.data);
            }
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to update recommendation';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    async updateRecommendationStatus({ commit }, { id, status, dismissalReason = null }) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await investmentService.updateRecommendationStatus(id, status, dismissalReason);
            if (response.success && response.data) {
                commit('updateInvestmentRecommendation', response.data);
            }
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to update recommendation status';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    async bulkUpdateRecommendationStatus({ commit, dispatch }, { ids, status, dismissalReason = null }) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await investmentService.bulkUpdateRecommendationStatus(ids, status, dismissalReason);
            // Refresh recommendations list after bulk update
            await dispatch('fetchInvestmentRecommendations');
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to bulk update recommendations';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    async deleteInvestmentRecommendation({ commit }, id) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await investmentService.deleteRecommendation(id);
            commit('removeInvestmentRecommendation', id);
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to delete recommendation';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    // Investment Scenarios actions (Phase 1.3)
    async fetchScenarioTemplates({ commit }) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await investmentService.getScenarioTemplates();
            if (response.success && response.data) {
                commit('setScenarioTemplates', response.data);
            }
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to fetch scenario templates';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    async fetchInvestmentScenarios({ commit }, filters = {}) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await investmentService.getScenarios(filters);
            if (response.success && response.data) {
                commit('setInvestmentScenarios', response.data.scenarios);
                commit('setScenarioStats', response.data.stats);
            }
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to fetch scenarios';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    async createInvestmentScenario({ commit }, data) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await investmentService.createScenario(data);
            if (response.success && response.data) {
                commit('addInvestmentScenario', response.data);
            }
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to create scenario';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    async updateInvestmentScenario({ commit }, { id, data }) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await investmentService.updateScenario(id, data);
            if (response.success && response.data) {
                commit('updateInvestmentScenario', response.data);
            }
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to update scenario';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    async runInvestmentScenario({ commit }, id) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await investmentService.runScenario(id);
            if (response.success && response.data) {
                // Update scenario status to running
                commit('updateInvestmentScenario', {
                    id,
                    status: 'running',
                    monte_carlo_job_id: response.data.job_id,
                });
            }
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to run scenario';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    async getScenarioResults({ commit }, id) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await investmentService.getScenarioResults(id);
            if (response.success && response.data && response.data.status === 'completed') {
                // Update scenario with results
                commit('updateInvestmentScenario', {
                    id,
                    status: 'completed',
                    results: response.data.results,
                    completed_at: response.data.completed_at,
                });
            }
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to get scenario results';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    async compareInvestmentScenarios({ commit }, scenarioIds) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await investmentService.compareScenarios(scenarioIds);
            if (response.success && response.data) {
                commit('setScenarioComparison', response.data);
            }
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to compare scenarios';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    async saveInvestmentScenario({ commit }, id) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await investmentService.saveScenario(id);
            if (response.success && response.data) {
                commit('updateInvestmentScenario', response.data);
            }
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to save scenario';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    async unsaveInvestmentScenario({ commit }, id) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await investmentService.unsaveScenario(id);
            if (response.success && response.data) {
                commit('updateInvestmentScenario', response.data);
            }
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to unsave scenario';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    async deleteInvestmentScenario({ commit }, id) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await investmentService.deleteScenario(id);
            commit('removeInvestmentScenario', id);
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to delete scenario';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    // Phase 2 actions

    // Phase 2.1: Contribution Planning
    async optimiseContributions({ commit }, inputs) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await investmentService.optimiseContributions(inputs);
            if (response.success && response.data) {
                commit('setContributionOptimization', response.data);
            }
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to optimise contributions';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    async calculateAffordability({ commit }, inputs) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await investmentService.calculateAffordability(inputs);
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to calculate affordability';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    async analyseLumpSumVsDCA({ commit }, inputs) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await investmentService.analyzeLumpSumVsDCA(inputs);
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to analyse lump sum vs DCA';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    // Phase 2.6: Asset Location & Tax Efficiency
    async analyseAssetLocation({ commit }) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await investmentService.analyzeAssetLocation();
            if (response.success && response.data) {
                commit('setAssetLocationAnalysis', response.data);
            }
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to analyse asset location';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    // Phase 2.7: Performance Attribution
    async analysePerformanceAttribution({ commit }, params = {}) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await investmentService.analyzePerformanceAttribution(params);
            if (response.success && response.data) {
                commit('setPerformanceAttribution', response.data);
            }
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to analyse performance attribution';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    async compareBenchmarks({ commit }, benchmarkIds) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await investmentService.compareBenchmarks(benchmarkIds);
            if (response.success && response.data) {
                commit('setBenchmarkComparison', response.data);
            }
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to compare benchmarks';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    // Phase 2.3: Goal Projection
    async projectGoal({ commit }, { goalId, params }) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await investmentService.projectGoal(goalId, params);
            if (response.success && response.data) {
                commit('setGoalProjection', { goalId, projection: response.data });
            }
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to project goal';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    // Phase 2.5: Fee Analysis
    async analyseFees({ commit }) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await investmentService.analyzeFees();
            if (response.success && response.data) {
                commit('setFeeAnalysis', response.data);
            }
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to analyse fees';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },
};

const mutations = {
    setAccounts(state, accounts) {
        state.accounts = accounts;
    },

    setGoals(state, goals) {
        state.goals = goals;
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

    SET_MONTE_CARLO_RESULT(state, { goalId, result }) {
        // Store Monte Carlo results keyed by goal ID
        if (!state.monteCarloResultsByGoal) {
            state.monteCarloResultsByGoal = {};
        }
        state.monteCarloResultsByGoal = {
            ...state.monteCarloResultsByGoal,
            [goalId]: result
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

    addGoal(state, goal) {
        state.goals.push(goal);
    },

    updateGoal(state, goal) {
        const index = state.goals.findIndex(g => g.id === goal.id);
        if (index !== -1) {
            state.goals.splice(index, 1, goal);
        }
    },

    removeGoal(state, id) {
        const index = state.goals.findIndex(g => g.id === id);
        if (index !== -1) {
            state.goals.splice(index, 1);
        }
    },

    setLoading(state, loading) {
        state.loading = loading;
    },

    setError(state, error) {
        state.error = error;
    },

    // Investment Plan mutations
    setInvestmentPlan(state, plan) {
        state.investmentPlan = plan;
    },

    setInvestmentPlans(state, plans) {
        state.investmentPlans = plans;
    },

    addInvestmentPlan(state, plan) {
        state.investmentPlans.unshift(plan);
    },

    removeInvestmentPlan(state, planId) {
        const index = state.investmentPlans.findIndex(p => p.id === planId);
        if (index !== -1) {
            state.investmentPlans.splice(index, 1);
        }
        // Clear latest plan if it matches
        if (state.investmentPlan && state.investmentPlan.id === planId) {
            state.investmentPlan = null;
        }
    },

    // Investment Recommendations mutations (Phase 1.2)
    setInvestmentRecommendations(state, recommendations) {
        state.investmentRecommendations = recommendations;
    },

    setRecommendationStats(state, stats) {
        state.recommendationStats = stats;
    },

    setRecommendationsDashboard(state, dashboard) {
        state.recommendationsDashboard = dashboard;
    },

    addInvestmentRecommendation(state, recommendation) {
        state.investmentRecommendations.push(recommendation);
    },

    updateInvestmentRecommendation(state, recommendation) {
        const index = state.investmentRecommendations.findIndex(r => r.id === recommendation.id);
        if (index !== -1) {
            state.investmentRecommendations.splice(index, 1, recommendation);
        }
    },

    removeInvestmentRecommendation(state, id) {
        const index = state.investmentRecommendations.findIndex(r => r.id === id);
        if (index !== -1) {
            state.investmentRecommendations.splice(index, 1);
        }
    },

    // Investment Scenarios mutations (Phase 1.3)
    setScenarioTemplates(state, templates) {
        state.scenarioTemplates = templates;
    },

    setInvestmentScenarios(state, scenarios) {
        state.investmentScenarios = scenarios;
    },

    setScenarioStats(state, stats) {
        state.scenarioStats = stats;
    },

    setScenarioComparison(state, comparison) {
        state.scenarioComparison = comparison;
    },

    addInvestmentScenario(state, scenario) {
        state.investmentScenarios.unshift(scenario);
    },

    updateInvestmentScenario(state, scenario) {
        const index = state.investmentScenarios.findIndex(s => s.id === scenario.id);
        if (index !== -1) {
            state.investmentScenarios.splice(index, 1, { ...state.investmentScenarios[index], ...scenario });
        }
    },

    removeInvestmentScenario(state, id) {
        const index = state.investmentScenarios.findIndex(s => s.id === id);
        if (index !== -1) {
            state.investmentScenarios.splice(index, 1);
        }
    },

    // Phase 2 mutations
    setContributionOptimization(state, data) {
        state.contributionOptimization = data;
    },

    setAssetLocationAnalysis(state, data) {
        state.assetLocationAnalysis = data;
    },

    setPerformanceAttribution(state, data) {
        state.performanceAttribution = data;
    },

    setBenchmarkComparison(state, data) {
        state.benchmarkComparison = data;
    },

    setGoalProjection(state, { goalId, projection }) {
        state.goalProjections = {
            ...state.goalProjections,
            [goalId]: projection
        };
    },

    setFeeAnalysis(state, data) {
        state.feeAnalysis = data;
    },
};

export default {
    namespaced: true,
    state,
    getters,
    actions,
    mutations,
};
