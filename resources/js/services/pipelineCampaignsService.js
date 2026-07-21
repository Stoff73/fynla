import api from './api';

/**
 * Admin CRUD for marketing campaigns (pipeline_campaigns). Wraps the
 * already-existing /api/admin/pipeline/campaigns REST resource.
 */
const pipelineCampaignsService = {
  async list(params = {}) {
    return (await api.get('/admin/pipeline/campaigns', { params })).data;
  },
  async get(id) {
    return (await api.get(`/admin/pipeline/campaigns/${id}`)).data;
  },
  async create(payload) {
    return (await api.post('/admin/pipeline/campaigns', payload)).data;
  },
  async update(id, payload) {
    return (await api.put(`/admin/pipeline/campaigns/${id}`, payload)).data;
  },
  async remove(id) {
    return (await api.delete(`/admin/pipeline/campaigns/${id}`)).data;
  },
};

export default pipelineCampaignsService;
