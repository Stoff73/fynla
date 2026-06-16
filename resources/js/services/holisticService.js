import api from './api';

/**
 * Holistic planning API wrapper.
 */
const holisticService = {
  /**
   * Cross-module composite plan: every module's composed strategies ranked
   * against the household's effective monthly surplus (after goal commitments),
   * each annotated fits / partially_fits / beyond_current_surplus with the
   * running surplus consumed. Read-only.
   *
   * GET /api/holistic/composite-plan
   */
  async getCompositePlan() {
    const response = await api.get('/holistic/composite-plan');
    return response.data;
  },
};

export default holisticService;
