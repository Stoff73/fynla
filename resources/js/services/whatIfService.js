import api from './api';

const whatIfService = {
  async getScenarios() {
    return (await api.get('/what-if-scenarios')).data;
  },

  async getScenarioCount() {
    return (await api.get('/what-if-scenarios/count')).data;
  },

  async getScenarioComparison(scenarioId) {
    return (await api.get(`/what-if-scenarios/${scenarioId}`)).data;
  },

  async createScenario(data) {
    return (await api.post('/what-if-scenarios', data)).data;
  },

  async renameScenario(scenarioId, name) {
    return (await api.put(`/what-if-scenarios/${scenarioId}`, { name })).data;
  },

  async deleteScenario(scenarioId) {
    return (await api.delete(`/what-if-scenarios/${scenarioId}`)).data;
  },
};

export default whatIfService;
