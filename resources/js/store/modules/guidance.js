/**
 * Guidance Store Module
 *
 * Manages the post-registration guidance system that walks new users
 * through key sections of the app step by step.
 */

import api from '@/services/api';

import logger from '@/utils/logger';
// Define all guidance steps with their targets and routes
const GUIDANCE_STEPS = [
    {
        id: 'personal_info',
        label: 'Personal Information',
        description: 'Review and update your basic details like name, date of birth, and contact information.',
        target: '#profile-section',
        route: '/profile',
        icon: 'user',
    },
    {
        id: 'family',
        label: 'Family Members',
        description: 'Add your spouse, children, and dependants to personalise your financial plan.',
        target: '#family-section',
        route: '/profile',
        icon: 'users',
    },
    {
        id: 'properties',
        label: 'Properties',
        description: 'Add your home and any other properties you own.',
        target: '#property-card',
        route: '/net-worth',
        icon: 'home',
    },
    {
        id: 'savings',
        label: 'Savings',
        description: 'Record your savings accounts, ISAs, and emergency fund.',
        target: '#savings-card',
        route: '/savings',
        icon: 'piggy-bank',
    },
    {
        id: 'investments',
        label: 'Investments',
        description: 'Add your investment accounts and holdings.',
        target: '#investment-card',
        route: '/investments',
        icon: 'trending-up',
    },
    {
        id: 'pensions',
        label: 'Pensions',
        description: 'Track your workplace, personal, and state pensions.',
        target: '#retirement-card',
        route: '/retirement',
        icon: 'calendar',
    },
    {
        id: 'protection',
        label: 'Protection',
        description: 'Review your life insurance, critical illness, and income protection cover.',
        target: '#protection-card',
        route: '/protection',
        icon: 'shield',
    },
    {
        id: 'income',
        label: 'Income & Expenditure',
        description: 'Add your income sources and monthly expenditure.',
        target: '#income-section',
        route: '/profile',
        icon: 'pound-sign',
    },
];

const GUIDANCE_VERSION = '1.0.0';

const state = {
    isActive: false,
    currentStepIndex: 0,
    completedSteps: [],
    skippedSteps: [],
    version: GUIDANCE_VERSION,
    showWelcomeModal: false,
    initialized: false,
};

const getters = {
    // All defined steps
    allSteps: () => GUIDANCE_STEPS,

    // Current step details
    currentStep: (state) => {
        if (state.currentStepIndex >= 0 && state.currentStepIndex < GUIDANCE_STEPS.length) {
            return GUIDANCE_STEPS[state.currentStepIndex];
        }
        return null;
    },

    // Current step ID
    currentStepId: (state, getters) => getters.currentStep?.id || null,

    // Progress information
    progress: (state) => {
        const totalHandled = state.completedSteps.length + state.skippedSteps.length;
        return {
            current: state.currentStepIndex + 1,
            total: GUIDANCE_STEPS.length,
            completed: state.completedSteps.length,
            skipped: state.skippedSteps.length,
            handled: totalHandled,
            percentage: Math.round((totalHandled / GUIDANCE_STEPS.length) * 100),
        };
    },

    // Whether all steps have been completed or skipped
    isComplete: (state) => {
        return state.completedSteps.length + state.skippedSteps.length >= GUIDANCE_STEPS.length;
    },

    // Whether guidance is actively running
    isActive: (state) => state.isActive,

    // Whether welcome modal should show
    shouldShowWelcomeModal: (state) => state.showWelcomeModal,

    // Check if a specific step has been completed
    isStepCompleted: (state) => (stepId) => state.completedSteps.includes(stepId),

    // Check if a specific step has been skipped
    isStepSkipped: (state) => (stepId) => state.skippedSteps.includes(stepId),

    // Check if a specific step has been handled (completed or skipped)
    isStepHandled: (state, getters) => (stepId) => {
        return getters.isStepCompleted(stepId) || getters.isStepSkipped(stepId);
    },

    // Get remaining steps
    remainingSteps: (state, getters) => {
        return GUIDANCE_STEPS.filter((step) => !getters.isStepHandled(step.id));
    },

    // Get next unhandled step
    nextUnhandledStep: (state, getters) => {
        const remaining = getters.remainingSteps;
        return remaining.length > 0 ? remaining[0] : null;
    },
};

const mutations = {
    SET_ACTIVE(state, isActive) {
        state.isActive = isActive;
    },

    SET_STEP(state, index) {
        state.currentStepIndex = Math.max(0, Math.min(index, GUIDANCE_STEPS.length - 1));
    },

    ADD_COMPLETED_STEP(state, stepId) {
        if (!state.completedSteps.includes(stepId)) {
            state.completedSteps.push(stepId);
        }
        // Remove from skipped if it was there
        state.skippedSteps = state.skippedSteps.filter((id) => id !== stepId);
    },

    ADD_SKIPPED_STEP(state, stepId) {
        if (!state.skippedSteps.includes(stepId)) {
            state.skippedSteps.push(stepId);
        }
        // Remove from completed if it was there
        state.completedSteps = state.completedSteps.filter((id) => id !== stepId);
    },

    SET_COMPLETED_STEPS(state, steps) {
        state.completedSteps = steps || [];
    },

    SET_SKIPPED_STEPS(state, steps) {
        state.skippedSteps = steps || [];
    },

    SET_VERSION(state, version) {
        state.version = version;
    },

    SET_SHOW_WELCOME_MODAL(state, show) {
        state.showWelcomeModal = show;
    },

    SET_STATUS(state, status) {
        state.isActive = status.guidance_active || false;
        state.currentStepIndex = status.guidance_current_step || 0;
        state.completedSteps = status.guidance_completed_steps || [];
        state.skippedSteps = status.guidance_skipped_steps || [];
        state.version = status.guidance_version || GUIDANCE_VERSION;
    },

    SET_INITIALIZED(state, initialized) {
        state.initialized = initialized;
    },

    RESET(state) {
        state.isActive = false;
        state.currentStepIndex = 0;
        state.completedSteps = [];
        state.skippedSteps = [];
        state.version = GUIDANCE_VERSION;
        state.showWelcomeModal = false;
        state.initialized = false;
    },
};

