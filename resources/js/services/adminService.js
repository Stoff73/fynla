import api from './api';

export default {
  // Dashboard
  getDashboard() {
    return api.get('/admin/dashboard');
  },

  // User Management
  getUsers(params = {}) {
    return api.get('/admin/users', { params });
  },

  createUser(userData) {
    return api.post('/admin/users', userData);
  },

  updateUser(userId, userData) {
    return api.put(`/admin/users/${userId}`, userData);
  },

  deleteUser(userId) {
    return api.delete(`/admin/users/${userId}`);
  },

  // Roles
  getRoles() {
    return api.get('/admin/roles');
  },

  // Subscription Stats
  getSubscriptionStats() {
    return api.get('/admin/subscriptions/stats');
  },

  // Database Backup
  createBackup() {
    return api.post('/admin/backup/create');
  },

  listBackups() {
    return api.get('/admin/backup/list');
  },

  restoreBackup(filename) {
    return api.post('/admin/backup/restore', { filename });
  },

  deleteBackup(filename) {
    return api.delete('/admin/backup/delete', { data: { filename } });
  },
};
