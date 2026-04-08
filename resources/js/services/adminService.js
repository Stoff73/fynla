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

  // User Module Status
  getUserModuleStatus(userId) {
    return api.get(`/admin/users/${userId}/module-status`);
  },

  // Roles
  getRoles() {
    return api.get('/admin/roles');
  },

  // Subscription Stats
  getSubscriptionStats() {
    return api.get('/admin/subscriptions/stats');
  },

  // Retirement Action Definitions
  getRetirementActions() {
    return api.get('/admin/retirement-actions');
  },

  createRetirementAction(data) {
    return api.post('/admin/retirement-actions', data);
  },

  updateRetirementAction(id, data) {
    return api.put(`/admin/retirement-actions/${id}`, data);
  },

  deleteRetirementAction(id) {
    return api.delete(`/admin/retirement-actions/${id}`);
  },

  toggleRetirementAction(id) {
    return api.patch(`/admin/retirement-actions/${id}/toggle`);
  },

  // Investment Action Definitions
  getInvestmentActions() {
    return api.get('/admin/investment-actions');
  },

  createInvestmentAction(data) {
    return api.post('/admin/investment-actions', data);
  },

  updateInvestmentAction(id, data) {
    return api.put(`/admin/investment-actions/${id}`, data);
  },

  deleteInvestmentAction(id) {
    return api.delete(`/admin/investment-actions/${id}`);
  },

  toggleInvestmentAction(id) {
    return api.patch(`/admin/investment-actions/${id}/toggle`);
  },

  // Protection Action Definitions
  getProtectionActions() {
    return api.get('/admin/protection-actions');
  },

  createProtectionAction(data) {
    return api.post('/admin/protection-actions', data);
  },

  updateProtectionAction(id, data) {
    return api.put(`/admin/protection-actions/${id}`, data);
  },

  deleteProtectionAction(id) {
    return api.delete(`/admin/protection-actions/${id}`);
  },

  toggleProtectionAction(id) {
    return api.patch(`/admin/protection-actions/${id}/toggle`);
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

  // User Metrics
  getUserMetricsSnapshot() {
    return api.get('/admin/user-metrics/snapshot');
  },

  getUserMetricsTrials() {
    return api.get('/admin/user-metrics/trials');
  },

  getUserMetricsPlans() {
    return api.get('/admin/user-metrics/plans');
  },

  getUserMetricsActivity(period = 'day', range = 7) {
    return api.get('/admin/user-metrics/activity', { params: { period, range } });
  },

  getUserMetricsEngagement() {
    return api.get('/admin/user-metrics/engagement');
  },

  // Discount Code Management
  getDiscountCodes() {
    return api.get('/admin/discount-codes');
  },

  createDiscountCode(data) {
    return api.post('/admin/discount-codes', data);
  },

  updateDiscountCode(id, data) {
    return api.put(`/admin/discount-codes/${id}`, data);
  },

  deleteDiscountCode(id) {
    return api.delete(`/admin/discount-codes/${id}`);
  },

  toggleDiscountCode(id) {
    return api.patch(`/admin/discount-codes/${id}/toggle`);
  },
};
