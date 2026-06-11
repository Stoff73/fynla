<template>
  <MobileChrome title="Savings and emergency fund" subtitle="Your cash, emergency-fund runway and ISA allowance" :loading="loading" loading-label="this account">
    <div class="m-card m-detail-header">
      <button class="m-back" @click="goBack" aria-label="Back to savings">Back</button>
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
        <p v-if="isJoint" class="m-hero-sub">Your share ({{ account.ownership_percentage }}%): {{ fmt(userShare) }}</p>
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
  components: { MobileChrome },
  data: () => ({ loading: true, error: '', account: null }),
  computed: {
    accountId() { return this.$route.params.id; },
    headerTitle() {
      if (!this.account) return 'Account';
      return this.account.provider || this.account.institution || 'Savings account';
    },
    headerSub() {
      if (!this.account) return 'Account details';
      return this.accountTypeLabel(this.account.account_type);
    },
    isJoint() { return this.account?.ownership_type === 'joint'; },
    fullBalance() {
      return this.account?.full_balance ?? this.account?.current_balance ?? 0;
    },
    userShare() {
      if (this.account?.user_share != null) return this.account.user_share;
      if (this.isJoint && this.account?.ownership_percentage) {
        return this.fullBalance * (Number(this.account.ownership_percentage) / 100);
      }
      return this.fullBalance;
    },
    rateNum() { return Number(this.account?.interest_rate || 0); },
    annualInterest() {
      return Number(this.fullBalance) * (this.rateNum / 100);
    },
    monthlyInterest() { return this.annualInterest / 12; },
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
        rows.push({ key: `Your share (${this.account.ownership_percentage}%)`, value: this.fmt(this.userShare) });
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
      if (a.ownership_type) rows.push({ key: 'Ownership', value: this.ownershipLabel(a.ownership_type) });
      return rows;
    },
    isaRows() {
      const a = this.account;
      const rows = [
        { key: 'ISA type', value: this.isaTypeLabel(a.isa_type) },
      ];
      if (a.isa_subscription_year) rows.push({ key: 'Subscription year', value: a.isa_subscription_year });
      if (a.isa_subscription_amount != null) rows.push({ key: 'Subscribed this year', value: this.fmt(a.isa_subscription_amount) });
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
        const { ok, data } = await apiGet(`/api/savings/accounts/${this.accountId}`, store.token);
        if (ok) this.account = data?.data || data || null;
        else this.error = data?.message || 'We could not load this account.';
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
.msa-tags { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 12px; }
.msa-tag { font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: var(--radius-sm); }
.msa-tag--isa { color: var(--violet-500); background: color-mix(in srgb, var(--violet-500) 12%, var(--white)); }
.msa-tag--ef { color: var(--spring-600); background: color-mix(in srgb, var(--spring-500) 12%, var(--white)); }
</style>
