import goalsService from '@/services/goalsService';

const state = {
    goals: [],
    summary: {
        total_goals: 0,
        on_track_count: 0,
        total_target: 0,
        total_current: 0,
        overall_progress: 0,
    },
    topGoals: [],
    byModule: {
        savings: [],
        investment: [],
        property: [],
        retirement: [],
    },
    bestStreak: 0,
    analysis: null,
    recommendations: [],
    goalTypes: [],
    riskLevels: [],
    dashboardOverview: null,
    selectedGoal: null,
    loading: false,
    error: null,
};

const getters = {
    // Get all active goals
    activeGoals: (state) => {
        return state.goals.filter(goal => goal.status === 'active');
    },

    // Get goals for a specific module
    goalsForModule: (state) => (module) => {
        return state.goals.filter(goal => goal.assigned_module === module && goal.status === 'active');
    },

    // Get goals on track
    goalsOnTrack: (state) => {
        return state.goals.filter(goal => goal.status === 'active' && goal.is_on_track);
    },

    // Get goals behind schedule
    goalsBehind: (state) => {
        return state.goals.filter(goal => goal.status === 'active' && !goal.is_on_track);
    },

    // Get completed goals
    completedGoals: (state) => {
        return state.goals.filter(goal => goal.status === 'completed');
    },

    // Get total target amount for active goals
    totalTargetAmount: (state, getters) => {
        return getters.activeGoals.reduce((sum, goal) => sum + parseFloat(goal.target_amount || 0), 0);
    },

    // Get total current amount for active goals
    totalCurrentAmount: (state, getters) => {
        return getters.activeGoals.reduce((sum, goal) => sum + parseFloat(goal.current_amount || 0), 0);
    },

    // Get overall progress percentage
    overallProgress: (state, getters) => {
        const target = getters.totalTargetAmount;
        if (target === 0) return 0;
        return Math.round((getters.totalCurrentAmount / target) * 100);
    },

    // Check if user has any goals
    hasGoals: (state) => {
        return state.goals.length > 0;
    },

    // Get goals by priority
    goalsByPriority: (state) => (priority) => {
        return state.goals.filter(goal => goal.priority === priority && goal.status === 'active');
    },

    // Get critical and high priority goals
    priorityGoals: (state) => {
        return state.goals.filter(goal =>
            (goal.priority === 'critical' || goal.priority === 'high') && goal.status === 'active'
        );
    },

    // Get dashboard data
    dashboardData: (state) => {
        return state.dashboardOverview || {
            has_goals: false,
            total_goals: 0,
            on_track_count: 0,
            total_target: 0,
            total_current: 0,
            overall_progress: 0,
            top_goals: [],
            best_streak: 0,
        };
    },
};

const mutations = {
    SET_GOALS(state, goals) {
        state.goals = goals;
    },

    SET_SUMMARY(state, summary) {
        state.summary = summary;
    },

    SET_TOP_GOALS(state, topGoals) {
        state.topGoals = topGoals;
    },

    SET_BY_MODULE(state, byModule) {
        state.byModule = byModule;
    },

    SET_BEST_STREAK(state, streak) {
        state.bestStreak = streak;
    },

    SET_ANALYSIS(state, analysis) {
        state.analysis = analysis;
    },

    SET_RECOMMENDATIONS(state, recommendations) {
        state.recommendations = recommendations;
    },

    SET_GOAL_TYPES(state, types) {
        state.goalTypes = types;
    },

    SET_RISK_LEVELS(state, levels) {
        state.riskLevels = levels;
    },

    SET_DASHBOARD_OVERVIEW(state, overview) {
        state.dashboardOverview = overview;
    },

    SET_SELECTED_GOAL(state, goal) {
        state.selectedGoal = goal;
    },

    ADD_GOAL(state, goal) {
        state.goals.push(goal);
    },

    UPDATE_GOAL(state, updatedGoal) {
        const index = state.goals.findIndex(g => g.id === updatedGoal.id);
        if (index !== -1) {
            state.goals.splice(index, 1, updatedGoal);
        }
    },

    REMOVE_GOAL(state, goalId) {
        state.goals = state.goals.filter(g => g.id !== goalId);
    },

    SET_LOADING(state, loading) {
        state.loading = loading;
    },

    SET_ERROR(state, error) {
        state.error = error;
    },

    CLEAR_ERROR(state) {
        state.error = null;
    },
};

