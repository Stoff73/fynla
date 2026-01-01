import api from './api';

export default {
  // Chattel CRUD operations
  async getChattels() {
    const response = await api.get('/chattels');
    return response.data;
  },

  async getChattel(id) {
    const response = await api.get(`/chattels/${id}`);
    return response.data;
  },

  async createChattel(data) {
    const response = await api.post('/chattels', data);
    return response.data;
  },

  async updateChattel(id, data) {
    const response = await api.put(`/chattels/${id}`, data);
    return response.data;
  },

  async deleteChattel(id) {
    const response = await api.delete(`/chattels/${id}`);
    return response.data;
  },

  // CGT calculation for chattel disposal
  async calculateCGT(id, data) {
    const response = await api.post(`/chattels/${id}/calculate-cgt`, data);
    return response.data;
  },
};
