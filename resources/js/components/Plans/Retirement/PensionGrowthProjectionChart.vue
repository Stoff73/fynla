<template>
  <div class="bg-white rounded-lg border border-light-gray p-4 mt-3">
    <div class="flex items-center justify-between mb-1">
      <h4 class="text-sm font-semibold text-horizon-500">{{ projection.pension_name }}</h4>
      <span
        v-if="projectionDifference > 0"
        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-spring-100 text-spring-800"
      >
        +{{ formatCurrency(projectionDifference) }} at retirement
      </span>
    </div>
    <p class="text-xs text-neutral-500 mb-3">
      {{ years }}-year projection ({{ projection.projection_label || 'to retirement' }}) &middot;
      {{ formatPercentage(projection.growth_rate * 100) }} net growth &middot;
      {{ formatCurrency(projection.annual_contribution) }}/year contributions
    </p>
    <apexchart
      type="line"
      :height="220"
      :options="chartOptions"
      :series="chartSeries"
    />
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';
import { CHART_COLORS, CHART_DEFAULTS, TEXT_COLORS, BORDER_COLORS } from '@/constants/designSystem';

export default {
  name: 'PensionGrowthProjectionChart',

  mixins: [currencyMixin],

  props: {
    projection: {
      type: Object,
      required: true,
    },
    enabledActionCount: {
      type: Number,
      default: 0,
    },
    totalActionCount: {
      type: Number,
      default: 0,
    },
  },

  computed: {
    years() {
      return this.projection.years || 10;
    },

    currentSeries() {
      return this.projection.current_series || [];
    },

    withActionsSeries() {
      // If all actions enabled, use backend data directly
      if (this.totalActionCount > 0 && this.enabledActionCount === this.totalActionCount) {
        return this.projection.with_actions_series || [];
      }

      // If no actions enabled, match the current series
      if (this.enabledActionCount === 0 || this.totalActionCount === 0) {
        return this.currentSeries;
      }

      // Linear interpolation for partial toggles
      const ratio = this.enabledActionCount / this.totalActionCount;
      const current = this.currentSeries;
      const full = this.projection.with_actions_series || [];

      if (!current.length || !full.length) return current;

      return current.map((val, i) => {
        const diff = (full[i] || val) - val;
        return Math.round(val + diff * ratio);
      });
    },

    projectionDifference() {
      const current = this.currentSeries;
      const withActions = this.withActionsSeries;

      if (!current.length || !withActions.length) return 0;

      const lastIdx = Math.min(current.length, withActions.length) - 1;
      const diff = withActions[lastIdx] - current[lastIdx];
      return diff > 0 ? diff : 0;
    },

    chartSeries() {
      return [
        { name: 'Current Trajectory', data: this.currentSeries },
        { name: 'With Actions', data: this.withActionsSeries },
      ];
    },

    chartOptions() {
      const self = this;
      return {
        chart: { ...CHART_DEFAULTS.chart, type: 'line' },
        colors: [CHART_COLORS[1], CHART_COLORS[2]],
        stroke: {
          curve: 'smooth',
          width: 2.5,
        },
        xaxis: {
          categories: Array.from({ length: this.years + 1 }, (_, i) => `Year ${i}`),
          labels: {
            style: { fontSize: '11px', colors: TEXT_COLORS.muted },
          },
          axisBorder: { show: false },
          axisTicks: { show: false },
        },
        yaxis: {
          labels: {
            formatter: (val) => self.formatCurrencyCompact(val),
            style: { fontSize: '11px', colors: TEXT_COLORS.muted },
          },
        },
        grid: {
          borderColor: BORDER_COLORS.default,
          strokeDashArray: 3,
        },
        tooltip: {
          y: {
            formatter: (val) => self.formatCurrency(val),
          },
        },
        legend: {
          position: 'top',
          horizontalAlign: 'left',
          fontSize: '12px',
          markers: { size: 4 },
        },
        dataLabels: { enabled: false },
      };
    },
  },
};
</script>
