import api from '@/services/api';
import logger from '@/utils/logger';
import { setActiveTaxYear } from '@/utils/dateFormatter';

/**
 * Tax Config store module.
 *
 * Holds the active UK tax-year snapshot loaded from the backend
 * (`GET /api/tax/config`). This is the single source of truth the frontend
 * should consult — components must prefer these getters over the fallback
 * constants in `resources/js/constants/taxConfig.js`, which only exist for
 * resilience if the API call fails before the UI renders.
 *
 * Flow:
 *   1. User logs in (or app boots with a valid token) →
 *      App.vue / auth.js dispatches `taxConfig/fetchConfig`
 *   2. Store sets `config`, `activeTaxYear`, and mirrors the year into
 *      dateFormatter's cache via `setActiveTaxYear`
 *   3. Components read values via `mapGetters('taxConfig', ['isaAnnualAllowance', ...])`
 *   4. Admin switches year in TaxSettings → dispatches `taxConfig/fetchConfig`
 *      again → every bound component re-renders with the new year's values
 */

const state = {
  activeTaxYear: null,
  effectiveFrom: null,
  effectiveTo: null,
  config: null,
  loading: false,
  error: null,
};

const getters = {
  activeTaxYear: (state) => state.activeTaxYear,
  effectiveFrom: (state) => state.effectiveFrom,
  effectiveTo: (state) => state.effectiveTo,
  config: (state) => state.config,
  isLoaded: (state) => state.config !== null,

  // ISA
  isaAnnualAllowance: (state) => state.config?.isa?.annual_allowance ?? null,
  lifetimeIsaAllowance: (state) => state.config?.isa?.lifetime_isa_allowance ?? null,
  juniorIsaAllowance: (state) => state.config?.isa?.junior_isa_allowance ?? null,

  // Pension
  pensionAnnualAllowance: (state) => state.config?.pension?.annual_allowance ?? null,
  moneyPurchaseAnnualAllowance: (state) => state.config?.pension?.money_purchase_annual_allowance ?? null,
  pensionLifetimeAllowance: (state) => state.config?.pension?.lifetime_allowance ?? null,
  pensionTaperThresholdIncome: (state) => state.config?.pension?.taper_threshold_income ?? null,
  pensionTaperAdjustedIncome: (state) => state.config?.pension?.taper_adjusted_income ?? null,
  pensionTaxFreeCashRate: (state) => state.config?.pension?.tax_free_cash_rate ?? null,
  pensionTaxFreeLumpSumLimit: (state) => state.config?.pension?.tax_free_lump_sum_limit ?? null,
  statePensionAnnual: (state) => state.config?.pension?.state_pension_annual ?? null,
  statePensionWeekly: (state) => state.config?.pension?.state_pension_weekly ?? null,

  // Income tax
  personalAllowance: (state) => state.config?.income_tax?.personal_allowance ?? null,
  personalAllowanceTaperThreshold: (state) => state.config?.income_tax?.personal_allowance_taper_threshold ?? null,
  higherRateThreshold: (state) => state.config?.income_tax?.higher_rate_threshold ?? null,
  additionalRateThreshold: (state) => state.config?.income_tax?.additional_rate_threshold ?? null,
  incomeTaxBasicRate: (state) => state.config?.income_tax?.basic_rate ?? null,
  incomeTaxHigherRate: (state) => state.config?.income_tax?.higher_rate ?? null,
  incomeTaxAdditionalRate: (state) => state.config?.income_tax?.additional_rate ?? null,
  savingsAllowanceBasic: (state) => state.config?.income_tax?.savings_allowance_basic ?? null,
  savingsAllowanceHigher: (state) => state.config?.income_tax?.savings_allowance_higher ?? null,
  marriageAllowance: (state) => state.config?.income_tax?.marriage_allowance ?? null,

  // National Insurance
  niPrimaryThreshold: (state) => state.config?.national_insurance?.primary_threshold ?? null,
  niUpperEarningsLimit: (state) => state.config?.national_insurance?.upper_earnings_limit ?? null,
  niBasicRate: (state) => state.config?.national_insurance?.basic_rate ?? null,
  niAdditionalRate: (state) => state.config?.national_insurance?.additional_rate ?? null,

  // CGT
  cgtAnnualAllowance: (state) => state.config?.capital_gains_tax?.annual_allowance ?? null,
  cgtBasicRate: (state) => state.config?.capital_gains_tax?.basic_rate ?? null,
  cgtHigherRate: (state) => state.config?.capital_gains_tax?.higher_rate ?? null,
  badrRate: (state) => state.config?.capital_gains_tax?.badr_rate ?? null,
  badrLifetimeLimit: (state) => state.config?.capital_gains_tax?.badr_lifetime_limit ?? null,

  // IHT
  ihtNilRateBand: (state) => state.config?.inheritance_tax?.nil_rate_band ?? null,
  ihtResidenceNilRateBand: (state) => state.config?.inheritance_tax?.residence_nil_rate_band ?? null,
  ihtRnrbTaperThreshold: (state) => state.config?.inheritance_tax?.rnrb_taper_threshold ?? null,
  ihtStandardRate: (state) => state.config?.inheritance_tax?.standard_rate ?? null,
  ihtReducedRate: (state) => state.config?.inheritance_tax?.reduced_rate ?? null,

  // Dividend tax
  dividendAllowance: (state) => state.config?.dividend_tax?.allowance ?? null,
  dividendBasicRate: (state) => state.config?.dividend_tax?.basic_rate ?? null,
  dividendHigherRate: (state) => state.config?.dividend_tax?.higher_rate ?? null,
  dividendAdditionalRate: (state) => state.config?.dividend_tax?.additional_rate ?? null,

  // Gifting
  annualGiftExemption: (state) => state.config?.gifting_exemptions?.annual_exemption ?? null,
  smallGiftExemption: (state) => state.config?.gifting_exemptions?.small_gift_exemption ?? null,

  // Stamp Duty Land Tax — England
  sdltStandardBands: (state) => state.config?.stamp_duty?.england?.standard_bands ?? null,
  sdltFtbBands: (state) => state.config?.stamp_duty?.england?.ftb_bands ?? null,
  sdltFtbMaxPrice: (state) => state.config?.stamp_duty?.england?.ftb_max_price ?? null,
  sdltAdditionalSurcharge: (state) => state.config?.stamp_duty?.england?.additional_surcharge ?? null,
  sdltNonUkSurcharge: (state) => state.config?.stamp_duty?.england?.non_uk_surcharge ?? null,

  // Land and Buildings Transaction Tax — Scotland
  lbttBands: (state) => state.config?.stamp_duty?.scotland?.lbtt_bands ?? null,
  lbttAdditionalSurcharge: (state) => state.config?.stamp_duty?.scotland?.lbtt_additional_surcharge ?? null,
  lbttNonUkSurcharge: (state) => state.config?.stamp_duty?.scotland?.lbtt_non_uk_surcharge ?? null,

  // Land Transaction Tax — Wales
  lttBands: (state) => state.config?.stamp_duty?.wales?.ltt_bands ?? null,
  lttAdditionalSurcharge: (state) => state.config?.stamp_duty?.wales?.ltt_additional_surcharge ?? null,
  lttNonUkSurcharge: (state) => state.config?.stamp_duty?.wales?.ltt_non_uk_surcharge ?? null,

  // Student Loan
  studentLoanRepaymentRate: (state) => state.config?.student_loan?.repayment_rate ?? null,

  // Other
  hicbcThreshold: (state) => state.config?.other?.hicbc_threshold ?? null,
  sspWeeklyRate: (state) => state.config?.other?.ssp_weekly_rate ?? null,
};

