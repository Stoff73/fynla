<template>
  <MobileChrome :title="source?.label || 'Income details'" subtitle="Canonical income source" :loading="loading" loading-label="this income source" :edit-details="!!source" :contextual-request="contextualRequest" back @back="goBack">
    <div v-if="error" class="m-card m-state">
      <p class="m-err">{{ error }}</p>
      <button class="m-btn" @click="load">Try again</button>
    </div>

    <template v-else-if="source">
      <div class="m-card m-hero">
        <p class="m-sub m-label">Annual amount</p>
        <p class="m-metric">{{ fmt(source.amount) }}</p>
      </div>

      <div class="m-card">
        <p class="m-section-label" style="margin-top:0">Income source</p>
        <div class="id-row"><span>Source</span><strong>{{ source.label }}</strong></div>
        <div class="id-row"><span>Frequency</span><strong>{{ human(source.frequency) }}</strong></div>
        <div class="id-row"><span>Ownership</span><strong>{{ source.ownership_label }}</strong></div>
        <div v-if="source.detail" class="id-row"><span>Details</span><strong>{{ source.detail }}</strong></div>
        <div class="id-row"><span>Tax position</span><strong>{{ source.tax_position }}</strong></div>
      </div>

      <div v-if="taxPosition" class="m-card">
        <p class="m-section-label" style="margin-top:0">Tax position</p>
        <div class="id-row"><span>Adjusted net income</span><strong>{{ fmt(taxPosition.adjusted_net_income) }}</strong></div>
        <div class="id-row"><span>{{ taxPosition.personal_allowance_label }}</span><strong>{{ fmt(taxPosition.personal_allowance) }}</strong></div>
        <div class="id-row"><span>{{ taxPosition.pension_annual_allowance_label }}</span><strong>{{ fmt(taxPosition.pension_annual_allowance) }}</strong></div>
      </div>
    </template>
  </MobileChrome>
</template>

<script>
import { apiGet } from '../api.js';
import { handleAuthExpiry } from '../authExpiry.js';
import MobileChrome from '../components/MobileChrome.vue';
import { buildContextualConversationRequest } from '../fyn/contextualConversation.js';
import { store } from '../store.js';

function formatCurrency(value) {
  if (value == null || value === '' || isNaN(Number(value))) return '—';
  return new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP', maximumFractionDigits: 0 }).format(Number(value));
}

export default {
  name: 'MobileIncomeDetail',
  components: { MobileChrome },
  data: () => ({ loading: true, error: '', summary: null }),
  computed: {
    owner() { return this.$route.params.owner; },
    sourceKey() { return this.$route.params.source; },
    ownerSummary() { return this.summary?.[this.owner] || null; },
    source() { return this.ownerSummary?.sources?.find(item => item.key === this.sourceKey) || null; },
    taxPosition() { return this.ownerSummary?.tax_position || null; },
    contextualRequest() {
      if (!this.source) return null;
      return buildContextualConversationRequest({
        action: 'edit',
        resourceType: 'income',
        currentDestination: {
          screen: 'income_detail',
          params: { income_owner: this.owner, income_source: this.sourceKey },
          fallback: 'income',
        },
        origin: { kind: 'surface_action' },
      });
    },
  },
  async created() { await this.load(); },
  methods: {
    fmt: formatCurrency,
    human(value) { return String(value || '').replaceAll('_', ' ').replace(/^./, c => c.toUpperCase()); },
    goBack() { this.$router.back(); },
    async load() {
      this.loading = true; this.error = ''; this.summary = null;
      try {
        const { ok, status, data } = await apiGet('/api/user/profile', store.token);
        if (handleAuthExpiry({ status }, this.$router)) return;
        if (!ok) this.error = data?.message || 'We could not load this income source.';
        else {
          this.summary = (data?.data || data || {}).income_summary || {};
          if (!this.source) this.error = 'This income source is no longer available.';
        }
      } catch { this.error = 'Network error. Please try again.'; }
      finally { this.loading = false; }
    },
  },
};
</script>

<style scoped>
.id-row { display: flex; align-items: baseline; justify-content: space-between; gap: 14px; padding: 10px 0; border-bottom: 1px solid var(--horizon-100); font-size: 14px; }
.id-row:last-child { border-bottom: 0; }
.id-row span { color: var(--neutral-600); }
.id-row strong { color: var(--horizon-500); text-align: right; }
</style>
