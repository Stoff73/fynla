<template>
  <div class="asset-allocation-donut">
    <h3 class="chart-title">Wealth Allocation</h3>
    <div v-if="hasData" class="chart-container">
      <apexchart
        type="donut"
        :options="chartOptions"
        :series="filteredSeries"
        height="350"
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

  props: {
    breakdown: {
      type: Object,
      required: true,
      default: () => ({}),
    },
  },

  computed: {
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

    chartOptions() {
      return {
        chart: {
          type: 'donut',
          fontFamily: 'Inter, system-ui, sans-serif',
        },
        labels: this.filteredLabels,
        colors: this.filteredColors,
        legend: {
          position: 'bottom',
          fontSize: '14px',
        },
        dataLabels: {
          enabled: true,
          formatter: (val) => {
            return val.toFixed(1) + '%';
          },
        },
        plotOptions: {
          pie: {
            donut: {
              size: '65%',
              labels: {
                show: true,
                name: {
                  show: true,
                  fontSize: '16px',
                  fontWeight: 600,
                },
                value: {
                  show: true,
                  fontSize: '24px',
                  fontWeight: 700,
                  formatter: (val) => {
                    return this.formatCurrency(val);
                  },
                },
                total: {
                  show: true,
                  label: 'Total Wealth',
                  fontSize: '14px',
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
              return this.formatCurrency(val);
            },
          },
        },
        responsive: [
          {
            breakpoint: 768,
            options: {
              chart: {
                height: 300,
              },
              legend: {
                position: 'bottom',
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
  @apply bg-white rounded-card p-6 shadow-sm border border-gray-200 transition-all duration-200;
}

.chart-title {
  @apply text-lg font-semibold text-gray-900 mb-5;
}

.chart-container {
  @apply w-full;
}

.no-data {
  @apply text-center py-12 px-5 text-gray-400;
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
