<template>
  <div class="px-4 py-3">
    <apexchart
      v-if="hasSeries"
      type="area"
      height="200"
      :options="chartOptions"
      :series="series"
    />
    <p v-else class="text-sm text-neutral-500 text-center py-4">No projection data</p>
  </div>
</template>

<script>
import { CHART_COLORS, TEXT_COLORS, BORDER_COLORS, CHART_DEFAULTS } from '@/constants/designSystem';
import { formatCurrency } from '@/utils/currency';

export default {
  name: 'MobileProjectionChart',

  props: {
    series: {
      type: Array,
      required: true,
      // Array of { name: String, data: Array<Number> }
    },
    categories: {
      type: Array,
      required: true,
      // Array of labels (years, dates, etc.)
    },
    yAxisLabel: { type: String, default: '' },
  },

  computed: {
    hasSeries() {
      return this.series && this.series.length > 0 && this.series.some(s => s.data?.length > 0);
    },

    chartOptions() {
      return {
        chart: {
          ...CHART_DEFAULTS.chart,
          type: 'area',
        },
        colors: CHART_COLORS,
        dataLabels: { enabled: false },
        stroke: {
          curve: 'smooth',
          width: 2,
        },
        fill: {
          type: 'gradient',
          gradient: {
            shadeIntensity: 1,
            opacityFrom: 0.3,
            opacityTo: 0.05,
            stops: [0, 100],
          },
        },
        xaxis: {
          categories: this.categories,
          labels: {
            style: { colors: TEXT_COLORS.muted, fontSize: '10px' },
            rotate: 0,
          },
          axisBorder: { show: false },
          axisTicks: { show: false },
        },
        yaxis: {
          labels: {
            style: { colors: TEXT_COLORS.muted, fontSize: '10px' },
            formatter: (val) => formatCurrency(val),
          },
          title: { text: this.yAxisLabel, style: { color: TEXT_COLORS.muted, fontSize: '10px' } },
        },
        grid: {
          borderColor: BORDER_COLORS.default,
          strokeDashArray: 3,
          xaxis: { lines: { show: false } },
        },
        legend: {
          position: 'bottom',
          fontSize: '11px',
          fontFamily: CHART_DEFAULTS.chart.fontFamily,
          labels: { colors: TEXT_COLORS.muted },
          markers: { size: 4, shape: 'circle' },
        },
        tooltip: {
          y: {
            formatter: (val) => formatCurrency(val),
          },
        },
      };
    },
  },
};
</script>
