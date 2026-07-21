<template>
  <div>
    <div class="m-card m-detail-header">
      <button class="m-back" @click="goBack" aria-label="Back to dashboard">Back</button>
      <h1 class="m-h1">{{ meta.label }}</h1>
      <p class="m-sub">{{ meta.subtitle }}</p>
    </div>

    <div v-if="loading" class="m-card m-state">
      <p class="m-sub">Loading {{ meta.label.toLowerCase() }}…</p>
    </div>

    <div v-else-if="error" class="m-card m-state">
      <p class="m-err">{{ error }}</p>
      <button class="m-btn" @click="load">Try again</button>
    </div>

    <template v-else>
      <div class="m-card m-hero">
        <p class="m-sub m-label">{{ heroLabel }}</p>
        <p class="m-metric">{{ heroValue }}</p>
        <p v-if="heroSecondary" class="m-hero-sub">{{ heroSecondary }}</p>
      </div>

      <div v-if="rows.length" class="m-card m-detail-rows">
        <p class="m-section-label">Detail</p>
        <div class="m-detail-row" v-for="row in rows" :key="row.key">
          <p class="m-detail-key">{{ row.label }}</p>
          <p class="m-detail-value">{{ row.value }}</p>
        </div>
      </div>

      <div v-if="!rows.length" class="m-card m-state">
        <p class="m-sub">No additional detail available yet for this module.</p>
      </div>

      <div class="m-card m-state">
        <span class="m-tag">Scaffold — full redesign pending</span>
        <p class="m-sub">This is a placeholder drill-down. The redesigned mobile UI replaces this with the production module surface.</p>
      </div>
    </template>
  </div>
</template>

<script>
import { store } from '../store.js';
import { apiGet } from '../api.js';

function formatCurrency(value) {
  if (value == null || value === '' || isNaN(Number(value))) return '—';
  return new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP', maximumFractionDigits: 0 }).format(Number(value));
}

function formatPercent(value) {
  if (value == null || value === '' || isNaN(Number(value))) return '—';
  return `${Number(value).toFixed(1)}%`;
}

function humanise(key) {
  return key
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (c) => c.toUpperCase())
    .replace(/Iht/g, 'IHT')
    .replace(/Isa/g, 'ISA')
    .replace(/Ni\b/g, 'NI');
}

// Per-module configuration: hero metric + which summary keys to surface.
// Keep this lean — full module presentation belongs to the redesign.
const MODULE_CONFIG = {
  protection: {
    label: 'Protection',
    subtitle: 'Life, income protection and critical illness cover',
    hero: (s) => ({ label: 'Total cover in place', value: formatCurrency(s?.total_coverage ?? s?.coverage?.total_life_cover), secondary: (s?.critical_gaps ?? 0) > 0 ? `${s.critical_gaps} critical gap${s.critical_gaps > 1 ? 's' : ''}` : 'No critical gaps' }),
    fields: ['total_life_cover', 'total_critical_illness_cover', 'total_income_protection', 'policy_count', 'critical_gaps', 'status'],
  },
  savings: {
    label: 'Savings',
    subtitle: 'Cash, ISA allowance and emergency fund',
    hero: (s) => ({
      label: 'Emergency fund runway',
      value: s?.emergency_fund_months != null ? `${Number(s.emergency_fund_months).toFixed(1)} months` : '—',
      secondary: s?.emergency_fund_status ? humanise(String(s.emergency_fund_status)) : null,
    }),
    fields: ['total_cash', 'isa_allowance_remaining', 'isa_cash_used', 'isa_stocks_shares_used', 'account_count', 'emergency_fund_target', 'status'],
  },
  investment: {
    label: 'Investment',
    subtitle: 'Portfolio value, allocation and fees',
    hero: (s) => ({ label: 'Portfolio value', value: formatCurrency(s?.portfolio_value ?? s?.total_value), secondary: s?.account_count ? `${s.account_count} account${s.account_count > 1 ? 's' : ''}` : null }),
    fields: ['portfolio_value', 'total_unrealised_gains', 'total_ongoing_charges', 'asset_allocation', 'account_count', 'status'],
  },
  retirement: {
    label: 'Retirement',
    subtitle: 'Projected income and shortfall',
    hero: (s) => ({
      label: 'Projected retirement income',
      value: formatCurrency(s?.projected_income ?? s?.projected_annual_income),
      secondary: s?.income_gap != null && s.income_gap !== 0
        ? `${formatCurrency(Math.abs(s.income_gap))} ${s.income_gap > 0 ? 'shortfall' : 'surplus'} vs target`
        : null,
    }),
    fields: ['projected_income', 'target_income', 'income_gap', 'total_pension_value', 'years_to_retirement', 'status'],
  },
  estate: {
    label: 'Estate',
    subtitle: 'Inheritance tax exposure and planning',
    hero: (s) => ({ label: 'Estimated IHT liability', value: formatCurrency(s?.iht_liability ?? s?.estimated_iht), secondary: s?.estate_value != null ? `${formatCurrency(s.estate_value)} estate` : null }),
    fields: ['estate_value', 'iht_liability', 'nil_rate_band_used', 'rnrb_used', 'effective_iht_rate', 'status'],
  },
  goals: {
    label: 'Goals & life events',
    subtitle: 'Progress towards financial milestones',
    hero: (s) => ({
      label: 'Goals on track',
      value: s?.total_goals != null ? `${s?.completed_goals ?? 0} of ${s.total_goals}` : '—',
      secondary: s?.next_milestone ? `Next: ${s.next_milestone}` : null,
    }),
    fields: ['total_goals', 'completed_goals', 'active_goals', 'overdue_goals', 'status'],
  },
};

