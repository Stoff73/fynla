import journeyService from '@/services/journeyService';

const state = {
  selections: [],
  journeyStates: {},
  currentJourney: null,
  currentSteps: [],
  currentStepIndex: 0,
  dashboardPrompts: [],
  loading: false,
  error: null,
};

const getters = {
  selectedJourneys: (state) => state.selections,
  completedJourneys: (state) => {
    return Object.entries(state.journeyStates)
      .filter(([, s]) => {
        const status = typeof s === 'string' ? s : s?.status;
        return status === 'completed';
      })
      .map(([key]) => key);
  },
  inProgressJourneys: (state) => {
    return Object.entries(state.journeyStates)
      .filter(([, s]) => {
        const status = typeof s === 'string' ? s : s?.status;
        return status === 'in_progress';
      })
      .map(([key]) => key);
  },
  notStartedJourneys: (state) => {
    return state.selections.filter((j) => {
      const s = state.journeyStates[j];
      if (!s) return true;
      const status = typeof s === 'string' ? s : s?.status;
      return status === 'not_started';
    });
  },
  currentStep: (state) => {
    return state.currentSteps[state.currentStepIndex] || null;
  },
  isLastStep: (state) => {
    return state.currentStepIndex >= state.currentSteps.length - 1;
  },
  progressPercentage: (state) => {
    if (state.currentSteps.length === 0) return 0;
    return Math.round(((state.currentStepIndex + 1) / state.currentSteps.length) * 100);
  },
};

const actions = {
  async fetchSelections({ commit }) {
    commit('SET_LOADING', true);
    commit('SET_ERROR', null);

    try {
      const response = await journeyService.getSelections();
      const data = response.data || response;
      commit('SET_SELECTIONS', data.selections || []);
      commit('SET_JOURNEY_STATES', data.states || data.journey_states || {});
      return data;
    } catch (error) {
      commit('SET_ERROR', error.message || 'Failed to fetch journey selections');
      throw error;
    } finally {
      commit('SET_LOADING', false);
    }
  },

  async saveSelections({ commit, dispatch }, journeys) {
    commit('SET_LOADING', true);
    commit('SET_ERROR', null);

    try {
      const response = await journeyService.saveSelections(journeys);
      const data = response.data || response;
      commit('SET_SELECTIONS', journeys);

      // Initialise journey states for newly selected journeys
      const states = {};
      journeys.forEach((j) => {
        states[j] = 'not_started';
      });
      commit('SET_JOURNEY_STATES', states);

      return data;
    } catch (error) {
      commit('SET_ERROR', error.message || 'Failed to save journey selections');
      throw error;
    } finally {
      commit('SET_LOADING', false);
    }
  },

  async fetchSteps({ commit }, journey) {
    commit('SET_LOADING', true);
    commit('SET_ERROR', null);

    try {
      const response = await journeyService.getSteps(journey);
      const data = response.data || response;
      const steps = data.steps || [];
      commit('SET_CURRENT_STEPS', steps);
      commit('SET_CURRENT_JOURNEY', journey);
      commit('SET_CURRENT_STEP_INDEX', 0);
      return steps;
    } catch (error) {
      commit('SET_ERROR', error.message || 'Failed to fetch journey steps');
      throw error;
    } finally {
      commit('SET_LOADING', false);
    }
  },

  async startJourney({ commit }, journey) {
    commit('SET_LOADING', true);
    commit('SET_ERROR', null);

    try {
      const response = await journeyService.startJourney(journey);
      commit('SET_JOURNEY_STATE', { journey, state: 'in_progress' });
      commit('SET_CURRENT_JOURNEY', journey);
      return response.data || response;
    } catch (error) {
      commit('SET_ERROR', error.message || 'Failed to start journey');
      throw error;
    } finally {
      commit('SET_LOADING', false);
    }
  },

  async completeJourney({ commit, state: s }, journey) {
    commit('SET_LOADING', true);
    commit('SET_ERROR', null);

    try {
      const response = await journeyService.completeJourney(journey || s.currentJourney);
      commit('SET_JOURNEY_STATE', { journey: journey || s.currentJourney, state: 'completed' });
      return response.data || response;
    } catch (error) {
      commit('SET_ERROR', error.message || 'Failed to complete journey');
      throw error;
    } finally {
      commit('SET_LOADING', false);
    }
  },

  nextStep({ commit, state: s, dispatch }) {
    const nextIndex = s.currentStepIndex + 1;
    if (nextIndex < s.currentSteps.length) {
      commit('SET_CURRENT_STEP_INDEX', nextIndex);
    }
  },

  previousStep({ commit, state: s }) {
    const prevIndex = s.currentStepIndex - 1;
    if (prevIndex >= 0) {
      commit('SET_CURRENT_STEP_INDEX', prevIndex);
    }
  },

  async fetchDashboardPrompts({ commit }) {
    commit('SET_LOADING', true);
    commit('SET_ERROR', null);

    try {
      const response = await journeyService.getDashboardPrompts();
      const data = response.data || response;
      commit('SET_DASHBOARD_PROMPTS', data.prompts || []);
      return data;
    } catch (error) {
      commit('SET_ERROR', error.message || 'Failed to fetch dashboard prompts');
      throw error;
    } finally {
      commit('SET_LOADING', false);
    }
  },

  async dismissPrompt({ commit, state: s }, promptId) {
    commit('SET_LOADING', true);
    commit('SET_ERROR', null);

    try {
      const response = await journeyService.dismissPrompt(promptId);
      commit('REMOVE_PROMPT', promptId);
      return response.data || response;
    } catch (error) {
      commit('SET_ERROR', error.message || 'Failed to dismiss prompt');
      throw error;
    } finally {
      commit('SET_LOADING', false);
    }
  },
};

const mutations = {
  SET_SELECTIONS(state, selections) {
    state.selections = selections;
  },

  SET_JOURNEY_STATES(state, states) {
    state.journeyStates = states;
  },

  SET_JOURNEY_STATE(state, { journey, state: journeyState }) {
    state.journeyStates = {
      ...state.journeyStates,
      [journey]: journeyState,
    };
  },

  SET_CURRENT_JOURNEY(state, journey) {
    state.currentJourney = journey;
  },

  SET_CURRENT_STEPS(state, steps) {
    state.currentSteps = steps;
  },

  SET_CURRENT_STEP_INDEX(state, index) {
    state.currentStepIndex = index;
  },

  SET_DASHBOARD_PROMPTS(state, prompts) {
    state.dashboardPrompts = prompts;
  },

  REMOVE_PROMPT(state, promptId) {
    state.dashboardPrompts = state.dashboardPrompts.filter((p) => p.id !== promptId);
  },

  SET_LOADING(state, loading) {
    state.loading = loading;
  },

  SET_ERROR(state, error) {
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
