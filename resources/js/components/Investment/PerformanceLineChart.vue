<template>
  <div class="performance-line-chart">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-4">
      <h3 class="text-lg font-semibold text-horizon-500">Portfolio Performance</h3>

      <div class="flex items-center gap-2">
        <label for="time-period" class="text-sm font-medium text-neutral-500">Period:</label>
        <select
          id="time-period"
          v-model="selectedPeriod"
          class="border border-horizon-300 rounded-md px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500"
        >
          <option value="1m">1 Month</option>
          <option value="3m">3 Months</option>
          <option value="6m">6 Months</option>
          <option value="ytd">Year to Date</option>
          <option value="1y">1 Year</option>
          <option value="3y">3 Years</option>
          <option value="5y">5 Years</option>
          <option value="all">All Time</option>
        </select>
      </div>
    </div>

    <div v-if="loading" class="flex items-center justify-center h-96">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-raspberry-500"></div>
    </div>

    <div v-else-if="hasData && !loading" class="chart-container">
      <apexchart
        v-if="hasData"
        :key="chartKey"
        type="line"
        :options="chartOptions"
        :series="series"
        height="400"
      />
    </div>

    <div v-else class="flex items-center justify-center h-96 text-neutral-500 border border-light-gray rounded-lg">
      <div class="text-center">
        <svg class="mx-auto h-12 w-12 text-horizon-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" />
        </svg>
        <p>No performance data available</p>
        <p class="text-sm mt-1">Historical performance will appear here</p>
      </div>
    </div>
  </div>
</template>

<script>
import VueApexCharts from 'vue3-apexcharts';
import { currencyMixin } from '@/mixins/currencyMixin';
import { CHART_COLORS, TEXT_COLORS, BORDER_COLORS, CHART_DEFAULTS } from '@/constants/designSystem';

export default {
  name: 'PerformanceLineChart',
  mixins: [currencyMixin],

  components: {
    apexchart: VueApexCharts,
  },

  props: {
    performanceData: {
      type: Array,
      required: true,
      default: () => [],
    },
    benchmarks: {
      type: Object,
      default: () => ({
        ftse_all_share: [],
        sp_500: [],
      }),
    },
    loading: {
      type: Boolean,
      default: false,
    },
  },

  data() {
    return {
      selectedPeriod: 'ytd',
    };
  },

  computed: {
    hasData() {
      return this.performanceData && this.performanceData.length > 0;
    },

    chartKey() {
      return `perf-line-${this.selectedPeriod}-${this.filteredData?.length || 0}-${Math.round(this.filteredData?.[this.filteredData?.length - 1]?.value || 0)}`;
    },

    filteredData() {
      if (!this.hasData) return [];

      const now = new Date();
      let startDate = new Date();

      switch (this.selectedPeriod) {
        case '1m':
          startDate.setMonth(now.getMonth() - 1);
          break;
        case '3m':
          startDate.setMonth(now.getMonth() - 3);
          break;
        case '6m':
          startDate.setMonth(now.getMonth() - 6);
          break;
        case 'ytd':
          startDate = new Date(now.getFullYear(), 0, 1);
          break;
        case '1y':
          startDate.setFullYear(now.getFullYear() - 1);
          break;
        case '3y':
          startDate.setFullYear(now.getFullYear() - 3);
          break;
        case '5y':
          startDate.setFullYear(now.getFullYear() - 5);
          break;
        case 'all':
          return this.performanceData;
      }

      return this.performanceData.filter(point => new Date(point.date) >= startDate);
    },

    series() {
      const portfolioSeries = {
        name: 'Portfolio',
        data: this.filteredData.map(point => ({
          x: new Date(point.date).getTime(),
          y: point.value,
        })),
      };

      const series = [portfolioSeries];

      // Add FTSE All-Share benchmark if available
      if (this.benchmarks.ftse_all_share && this.benchmarks.ftse_all_share.length > 0) {
        series.push({
          name: 'FTSE All-Share',
          data: this.filterBenchmarkData(this.benchmarks.ftse_all_share),
        });
      }

      // Add S&P 500 benchmark if available
      if (this.benchmarks.sp_500 && this.benchmarks.sp_500.length > 0) {
        series.push({
          name: 'S&P 500',
          data: this.filterBenchmarkData(this.benchmarks.sp_500),
        });
      }

      return series;
    },

    chartOptions() {
      return {
        chart: {
          ...CHART_DEFAULTS.chart,
          type: 'line',
          zoom: {
            enabled: true,
            type: 'x',
            autoScaleYaxis: true,
          },
          toolbar: {
            show: true,
            tools: {
              download: true,
              selection: true,
              zoom: true,
              zoomin: true,
              zoomout: true,
              pan: true,
              reset: true,
            },
          },
        },
        colours: CHART_COLORS.slice(0, 3),
        stroke: {
          width: [3, 2, 2],
          curve: 'smooth',
        },
        xaxis: {
          type: 'datetime',
          labels: {
            format: 'MMM yyyy',
            style: {
              colours: TEXT_COLORS.muted,
              fontSize: '12px',
            },
          },
        },
        yaxis: {
          labels: {
            formatter: (val) => this.formatCurrency(val),
            style: {
              colours: TEXT_COLORS.muted,
              fontSize: '12px',
            },
          },
        },
        tooltip: {
          enabled: true,
          shared: true,
          intersect: false,
          x: {
            format: 'dd MMM yyyy',
          },
          y: {
            formatter: (val) => this.formatCurrency(val),
          },
        },
        legend: {
          position: 'top',
          horizontalAlign: 'left',
          fontSize: '14px',
          fontWeight: 500,
          labels: {
            colours: TEXT_COLORS.secondary,
          },
          markers: {
            width: 12,
            height: 12,
            radius: 3,
          },
        },
        grid: {
          borderColour: BORDER_COLORS.default,
          strokeDashArray: 3,
          xaxis: {
            lines: {
              show: true,
            },
          },
          yaxis: {
            lines: {
              show: true,
            },
          },
        },
        dataLabels: {
          enabled: false,
        },
        markers: {
          size: 0,
          hover: {
            size: 5,
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

  methods: {
    filterBenchmarkData(benchmarkData) {
      if (!this.filteredData || this.filteredData.length === 0) return [];

      const startDate = new Date(this.filteredData[0].date);
      const endDate = new Date(this.filteredData[this.filteredData.length - 1].date);

      return benchmarkData
        .filter(point => {
          const pointDate = new Date(point.date);
          return pointDate >= startDate && pointDate <= endDate;
        })
        .map(point => ({
          x: new Date(point.date).getTime(),
          y: point.value,
        }));
    },

  },
};
</script>

<style scoped>
.chart-container {
  width: 100%;
}
</style>