const mutations = {
  setConfig(state, config) {
    state.config = config;
    state.activeTaxYear = config?.tax_year ?? null;
    state.effectiveFrom = config?.effective_from ?? null;
    state.effectiveTo = config?.effective_to ?? null;
    setActiveTaxYear(state.activeTaxYear);
  },
  clearConfig(state) {
    state.config = null;
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
  async fetchConfig({ commit }) {
    commit('setLoading', true);
    commit('setError', null);
    try {
      const response = await api.get('/tax/config');
      const data = response.data?.data ?? null;
      commit('setConfig', data);
      return data;
    } catch (error) {
      const message = error.response?.data?.message || error.message || 'Failed to load tax configuration';
      commit('setError', message);
      logger.warn('taxConfig/fetchConfig failed — falling back to hardcoded constants', error);
      return null;
    } finally {
      commit('setLoading', false);
    }
  },

  // Back-compat alias — three callers historically dispatched `fetchActive`.
  // Delegates to fetchConfig so all bound components hydrate fully.
  async fetchActive({ dispatch }) {
    return dispatch('fetchConfig');
  },

  // Public counterpart of fetchConfig — used by PublicLayout on mount so that
  // pages rendered for unauthenticated visitors (CalculatorsPage etc.) get
  // the same hydrated snapshot. Same payload shape, different URL, no auth.
  async fetchPublicConfig({ commit }) {
    commit('setLoading', true);
    commit('setError', null);
    try {
      const response = await api.get('/public/tax-config');
      const data = response.data?.data ?? null;
      commit('setConfig', data);
      return data;
    } catch (error) {
      const message = error.response?.data?.message || error.message || 'Failed to load tax configuration';
      commit('setError', message);
      logger.warn('taxConfig/fetchPublicConfig failed — falling back to hardcoded constants', error);
      return null;
    } finally {
      commit('setLoading', false);
    }
  },

  clear({ commit }) {
    commit('clearConfig');
  },
};

export default {
  namespaced: true,
  state,
  getters,
  mutations,
  actions,
};
