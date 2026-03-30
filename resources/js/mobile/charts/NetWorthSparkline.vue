<template>
  <div class="h-12">
    <apexchart
      type="area"
      height="48"
      :options="chartOptions"
      :series="series"
    />
  </div>
</template>

<script>
import { CHART_COLORS, CHART_DEFAULTS } from '@/constants/designSystem';

export default {
  name: 'NetWorthSparkline',
  props: {
    data: { type: Array, required: true },
  },
  computed: {
    series() {
      return [{ data: this.data.map(d => d.value || d) }];
    },
    chartOptions() {
      return {
        chart: {
          ...CHART_DEFAULTS.chart,
          type: 'area',
          sparkline: { enabled: true },
          animations: { enabled: false },
        },
        stroke: { curve: 'smooth', width: 2 },
        fill: {
          type: 'gradient',
          gradient: { opacityFrom: 0.3, opacityTo: 0 },
        },
        colors: [CHART_COLORS[0]],
        tooltip: { enabled: false },
        xaxis: { labels: { show: false } },
        yaxis: { labels: { show: false } },
      };
    },
  },
};
</script>
