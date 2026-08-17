<template>
  <MobileChrome title="Expenditure" subtitle="What you spend each month" :loading="loading" loading-label="your expenditure" :contextual-request="contextualRequest">
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

      <div class="m-card exp-mode">
        <p class="m-section-label" style="margin-top:0">{{ presentation.entry_mode_label || 'Expenditure summary' }}</p>
        <p class="m-sub">{{ presentation.total_basis }}</p>
        <p v-if="!presentation.detail_available && presentation.summary_only_reason" class="exp-mode__reason">{{ presentation.summary_only_reason }}</p>
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
import { handleAuthExpiry } from '../authExpiry.js';
import MobileChrome from '../components/MobileChrome.vue';
import { buildContextualConversationRequest } from '../fyn/contextualConversation.js';

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
    presentation() { return this.expenditure?.presentation || {}; },
    monthly() { return Number(this.presentation.active_monthly_total) || 0; },
    annual() { return Number(this.presentation.active_annual_total) || 0; },
    contextualRequest() {
      return buildContextualConversationRequest({
        action: 'edit',
        resourceType: 'expenditure',
        currentDestination: { screen: 'expenditure', params: {}, fallback: 'dashboard' },
        origin: { kind: 'surface_action' },
      });
    },
    categoryRows() {
      const cats = this.expenditure?.categories || {};
      return CATEGORIES.map(c => ({ ...c, amount: Number(cats[c.key]) || 0 })).filter(r => r.amount > 0);
    },
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
    async load() {
      this.loading = true; this.error = ''; this.expenditure = null;
      try {
        const { ok, status, data } = await apiGet('/api/user/profile', store.token);
        if (handleAuthExpiry({ status }, this.$router)) return;
        if (ok) this.expenditure = (data?.data || data || {}).expenditure || {};
        else this.error = data?.message || 'We could not load your expenditure.';
      } catch { this.error = 'Network error. Please try again.'; }
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
.exp-mode__reason { margin: 8px 0 0; color: var(--neutral-600); font-size: 14px; line-height: 1.45; }
</style>
