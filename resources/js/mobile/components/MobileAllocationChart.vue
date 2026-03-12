<template>
  <div class="px-4 py-3">
    <apexchart
      v-if="hasData"
      type="donut"
      height="200"
      :options="chartOptions"
      :series="series"
    />
    <p v-else class="text-sm text-neutral-500 text-center py-4">No allocation data</p>

    <!-- Legend rows -->
    <div v-if="hasData" class="mt-3 space-y-1.5">
      <div
        v-for="(item, index) in items"
        :key="item.label"
        class="flex items-center justify-between"
      >
        <div class="flex items-center gap-2">
          <span class="w-2.5 h-2.5 rounded-full" :style="{ backgroundColor: colors[index % colors.length] }"></span>
          <span class="text-xs text-neutral-500">{{ item.label }}</span>
        </div>
        <span class="text-xs font-medium text-horizon-500">{{ item.percentage.toFixed(1) }}%</span>
      </div>
    </div>
  </div>
</template>

<script>
import { CHART_COLORS } from '@/constants/designSystem';

export default {
  name: 'MobileAllocationChart',

  props: {
    items: {
      type: Array,
      required: true,
      // Each item: { label: String, value: Number, percentage: Number }
    },
  },

  computed: {
    colors() {
      return CHART_COLORS;
    },

    hasData() {
      return this.items && this.items.length > 0;
    },

    series() {
      return this.items.map(i => i.value || 0);
    },

    chartOptions() {
      return {
        chart: {
          type: 'donut',
          sparkline: { enabled: true },
        },
        colors: this.colors,
        labels: this.items.map(i => i.label),
        legend: { show: false },
        dataLabels: { enabled: false },
        tooltip: {
          enabled: true,
          y: {
            formatter: (val) => `${val.toFixed(1)}%`,
          },
        },
        plotOptions: {
          pie: {
            donut: {
              size: '55%',
            },
          },
        },
        stroke: {
          width: 2,
          colors: ['#fff'],
        },
      };
    },
  },
};
</script>
