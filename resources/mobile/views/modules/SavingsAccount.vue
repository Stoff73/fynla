<template>
  <MobileChrome title="Savings and emergency fund" subtitle="Your cash, emergency-fund runway and ISA allowance" :loading="loading" loading-label="this account" :edit-details="canEdit" :contextual-request="contextualRequest" back @back="goBack">
    <div class="m-card m-detail-header">
      <h1 class="m-h1">{{ headerTitle }}</h1>
      <p class="m-sub">{{ headerSub }}</p>
    </div>

    <div v-if="loading" class="m-card m-state">
      <p class="m-sub">Loading account details…</p>
    </div>

    <div v-else-if="error" class="m-card m-state">
      <p class="m-err">{{ error }}</p>
      <button class="m-btn" @click="load">Try again</button>
    </div>

    <template v-else-if="account">
      <!-- Balance hero -->
      <div class="m-card m-hero">
        <p class="m-sub m-label">Full balance</p>
        <p class="m-metric">{{ fmt(fullBalance) }}</p>
        <p v-if="isJoint" class="m-hero-sub">Your share ({{ sharePercent }}): {{ fmt(userShare) }}</p>
        <p v-if="coOwner" class="m-hero-sub">Held with {{ coOwner }}</p>
        <div v-if="tags.length" class="msa-tags">
          <span v-for="t in tags" :key="t.label" class="msa-tag" :class="t.cls">{{ t.label }}</span>
        </div>
      </div>

      <!-- Balance & interest -->
      <div class="m-card m-detail-rows">
        <p class="m-section-label">Balance &amp; interest</p>
        <div v-for="row in balanceRows" :key="row.key" class="m-detail-row">
          <span class="m-detail-key">{{ row.key }}</span>
          <span class="m-detail-value">{{ row.value }}</span>
        </div>
      </div>

      <!-- Account information -->
      <div class="m-card m-detail-rows">
        <p class="m-section-label">Account information</p>
        <div v-for="row in infoRows" :key="row.key" class="m-detail-row">
          <span class="m-detail-key">{{ row.key }}</span>
          <span class="m-detail-value">{{ row.value }}</span>
        </div>
      </div>

      <!-- ISA details -->
      <div v-if="account.is_isa" class="m-card m-detail-rows">
        <p class="m-section-label">ISA details</p>
        <div v-for="row in isaRows" :key="row.key" class="m-detail-row">
          <span class="m-detail-key">{{ row.key }}</span>
          <span class="m-detail-value">{{ row.value }}</span>
        </div>
      </div>

      <div v-if="account.is_isa" class="m-card">
        <p class="m-section-label" style="margin-top:0">ISA contribution history</p>
        <div v-if="isaStatus?.available_tax_years?.length" class="msa-years">
          <button
            v-for="year in isaStatus.available_tax_years"
            :key="year"
            type="button"
            class="msa-year"
            :class="{ 'msa-year--active': isaStatus.tax_year === year }"
            @click="loadIsaStatus(year)"
          >{{ year }}</button>
        </div>
        <p v-if="isaLoading" class="m-sub">Loading ISA contributions…</p>
        <ISAContributionHistory v-else :status="isaStatus" :account-id="account.id" account-class="savings" />
      </div>
    </template>
  </MobileChrome>
</template>

<script>
import { store } from '../../store.js';
import { apiGet } from '../../api.js';
import { handleAuthExpiry } from '../../authExpiry.js';
import MobileChrome from '../../components/MobileChrome.vue';
import ISAContributionHistory from '../../components/ISAContributionHistory.vue';
import { buildContextualConversationRequest } from '../../fyn/contextualConversation.js';
import { calculateUserShare, coOwnerName, isSharedRecord, userSharePercent } from '../../../js/utils/ownership.js';

function formatCurrency(value) {
  if (value == null || value === '' || isNaN(Number(value))) return '—';
  return new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP', maximumFractionDigits: 0 }).format(Number(value));
}

