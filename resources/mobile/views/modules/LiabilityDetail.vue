<template>
  <MobileChrome :title="heading" subtitle="Liability details" :loading="loading" loading-label="this liability" :edit-details="canEdit" :contextual-request="contextualRequest" back @back="goBack">
    <div v-if="error" class="m-card m-state"><p class="m-err">{{ error }}</p><button class="m-btn" @click="load">Try again</button></div>
    <template v-else-if="liability">
      <div class="m-card m-hero"><p class="m-sub m-label">Current balance</p><p class="m-metric">{{ fmt(liability.current_balance) }}</p><p class="m-hero-sub">{{ label(liability.liability_type) }}</p></div>
      <div class="m-card m-detail-rows"><p class="m-section-label">Liability</p><div v-for="row in rows" :key="row.key" class="m-detail-row"><span class="m-detail-key">{{ row.key }}</span><span class="m-detail-value">{{ row.value }}</span></div></div>
      <div v-if="liability.notes" class="m-card"><p class="m-section-label">Notes</p><p class="m-sub">{{ liability.notes }}</p></div>
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
  name: 'MobileLiabilityDetail', components: { MobileChrome }, data: () => ({ loading: true, error: '', liability: null }),
  computed: {
    recordId() { return Number(this.$route.params.id); }, heading() { return this.liability?.liability_name || 'Liability'; }, canEdit() { return this.liability?.is_primary_owner !== false; },
    contextualRequest() { if (!this.canEdit || !Number.isInteger(this.recordId) || this.recordId < 1) return null; return buildContextualConversationRequest({ action: 'edit', resourceType: 'liability', resourceId: this.recordId, currentDestination: { screen: 'liability_detail', params: { liability_id: this.recordId }, fallback: 'net_worth' }, origin: { kind: 'surface_action' } }); },
    rows() { return [
      { key: 'Type', value: label(this.liability.liability_type) }, { key: 'Ownership', value: label(this.liability.ownership_type) }, { key: 'Monthly repayment', value: fmt(this.liability.monthly_payment) }, { key: 'Interest rate', value: this.rate(this.liability.interest_rate) }, { key: 'Maturity date', value: date(this.liability.maturity_date) }, { key: 'Secured against', value: this.liability.secured_against || 'Unsecured' }, { key: 'Rate fixed until', value: date(this.liability.fixed_until) },
    ]; },
  },
  async created() { await this.load(); },
  methods: {
    fmt, label, rate(value) { return value == null || isNaN(Number(value)) ? '—' : `${Number(value).toFixed(2)}%`; }, goBack() { this.$router.push({ name: 'm-net-worth-category', params: { category: 'liabilities' } }); },
    async load() { this.loading = true; this.error = ''; this.liability = null; try { const response = await apiGet(`/api/estate/liabilities/${this.recordId}`, store.token); if (handleAuthExpiry(response, this.$router)) return; if (response.ok) this.liability = (response.data?.data || response.data || {}).liability || null; else this.error = response.data?.message || 'We could not load this liability.'; } catch { this.error = 'Network error. Please try again.'; } finally { this.loading = false; } },
  },
};
</script>
