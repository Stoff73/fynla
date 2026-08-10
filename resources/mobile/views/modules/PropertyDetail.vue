<template>
  <MobileChrome :title="heading" subtitle="Property details" :loading="loading" loading-label="this property" :edit-details="canEdit" :contextual-request="contextualRequest" back @back="goBack">
    <div v-if="error" class="m-card m-state">
      <p class="m-err">{{ error }}</p>
      <button class="m-btn" @click="load">Try again</button>
    </div>
    <template v-else-if="property">
      <div class="m-card m-hero">
        <p class="m-sub m-label">Current value</p>
        <p class="m-metric">{{ fmt(property.current_value) }}</p>
        <p v-if="Number(property.outstanding_mortgage) > 0" class="m-hero-sub pd-debt">Mortgage {{ fmt(property.outstanding_mortgage) }}</p>
      </div>
      <div class="m-card m-detail-rows">
        <p class="m-section-label">Property</p>
        <div v-for="row in rows" :key="row.key" class="m-detail-row"><span class="m-detail-key">{{ row.key }}</span><span class="m-detail-value">{{ row.value }}</span></div>
      </div>
      <div v-if="property.mortgages?.length" class="m-card m-detail-rows">
        <p class="m-section-label">Mortgages</p>
        <button v-for="mortgage in property.mortgages" :key="mortgage.id" type="button" class="pd-link" @click="openMortgage(mortgage.id)">
          <span>{{ mortgage.lender_name || mortgage.lender || 'Mortgage' }}</span>
          <span class="pd-debt">{{ fmt(mortgage.outstanding_balance ?? mortgage.current_balance) }}</span>
        </button>
      </div>
    </template>
  </MobileChrome>
</template>

<script>
import { store } from '../../store.js';
import { apiGet } from '../../api.js';
import { handleAuthExpiry } from '../../authExpiry.js';
import MobileChrome from '../../components/MobileChrome.vue';
import { buildContextualConversationRequest } from '../../fyn/contextualConversation.js';

const fmt = (value) => value == null || isNaN(Number(value)) ? '—' : new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP', maximumFractionDigits: 0 }).format(Number(value));
const label = (value) => value ? String(value).replace(/_/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase()) : '—';
const date = (value) => {
  if (!value) return '—';
  const parsed = new Date(value);
  return isNaN(parsed.getTime()) ? '—' : parsed.toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit', year: 'numeric' });
};

export default {
  name: 'MobilePropertyDetail',
  components: { MobileChrome },
  data: () => ({ loading: true, error: '', property: null }),
  computed: {
    recordId() { return Number(this.$route.params.id); },
    heading() { return this.property?.address_line_1 || 'Property'; },
    canEdit() { return this.property?.is_primary_owner !== false; },
    contextualRequest() {
      if (!this.canEdit || !Number.isInteger(this.recordId) || this.recordId < 1) return null;
      return buildContextualConversationRequest({ action: 'edit', resourceType: 'property', resourceId: this.recordId, currentDestination: { screen: 'property_detail', params: { property_id: this.recordId }, fallback: 'net_worth' }, origin: { kind: 'surface_action' } });
    },
    rows() {
      return [
        { key: 'Type', value: label(this.property.property_type) },
        { key: 'Ownership', value: label(this.property.ownership_type) },
        { key: 'Purchase price', value: fmt(this.property.purchase_price) },
        { key: 'Purchase date', value: date(this.property.purchase_date) },
        { key: 'Valuation date', value: date(this.property.valuation_date) },
        { key: 'Equity', value: fmt(this.property.equity) },
      ];
    },
  },
  async created() { await this.load(); },
  methods: {
    fmt,
    goBack() { this.$router.push({ name: 'm-net-worth-category', params: { category: 'property' } }); },
    openMortgage(id) { this.$router.push({ name: 'm-mortgage', params: { id } }); },
    async load() {
      this.loading = true; this.error = ''; this.property = null;
      try {
        const response = await apiGet(`/api/properties/${this.recordId}`, store.token);
        if (handleAuthExpiry(response, this.$router)) return;
        if (response.ok) this.property = (response.data?.data || response.data || {}).property || null;
        else this.error = response.data?.message || 'We could not load this property.';
      } catch { this.error = 'Network error. Please try again.'; }
      finally { this.loading = false; }
    },
  },
};
</script>

<style scoped>
.pd-debt { color: var(--raspberry-500); }
.pd-link { display:flex; width:100%; justify-content:space-between; gap:12px; padding:12px 0; border:0; border-bottom:1px solid var(--horizon-200); background:transparent; color:var(--horizon-500); font-size:14px; font-weight:700; text-align:left; cursor:pointer; }
.pd-link:last-child { border-bottom:0; }
</style>
