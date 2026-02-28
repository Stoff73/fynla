import plansService from '@/services/plansService';

const state = {
  plans: {},          // keyed by type: { investment: {...}, protection: {...}, etc. }
  goalPlans: {},      // keyed by goalId: { 1: {...}, 2: {...} }
  actionStates: {},   // toggle states: { investment: { action_id: true/false } }
  planStatuses: null,  // dashboard readiness per type
  loading: false,
  recalculating: false,
  error: null,
};

const getters = {
  getPlan: (state) => (type) => state.plans[type] || null,
  getGoalPlan: (state) => (goalId) => state.goalPlans[goalId] || null,

  enabledActions: (state) => (type) => {
    const plan = state.plans[type];
    if (!plan || !plan.actions) return [];
    const overrides = state.actionStates[type] || {};
    return plan.actions.filter(a => {
      return overrides[a.id] !== undefined ? overrides[a.id] : a.enabled;
    });
  },

  disabledActions: (state) => (type) => {
    const plan = state.plans[type];
    if (!plan || !plan.actions) return [];
    const overrides = state.actionStates[type] || {};
    return plan.actions.filter(a => {
      return overrides[a.id] !== undefined ? !overrides[a.id] : !a.enabled;
    });
  },

  enabledGoalActions: (state) => (goalId) => {
    const plan = state.goalPlans[goalId];
    if (!plan || !plan.actions) return [];
    const key = `goal_${goalId}`;
    const overrides = state.actionStates[key] || {};
    return plan.actions.filter(a => {
      return overrides[a.id] !== undefined ? overrides[a.id] : a.enabled;
    });
  },

  isLoading: (state) => state.loading,
  isRecalculating: (state) => state.recalculating,
  planStatuses: (state) => state.planStatuses,
};

const mutations = {
  setPlan(state, { type, plan }) {
    state.plans = { ...state.plans, [type]: plan };
  },

  setGoalPlan(state, { goalId, plan }) {
    state.goalPlans = { ...state.goalPlans, [goalId]: plan };
  },

  toggleAction(state, { planKey, actionId }) {
    const current = state.actionStates[planKey] || {};
    const currentState = current[actionId];
    const newState = currentState !== undefined ? !currentState : false; // Default is enabled, so first toggle disables
    state.actionStates = {
      ...state.actionStates,
      [planKey]: { ...current, [actionId]: newState },
    };

    // Update the plan's action enabled state for UI reactivity
    const plan = planKey.startsWith('goal_')
      ? state.goalPlans[planKey.replace('goal_', '')]
      : state.plans[planKey];
    if (plan && plan.actions) {
      plan.actions = plan.actions.map(a =>
        a.id === actionId ? { ...a, enabled: newState } : a
      );
    }
  },

  setPlanStatuses(state, statuses) {
    state.planStatuses = statuses;
  },

  setLoading(state, loading) {
    state.loading = loading;
  },

  setRecalculating(state, recalculating) {
    state.recalculating = recalculating;
  },

  setError(state, error) {
    state.error = error;
  },

  clearPlan(state, type) {
    const { [type]: removed, ...rest } = state.plans;
    state.plans = rest;
  },
};

const actions = {
  async fetchPlan({ commit }, type) {
    commit('setLoading', true);
    commit('setError', null);
    try {
      const response = await plansService.generatePlan(type);
      commit('setPlan', { type, plan: response.data });
      return response.data;
    } catch (error) {
      commit('setError', error.message || 'Failed to generate plan');
      throw error;
    } finally {
      commit('setLoading', false);
    }
  },

  async fetchGoalPlan({ commit }, goalId) {
    commit('setLoading', true);
    commit('setError', null);
    try {
      const response = await plansService.generateGoalPlan(goalId);
      commit('setGoalPlan', { goalId, plan: response.data });
      return response.data;
    } catch (error) {
      commit('setError', error.message || 'Failed to generate goal plan');
      throw error;
    } finally {
      commit('setLoading', false);
    }
  },

  toggleAction({ commit, state }, { planKey, actionId }) {
    commit('toggleAction', { planKey, actionId });
  },

  async recalculateScenario({ commit, state }, { type }) {
    commit('setRecalculating', true);
    try {
      const overrides = state.actionStates[type] || {};
      const plan = state.plans[type];
      if (!plan) return;

      const enabledIds = plan.actions
        .filter(a => overrides[a.id] !== undefined ? overrides[a.id] : a.enabled)
        .map(a => a.id);

      const response = await plansService.recalculateScenario(type, enabledIds);
      commit('setPlan', { type, plan: response.data });
      return response.data;
    } catch (error) {
      commit('setError', error.message || 'Recalculation failed');
      throw error;
    } finally {
      commit('setRecalculating', false);
    }
  },

  async recalculateGoalScenario({ commit, state }, { goalId }) {
    commit('setRecalculating', true);
    try {
      const key = `goal_${goalId}`;
      const overrides = state.actionStates[key] || {};
      const plan = state.goalPlans[goalId];
      if (!plan) return;

      const enabledIds = plan.actions
        .filter(a => overrides[a.id] !== undefined ? overrides[a.id] : a.enabled)
        .map(a => a.id);

      const response = await plansService.recalculateGoalScenario(goalId, enabledIds);
      commit('setGoalPlan', { goalId, plan: response.data });
      return response.data;
    } catch (error) {
      commit('setError', error.message || 'Recalculation failed');
      throw error;
    } finally {
      commit('setRecalculating', false);
    }
  },

  async fetchDashboardStatuses({ commit }) {
    try {
      const response = await plansService.getDashboardStatuses();
      commit('setPlanStatuses', response.data);
      return response.data;
    } catch (error) {
      // Non-critical, don't throw
      console.warn('Failed to fetch plan statuses:', error.message);
    }
  },
};

export default {
  namespaced: true,
  state,
  getters,
  mutations,
  actions,
};
