<template>
  <MobileChrome
    title="Holistic Plan"
    subtitle="Your whole plan, ranked against what you can afford"
    :loading="loading"
    loading-label="your holistic plan"
  >
    <!-- Premium gate (/m freemium 5.3) — Holistic Plan is Tier 2+ -->
    <div v-if="upgradeLocked" class="m-card m-state">
      <p class="m-section-label" style="margin-top:0">A premium feature</p>
      <p class="m-sub">Your Holistic Plan brings every module together into one plan, ranked against what you can afford. It's part of Tier 2 and above.</p>
      <button class="m-btn" @click="goUpgrade">Upgrade your plan</button>
    </div>

    <div v-else-if="error" class="m-card m-state">
      <p class="m-err">{{ error }}</p>
      <button class="m-btn" @click="load">Try again</button>
    </div>

    <template v-else>
      <!-- Surplus hero -->
      <div class="m-card m-hero">
        <p class="m-sub m-label">Left to put to work each month</p>
        <p class="m-metric">{{ fmt(effectiveSurplus) }}</p>
        <p class="m-hero-sub">{{ surplusContext }}</p>
      </div>

      <!-- Ranked actions -->
      <div class="m-card">
        <p class="m-section-label" style="margin-top:0">Your ranked actions</p>
        <p v-if="!items.length" class="m-sub" style="margin-bottom:0">
          No cross-module actions just yet. Add more detail to your modules to build your plan.
        </p>
        <article
          v-for="(item, i) in items"
          :key="item.module + '_' + item.type + '_' + i"
          class="mhp-rec"
          :class="`mhp-rec--${item.affordability}`"
        >
          <div class="mhp-rec__top">
            <div class="mhp-rec__badges">
              <span class="mhp-rec__priority" :class="`mhp-rec__priority--${item.priority}`">{{ priorityLabel(item.priority) }}</span>
              <span class="mhp-rec__module">{{ moduleLabel(item.module) }}</span>
            </div>
            <span v-if="hasMonthlyCost(item)" class="mhp-rec__cost">
              {{ fmt(item.required_monthly_cost) }}<span class="mhp-rec__cost-cap">/mo</span>
            </span>
          </div>
          <h3 class="mhp-rec__title">{{ item.title }}</h3>
          <p v-if="item.description" class="mhp-rec__desc">{{ item.description }}</p>
          <p v-if="item.conflict_note" class="mhp-rec__conflict">{{ item.conflict_note }}</p>
          <p class="mhp-rec__afford" :class="`mhp-rec__afford--${item.affordability}`">{{ affordabilityLabel(item.affordability) }}</p>
        </article>
      </div>

      <!-- Locked strategies — surfaced, never dropped -->
      <div v-if="locked.length" class="m-card">
        <p class="m-section-label" style="margin-top:0">Unlock more of your plan</p>
        <p class="m-sub">These strategies need a little more detail before we can rank them.</p>
        <p v-for="(lock, i) in locked" :key="lock.module + '_' + lock.strategy_type + '_' + i" class="mhp-lock">
          <span class="mhp-lock__module">{{ moduleLabel(lock.module) }}:</span> {{ strategyLabel(lock.strategy_type) }}
        </p>
      </div>
    </template>
  </MobileChrome>
</template>

<script>
import { store } from '../store.js';
import { apiGet } from '../api.js';
import MobileChrome from '../components/MobileChrome.vue';
import { upgradeMixin } from '../mixins/upgrade.js';

function formatCurrency(value) {
  if (value == null || value === '' || isNaN(Number(value))) return '—';
  return new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP', maximumFractionDigits: 0 }).format(Number(value));
}

// Spell out embedded acronyms (Rule #9 — only ISA may stay abbreviated).
const ACRONYM_EXPANSIONS = {
  aa: 'Annual Allowance',
  mpaa: 'Money Purchase Annual Allowance',
  gia: 'General Investment Account',
  psa: 'Personal Savings Allowance',
  isa: 'ISA',
  cgt: 'Capital Gains Tax',
  iht: 'Inheritance Tax',
  nrb: 'Nil-Rate Band',
  rnrb: 'Residence Nil-Rate Band',
  topup: 'top-up',
};

const MODULE_LABELS = {
  tax: 'Tax',
  retirement: 'Retirement',
  savings: 'Savings',
  investment: 'Investment',
  protection: 'Protection',
  estate: 'Estate',
};

const AFFORDABILITY_LABELS = {
  fits: 'Fits within your surplus',
  partially_fits: 'Partially fits',
  beyond_current_surplus: 'Beyond your current surplus',
};

