import api from './api';

const pipelinePostsService = {
  async list(params = {}) {
    return (await api.get('/admin/pipeline/posts', { params })).data;
  },

  async get(id) {
    return (await api.get(`/admin/pipeline/posts/${id}`)).data;
  },

  async update(id, payload) {
    return (await api.patch(`/admin/pipeline/posts/${id}`, payload)).data;
  },

  async approve(id) {
    return (await api.post(`/admin/pipeline/posts/${id}/approve`)).data;
  },

  async reject(id, reason) {
    return (await api.post(`/admin/pipeline/posts/${id}/reject`, { reason })).data;
  },

  async listCampaigns(params = {}) {
    return (await api.get('/admin/pipeline/campaigns', { params })).data;
  },
};

export default pipelinePostsService;
