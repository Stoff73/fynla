import savingsService from '@/services/savingsService';

import logger from '@/utils/logger';
const state = {
    accounts: [],
    expenditureProfile: null,
    analysis: null,
    isaAllowance: null,
    recommendations: [],
    lifeEvents: [],
    lifeEventImpact: null,
    goalStrategies: [],
    goalsSummary: null,
    canProceed: true,
    readinessChecks: null,
    loading: false,
    error: null,
};

const getters = {
    // Get total savings across all accounts (user's share for joint accounts)
    totalSavings: (state) => {
        return state.accounts.reduce((sum, account) => {
            const balance = parseFloat(account.current_balance || 0);
            const isJoint = account.ownership_type === 'joint' || account.ownership_type === 'tenants_in_common';
            if (isJoint && account.ownership_percentage) {
                return sum + (balance * (account.ownership_percentage / 100));
            }
            return sum + balance;
        }, 0);
    },

    // Get total emergency fund (only accounts marked as emergency fund, user's share for joint)
    emergencyFundTotal: (state) => {
        return state.accounts
            .filter(account => account.is_emergency_fund)
            .reduce((sum, account) => {
                const balance = parseFloat(account.current_balance || 0);
                const isJoint = account.ownership_type === 'joint' || account.ownership_type === 'tenants_in_common';
                if (isJoint && account.ownership_percentage) {
                    return sum + (balance * (account.ownership_percentage / 100));
                }
                return sum + balance;
            }, 0);
    },

    // Get emergency fund runway in months
    emergencyFundRunway: (state, getters) => {
        const monthlyExpenditure = getters.monthlyExpenditure;
        if (monthlyExpenditure === 0) return 0;
        return getters.emergencyFundTotal / monthlyExpenditure;
    },

    // Get ISA allowance remaining
    // Note: Returns 0 if ISA data not loaded - ensure fetchISAAllowance is called on init
    isaAllowanceRemaining: (state) => {
        if (!state.isaAllowance) {
            // Return 0 instead of hardcoded fallback - forces proper API fetch
            console.warn('ISA allowance not loaded - call fetchISAAllowance first');
            return 0;
        }

        const cashISAUsed = state.isaAllowance.cash_isa_used || 0;
        const stocksISAUsed = state.isaAllowance.stocks_shares_isa_used || 0;
        const totalAllowance = state.isaAllowance.total_allowance || 0;

        return totalAllowance - cashISAUsed - stocksISAUsed;
    },

    // Get ISA usage percentage
    isaUsagePercent: (state, getters) => {
        if (!state.isaAllowance) return 0;

        const totalAllowance = state.isaAllowance.total_allowance || 0;
        if (totalAllowance === 0) return 0;
        const remaining = getters.isaAllowanceRemaining;

        return Math.round(((totalAllowance - remaining) / totalAllowance) * 100);
    },

    // Get current year ISA subscription (Cash ISA)
    currentYearISASubscription: (state) => {
        return state.isaAllowance?.cash_isa_used || 0;
    },

    // Get total ISA balances (user's share for joint accounts)
    totalISABalance: (state) => {
        return state.accounts
            .filter(account => account.is_isa)
            .reduce((sum, account) => {
                const balance = parseFloat(account.current_balance || 0);
                const isJoint = account.ownership_type === 'joint' || account.ownership_type === 'tenants_in_common';
                if (isJoint && account.ownership_percentage) {
                    return sum + (balance * (account.ownership_percentage / 100));
                }
                return sum + balance;
            }, 0);
    },

    // Get accounts by access type
    accountsByAccessType: (state) => {
        const grouped = {
            immediate: [],
            notice: [],
            fixed: [],
        };

        state.accounts.forEach(account => {
            const accessType = account.access_type || 'immediate';
            if (grouped[accessType]) {
                grouped[accessType].push(account);
            }
        });

        return grouped;
    },

    // Get monthly expenditure from profile
    monthlyExpenditure: (state) => {
        return state.expenditureProfile?.total_monthly_expenditure || 0;
    },

    // Life events relevant to savings module
    upcomingLifeEvents: (state) => state.lifeEvents,
    lifeEventNetImpact: (state) => state.lifeEventImpact?.net_impact || 0,

    // Goal strategies for savings module
    activeGoalStrategies: (state) => state.goalStrategies,
    totalGoalCommitment: (state) => state.goalsSummary?.total_monthly_commitment || 0,
    goalsOnTrackCount: (state) => {
        return state.goalStrategies.filter(s => s.goal?.is_on_track).length;
    },

    canProceed: (state) => state.canProceed,
    readinessChecks: (state) => state.readinessChecks,

    loading: (state) => state.loading,
    error: (state) => state.error,
};

const actions = {
    // Fetch all savings data
    async fetchSavingsData({ commit }) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await savingsService.getSavingsData();
            const data = response.data || response;

            // Guard: handle can_proceed: false
            if (data?.can_proceed === false) {
                commit('SET_CAN_PROCEED', false);
                commit('SET_READINESS_CHECKS', data?.readiness_checks || null);
                return response;
            }

            commit('SET_CAN_PROCEED', true);
            commit('SET_READINESS_CHECKS', null);
            commit('setAccounts', data.accounts || []);
            commit('setExpenditureProfile', data.expenditure_profile || null);
            commit('setAnalysis', data.analysis || null);
            commit('setISAAllowance', data.isa_allowance || null);
            commit('setLifeEvents', data.life_events || []);
            commit('setLifeEventImpact', data.life_event_impact || null);
            commit('setGoalStrategies', data.goal_strategies || []);
            commit('setGoalsSummary', data.goals_summary || null);
            return response;
        } catch (error) {
            const errorMessage = error.response?.data?.message || error.message || 'Failed to fetch savings data';
            commit('setError', errorMessage);
            logger.error('Savings data fetch error:', error);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    // Analyse savings
    async analyseSavings({ commit }, data) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await savingsService.analyzeSavings(data);
            const responseData = response.data || response;

            // Guard: handle can_proceed: false
            if (responseData?.can_proceed === false) {
                commit('SET_CAN_PROCEED', false);
                commit('SET_READINESS_CHECKS', responseData?.readiness_checks || null);
                commit('setAnalysis', null);
                return response;
            }

            commit('SET_CAN_PROCEED', true);
            commit('SET_READINESS_CHECKS', null);
            commit('setAnalysis', responseData.analysis);
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Analysis failed';
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
            const response = await savingsService.createAccount(accountData);
            const account = response.data || response;
            commit('addAccount', account);
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

    async fetchAccount({ commit }, id) {
        try {
            const response = await savingsService.getAccount(id);
            return response.data || response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to fetch account';
            commit('setError', errorMessage);
            throw error;
        }
    },

    async updateAccount({ commit, dispatch }, { id, accountData }) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await savingsService.updateAccount(id, accountData);
            const account = response.data || response;
            commit('updateAccount', account);
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
            const response = await savingsService.deleteAccount(id);
            commit('removeAccount', id);
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

    // Expenditure profile actions
    async updateExpenditureProfile({ commit }, profileData) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await savingsService.updateExpenditureProfile(profileData);
            commit('setExpenditureProfile', response.data.profile);
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to update expenditure profile';
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

    setExpenditureProfile(state, profile) {
        state.expenditureProfile = profile;
    },

    setAnalysis(state, analysis) {
        state.analysis = analysis;
    },

    setISAAllowance(state, allowance) {
        state.isaAllowance = allowance;
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
};

export default {
    namespaced: true,
    state,
    getters,
    actions,
    mutations,
};
