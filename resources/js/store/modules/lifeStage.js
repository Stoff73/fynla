import { LIFE_STAGES, STAGE_ORDER, PERSONA_TO_STAGE } from '@/constants/lifeStageConfig';
import lifeStageService from '@/services/lifeStageService';

const state = {
  currentStage: null, // 'university' | 'early_career' | 'mid_career' | 'peak' | 'retirement'
  completedSteps: [], // explicitly marked via onboarding flow
  dataCompletedSteps: [], // from backend DataReadiness checks (actual DB data)
  stepCompleteness: {}, // per-step field-level completeness from backend
  loading: false,
  error: null,
};

const getters = {
  currentStage: (state) => state.currentStage,
  stageConfig: (state) => state.currentStage ? LIFE_STAGES[state.currentStage] : null,
  stageLabel: (state, getters) => getters.stageConfig?.label || '',
  stageColour: (state, getters) => getters.stageConfig?.colour || 'horizon',
  stageTagline: (state, getters) => getters.stageConfig?.tagline || '',

  dashboardCards: (state, getters) => getters.stageConfig?.dashboard?.cards || [],
  onboardingSteps: (state, getters) => getters.stageConfig?.onboarding?.steps || [],
  learningMilestone: (state, getters) => (stepId) => getters.stageConfig?.onboarding?.learningMilestones?.[stepId] || null,
  formFields: (state, getters) => (formName) => getters.stageConfig?.formFields?.[formName] || {},

  // Per-step field completeness from backend.
  // Returns { status, filled, missing, filled_count, total_count, percentage } per step.
  stepCompleteness: (state) => state.stepCompleteness || {},

  // Get status for a single step: 'skipped' | 'partial' | 'complete' | null
  stepStatus: (state) => (stepId) => {
    return state.stepCompleteness?.[stepId]?.status || null;
  },

  // Steps completed based on actual data (from backend DataReadiness checks).
  dataCompletedSteps: (state) => {
    return state.dataCompletedSteps || [];
  },

  // Union of both completion sources: explicit flags (from onboarding flow)
  // AND data-readiness checks (from actual DB records).
  allCompletedSteps: (state) => {
    const explicit = state.completedSteps || [];
    const dataReady = state.dataCompletedSteps || [];
    return [...new Set([...explicit, ...dataReady])];
  },

  // Progress percentage based on field-level completeness (not binary stamps).
  // Only counts steps as complete when ALL tracked fields are filled.
  progressPercentage: (state, getters) => {
    const steps = getters.onboardingSteps;
    if (!steps.length) return 0;
    const completeness = state.stepCompleteness || {};

    // If no field-level completeness data, fall back to binary step completion
    if (Object.keys(completeness).length === 0) {
      const completed = getters.allCompletedSteps;
      const stepsCompleted = completed.filter(s => steps.includes(s)).length;
      return steps.length > 0 ? Math.round((stepsCompleted / steps.length) * 100) : 0;
    }

    let totalFields = 0;
    let filledFields = 0;

    steps.forEach(stepId => {
      const stepInfo = completeness[stepId];
      if (stepInfo) {
        totalFields += stepInfo.total_count;
        filledFields += stepInfo.filled_count;
      }
    });

    return totalFields > 0 ? Math.round((filledFields / totalFields) * 100) : 0;
  },

  nextStep: (state, getters) => {
    const steps = getters.onboardingSteps;
    const completeness = state.stepCompleteness || {};
    // Find first step that is not 'complete'
    return steps.find(step => completeness[step]?.status !== 'complete') || null;
  },

  isFieldVisible: (state, getters) => (formName, fieldName, context) => {
    if (context === 'standalone') return true;
    const config = getters.formFields(formName);
    if (!config) return true;
    const onboardingHide = config.onboardingHide || [];
    if (context === 'onboarding' && onboardingHide.includes(fieldName)) return false;
    return true;
  },

  allStages: () => STAGE_ORDER.map(id => LIFE_STAGES[id]),
  personaToStage: () => PERSONA_TO_STAGE,
};

const mutations = {
  resetState(state) {
    state.currentStage = null;
    state.completedSteps = [];
    state.dataCompletedSteps = [];
    state.stepCompleteness = {};
    state.loading = false;
    state.error = null;
  },
  setCurrentStage(state, stage) { state.currentStage = stage; },
  setCompletedSteps(state, steps) { state.completedSteps = steps; },
  setDataCompletedSteps(state, steps) { state.dataCompletedSteps = steps; },
  setStepCompleteness(state, completeness) { state.stepCompleteness = completeness; },
  addCompletedStep(state, step) {
    if (!state.completedSteps.includes(step)) {
      state.completedSteps.push(step);
    }
  },
  setLoading(state, loading) { state.loading = loading; },
  setError(state, error) { state.error = error; },
};

const actions = {
  async fetchStage({ commit, rootGetters }) {
    commit('setLoading', true);
    try {
      const user = rootGetters['auth/user'];
      if (user?.life_stage) {
        commit('setCurrentStage', user.life_stage);
      }
      const response = await lifeStageService.getProgress();
      if (response && typeof response === 'object' && response.success) {
        const progressData = response.data || response;
        commit('setCompletedSteps', progressData.completed_steps || []);
        commit('setDataCompletedSteps', progressData.data_completed_steps || []);
        commit('setStepCompleteness', progressData.step_completeness || {});
      }
    } catch (error) {
      commit('setError', error.message);
    } finally {
      commit('setLoading', false);
    }
  },

  async refreshCompleteness({ commit }) {
    try {
      const response = await lifeStageService.getProgress();
      if (response && typeof response === 'object' && response.success) {
        const progressData = response.data || response;
        commit('setDataCompletedSteps', progressData.data_completed_steps || []);
        commit('setStepCompleteness', progressData.step_completeness || {});
      }
    } catch (error) {
      // Non-blocking refresh
    }
  },

  async setStage({ commit }, stage) {
    commit('setLoading', true);
    try {
      await lifeStageService.setStage(stage);
      commit('setCurrentStage', stage);
    } catch (error) {
      commit('setError', error.message);
      throw error;
    } finally {
      commit('setLoading', false);
    }
  },

  async completeStep({ commit, dispatch }, stepId) {
    commit('addCompletedStep', stepId);
    try {
      await lifeStageService.completeStep(stepId);
      // Refresh field-level completeness from backend after each step
      await dispatch('refreshCompleteness');
    } catch (error) {
      commit('setError', error.message);
    }
  },

  setStageFromPersona({ commit }, personaId) {
    const basePersona = personaId.replace(/_spouse$/, '');
    const stage = PERSONA_TO_STAGE[basePersona];
    if (stage) {
      commit('setCurrentStage', stage);
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
