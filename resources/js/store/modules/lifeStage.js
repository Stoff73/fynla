import { LIFE_STAGES, STAGE_ORDER, PERSONA_TO_STAGE } from '@/constants/lifeStageConfig';
import lifeStageService from '@/services/lifeStageService';

const state = {
  currentStage: null, // 'university' | 'early_career' | 'mid_career' | 'peak' | 'retirement'
  completedSteps: [],
  loading: false,
  error: null,
};

const getters = {
  currentStage: (state) => state.currentStage,
  stageConfig: (state) => state.currentStage ? LIFE_STAGES[state.currentStage] : null,
  stageLabel: (state, getters) => getters.stageConfig?.label || '',
  stageColour: (state, getters) => getters.stageConfig?.colour || 'horizon',
  stageTagline: (state, getters) => getters.stageConfig?.tagline || '',

  sidebarPrimary: (state, getters) => getters.stageConfig?.sidebar?.primary || [],
  sidebarExplore: (state, getters) => getters.stageConfig?.sidebar?.explore || [],
  dashboardCards: (state, getters) => getters.stageConfig?.dashboard?.cards || [],
  onboardingSteps: (state, getters) => getters.stageConfig?.onboarding?.steps || [],
  suggestedGoals: (state, getters) => getters.stageConfig?.suggestedGoals || [],
  learningMilestone: (state, getters) => (stepId) => getters.stageConfig?.onboarding?.learningMilestones?.[stepId] || null,
  formFields: (state, getters) => (formName) => getters.stageConfig?.formFields?.[formName] || {},

  progressPercentage: (state, getters) => {
    const steps = getters.onboardingSteps;
    if (!steps.length) return 0;
    return Math.round((state.completedSteps.length / steps.length) * 100);
  },

  nextStep: (state, getters) => {
    const steps = getters.onboardingSteps;
    return steps.find(step => !state.completedSteps.includes(step)) || null;
  },

  // Dynamic promotion: merge primary + user-data-promoted modules
  effectiveSidebarPrimary: (state, getters, rootState, rootGetters) => {
    const primary = [...(getters.sidebarPrimary || [])];
    const explore = getters.sidebarExplore || [];
    const flags = getters.userDataFlags;

    // Promote modules from explore to primary if user has data
    const moduleToFlag = {
      'property': 'properties',
      'protection': 'protection',
      'investments': 'investments',
      'retirement': 'pensions',
      'will': 'will',
      'estate': 'will',
      'trusts': 'trusts',
      'business': 'business',
      'savings': 'savings',
    };

    explore.forEach(moduleId => {
      const flagKey = moduleToFlag[moduleId];
      if (flagKey && flags[flagKey] && !primary.includes(moduleId)) {
        primary.push(moduleId);
      }
    });

    return primary;
  },

  effectiveSidebarExplore: (state, getters) => {
    const effectivePrimary = getters.effectiveSidebarPrimary;
    return (getters.sidebarExplore || []).filter(id => !effectivePrimary.includes(id));
  },

  userDataFlags: (state, getters, rootState) => ({
    properties: (rootState.netWorth?.properties?.length || 0) > 0,
    savings: (rootState.savings?.accounts?.length || 0) > 0,
    investments: (rootState.investment?.accounts?.length || 0) > 0,
    pensions: (rootState.retirement?.pensions?.length || 0) > 0,
    protection: (rootState.protection?.policies?.length || 0) > 0,
    will: rootState.estate?.will !== null && rootState.estate?.will !== undefined,
    trusts: (rootState.estate?.trusts?.length || 0) > 0,
    business: (rootState.netWorth?.businessInterests?.length || 0) > 0,
  }),

  isFieldVisible: (state, getters) => (formName, fieldName, context) => {
    if (context === 'standalone') return true;
    const config = getters.formFields(formName);
    if (!config) return true;
    const alwaysFields = config.always || [];
    const stageFields = config.stage || [];
    const onboardingHide = config.onboardingHide || [];
    if (context === 'onboarding' && onboardingHide.includes(fieldName)) return false;
    return alwaysFields.includes(fieldName) || stageFields.includes(fieldName);
  },

  allStages: () => STAGE_ORDER.map(id => LIFE_STAGES[id]),
  personaToStage: () => PERSONA_TO_STAGE,
};

const mutations = {
  setCurrentStage(state, stage) { state.currentStage = stage; },
  setCompletedSteps(state, steps) { state.completedSteps = steps; },
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
      // Also load completed steps from backend
      const response = await lifeStageService.getProgress();
      commit('setCompletedSteps', response.completed_steps || []);
    } catch (error) {
      commit('setError', error.message);
    } finally {
      commit('setLoading', false);
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

  async completeStep({ commit }, stepId) {
    commit('addCompletedStep', stepId);
    try {
      await lifeStageService.completeStep(stepId);
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
