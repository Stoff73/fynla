<template>
  <MobileChrome>
    <div class="m-card m-detail-header">
      <button class="m-back" @click="goBack" aria-label="Back to investments">Back</button>
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

      <!-- Holdings -->
      <div class="m-card">
        <p class="m-section-label" style="margin-top:0">Holdings</p>
        <p v-if="!holdings.length" class="m-sub" style="margin-bottom:0">
          No individual holdings recorded for this account.
        </p>
        <div v-else>
          <div v-for="(h, i) in holdings" :key="h.id || i" class="mid-holding">
            <div class="mid-holding__top">
              <span class="mid-holding__name">{{ h.security_name || h.ticker || 'Holding' }}</span>
              <span class="mid-holding__value">{{ fmt(h.current_value) }}</span>
            </div>
            <div class="mid-holding__sub">
              <span class="mid-holding__meta">
                <span v-if="h.allocation_percent != null">{{ pct(h.allocation_percent) }} of account</span>
                <span v-if="assetTypeLabel(h)">{{ assetTypeLabel(h) }}</span>
              </span>
              <span v-if="h.gain_loss != null" class="mid-holding__gl" :class="h.gain_loss >= 0 ? 'mid-holding__gl--up' : 'mid-holding__gl--down'">
                {{ h.gain_loss >= 0 ? '+' : '' }}{{ fmt(h.gain_loss) }}<template v-if="h.gain_loss_percent != null"> ({{ pct(h.gain_loss_percent) }})</template>
              </span>
            </div>
          </div>
        </div>
      </div>
    </template>
  </MobileChrome>
</template>

<script>
import { store } from '../../store.js';
import { apiGet } from '../../api.js';
import { formatCurrency, accountTypeLabel, isIsaAccount } from './investmentFormat.js';
import MobileChrome from '../../components/MobileChrome.vue';

function capitalise(s) {
  if (!s) return '';
  return String(s).replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

export default {
  name: 'MobileInvestmentAccountDetail',
  components: { MobileChrome },
  data: () => ({ loading: true, error: '', accounts: [] }),
  computed: {
    accountId() { return this.$route.params.id; },
    account() {
      return this.accounts.find((a) => String(a.id) === String(this.accountId)) || null;
    },
    holdings() {
      return Array.isArray(this.account?.holdings) ? this.account.holdings : [];
    },
    infoRows() {
      const a = this.account;
      if (!a) return [];
      const rows = [
        { label: 'Provider', value: a.provider || '—' },
        { label: 'Platform', value: a.platform || '—' },
        { label: 'Account type', value: accountTypeLabel(a) },
        { label: 'Ownership', value: capitalise(a.ownership_type) || 'Individual' },
        { label: 'Country', value: a.country === 'UK' ? 'United Kingdom' : (a.country || 'United Kingdom') },
      ];
      if (a.monthly_contribution_amount) {
        rows.push({ label: 'Monthly contribution', value: this.fmt(a.monthly_contribution_amount) });
      }
      if (isIsaAccount(a) && a.isa_subscription_current_year != null) {
        rows.push({ label: 'ISA subscribed this year', value: this.fmt(a.isa_subscription_current_year) });
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
        const { ok, data } = await apiGet('/api/investment', store.token);
        if (ok) this.accounts = (data?.data || data || {}).accounts || [];
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
.mid-row { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--horizon-100); }
.mid-row:last-child { border-bottom: 0; }
.mid-row__key { font-size: 13px; color: var(--neutral-500); }
.mid-row__val { font-size: 14px; font-weight: 700; color: var(--horizon-500); text-align: right; }

.mid-holding { padding: 12px 0; border-bottom: 1px solid var(--horizon-100); }
.mid-holding:first-child { padding-top: 4px; }
.mid-holding:last-child { border-bottom: 0; padding-bottom: 0; }
.mid-holding__top { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; }
.mid-holding__name { font-size: 14px; font-weight: 700; color: var(--horizon-500); }
.mid-holding__value { font-size: 14px; font-weight: 700; color: var(--horizon-500); white-space: nowrap; }
.mid-holding__sub { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; margin-top: 4px; }
.mid-holding__meta { display: flex; flex-wrap: wrap; gap: 8px; font-size: 12px; color: var(--neutral-500); }
.mid-holding__gl { font-size: 12px; font-weight: 700; white-space: nowrap; }
.mid-holding__gl--up { color: var(--spring-600); }
.mid-holding__gl--down { color: var(--raspberry-500); }
</style>
