<template>
  <MobileChrome title="Net Worth" subtitle="Everything you own, less what you owe" :loading="loading" loading-label="your net worth">
    <div v-if="loading" class="m-card m-state">
      <p class="m-sub">Loading your net worth…</p>
    </div>

    <div v-else-if="error" class="m-card m-state">
      <p class="m-err">{{ error }}</p>
      <button class="m-btn" @click="load">Try again</button>
    </div>

    <template v-else>
      <!-- Net worth hero -->
      <div class="m-card m-hero">
        <p class="m-sub m-label">Total net worth</p>
        <p class="m-metric">{{ fmt(netWorth) }}</p>
        <p class="m-hero-sub">{{ fmt(totalAssets) }} in assets, less {{ fmt(totalLiabilities) }} of liabilities.</p>
      </div>

      <p v-if="hasDbPensions" class="m-sub mnw-note">
        Defined Benefit pensions are excluded from net worth — they provide a guaranteed income rather than accessible capital.
      </p>

      <!-- Assets -->
      <div class="m-card">
        <p class="m-section-label" style="margin-top:0">Assets</p>
        <button
          v-for="cat in assetCategories"
          :key="cat.key"
          class="mnw-cat"
          @click="openCategory(cat.key)"
        >
          <div class="mnw-cat__main">
            <span class="mnw-cat__name">{{ cat.label }}</span>
            <span class="mnw-cat__meta">{{ cat.count }} {{ cat.count === 1 ? 'item' : 'items' }}, {{ cat.pct }}% of assets</span>
          </div>
          <div class="mnw-cat__right">
            <span class="mnw-cat__value">{{ fmt(cat.value) }}</span>
            <span class="mnw-cat__view">View</span>
          </div>
        </button>
        <p v-if="!assetCategories.length" class="m-sub" style="margin-bottom:0">No assets recorded yet.</p>
      </div>

      <!-- Liabilities -->
      <div class="m-card">
        <p class="m-section-label" style="margin-top:0">Liabilities</p>
        <button
          class="mnw-cat"
          @click="openCategory('liabilities')"
        >
          <div class="mnw-cat__main">
            <span class="mnw-cat__name">Liabilities</span>
            <span class="mnw-cat__meta">Mortgages, loans and other debts</span>
          </div>
          <div class="mnw-cat__right">
            <span class="mnw-cat__value mnw-cat__value--debt">{{ fmt(totalLiabilities) }}</span>
            <span class="mnw-cat__view">View</span>
          </div>
        </button>
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

const ASSET_LABELS = {
  property: 'Property',
  investments: 'Investments',
  pensions: 'Pensions',
  cash: 'Cash & savings',
  business: 'Business interests',
  chattels: 'Possessions',
};
const ASSET_ORDER = ['property', 'investments', 'pensions', 'cash', 'business', 'chattels'];

export default {
  name: 'MobileNetWorth',
  components: { MobileChrome },
  data: () => ({ loading: true, error: '', overview: null, detailed: null }),
  computed: {
    netWorth() { return this.overview?.net_worth ?? 0; },
    totalAssets() { return this.overview?.total_assets ?? 0; },
    totalLiabilities() { return this.overview?.total_liabilities ?? 0; },
    hasDbPensions() { return !!this.overview?.has_db_pensions; },
    assetCategories() {
      const detailed = this.detailed || {};
      const cats = [];
      for (const key of ASSET_ORDER) {
        const section = detailed[key];
        const value = Number(section?.total_value ?? 0);
        const count = Number(section?.count ?? 0);
        if (!section || (value === 0 && count === 0)) continue;
        const pct = this.totalAssets > 0 ? Math.round((value / this.totalAssets) * 100) : 0;
        cats.push({ key, label: ASSET_LABELS[key] || key, value, count, pct });
      }
      return cats;
    },
  },
  async created() { await this.load(); },
  methods: {
    fmt(v) { return formatCurrency(v); },
    goBack() { this.$router.push({ name: 'dashboard' }); },
    openCategory(key) { this.$router.push(`/net-worth/${key}`); },
    async load() {
      this.loading = true;
      this.error = '';
      this.overview = null;
      this.detailed = null;
      try {
        const [ov, det] = await Promise.all([
          apiGet('/api/net-worth/overview', store.token),
          apiGet('/api/net-worth/assets-summary-detailed', store.token),
        ]);
        if (ov.ok) this.overview = ov.data?.data || ov.data || {};
        else this.error = ov.data?.message || 'We could not load your net worth.';
        if (det.ok) this.detailed = det.data?.data || det.data || {};
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
.mnw-note { margin: -4px 4px 14px; font-size: 13px; line-height: 1.5; color: var(--neutral-600); }
.mnw-cat {
  display: flex; align-items: center; justify-content: space-between; gap: 12px;
  width: 100%; text-align: left; background: transparent; border: 0; cursor: pointer;
  padding: 12px 0; border-bottom: 1px solid var(--horizon-200);
}
.mnw-cat:first-of-type { padding-top: 4px; }
.mnw-cat:last-of-type { border-bottom: 0; padding-bottom: 0; }
.mnw-cat:active { opacity: 0.7; }
.mnw-cat__main { min-width: 0; }
.mnw-cat__name { display: block; font-size: 15px; font-weight: 700; color: var(--horizon-500); }
.mnw-cat__meta { display: block; font-size: 12px; color: var(--neutral-500); margin-top: 2px; }
.mnw-cat__right { display: flex; flex-direction: column; align-items: flex-end; gap: 2px; flex-shrink: 0; }
.mnw-cat__value { font-size: 15px; font-weight: 700; color: var(--horizon-500); white-space: nowrap; }
.mnw-cat__value--debt { color: var(--raspberry-500); }
.mnw-cat__view { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--raspberry-500); }
</style>
