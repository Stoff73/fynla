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
        <p v-if="teaser.unmodelled_relief_caveat" class="me-caveat">{{ teaser.unmodelled_relief_caveat }}</p>
        <!--
          W-0467, compliance-lead finding D (2026-08-24): "personalised" is precisely
          the word separating generic guidance from a personal recommendation, and
          Fynla is not FCA-authorised. It appeared here and twice in the headline —
          one claim with two homes, so both were changed together (Rule 20).
        -->
        <p class="me-note">Full estate planning — assets, gifts, trusts, will and Inheritance Tax planning tools — is part of Premium.</p>
        <!-- Rule 3: the only next step offered to a household facing a large bill was a purchase. -->
        <p class="me-note" style="margin-top:8px">For an estate of this size it is worth discussing your position with a regulated financial adviser or a specialist solicitor.</p>
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
        <button type="button" class="me-row me-row--link" @click="openBequests">
          <span class="me-row__label">Specific bequests</span>
          <span class="me-row__right">
            <span class="me-row__value">{{ bequests.length }} {{ bequests.length === 1 ? 'bequest' : 'bequests' }}</span>
            <span class="me-row__view">View</span>
          </span>
        </button>
      </div>

      <!--
        W-0469. This screen shows an estate value and a composition and NO
        Inheritance Tax calculation — no allowances, no business relief, no tax on
        failed gifts. CSJ's decision (2026-08-23) is that it stays a summary and
        says so, rather than rendering a subset of the web breakdown and letting a
        reader mistake the part for the whole.

        No W-0466 caveat here: this card shows no Inheritance Tax figure, so there
        is nothing on it to qualify. The caveat lives where a figure does — the web
        breakdown, and the Free teaser at the top of this file, which is the only
        Inheritance Tax number `/m` ever prints.
      -->
      <div class="m-card">
        <p class="m-section-label" style="margin-top:0">Your Inheritance Tax calculation</p>
        <p class="me-note">The full breakdown — your allowances, any business relief, and tax on gifts made in the last seven years — is on the web app.</p>
        <button type="button" class="m-btn" style="margin-top:16px" @click="openIhtOnWeb">Open on the web app</button>
        <p v-if="handoffError" class="m-err" style="margin-top:12px">{{ handoffError }}</p>
      </div>
    </template>
  </MobileChrome>
</template>

<script>
import { store } from '../../store.js';
import { apiGet } from '../../api.js';
import { handleAuthExpiry } from '../../authExpiry.js';
import MobileChrome from '../../components/MobileChrome.vue';
import { upgradeMixin } from '../../mixins/upgrade.js';
import { issueWebHandoff } from '../../navigation/webHandoff.js';

function formatCurrency(value) {
  if (value == null || value === '' || isNaN(Number(value))) return '—';
  return new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP', maximumFractionDigits: 0 }).format(Number(value));
}

const COMP_LABELS = { property: 'Property', investment: 'Investments', cash: 'Cash & savings', pension: 'Pensions', business: 'Business interests', chattel: 'Possessions', other: 'Other assets' };

export default {
  name: 'MobileEstate',
  components: { MobileChrome },
  mixins: [upgradeMixin],
  data: () => ({ loading: true, error: '', mode: '', teaser: {}, payload: null, netWorth: null, bequests: [], handoffError: '' }),
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
    openBequests() { this.$router.push({ name: 'm-estate-bequests' }); },
    async openIhtOnWeb() {
      this.handoffError = '';
      try {
        await issueWebHandoff('estate_iht');
      } catch {
        this.handoffError = 'We could not open the web app just now. Please try again.';
      }
    },
    async load() {
      this.loading = true;
      this.error = '';
      this.mode = '';
      this.teaser = {};
      this.payload = null;
      this.netWorth = null;
      this.bequests = [];
      this.handoffError = '';
      try {
        const { ok, status, data } = await apiGet('/api/estate', store.token);
        if (handleAuthExpiry({ status }, this.$router)) return;
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
          if (handleAuthExpiry(nw, this.$router)) return;
          if (nw.ok) this.netWorth = nw.data?.data?.net_worth || nw.data?.net_worth || nw.data?.data || null;
          // /api/estate's will_info carries no bequests, so the count for the
          // drill-down row comes from the same endpoint the bequests screen uses.
          const bq = await apiGet('/api/estate/bequests', store.token);
          if (handleAuthExpiry(bq, this.$router)) return;
          if (bq.ok) this.bequests = bq.data?.data || [];
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
/* W-0466 F8 — `--violet-800` is not defined in resources/mobile/style.css, which
   declares only `--violet-400` and `--violet-500`; the mobile bundle carries its
   own tokens and does not inherit the web palette, so the text fell back to the
   browser default. `--eggshell-500` IS defined (style.css:39) and is unchanged. */
.me-caveat { font-size: 13px; color: var(--violet-500); line-height: 1.5; margin: 0 0 12px; background: var(--eggshell-500); border-radius: 8px; padding: 12px; }
.me-row { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--light-gray); }
.me-row:first-of-type { padding-top: 4px; }
.me-row:last-of-type { border-bottom: 0; padding-bottom: 0; }
.me-row--total { border-top: 1px solid var(--horizon-200); border-bottom: 0; margin-top: 2px; padding-top: 12px; }
.me-row__label { font-size: 14px; color: var(--neutral-600); }
.me-row--total .me-row__label { font-weight: 700; color: var(--horizon-500); }
.me-row__value { font-size: 14px; font-weight: 700; color: var(--horizon-500); white-space: nowrap; }
.me-row--total .me-row__value { font-size: 16px; }
.me-row--link { width: 100%; background: none; border: 0; border-bottom: 1px solid var(--light-gray); font: inherit; text-align: left; cursor: pointer; }
.me-row--link:last-of-type { border-bottom: 0; padding-bottom: 0; }
.me-row__right { display: flex; align-items: baseline; gap: 10px; }
.me-row__view { font-size: 12px; font-weight: 700; color: var(--raspberry-500); }
.me-row__value--ok { color: var(--spring-600); }
.me-row__value--warn { color: var(--violet-500); }
</style>
