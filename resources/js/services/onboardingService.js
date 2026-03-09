import api from './api';

const onboardingService = {
  /**
   * Get onboarding status for the authenticated user
   */
  async getStatus() {
    const response = await api.get('/onboarding/status');
    return response.data;
  },

  /**
   * Set the focus area for onboarding
   */
  async setFocusArea(focusArea) {
    const response = await api.post('/onboarding/focus-area', {
      focus_area: focusArea,
    });
    return response.data;
  },

  /**
   * Get all steps for the current focus area
   */
  async getSteps() {
    const response = await api.get('/onboarding/steps');
    return response.data;
  },

  /**
   * Get data for a specific step
   */
  async getStepData(stepName) {
    const response = await api.get(`/onboarding/step/${stepName}`);
    return response.data;
  },

  /**
   * Save progress for a step
   */
  async saveStepProgress(stepName, data) {
    const response = await api.post('/onboarding/step', {
      step_name: stepName,
      data,
    });
    return response.data;
  },

  /**
   * Skip a step
   */
  async skipStep(stepName) {
    const response = await api.post('/onboarding/skip-step', {
      step_name: stepName,
    });
    return response.data;
  },

  /**
   * Get skip reason text for a step
   */
  async getSkipReason(stepName) {
    const response = await api.get(`/onboarding/skip-reason/${stepName}`);
    return response.data;
  },

  /**
   * Skip all remaining steps and go to dashboard
   */
  async skipToDashboard() {
    const response = await api.post('/onboarding/skip-to-dashboard');
    return response.data;
  },

  /**
   * Complete the onboarding process
   */
  async completeOnboarding() {
    const response = await api.post('/onboarding/complete');
    return response.data;
  },

  /**
   * Complete quick onboarding (3-step progressive flow)
   */
  async completeQuickOnboarding() {
    const response = await api.post('/onboarding/complete-quick');
    return response.data;
  },

  /**
   * Restart the onboarding process
   */
  async restartOnboarding() {
    const response = await api.post('/onboarding/restart');
    return response.data;
  },
};

export default onboardingService;
