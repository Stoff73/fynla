<template>
  <MobileChrome title="Retirement" subtitle="Your projected retirement income, pensions and projections" :loading="loading" loading-label="your retirement">
    <div v-if="loading" class="m-card m-state">
      <p class="m-sub">Loading your retirement position…</p>
    </div>

    <div v-else-if="error" class="m-card m-state">
      <p class="m-err">{{ error }}</p>
      <button class="m-btn" @click="load">Try again</button>
    </div>

    <template v-else>
      <!-- Projected income vs target hero -->
      <div class="m-card m-hero">
        <p class="m-sub m-label">Projected retirement income</p>
        <p class="m-metric">{{ fmt(projectedIncome) }}<span class="mr-hero-per">a year</span></p>
        <p class="m-hero-sub">{{ gapNarrative }}</p>
        <div class="mr-hero-split">
          <div class="mr-hero-stat">
            <span class="mr-hero-stat__cap">Target income</span>
            <span class="mr-hero-stat__val">{{ fmt(targetIncome) }}</span>
          </div>
          <div class="mr-hero-stat">
            <span class="mr-hero-stat__cap">{{ isSurplus ? 'Surplus' : 'Shortfall' }}</span>
            <span class="mr-hero-stat__val" :class="isSurplus ? 'mr-pos' : 'mr-neg'">{{ fmt(Math.abs(incomeGap)) }}</span>
          </div>
        </div>
      </div>

      <!-- Headline figures -->
      <div class="m-card m-detail-rows">
        <p class="m-section-label" style="margin-top:0">Overview</p>
        <div class="m-detail-row">
          <span class="m-detail-key">Total pension wealth</span>
          <span class="m-detail-value">{{ fmt(totalPensionWealth) }}</span>
        </div>
        <div class="m-detail-row">
          <span class="m-detail-key">Years to retirement</span>
          <span class="m-detail-value">{{ yearsToRetirement != null ? yearsToRetirement : '—' }}</span>
        </div>
        <div class="m-detail-row">
          <span class="m-detail-key">Target retirement age</span>
          <span class="m-detail-value">{{ targetRetirementAge || '—' }}</span>
        </div>
      </div>

      <!-- Pensions list -->
      <div class="m-card">
        <p class="m-section-label" style="margin-top:0">Your pensions</p>
        <p v-if="!pensions.length" class="m-sub" style="margin-bottom:0">
          No pensions recorded yet. Add a pension to see your full retirement picture.
        </p>
        <div v-else>
          <button
            v-for="p in pensions"
            :key="p.routeKey"
            type="button"
            class="mr-pension"
            @click="openPension(p)"
          >
            <span class="mr-pension__left">
              <span class="mr-pension__name">{{ p.name }}</span>
              <span class="mr-pension__type">{{ p.typeLabel }}</span>
            </span>
            <span class="mr-pension__right">
              <span class="mr-pension__value">{{ p.valueLabel }}</span>
              <span class="mr-pension__view">View</span>
            </span>
          </button>
        </div>
      </div>

      <!-- Projections section -->
      <div class="m-card m-detail-rows">
        <p class="m-section-label" style="margin-top:0">Pension pot projection</p>
        <p v-if="projError" class="m-sub" style="margin-bottom:0">{{ projError }}</p>
        <template v-else-if="pot">
          <div class="m-detail-row">
            <span class="m-detail-key">Current pot value</span>
            <span class="m-detail-value">{{ fmt(pot.current_value) }}</span>
          </div>
          <div class="m-detail-row">
            <span class="m-detail-key">Monthly contributions</span>
            <span class="m-detail-value">{{ fmt(pot.monthly_contribution) }}</span>
          </div>
          <div class="m-detail-row">
            <span class="m-detail-key">Projected at retirement</span>
            <span class="m-detail-value">{{ fmt(pot.percentile_20_at_retirement) }}</span>
          </div>
          <div class="m-detail-row">
            <span class="m-detail-key">Median projection</span>
            <span class="m-detail-value">{{ fmt(pot.median_at_retirement) }}</span>
          </div>
          <p class="mr-proj-note">
            Projected value at age {{ pot.retirement_age }} based on {{ pot.years_to_retirement }} years to
            retirement and an estimated {{ pot.expected_return }}% annual return. The projected figure is a
            conservative estimate (80% likelihood of exceeding it).
          </p>
        </template>
        <p v-else class="m-sub" style="margin-bottom:0">No projection available yet.</p>
      </div>

      <!-- Recommendations -->
      <div v-if="recommendations.length" class="m-card">
        <p class="m-section-label" style="margin-top:0">Recommended actions</p>
        <article v-for="(rec, i) in recommendations" :key="rec.type || rec.title || i" class="mr-rec">
          <h3 class="mr-rec__title">{{ rec.title || rec.action || 'Recommendation' }}</h3>
          <p v-if="rec.description" class="mr-rec__desc">{{ rec.description }}</p>
        </article>
      </div>
    </template>
  </MobileChrome>
