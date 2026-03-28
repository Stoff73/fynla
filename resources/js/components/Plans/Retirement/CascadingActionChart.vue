<template>
  <div class="bg-white rounded-lg border border-light-gray p-3 mt-2 mb-1">
    <div class="flex items-center justify-between mb-2">
      <p class="text-xs text-neutral-500">
        {{ years }}-year projection &middot; impact of this action
      </p>
      <span
        v-if="differenceAmount > 0"
        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-spring-100 text-spring-800"
      >
        +{{ formatCurrency(differenceAmount) }} at retirement
      </span>
    </div>
    <apexchart
      type="line"
      :height="180"
      :options="chartOptions"
      :series="chartSeries"
    />
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';
import { CHART_COLORS, CHART_DEFAULTS, TEXT_COLORS, BORDER_COLORS } from '@/constants/designSystem';

export default {
  name: 'CascadingActionChart',

  mixins: [currencyMixin],

  props: {
    beforeSeries: {
      type: Array,
      required: true,
    },
    afterSeries: {
      type: Array,
      required: true,
    },
    years: {
      type: Number,
      default: 10,
    },
    differenceAmount: {
      type: Number,
      default: 0,
    },
  },

  computed: {
    chartSeries() {
      return [
        { name: 'Before', data: this.beforeSeries },
        { name: 'After this action', data: this.afterSeries },
      ];
    },

    chartOptions() {
      const self = this;
      return {
        chart: { ...CHART_DEFAULTS.chart, type: 'line', sparkline: { enabled: false } },
        colors: [CHART_COLORS[1], CHART_COLORS[2]],
        stroke: {
          curve: 'smooth',
          width: 2,
        },
        xaxis: {
          categories: Array.from({ length: this.years + 1 }, (_, i) => `Year ${i}`),
          labels: {
            style: { fontSize: '10px', colors: TEXT_COLORS.muted },
            rotate: 0,
            hideOverlappingLabels: true,
          },
          axisBorder: { show: false },
          axisTicks: { show: false },
        },
        yaxis: {
          labels: {
            formatter: (val) => self.formatCurrencyCompact(val),
            style: { fontSize: '10px', colors: TEXT_COLORS.muted },
          },
        },
        grid: {
          borderColor: BORDER_COLORS.default,
          strokeDashArray: 3,
          padding: { left: 5, right: 5, top: 0, bottom: 0 },
        },
        tooltip: {
          y: {
            formatter: (val) => self.formatCurrency(val),
          },
        },
        legend: {
          position: 'top',
          horizontalAlign: 'left',
          fontSize: '11px',
          markers: { size: 3 },
          itemMargin: { horizontal: 8 },
        },
        dataLabels: { enabled: false },
      };
    },
  },
};
</script>
