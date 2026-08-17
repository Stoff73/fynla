import api from './api';

const pipelineClipApprovalsService = {
  async list(params = {}) {
    return (await api.get('/admin/pipeline/clip-approvals', { params })).data;
  },
  async approve(id) {
    return (await api.post(`/admin/pipeline/clip-approvals/${id}/approve`)).data;
  },
  async reject(id, reason) {
    return (await api.post(`/admin/pipeline/clip-approvals/${id}/reject`, { reason })).data;
  },
  async approveAll(pipelineArticleId) {
    return (await api.post(`/admin/pipeline/clip-approvals/article/${pipelineArticleId}/approve-all`)).data;
  },
};

export default pipelineClipApprovalsService;
