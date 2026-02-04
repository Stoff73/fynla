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

    // Life Events
    lifeEvents: [],
    lifeEventsLoading: false,
    eventTypes: [],

    // Projection
    projectionData: null,
    projectionLoading: false,
    chartView: 'net_worth', // 'net_worth', 'cash_flow', 'asset_breakdown'
    viewMode: 'individual', // 'individual', 'household'
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

    // Life Events getters
    activeLifeEvents: (state) => {
        return (state.lifeEvents || []).filter(e => e.status === 'expected' || e.status === 'confirmed');
    },

    incomeEvents: (state) => {
        return (state.lifeEvents || []).filter(e => e.impact_type === 'income');
    },

    expenseEvents: (state) => {
        return (state.lifeEvents || []).filter(e => e.impact_type === 'expense');
    },

    lifeEventsForProjection: (state) => {
        return (state.lifeEvents || []).filter(e => e.show_in_projection);
    },

    // Projection getters
    currentChartView: (state) => state.chartView,
    currentViewMode: (state) => state.viewMode,
    isHouseholdView: (state) => state.viewMode === 'household',
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

    // Life Events mutations
    SET_LIFE_EVENTS(state, events) {
        state.lifeEvents = events;
    },

    SET_LIFE_EVENTS_LOADING(state, loading) {
        state.lifeEventsLoading = loading;
    },

    SET_EVENT_TYPES(state, types) {
        state.eventTypes = types;
    },

    ADD_LIFE_EVENT(state, event) {
        state.lifeEvents.push(event);
    },

    UPDATE_LIFE_EVENT(state, updatedEvent) {
        const index = state.lifeEvents.findIndex(e => e.id === updatedEvent.id);
        if (index !== -1) {
            state.lifeEvents.splice(index, 1, updatedEvent);
        }
    },

    REMOVE_LIFE_EVENT(state, eventId) {
        state.lifeEvents = state.lifeEvents.filter(e => e.id !== eventId);
    },

    // Projection mutations
    SET_PROJECTION_DATA(state, data) {
        state.projectionData = data;
    },

    SET_PROJECTION_LOADING(state, loading) {
        state.projectionLoading = loading;
    },

    SET_CHART_VIEW(state, view) {
        state.chartView = view;
    },

    SET_VIEW_MODE(state, mode) {
        state.viewMode = mode;
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
        commit('SET_LIFE_EVENTS', []);
        commit('SET_PROJECTION_DATA', null);
        commit('CLEAR_ERROR');
    },

    // =========================================
    // Life Events Actions
    // =========================================

    /**
     * Fetch all life events for the user.
     */
    async fetchLifeEvents({ commit }, { household = false } = {}) {
        commit('SET_LIFE_EVENTS_LOADING', true);

        try {
            const response = await goalsService.getLifeEvents({ household });
            if (response.success) {
                // API returns { events: [...], count: n } - extract just the events array
                commit('SET_LIFE_EVENTS', response.data.events || response.data || []);
            }
            return response;
        } catch (error) {
            console.error('Failed to fetch life events:', error);
            throw error;
        } finally {
            commit('SET_LIFE_EVENTS_LOADING', false);
        }
    },

    /**
     * Fetch event types for life events.
     */
    async fetchEventTypes({ commit, state }) {
        // Only fetch if not already loaded
        if (state.eventTypes.length > 0) {
            return { success: true, data: state.eventTypes };
        }

        try {
            const response = await goalsService.getEventTypes();
            if (response.success) {
                // API returns { event_types: [...], certainty_levels: [...] }
                // Extract just the event_types array for the store
                commit('SET_EVENT_TYPES', response.data.event_types || []);
            }
            return response;
        } catch (error) {
            console.error('Failed to fetch event types:', error);
            throw error;
        }
    },

    /**
     * Create a new life event.
     */
    async createLifeEvent({ commit, dispatch }, eventData) {
        commit('SET_LIFE_EVENTS_LOADING', true);

        try {
            const response = await goalsService.createLifeEvent(eventData);
            if (response.success) {
                commit('ADD_LIFE_EVENT', response.data);
                // Refresh projection data
                dispatch('fetchProjection');
            }
            return response;
        } catch (error) {
            console.error('Failed to create life event:', error);
            throw error;
        } finally {
            commit('SET_LIFE_EVENTS_LOADING', false);
        }
    },

    /**
     * Update a life event.
     */
    async updateLifeEvent({ commit, dispatch }, { eventId, eventData }) {
        commit('SET_LIFE_EVENTS_LOADING', true);

        try {
            const response = await goalsService.updateLifeEvent(eventId, eventData);
            if (response.success) {
                commit('UPDATE_LIFE_EVENT', response.data);
                // Refresh projection data
                dispatch('fetchProjection');
            }
            return response;
        } catch (error) {
            console.error('Failed to update life event:', error);
            throw error;
        } finally {
            commit('SET_LIFE_EVENTS_LOADING', false);
        }
    },

    /**
     * Delete a life event.
     */
    async deleteLifeEvent({ commit, dispatch }, eventId) {
        commit('SET_LIFE_EVENTS_LOADING', true);

        try {
            const response = await goalsService.deleteLifeEvent(eventId);
            if (response.success) {
                commit('REMOVE_LIFE_EVENT', eventId);
                // Refresh projection data
                dispatch('fetchProjection');
            }
            return response;
        } catch (error) {
            console.error('Failed to delete life event:', error);
            throw error;
        } finally {
            commit('SET_LIFE_EVENTS_LOADING', false);
        }
    },

    // =========================================
    // Projection Actions
    // =========================================

    /**
     * Fetch projection data.
     */
    async fetchProjection({ commit, state }) {
        commit('SET_PROJECTION_LOADING', true);

        try {
            const household = state.viewMode === 'household';
            const response = await goalsService.getProjection({ household });
            if (response.success) {
                commit('SET_PROJECTION_DATA', response.data);
            }
            return response;
        } catch (error) {
            console.error('Failed to fetch projection:', error);
            throw error;
        } finally {
            commit('SET_PROJECTION_LOADING', false);
        }
    },

    /**
     * Set chart view (net_worth, cash_flow, asset_breakdown).
     */
    setChartView({ commit }, view) {
        commit('SET_CHART_VIEW', view);
    },

    /**
     * Set view mode (individual, household) and refresh projection.
     */
    async setViewMode({ commit, dispatch }, mode) {
        commit('SET_VIEW_MODE', mode);
        // Refresh projection with new view mode
        await dispatch('fetchProjection');
        // Also refresh life events for household view
        await dispatch('fetchLifeEvents', { household: mode === 'household' });
    },
};

export default {
    namespaced: true,
    state,
    getters,
    mutations,
    actions,
};
