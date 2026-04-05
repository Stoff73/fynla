import protectionService from '@/services/protectionService';

// Helper: Convert premium to monthly amount
const convertPremiumToMonthly = (premium, frequency) => {
    const amount = parseFloat(premium || 0);
    if (frequency === 'annual') {
        return amount / 12;
    }
    return amount;
};

// Helper: Calculate total monthly premium for a list of policies
const calculateMonthlyPremium = (policies) => {
    return policies.reduce((sum, policy) => {
        return sum + convertPremiumToMonthly(policy.premium_amount, policy.premium_frequency || 'monthly');
    }, 0);
};

// Action factory for creating policies
const createPolicyActionFactory = (policyType, serviceMethod, errorMessage) => {
    return async ({ commit, dispatch }, policyData) => {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await protectionService[serviceMethod](policyData);
            const policy = response.data || response;
            commit('addPolicy', { type: policyType, policy });
            await dispatch('analyseProtection', {});
            dispatch('recommendations/fetchRecommendations', {}, { root: true });
            return response;
        } catch (error) {
            commit('setError', error.message || errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    };
};

// Action factory for updating policies
const updatePolicyActionFactory = (policyType, serviceMethod, errorMessage) => {
    return async ({ commit, dispatch }, { id, policyData }) => {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await protectionService[serviceMethod](id, policyData);
            const policy = response.data || response;
            commit('updatePolicy', { type: policyType, policy });
            await dispatch('analyseProtection', {});
            dispatch('recommendations/fetchRecommendations', {}, { root: true });
            return response;
        } catch (error) {
            commit('setError', error.message || errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    };
};

// Action factory for deleting policies
const deletePolicyActionFactory = (policyType, serviceMethod, errorMessage) => {
    return async ({ commit, dispatch }, id) => {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await protectionService[serviceMethod](id);
            commit('removePolicy', { type: policyType, id });
            await dispatch('analyseProtection', {});
            dispatch('recommendations/fetchRecommendations', {}, { root: true });
            return response;
        } catch (error) {
            commit('setError', error.message || errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    };
};

const state = {
    profile: null,
    policies: {
        life: [],
        criticalIllness: [],
        incomeProtection: [],
        disability: [],
        sicknessIllness: [],
    },
    analysis: null,
    recommendations: [],
    lifeEvents: [],
    lifeEventImpact: null,
    canProceed: true,
    readinessChecks: null,
    loading: false,
    error: null,
};

const getters = {
    policies: (state) => state.policies,

    // Get adequacy rating from analysis (category string: Excellent/Good/Fair/Critical)
    adequacyScore: (state) => {
        const adequacy = state.analysis?.data?.adequacy_score;
        if (!adequacy) return 'Incomplete';
        return adequacy.rating || 'Incomplete';
    },

    // Get total coverage across all policy types
    totalCoverage: (state) => {
        const lifeCoverage = state.policies.life.reduce((sum, policy) => sum + parseFloat(policy.sum_assured || 0), 0);
        const criticalIllnessCoverage = state.policies.criticalIllness.reduce((sum, policy) => sum + parseFloat(policy.sum_assured || 0), 0);
        return lifeCoverage + criticalIllnessCoverage;
    },

    // Get total premium across all policy types (monthly)
    totalPremium: (state) => {
        return Object.values(state.policies).reduce((total, policies) => {
            return total + calculateMonthlyPremium(policies);
        }, 0);
    },

    // Get coverage gaps from analysis
    coverageGaps: (state) => {
        return state.analysis?.data?.gaps || {};
    },

    // Individual policy type getters for dashboard
    lifePolicies: (state) => state.policies.life || [],
    criticalIllnessPolicies: (state) => state.policies.criticalIllness || [],
    incomeProtectionPolicies: (state) => state.policies.incomeProtection || [],
    disabilityPolicies: (state) => state.policies.disability || [],
    sicknessIllnessPolicies: (state) => state.policies.sicknessIllness || [],

    // Get high priority recommendations
    priorityRecommendations: (state) => {
        return state.recommendations.filter(rec => rec.priority === 'high');
    },

    // Get all policies as a flat array with type indicator
    allPolicies: (state) => {
        const allPolicies = [];

        Object.entries(state.policies).forEach(([type, policies]) => {
            policies.forEach(policy => {
                // For life insurance, preserve the original policy_type as policy_subtype
                if (type === 'life') {
                    allPolicies.push({
                        ...policy,
                        policy_subtype: policy.policy_type,
                        policy_type: type,
                    });
                } else {
                    allPolicies.push({
                        ...policy,
                        policy_type: type,
                    });
                }
            });
        });

        return allPolicies;
    },

    // Premium breakdown by policy type (monthly)
    premiumBreakdown: (state) => {
        const breakdown = {};
        Object.entries(state.policies).forEach(([type, policies]) => {
            breakdown[type] = calculateMonthlyPremium(policies);
        });
        return breakdown;
    },

    // Check if any life insurance policies are in trust
    hasLifePoliciesInTrust: (state) => {
        return state.policies.life.some(policy => policy.in_trust === true || policy.in_trust === 1);
    },

    // Check if any life insurance policies are NOT in trust
    hasLifePoliciesNotInTrust: (state) => {
        return state.policies.life.some(policy => policy.in_trust === false || policy.in_trust === 0 || policy.in_trust === null);
    },

    // Check if user has income protection coverage
    hasIncomeProtection: (state) => {
        return state.policies.incomeProtection && state.policies.incomeProtection.length > 0;
    },

    // Check if user has critical illness coverage
    hasCriticalIllness: (state) => {
        return state.policies.criticalIllness && state.policies.criticalIllness.length > 0;
    },

    // Check if user has disability coverage
    hasDisabilityInsurance: (state) => {
        return state.policies.disability && state.policies.disability.length > 0;
    },

    // Life events relevant to protection module
    upcomingLifeEvents: (state) => state.lifeEvents,
    lifeEventNetImpact: (state) => state.lifeEventImpact?.net_impact || 0,

    canProceed: (state) => state.canProceed,
    readinessChecks: (state) => state.readinessChecks,

    loading: (state) => state.loading,
    error: (state) => state.error,
};

const actions = {
    // Fetch protection profile
    async fetchProfile({ commit }) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await protectionService.getProtectionData();
            const data = response.data || response;
            commit('setProfile', data.profile || null);
            return response;
        } catch (error) {
            const errorMessage = error.response?.data?.message || error.message || 'Failed to fetch protection profile';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    // Update protection profile
    async updateProfile({ commit }, profileData) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await protectionService.saveProfile(profileData);
            const data = response.data || response;
            commit('setProfile', data.profile || data);
            return response;
        } catch (error) {
            const errorMessage = error.response?.data?.message || error.message || 'Failed to update protection profile';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    // Fetch all protection data
    async fetchProtectionData({ commit }) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await protectionService.getProtectionData();
            const data = response.data || response;
            commit('setProfile', data.profile || null);
            commit('setPolicies', data.policies || {});
            commit('setLifeEvents', data.life_events || []);
            commit('setLifeEventImpact', data.life_event_impact || null);

            // Also fetch analysis data to get human capital and total debt
            try {
                const analysisResponse = await protectionService.analyzeProtection({});
                const analysisData = analysisResponse.data || analysisResponse;

                // Guard: handle success: false or can_proceed: false
                if (analysisData?.success === false || analysisData?.can_proceed === false) {
                    commit('SET_CAN_PROCEED', false);
                    commit('SET_READINESS_CHECKS', analysisData?.readiness_checks || null);
                    commit('setAnalysis', null);
                } else {
                    commit('SET_CAN_PROCEED', true);
                    commit('SET_READINESS_CHECKS', null);
                    commit('setAnalysis', analysisData);
                }
            } catch (analysisError) {
                // Don't fail the whole request if analysis fails
                commit('setAnalysis', null);
            }

            return response;
        } catch (error) {
            const errorMessage = error.response?.data?.message || error.message || 'Failed to fetch protection data';
            commit('setError', errorMessage);
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    // Analyse protection coverage
    async analyseProtection({ commit }, data) {
        commit('setLoading', true);
        commit('setError', null);

        try {
            const response = await protectionService.analyzeProtection(data);
            const analysisData = response.data || response;

            // Guard: handle success: false or can_proceed: false
            if (analysisData?.success === false || analysisData?.can_proceed === false) {
                commit('SET_CAN_PROCEED', false);
                commit('SET_READINESS_CHECKS', analysisData?.readiness_checks || null);
                commit('setAnalysis', null);
                return response;
            }

            commit('SET_CAN_PROCEED', true);
            commit('SET_READINESS_CHECKS', null);
            commit('setAnalysis', analysisData);
            return response;
        } catch (error) {
            commit('setError', error.message || 'Analysis failed');
            throw error;
        } finally {
            commit('setLoading', false);
        }
    },

    // Generic create/update/delete dispatchers
    async createPolicy({ dispatch }, { policyType, policyData }) {
        const actionMap = {
            life: 'createLifePolicy',
            criticalIllness: 'createCriticalIllnessPolicy',
            incomeProtection: 'createIncomeProtectionPolicy',
            disability: 'createDisabilityPolicy',
            sicknessIllness: 'createSicknessIllnessPolicy',
        };

        const action = actionMap[policyType];
        if (!action) {
            throw new Error(`Unknown policy type: ${policyType}`);
        }
        return dispatch(action, policyData);
    },

    async updatePolicy({ dispatch }, { policyType, id, policyData }) {
        const actionMap = {
            life: 'updateLifePolicy',
            criticalIllness: 'updateCriticalIllnessPolicy',
            incomeProtection: 'updateIncomeProtectionPolicy',
            disability: 'updateDisabilityPolicy',
            sicknessIllness: 'updateSicknessIllnessPolicy',
        };

        const action = actionMap[policyType];
        if (!action) {
            throw new Error(`Unknown policy type: ${policyType}`);
        }
        return dispatch(action, { id, policyData });
    },

    async deletePolicy({ dispatch }, { policyType, id }) {
        const actionMap = {
            life: 'deleteLifePolicy',
            criticalIllness: 'deleteCriticalIllnessPolicy',
            incomeProtection: 'deleteIncomeProtectionPolicy',
            disability: 'deleteDisabilityPolicy',
            sicknessIllness: 'deleteSicknessIllnessPolicy',
        };

        const action = actionMap[policyType];
        if (!action) {
            throw new Error(`Unknown policy type: ${policyType}`);
        }
        return dispatch(action, id);
    },

    // Life Insurance Policy Actions
    createLifePolicy: createPolicyActionFactory('life', 'createLifePolicy', 'Failed to create life insurance policy'),
    updateLifePolicy: updatePolicyActionFactory('life', 'updateLifePolicy', 'Failed to update life insurance policy'),
    deleteLifePolicy: deletePolicyActionFactory('life', 'deleteLifePolicy', 'Failed to delete life insurance policy'),

    // Critical Illness Policy Actions
    createCriticalIllnessPolicy: createPolicyActionFactory('criticalIllness', 'createCriticalIllnessPolicy', 'Failed to create critical illness policy'),
    updateCriticalIllnessPolicy: updatePolicyActionFactory('criticalIllness', 'updateCriticalIllnessPolicy', 'Failed to update critical illness policy'),
    deleteCriticalIllnessPolicy: deletePolicyActionFactory('criticalIllness', 'deleteCriticalIllnessPolicy', 'Failed to delete critical illness policy'),

    // Income Protection Policy Actions
    createIncomeProtectionPolicy: createPolicyActionFactory('incomeProtection', 'createIncomeProtectionPolicy', 'Failed to create income protection policy'),
    updateIncomeProtectionPolicy: updatePolicyActionFactory('incomeProtection', 'updateIncomeProtectionPolicy', 'Failed to update income protection policy'),
    deleteIncomeProtectionPolicy: deletePolicyActionFactory('incomeProtection', 'deleteIncomeProtectionPolicy', 'Failed to delete income protection policy'),

    // Disability Policy Actions
    createDisabilityPolicy: createPolicyActionFactory('disability', 'createDisabilityPolicy', 'Failed to create disability policy'),
    updateDisabilityPolicy: updatePolicyActionFactory('disability', 'updateDisabilityPolicy', 'Failed to update disability policy'),
    deleteDisabilityPolicy: deletePolicyActionFactory('disability', 'deleteDisabilityPolicy', 'Failed to delete disability policy'),

    // Sickness/Illness Policy Actions
    createSicknessIllnessPolicy: createPolicyActionFactory('sicknessIllness', 'createSicknessIllnessPolicy', 'Failed to create sickness/illness policy'),
    updateSicknessIllnessPolicy: updatePolicyActionFactory('sicknessIllness', 'updateSicknessIllnessPolicy', 'Failed to update sickness/illness policy'),
    deleteSicknessIllnessPolicy: deletePolicyActionFactory('sicknessIllness', 'deleteSicknessIllnessPolicy', 'Failed to delete sickness/illness policy'),
};

const mutations = {
    setProfile(state, profile) {
        state.profile = profile;
    },

    setPolicies(state, policies) {
        state.policies = {
            life: policies.life_insurance || policies.life || [],
            criticalIllness: policies.critical_illness || [],
            incomeProtection: policies.income_protection || [],
            disability: policies.disability || [],
            sicknessIllness: policies.sickness_illness || [],
        };
    },

    setAnalysis(state, analysis) {
        state.analysis = analysis;
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

    addPolicy(state, { type, policy }) {
        state.policies[type].push(policy);
    },

    updatePolicy(state, { type, policy }) {
        const index = state.policies[type].findIndex(p => p.id === policy.id);
        if (index !== -1) {
            state.policies[type].splice(index, 1, policy);
        }
    },

    removePolicy(state, { type, id }) {
        const index = state.policies[type].findIndex(p => p.id === id);
        if (index !== -1) {
            state.policies[type].splice(index, 1);
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
