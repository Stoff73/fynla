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
import { PRIMARY_COLORS } from '@/constants/designSystem';

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
        chart: {
          type: 'bar',
          fontFamily: 'Inter, system-ui, sans-serif',
          toolbar: { show: false },
          zoom: { enabled: false },
        },
        plotOptions: {
          bar: {
            horizontal: true,
            barHeight: '60%',
            borderRadius: 3,
          },
        },
        colors: [PRIMARY_COLORS[400], '#10B981'],
        dataLabels: { enabled: false },
        xaxis: {
          categories: this.categories,
          labels: {
            style: {
              colors: '#6B7280',
              fontSize: '11px',
            },
            formatter(val) {
              return self.formatCurrencyCompact(val);
            },
          },
          axisBorder: { show: false },
          axisTicks: { show: false },
        },
        yaxis: {
          labels: {
            style: {
              colors: '#6B7280',
              fontSize: '11px',
            },
          },
        },
        grid: {
          borderColor: '#E5E7EB',
          strokeDashArray: 4,
          xaxis: { lines: { show: true } },
          yaxis: { lines: { show: false } },
        },
        legend: {
          position: 'top',
          horizontalAlign: 'right',
          fontSize: '12px',
          fontFamily: 'Inter, system-ui, sans-serif',
          markers: { radius: 2 },
        },
        tooltip: {
          y: {
            formatter(val) {
              return self.formatCurrency(val);
            },
          },
          style: {
            fontSize: '12px',
            fontFamily: 'Inter, system-ui, sans-serif',
          },
        },
      };
    },
  },
};
</script>
