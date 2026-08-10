<template>
  <div v-if="portfolio" class="cp-stack">
    <div class="m-card">
      <p class="m-section-label" style="margin-top:0">Holdings</p>
      <p v-if="!holdings.length" class="m-sub" style="margin-bottom:0">No individual holdings recorded for this account.</p>
      <div v-else>
        <div v-for="holding in holdings" :key="holding.id" class="cp-holding">
          <div class="cp-line cp-line--strong">
            <span>{{ holding.name || holding.ticker || 'Holding' }}</span>
            <span>{{ fmt(holding.current_value) }}</span>
          </div>
          <div class="cp-meta">
            <span>{{ pct(holding.wrapper_percentage) }} of this account</span>
            <span>{{ pct(holding.whole_relevant_portfolio_percentage) }} of your relevant portfolio</span>
          </div>
          <div v-if="holding.classified_exposure?.length" class="cp-chips">
            <span v-for="exposure in holding.classified_exposure" :key="exposure.asset_class" class="cp-chip">
              {{ label(exposure.asset_class) }} {{ pct(exposure.holding_percentage) }}
            </span>
          </div>
          <div class="cp-statuses">
            <span v-if="holding.fees?.available">
              {{ pct(holding.fees.ocf_percent, 2) }} OCF · {{ fmt(holding.fees.estimated_annual_cost) }} estimated a year
            </span>
            <span v-else>Holding charge unavailable</span>
            <span v-if="holding.performance?.available" :class="tone(holding.performance.gain_loss)">
              {{ signedCurrency(holding.performance.gain_loss) }} ({{ signedPct(holding.performance.gain_loss_percent) }})
            </span>
            <span v-else>Performance unavailable</span>
          </div>
        </div>
      </div>
    </div>

    <div class="m-card">
      <p class="m-section-label" style="margin-top:0">Portfolio exposure and drift</p>
      <p class="cp-coverage">
        {{ pct(analysis.coverage_percent) }} classified
        <span v-if="Number(analysis.unclassified_value) > 0">· {{ fmt(analysis.unclassified_value) }} unclassified</span>
      </p>
      <div v-if="analysis.allocation?.length" class="cp-allocation">
        <div v-for="row in analysis.allocation" :key="row.asset_class" class="cp-line">
          <span>{{ label(row.asset_class) }}</span>
          <span>{{ pct(row.portfolio_percentage) }} of whole portfolio</span>
        </div>
      </div>
      <div class="cp-comparisons">
        <div v-if="enteredComparison" class="cp-comparison">
          <p class="cp-comparison__title">Entered portfolio</p>
          <DriftRows :comparison="enteredComparison" />
        </div>
        <div v-if="recommendedComparison" class="cp-comparison">
          <p class="cp-comparison__title">Recommended allocation</p>
          <DriftRows :comparison="recommendedComparison" />
        </div>
        <p v-if="!enteredComparison && !recommendedComparison" class="m-sub" style="margin-bottom:0">
          No entered or recommended comparison portfolio is available.
        </p>
      </div>
      <p v-if="!analysis.drift_available" class="cp-note">
        Drift is unavailable because {{ pct(analysis.coverage_percent) }} is classified; at least {{ pct(analysis.coverage_threshold_percent) }} is required.
      </p>
    </div>

    <div class="m-card">
      <p class="m-section-label" style="margin-top:0">Recorded performance history</p>
      <template v-if="history.available && history.points?.length">
        <svg
          v-if="history.points.length > 1"
          data-test="portfolio-performance-chart"
          class="cp-chart"
          viewBox="0 0 320 120"
          role="img"
          :aria-label="`Recorded portfolio values from ${history.points[0].date} to ${history.points[history.points.length - 1].date}`"
        >
          <polyline :points="chartPoints" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <div class="cp-history-range">
          <span>{{ date(history.points[0].date) }} · {{ fmt(history.points[0].value) }}</span>
          <span>{{ date(history.points[history.points.length - 1].date) }} · {{ fmt(history.points[history.points.length - 1].value) }}</span>
        </div>
        <p class="cp-note">Recorded account-value snapshots only; no missing values are inferred.</p>
      </template>
      <p v-else class="m-sub" style="margin-bottom:0">Recorded performance history is unavailable.</p>
    </div>
  </div>
</template>

<script>
import { defineComponent } from 'vue';

function currency(value) {
  if (value == null || Number.isNaN(Number(value))) return '—';
  return new Intl.NumberFormat('en-GB', {
    style: 'currency', currency: 'GBP', maximumFractionDigits: 0,
  }).format(Number(value));
}

