import api from './api';

const pipelineArticlesService = {
  async list(params = {}) {
    return (await api.get('/admin/pipeline/articles', { params })).data;
  },
  async get(id) {
    return (await api.get(`/admin/pipeline/articles/${id}`)).data;
  },
  async update(id, payload) {
    return (await api.patch(`/admin/pipeline/articles/${id}`, payload)).data;
  },
  async publishLocal(id) {
    return (await api.post(`/admin/pipeline/articles/${id}/publish-local`)).data;
  },
  async pushToDev(id) {
    return (await api.post(`/admin/pipeline/articles/${id}/push-to-dev`)).data;
  },
  async pushToLive(id) {
    return (await api.post(`/admin/pipeline/articles/${id}/push-to-live`)).data;
  },
  async reimport(id) {
    return (await api.post(`/admin/pipeline/articles/${id}/reimport`)).data;
  },
  async remove(id) {
    return (await api.delete(`/admin/pipeline/articles/${id}`)).data;
  },
  async listCampaigns() {
    return (await api.get('/admin/pipeline/campaigns', { params: { active_only: true } })).data;
  },
};

export default pipelineArticlesService;
