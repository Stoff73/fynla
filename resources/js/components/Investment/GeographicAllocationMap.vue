<template>
  <div class="geographic-allocation-map">
    <div class="flex justify-between items-center mb-4">
      <h3 class="text-lg font-semibold text-horizon-500">Geographic Allocation</h3>
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

    <div v-else-if="hasData && !loading && chartReady" class="chart-container">
      <apexchart
        :key="chartKey"
        type="bar"
        :options="chartOptions"
        :series="series"
        height="350"
      />
    </div>

    <div v-else class="flex items-center justify-center h-64 text-neutral-500">
      <div class="text-center">
        <svg class="mx-auto h-12 w-12 text-horizon-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <p>No geographic data available</p>
        <p class="text-sm mt-1">Add holdings to see geographic allocation</p>
      </div>
    </div>
  </div>
</template>

<script>
import VueApexCharts from 'vue3-apexcharts';
import { currencyMixin } from '@/mixins/currencyMixin';
import { CHART_COLORS, TEXT_COLORS, BORDER_COLORS, CHART_DEFAULTS } from '@/constants/designSystem';

export default {
  name: 'GeographicAllocationMap',

  emits: ['view-details'],

  mixins: [currencyMixin],

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

    sortedRegions() {
      if (!this.hasData) return [];

      // Convert to array and sort by value descending
      return Object.entries(this.allocation)
        .map(([region, data]) => ({
          region,
          percentage: typeof data === 'object' ? data.percentage : data,
          value: typeof data === 'object' ? data.value : 0,
        }))
        .sort((a, b) => b.percentage - a.percentage);
    },

    chartKey() {
      const total = this.sortedRegions.reduce((sum, r) => sum + (r.percentage || 0), 0);
      return `geo-alloc-${this.sortedRegions.length}-${Math.round(total)}`;
    },

    series() {
      if (!this.hasData) return [];

      return [
        {
          name: 'Allocation %',
          data: this.sortedRegions.map(item => item.percentage),
        },
      ];
    },

    chartOptions() {
      const regions = this.sortedRegions.map(item => this.formatRegionName(item.region));

      return {
        chart: {
          ...CHART_DEFAULTS.chart,
          type: 'bar',
        },
        plotOptions: {
          bar: {
            horizontal: true,
            borderRadius: 4,
            barHeight: '70%',
            distributed: true,
            dataLabels: {
              position: 'top',
            },
          },
        },
        colors: CHART_COLORS,
        dataLabels: {
          enabled: true,
          formatter: (val) => `${val.toFixed(1)}%`,
          offsetX: 30,
          style: {
            fontSize: '12px',
            fontWeight: 600,
            colors: [TEXT_COLORS.secondary],
          },
        },
        xaxis: {
          categories: regions,
          labels: {
            formatter: (val) => `${val}%`,
            style: {
              colors: TEXT_COLORS.muted,
              fontSize: '12px',
            },
          },
          max: Math.max(100, ...this.sortedRegions.map(r => r.percentage)),
        },
        yaxis: {
          labels: {
            style: {
              colors: TEXT_COLORS.secondary,
              fontSize: '13px',
              fontWeight: 500,
            },
          },
        },
        tooltip: {
          enabled: true,
          y: {
            formatter: (val, opts) => {
              const region = this.sortedRegions[opts.dataPointIndex];
              return `${val.toFixed(2)}% (${this.formatCurrency(region.value)})`;
            },
          },
        },
        legend: {
          show: false,
        },
        grid: {
          borderColor: BORDER_COLORS.default,
          strokeDashArray: 3,
          xaxis: {
            lines: {
              show: true,
            },
          },
          yaxis: {
            lines: {
              show: false,
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
              plotOptions: {
                bar: {
                  barHeight: '60%',
                },
              },
              dataLabels: {
                style: {
                  fontSize: '10px',
                },
              },
            },
          },
        ],
      };
    },
  },

  methods: {
    formatRegionName(region) {
      const regionNames = {
        uk: 'United Kingdom',
        us: 'United States',
        europe: 'Europe',
        asia: 'Asia Pacific',
        emerging_markets: 'Emerging Markets',
        developed_markets: 'Developed Markets',
        global: 'Global',
        other: 'Other',
      };

      return regionNames[region.toLowerCase()] || region
        .split('_')
        .map(word => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
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
