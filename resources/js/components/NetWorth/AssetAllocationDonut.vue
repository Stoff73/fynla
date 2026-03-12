<template>
  <div class="asset-allocation-donut">
    <h3 class="chart-title">{{ title }}</h3>
    <div v-if="hasData" class="chart-container">
      <apexchart
        :key="chartKey"
        type="donut"
        :options="chartOptions"
        :series="filteredSeries"
        height="260"
      ></apexchart>
    </div>
    <div v-else class="no-data">
      <p>No wealth data available</p>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';
import { ASSET_COLORS, TEXT_COLORS, CHART_DEFAULTS } from '@/constants/designSystem';

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
    chartKey() {
      const total = this.filteredSeries?.reduce((a, b) => a + b, 0) || 0;
      return `donut-${this.filteredSeries?.length || 0}-${Math.round(total)}`;
    },

    hasData() {
      return this.filteredSeries.some(value => value > 0);
    },

    allCategories() {
      // All possible categories with their values and labels - using design system colors
      return [
        { label: 'Pensions', value: this.breakdown.pensions || 0, color: ASSET_COLORS.pensions },
        { label: 'Property', value: this.breakdown.property || 0, color: ASSET_COLORS.property },
        { label: 'Investments', value: this.breakdown.investments || 0, color: ASSET_COLORS.investments },
        { label: 'Cash & Savings', value: this.breakdown.cash || 0, color: ASSET_COLORS.cash },
        { label: 'Business', value: this.breakdown.business || 0, color: ASSET_COLORS.business },
        { label: 'Chattels', value: this.breakdown.chattels || 0, color: ASSET_COLORS.chattels },
      ];
    },

    filteredCategories() {
      // Filter out categories with zero values
      return this.allCategories.filter(cat => cat.value > 0);
    },

    filteredSeries() {
      // Array of values for non-zero categories
      return this.filteredCategories.map(cat => cat.value);
    },

    filteredLabels() {
      // Array of labels for non-zero categories
      return this.filteredCategories.map(cat => cat.label);
    },

    filteredColors() {
      // Array of colors for non-zero categories
      return this.filteredCategories.map(cat => cat.color);
    },

    // Map label back to breakdown key for highlight events
    filteredCategoryKeys() {
      const labelToKey = {
        'Pensions': 'pensions',
        'Property': 'property',
        'Investments': 'investments',
        'Cash & Savings': 'cash',
        'Business': 'business',
        'Chattels': 'chattels',
      };
      return this.filteredCategories.map(cat => labelToKey[cat.label]);
    },

    chartOptions() {
      const vm = this;
      return {
        chart: {
          type: 'donut',
          fontFamily: 'Segoe UI, Inter, system-ui, sans-serif',
          events: {
            dataPointMouseEnter(event, chartContext, config) {
              const idx = config.dataPointIndex;
              const key = vm.filteredCategoryKeys[idx];
              const color = vm.filteredColors[idx];
              if (key) vm.$emit('highlight', { category: key, color });
            },
            dataPointMouseLeave() {
              vm.$emit('clear-highlight');
            },
          },
        },
        labels: this.filteredLabels,
        colors: this.filteredColors,
        legend: {
          show: false,
        },
        dataLabels: {
          enabled: false,
        },
        plotOptions: {
          pie: {
            donut: {
              size: '65%',
              labels: {
                show: true,
                name: {
                  show: true,
                  fontSize: '12px',
                  fontWeight: 600,
                  formatter: () => 'Total',
                },
                value: {
                  show: true,
                  fontSize: '16px',
                  fontWeight: 700,
                  formatter: () => {
                    const total = this.filteredSeries.reduce((sum, val) => sum + val, 0);
                    return this.formatCurrency(total);
                  },
                },
                total: {
                  show: true,
                  showAlways: true,
                  label: 'Total',
                  fontSize: '11px',
                  fontWeight: 600,
                  color: TEXT_COLORS.muted,
                  formatter: () => {
                    const total = this.filteredSeries.reduce((sum, val) => sum + val, 0);
                    return this.formatCurrency(total);
                  },
                },
              },
            },
          },
        },
        tooltip: {
          y: {
            formatter: (val) => {
              const total = this.filteredSeries.reduce((a, b) => a + b, 0);
              const percent = total > 0 ? ((val / total) * 100).toFixed(1) : 0;
              return `${this.formatCurrency(val)} (${percent}%)`;
            },
          },
        },
        responsive: [
          {
            breakpoint: 768,
            options: {
              chart: {
                height: 240,
              },
            },
          },
        ],
      };
    },
  },

};
</script>

<style scoped>
.asset-allocation-donut {
  @apply bg-white rounded-card p-4 shadow-sm border border-light-gray transition-all duration-200;
  height: 340px;
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