const actions = {
    /**
     * Fetch all goals for the user.
     */
    async fetchGoals({ commit }, filters = {}) {
        commit('SET_LOADING', true);
        commit('CLEAR_ERROR');

        try {
            const response = await goalsService.getGoals(filters);
            if (response.success) {
                commit('SET_GOALS', response.data.goals);
            }
            return response;
        } catch (error) {
            commit('SET_ERROR', error.response?.data?.message || 'Failed to fetch goals');
            throw error;
        } finally {
            commit('SET_LOADING', false);
        }
    },

    /**
     * Fetch comprehensive goals analysis.
     */
    async fetchAnalysis({ commit }) {
        commit('SET_LOADING', true);
        commit('CLEAR_ERROR');

        try {
            const response = await goalsService.getAnalysis();
            if (response.success) {
                const data = response.data;
                commit('SET_ANALYSIS', data);
                commit('SET_SUMMARY', data.summary || {});
                commit('SET_BY_MODULE', data.by_module || {});
                commit('SET_TOP_GOALS', data.top_goals || []);
                commit('SET_BEST_STREAK', data.streaks?.best_current_streak || 0);
                commit('SET_RECOMMENDATIONS', data.recommendations?.recommendations || []);
            }
            return response;
        } catch (error) {
            commit('SET_ERROR', error.response?.data?.message || 'Failed to fetch analysis');
            throw error;
        } finally {
            commit('SET_LOADING', false);
        }
    },

    /**
     * Fetch dashboard overview for goals card.
     */
    async fetchDashboardOverview({ commit }) {
        try {
            const response = await goalsService.getDashboardOverview();
            if (response.success) {
                commit('SET_DASHBOARD_OVERVIEW', response.data);
            }
            return response;
        } catch (error) {
            console.error('Failed to fetch goals dashboard overview:', error);
            throw error;
        }
    },

    /**
     * Fetch goal types.
     */
    async fetchGoalTypes({ commit, state }) {
        // Only fetch if not already loaded
        if (state.goalTypes.length > 0) {
            return { success: true, data: state.goalTypes };
        }

        try {
            const response = await goalsService.getGoalTypes();
            if (response.success) {
                commit('SET_GOAL_TYPES', response.data);
            }
            return response;
        } catch (error) {
            console.error('Failed to fetch goal types:', error);
            throw error;
        }
    },

    /**
     * Fetch risk levels.
     */
    async fetchRiskLevels({ commit, state }) {
        // Only fetch if not already loaded
        if (state.riskLevels.length > 0) {
            return { success: true, data: state.riskLevels };
        }

        try {
            const response = await goalsService.getRiskLevels();
            if (response.success) {
                commit('SET_RISK_LEVELS', response.data);
            }
            return response;
        } catch (error) {
            console.error('Failed to fetch risk levels:', error);
            throw error;
        }
    },

    /**
     * Create a new goal.
     */
    async createGoal({ commit, dispatch }, goalData) {
        commit('SET_LOADING', true);
        commit('CLEAR_ERROR');

        try {
            const response = await goalsService.createGoal(goalData);
            if (response.success) {
                commit('ADD_GOAL', response.data);
                // Refresh analysis to update summaries
                dispatch('fetchDashboardOverview');
            }
            return response;
        } catch (error) {
            commit('SET_ERROR', error.response?.data?.message || 'Failed to create goal');
            throw error;
        } finally {
            commit('SET_LOADING', false);
        }
    },

    /**
     * Fetch a specific goal.
     */
    async fetchGoal({ commit }, goalId) {
        commit('SET_LOADING', true);
        commit('CLEAR_ERROR');

        try {
            const response = await goalsService.getGoal(goalId);
            if (response.success) {
                commit('SET_SELECTED_GOAL', response.data);
            }
            return response;
        } catch (error) {
            commit('SET_ERROR', error.response?.data?.message || 'Failed to fetch goal');
            throw error;
        } finally {
            commit('SET_LOADING', false);
        }
    },

    /**
     * Update a goal.
     */
    async updateGoal({ commit, dispatch }, { goalId, goalData }) {
        commit('SET_LOADING', true);
        commit('CLEAR_ERROR');

        try {
            const response = await goalsService.updateGoal(goalId, goalData);
            if (response.success) {
                commit('UPDATE_GOAL', response.data);
                // Refresh analysis
                dispatch('fetchDashboardOverview');
            }
            return response;
        } catch (error) {
            commit('SET_ERROR', error.response?.data?.message || 'Failed to update goal');
            throw error;
        } finally {
            commit('SET_LOADING', false);
        }
    },

    /**
     * Delete a goal.
     */
    async deleteGoal({ commit, dispatch }, goalId) {
        commit('SET_LOADING', true);
        commit('CLEAR_ERROR');

        try {
            const response = await goalsService.deleteGoal(goalId);
            if (response.success) {
                commit('REMOVE_GOAL', goalId);
                // Refresh analysis
                dispatch('fetchDashboardOverview');
            }
            return response;
        } catch (error) {
            commit('SET_ERROR', error.response?.data?.message || 'Failed to delete goal');
            throw error;
        } finally {
            commit('SET_LOADING', false);
        }
    },

    /**
     * Record a contribution to a goal.
     */
    async recordContribution({ commit, dispatch }, { goalId, contributionData }) {
        commit('SET_LOADING', true);
        commit('CLEAR_ERROR');

        try {
            const response = await goalsService.recordContribution(goalId, contributionData);
            if (response.success) {
                commit('UPDATE_GOAL', response.data.goal);
                // Refresh dashboard
                dispatch('fetchDashboardOverview');
            }
            return response;
        } catch (error) {
            commit('SET_ERROR', error.response?.data?.message || 'Failed to record contribution');
            throw error;
        } finally {
            commit('SET_LOADING', false);
        }
    },

    /**
     * Get projections for a goal.
     */
    async getProjections({ commit }, goalId) {
        try {
            const response = await goalsService.getProjections(goalId);
            return response;
        } catch (error) {
            console.error('Failed to fetch projections:', error);
            throw error;
        }
    },

    /**
     * Get scenarios for a goal.
     */
    async getScenarios({ commit }, goalId) {
        try {
            const response = await goalsService.getScenarios(goalId);
            return response;
        } catch (error) {
            console.error('Failed to fetch scenarios:', error);
            throw error;
        }
    },

    /**
     * Calculate property costs.
     */
    async calculatePropertyCosts({ commit }, propertyData) {
        try {
            const response = await goalsService.calculatePropertyCosts(propertyData);
            return response;
        } catch (error) {
            console.error('Failed to calculate property costs:', error);
            throw error;
        }
    },

    /**
     * Clear goals state.
     */
    clearGoals({ commit }) {
        commit('SET_GOALS', []);
        commit('SET_ANALYSIS', null);
        commit('SET_DASHBOARD_OVERVIEW', null);
        commit('SET_SELECTED_GOAL', null);
        commit('CLEAR_ERROR');
    },
};

export default {
    namespaced: true,
    state,
    getters,
    mutations,
    actions,
};
