import api from './api';

const journeyService = {
  async getSelections() {
    const response = await api.get('/journeys/selections');
    return response.data;
  },

  async saveSelections(journeys) {
    const response = await api.post('/journeys/selections', { journeys });
    return response.data;
  },

  async getPreview(journeys) {
    const params = journeys.map(j => `journeys[]=${encodeURIComponent(j)}`).join('&');
    const response = await api.get(`/journeys/preview?${params}`);
    return response.data;
  },

  async getSteps(journey) {
    const response = await api.get(`/journeys/${journey}/steps`);
    return response.data;
  },

  async startJourney(journey) {
    const response = await api.post(`/journeys/${journey}/start`);
    return response.data;
  },

  async completeJourney(journey) {
    const response = await api.post(`/journeys/${journey}/complete`);
    return response.data;
  },

  async getDashboardPrompts() {
    const response = await api.get('/journeys/dashboard-prompts');
    return response.data;
  },

  async dismissPrompt(promptId) {
    const response = await api.post('/journeys/dismiss-prompt', { prompt_id: promptId });
    return response.data;
  },
};

export default journeyService;
