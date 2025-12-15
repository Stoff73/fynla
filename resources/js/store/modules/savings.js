import savingsService from '@/services/savingsService';

const state = {
    accounts: [],
    goals: [],
    expenditureProfile: null,
    analysis: null,
    isaAllowance: null,
    recommendations: [],
    loading: false,
    error: null,
};

const getters = {
    // Get total savings across all accounts
    totalSavings: (state) => {
        return state.accounts.reduce((sum, account) => {
            return sum + parseFloat(account.current_balance || 0);
        }, 0);
    },

    // Get total emergency fund (only accounts marked as emergency fund)
    emergencyFundTotal: (state) => {
        return state.accounts
            .filter(account => account.is_emergency_fund)
            .reduce((sum, account) => {
                return sum + parseFloat(account.current_balance || 0);
            }, 0);
    },

    // Get emergency fund runway in months
    emergencyFundRunway: (state, getters) => {
        const monthlyExpenditure = getters.monthlyExpenditure;
        if (monthlyExpenditure === 0) return 0;
        return getters.emergencyFundTotal / monthlyExpenditure;
    },

    // Get ISA allowance remaining
    isaAllowanceRemaining: (state) => {
        if (!state.isaAllowance) return 20000; // Default UK ISA allowance

        const cashISAUsed = state.isaAllowance.cash_isa_used || 0;
        const stocksISAUsed = state.isaAllowance.stocks_shares_isa_used || 0;
        const totalAllowance = state.isaAllowance.total_allowance || 20000;

        return totalAllowance - cashISAUsed - stocksISAUsed;
    },

    // Get ISA usage percentage
    isaUsagePercent: (state, getters) => {
        if (!state.isaAllowance) return 0;

        const totalAllowance = state.isaAllowance.total_allowance || 20000;
        const remaining = getters.isaAllowanceRemaining;

        return Math.round(((totalAllowance - remaining) / totalAllowance) * 100);
    },

    // Get current year ISA subscription (Cash ISA)
    currentYearISASubscription: (state) => {
        return state.isaAllowance?.cash_isa_used || 0;
    },

    // Get goals that are on track
    goalsOnTrack: (state) => {
        return state.goals.filter(goal => {
            const progress = (goal.current_saved / goal.target_amount) * 100;
            const now = new Date();
            const targetDate = new Date(goal.target_date);
            const totalMonths = (targetDate - new Date(goal.created_at)) / (1000 * 60 * 60 * 24 * 30);
            const elapsedMonths = (now - new Date(goal.created_at)) / (1000 * 60 * 60 * 24 * 30);
            const expectedProgress = (elapsedMonths / totalMonths) * 100;

            return progress >= expectedProgress;
        });
    },

    // Get goals that are off track
    goalsOffTrack: (state, getters) => {
        return state.goals.filter(goal => !getters.goalsOnTrack.includes(goal));
    },

    // Get total ISA balances
    totalISABalance: (state) => {
        return state.accounts
            .filter(account => account.is_isa)
            .reduce((sum, account) => sum + parseFloat(account.current_balance || 0), 0);
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
            commit('setAccounts', data.accounts || []);
            commit('setGoals', data.goals || []);
            commit('setExpenditureProfile', data.expenditure_profile || null);
            commit('setAnalysis', data.analysis || null);
            commit('setISAAllowance', data.isa_allowance || null);
            return response;
        } catch (error) {
            const errorMessage = error.response?.data?.message || error.message || 'Failed to fetch savings data';
            commit('setError', errorMessage);
            console.error('Savings data fetch error:', error);
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
            commit('setAnalysis', response.data.analysis);
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
            const response = await savingsService.getRecommendations();
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

    // Account actions
    async createAccount({ commit }, accountData) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await savingsService.createAccount(accountData);
            const account = response.data || response;
            commit('addAccount', account);
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

    async updateAccount({ commit }, { id, accountData }) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await savingsService.updateAccount(id, accountData);
            const account = response.data || response;
            commit('updateAccount', account);
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to update account';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    async deleteAccount({ commit }, id) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await savingsService.deleteAccount(id);
            commit('removeAccount', id);
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to delete account';
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
            const response = await savingsService.createGoal(goalData);
            const goal = response.data || response;
            commit('addGoal', goal);
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
            const response = await savingsService.updateGoal(id, goalData);
            const goal = response.data || response;
            commit('updateGoal', goal);
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
            const response = await savingsService.deleteGoal(id);
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

    async updateGoalProgress({ commit }, { id, amount }) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await savingsService.updateGoalProgress(id, amount);
            const goal = response.data || response;
            commit('updateGoal', goal);
            return response;
        } catch (error) {
            const errorMessage = error.message || 'Failed to update goal progress';
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

    setGoals(state, goals) {
        state.goals = goals;
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
};

export default {
    namespaced: true,
    state,
    getters,
    actions,
    mutations,
};
