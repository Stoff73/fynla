import api from './api';

const insightsService = {
  // Public
  async list({ category } = {}) {
    const params = {};
    if (category) params.category = category;
    return (await api.get('/insights', { params })).data;
  },
  async featured() {
    return (await api.get('/insights/featured')).data;
  },
  async getBySlug(slug, { preview = false } = {}) {
    const params = preview ? { preview: 'true' } : {};
    return (await api.get(`/insights/${slug}`, { params })).data;
  },

  // Admin — articles
  async adminList({ status, category, featured, page = 1 } = {}) {
    const params = { page };
    if (status) params.status = status;
    if (category) params.category = category;
    if (featured !== undefined) params.featured = featured ? 1 : 0;
    return (await api.get('/admin/insights/articles', { params })).data;
  },
  async adminGet(id) {
    return (await api.get(`/admin/insights/articles/${id}`)).data;
  },
  async create(data) {
    return (await api.post('/admin/insights/articles', data)).data;
  },
  async update(id, data) {
    return (await api.put(`/admin/insights/articles/${id}`, data)).data;
  },
  async remove(id) {
    return (await api.delete(`/admin/insights/articles/${id}`)).data;
  },
  async publish(id) {
    return (await api.post(`/admin/insights/articles/${id}/publish`)).data;
  },
  async archive(id) {
    return (await api.post(`/admin/insights/articles/${id}/archive`)).data;
  },
  async unarchive(id) {
    return (await api.post(`/admin/insights/articles/${id}/unarchive`)).data;
  },
  async feature(id) {
    return (await api.post(`/admin/insights/articles/${id}/feature`)).data;
  },
  async unfeature(id) {
    return (await api.post(`/admin/insights/articles/${id}/unfeature`)).data;
  },
  async resyncTemplate(id) {
    return (await api.post(`/admin/insights/articles/${id}/resync-template`)).data;
  },
  async revisions(id) {
    return (await api.get(`/admin/insights/articles/${id}/revisions`)).data;
  },
  async restoreRevision(articleId, revisionId) {
    return (await api.post(`/admin/insights/articles/${articleId}/revisions/${revisionId}/restore`)).data;
  },

  // Admin — templates
  async listTemplates() {
    return (await api.get('/admin/insights/templates')).data;
  },
  async saveTemplate({ articleId, name, description }) {
    return (await api.post('/admin/insights/templates', {
      article_id: articleId,
      name,
      description,
    })).data;
  },
  async renameTemplate(id, name) {
    return (await api.put(`/admin/insights/templates/${id}`, { name })).data;
  },
  async deleteTemplate(id) {
    return (await api.delete(`/admin/insights/templates/${id}`)).data;
  },

  // Admin — images
  async uploadImage(file, slug) {
    const form = new FormData();
    form.append('image', file);
    form.append('slug', slug);
    return (await api.post('/admin/insights/images', form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })).data;
  },
};

export default insightsService;
