<template>
  <div class="asset-allocation-donut">
    <h3 class="chart-title">{{ title }}</h3>
    <div v-if="hasData" class="chart-container">
      <div class="relative" style="width: 200px; height: 200px;">
        <svg viewBox="0 0 220 220" width="200" height="200">
          <defs>
            <linearGradient
              v-for="(seg, idx) in donutSegments"
              :key="'grad-' + idx"
              :id="gradientId(idx)"
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
            :stroke="'url(#' + gradientId(idx) + ')'"
            stroke-width="40"
            stroke-linecap="round"
            :stroke-dasharray="seg.arcLength + ' ' + 471.2"
            :stroke-dashoffset="-seg.offset"
            transform="rotate(-90 110 110)"
            class="cursor-pointer"
            @mouseenter="onSegmentHover(idx)"
            @mouseleave="onSegmentLeave"
          />
        </svg>
        <div class="absolute inset-0 flex flex-col items-center justify-center">
          <span class="text-[10px] font-semibold text-horizon-400">Total</span>
          <span class="text-base font-bold text-horizon-700">{{ formatCurrency(total) }}</span>
        </div>
      </div>
    </div>
    <div v-else class="no-data">
      <p>No wealth data available</p>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';
import { ASSET_COLORS } from '@/constants/designSystem';

export default {
  name: 'AssetAllocationDonut',
  mixins: [currencyMixin],

  emits: ['highlight', 'clear-highlight'],

  props: {
    breakdown: {
      type: Object,
      required: true,
      default: () => ({}),
    },
    title: {
      type: String,
      default: 'Wealth Allocation',
    },
  },

  computed: {
    hasData() {
      return this.filteredCategories.length > 0;
    },

    allCategories() {
      return [
        { key: 'pensions', label: 'Pensions', value: this.breakdown.pensions || 0, color: ASSET_COLORS.pensions },
        { key: 'property', label: 'Property', value: this.breakdown.property || 0, color: ASSET_COLORS.property },
        { key: 'investments', label: 'Investments', value: this.breakdown.investments || 0, color: ASSET_COLORS.investments },
        { key: 'cash', label: 'Cash & Savings', value: this.breakdown.cash || 0, color: ASSET_COLORS.cash },
        { key: 'business', label: 'Business', value: this.breakdown.business || 0, color: ASSET_COLORS.business },
        { key: 'chattels', label: 'Chattels', value: this.breakdown.chattels || 0, color: ASSET_COLORS.chattels },
      ];
    },

    filteredCategories() {
      return this.allCategories.filter(cat => cat.value > 0);
    },

    total() {
      return this.filteredCategories.reduce((sum, c) => sum + c.value, 0);
    },

    donutSegments() {
      if (this.total === 0) return [];

      const circumference = 471.2; // 2 * PI * 75
      const gap = 3;
      let offset = 0;
      return this.filteredCategories.map(cat => {
        const proportion = cat.value / this.total;
        const arcLength = Math.max(proportion * circumference - gap, 2);
        const seg = {
          color: cat.color,
          colorLight: this.lightenColor(cat.color, 0.35),
          arcLength,
          offset,
        };
        offset += proportion * circumference;
        return seg;
      });
    },
  },

  methods: {
    gradientId(idx) {
      return `nw-alloc-grad-${this._uid}-${idx}`;
    },

    lightenColor(hex, amount) {
      const r = parseInt(hex.slice(1, 3), 16);
      const g = parseInt(hex.slice(3, 5), 16);
      const b = parseInt(hex.slice(5, 7), 16);
      const lighten = (c) => Math.min(255, Math.round(c + (255 - c) * amount));
      return `#${lighten(r).toString(16).padStart(2, '0')}${lighten(g).toString(16).padStart(2, '0')}${lighten(b).toString(16).padStart(2, '0')}`;
    },

    onSegmentHover(idx) {
      const cat = this.filteredCategories[idx];
      if (cat) {
        this.$emit('highlight', { category: cat.key, color: cat.color });
      }
    },

    onSegmentLeave() {
      this.$emit('clear-highlight');
    },
  },
};
</script>

<style scoped>
.asset-allocation-donut {
  @apply bg-white rounded-card p-4 shadow-sm border border-light-gray transition-all duration-200;
  height: 255px;
  display: flex;
  flex-direction: column;
  overflow: visible;
  position: relative;
  z-index: 1;
}

.asset-allocation-donut:hover {
  z-index: 100;
}

.chart-container {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: visible;
}

.chart-title {
  @apply text-xs font-semibold text-horizon-500 mb-2;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.no-data {
  @apply text-center py-12 px-5 text-horizon-400;
}

.no-data p {
  @apply m-0 text-sm;
}

@media (max-width: 768px) {
  .asset-allocation-donut {
    @apply p-4;
  }

  .chart-title {
    @apply text-base;
  }
}
</style>
