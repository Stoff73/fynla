<template>
  <div v-if="chartSeries.length && hasData" class="mb-4">
    <apexchart
      type="bar"
      :height="chartHeight"
      :options="chartOptions"
      :series="chartSeries"
    />
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';
import { CHART_DEFAULTS, CHART_COLORS, TEXT_COLORS, BORDER_COLORS } from '@/constants/designSystem';

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

    categories() {
      return this.metrics.map((m) => m.label);
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
        ...CHART_DEFAULTS,
        chart: {
          ...CHART_DEFAULTS.chart,
          type: 'bar',
        },
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
          ...CHART_DEFAULTS.xaxis,
          categories: this.categories,
          labels: {
            ...CHART_DEFAULTS.xaxis.labels,
            formatter(val) {
              return self.formatCurrencyCompact(val);
            },
          },
        },
        yaxis: {
          ...CHART_DEFAULTS.yaxis,
        },
        grid: {
          ...CHART_DEFAULTS.grid,
          xaxis: { lines: { show: true } },
          yaxis: { lines: { show: false } },
        },
        legend: {
          ...CHART_DEFAULTS.legend,
          position: 'top',
          horizontalAlign: 'right',
          fontSize: '12px',
          markers: { radius: 2 },
        },
        tooltip: {
          ...CHART_DEFAULTS.tooltip,
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
