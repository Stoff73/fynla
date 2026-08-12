<template>
  <MobileChrome title="Income" :subtitle="incomeSubtitle" :loading="loading" loading-label="your income">
    <div v-if="error" class="m-card m-state">
      <p class="m-err">{{ error }}</p>
      <button class="m-btn" @click="load">Try again</button>
    </div>

    <template v-else>
      <div class="m-card m-hero">
        <p class="m-sub m-label">{{ isSpouseView ? "Your spouse's total annual income" : 'Your total annual income' }}</p>
        <p class="m-metric">{{ fmt(isSpouseView ? spouseTotal : userTotal) }}</p>
      </div>

      <div v-if="!isSpouseView" class="m-card">
        <p class="m-section-label" style="margin-top:0">Your income</p>
        <p v-if="!userRows.length" class="m-sub" style="margin-bottom:0">No income recorded yet.</p>
        <button v-for="row in userRows" :key="row.key" type="button" class="inc-row inc-row--button" :data-destination="`income_detail:user:${row.key}`" @click="openDetail('user', row.key)">
          <span class="inc-row__label">
            {{ row.label }}
            <span v-if="row.detail" class="inc-row__detail">{{ row.detail }}</span>
          </span>
          <span class="inc-row__amt">{{ fmt(row.amount) }}</span>
        </button>
      </div>

      <div v-if="isSpouseView || hasSpouse" class="m-card">
        <p class="m-section-label" style="margin-top:0">Your spouse's income</p>
        <p v-if="!spouseRows.length" class="m-sub" style="margin-bottom:0">No spouse income recorded yet.</p>
        <button v-for="row in spouseRows" :key="row.key" type="button" class="inc-row inc-row--button" :data-destination="`income_detail:spouse:${row.key}`" @click="openDetail('spouse', row.key)">
          <span class="inc-row__label">{{ row.label }}<span v-if="row.detail" class="inc-row__detail">{{ row.detail }}</span></span>
          <span class="inc-row__amt">{{ fmt(row.amount) }}</span>
        </button>
      </div>
    </template>
  </MobileChrome>
</template>

<script>
import { store } from '../store.js';
import { apiGet } from '../api.js';
import { handleAuthExpiry } from '../authExpiry.js';
import MobileChrome from '../components/MobileChrome.vue';

function formatCurrency(value) {
  if (value == null || value === '' || isNaN(Number(value))) return '—';
  return new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP', maximumFractionDigits: 0 }).format(Number(value));
}

const LEGACY_SOURCES = [
  { key: 'employment', label: 'Employment' },
  { key: 'self_employment', label: 'Self-employment' },
  { key: 'dividend', label: 'Dividends' },
  { key: 'interest', label: 'Interest' },
  { key: 'other', label: 'Other' },
];

export default {
  name: 'MobileIncome',
  components: { MobileChrome },
  data: () => ({ loading: true, error: '', summary: null }),
  computed: {
    // Spouse-verify view: the onboarding verify navigated here for the spouse
    // section (carried as ?section=spouse) — focus the screen on the spouse.
    isSpouseView() { return this.$route.query.section === 'spouse'; },
    incomeSubtitle() {
      return this.isSpouseView ? "Your spouse's income" : 'Your income';
    },
    userIncome() { return this.summary?.user || {}; },
    spouseIncome() { return this.summary?.spouse || null; },
    hasSpouse() { return !!this.spouseIncome; },
    userRows() { return this.rowsFor(this.userIncome); },
    spouseRows() { return this.rowsFor(this.spouseIncome || {}); },
    userTotal() { return Number(this.userIncome.total) || 0; },
    spouseTotal() { return Number((this.spouseIncome || {}).total) || 0; },
  },
  async created() {
    await this.load();
    // Same-route verify refresh: the onboarding chat bumps this after
    // applying an edit on this very screen — refetch so the page shows the
    // just-edited figures (no remount happens without a route change).
    this.$watch(() => store.screenRefreshTick, () => { this.load(); });
  },
  methods: {
    fmt(v) { return formatCurrency(v); },
    rowsFor(income) {
      if (Array.isArray(income.sources)) return income.sources;
      return LEGACY_SOURCES.map((s) => {
        const row = { ...s, amount: Number(income[s.key]) || 0, detail: '' };
        // Show the employer + role the user entered under the Employment row.
        if (s.key === 'employment') {
          row.detail = [income.employer, income.occupation].filter(Boolean).join(' · ');
        }
        return row;
      }).filter(r => r.amount > 0);
    },
    openDetail(owner, source) {
      this.$router.push({ name: 'm-income-detail', params: { owner, source } });
    },
    async load() {
      this.loading = true; this.error = ''; this.summary = null;
      try {
        const { ok, status, data } = await apiGet('/api/user/profile', store.token);
        if (handleAuthExpiry({ status }, this.$router)) return;
        if (ok) this.summary = (data?.data || data || {}).income_summary || { user: {}, spouse: null };
        else this.error = data?.message || 'We could not load your income.';
      } catch { this.error = 'Network error. Please try again.'; }
      finally { this.loading = false; }
    },
  },
};
</script>

<style scoped>
.inc-row { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--horizon-100); }
.inc-row:last-of-type { border-bottom: 0; }
.inc-row__label { display: flex; flex-direction: column; gap: 2px; font-size: 14px; font-weight: 700; color: var(--horizon-500); }
.inc-row__detail { font-size: 12px; font-weight: 400; color: var(--neutral-500); }
.inc-row__amt { font-size: 14px; color: var(--neutral-600); white-space: nowrap; }
.inc-row--button { width: 100%; border: 0; background: transparent; text-align: left; cursor: pointer; }
</style>
