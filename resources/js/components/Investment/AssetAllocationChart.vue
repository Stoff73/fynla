<template>
  <div class="asset-allocation-chart">
    <div class="flex justify-between items-center mb-4">
      <h3 class="text-lg font-semibold text-horizon-500">Asset Allocation</h3>
      <button
        v-if="showViewDetails"
        class="text-sm text-violet-600 hover:text-violet-800"
        @click="$emit('view-details')"
      >
        View Details
      </button>
    </div>

    <div v-if="loading" class="flex items-center justify-center h-64">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-raspberry-500"></div>
    </div>

    <div v-else-if="hasData && !loading" class="chart-container">
      <div class="flex justify-center">
        <div class="relative" style="width: 300px; height: 300px;">
          <svg viewBox="0 0 220 220" width="300" height="300">
            <defs>
              <linearGradient
                v-for="(seg, idx) in donutSegments"
                :key="'grad-' + idx"
                :id="'asset-alloc-grad-' + idx"
                x1="0%" y1="0%" x2="100%" y2="0%"
              >
                <stop offset="0%" :stop-color="seg.color" />
                <stop offset="100%" :stop-color="seg.colorLight" />
              </linearGradient>
            </defs>
            <circle
              v-for="(seg, idx) in donutSegments"
              :key="'seg-' + idx"
              cx="110" cy="110" r="75"
              fill="none"
              :stroke="'url(#asset-alloc-grad-' + idx + ')'"
              stroke-width="40"
              stroke-linecap="round"
              :stroke-dasharray="seg.arcLength + ' ' + 471.2"
              :stroke-dashoffset="-seg.offset"
              transform="rotate(-90 110 110)"
              class="cursor-pointer"
              @mouseenter="hoveredIndex = idx"
              @mouseleave="hoveredIndex = null"
            />
          </svg>
          <div class="absolute inset-0 flex flex-col items-center justify-center">
            <template v-if="hoveredIndex !== null && hoveredIndex < chartLabels.length">
              <span class="text-[10px] font-semibold text-horizon-400">{{ chartLabels[hoveredIndex] }}</span>
              <span class="text-xl font-bold text-horizon-700">{{ series[hoveredIndex].toFixed(1) }}%</span>
            </template>
            <template v-else>
              <span class="text-[10px] font-semibold text-horizon-400">Total</span>
              <span class="text-xl font-bold text-horizon-700">{{ totalPercent.toFixed(1) }}%</span>
            </template>
          </div>
        </div>
      </div>
      <!-- Legend -->
      <div class="mt-4 flex flex-wrap justify-center gap-x-6 gap-y-2">
        <div v-for="(label, idx) in chartLabels" :key="label" class="flex items-center gap-2">
          <span
            class="w-3 h-3 rounded-full flex-shrink-0"
            :style="{ backgroundColor: chartColors[idx % chartColors.length] }"
          ></span>
          <span class="text-sm text-neutral-500">{{ label }}: {{ series[idx].toFixed(1) }}%</span>
        </div>
      </div>
    </div>

    <div v-else class="flex items-center justify-center h-64 text-neutral-500">
      <div class="text-center max-w-md p-6">
        <svg class="mx-auto h-16 w-16 text-horizon-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" />
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" />
        </svg>
        <h4 class="text-lg font-semibold text-horizon-500 mb-2">No Asset Allocation Data</h4>
        <p class="text-sm text-neutral-500 mb-4">
          Add your investment holdings to see a breakdown of your asset allocation across different asset classes.
        </p>
        <button
          @click="$emit('add-holding')"
          class="px-4 py-2 bg-raspberry-500 text-white text-sm font-medium rounded-button hover:bg-raspberry-600 transition-colors"
        >
          Add Your First Holding
        </button>
        <div class="mt-6 text-left bg-eggshell-500 rounded-lg p-4">
          <p class="text-xs font-medium text-neutral-500 mb-2">Typical Asset Classes:</p>
          <ul class="text-xs text-neutral-500 space-y-1">
            <li>• UK Equities (Stocks)</li>
            <li>• International Equities</li>
            <li>• Bonds (Fixed Income)</li>
            <li>• Cash & Money Market</li>
            <li>• Property & Alternatives</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { CHART_COLORS } from '@/constants/designSystem';

export default {
  name: 'AssetAllocationChart',

  emits: ['view-details', 'add-holding'],

  props: {
    allocation: {
      type: Object,
      required: true,
      default: () => ({}),
    },
    loading: {
      type: Boolean,
      default: false,
    },
    showViewDetails: {
      type: Boolean,
      default: false,
    },
  },

  data() {
    return {
      hoveredIndex: null,
      chartColors: CHART_COLORS,
    };
  },

  computed: {
    hasData() {
      return this.allocation && Object.keys(this.allocation).length > 0;
    },

    series() {
      if (!this.hasData) return [];
      return Object.values(this.allocation).map(item =>
        typeof item === 'object' ? item.percentage : item
      );
    },

    chartLabels() {
      return Object.keys(this.allocation).map(key => {
        return key.split('_')
          .map(word => word.charAt(0).toUpperCase() + word.slice(1))
          .join(' ');
      });
    },

    totalPercent() {
      return this.series.reduce((sum, val) => sum + val, 0);
    },

    donutSegments() {
      const total = this.totalPercent;
      if (total === 0) return [];

      const circumference = 471.2;
      const gap = 3;
      let offset = 0;
      return this.series.map((value, idx) => {
        const proportion = value / total;
        const arcLength = Math.max(proportion * circumference - gap, 2);
        const color = CHART_COLORS[idx % CHART_COLORS.length];
        const seg = {
          color,
          colorLight: this.lightenColor(color, 0.35),
          arcLength,
          offset,
        };
        offset += proportion * circumference;
        return seg;
      });
    },
  },

  methods: {
    lightenColor(hex, amount) {
      const r = parseInt(hex.slice(1, 3), 16);
      const g = parseInt(hex.slice(3, 5), 16);
      const b = parseInt(hex.slice(5, 7), 16);
      const lighten = (c) => Math.min(255, Math.round(c + (255 - c) * amount));
      return `#${lighten(r).toString(16).padStart(2, '0')}${lighten(g).toString(16).padStart(2, '0')}${lighten(b).toString(16).padStart(2, '0')}`;
    },
  },
};
</script>

<style scoped>
.chart-container {
  width: 100%;
}
</style>
