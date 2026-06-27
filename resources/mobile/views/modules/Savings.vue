<template>
  <MobileChrome title="Savings and emergency fund" subtitle="Your cash, emergency-fund runway and ISA allowance" :loading="loading" loading-label="your savings">
    <div v-if="loading" class="m-card m-state">
      <p class="m-sub">Loading your savings…</p>
    </div>

    <div v-else-if="error" class="m-card m-state">
      <p class="m-err">{{ error }}</p>
      <button class="m-btn" @click="load">Try again</button>
    </div>

    <template v-else>
      <!-- Total cash hero -->
      <div class="m-card m-hero">
        <p class="m-sub m-label">Total cash savings</p>
        <p class="m-metric">{{ fmt(totalCash) }}</p>
        <p class="m-hero-sub">{{ accountCountLabel }}</p>
      </div>

      <!-- Emergency fund runway -->
      <div class="m-card">
        <p class="m-section-label" style="margin-top:0">Emergency fund</p>
        <div class="ms-ef">
          <div class="ms-ef__row">
            <span class="ms-ef__label">Cash held</span>
            <span class="ms-ef__value">{{ fmt(totalCash) }}</span>
          </div>
          <div class="ms-ef__row">
            <span class="ms-ef__label">Target ({{ targetMonths }} {{ targetMonths === 1 ? 'month' : 'months' }})</span>
            <span class="ms-ef__value">{{ fmt(emergencyTarget) }}</span>
          </div>
          <div class="mts-bar" style="margin:8px 0">
            <div class="mts-bar__fill" :class="`mts-bar__fill--${runwayStatus}`" :style="{ width: runwayBarWidth }"></div>
          </div>
          <div class="ms-ef__row">
            <span class="ms-ef__runway" :class="`ms-ef__runway--${runwayStatus}`">{{ runwayLabel }}</span>
            <span class="ms-ef__label">{{ runwayCovered }}</span>
          </div>
        </div>
        <p v-if="emergencyRationale" class="m-sub" style="margin:10px 0 0">{{ emergencyRationale }}</p>
      </div>

      <!-- ISA allowance -->
      <div v-if="isaAllowance" class="m-card">
        <p class="m-section-label" style="margin-top:0">ISA allowance this year</p>
        <div class="mts-allow" style="border-bottom:0;padding-bottom:0">
          <div class="mts-allow__head">
            <span class="mts-allow__label">ISA allowance used</span>
            <span class="mts-allow__cap">of {{ fmt(isaTotal) }}</span>
          </div>
          <div class="mts-bar">
            <div class="mts-bar__fill" :class="`mts-bar__fill--${isaStatus}`" :style="{ width: isaBarWidth }"></div>
          </div>
          <div class="mts-allow__foot">
            <span class="mts-allow__remain" :class="`mts-allow__remain--${isaStatus}`">{{ isaRemainingLabel }}</span>
            <span class="mts-allow__used">{{ fmt(isaUsed) }} used</span>
          </div>
        </div>
      </div>

      <!-- Accounts list -->
      <div class="m-card">
        <div class="m-cap-head" style="margin-top:0">
          <p class="m-section-label">Savings accounts</p>
          <div v-if="accountLimit" class="m-cap">
            <span class="m-cap__count" :class="{ 'm-cap__count--full': atCap }">{{ accountCount }} of {{ accountLimit }} accounts used</span>
            <button type="button" class="m-cap__upgrade" @click="goUpgrade">Upgrade</button>
          </div>
        </div>
        <p v-if="!accounts.length" class="m-sub" style="margin-bottom:0">
          You haven't added any savings accounts yet.
        </p>
        <div v-else>
          <button
            v-for="acct in accounts"
            :key="acct.id"
            type="button"
            class="ms-acct"
            @click="openAccount(acct.id)"
          >
            <div class="ms-acct__main">
              <span class="ms-acct__provider">{{ acct.provider || acct.institution || 'Savings account' }}</span>
              <span class="ms-acct__meta">
                <span v-if="acct.is_isa" class="ms-acct__tag">ISA</span>
                <span v-if="acct.is_emergency_fund" class="ms-acct__tag ms-acct__tag--ef">Emergency fund</span>
                <span class="ms-acct__rate">{{ rate(acct.interest_rate) }}</span>
              </span>
            </div>
            <div class="ms-acct__right">
              <span class="ms-acct__balance">{{ fmt(balanceOf(acct)) }}</span>
              <span class="ms-acct__view">View</span>
            </div>
          </button>
        </div>
      </div>
    </template>
  </MobileChrome>
