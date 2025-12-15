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
};

const getters = {
    totalPensionWealth: (state) => {
        const dcTotal = state.dcPensions.reduce((sum, p) => sum + parseFloat(p.current_fund_value || 0), 0);
        // DB pensions don't have a "value" - they're income streams
        // State pension also doesn't have a fund value
        return dcTotal;
    },

    retirementReadinessScore: (state) => {
        return state.analysis?.readiness_score || 0;
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
};

export default {
    namespaced: true,
    state,
    mutations,
    actions,
    getters,
};
