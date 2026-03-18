import api from './api';

export default {
  getDecisionMatrix(module) {
    return api.get(`/admin/decision-matrix/${module}`);
  },
  getDefinitions(module) {
    return api.get(`/admin/action-definitions/${module}`);
  },
  getDefinition(module, id) {
    return api.get(`/admin/action-definitions/${module}/${id}`);
  },
  createDefinition(module, data) {
    return api.post(`/admin/action-definitions/${module}`, data);
  },
  updateDefinition(module, id, data) {
    return api.patch(`/admin/action-definitions/${module}/${id}`, data);
  },
  deleteDefinition(module, id) {
    return api.delete(`/admin/action-definitions/${module}/${id}`);
  },
  toggleDefinition(module, id) {
    return api.patch(`/admin/action-definitions/${module}/${id}/toggle`);
  },
};
