<template>
  <MobileChrome title="Expenditure" subtitle="What you spend each month" :loading="loading" loading-label="your expenditure">
    <div v-if="error" class="m-card m-state">
      <p class="m-err">{{ error }}</p>
      <button class="m-btn" @click="load">Try again</button>
    </div>

    <template v-else>
      <div class="m-card m-hero">
        <p class="m-sub m-label">Monthly expenditure</p>
        <p class="m-metric">{{ fmt(monthly) }}</p>
        <p class="m-hero-sub">{{ fmt(annual) }} a year</p>
      </div>

      <div v-if="categoryRows.length" class="m-card">
        <p class="m-section-label" style="margin-top:0">Where it goes each month</p>
        <div v-for="row in categoryRows" :key="row.key" class="exp-row">
          <span class="exp-row__label">{{ row.label }}</span>
          <span class="exp-row__amt">{{ fmt(row.amount) }}</span>
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

const CATEGORIES = [
  { key: 'food_groceries', label: 'Food & groceries' },
  { key: 'transport_fuel', label: 'Transport & fuel' },
  { key: 'clothing_personal_care', label: 'Clothing & personal care' },
  { key: 'entertainment_dining', label: 'Entertainment & dining' },
  { key: 'childcare', label: 'Childcare' },
  { key: 'other_expenditure', label: 'Other' },
];

export default {
  name: 'MobileExpenditure',
  components: { MobileChrome },
  data: () => ({ loading: true, error: '', expenditure: null }),
  computed: {
    monthly() { return Number(this.expenditure?.monthly_expenditure) || 0; },
    annual() { return Number(this.expenditure?.annual_expenditure) || (this.monthly * 12); },
    categoryRows() {
      const cats = this.expenditure?.categories || {};
      return CATEGORIES.map(c => ({ ...c, amount: Number(cats[c.key]) || 0 })).filter(r => r.amount > 0);
    },
  },
  async created() { await this.load(); },
  methods: {
    fmt(v) { return formatCurrency(v); },
    async load() {
      this.loading = true; this.error = ''; this.expenditure = null;
      try {
        const { ok, data } = await apiGet('/api/user/profile', store.token);
        if (ok) this.expenditure = (data?.data || data || {}).expenditure || {};
        else this.error = data?.message || 'We could not load your expenditure.';
      } catch (e) { this.error = 'Network error. Please try again.'; }
      finally { this.loading = false; }
    },
  },
};
</script>

<style scoped>
.exp-row { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--horizon-100); }
.exp-row:last-of-type { border-bottom: 0; }
.exp-row__label { font-size: 14px; font-weight: 700; color: var(--horizon-500); }
.exp-row__amt { font-size: 14px; color: var(--neutral-600); white-space: nowrap; }
</style>
