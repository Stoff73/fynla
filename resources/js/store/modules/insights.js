import insightsService from '@/services/insightsService';

const state = () => ({
  // Public
  list: [],
  listLoading: false,
  featured: null,
  supporting: [],
  featuredLoading: false,
  current: null,
  currentLoading: false,
  error: null,

  // Admin
  adminList: [],
  adminPagination: null,
  templates: [],
});

const mutations = {
  setList(state, list) { state.list = list; },
  setListLoading(state, v) { state.listLoading = v; },
  setFeatured(state, { featured, supporting }) {
    state.featured = featured;
    state.supporting = supporting;
  },
  setFeaturedLoading(state, v) { state.featuredLoading = v; },
  setCurrent(state, article) { state.current = article; },
  setCurrentLoading(state, v) { state.currentLoading = v; },
  setError(state, e) { state.error = e; },
  setAdminList(state, { data, pagination }) {
    state.adminList = data;
    state.adminPagination = pagination;
  },
  setTemplates(state, list) { state.templates = list; },
  addTemplate(state, t) { state.templates.push(t); },
  updateTemplate(state, t) {
    const i = state.templates.findIndex(x => x.id === t.id);
    if (i !== -1) state.templates.splice(i, 1, t);
  },
  removeTemplate(state, id) {
    state.templates = state.templates.filter(t => t.id !== id);
  },
};

const actions = {
  async fetchList({ commit }, { category } = {}) {
    commit('setListLoading', true);
    try {
      const res = await insightsService.list({ category });
      commit('setList', res.data);
    } catch (e) {
      commit('setError', e.message);
      throw e;
    } finally {
      commit('setListLoading', false);
    }
  },

  async fetchFeatured({ commit }) {
    commit('setFeaturedLoading', true);
    try {
      const res = await insightsService.featured();
      commit('setFeatured', {
        featured: res.data.featured,
        supporting: res.data.supporting || [],
      });
    } catch (e) {
      commit('setError', e.message);
      throw e;
    } finally {
      commit('setFeaturedLoading', false);
    }
  },

  async fetchBySlug({ commit }, { slug, preview = false }) {
    commit('setCurrentLoading', true);
    try {
      const res = await insightsService.getBySlug(slug, { preview });
      commit('setCurrent', res.data);
      return res.data;
    } catch (e) {
      commit('setError', e.message);
      throw e;
    } finally {
      commit('setCurrentLoading', false);
    }
  },

  async fetchAdminList({ commit }, params = {}) {
    const res = await insightsService.adminList(params);
    commit('setAdminList', {
      data: res.data,
      pagination: {
        current_page: res.meta?.current_page,
        last_page: res.meta?.last_page,
        total: res.meta?.total,
      },
    });
  },

  async fetchTemplates({ commit }) {
    const res = await insightsService.listTemplates();
    commit('setTemplates', res.data);
  },

  async saveAsTemplate({ commit }, payload) {
    const res = await insightsService.saveTemplate(payload);
    commit('addTemplate', res.data);
    return res.data;
  },

  async renameTemplate({ commit }, { id, name }) {
    const res = await insightsService.renameTemplate(id, name);
    commit('updateTemplate', res.data);
    return res.data;
  },

  async deleteTemplate({ commit }, id) {
    await insightsService.deleteTemplate(id);
    commit('removeTemplate', id);
  },
};

const getters = {
  listItems: s => s.list,
  featured: s => s.featured,
  supporting: s => s.supporting,
  current: s => s.current,
  templates: s => s.templates,
};

export default {
  namespaced: true,
  state,
  mutations,
  actions,
  getters,
};