</template>

<script>
import { store } from '../../store.js';
import { apiGet } from '../../api.js';
import MobileChrome from '../../components/MobileChrome.vue';
import { upgradeMixin } from '../../mixins/upgrade.js';

function formatCurrency(value) {
  if (value == null || value === '' || isNaN(Number(value))) return '—';
  return new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP', maximumFractionDigits: 0 }).format(Number(value));
}

export default {
  name: 'MobileSavings',
  components: { MobileChrome },
  mixins: [upgradeMixin],
  data: () => ({ loading: true, error: '', payload: null }),
  computed: {
    accounts() { return this.payload?.accounts || []; },
    // Free-tier cap nudge (5.1). account_limit null = unlimited tier → hide nudge.
    accountCount() { return this.payload?.account_count ?? this.accounts.length; },
    accountLimit() { return this.payload?.account_limit ?? null; },
    atCap() { return this.accountLimit != null && this.accountCount >= this.accountLimit; },
    isaAllowance() { return this.payload?.isa_allowance || null; },
    emergencyTargetData() { return this.payload?.emergency_fund_target || null; },
    expenditure() { return this.payload?.expenditure_profile || null; },

    totalCash() {
      return this.accounts.reduce((sum, a) => sum + (Number(this.balanceOf(a)) || 0), 0);
    },
    accountCountLabel() {
      const n = this.accounts.length;
      if (n === 0) return 'No accounts added yet.';
      return `Across ${n} ${n === 1 ? 'account' : 'accounts'}.`;
    },

    monthlyExpenditure() {
      return Number(this.expenditure?.total_monthly_expenditure || 0);
    },
    targetMonths() {
      return Number(this.emergencyTargetData?.target_months || 6);
    },
    emergencyTarget() {
      return Number(this.emergencyTargetData?.target_amount || 0);
    },
    emergencyRationale() {
      return this.emergencyTargetData?.rationale || '';
    },
    runwayMonths() {
      if (this.monthlyExpenditure <= 0) return null;
      return this.totalCash / this.monthlyExpenditure;
    },
    runwayBarWidth() {
      if (this.emergencyTarget <= 0) return this.totalCash > 0 ? '100%' : '0%';
      return `${Math.min((this.totalCash / this.emergencyTarget) * 100, 100)}%`;
    },
    runwayStatus() {
      if (this.emergencyTarget <= 0) return 'spring';
      const pct = (this.totalCash / this.emergencyTarget) * 100;
      if (pct >= 100) return 'spring';
      if (pct >= 50) return 'violet';
      return 'raspberry';
    },
    runwayLabel() {
      if (this.runwayMonths == null) return 'Runway unavailable';
      const m = this.runwayMonths;
      const rounded = m >= 10 ? Math.round(m) : Math.round(m * 10) / 10;
      return `${rounded} ${rounded === 1 ? 'month' : 'months'} of cover`;
    },
    runwayCovered() {
      if (this.emergencyTarget <= 0) return '';
      const pct = Math.round((this.totalCash / this.emergencyTarget) * 100);
      return `${Math.min(pct, 100)}% of target`;
    },

    isaTotal() { return Number(this.isaAllowance?.total_allowance || 0); },
    isaUsed() { return Number(this.isaAllowance?.total_used || 0); },
    isaRemaining() { return Number(this.isaAllowance?.remaining || 0); },
    isaPct() {
      if (this.isaAllowance?.percentage_used != null) return Number(this.isaAllowance.percentage_used);
      if (this.isaTotal <= 0) return 0;
      return (this.isaUsed / this.isaTotal) * 100;
    },
    isaBarWidth() { return `${Math.min(this.isaPct, 100)}%`; },
    isaStatus() {
      if (this.isaPct >= 100) return 'spring';
      if (this.isaPct >= 80) return 'violet';
      return 'spring';
    },
    isaRemainingLabel() {
      if (this.isaPct >= 100 || this.isaRemaining <= 0) return 'Fully used';
      return `${this.fmt(this.isaRemaining)} remaining`;
    },
  },
  async created() { await this.load(); },
  methods: {
    fmt(v) { return formatCurrency(v); },
    rate(r) {
      if (r == null || isNaN(Number(r))) return '—';
      return `${Number(r).toFixed(2)}%`;
    },
    balanceOf(a) {
      return a.full_balance ?? a.current_balance ?? 0;
    },
    openAccount(id) { this.$router.push(`/savings/account/${id}`); },
    goBack() { this.$router.push({ name: 'dashboard' }); },
    async load() {
      this.loading = true;
      this.error = '';
      this.payload = null;
      try {
        const { ok, data } = await apiGet('/api/savings', store.token);
        if (ok) this.payload = data?.data || data || {};
        else this.error = data?.message || 'We could not load your savings.';
      } catch (e) {
        this.error = 'Network error. Please try again.';
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>

<style scoped>
.ms-ef__row { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; padding: 4px 0; }
.ms-ef__label { font-size: 13px; color: var(--neutral-500); }
.ms-ef__value { font-size: 14px; font-weight: 700; color: var(--horizon-500); white-space: nowrap; }
.ms-ef__runway { font-size: 13px; font-weight: 700; }
.ms-ef__runway--spring { color: var(--spring-600); }
.ms-ef__runway--violet { color: var(--violet-500); }
.ms-ef__runway--raspberry { color: var(--raspberry-500); }

.mts-bar { width: 100%; height: 6px; background: var(--horizon-200); border-radius: var(--radius-full); overflow: hidden; }
.mts-bar__fill { height: 6px; border-radius: var(--radius-full); transition: width 0.3s ease; }
.mts-bar__fill--spring { background: var(--spring-500); }
.mts-bar__fill--violet { background: var(--violet-500); }
.mts-bar__fill--raspberry { background: var(--raspberry-500); }

.mts-allow__head { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; margin-bottom: 6px; }
.mts-allow__label { font-size: 14px; font-weight: 700; color: var(--horizon-500); }
.mts-allow__cap { font-size: 12px; color: var(--neutral-500); white-space: nowrap; }
.mts-allow__foot { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; margin-top: 6px; }
.mts-allow__remain { font-size: 12px; font-weight: 700; }
.mts-allow__remain--spring { color: var(--spring-600); }
.mts-allow__remain--violet { color: var(--violet-500); }
.mts-allow__remain--raspberry { color: var(--raspberry-500); }
.mts-allow__used { font-size: 12px; color: var(--neutral-500); white-space: nowrap; }

.ms-acct { display: flex; align-items: center; justify-content: space-between; gap: 12px; width: 100%; text-align: left; background: transparent; border: 0; border-bottom: 1px solid var(--light-gray); padding: 14px 0; cursor: pointer; }
.ms-acct:last-child { border-bottom: 0; padding-bottom: 0; }
.ms-acct:first-child { padding-top: 4px; }
.ms-acct:active { opacity: 0.7; }
.ms-acct__main { min-width: 0; flex: 1; }
.ms-acct__provider { display: block; font-size: 15px; font-weight: 700; color: var(--horizon-500); }
.ms-acct__meta { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; margin-top: 4px; }
.ms-acct__tag { font-size: 11px; font-weight: 700; color: var(--violet-500); background: color-mix(in srgb, var(--violet-500) 12%, var(--white)); padding: 1px 7px; border-radius: var(--radius-sm); }
.ms-acct__tag--ef { color: var(--spring-600); background: color-mix(in srgb, var(--spring-500) 12%, var(--white)); }
.ms-acct__rate { font-size: 12px; color: var(--neutral-500); }
.ms-acct__right { display: flex; flex-direction: column; align-items: flex-end; gap: 2px; flex-shrink: 0; }
.ms-acct__balance { font-size: 15px; font-weight: 700; color: var(--horizon-500); white-space: nowrap; }
.ms-acct__view { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--raspberry-500); }
</style>
