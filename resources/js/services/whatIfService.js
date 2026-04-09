import api from './api';

const whatIfService = {
  async getScenarios() {
    return (await api.get('/what-if-scenarios')).data;
  },

  async getScenarioComparison(scenarioId) {
    return (await api.get(`/what-if-scenarios/${scenarioId}`)).data;
  },

  async createScenario(data) {
    return (await api.post('/what-if-scenarios', data)).data;
  },

  async deleteScenario(scenarioId) {
    return (await api.delete(`/what-if-scenarios/${scenarioId}`)).data;
  },
};

export default whatIfService;
