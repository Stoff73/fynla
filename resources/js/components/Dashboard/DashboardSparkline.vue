<template>
  <div class="dashboard-sparkline">
    <div class="text-xs text-neutral-500 mb-1">Last 6 months</div>
    <apexchart
      v-if="chartReady"
      type="line"
      :options="chartOptions"
      :series="chartSeries"
      :height="height"
    />
  </div>
</template>

<script>
import { SECONDARY_COLORS, BORDER_COLORS, TEXT_COLORS, CHART_DEFAULTS } from '@/constants/designSystem';

export default {
  name: 'DashboardSparkline',

  props: {
    data: {
      type: Array,
      required: true,
      // Array of { label: string, value: number }
    },
    color: {
      type: String,
      default: SECONDARY_COLORS[500],
    },
    height: {
      type: Number,
      default: 80,
    },
  },

  data() {
    return {
      chartReady: false,
    };
  },

  mounted() {
    // Delay render to avoid ApexCharts flash
    setTimeout(() => {
      this.chartReady = true;
    }, 100);
  },

  computed: {
    chartSeries() {
      return [{
        name: 'Balance',
        data: this.data.map(d => d.value),
      }];
    },

    chartOptions() {
      return {
        chart: {
          ...CHART_DEFAULTS.chart,
          type: 'line',
          toolbar: { show: false },
          zoom: { enabled: false },
          sparkline: { enabled: false },
        },
        colors: [this.color],
        stroke: {
          curve: 'straight',
          width: 3.5,
          lineCap: 'round',
        },
        markers: {
          size: 7,
          colors: [this.color],
          strokeColors: '#ffffff',
          strokeWidth: 3.5,
          hover: { sizeOffset: 2 },
        },
        fill: {
          type: 'gradient',
          gradient: {
            shade: 'light',
            type: 'vertical',
            opacityFrom: 0.12,
            opacityTo: 0.01,
          },
        },
        xaxis: {
          categories: this.data.map(d => d.label),
          labels: {
            style: { fontSize: '10px', colors: TEXT_COLORS.muted },
          },
          axisBorder: { show: false },
          axisTicks: { show: false },
        },
        yaxis: { show: false },
        grid: {
          borderColor: BORDER_COLORS.default,
          strokeDashArray: 0,
          xaxis: { lines: { show: false } },
          yaxis: { lines: { show: true } },
          padding: { left: 0, right: 0, top: -10, bottom: 0 },
        },
        tooltip: { enabled: false },
        legend: { show: false },
        dataLabels: { enabled: false },
      };
    },
  },
};
</script>
