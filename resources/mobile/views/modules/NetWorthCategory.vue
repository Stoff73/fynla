<template>
  <MobileChrome title="Net Worth" subtitle="Everything you own, less what you owe" :loading="loading" loading-label="your accounts">
    <div class="m-card m-detail-header">
      <button class="m-back" @click="goBack" aria-label="Back to net worth">Back</button>
      <h1 class="m-h1">{{ title }}</h1>
      <p class="m-sub">{{ subtitle }}</p>
    </div>

    <div v-if="loading" class="m-card m-state">
      <p class="m-sub">Loading…</p>
    </div>

    <div v-else-if="error" class="m-card m-state">
      <p class="m-err">{{ error }}</p>
      <button class="m-btn" @click="load">Try again</button>
    </div>

    <template v-else>
      <!-- Category total hero -->
      <div class="m-card m-hero">
        <p class="m-sub m-label">{{ isLiabilities ? 'Total owed' : 'Total value' }}</p>
        <p class="m-metric">{{ fmt(total) }}</p>
        <p class="m-hero-sub">{{ heroSub }}</p>
      </div>

      <!-- Items list -->
      <div class="m-card">
        <p class="m-section-label" style="margin-top:0">{{ isLiabilities ? 'Breakdown' : 'Items' }}</p>

        <div v-if="!items.length" class="m-state" style="padding:8px 0">
          <p class="m-sub" style="margin-bottom:0">Nothing recorded in this category yet.</p>
        </div>

        <article v-for="item in items" :key="item.key" class="mnwc-item">
          <div class="mnwc-item__head">
            <span class="mnwc-item__name">{{ item.name }}</span>
            <span class="mnwc-item__value" :class="{ 'mnwc-item__value--debt': isLiabilities }">{{ fmt(item.value) }}</span>
          </div>
          <div v-if="item.fields && item.fields.length" class="mnwc-item__fields">
            <span v-for="(f, i) in item.fields" :key="i" class="mnwc-item__field">{{ f }}</span>
          </div>
        </article>
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

function titleCase(value) {
  if (!value) return '';
  return String(value).replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

function ownershipLabel(value) {
  const map = {
    individual: 'Individually owned',
    joint: 'Jointly owned',
    tenants_in_common: 'Tenants in common',
    trust: 'Held in trust',
  };
  return map[value] || titleCase(value);
}

const CONFIG = {
  property: { title: 'Property', sub: 'Homes and other property you own', source: 'detailed' },
  investments: { title: 'Investments', sub: 'Investment accounts and ISAs', source: 'detailed' },
  pensions: { title: 'Pensions', sub: 'Accessible pension capital', source: 'detailed' },
  cash: { title: 'Cash & savings', sub: 'Savings accounts and cash', source: 'detailed' },
  business: { title: 'Business interests', sub: 'Your share of business holdings', source: 'detailed' },
  chattels: { title: 'Possessions', sub: 'Valuable personal possessions', source: 'detailed' },
  liabilities: { title: 'Liabilities', sub: 'Everything you owe', source: 'overview' },
};

const LIABILITY_LABELS = {
  mortgages: 'Mortgages',
  loans: 'Loans',
  credit_cards: 'Credit cards',
  other: 'Other debts',
};

export default {
  name: 'MobileNetWorthCategory',
  components: { MobileChrome },
  data: () => ({ loading: true, error: '', payload: null }),
  computed: {
    categoryKey() { return this.$route.params.category; },
    config() { return CONFIG[this.categoryKey] || { title: 'Net Worth', sub: '', source: 'detailed' }; },
    isLiabilities() { return this.categoryKey === 'liabilities'; },
    title() { return this.config.title; },
    subtitle() { return this.config.sub; },
    total() {
      if (this.isLiabilities) return this.payload?.total_liabilities ?? 0;
      return this.payload?.[this.categoryKey]?.total_value ?? 0;
    },
    items() {
      if (this.isLiabilities) return this.liabilityItems();
      return this.assetItems();
    },
    heroSub() {
      const n = this.items.length;
      if (this.isLiabilities) return `Across ${n} ${n === 1 ? 'type' : 'types'} of debt.`;
      return `Across ${n} ${n === 1 ? 'item' : 'items'}.`;
    },
  },
  async created() { await this.load(); },
  methods: {
    fmt(v) { return formatCurrency(v); },
    goBack() { this.$router.push('/net-worth'); },
    assetItems() {
      const raw = this.payload?.[this.categoryKey]?.items || [];
      return raw.map((it, idx) => {
        const fields = [];
        if (this.categoryKey === 'property' && it.type) fields.push(titleCase(it.type));
        if (this.categoryKey === 'investments' && it.account_type) fields.push(titleCase(it.account_type));
        if (this.categoryKey === 'cash') {
          if (it.is_isa) fields.push('ISA');
          if (it.is_emergency_fund) fields.push('Emergency fund');
        }
        if (this.categoryKey === 'pensions' && it.type) fields.push(it.type === 'db' ? 'Defined Benefit' : 'Defined Contribution');
        if (this.categoryKey === 'pensions' && it.annual_pension) fields.push(`${this.fmt(it.annual_pension)} a year`);
        if (this.categoryKey === 'business') {
          if (it.business_type) fields.push(titleCase(it.business_type));
          if (it.ownership_percentage != null) fields.push(`${Math.round(Number(it.ownership_percentage))}% owned`);
        }
        if (this.categoryKey === 'chattels') {
          if (it.chattel_type) fields.push(titleCase(it.chattel_type));
          if (it.year) fields.push(String(it.year));
        }
        if (it.ownership_type) fields.push(ownershipLabel(it.ownership_type));
        return { key: it.id ?? idx, name: it.name || titleCase(this.categoryKey), value: it.value, fields };
      });
    },
    liabilityItems() {
      const breakdown = this.payload?.liabilities_breakdown || {};
      return Object.keys(LIABILITY_LABELS)
        .filter((k) => Number(breakdown[k] ?? 0) > 0)
        .map((k) => ({ key: k, name: LIABILITY_LABELS[k], value: breakdown[k], fields: [] }));
    },
    async load() {
      this.loading = true;
      this.error = '';
      this.payload = null;
      const path = this.config.source === 'overview'
        ? '/api/net-worth/overview'
        : '/api/net-worth/assets-summary-detailed';
      try {
        const { ok, data } = await apiGet(path, store.token);
        if (ok) this.payload = data?.data || data || {};
        else this.error = data?.message || 'We could not load this category.';
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
.mnwc-item { padding: 12px 0; border-bottom: 1px solid var(--horizon-200); }
.mnwc-item:first-of-type { padding-top: 4px; }
.mnwc-item:last-of-type { border-bottom: 0; padding-bottom: 0; }
.mnwc-item__head { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; }
.mnwc-item__name { font-size: 15px; font-weight: 700; color: var(--horizon-500); }
.mnwc-item__value { font-size: 15px; font-weight: 700; color: var(--horizon-500); white-space: nowrap; }
.mnwc-item__value--debt { color: var(--raspberry-500); }
.mnwc-item__fields { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px; }
.mnwc-item__field {
  font-size: 11px; font-weight: 700; color: var(--neutral-600);
  background: var(--horizon-100); padding: 2px 8px; border-radius: var(--radius-full);
}
</style>
