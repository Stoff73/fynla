import api from '@/services/api';
import logger from '@/utils/logger';
import { setActiveTaxYear } from '@/utils/dateFormatter';

/**
 * Tax Config store module.
 *
 * Holds the active UK tax year (e.g. "2026/27") loaded from the backend.
 * This is the single source of truth for which year the app is currently
 * calculating allowances against — it can diverge from the calendar year
 * when an admin has switched the active tax year via TaxSettings.
 *
 * Flow:
 *   1. User logs in → App.vue dispatches taxConfig/fetchActive
 *   2. Store sets activeTaxYear and mirrors it into dateFormatter's cache
 *   3. All components that call getCurrentTaxYear() now see the backend year
 *   4. Admin switches year in TaxSettings → dispatches taxConfig/fetchActive
 *      again → every bound component re-renders with the new year
 */

const state = {
  activeTaxYear: null,
  effectiveFrom: null,
  effectiveTo: null,
  loading: false,
  error: null,
};

const getters = {
  activeTaxYear: (state) => state.activeTaxYear,
  effectiveFrom: (state) => state.effectiveFrom,
  effectiveTo: (state) => state.effectiveTo,
};

const mutations = {
  setActiveTaxYear(state, { taxYear, effectiveFrom, effectiveTo }) {
    state.activeTaxYear = taxYear;
    state.effectiveFrom = effectiveFrom;
    state.effectiveTo = effectiveTo;
    setActiveTaxYear(taxYear);
  },
  clearActiveTaxYear(state) {
    state.activeTaxYear = null;
    state.effectiveFrom = null;
    state.effectiveTo = null;
    setActiveTaxYear(null);
  },
  setLoading(state, loading) {
    state.loading = loading;
  },
  setError(state, error) {
    state.error = error;
  },
};

const actions = {
  async fetchActive({ commit }) {
    commit('setLoading', true);
    commit('setError', null);
    try {
      const response = await api.get('/tax-year/current');
      const data = response.data?.data || {};
      commit('setActiveTaxYear', {
        taxYear: data.tax_year,
        effectiveFrom: data.effective_from,
        effectiveTo: data.effective_to,
      });
      return data.tax_year;
    } catch (error) {
      const message = error.response?.data?.message || error.message || 'Failed to load active tax year';
      commit('setError', message);
      logger.warn('taxConfig/fetchActive failed — falling back to calendar year', error);
      return null;
    } finally {
      commit('setLoading', false);
    }
  },

  clear({ commit }) {
    commit('clearActiveTaxYear');
  },
};

export default {
  namespaced: true,
  state,
  getters,
  mutations,
  actions,
};
