<template>
  <MobileChrome title="Retirement" subtitle="Your projected retirement income, pensions and projections" :loading="loading" loading-label="this pension" back @back="goBack">
    <div class="m-card m-detail-header">
      <h1 class="m-h1">{{ title }}</h1>
      <p class="m-sub">{{ typeLabel }}</p>
    </div>

    <div v-if="loading" class="m-card m-state">
      <p class="m-sub">Loading pension details…</p>
    </div>

    <div v-else-if="error" class="m-card m-state">
      <p class="m-err">{{ error }}</p>
      <button class="m-btn" @click="load">Try again</button>
    </div>

    <template v-else-if="pension">
      <!-- Headline value hero -->
      <div class="m-card m-hero">
        <p class="m-sub m-label">{{ heroLabel }}</p>
        <p class="m-metric">{{ fmt(heroValue) }}<span v-if="heroPer" class="rpd-hero-per">{{ heroPer }}</span></p>
        <p v-if="heroSub" class="m-hero-sub">{{ heroSub }}</p>
      </div>

      <!-- DC pension details -->
      <div v-if="type === 'dc'" class="m-card m-detail-rows">
        <p class="m-section-label" style="margin-top:0">Scheme information</p>
        <div class="m-detail-row">
          <span class="m-detail-key">Scheme name</span>
          <span class="m-detail-value">{{ pension.scheme_name || '—' }}</span>
        </div>
        <div class="m-detail-row">
          <span class="m-detail-key">Pension type</span>
          <span class="m-detail-value">{{ schemeTypeLabel(pension.pension_type || pension.scheme_type) }}</span>
        </div>
        <div class="m-detail-row">
          <span class="m-detail-key">Provider</span>
          <span class="m-detail-value">{{ pension.provider || '—' }}</span>
        </div>
        <div class="m-detail-row">
          <span class="m-detail-key">Current fund value</span>
          <span class="m-detail-value">{{ fmt(pension.current_fund_value) }}</span>
        </div>
        <div class="m-detail-row">
          <span class="m-detail-key">Monthly contribution</span>
          <span class="m-detail-value">{{ fmt(monthlyContributionDc) }}</span>
        </div>
        <div class="m-detail-row">
          <span class="m-detail-key">Retirement age</span>
          <span class="m-detail-value">{{ pension.retirement_age || '—' }}</span>
        </div>
      </div>

      <!-- DB pension details -->
      <div v-if="type === 'db'" class="m-card m-detail-rows">
        <p class="m-section-label" style="margin-top:0">Benefit details</p>
        <div class="m-detail-row">
          <span class="m-detail-key">Scheme name</span>
          <span class="m-detail-value">{{ pension.scheme_name || '—' }}</span>
        </div>
        <div class="m-detail-row">
          <span class="m-detail-key">Scheme type</span>
          <span class="m-detail-value">{{ dbSchemeTypeLabel(pension.scheme_type) }}</span>
        </div>
        <div class="m-detail-row">
          <span class="m-detail-key">Accrued annual pension</span>
          <span class="m-detail-value">{{ fmt(pension.accrued_annual_pension) }}</span>
        </div>
        <div class="m-detail-row">
          <span class="m-detail-key">Normal retirement age</span>
          <span class="m-detail-value">{{ pension.normal_retirement_age || '—' }}</span>
        </div>
        <div class="m-detail-row">
          <span class="m-detail-key">Lump sum entitlement</span>
          <span class="m-detail-value">{{ fmt(pension.lump_sum_entitlement || 0) }}</span>
        </div>
        <div class="m-detail-row">
          <span class="m-detail-key">Spouse pension</span>
          <span class="m-detail-value">{{ (pension.spouse_pension_percent || 0) }}%</span>
        </div>
      </div>

      <!-- State pension details -->
      <div v-if="type === 'state'" class="m-card m-detail-rows">
        <p class="m-section-label" style="margin-top:0">Entitlement</p>
        <div class="m-detail-row">
          <span class="m-detail-key">Forecast weekly amount</span>
          <span class="m-detail-value">{{ weeklyLabel }}</span>
        </div>
        <div class="m-detail-row">
          <span class="m-detail-key">Annual forecast</span>
          <span class="m-detail-value">{{ fmt(annualForecast) }}</span>
        </div>
        <div class="m-detail-row">
          <span class="m-detail-key">Qualifying years</span>
          <span class="m-detail-value">{{ (pension.ni_years_completed || 0) }} of {{ pension.ni_years_required || 35 }}</span>
        </div>
        <div class="m-detail-row">
          <span class="m-detail-key">State Pension age</span>
          <span class="m-detail-value">{{ pension.state_pension_age || '—' }}</span>
        </div>
      </div>

      <!-- DC projection -->
      <div v-if="type === 'dc'" class="m-card m-detail-rows">
        <p class="m-section-label" style="margin-top:0">Pension pot projection</p>
        <div v-if="projLoading" class="m-state">
          <p class="m-sub" style="margin-bottom:0">Loading projection…</p>
        </div>
        <template v-else-if="projection">
          <div class="m-detail-row">
            <span class="m-detail-key">Current value</span>
            <span class="m-detail-value">{{ fmt(projection.current_value) }}</span>
          </div>
          <div class="m-detail-row">
            <span class="m-detail-key">Projected at retirement</span>
            <span class="m-detail-value">{{ fmt(projection.percentile_20_at_retirement) }}</span>
          </div>
          <div class="m-detail-row">
            <span class="m-detail-key">Median projection</span>
            <span class="m-detail-value">{{ fmt(projection.median_at_retirement) }}</span>
          </div>
          <p class="rpd-proj-note">
            Projected to age {{ projection.retirement_age }} over {{ projection.years_to_retirement }} years
            at an estimated {{ projection.expected_return }}% annual return. The projected figure is a
            conservative estimate (80% likelihood of exceeding it).
          </p>
        </template>
        <p v-else class="m-sub" style="margin-bottom:0">No projection available for this pension.</p>
      </div>
    </template>
  </MobileChrome>