</template>

<script>
import { store } from '../../store.js';
import { apiGet, apiPost } from '../../api.js';
import MobileChrome from '../../components/MobileChrome.vue';

function formatCurrency(value) {
  if (value == null || value === '' || isNaN(Number(value))) return '—';
  return new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP', maximumFractionDigits: 0 }).format(Number(value));
}

const TYPE_LABELS = {
  dc: 'Defined Contribution',
  db: 'Defined Benefit',
  state: 'State Pension',
};

export default {
  name: 'MobileRetirement',
  components: { MobileChrome },
  data: () => ({
    loading: true,
    error: '',
    data: null,
    analysis: null,
    pot: null,
    projError: '',
  }),
  computed: {
    profile() { return this.data?.profile || null; },
    dcPensions() { return this.data?.dc_pensions || []; },
    dbPensions() { return this.data?.db_pensions || []; },
    statePension() { return this.data?.state_pension || null; },
    projectedIncome() { return Number(this.analysis?.projected_income || 0); },
    targetIncome() { return Number(this.analysis?.target_income || 0); },
    incomeGap() {
      // analyze returns income_gap as the shortfall (target - projected). A negative
      // gap (or where projected exceeds target) is a surplus.
      const gap = Number(this.analysis?.income_gap || 0);
      return this.targetIncome - this.projectedIncome || gap;
    },
    isSurplus() { return this.incomeGap <= 0; },
    totalPensionWealth() { return Number(this.analysis?.total_pension_wealth || 0); },
    yearsToRetirement() {
      const y = this.analysis?.years_to_retirement;
      return y != null ? y : (this.profile?.years_to_retirement ?? null);
    },
    targetRetirementAge() { return this.profile?.target_retirement_age || null; },
    recommendations() { return this.analysis?.recommendations || []; },
    gapNarrative() {
      if (!this.targetIncome) return 'Add a target retirement income to see how your projection compares.';
      if (this.isSurplus) {
        return `You are on track to exceed your target by ${this.fmt(Math.abs(this.incomeGap))} a year.`;
      }
      return `You have a shortfall of ${this.fmt(this.incomeGap)} a year against your target.`;
    },
    pensions() {
      const list = [];
      for (const p of this.dcPensions) {
        list.push({
          routeKey: `dc-${p.id}`,
          type: 'dc',
          id: p.id,
          typeLabel: TYPE_LABELS.dc,
          name: p.scheme_name || p.provider || 'Defined Contribution Pension',
          valueLabel: this.fmt(p.current_fund_value),
        });
      }
      for (const p of this.dbPensions) {
        list.push({
          routeKey: `db-${p.id}`,
          type: 'db',
          id: p.id,
          typeLabel: TYPE_LABELS.db,
          name: p.scheme_name || 'Defined Benefit Pension',
          valueLabel: `${this.fmt(p.accrued_annual_pension)} a year`,
        });
      }
      if (this.statePension) {
        const annual = Number(this.statePension.state_pension_forecast_annual || 0);
        list.push({
          routeKey: `state-${this.statePension.id || 'self'}`,
          type: 'state',
          id: this.statePension.id || 'self',
          typeLabel: TYPE_LABELS.state,
          name: 'State Pension',
          valueLabel: `${this.fmt(annual)} a year`,
        });
      }
      return list;
    },
  },
  async created() { await this.load(); },
  methods: {
    fmt(v) { return formatCurrency(v); },
    goBack() { this.$router.push({ name: 'dashboard' }); },
    openPension(p) {
      this.$router.push({ name: 'm-retirement-pension', params: { type: p.type, id: String(p.id) } });
    },
    async load() {
      this.loading = true;
      this.error = '';
      this.data = null;
      this.analysis = null;
      this.pot = null;
      this.projError = '';
      try {
        const [indexRes, analyzeRes] = await Promise.all([
          apiGet('/api/retirement', store.token),
          apiPost('/api/retirement/analyze', {}, store.token),
        ]);
        if (indexRes.ok) {
          this.data = indexRes.data?.data || indexRes.data || {};
        } else {
          this.error = indexRes.data?.message || 'We could not load your retirement data.';
          return;
        }
        if (analyzeRes.ok) {
          this.analysis = analyzeRes.data?.data || analyzeRes.data || {};
        }
        await this.loadProjections();
      } catch (e) {
        this.error = 'Network error. Please try again.';
      } finally {
        this.loading = false;
      }
    },
    async loadProjections() {
      try {
        const { ok, data } = await apiGet('/api/retirement/projections', store.token);
        if (ok) {
          const payload = data?.data || data || {};
          this.pot = payload.pension_pot_projection || null;
        } else {
          this.projError = 'Projections are not available right now.';
        }
      } catch (e) {
        this.projError = 'Projections are not available right now.';
      }
    },
  },
};
</script>