export default {
  name: 'MobileModuleDetail',
  props: {
    slug: { type: String, required: true },
  },
  data: () => ({ loading: true, error: '', summary: null }),
  computed: {
    meta() {
      return MODULE_CONFIG[this.slug] || { label: humanise(this.slug), subtitle: '', hero: () => ({ label: '', value: '—', secondary: null }), fields: [] };
    },
    hero() {
      return this.meta.hero(this.summary || {});
    },
    heroLabel() { return this.hero.label; },
    heroValue() { return this.hero.value; },
    heroSecondary() { return this.hero.secondary; },
    rows() {
      if (!this.summary) return [];
      // Pull the curated fields if present; fall back to flattening the summary.
      const out = [];
      const seen = new Set();
      for (const key of this.meta.fields) {
        if (Object.prototype.hasOwnProperty.call(this.summary, key)) {
          out.push({ key, label: humanise(key), value: this.formatFieldValue(key, this.summary[key]) });
          seen.add(key);
        }
      }
      // If no curated fields matched, surface top-level scalars as a fallback so the user sees something
      if (!out.length) {
        for (const [key, value] of Object.entries(this.summary)) {
          if (value == null) continue;
          if (typeof value === 'object') continue;
          if (seen.has(key)) continue;
          out.push({ key, label: humanise(key), value: this.formatFieldValue(key, value) });
          if (out.length >= 8) break;
        }
      }
      return out;
    },
  },
  async created() {
    await this.load();
  },
  watch: {
    slug() { this.load(); },
  },
  methods: {
    async load() {
      this.loading = true;
      this.error = '';
      this.summary = null;
      try {
        const { ok, data } = await apiGet(`/api/v1/mobile/modules/${this.slug}`, store.token);
        if (ok) this.summary = data?.data?.summary ?? data?.summary ?? data?.data ?? data ?? {};
        else this.error = data?.message || 'We could not load this module.';
      } catch (e) {
        this.error = 'Network error. Please try again.';
      } finally {
        this.loading = false;
      }
    },
    goBack() {
      this.$router.push({ name: 'dashboard' });
    },
    formatFieldValue(key, value) {
      if (value == null || value === '') return '—';
      if (typeof value === 'boolean') return value ? 'Yes' : 'No';
      if (Array.isArray(value)) return `${value.length} item${value.length === 1 ? '' : 's'}`;
      if (typeof value === 'object') return `${Object.keys(value).length} field${Object.keys(value).length === 1 ? '' : 's'}`;
      const numeric = Number(value);
      const lower = key.toLowerCase();
      if (!isNaN(numeric)) {
        if (lower.includes('rate') || lower.includes('percent') || lower.endsWith('_pct')) return formatPercent(numeric);
        if (lower.includes('months') || lower.includes('years') || lower.endsWith('_count') || lower === 'policy_count' || lower === 'account_count' || lower === 'total_goals' || lower === 'completed_goals' || lower === 'active_goals' || lower === 'overdue_goals' || lower === 'critical_gaps') {
          return String(value);
        }
        if (lower.includes('value') || lower.includes('cover') || lower.includes('income') || lower.includes('liability') || lower.includes('cash') || lower.includes('allowance') || lower.includes('amount') || lower.includes('balance') || lower.includes('total') || lower.includes('used') || lower.includes('target') || lower.includes('gap') || lower.includes('iht') || lower.includes('charges') || lower.includes('gains') || lower.includes('estate')) {
          return formatCurrency(numeric);
        }
      }
      const str = String(value);
      return str.length > 0 ? str.charAt(0).toUpperCase() + str.slice(1).replace(/_/g, ' ') : '—';
    },
  },
};
</script>
