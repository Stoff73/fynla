<template>
  <MobileChrome title="Investments" subtitle="Your investment accounts, holdings and allowances" :loading="loading" loading-label="your investments" :edit-prompt="editPrompt">
    <div v-if="loading" class="m-card m-state">
      <p class="m-sub">Loading your investments…</p>
    </div>

    <div v-else-if="error" class="m-card m-state">
      <p class="m-err">{{ error }}</p>
      <button class="m-btn" @click="load">Try again</button>
    </div>

    <template v-else>
      <!-- Portfolio value hero -->
      <div class="m-card m-hero">
        <p class="m-sub m-label">Total portfolio value</p>
        <p class="m-metric">{{ fmt(totalValue) }}</p>
        <p class="m-hero-sub">{{ accountCountLabel }}</p>
      </div>

      <!-- Risk profile -->
      <div v-if="riskLabel" class="m-card">
        <p class="m-section-label" style="margin-top:0">Risk profile</p>
        <div class="mi-row">
          <span class="mi-row__label">Attitude to risk</span>
          <span class="mi-row__value">{{ riskLabel }}</span>
        </div>
      </div>

      <!-- Accounts list -->
      <div class="m-card">
        <div class="m-cap-head" style="margin-top:0">
          <p class="m-section-label">Investment accounts</p>
          <div v-if="accountLimit" class="m-cap">
            <span class="m-cap__count" :class="{ 'm-cap__count--full': atCap }">{{ accountCount }} of {{ accountLimit }} accounts used</span>
            <button v-if="paidUpgradeAvailable" type="button" class="m-cap__upgrade" @click="goUpgrade">Upgrade</button>
          </div>
        </div>
        <p v-if="!accounts.length" class="m-sub" style="margin-bottom:0">
          You haven't added any investment accounts yet.
        </p>
        <div v-else>
          <button
            v-for="acct in accounts"
            :key="acct.id"
            type="button"
            class="mi-acct"
            @click="openAccount(acct.id)"
          >
            <div class="mi-acct__main">
              <span class="mi-acct__provider">{{ acct.provider || acct.platform || 'Investment account' }}</span>
              <span class="mi-acct__meta">
                <span v-if="isIsa(acct) && !typeMentionsIsa(acct)" class="mi-acct__tag">ISA</span>
                <span class="mi-acct__type">{{ accountTypeLabel(acct) }}</span>
                <span v-if="holdingCount(acct)" class="mi-acct__holdings">{{ holdingCount(acct) }} {{ holdingCount(acct) === 1 ? 'holding' : 'holdings' }}</span>
              </span>
            </div>
            <div class="mi-acct__right">
              <span class="mi-acct__value">{{ fmt(acct.current_value) }}</span>
              <span class="mi-acct__view">View</span>
            </div>
          </button>
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
import { upgradeMixin } from '../../mixins/upgrade.js';
import { buildEditPrompt } from '../../utils/editPrompt.js';

export default {
  name: 'MobileInvestment',
  components: { MobileChrome },
  mixins: [upgradeMixin],
  data: () => ({ loading: true, error: '', payload: null }),
  computed: {
    accounts() { return this.payload?.accounts || []; },
    // Free-tier cap nudge (5.1). account_limit null = unlimited tier → hide nudge.
    accountCount() { return this.payload?.account_count ?? this.accounts.length; },
    accountLimit() { return this.payload?.account_limit ?? null; },
    atCap() { return this.accountLimit != null && this.accountCount >= this.accountLimit; },
    editPrompt() {
      return buildEditPrompt('investments', "I'd like to add an investment account.",
        this.accounts.map((a) => a.provider || a.platform || 'Investment account'));
    },
    riskProfile() { return this.payload?.risk_profile || null; },
    riskLabel() {
      const r = this.riskProfile?.risk_category || this.riskProfile?.attitude_to_risk || this.riskProfile?.risk_level;
      if (!r) return '';
      return String(r).replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
    },
    totalValue() {
      return this.accounts.reduce((sum, a) => sum + (Number(a.current_value) || 0), 0);
    },
    accountCountLabel() {
      const n = this.accounts.length;
      if (n === 0) return 'No accounts added yet.';
      return `Across ${n} ${n === 1 ? 'account' : 'accounts'}.`;
    },
  },
  async created() {
    await this.load();
    // Same-route verify refresh: the onboarding chat bumps this after
    // applying an edit on this very screen — refetch so the page shows the
    // just-edited figures (no remount happens without a route change).
    this.$watch(() => store.screenRefreshTick, () => { this.load(); });
  },
  methods: {
    fmt(v) { return formatCurrency(v); },
    accountTypeLabel(a) { return accountTypeLabel(a); },
    isIsa(a) { return isIsaAccount(a); },
    typeMentionsIsa(a) { return accountTypeLabel(a).toLowerCase().includes('isa'); },
    holdingCount(a) { return Array.isArray(a.holdings) ? a.holdings.length : 0; },
    openAccount(id) { this.$router.push(`/investment/account/${id}`); },
    goBack() { this.$router.push({ name: 'dashboard' }); },
    async load() {
      this.loading = true;
      this.error = '';
      this.payload = null;
      try {
        const { ok, data } = await apiGet('/api/investment', store.token);
        if (ok) this.payload = data?.data || data || {};
        else this.error = data?.message || 'We could not load your investments.';
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
.mi-row { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; padding: 4px 0; }
.mi-row__label { font-size: 13px; color: var(--neutral-500); }
.mi-row__value { font-size: 14px; font-weight: 700; color: var(--horizon-500); }

.mi-acct { display: flex; align-items: center; justify-content: space-between; gap: 12px; width: 100%; text-align: left; background: transparent; border: 0; border-bottom: 1px solid var(--light-gray); padding: 14px 0; cursor: pointer; }
.mi-acct:last-child { border-bottom: 0; padding-bottom: 0; }
.mi-acct:first-child { padding-top: 4px; }
.mi-acct:active { opacity: 0.7; }
.mi-acct__main { min-width: 0; flex: 1; }
.mi-acct__provider { display: block; font-size: 15px; font-weight: 700; color: var(--horizon-500); }
.mi-acct__meta { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; margin-top: 4px; }
.mi-acct__tag { font-size: 11px; font-weight: 700; color: var(--violet-500); background: color-mix(in srgb, var(--violet-500) 12%, var(--white)); padding: 1px 7px; border-radius: var(--radius-sm); }
.mi-acct__type { font-size: 12px; color: var(--neutral-500); }
.mi-acct__holdings { font-size: 12px; color: var(--neutral-500); }
.mi-acct__right { display: flex; flex-direction: column; align-items: flex-end; gap: 2px; flex-shrink: 0; }
.mi-acct__value { font-size: 15px; font-weight: 700; color: var(--horizon-500); white-space: nowrap; }
.mi-acct__view { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--raspberry-500); }
</style>
