import api from './api';

const newsService = {
  async list({ page = 1 } = {}) {
    return (await api.get('/news', { params: { page } })).data;
  },
  async getBySlug(slug) {
    return (await api.get(`/news/${slug}`)).data;
  },
};

export default newsService;
