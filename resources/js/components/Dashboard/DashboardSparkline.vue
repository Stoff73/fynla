<template>
  <div class="dashboard-sparkline">
    <div class="text-xs text-neutral-500 mb-1">Last 6 months</div>
    <apexchart
      v-if="chartReady"
      type="area"
      :options="chartOptions"
      :series="chartSeries"
      :height="height"
    />
  </div>
</template>

<script>
import { SECONDARY_COLORS, TEXT_COLORS, CHART_DEFAULTS } from '@/constants/designSystem';

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
          type: 'area',
          toolbar: { show: false },
          zoom: { enabled: false },
          sparkline: { enabled: false },
        },
        colors: [this.color],
        stroke: {
          curve: 'smooth',
          width: 2.5,
        },
        markers: {
          size: 6,
          colors: ['#ffffff'],
          strokeColors: [this.color],
          strokeWidth: 3,
          hover: { sizeOffset: 3 },
        },
        fill: {
          type: 'gradient',
          gradient: {
            shadeIntensity: 1,
            type: 'vertical',
            opacityFrom: 0.35,
            opacityTo: 0.05,
            stops: [0, 100],
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
        yaxis: {
          show: false,
          min: (min) => min * 0.9,
          max: (max) => max * 1.05,
        },
        grid: {
          show: false,
          padding: { left: 10, right: 10, top: -10, bottom: 0 },
        },
        tooltip: {
          enabled: true,
          y: {
            formatter: (val) => '£' + val.toLocaleString('en-GB'),
          },
        },
        legend: { show: false },
        dataLabels: { enabled: false },
      };
    },
  },
};
</script>