const ACCOUNT_TYPES = {
  savings_account: 'Savings account',
  current_account: 'Current account',
  easy_access: 'Easy access',
  notice: 'Notice account',
  fixed: 'Fixed term',
  cash_isa: 'Cash ISA',
  lisa: 'Lifetime ISA',
};
const ACCESS_TYPES = {
  immediate: 'Immediate access',
  instant: 'Instant access',
  notice: 'Notice required',
  fixed: 'Fixed term',
};
const ISA_TYPES = {
  cash: 'Cash ISA',
  stocks_and_shares: 'Stocks & Shares ISA',
  lifetime: 'Lifetime ISA',
  LISA: 'Lifetime ISA',
  lisa: 'Lifetime ISA',
  junior: 'Junior ISA',
  junior_isa: 'Junior ISA',
  innovative_finance: 'Innovative Finance ISA',
};

export default {
  name: 'MobileSavingsAccount',
  components: { ISAContributionHistory, MobileChrome },
  data: () => ({ loading: true, error: '', account: null, isaStatus: null, isaLoading: false }),
  computed: {
    accountId() { return this.$route.params.id; },
    canEdit() { return this.account?.is_primary_owner !== false; },
    contextualRequest() {
      if (!this.canEdit) return null;
      const accountId = Number(this.accountId);
      if (!Number.isInteger(accountId) || accountId < 1) return null;
      return buildContextualConversationRequest({
        action: 'edit',
        resourceType: 'savings_account',
        resourceId: accountId,
        currentDestination: {
          screen: 'savings_account_detail',
          params: { account_id: accountId },
          fallback: 'savings',
        },
        origin: { kind: 'surface_action' },
      });
    },
    headerTitle() {
      if (!this.account) return 'Account';
      return this.account.provider || this.account.institution || 'Savings account';
    },
    headerSub() {
      if (!this.account) return 'Account details';
      return this.accountTypeLabel(this.account.account_type);
    },
    // Ownership display via the ONE home shared with the desktop SPA
    // (Rule 19 + Rule 20). The stored percentage is the PRIMARY owner's, so
    // rendering it to the joint owner shows the wrong side of the split.
    isJoint() { return isSharedRecord(this.account); },
    fullBalance() {
      return this.account?.full_balance ?? this.account?.current_balance ?? 0;
    },
    userShare() {
      return calculateUserShare(this.account, { valueField: 'current_balance' });
    },
    sharePercent() {
      return `${userSharePercent(this.account).toFixed(2)}%`;
    },
    coOwner() {
      return coOwnerName(this.account);
    },
    rateNum() { return Number(this.account?.interest_rate || 0); },
    // CSJ 2026-08-23: /m never works anything out. These were
    // `balance * (rate / 100)` and `/ 12` in the client; the model appends both
    // now, so this screen and the Personal Savings Allowance work cannot disagree
    // about what an account earns (Rule 20).
    annualInterest() { return Number(this.account?.annual_interest ?? 0); },
    monthlyInterest() { return Number(this.account?.monthly_interest ?? 0); },
    tags() {
      const out = [];
      if (this.account?.is_emergency_fund) out.push({ label: 'Emergency fund', cls: 'msa-tag--ef' });
      if (this.account?.is_isa) out.push({ label: 'ISA', cls: 'msa-tag--isa' });
      return out;
    },
    balanceRows() {
      const rows = [
        { key: 'Full balance', value: this.fmt(this.fullBalance) },
      ];
      if (this.isJoint) {
        rows.push({ key: `Your share (${this.sharePercent})`, value: this.fmt(this.userShare) });
      }
      rows.push({ key: 'Interest rate', value: this.rate(this.account.interest_rate) });
      rows.push({ key: 'Monthly interest', value: this.fmt(this.monthlyInterest) });
      rows.push({ key: 'Annual interest', value: this.fmt(this.annualInterest) });
      return rows;
    },
    infoRows() {
      const a = this.account;
      const rows = [
        { key: 'Provider', value: a.provider || a.institution || '—' },
        { key: 'Account type', value: this.accountTypeLabel(a.account_type) },
        { key: 'Access', value: this.accessTypeLabel(a.access_type) },
      ];
      if (a.access_type === 'notice' && a.notice_period_days) {
        rows.push({ key: 'Notice period', value: `${a.notice_period_days} days` });
      }
      if (a.access_type === 'fixed' && a.maturity_date) {
        rows.push({ key: 'Maturity date', value: this.fmtDate(a.maturity_date) });
        rows.push({ key: 'Time to maturity', value: this.timeToMaturity(a.maturity_date) });
      }
      if (a.country) rows.push({ key: 'Country', value: a.country });
      if (a.is_isa) rows.push({ key: 'Owner', value: a.owner_name || 'You' });
      else if (a.ownership_type) rows.push({ key: 'Ownership', value: this.ownershipLabel(a.ownership_type) });
      return rows;
    },
    isaRows() {
      const a = this.account;
      const rows = [
        { key: 'ISA type', value: this.isaTypeLabel(a.isa_type) },
      ];
      rows.push({ key: 'Interest', value: 'Tax-free' });
      return rows;
    },
  },
  async created() { await this.load(); },
  methods: {
    fmt(v) { return formatCurrency(v); },
    rate(r) {
      if (r == null || isNaN(Number(r))) return '—';
      return `${Number(r).toFixed(2)}%`;
    },
    accountTypeLabel(t) { return ACCOUNT_TYPES[t] || (t ? String(t).replace(/_/g, ' ') : '—'); },
    accessTypeLabel(t) { return ACCESS_TYPES[t] || (t ? String(t).replace(/_/g, ' ') : '—'); },
    isaTypeLabel(t) { return ISA_TYPES[t] || (t ? String(t).replace(/_/g, ' ') : '—'); },
    ownershipLabel(t) {
      const map = { individual: 'Individual', joint: 'Joint', tenants_in_common: 'Tenants in common', trust: 'Trust' };
      return map[t] || t;
    },
    fmtDate(d) {
      if (!d) return '—';
      const parsed = new Date(d);
      if (isNaN(parsed.getTime())) return '—';
      return parsed.toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit', year: 'numeric' });
    },
    timeToMaturity(d) {
      if (!d) return '—';
      const maturity = new Date(d);
      if (isNaN(maturity.getTime())) return '—';
      const diffDays = Math.ceil((maturity - new Date()) / (1000 * 60 * 60 * 24));
      if (diffDays <= 0) return 'Matured';
      if (diffDays < 31) return `${diffDays} days`;
      const months = Math.ceil(diffDays / 30.44);
      const years = Math.floor(months / 12);
      const rem = months % 12;
      if (years === 0) return `${rem} ${rem === 1 ? 'month' : 'months'}`;
      if (rem === 0) return `${years} ${years === 1 ? 'year' : 'years'}`;
      return `${years} ${years === 1 ? 'year' : 'years'}, ${rem} ${rem === 1 ? 'month' : 'months'}`;
    },
    goBack() { this.$router.push('/savings'); },
    async load() {
      this.loading = true;
      this.error = '';
      this.account = null;
      try {
        const { ok, status, data } = await apiGet(`/api/savings/accounts/${this.accountId}`, store.token);
        if (handleAuthExpiry({ status }, this.$router)) return;
        if (ok) {
          this.account = data?.data || data || null;
          if (this.account?.is_isa) await this.loadIsaStatus();
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
.msa-tags { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 12px; }
.msa-tag { font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: var(--radius-sm); }
.msa-tag--isa { color: var(--violet-500); background: color-mix(in srgb, var(--violet-500) 12%, var(--white)); }
.msa-tag--ef { color: var(--spring-600); background: color-mix(in srgb, var(--spring-500) 12%, var(--white)); }
.msa-years { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 12px; }
.msa-year { border: 1px solid var(--horizon-200); border-radius: var(--radius-sm); background: var(--white); padding: 5px 9px; color: var(--horizon-500); font-size: 12px; font-weight: 700; }
.msa-year--active { border-color: var(--violet-500); background: var(--light-blue-100); color: var(--violet-500); }
</style>
