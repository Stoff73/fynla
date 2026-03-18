import api from './api';

const lifeStageService = {
  async getProgress() {
    const response = await api.get('/life-stage/progress');
    return response.data;
  },
  async setStage(stage) {
    const response = await api.post('/life-stage/set', { life_stage: stage });
    return response.data;
  },
  async completeStep(stepId) {
    const response = await api.post('/life-stage/complete-step', { step: stepId });
    return response.data;
  },
  async getCompleteness() {
    const response = await api.get('/life-stage/completeness');
    return response.data;
  },
};

export default lifeStageService;
