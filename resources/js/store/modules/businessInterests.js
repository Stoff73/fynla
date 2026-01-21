import businessInterestService from '../../services/businessInterestService';

const state = {
  businesses: [],
  selectedBusiness: null,
  taxDeadlines: null,
  exitCalculation: null,
  loading: false,
  error: null,
};

const getters = {
  tradingBusinesses: (state) => state.businesses.filter(b => b.trading_status === 'trading'),
  dormantBusinesses: (state) => state.businesses.filter(b => b.trading_status === 'dormant'),
  totalBusinessValue: (state) => {
    return state.businesses.reduce((sum, business) => {
      return sum + (business.user_share || business.current_valuation || 0);
    }, 0);
  },
  getBusinessById: (state) => (id) => {
    return state.businesses.find(b => b.id === id);
  },
  businessesByType: (state) => {
    const grouped = {};
    state.businesses.forEach(b => {
      const type = b.business_type || 'other';
      if (!grouped[type]) {
        grouped[type] = [];
      }
      grouped[type].push(b);
    });
    return grouped;
  },
};

const actions = {
  async fetchBusinesses({ commit }) {
    commit('setLoading', true);
    commit('setError', null);

    try {
      const data = await businessInterestService.getBusinessInterests();
      commit('setBusinesses', data);
      return data;
    } catch (error) {
      const errorMessage = error.response?.data?.message || 'Failed to fetch business interests';
      commit('setError', errorMessage);
      throw error;
    } finally {
      commit('setLoading', false);
    }
  },

  async fetchBusinessById({ commit }, id) {
    commit('setLoading', true);
    commit('setError', null);

    try {
      const response = await businessInterestService.getBusinessInterest(id);
      commit('setSelectedBusiness', response.data?.business || response.business || response);
      return response;
    } catch (error) {
      const errorMessage = error.response?.data?.message || 'Failed to fetch business interest';
      commit('setError', errorMessage);
      throw error;
    } finally {
      commit('setLoading', false);
    }
  },

  async createBusiness({ commit, dispatch }, businessData) {
    commit('setLoading', true);
    commit('setError', null);

    try {
      const response = await businessInterestService.createBusinessInterest(businessData);
      await dispatch('fetchBusinesses');
      // Refresh net worth to update wealth summary
      await dispatch('netWorth/refreshNetWorth', null, { root: true });
      return response;
    } catch (error) {
      const errorMessage = error.response?.data?.message || 'Failed to create business interest';
      commit('setError', errorMessage);
      throw error;
    } finally {
      commit('setLoading', false);
    }
  },

  async updateBusiness({ commit, dispatch }, { id, data }) {
    commit('setLoading', true);
    commit('setError', null);

    try {
      const response = await businessInterestService.updateBusinessInterest(id, data);
      await dispatch('fetchBusinesses');
      // Refresh net worth to update wealth summary
      await dispatch('netWorth/refreshNetWorth', null, { root: true });
      return response;
    } catch (error) {
      const errorMessage = error.response?.data?.message || 'Failed to update business interest';
      commit('setError', errorMessage);
      throw error;
    } finally {
      commit('setLoading', false);
    }
  },

  async deleteBusiness({ commit, dispatch }, id) {
    commit('setLoading', true);
    commit('setError', null);

    try {
      const response = await businessInterestService.deleteBusinessInterest(id);
      await dispatch('fetchBusinesses');
      // Refresh net worth to update wealth summary
      await dispatch('netWorth/refreshNetWorth', null, { root: true });
      return response;
    } catch (error) {
      const errorMessage = error.response?.data?.message || 'Failed to delete business interest';
      commit('setError', errorMessage);
      throw error;
    } finally {
      commit('setLoading', false);
    }
  },

  async fetchTaxDeadlines({ commit }, businessId) {
    commit('setLoading', true);
    commit('setError', null);

    try {
      const response = await businessInterestService.getTaxDeadlines(businessId);
      // Extract deadlines array from nested response
      const deadlines = response.data?.deadlines || response.deadlines || [];
      commit('setTaxDeadlines', deadlines);
      return deadlines;
    } catch (error) {
      const errorMessage = error.response?.data?.message || 'Failed to fetch tax deadlines';
      commit('setError', errorMessage);
      throw error;
    } finally {
      commit('setLoading', false);
    }
  },

  async fetchExitCalculation({ commit }, businessId) {
    commit('setLoading', true);
    commit('setError', null);

    try {
      const response = await businessInterestService.getExitCalculation(businessId);
      // Extract exit_calculation object from nested response
      const exitCalc = response.data?.exit_calculation || response.exit_calculation || null;
      commit('setExitCalculation', exitCalc);
      return exitCalc;
    } catch (error) {
      const errorMessage = error.response?.data?.message || 'Failed to fetch exit calculation';
      commit('setError', errorMessage);
      throw error;
    } finally {
      commit('setLoading', false);
    }
  },

  clearSelectedBusiness({ commit }) {
    commit('setSelectedBusiness', null);
    commit('setTaxDeadlines', null);
    commit('setExitCalculation', null);
  },
};

const mutations = {
  setBusinesses(state, businesses) {
    state.businesses = businesses;
  },

  setSelectedBusiness(state, business) {
    state.selectedBusiness = business;
  },

  setTaxDeadlines(state, deadlines) {
    state.taxDeadlines = deadlines;
  },

  setExitCalculation(state, calculation) {
    state.exitCalculation = calculation;
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