export default {
  name: 'MobileHolisticPlan',
  components: { MobileChrome },
  mixins: [upgradeMixin],
  data: () => ({ loading: true, error: '', upgradeLocked: false, plan: null }),
  computed: {
    items() { return Array.isArray(this.plan?.items) ? this.plan.items : []; },
    locked() { return Array.isArray(this.plan?.locked) ? this.plan.locked : []; },
    availableSurplus() { return Number(this.plan?.available_monthly_surplus) || 0; },
    goalCommitments() { return Number(this.plan?.goal_commitments) || 0; },
    effectiveSurplus() { return Number(this.plan?.effective_surplus) || 0; },
    nearTermCapital() { return Number(this.plan?.near_term_capital) || 0; },
    surplusContext() {
      let text = `From a ${this.fmt(this.availableSurplus)} monthly surplus`;
      if (this.goalCommitments > 0) {
        text += `, after ${this.fmt(this.goalCommitments)} committed to your goals`;
      }
      text += '.';
      if (this.nearTermCapital > 0) {
        text += ` Plus ${this.fmt(this.nearTermCapital)} of capital expected in the next 12 months.`;
      } else if (this.nearTermCapital < 0) {
        text += ` ${this.fmt(Math.abs(this.nearTermCapital))} of expected outgoings is kept in reserve.`;
      }
      return text;
    },
  },
  async created() { await this.load(); },
  methods: {
    fmt(v) { return formatCurrency(v); },
    priorityLabel(priority) { return ({ high: 'High', medium: 'Medium', low: 'Low' })[priority] || 'Medium'; },
    moduleLabel(module) {
      return MODULE_LABELS[module] || (module ? module.charAt(0).toUpperCase() + module.slice(1) : '');
    },
    affordabilityLabel(affordability) { return AFFORDABILITY_LABELS[affordability] || AFFORDABILITY_LABELS.fits; },
    hasMonthlyCost(item) {
      return item.required_monthly_cost != null && Number(item.required_monthly_cost) > 0;
    },
    strategyLabel(strategyType) {
      if (!strategyType) return '';
      return strategyType
        .split('_')
        .map((word) => ACRONYM_EXPANSIONS[word] || word)
        .join(' ')
        .replace(/^./, (char) => char.toUpperCase());
    },
    async load() {
      this.loading = true;
      this.error = '';
      this.upgradeLocked = false;
      this.plan = null;
      try {
        const { ok, status, data } = await apiGet('/api/holistic/composite-plan', store.token);
        if (ok) {
          this.plan = data?.data || {};
        } else if (status === 403 && data?.error === 'upgrade_required') {
          this.upgradeLocked = true;
        } else {
          this.error = data?.message || 'We could not load your holistic plan.';
        }
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
.mhp-rec { border: 1px solid var(--light-gray); border-radius: var(--radius-lg); padding: 16px; margin-bottom: 12px; background: var(--white); }
.mhp-rec:last-child { margin-bottom: 0; }
.mhp-rec--partially_fits { border-color: var(--violet-200); }
.mhp-rec--beyond_current_surplus { background: var(--eggshell-500); opacity: 0.85; }
.mhp-rec__top { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 8px; }
.mhp-rec__badges { display: flex; flex-wrap: wrap; gap: 6px; }
.mhp-rec__priority,
.mhp-rec__module { font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: var(--radius-sm); }
.mhp-rec__priority--high { color: var(--violet-800); background: var(--violet-100); }
.mhp-rec__priority--medium { color: var(--horizon-500); background: var(--savannah-100); }
.mhp-rec__priority--low { color: var(--spring-800); background: var(--spring-100); }
.mhp-rec__module { color: var(--horizon-500); background: var(--horizon-100); }
.mhp-rec__cost { text-align: right; flex-shrink: 0; font-size: 15px; font-weight: 700; color: var(--horizon-500); white-space: nowrap; }
.mhp-rec__cost-cap { font-size: 11px; font-weight: 400; color: var(--neutral-500); }
.mhp-rec__title { font-size: 15px; font-weight: 700; color: var(--horizon-500); line-height: 1.3; }
.mhp-rec__desc { font-size: 13px; color: var(--neutral-600); line-height: 1.5; margin-top: 4px; }
.mhp-rec__conflict { font-size: 12px; color: var(--violet-700); margin-top: 6px; }
.mhp-rec__afford { font-size: 12px; font-weight: 700; margin-top: 8px; }
.mhp-rec__afford--fits { color: var(--spring-600); }
.mhp-rec__afford--partially_fits { color: var(--violet-500); }
.mhp-rec__afford--beyond_current_surplus { color: var(--neutral-500); }

.mhp-lock { font-size: 13px; color: var(--horizon-500); padding: 6px 0; border-bottom: 1px solid var(--horizon-100); }
.mhp-lock:last-child { border-bottom: 0; padding-bottom: 0; }
.mhp-lock__module { font-weight: 700; }
</style>