</template>

<script>
import { store } from '../../store.js';
import { apiGet } from '../../api.js';
import MobileChrome from '../../components/MobileChrome.vue';

function formatCurrency(value) {
  if (value == null || value === '' || isNaN(Number(value))) return '—';
  return new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP', maximumFractionDigits: 0 }).format(Number(value));
}

const TYPE_LABELS = {
  dc: 'Defined Contribution Pension',
  db: 'Defined Benefit Pension',
  state: 'State Pension',
};

const SCHEME_TYPES = {
  occupational: 'Occupational (Workplace) Pension',
  workplace: 'Workplace Pension',
  sipp: 'Self-Invested Personal Pension',
  personal: 'Personal Pension',
  stakeholder: 'Stakeholder Pension',
};

const DB_SCHEME_TYPES = {
  final_salary: 'Final Salary',
  career_average: 'Career Average',
  public_sector: 'Public Sector',
};

export default {
  name: 'MobileRetirementPensionDetail',
  components: { MobileChrome },
  data: () => ({
    loading: true,
    error: '',
    pension: null,
    projection: null,
    projLoading: false,
  }),
  computed: {
    type() { return this.$route.params.type; },
    id() { return this.$route.params.id; },
    typeLabel() { return TYPE_LABELS[this.type] || 'Pension'; },
    title() {
      if (this.type === 'state') return 'State Pension';
      return this.pension?.scheme_name || this.pension?.provider || 'Pension';
    },
    annualForecast() { return Number(this.pension?.state_pension_forecast_annual || 0); },
    weeklyAmount() { return this.annualForecast / 52; },
    weeklyLabel() {
      if (!this.pension) return '—';
      return new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP', minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(this.weeklyAmount) + ' a week';
    },
    monthlyContributionDc() {
      // Mirrors RetirementProjectionService: salary-percentage schemes derive
      // the monthly figure from contribution percentages, otherwise use the
      // flat monthly amount.
      const p = this.pension;
      if (!p) return 0;
      if (Number(p.employee_contribution_percent) > 0 && Number(p.annual_salary) > 0) {
        const pct = Number(p.employee_contribution_percent || 0) + Number(p.employer_contribution_percent || 0);
        return (pct * Number(p.annual_salary)) / 100 / 12;
      }
      return Number(p.monthly_contribution_amount || 0);
    },
    heroLabel() {
      if (this.type === 'db') return 'Accrued annual pension';
      if (this.type === 'state') return 'Annual forecast';
      return 'Current fund value';
    },
    heroValue() {
      if (this.type === 'db') return this.pension?.accrued_annual_pension || 0;
      if (this.type === 'state') return this.annualForecast;
      return this.pension?.current_fund_value || 0;
    },
    heroPer() {
      return (this.type === 'db' || this.type === 'state') ? 'a year' : '';
    },
    heroSub() {
      if (this.type === 'state') return this.weeklyLabel;
      if (this.type === 'dc' && this.pension?.provider) return this.pension.provider;
      return '';
    },
  },
  async created() { await this.load(); },
  methods: {
    fmt(v) { return formatCurrency(v); },
    schemeTypeLabel(t) { return SCHEME_TYPES[t] || t || '—'; },
    dbSchemeTypeLabel(t) { return DB_SCHEME_TYPES[t] || t || '—'; },
    goBack() { this.$router.push({ name: 'm-retirement' }); },
    async load() {
      this.loading = true;
      this.error = '';
      this.pension = null;
      this.projection = null;
      try {
        const { ok, data } = await apiGet('/api/retirement', store.token);
        if (!ok) {
          this.error = data?.message || 'We could not load this pension.';
          return;
        }
        const payload = data?.data || data || {};
        const numId = String(this.id);
        if (this.type === 'dc') {
          this.pension = (payload.dc_pensions || []).find((p) => String(p.id) === numId) || null;
        } else if (this.type === 'db') {
          this.pension = (payload.db_pensions || []).find((p) => String(p.id) === numId) || null;
        } else if (this.type === 'state') {
          this.pension = payload.state_pension || null;
        }
        if (!this.pension) {
          this.error = 'Pension not found.';
          return;
        }
        if (this.type === 'dc') await this.loadProjection();
      } catch (e) {
        this.error = 'Network error. Please try again.';
      } finally {
        this.loading = false;
      }
    },
    async loadProjection() {
      this.projLoading = true;
      try {
        const { ok, data } = await apiGet(`/api/retirement/dc-pensions/${this.pension.id}/projections`, store.token);
        if (ok) this.projection = data?.data || data || null;
      } catch (e) {
        // Leave projection null — the template shows a graceful fallback.
      } finally {
        this.projLoading = false;
      }
    },
  },
};
</script>

<style scoped>
.rpd-hero-per { font-size: 14px; font-weight: 600; color: var(--horizon-300); margin-left: 6px; }
.rpd-proj-note { font-size: 12px; color: var(--neutral-500); line-height: 1.5; margin-top: 12px; }
</style>
