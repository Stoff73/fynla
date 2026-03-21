import api from './api';

const whatIfService = {
  async getScenarios() {
    return (await api.get('/api/what-if/scenarios')).data;
  },

  async getScenarioComparison(scenarioId) {
    return (await api.get(`/api/what-if/scenarios/${scenarioId}/comparison`)).data;
  },

  async deleteScenario(scenarioId) {
    return (await api.delete(`/api/what-if/scenarios/${scenarioId}`)).data;
  },
};

export default whatIfService;
