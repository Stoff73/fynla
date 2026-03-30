<template>
  <div class="bg-white rounded-lg border border-light-gray p-4 mt-3">
    <div class="flex items-center justify-between mb-1">
      <h4 class="text-sm font-semibold text-horizon-500">{{ projection.account_name }}</h4>
      <span
        v-if="projectionDifference > 0"
        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-spring-100 text-spring-800"
      >
        +{{ formatCurrency(projectionDifference) }} over {{ years }} years
      </span>
    </div>
    <p class="text-xs text-neutral-500 mb-3">
      {{ years }}-year projection ({{ projection.projection_label || 'to retirement' }}) &middot;
      5% assumed growth &middot;
      Current fees {{ projection.current_fee_percent }}%
      &rarr; Reduced fees {{ effectiveFeePercent }}%
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
  name: 'AccountFeeProjectionChart',

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
    effectiveFeePercent() {
      const current = this.projection.current_fee_percent;
      const reduced = this.projection.reduced_fee_percent;

      if (this.totalActionCount === 0) return current;
      if (this.enabledActionCount === this.totalActionCount) return reduced;
      if (this.enabledActionCount === 0) return current;

      // Linear interpolation for partial toggles
      const ratio = this.enabledActionCount / this.totalActionCount;
      const interpolated = current - (current - reduced) * ratio;
      return Math.round(interpolated * 100) / 100;
    },

    years() {
      return this.projection.years || 10;
    },

    currentValue() {
      return this.projection.current_value || 0;
    },

    currentFeesSeries() {
      return this.projection.current_fees_series || [];
    },

    reducedFeesSeries() {
      // If all actions enabled, use backend data directly
      if (this.totalActionCount > 0 && this.enabledActionCount === this.totalActionCount) {
        return this.projection.reduced_fees_series || [];
      }

      // Otherwise recompute from current value using effective fee
      const growthRate = 0.05;
      const feeRate = this.effectiveFeePercent / 100;
      const series = [];

      for (let y = 0; y <= this.years; y++) {
        const value = this.currentValue * Math.pow(1 + growthRate - feeRate, y);
        series.push(Math.round(value));
      }

      return series;
    },

    projectionDifference() {
      const currentSeries = this.currentFeesSeries;
      const reducedSeries = this.reducedFeesSeries;

      if (!currentSeries.length || !reducedSeries.length) return 0;

      const lastIdx = Math.min(currentSeries.length, reducedSeries.length) - 1;
      const diff = reducedSeries[lastIdx] - currentSeries[lastIdx];
      return diff > 0 ? diff : 0;
    },

    chartSeries() {
      return [
        {
          name: 'Current Fees',
          data: this.currentFeesSeries,
        },
        {
          name: 'Reduced Fees',
          data: this.reducedFeesSeries,
        },
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