const actions = {
    /**
     * Start the guidance system
     */
    async startGuidance({ commit, dispatch }) {
        commit('SET_ACTIVE', true);
        commit('SET_STEP', 0);
        commit('SET_SHOW_WELCOME_MODAL', false);
        await dispatch('saveStatus');
    },

    /**
     * Show the welcome modal (for new registrations)
     */
    showWelcomeModal({ commit }) {
        commit('SET_SHOW_WELCOME_MODAL', true);
    },

    /**
     * Hide the welcome modal
     */
    hideWelcomeModal({ commit }) {
        commit('SET_SHOW_WELCOME_MODAL', false);
    },

    /**
     * Complete a step and move to next
     */
    async completeStep({ commit, state, getters, dispatch }, stepId) {
        commit('ADD_COMPLETED_STEP', stepId);

        // Find next unhandled step
        const nextStep = getters.nextUnhandledStep;
        if (nextStep) {
            const nextIndex = GUIDANCE_STEPS.findIndex((s) => s.id === nextStep.id);
            commit('SET_STEP', nextIndex);
        }

        // Check if guidance is now complete
        if (getters.isComplete) {
            commit('SET_ACTIVE', false);
        }

        await dispatch('saveStatus');
    },

    /**
     * Skip a step and move to next
     */
    async skipStep({ commit, state, getters, dispatch }, stepId) {
        commit('ADD_SKIPPED_STEP', stepId);

        // Find next unhandled step
        const nextStep = getters.nextUnhandledStep;
        if (nextStep) {
            const nextIndex = GUIDANCE_STEPS.findIndex((s) => s.id === nextStep.id);
            commit('SET_STEP', nextIndex);
        }

        // Check if guidance is now complete
        if (getters.isComplete) {
            commit('SET_ACTIVE', false);
        }

        await dispatch('saveStatus');
    },

    /**
     * Go to a specific step
     */
    async goToStep({ commit, dispatch }, stepId) {
        const index = GUIDANCE_STEPS.findIndex((s) => s.id === stepId);
        if (index >= 0) {
            commit('SET_STEP', index);
            await dispatch('saveStatus');
        }
    },

    /**
     * Dismiss/cancel the guidance system
     */
    async dismissGuidance({ commit, dispatch }) {
        commit('SET_ACTIVE', false);
        commit('SET_SHOW_WELCOME_MODAL', false);
        await dispatch('saveStatus');
    },

    /**
     * Re-enable guidance (from settings)
     */
    async restartGuidance({ commit, dispatch }) {
        commit('RESET');
        commit('SET_ACTIVE', true);
        commit('SET_SHOW_WELCOME_MODAL', true);
        await dispatch('saveStatus');
    },

    /**
     * Save guidance status to backend
     */
    async saveStatus({ state }) {
        try {
            await api.post('/user/guidance-status', {
                guidance_active: state.isActive,
                guidance_current_step: state.currentStepIndex,
                guidance_completed_steps: state.completedSteps,
                guidance_skipped_steps: state.skippedSteps,
                guidance_version: state.version,
            });
        } catch (error) {
            logger.error('Failed to save guidance status:', error);
            // Don't throw - guidance is non-critical
        }
    },

    /**
     * Fetch guidance status from backend
     */
    async fetchStatus({ commit, state }) {
        // Only fetch if not already initialized
        if (state.initialized) {
            return;
        }

        try {
            const response = await api.get('/user/guidance-status');
            if (response.data) {
                commit('SET_STATUS', response.data);
            }
            commit('SET_INITIALIZED', true);
        } catch (error) {
            logger.error('Failed to fetch guidance status:', error);
            commit('SET_INITIALIZED', true);
            // Don't throw - guidance is non-critical
        }
    },

    /**
     * Initialize guidance from user data (called after login/register)
     */
    initFromUser({ commit }, user) {
        if (!user) return;

        commit('SET_STATUS', {
            guidance_active: user.guidance_active || false,
            guidance_current_step: user.guidance_current_step || 0,
            guidance_completed_steps: user.guidance_completed_steps || [],
            guidance_skipped_steps: user.guidance_skipped_steps || [],
            guidance_version: user.guidance_version || GUIDANCE_VERSION,
        });
        commit('SET_INITIALIZED', true);

        // Show welcome modal for new users with active guidance
        if (user.guidance_active && !user.guidance_completed) {
            commit('SET_SHOW_WELCOME_MODAL', true);
        }
    },

    /**
     * Reset guidance state (for logout)
     */
    reset({ commit }) {
        commit('RESET');
    },
};

export default {
    namespaced: true,
    state,
    getters,
    mutations,
    actions,
};

// Export steps for use in components
export { GUIDANCE_STEPS, GUIDANCE_VERSION };