<style scoped>
.mr-hero-per { font-size: 14px; font-weight: 600; color: var(--horizon-300); margin-left: 6px; }
.mr-hero-split { display: flex; gap: 16px; margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--horizon-400); }
.mr-hero-stat { flex: 1; }
.mr-hero-stat__cap { display: block; font-size: 12px; color: var(--horizon-300); margin-bottom: 2px; }
.mr-hero-stat__val { display: block; font-size: 18px; font-weight: 900; color: var(--white); }
.mr-pos { color: var(--spring-400); }
.mr-neg { color: var(--raspberry-300); }

.mr-pension { display: flex; align-items: center; justify-content: space-between; gap: 12px; width: 100%; text-align: left; background: transparent; border: 0; border-bottom: 1px solid var(--horizon-100); padding: 14px 0; cursor: pointer; }
.mr-pension:last-child { border-bottom: 0; padding-bottom: 0; }
.mr-pension:first-of-type { padding-top: 4px; }
.mr-pension:active { opacity: 0.7; }
.mr-pension__left { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.mr-pension__name { font-size: 15px; font-weight: 700; color: var(--horizon-500); }
.mr-pension__type { font-size: 12px; color: var(--neutral-500); }
.mr-pension__right { display: flex; flex-direction: column; align-items: flex-end; gap: 2px; flex-shrink: 0; }
.mr-pension__value { font-size: 14px; font-weight: 700; color: var(--horizon-500); white-space: nowrap; }
.mr-pension__view { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--raspberry-500); }

.mr-proj-note { font-size: 12px; color: var(--neutral-500); line-height: 1.5; margin-top: 12px; }

.mr-rec { border: 1px solid var(--light-gray); border-radius: var(--radius-lg); padding: 14px; margin-bottom: 10px; }
.mr-rec:last-child { margin-bottom: 0; }
.mr-rec__title { font-size: 14px; font-weight: 700; color: var(--horizon-500); line-height: 1.3; }
.mr-rec__desc { font-size: 13px; color: var(--neutral-600); line-height: 1.5; margin-top: 4px; }
</style>