const DriftRows = defineComponent({
  props: { comparison: { type: Object, required: true } },
  methods: {
    label(value) {
      return String(value || '').replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());
    },
    signed(value) {
      const number = Number(value || 0);
      return `${number > 0 ? '+' : ''}${number.toFixed(1)}pp`;
    },
    sourceLabel(source) {
      return {
        user_entered: 'User-entered baseline',
        fynla_recommended_asset_allocation: 'Fynla recommended asset allocation',
      }[source] || this.label(source);
    },
    date(value) {
      const parsed = new Date(value);
      return Number.isNaN(parsed.getTime())
        ? value
        : parsed.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
    },
  },
  template: `
    <div>
      <div v-if="comparison.drift_percentage_points" class="cp-drift-list">
        <div v-for="(value, assetClass) in comparison.drift_percentage_points" :key="assetClass" class="cp-line">
          <span>{{ label(assetClass) }}</span><span>{{ signed(value) }}</span>
        </div>
      </div>
      <p v-else class="cp-note">Drift unavailable until the portfolio has sufficient classified coverage.</p>
      <p v-if="comparison.source || comparison.effective_at" class="cp-note">
        <span v-if="comparison.source">{{ sourceLabel(comparison.source) }}</span>
        <span v-if="comparison.source && comparison.effective_at"> · </span>
        <span v-if="comparison.effective_at">Effective {{ date(comparison.effective_at) }}</span>
      </p>
    </div>
  `,
});

export default {
  name: 'CanonicalPortfolio',
  components: { DriftRows },
  props: {
    portfolio: { type: Object, default: null },
  },
  computed: {
    holdings() { return this.portfolio?.holdings || []; },
    analysis() { return this.portfolio?.analysis || {}; },
    history() { return this.portfolio?.performance_history || { available: false, points: [] }; },
    enteredComparison() { return this.analysis?.comparisons?.entered || null; },
    recommendedComparison() { return this.analysis?.comparisons?.recommended || null; },
    chartPoints() {
      const points = this.history.points || [];
      if (points.length < 2) return '';
      const values = points.map((point) => Number(point.value || 0));
      const min = Math.min(...values);
      const max = Math.max(...values);
      const range = max - min || 1;
      return values.map((value, index) => {
        const x = 10 + ((300 * index) / (values.length - 1));
        const y = 105 - (((value - min) / range) * 90);
        return `${x.toFixed(1)},${y.toFixed(1)}`;
      }).join(' ');
    },
  },
  methods: {
    fmt: currency,
    pct(value, digits = 1) {
      if (value == null || Number.isNaN(Number(value))) return '—';
      return `${Number(value).toFixed(digits)}%`;
    },
    signedCurrency(value) {
      const number = Number(value || 0);
      return `${number > 0 ? '+' : ''}${currency(number)}`;
    },
    signedPct(value) {
      const number = Number(value || 0);
      return `${number > 0 ? '+' : ''}${number.toFixed(1)}%`;
    },
    label(value) {
      return String(value || '').replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());
    },
    tone(value) { return Number(value) >= 0 ? 'cp-positive' : 'cp-negative'; },
    date(value) {
      const parsed = new Date(value);
      return Number.isNaN(parsed.getTime()) ? value : parsed.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
    },
  },
};
</script>

<style scoped>
.cp-stack { display: contents; }
.cp-holding { padding: 12px 0; border-bottom: 1px solid var(--horizon-100); }
.cp-holding:first-child { padding-top: 4px; }
.cp-holding:last-child { border-bottom: 0; padding-bottom: 0; }
.cp-line { display: flex; justify-content: space-between; gap: 12px; font-size: 13px; padding: 4px 0; color: var(--horizon-500); }
:deep(.cp-line) { display: flex; justify-content: space-between; gap: 12px; font-size: 13px; padding: 4px 0; color: var(--horizon-500); }
.cp-line--strong { font-size: 14px; font-weight: 700; }
.cp-meta, .cp-statuses { display: flex; flex-wrap: wrap; gap: 6px 12px; margin-top: 5px; font-size: 12px; color: var(--neutral-500); }
.cp-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 7px; }
.cp-chip { padding: 2px 7px; border-radius: var(--radius-sm); background: var(--horizon-100); color: var(--horizon-500); font-size: 11px; font-weight: 700; }
.cp-positive { color: var(--spring-600); font-weight: 700; }
.cp-negative { color: var(--raspberry-500); font-weight: 700; }
.cp-coverage { margin: 0 0 8px; font-size: 13px; font-weight: 700; color: var(--horizon-500); }
.cp-allocation { border-bottom: 1px solid var(--horizon-100); padding-bottom: 8px; }
.cp-comparisons { display: grid; gap: 10px; margin-top: 12px; }
.cp-comparison { border: 1px solid var(--horizon-100); border-radius: var(--radius-sm); padding: 10px; }
.cp-comparison__title { margin: 0 0 4px; font-size: 13px; font-weight: 700; color: var(--horizon-500); }
.cp-note { margin: 8px 0 0; color: var(--neutral-500); font-size: 11px; line-height: 1.45; }
.cp-chart { display: block; width: 100%; max-height: 150px; color: var(--violet-500); background: linear-gradient(180deg, var(--light-blue-100), transparent); border-radius: var(--radius-sm); }
.cp-history-range { display: flex; justify-content: space-between; gap: 12px; margin-top: 8px; font-size: 11px; color: var(--neutral-500); }
.cp-history-range span:last-child { text-align: right; }
</style>
