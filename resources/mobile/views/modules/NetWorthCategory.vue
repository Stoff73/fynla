<template>
  <MobileChrome title="Net Worth" subtitle="Everything you own, less what you owe" :loading="loading" loading-label="your accounts" :edit-details="false" back @back="goBack">
    <div class="m-card m-detail-header">
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

        <component
          :is="item.destination ? 'button' : 'article'"
          v-for="item in items"
          :key="item.key"
          :type="item.destination ? 'button' : null"
          class="mnwc-item"
          :class="{ 'mnwc-item--link': item.destination }"
          :data-destination="item.destination ? `${item.destination.screen}:${item.key}` : null"
          @click="navigate(item)"
        >
          <div class="mnwc-item__head">
            <span class="mnwc-item__name">{{ item.name }}</span>
            <span class="mnwc-item__value" :class="{ 'mnwc-item__value--debt': isLiabilities }">{{ fmt(item.value) }}</span>
          </div>
          <div v-if="item.fields && item.fields.length" class="mnwc-item__fields">
            <span v-for="(f, i) in item.fields" :key="i" class="mnwc-item__field">{{ f }}</span>
          </div>
          <span v-if="item.outstandingMortgage > 0" class="mnwc-item__mortgage mnwc-item__mortgage--debt">
            Mortgage {{ fmt(item.outstandingMortgage) }}
          </span>
        </component>
      </div>
    </template>
  </MobileChrome>
</template>

<script>
import { store } from '../../store.js';
import { apiGet } from '../../api.js';
import { handleAuthExpiry } from '../../authExpiry.js';
import MobileChrome from '../../components/MobileChrome.vue';
import { userSharePercent } from '../../../js/utils/ownership.js';

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
  chattels: { title: 'Valuables', sub: 'Valuable personal possessions', source: 'detailed' },
  liabilities: { title: 'Liabilities', sub: 'Everything you owe', source: 'detailed' },
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
      if (this.isLiabilities) return this.payload?.liabilities?.total_value ?? 0;
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
          // The viewer's share, not the record's stored primary-owner share —
          // the joint owner holds the complement (W-0015).
          if (it.ownership_percentage != null) fields.push(`${Math.round(userSharePercent(it))}% owned`);
          // Companies House filing deadline, once close enough to act on.
          // next_filing is computed server-side (NetWorthService) so this
          // matches the web card exactly rather than re-deriving it here.
          const filing = it.next_filing;
          if (filing && filing.days_until <= 30) {
            const label = filing.type === 'accounts' ? 'Accounts' : 'Confirmation statement';
            const days = filing.days_until;
            if (days < 0) fields.push(`${label} overdue by ${Math.abs(days)} ${Math.abs(days) === 1 ? 'day' : 'days'}`);
            else if (days === 0) fields.push(`${label} due today`);
            else if (days === 1) fields.push(`${label} due tomorrow`);
            else fields.push(`${label} due in ${days} days`);
          }
        }
        if (this.categoryKey === 'chattels') {
          if (it.chattel_type) fields.push(titleCase(it.chattel_type));
          if (it.year) fields.push(String(it.year));
        }
        if (it.ownership_type) fields.push(ownershipLabel(it.ownership_type));
        return {
          key: it.id ?? idx,
          name: it.name || titleCase(this.categoryKey),
          value: it.value,
          fields,
          outstandingMortgage: Number(it.outstanding_mortgage) || 0,
          destination: this.assetDestination(it),
        };
      });
    },
    liabilityItems() {
      return (this.payload?.liabilities?.items || []).map((item) => ({
        key: item.id,
        name: item.name,
        value: item.value,
        fields: [item.liability_type ? titleCase(item.liability_type) : null].filter(Boolean),
        destination: item.kind === 'mortgage'
          ? { screen: 'mortgage_detail', route: 'm-mortgage' }
          : { screen: 'liability_detail', route: 'm-liability' },
      }));
    },
    assetDestination(item) {
      if (!item?.id) return null;
      if (this.categoryKey === 'property') return { screen: 'property_detail', route: 'm-property' };
      if (this.categoryKey === 'investments') return { screen: 'investment_account_detail', route: 'm-investment-account' };
      if (this.categoryKey === 'cash') return { screen: 'savings_account_detail', route: 'm-savings-account' };
      if (this.categoryKey === 'pensions' && item.type) {
        return { screen: 'pension_detail', route: 'm-retirement-pension', type: item.type };
      }
      return null;
    },
    navigate(item) {
      if (!item?.destination) return;
      const params = { id: item.key };
      if (item.destination.type) params.type = item.destination.type;
      this.$router.push({ name: item.destination.route, params });
    },
    async load() {
      this.loading = true;
      this.error = '';
      this.payload = null;
      const path = this.config.source === 'overview'
        ? '/api/net-worth/overview'
        : '/api/net-worth/assets-summary-detailed';
      try {
        const { ok, status, data } = await apiGet(path, store.token);
        if (handleAuthExpiry({ status }, this.$router)) return;
        if (ok) this.payload = data?.data || data || {};
        else this.error = data?.message || 'We could not load this category.';
      } catch {
        this.error = 'Network error. Please try again.';
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>

<style scoped>
.mnwc-item { display: block; width: 100%; padding: 12px 0; border: 0; border-bottom: 1px solid var(--horizon-200); background: transparent; text-align: left; }
.mnwc-item--link { cursor: pointer; }
.mnwc-item--link:active { opacity: 0.72; }
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
.mnwc-item__mortgage { display: block; margin-top: 6px; font-size: 12px; font-weight: 700; }
.mnwc-item__mortgage--debt { color: var(--raspberry-500); }
</style>
