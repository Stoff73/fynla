<template>
  <div v-if="chartSeries.length && hasData" class="mb-4">
    <apexchart
      :key="chartKey"
      type="bar"
      :height="chartHeight"
      :options="chartOptions"
      :series="chartSeries"
    />
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';
import { CHART_COLORS, CHART_DEFAULTS, TEXT_COLORS, BORDER_COLORS } from '@/constants/designSystem';

export default {
  name: 'PlanWhatIfChart',

  mixins: [currencyMixin],

  props: {
    currentScenario: { type: Object, default: null },
    projectedScenario: { type: Object, default: null },
    metrics: {
      type: Array,
      required: true,
      validator: (arr) => arr.every((m) => m.key && m.label),
    },
  },

  computed: {
    hasData() {
      return this.currentScenario && this.projectedScenario;
    },

    chartKey() {
      if (!this.hasData) return 'no-data';
      const total = this.metrics.reduce((sum, m) => {
        return sum + (this.currentScenario[m.key] || 0) + (this.projectedScenario[m.key] || 0);
      }, 0);
      return `whatif-${this.metrics.length}-${Math.round(total)}`;
    },

    chartHeight() {
      return Math.max(160, this.metrics.length * 40 + 60);
    },

    chartSeries() {
      if (!this.hasData) return [];

      return [
        {
          name: 'Current',
          data: this.metrics.map((m) => {
            const val = this.currentScenario[m.key];
            return typeof val === 'number' ? val : 0;
          }),
        },
        {
          name: 'With Actions',
          data: this.metrics.map((m) => {
            const val = this.projectedScenario[m.key];
            return typeof val === 'number' ? val : 0;
          }),
        },
      ];
    },

    chartOptions() {
      const self = this;
      return {
        chart: { ...CHART_DEFAULTS.chart, type: 'bar' },
        plotOptions: {
          bar: {
            horizontal: true,
            barHeight: '60%',
            borderRadius: 3,
          },
        },
        colors: [CHART_COLORS[1], CHART_COLORS[2]],
        dataLabels: { enabled: false },
        xaxis: {
          categories: this.metrics.map((m) => m.label),
          labels: {
            formatter(val) {
              return self.formatCurrencyCompact(val);
            },
            style: {
              fontSize: '11px',
            },
          },
          axisBorder: { show: false },
          axisTicks: { show: false },
        },
        yaxis: {
          labels: {
            style: {
              fontSize: '11px',
            },
          },
        },
        grid: {
          borderColor: BORDER_COLORS.default,
          strokeDashArray: 4,
          xaxis: { lines: { show: true } },
          yaxis: { lines: { show: false } },
        },
        legend: {
          position: 'top',
          horizontalAlign: 'right',
          fontSize: '12px',
          fontFamily: CHART_DEFAULTS.chart.fontFamily,
          markers: { radius: 2 },
        },
        tooltip: {
          style: {
            fontSize: '14px',
            fontFamily: CHART_DEFAULTS.chart.fontFamily,
          },
          y: {
            formatter(val) {
              return self.formatCurrency(val);
            },
          },
        },
      };
    },
  },
};
</script>
