<template>
  <MobileChrome title="Investments" subtitle="Your investment accounts, holdings and allowances" :loading="loading" loading-label="this account" :edit-details="canEdit" :contextual-request="contextualRequest" back @back="goBack">
    <div class="m-card m-detail-header">
      <h1 class="m-h1">{{ account ? (account.provider || account.platform || 'Investment account') : 'Investment account' }}</h1>
      <p class="m-sub">{{ account ? accountTypeLabel(account) : 'Account detail' }}</p>
    </div>

    <div v-if="loading" class="m-card m-state">
      <p class="m-sub">Loading account…</p>
    </div>

    <div v-else-if="error" class="m-card m-state">
      <p class="m-err">{{ error }}</p>
      <button class="m-btn" @click="load">Try again</button>
    </div>

    <div v-else-if="!account" class="m-card m-state">
      <p class="m-sub">We could not find that account.</p>
      <button class="m-btn" @click="goBack">Back to investments</button>
    </div>

    <template v-else>
      <!-- Value hero -->
      <div class="m-card m-hero">
        <p class="m-sub m-label">Current value</p>
        <p class="m-metric">{{ fmt(account.current_value) }}</p>
        <p v-if="account.contributions_ytd" class="m-hero-sub">{{ fmt(account.contributions_ytd) }} contributed this tax year</p>
      </div>

      <!-- Account information -->
      <div class="m-card">
        <p class="m-section-label" style="margin-top:0">Account information</p>
        <div v-for="row in infoRows" :key="row.label" class="mid-row">
          <span class="mid-row__key">{{ row.label }}</span>
          <span class="mid-row__val">{{ row.value }}</span>
        </div>
      </div>

      <div v-if="isIsa" class="m-card">
        <p class="m-section-label" style="margin-top:0">ISA contribution history</p>
        <div v-if="isaStatus?.available_tax_years?.length" class="mid-years">
          <button
            v-for="year in isaStatus.available_tax_years"
            :key="year"
            type="button"
            class="mid-year"
            :class="{ 'mid-year--active': isaStatus.tax_year === year }"
            @click="loadIsaStatus(year)"
          >{{ year }}</button>
        </div>
        <p v-if="isaLoading" class="m-sub">Loading ISA contributions…</p>
        <ISAContributionHistory v-else :status="isaStatus" :account-id="account.id" account-class="investment" />
      </div>

      <CanonicalPortfolio :portfolio="account.portfolio" />
    </template>
  </MobileChrome>
</template>

<script>
import { store } from '../../store.js';
import { apiGet } from '../../api.js';
import { handleAuthExpiry } from '../../authExpiry.js';
import { formatCurrency, accountTypeLabel, isIsaAccount } from './investmentFormat.js';
import MobileChrome from '../../components/MobileChrome.vue';
import CanonicalPortfolio from '../../components/CanonicalPortfolio.vue';
import ISAContributionHistory from '../../components/ISAContributionHistory.vue';
import { buildContextualConversationRequest } from '../../fyn/contextualConversation.js';

function capitalise(s) {
  if (!s) return '';
  return String(s).replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

export default {
  name: 'MobileInvestmentAccountDetail',
  components: { CanonicalPortfolio, ISAContributionHistory, MobileChrome },
  data: () => ({ loading: true, error: '', accounts: [], isaStatus: null, isaLoading: false }),
  computed: {
    accountId() { return this.$route.params.id; },
    canEdit() { return this.account?.is_primary_owner !== false; },
    contextualRequest() {
      if (!this.canEdit) return null;
      const accountId = Number(this.accountId);
      if (!Number.isInteger(accountId) || accountId < 1) return null;
      return buildContextualConversationRequest({
        action: 'edit',
        resourceType: 'investment_account',
        resourceId: accountId,
        currentDestination: {
          screen: 'investment_account_detail',
          params: { account_id: accountId },
          fallback: 'investment',
        },
        origin: { kind: 'surface_action' },
      });
    },
    account() {
      return this.accounts.find((a) => String(a.id) === String(this.accountId)) || null;
    },
    isIsa() { return isIsaAccount(this.account || {}); },
    infoRows() {
      const a = this.account;
      if (!a) return [];
      const rows = [
        { label: 'Provider', value: a.provider || '—' },
        { label: 'Platform', value: a.platform || '—' },
        { label: 'Account type', value: accountTypeLabel(a) },
        { label: 'Country', value: a.country === 'UK' ? 'United Kingdom' : (a.country || 'United Kingdom') },
      ];
      if (this.isIsa) rows.splice(3, 0, { label: 'Owner', value: a.owner_name || 'You' });
      else rows.splice(3, 0, { label: 'Ownership', value: capitalise(a.ownership_type) || 'Individual' });
      if (a.monthly_contribution_amount) {
        rows.push({ label: 'Monthly contribution', value: this.fmt(a.monthly_contribution_amount) });
      }
      return rows;
    },
  },
  async created() { await this.load(); },
  methods: {
    fmt(v) { return formatCurrency(v); },
    accountTypeLabel(a) { return accountTypeLabel(a); },
    pct(v) {
      if (v == null || Number.isNaN(Number(v))) return '—';
      return `${Number(v).toFixed(1)}%`;
    },
    assetTypeLabel(h) { return capitalise(h.asset_type); },
    goBack() { this.$router.push('/investment'); },
    async load() {
      this.loading = true;
      this.error = '';
      try {
        const { ok, status, data } = await apiGet('/api/investment', store.token);
        if (handleAuthExpiry({ status }, this.$router)) return;
        if (ok) {
          this.accounts = (data?.data || data || {}).accounts || [];
          if (this.isIsa) await this.loadIsaStatus();
        }
        else this.error = data?.message || 'We could not load this account.';
      } catch {
        this.error = 'Network error. Please try again.';
      } finally {
        this.loading = false;
      }
    },
    async loadIsaStatus(taxYear = null) {
      this.isaLoading = true;
      try {
        const path = taxYear
          ? `/api/savings/isa-allowance/${taxYear}`
          : '/api/savings';
        const { ok, status, data } = await apiGet(path, store.token);
        if (handleAuthExpiry({ status }, this.$router)) return;
        if (!ok) return;
        const payload = data?.data || data || {};
        this.isaStatus = taxYear ? payload : payload.isa_allowance;
      } catch {
        // ISA history is supplementary; keep the canonical account page usable
        // if this independent request is temporarily unavailable.
      } finally {
        this.isaLoading = false;
      }
    },
  },
};
</script>

<style scoped>
.mid-row { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--horizon-100); }
.mid-row:last-child { border-bottom: 0; }
.mid-row__key { font-size: 13px; color: var(--neutral-500); }
.mid-row__val { font-size: 14px; font-weight: 700; color: var(--horizon-500); text-align: right; }

.mid-years { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 12px; }
.mid-year { border: 1px solid var(--horizon-200); border-radius: var(--radius-sm); background: var(--white); padding: 5px 9px; color: var(--horizon-500); font-size: 12px; font-weight: 700; }
.mid-year--active { border-color: var(--violet-500); background: var(--light-blue-100); color: var(--violet-500); }
</style>
