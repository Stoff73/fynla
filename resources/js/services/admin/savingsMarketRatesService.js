import api from '@/services/api';

const savingsMarketRatesService = {
  async list() {
    return (await api.get('/admin/savings-market-rates')).data;
  },
  async create(payload) {
    return (await api.post('/admin/savings-market-rates', payload)).data;
  },
  async update(id, payload) {
    return (await api.patch(`/admin/savings-market-rates/${id}`, payload)).data;
  },
  async delete(id) {
    return (await api.delete(`/admin/savings-market-rates/${id}`)).data;
  },
};

export default savingsMarketRatesService;
