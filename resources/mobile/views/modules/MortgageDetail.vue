<template>
  <MobileChrome :title="heading" subtitle="Mortgage details" :loading="loading" loading-label="this mortgage" :edit-details="canEdit" :contextual-request="contextualRequest" back @back="goBack">
    <div v-if="error" class="m-card m-state"><p class="m-err">{{ error }}</p><button class="m-btn" @click="load">Try again</button></div>
    <template v-else-if="mortgage">
      <div class="m-card m-hero"><p class="m-sub m-label">Outstanding balance</p><p class="m-metric">{{ fmt(mortgage.outstanding_balance ?? mortgage.current_balance) }}</p><p class="m-hero-sub">{{ rate(mortgage.interest_rate) }} · {{ label(mortgage.rate_type) }}</p></div>
      <div class="m-card m-detail-rows"><p class="m-section-label">Mortgage</p><div v-for="row in rows" :key="row.key" class="m-detail-row"><span class="m-detail-key">{{ row.key }}</span><span class="m-detail-value">{{ row.value }}</span></div></div>
      <button v-if="mortgage.property?.id" type="button" class="m-card mdg-property" @click="openProperty"><span>Secured on</span><strong>{{ mortgage.property.address_line_1 || 'Property' }}</strong></button>
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
const date = (value) => { if (!value) return '—'; const parsed = new Date(value); return isNaN(parsed.getTime()) ? '—' : parsed.toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit', year: 'numeric' }); };

export default {
  name: 'MobileMortgageDetail', components: { MobileChrome }, data: () => ({ loading: true, error: '', mortgage: null }),
  computed: {
    recordId() { return Number(this.$route.params.id); }, heading() { return this.mortgage?.lender_name || this.mortgage?.lender || 'Mortgage'; }, canEdit() { return this.mortgage?.is_primary_owner !== false; },
    contextualRequest() { if (!this.canEdit || !Number.isInteger(this.recordId) || this.recordId < 1) return null; return buildContextualConversationRequest({ action: 'edit', resourceType: 'mortgage', resourceId: this.recordId, currentDestination: { screen: 'mortgage_detail', params: { mortgage_id: this.recordId }, fallback: 'net_worth' }, origin: { kind: 'surface_action' } }); },
    rows() { return [
      { key: 'Type', value: label(this.mortgage.mortgage_type) }, { key: 'Ownership', value: label(this.mortgage.ownership_type) }, { key: 'Monthly payment', value: fmt(this.mortgage.monthly_payment) }, { key: 'Interest rate', value: this.rate(this.mortgage.interest_rate) }, { key: 'Rate type', value: label(this.mortgage.rate_type) }, { key: 'Remaining term', value: this.mortgage.remaining_term_months == null ? '—' : `${this.mortgage.remaining_term_months} months` }, { key: 'Maturity date', value: date(this.mortgage.maturity_date) },
    ]; },
  },
  async created() { await this.load(); },
  methods: {
    fmt, label, rate(value) { return value == null || isNaN(Number(value)) ? '—' : `${Number(value).toFixed(2)}%`; },
    goBack() { this.$router.push({ name: 'm-net-worth-category', params: { category: 'liabilities' } }); }, openProperty() { this.$router.push({ name: 'm-property', params: { id: this.mortgage.property.id } }); },
    async load() { this.loading = true; this.error = ''; this.mortgage = null; try { const response = await apiGet(`/api/mortgages/${this.recordId}`, store.token); if (handleAuthExpiry(response, this.$router)) return; if (response.ok) this.mortgage = (response.data?.data || response.data || {}).mortgage || null; else this.error = response.data?.message || 'We could not load this mortgage.'; } catch { this.error = 'Network error. Please try again.'; } finally { this.loading = false; } },
  },
};
</script>

<style scoped>
.mdg-property { display:flex; width:100%; justify-content:space-between; gap:12px; border:0; color:var(--horizon-500); font-size:14px; text-align:left; cursor:pointer; }
</style>
