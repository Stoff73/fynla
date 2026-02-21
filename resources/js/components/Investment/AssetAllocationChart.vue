<template>
  <div class="asset-allocation-chart">
    <div class="flex justify-between items-center mb-4">
      <h3 class="text-lg font-semibold text-gray-900">Asset Allocation</h3>
      <button
        v-if="showViewDetails"
        class="text-sm text-blue-600 hover:text-blue-800"
        @click="$emit('view-details')"
      >
        View Details
      </button>
    </div>

    <div v-if="loading" class="flex items-center justify-center h-64">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
    </div>

    <div v-else-if="hasData && !loading && chartReady" class="chart-container">
      <apexchart
        :key="chartKey"
        type="donut"
        :options="chartOptions"
        :series="series"
        height="350"
      />
    </div>

    <div v-else class="flex items-center justify-center h-64 text-gray-500">
      <div class="text-center max-w-md p-6">
        <svg class="mx-auto h-16 w-16 text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" />
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" />
        </svg>
        <h4 class="text-lg font-semibold text-gray-900 mb-2">No Asset Allocation Data</h4>
        <p class="text-sm text-gray-600 mb-4">
          Add your investment holdings to see a breakdown of your asset allocation across different asset classes.
        </p>
        <button
          @click="$emit('add-holding')"
          class="px-4 py-2 bg-primary-600 text-white text-sm font-medium rounded-button hover:bg-primary-700 transition-colors"
        >
          Add Your First Holding
        </button>
        <div class="mt-6 text-left bg-gray-50 rounded-lg p-4">
          <p class="text-xs font-medium text-gray-700 mb-2">Typical Asset Classes:</p>
          <ul class="text-xs text-gray-600 space-y-1">
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
import VueApexCharts from 'vue3-apexcharts';
import { CHART_COLORS, TEXT_COLORS } from '@/constants/designSystem';

export default {
  name: 'AssetAllocationChart',

  emits: ['view-details', 'add-holding'],

  components: {
    apexchart: VueApexCharts,
  },

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
      chartReady: false,
      renderTimeout: null,
    };
  },

  computed: {
    hasData() {
      return this.allocation && Object.keys(this.allocation).length > 0;
    },

    chartKey() {
      const total = this.series?.reduce((a, b) => a + b, 0) || 0;
      return `asset-donut-${this.series?.length || 0}-${Math.round(total)}`;
    },

    series() {
      if (!this.hasData) return [];

      return Object.values(this.allocation).map(item =>
        typeof item === 'object' ? item.percentage : item
      );
    },

    chartOptions() {
      const labels = Object.keys(this.allocation).map(key => {
        // Convert snake_case to Title Case
        return key.split('_')
          .map(word => word.charAt(0).toUpperCase() + word.slice(1))
          .join(' ');
      });

      return {
        chart: {
          type: 'donut',
          fontFamily: 'Inter, system-ui, sans-serif',
          toolbar: {
            show: false,
          },
        },
        labels: labels,
        colors: CHART_COLORS,
        plotOptions: {
          pie: {
            donut: {
              size: '65%',
              labels: {
                show: true,
                name: {
                  show: true,
                  fontSize: '14px',
                  fontWeight: 600,
                  color: TEXT_COLORS.secondary,
                },
                value: {
                  show: true,
                  fontSize: '24px',
                  fontWeight: 700,
                  color: TEXT_COLORS.primary,
                  formatter: (val) => `${val.toFixed(1)}%`,
                },
                total: {
                  show: true,
                  label: 'Total Value',
                  fontSize: '14px',
                  fontWeight: 500,
                  color: TEXT_COLORS.muted,
                  formatter: () => {
                    const total = this.series.reduce((sum, val) => sum + val, 0);
                    return `${total.toFixed(1)}%`;
                  },
                },
              },
            },
          },
        },
        dataLabels: {
          enabled: false,
        },
        legend: {
          position: 'bottom',
          fontSize: '14px',
          fontWeight: 500,
          labels: {
            colors: TEXT_COLORS.secondary,
          },
          markers: {
            width: 12,
            height: 12,
            radius: 3,
          },
          itemMargin: {
            horizontal: 10,
            vertical: 5,
          },
          formatter: (seriesName, opts) => {
            const value = opts.w.globals.series[opts.seriesIndex];
            return `${seriesName}: ${value.toFixed(1)}%`;
          },
        },
        tooltip: {
          enabled: true,
          y: {
            formatter: (val) => `${val.toFixed(2)}%`,
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
                fontSize: '12px',
              },
            },
          },
        ],
      };
    },
  },

  mounted() {
    this.$nextTick(() => {
      // Delay chart rendering to ensure DOM is ready
      this.renderTimeout = setTimeout(() => {
        this.chartReady = true;
      }, 100);
    });
  },

  beforeUnmount() {
    if (this.renderTimeout) clearTimeout(this.renderTimeout);
  },
};
</script>

<style scoped>
.chart-container {
  width: 100%;
}
</style>
