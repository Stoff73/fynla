import { LIFE_STAGES, STAGE_ORDER, PERSONA_TO_STAGE } from '@/constants/lifeStageConfig';
import lifeStageService from '@/services/lifeStageService';

const state = {
  currentStage: null, // 'university' | 'early_career' | 'mid_career' | 'peak' | 'retirement'
  completedSteps: [], // explicitly marked via onboarding flow
  dataCompletedSteps: [], // from backend DataReadiness checks (actual DB data)
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

  // Steps completed based on actual data (from backend DataReadiness checks).
  // This is populated by fetchStage from the /api/life-stage/progress endpoint.
  // The backend checks actual DB records (same as PrerequisiteGateService and
  // DataReadiness services used by agents). NOT guessed from Vuex state.
  dataCompletedSteps: (state) => {
    return state.dataCompletedSteps || [];
  },

  progressPercentage: (state, getters) => {
    const steps = getters.onboardingSteps;
    if (!steps.length) return 0;
    const completed = getters.dataCompletedSteps;
    // Only count steps that are in the current stage's step list
    const relevant = completed.filter(s => steps.includes(s));
    return Math.round((relevant.length / steps.length) * 100);
  },

  nextStep: (state, getters) => {
    const steps = getters.onboardingSteps;
    const completed = getters.dataCompletedSteps;
    return steps.find(step => !completed.includes(step)) || null;
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
    const onboardingHide = config.onboardingHide || [];
    // In onboarding: hide only fields explicitly in onboardingHide. Show everything else.
    if (context === 'onboarding' && onboardingHide.includes(fieldName)) return false;
    return true;
  },

  allStages: () => STAGE_ORDER.map(id => LIFE_STAGES[id]),
  personaToStage: () => PERSONA_TO_STAGE,
};

const mutations = {
  setCurrentStage(state, stage) { state.currentStage = stage; },
  setCompletedSteps(state, steps) { state.completedSteps = steps; },
  setDataCompletedSteps(state, steps) { state.dataCompletedSteps = steps; },
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
      // Load progress from backend — includes data completeness from actual DB checks
      // (same checks used by PrerequisiteGateService and DataReadiness services)
      const response = await lifeStageService.getProgress();
      // Guard: API might return HTML (redirect) if token isn't ready yet
      if (response && typeof response === 'object' && response.success) {
        const progressData = response.data || response;
        commit('setCompletedSteps', progressData.completed_steps || []);
        commit('setDataCompletedSteps', progressData.data_completed_steps || []);
      }
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
