import chattelService from '../../services/chattelService';

const state = {
  chattels: [],
  selectedChattel: null,
  cgtCalculation: null,
  loading: false,
  error: null,
};

const getters = {
  chattelsByType: (state) => {
    const grouped = {};
    state.chattels.forEach(c => {
      const type = c.chattel_type || 'other';
      if (!grouped[type]) {
        grouped[type] = [];
      }
      grouped[type].push(c);
    });
    return grouped;
  },

  totalChattelValue: (state) => {
    return state.chattels.reduce((sum, chattel) => {
      return sum + (chattel.user_share || chattel.current_value || 0);
    }, 0);
  },

  getChattelById: (state) => (id) => {
    return state.chattels.find(c => c.id === id);
  },

  vehicleChattels: (state) => state.chattels.filter(c => c.chattel_type === 'vehicle'),

  nonVehicleChattels: (state) => state.chattels.filter(c => c.chattel_type !== 'vehicle'),

  wastingAssets: (state) => state.chattels.filter(c => c.is_wasting_asset),

  taxableChattels: (state) => state.chattels.filter(c => !c.is_wasting_asset),
};

const actions = {
  async fetchChattels({ commit }) {
    commit('setLoading', true);
    commit('setError', null);

    try {
      const data = await chattelService.getChattels();
      commit('setChattels', data);
      return data;
    } catch (error) {
      const errorMessage = error.response?.data?.message || 'Failed to fetch chattels';
      commit('setError', errorMessage);
      throw error;
    } finally {
      commit('setLoading', false);
    }
  },

  async fetchChattelById({ commit }, id) {
    commit('setLoading', true);
    commit('setError', null);

    try {
      const response = await chattelService.getChattel(id);
      commit('setSelectedChattel', response.data || response);
      return response;
    } catch (error) {
      const errorMessage = error.response?.data?.message || 'Failed to fetch chattel';
      commit('setError', errorMessage);
      throw error;
    } finally {
      commit('setLoading', false);
    }
  },

  async createChattel({ commit, dispatch }, chattelData) {
    commit('setLoading', true);
    commit('setError', null);

    try {
      const response = await chattelService.createChattel(chattelData);
      await dispatch('fetchChattels');
      // Refresh net worth to update wealth summary
      await dispatch('netWorth/refreshNetWorth', null, { root: true });
      return response;
    } catch (error) {
      const errorMessage = error.response?.data?.message || 'Failed to create chattel';
      commit('setError', errorMessage);
      throw error;
    } finally {
      commit('setLoading', false);
    }
  },

  async updateChattel({ commit, dispatch }, { id, data }) {
    commit('setLoading', true);
    commit('setError', null);

    try {
      const response = await chattelService.updateChattel(id, data);
      await dispatch('fetchChattels');
      // Refresh net worth to update wealth summary
      await dispatch('netWorth/refreshNetWorth', null, { root: true });
      return response;
    } catch (error) {
      const errorMessage = error.response?.data?.message || 'Failed to update chattel';
      commit('setError', errorMessage);
      throw error;
    } finally {
      commit('setLoading', false);
    }
  },

  async deleteChattel({ commit, dispatch }, id) {
    commit('setLoading', true);
    commit('setError', null);

    try {
      const response = await chattelService.deleteChattel(id);
      await dispatch('fetchChattels');
      // Refresh net worth to update wealth summary
      await dispatch('netWorth/refreshNetWorth', null, { root: true });
      return response;
    } catch (error) {
      const errorMessage = error.response?.data?.message || 'Failed to delete chattel';
      commit('setError', errorMessage);
      throw error;
    } finally {
      commit('setLoading', false);
    }
  },

  async calculateCGT({ commit }, { chattelId, data }) {
    commit('setLoading', true);
    commit('setError', null);

    try {
      const response = await chattelService.calculateCGT(chattelId, data);
      const calculation = response.data || response;
      commit('setCGTCalculation', calculation);
      return calculation;
    } catch (error) {
      const errorMessage = error.response?.data?.message || 'Failed to calculate CGT';
      commit('setError', errorMessage);
      throw error;
    } finally {
      commit('setLoading', false);
    }
  },

  clearSelectedChattel({ commit }) {
    commit('setSelectedChattel', null);
    commit('setCGTCalculation', null);
  },
};

const mutations = {
  setChattels(state, chattels) {
    state.chattels = chattels;
  },

  setSelectedChattel(state, chattel) {
    state.selectedChattel = chattel;
  },

  setCGTCalculation(state, calculation) {
    state.cgtCalculation = calculation;
  },

  setLoading(state, loading) {
    state.loading = loading;
  },

  setError(state, error) {
    state.error = error;
  },

  clearError(state) {
    state.error = null;
  },
};

export default {
  namespaced: true,
  state,
  getters,
  actions,
  mutations,
};
