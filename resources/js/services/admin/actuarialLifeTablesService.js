import api from '@/services/api';

const actuarialLifeTablesService = {
  async list() {
    return (await api.get('/admin/actuarial-life-tables')).data;
  },
  async create(payload) {
    return (await api.post('/admin/actuarial-life-tables', payload)).data;
  },
  async update(id, payload) {
    return (await api.patch(`/admin/actuarial-life-tables/${id}`, payload)).data;
  },
  async delete(id) {
    return (await api.delete(`/admin/actuarial-life-tables/${id}`)).data;
  },
};

export default actuarialLifeTablesService;
