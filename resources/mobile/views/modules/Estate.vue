<template>
  <MobileChrome title="Estate" subtitle="Inheritance tax exposure and planning" :loading="loading" loading-label="your estate">
    <div v-if="loading" class="m-card m-state">
      <p class="m-sub">Loading your estate position…</p>
    </div>

    <div v-else-if="error" class="m-card m-state">
      <p class="m-err">{{ error }}</p>
      <button class="m-btn" @click="load">Try again</button>
    </div>

    <!-- Teaser mode (Free) -->
    <template v-else-if="mode === 'teaser'">
      <div class="m-card m-hero">
        <p class="m-sub m-label">Estimated Inheritance Tax liability</p>
        <p class="m-metric">{{ fmt(teaser.estimated_liability_gbp) }}</p>
      </div>
      <div class="m-card">
        <p v-if="teaser.headline" class="m-sub" style="margin:0 0 12px">{{ teaser.headline }}</p>
        <p class="me-note">Full estate planning — assets, gifts, trusts, will and personalised Inheritance Tax planning — is part of Premium.</p>
        <button v-if="paidUpgradeAvailable" type="button" class="m-btn" style="margin-top:16px" @click="goUpgrade">Compare plans</button>
      </div>
    </template>

    <!-- Full mode (Premium) -->
    <template v-else>
      <div class="m-card m-hero">
        <p class="m-sub m-label">Estimated estate value</p>
        <p class="m-metric">{{ fmt(netEstate) }}</p>
        <p class="m-hero-sub">{{ fmt(totalAssets) }} in assets, less {{ fmt(totalLiabilities) }} of liabilities.</p>
      </div>

      <div class="m-card">
        <p class="m-section-label" style="margin-top:0">Estate breakdown</p>
        <div v-for="comp in assetComposition" :key="comp.type" class="me-row">
          <span class="me-row__label">{{ compLabel(comp.type) }}</span>
          <span class="me-row__value">{{ fmt(comp.value) }}</span>
        </div>
        <div class="me-row">
          <span class="me-row__label">Liabilities</span>
          <span class="me-row__value">{{ fmt(totalLiabilities) }}</span>
        </div>
        <div class="me-row me-row--total">
          <span class="me-row__label">Net estate</span>
          <span class="me-row__value">{{ fmt(netEstate) }}</span>
        </div>
      </div>

      <div class="m-card">
        <p class="m-section-label" style="margin-top:0">Estate planning</p>
        <div class="me-row">
          <span class="me-row__label">Lifetime gifts</span>
          <span class="me-row__value">{{ gifts.length }} {{ gifts.length === 1 ? 'gift' : 'gifts' }}</span>
        </div>
        <div class="me-row">
          <span class="me-row__label">Trusts</span>
          <span class="me-row__value">{{ trusts.length }} {{ trusts.length === 1 ? 'trust' : 'trusts' }}</span>
        </div>
        <div class="me-row">
          <span class="me-row__label">Will</span>
          <span class="me-row__value" :class="willInPlace ? 'me-row__value--ok' : 'me-row__value--warn'">{{ willInPlace ? 'In place' : 'Not set up' }}</span>
        </div>
      </div>
    </template>
  </MobileChrome>
</template>

<script>
import { store } from '../../store.js';
import { apiGet } from '../../api.js';
import MobileChrome from '../../components/MobileChrome.vue';
import { upgradeMixin } from '../../mixins/upgrade.js';

function formatCurrency(value) {
  if (value == null || value === '' || isNaN(Number(value))) return '—';
  return new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP', maximumFractionDigits: 0 }).format(Number(value));
}

const COMP_LABELS = { property: 'Property', investment: 'Investments', cash: 'Cash & savings', pension: 'Pensions', business: 'Business interests', chattel: 'Possessions', other: 'Other assets' };

export default {
  name: 'MobileEstate',
  components: { MobileChrome },
  mixins: [upgradeMixin],
  data: () => ({ loading: true, error: '', mode: '', teaser: {}, payload: null, netWorth: null }),
  computed: {
    gifts() { return this.payload?.gifts || []; },
    trusts() { return this.payload?.trusts || []; },
    willInfo() { return this.payload?.will_info || null; },
    willInPlace() { return !!(this.willInfo && this.willInfo.has_will); },
    // Estate value comes from the authoritative estate net-worth endpoint
    // (includes property/pensions, which /api/estate's `assets` collection omits).
    assetComposition() { return this.netWorth?.asset_composition || []; },
    totalAssets() { return Number(this.netWorth?.total_assets ?? 0); },
    totalLiabilities() { return Number(this.netWorth?.total_liabilities ?? 0); },
    netEstate() { return Number(this.netWorth?.net_worth ?? (this.totalAssets - this.totalLiabilities)); },
  },
  async created() { await this.load(); },
  methods: {
    fmt(v) { return formatCurrency(v); },
    compLabel(type) { return COMP_LABELS[type] || (type ? type.charAt(0).toUpperCase() + type.slice(1) : 'Assets'); },
    goBack() { this.$router.push({ name: 'dashboard' }); },
    async load() {
      this.loading = true;
      this.error = '';
      this.mode = '';
      this.teaser = {};
      this.payload = null;
      this.netWorth = null;
      try {
        const { ok, data } = await apiGet('/api/estate', store.token);
        if (!ok) {
          this.error = data?.message || 'We could not load your estate position.';
          return;
        }
        this.mode = data?.mode || (data?.data ? 'full' : 'teaser');
        if (this.mode === 'teaser') {
          this.teaser = data?.teaser || {};
        } else {
          this.payload = data?.data || {};
          // Pull the authoritative estate net-worth for the headline figures.
          const nw = await apiGet('/api/estate/net-worth', store.token);
          if (nw.ok) this.netWorth = nw.data?.data?.net_worth || nw.data?.net_worth || nw.data?.data || null;
        }
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
.me-note { font-size: 13px; color: var(--neutral-600); line-height: 1.5; margin: 0; }
.me-row { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--light-gray); }
.me-row:first-of-type { padding-top: 4px; }
.me-row:last-of-type { border-bottom: 0; padding-bottom: 0; }
.me-row--total { border-top: 1px solid var(--horizon-200); border-bottom: 0; margin-top: 2px; padding-top: 12px; }
.me-row__label { font-size: 14px; color: var(--neutral-600); }
.me-row--total .me-row__label { font-weight: 700; color: var(--horizon-500); }
.me-row__value { font-size: 14px; font-weight: 700; color: var(--horizon-500); white-space: nowrap; }
.me-row--total .me-row__value { font-size: 16px; }
.me-row__value--ok { color: var(--spring-600); }
.me-row__value--warn { color: var(--violet-500); }
</style>
