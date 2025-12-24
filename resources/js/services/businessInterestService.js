import api from './api';

export default {
  // Business Interest CRUD operations
  async getBusinessInterests() {
    const response = await api.get('/business-interests');
    return response.data;
  },

  async getBusinessInterest(id) {
    const response = await api.get(`/business-interests/${id}`);
    return response.data;
  },

  async createBusinessInterest(data) {
    const response = await api.post('/business-interests', data);
    return response.data;
  },

  async updateBusinessInterest(id, data) {
    const response = await api.put(`/business-interests/${id}`, data);
    return response.data;
  },

  async deleteBusinessInterest(id) {
    const response = await api.delete(`/business-interests/${id}`);
    return response.data;
  },

  // Tax deadlines for a business
  async getTaxDeadlines(id) {
    const response = await api.get(`/business-interests/${id}/tax-deadlines`);
    return response.data;
  },

  // Exit/sale CGT calculation
  async getExitCalculation(id) {
    const response = await api.get(`/business-interests/${id}/exit-calculation`);
    return response.data;
  },
};
