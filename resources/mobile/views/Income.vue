<template>
  <MobileChrome title="Income" subtitle="What you (and your spouse) earn each year" :loading="loading" loading-label="your income">
    <div v-if="error" class="m-card m-state">
      <p class="m-err">{{ error }}</p>
      <button class="m-btn" @click="load">Try again</button>
    </div>

    <template v-else>
      <div class="m-card m-hero">
        <p class="m-sub m-label">Your total annual income</p>
        <p class="m-metric">{{ fmt(userTotal) }}</p>
      </div>

      <div class="m-card">
        <p class="m-section-label" style="margin-top:0">Your income</p>
        <p v-if="!userRows.length" class="m-sub" style="margin-bottom:0">No income recorded yet.</p>
        <div v-for="row in userRows" :key="row.key" class="inc-row">
          <span class="inc-row__label">{{ row.label }}</span>
          <span class="inc-row__amt">{{ fmt(row.amount) }}</span>
        </div>
      </div>

      <div v-if="hasSpouse" class="m-card">
        <p class="m-section-label" style="margin-top:0">Your spouse's income</p>
        <p v-if="!spouseRows.length" class="m-sub" style="margin-bottom:0">No spouse income recorded yet.</p>
        <div v-for="row in spouseRows" :key="row.key" class="inc-row">
          <span class="inc-row__label">{{ row.label }}</span>
          <span class="inc-row__amt">{{ fmt(row.amount) }}</span>
        </div>
      </div>
    </template>
  </MobileChrome>
</template>

<script>
import { store } from '../store.js';
import { apiGet } from '../api.js';
import MobileChrome from '../components/MobileChrome.vue';

function formatCurrency(value) {
  if (value == null || value === '' || isNaN(Number(value))) return '—';
  return new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP', maximumFractionDigits: 0 }).format(Number(value));
}

const SOURCES = [
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
    userIncome() { return this.summary?.user || {}; },
    spouseIncome() { return this.summary?.spouse || null; },
    hasSpouse() { return !!this.spouseIncome; },
    userRows() { return this.rowsFor(this.userIncome); },
    spouseRows() { return this.rowsFor(this.spouseIncome || {}); },
    userTotal() { return Number(this.userIncome.total) || this.userRows.reduce((s, r) => s + r.amount, 0); },
  },
  async created() { await this.load(); },
  methods: {
    fmt(v) { return formatCurrency(v); },
    rowsFor(income) {
      return SOURCES.map(s => ({ ...s, amount: Number(income[s.key]) || 0 })).filter(r => r.amount > 0);
    },
    async load() {
      this.loading = true; this.error = ''; this.summary = null;
      try {
        const { ok, data } = await apiGet('/api/user/profile', store.token);
        if (ok) this.summary = (data?.data || data || {}).income_summary || { user: {}, spouse: null };
        else this.error = data?.message || 'We could not load your income.';
      } catch (e) { this.error = 'Network error. Please try again.'; }
      finally { this.loading = false; }
    },
  },
};
</script>

<style scoped>
.inc-row { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--horizon-100); }
.inc-row:last-of-type { border-bottom: 0; }
.inc-row__label { font-size: 14px; font-weight: 700; color: var(--horizon-500); }
.inc-row__amt { font-size: 14px; color: var(--neutral-600); white-space: nowrap; }
</style>
