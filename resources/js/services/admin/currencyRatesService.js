import api from '@/services/api';

const currencyRatesService = {
  async list() {
    return (await api.get('/admin/currency-rates')).data;
  },
  async create(payload) {
    return (await api.post('/admin/currency-rates', payload)).data;
  },
  async update(id, payload) {
    return (await api.patch(`/admin/currency-rates/${id}`, payload)).data;
  },
  async delete(id) {
    return (await api.delete(`/admin/currency-rates/${id}`)).data;
  },
};

export default currencyRatesService;
