import savingsMarketRatesService from '@/services/admin/savingsMarketRatesService';

const state = {
  items: [],
  loading: false,
  error: null,
};

const getters = {
  items: (s) => s.items,
  loading: (s) => s.loading,
  error: (s) => s.error,
};

const mutations = {
  setItems(s, items) { s.items = items; },
  setLoading(s, v) { s.loading = v; },
  setError(s, e) { s.error = e; },
  addItem(s, item) { s.items.unshift(item); },
  updateItem(s, item) {
    const i = s.items.findIndex((x) => x.id === item.id);
    if (i !== -1) s.items.splice(i, 1, item);
  },
  removeItem(s, id) {
    s.items = s.items.filter((x) => x.id !== id);
  },
};

const actions = {
  async fetchItems({ commit }) {
    commit('setLoading', true);
    commit('setError', null);
    try {
      const res = await savingsMarketRatesService.list();
      commit('setItems', res.data);
    } catch (e) {
      commit('setError', e.message || 'Failed to load savings market rates');
      throw e;
    } finally {
      commit('setLoading', false);
    }
  },
  async createItem({ commit }, payload) {
    const res = await savingsMarketRatesService.create(payload);
    commit('addItem', res.data);
    return res.data;
  },
  async updateItem({ commit }, { id, payload }) {
    const res = await savingsMarketRatesService.update(id, payload);
    commit('updateItem', res.data);
    return res.data;
  },
  async deleteItem({ commit }, id) {
    await savingsMarketRatesService.delete(id);
    commit('removeItem', id);
  },
};

export default { namespaced: true, state, getters, mutations, actions };
