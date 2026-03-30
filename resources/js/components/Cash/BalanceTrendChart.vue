<template>
  <div class="balance-trend-chart">
    <h3 class="chart-title">Balance Over Time</h3>

    <div v-if="chartReady" class="chart-container">
      <apexchart
        :key="chartKey"
        type="area"
        :options="chartOptions"
        :series="series"
        height="220"
      />
    </div>
  </div>
</template>

<script>
import VueApexCharts from 'vue3-apexcharts';
import { generateMockBalanceTrend } from './mockData';
import { CHART_COLORS, TEXT_COLORS, BORDER_COLORS, CHART_DEFAULTS } from '@/constants/designSystem';

export default {
  name: 'BalanceTrendChart',

  components: {
    apexchart: VueApexCharts,
  },

  data() {
    return {
      chartReady: false,
      trendData: [],
      renderTimeout: null,
    };
  },

  computed: {
    chartKey() {
      const lastVal = this.trendData[this.trendData.length - 1]?.value || 0;
      return `balance-${this.trendData.length}-${Math.round(lastVal)}`;
    },

    series() {
      return [
        {
          name: 'Balance',
          data: this.trendData.map(d => d.value),
        },
      ];
    },

    categories() {
      return this.trendData.map(d => d.date);
    },

    chartOptions() {
      return {
        chart: {
          ...CHART_DEFAULTS.chart,
          type: 'area',
          sparkline: { enabled: false },
        },
        colors: [CHART_COLORS[5]], // Purple
        fill: {
          type: 'gradient',
          gradient: {
            shadeIntensity: 1,
            opacityFrom: 0.4,
            opacityTo: 0.1,
            stops: [0, 100],
          },
        },
        stroke: {
          curve: 'smooth',
          width: 2,
        },
        dataLabels: { enabled: false },
        xaxis: {
          categories: this.categories,
          labels: {
            show: true,
            rotate: 0,
            style: { fontSize: '10px', colors: TEXT_COLORS.muted },
            formatter: (value) => {
              if (!value) return '';
              const date = new Date(value);
              return date.getDate();
            },
          },
          axisBorder: { show: false },
          axisTicks: { show: false },
          tickAmount: 6,
        },
        yaxis: {
          labels: {
            style: { fontSize: '11px', colors: TEXT_COLORS.muted },
            formatter: (val) => {
              if (val >= 1000) {
                return `£${(val / 1000).toFixed(1)}k`;
              }
              return `£${val}`;
            },
          },
        },
        grid: {
          borderColor: BORDER_COLORS.default,
          strokeDashArray: 4,
          xaxis: { lines: { show: false } },
          yaxis: { lines: { show: true } },
          padding: { left: 10, right: 10 },
        },
        tooltip: {
          enabled: true,
          x: {
            formatter: (val) => {
              const date = new Date(this.categories[val - 1]);
              return date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
            },
          },
          y: {
            formatter: (val) => `£${val.toLocaleString('en-GB', { minimumFractionDigits: 2 })}`,
          },
        },
        responsive: [
          {
            breakpoint: 768,
            options: {
              chart: { height: 180 },
            },
          },
        ],
      };
    },
  },

  created() {
    this.trendData = generateMockBalanceTrend(4500, 30);
  },

  mounted() {
    this.$nextTick(() => {
      this.renderTimeout = setTimeout(() => {
        this.chartReady = true;
      }, 100);
    });
  },

  beforeUnmount() {
    if (this.renderTimeout) clearTimeout(this.renderTimeout);
  },
};
</script>

<style scoped>
.balance-trend-chart {
  background: white;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.chart-title {
  font-size: 16px;
  font-weight: 600;
  @apply text-horizon-500;
  margin: 0 0 16px 0;
}

.chart-container {
  width: 100%;
}
</style>
