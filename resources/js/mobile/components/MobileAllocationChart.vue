<template>
  <div class="px-4 py-3">
    <div v-if="hasData" class="flex justify-center">
      <div class="relative" style="width: 180px; height: 180px;">
        <svg viewBox="0 0 220 220" width="180" height="180">
          <defs>
            <linearGradient
              v-for="(seg, idx) in donutSegments"
              :key="'grad-' + idx"
              :id="'mob-alloc-grad-' + idx"
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
            :stroke="'url(#mob-alloc-grad-' + idx + ')'"
            stroke-width="40"
            stroke-linecap="round"
            :stroke-dasharray="seg.arcLength + ' ' + 471.2"
            :stroke-dashoffset="-seg.offset"
            transform="rotate(-90 110 110)"
          />
        </svg>
      </div>
    </div>
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

    donutSegments() {
      const total = this.series.reduce((sum, v) => sum + v, 0);
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
