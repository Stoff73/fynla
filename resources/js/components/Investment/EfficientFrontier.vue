<template>
  <div class="efficient-frontier">
    <div class="flex justify-between items-center mb-6">
      <div>
        <h3 class="text-lg font-semibold text-gray-900">Efficient Frontier</h3>
        <p class="text-sm text-gray-600 mt-1">
          Modern Portfolio Theory - Optimal risk-return trade-offs for your portfolio
        </p>
      </div>
      <div class="flex gap-2">
        <button
          @click="refreshFrontier"
          :disabled="loading"
          class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50"
        >
          {{ loading ? 'Calculating...' : 'Refresh' }}
        </button>
        <button
          @click="$emit('view-optimiser')"
          class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700"
        >
          Optimise Portfolio
        </button>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center h-96">
      <div class="text-center">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
        <p class="text-sm text-gray-600">Calculating efficient frontier...</p>
      </div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-lg p-6">
      <div class="flex">
        <svg class="h-6 w-6 text-red-600 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <div>
          <h4 class="text-sm font-medium text-red-800 mb-1">Unable to Calculate Efficient Frontier</h4>
          <p class="text-sm text-red-700">{{ error }}</p>
        </div>
      </div>
    </div>

    <!-- Chart Display -->
    <div v-else-if="hasData" class="space-y-6">
      <!-- Chart -->
      <div class="bg-white rounded-lg border border-gray-200 p-6">
        <apexchart
          type="scatter"
          :options="chartOptions"
          :series="chartSeries"
          height="450"
        />
      </div>

      <!-- Portfolio Comparison Cards -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Current Portfolio Card -->
        <div class="bg-white rounded-lg border border-gray-200 p-4">
          <div class="flex items-center justify-between mb-3">
            <h4 class="text-sm font-semibold text-gray-900">Current Portfolio</h4>
            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
              Current
            </span>
          </div>
          <div class="space-y-2">
            <div class="flex justify-between text-sm">
              <span class="text-gray-600">Expected Return:</span>
              <span class="font-medium text-gray-900">{{ formatPercentage(frontierData.current_portfolio.expected_return) }}</span>
            </div>
            <div class="flex justify-between text-sm">
              <span class="text-gray-600">Expected Risk:</span>
              <span class="font-medium text-gray-900">{{ formatPercentage(frontierData.current_portfolio.expected_risk) }}</span>
            </div>
            <div class="flex justify-between text-sm">
              <span class="text-gray-600">Sharpe Ratio:</span>
              <span class="font-medium text-gray-900">{{ frontierData.current_portfolio.sharpe_ratio?.toFixed(2) || 'N/A' }}</span>
            </div>
          </div>
        </div>

        <!-- Tangency Portfolio Card -->
        <div class="bg-blue-50 rounded-lg border border-blue-200 p-4">
          <div class="flex items-center justify-between mb-3">
            <h4 class="text-sm font-semibold text-blue-900">Optimal Portfolio</h4>
            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-600 text-white">
              Max Sharpe
            </span>
          </div>
          <div class="space-y-2">
            <div class="flex justify-between text-sm">
              <span class="text-blue-700">Expected Return:</span>
              <span class="font-medium text-blue-900">{{ formatPercentage(frontierData.tangency_portfolio.expected_return) }}</span>
            </div>
            <div class="flex justify-between text-sm">
              <span class="text-blue-700">Expected Risk:</span>
              <span class="font-medium text-blue-900">{{ formatPercentage(frontierData.tangency_portfolio.expected_risk) }}</span>
            </div>
            <div class="flex justify-between text-sm">
              <span class="text-blue-700">Sharpe Ratio:</span>
              <span class="font-medium text-blue-900">{{ frontierData.tangency_portfolio.sharpe_ratio?.toFixed(2) }}</span>
            </div>
          </div>
        </div>

        <!-- Minimum Variance Portfolio Card -->
        <div class="bg-green-50 rounded-lg border border-green-200 p-4">
          <div class="flex items-center justify-between mb-3">
            <h4 class="text-sm font-semibold text-green-900">Min Risk Portfolio</h4>
            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-600 text-white">
              Min Variance
            </span>
          </div>
          <div class="space-y-2">
            <div class="flex justify-between text-sm">
              <span class="text-green-700">Expected Return:</span>
              <span class="font-medium text-green-900">{{ formatPercentage(frontierData.minimum_variance_portfolio.expected_return) }}</span>
            </div>
            <div class="flex justify-between text-sm">
              <span class="text-green-700">Expected Risk:</span>
              <span class="font-medium text-green-900">{{ formatPercentage(frontierData.minimum_variance_portfolio.expected_risk) }}</span>
            </div>
            <div class="flex justify-between text-sm">
              <span class="text-green-700">Sharpe Ratio:</span>
              <span class="font-medium text-green-900">N/A</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Improvement Opportunities -->
      <div v-if="frontierData.improvement_opportunities" class="bg-amber-50 border border-amber-200 rounded-lg p-6">
        <h4 class="text-sm font-semibold text-amber-900 mb-3">Improvement Opportunities</h4>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <p class="text-sm text-amber-800 mb-2">
              <span class="font-medium">Sharpe Improvement:</span>
              {{ frontierData.improvement_opportunities.sharpe_improvement > 0 ? '+' : '' }}{{ frontierData.improvement_opportunities.sharpe_improvement.toFixed(4) }}
              ({{ frontierData.improvement_opportunities.sharpe_improvement_percent.toFixed(1) }}%)
            </p>
            <p class="text-sm text-amber-800">
              <span class="font-medium">Potential Risk Reduction:</span>
              {{ formatPercentage(frontierData.improvement_opportunities.potential_risk_reduction) }}
            </p>
          </div>
          <div>
            <p class="text-sm text-amber-900 italic">
              {{ frontierData.improvement_opportunities.recommendation }}
            </p>
          </div>
        </div>
      </div>

      <!-- Diversification Metrics -->
      <div v-if="frontierData.diversification" class="bg-white rounded-lg border border-gray-200 p-6">
        <h4 class="text-sm font-semibold text-gray-900 mb-3">Diversification Analysis</h4>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <p class="text-xs text-gray-600 mb-1">Diversification Ratio</p>
            <p class="text-2xl font-bold text-gray-900">{{ frontierData.diversification.diversification_ratio.toFixed(2) }}</p>
            <p class="text-xs text-gray-600 mt-1">{{ frontierData.diversification.interpretation }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-600 mb-1">Risk Reduction</p>
            <p class="text-2xl font-bold text-green-600">{{ formatPercentage(frontierData.diversification.risk_reduction) }}</p>
            <p class="text-xs text-gray-600 mt-1">vs. weighted average risk</p>
          </div>
          <div>
            <p class="text-xs text-gray-600 mb-1">Risk Reduction %</p>
            <p class="text-2xl font-bold text-green-600">{{ frontierData.diversification.risk_reduction_percent.toFixed(1) }}%</p>
            <p class="text-xs text-gray-600 mt-1">benefit from diversification</p>
          </div>
        </div>
      </div>
    </div>

    <!-- No Data State -->
    <div v-else class="flex items-center justify-center h-96">
      <div class="text-center max-w-md">
        <svg class="mx-auto h-16 w-16 text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
        </svg>
        <h4 class="text-lg font-semibold text-gray-900 mb-2">Insufficient Data</h4>
        <p class="text-sm text-gray-600 mb-4">
          Add at least 2 holdings with historical returns to calculate the efficient frontier.
        </p>
        <button
          @click="$emit('add-holdings')"
          class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700"
        >
          Add Holdings
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import VueApexCharts from 'vue3-apexcharts';
import portfolioOptimizationService from '@/services/portfolioOptimizationService';

export default {
  name: 'EfficientFrontier',

  components: {
    apexchart: VueApexCharts,
  },

  props: {
    riskFreeRate: {
      type: Number,
      default: 0.045, // UK Gilts ~4.5%
    },
    numPoints: {
      type: Number,
      default: 50,
    },
  },

  data() {
    return {
      loading: false,
      error: null,
      frontierData: null,
    };
  },

  computed: {
    isPreviewMode() {
      return this.$store.getters['preview/isPreviewMode'];
    },
    hasData() {
      return this.frontierData && this.frontierData.frontier_points && this.frontierData.frontier_points.length > 0;
    },

    chartSeries() {
      if (!this.hasData) return [];

      const series = [];

      // Efficient Frontier Line
      series.push({
        name: 'Efficient Frontier',
        data: this.frontierData.frontier_points.map(point => ({
          x: point.risk * 100, // Convert to percentage
          y: point.return * 100,
        })),
      });

      // Current Portfolio Point
      if (this.frontierData.current_portfolio) {
        series.push({
          name: 'Current Portfolio',
          data: [{
            x: this.frontierData.current_portfolio.expected_risk * 100,
            y: this.frontierData.current_portfolio.expected_return * 100,
          }],
        });
      }

      // Tangency Portfolio (Max Sharpe)
      if (this.frontierData.tangency_portfolio) {
        series.push({
          name: 'Optimal (Max Sharpe)',
          data: [{
            x: this.frontierData.tangency_portfolio.expected_risk * 100,
            y: this.frontierData.tangency_portfolio.expected_return * 100,
          }],
        });
      }

      // Minimum Variance Portfolio
      if (this.frontierData.minimum_variance_portfolio) {
        series.push({
          name: 'Min Variance',
          data: [{
            x: this.frontierData.minimum_variance_portfolio.expected_risk * 100,
            y: this.frontierData.minimum_variance_portfolio.expected_return * 100,
          }],
        });
      }

      // Capital Allocation Line (CAL)
      if (this.frontierData.capital_allocation_line && this.frontierData.capital_allocation_line.points) {
        series.push({
          name: 'Capital Allocation Line',
          data: this.frontierData.capital_allocation_line.points.map(point => ({
            x: point.risk * 100,
            y: point.return * 100,
          })),
        });
      }

      return series;
    },

    chartOptions() {
      return {
        chart: {
          type: 'scatter',
          height: 450,
          zoom: {
            enabled: true,
            type: 'xy',
          },
          toolbar: {
            show: true,
            tools: {
              download: true,
              zoom: true,
              zoomin: true,
              zoomout: true,
              pan: true,
              reset: true,
            },
          },
        },
        colours: ['#3B82F6', '#6B7280', '#10B981', '#F59E0B', '#8B5CF6'],
        xaxis: {
          title: {
            text: 'Expected Risk (Standard Deviation %)',
            style: {
              fontSize: '14px',
              fontWeight: 600,
            },
          },
          labels: {
            formatter: (val) => val?.toFixed(2) + '%',
          },
        },
        yaxis: {
          title: {
            text: 'Expected Return (%)',
            style: {
              fontSize: '14px',
              fontWeight: 600,
            },
          },
          labels: {
            formatter: (val) => val?.toFixed(2) + '%',
          },
        },
        markers: {
          size: [2, 8, 10, 10, 2], // Frontier line small, portfolios large
        },
        stroke: {
          width: [3, 0, 0, 0, 2], // Frontier and CAL have lines, points don't
          dashArray: [0, 0, 0, 0, 5], // CAL is dashed
        },
        legend: {
          position: 'top',
          horizontalAlign: 'right',
          fontSize: '12px',
        },
        tooltip: {
          shared: false,
          intersect: true,
          custom: ({ series, seriesIndex, dataPointIndex, w }) => {
            const point = w.config.series[seriesIndex].data[dataPointIndex];
            const seriesName = w.config.series[seriesIndex].name;

            return `
              <div class="apexcharts-tooltip-custom p-3">
                <div class="font-semibold text-gray-900 mb-2">${seriesName}</div>
                <div class="text-sm">
                  <div class="flex justify-between gap-4">
                    <span class="text-gray-600">Return:</span>
                    <span class="font-medium">${point.y.toFixed(2)}%</span>
                  </div>
                  <div class="flex justify-between gap-4">
                    <span class="text-gray-600">Risk:</span>
                    <span class="font-medium">${point.x.toFixed(2)}%</span>
                  </div>
                </div>
              </div>
            `;
          },
        },
        grid: {
          borderColour: '#E5E7EB',
          strokeDashArray: 4,
        },
      };
    },
  },

  methods: {
    async loadFrontier() {
      this.loading = true;
      this.error = null;

      try {
        const response = await portfolioOptimizationService.calculateEfficientFrontier({
          risk_free_rate: this.riskFreeRate,
          num_points: this.numPoints,
        });

        if (response.success) {
          this.frontierData = response.data;
        } else {
          this.error = response.message || 'Failed to calculate efficient frontier';
        }
      } catch (err) {
        // Handle "no holdings" gracefully without logging error
        if (err.response && err.response.status === 400) {
          const message = err.response.data?.message || '';
          if (message.includes('No investment accounts') || message.includes('No holdings')) {
            this.error = 'Add investment holdings to view efficient frontier analysis';
            return; // Don't log this as an error - it's expected for new users
          }
        }

        // Log unexpected errors
        console.error('Error loading efficient frontier:', err);
        this.error = err.message || 'Unable to load efficient frontier data';
      } finally {
        this.loading = false;
      }
    },

    async refreshFrontier() {
      // Clear cache first
      try {
        await portfolioOptimizationService.clearCache();
      } catch (err) {
        console.warn('Failed to clear cache:', err);
      }

      await this.loadFrontier();
    },

    formatPercentage(value) {
      if (value === null || value === undefined) return 'N/A';
      return `${(value * 100).toFixed(2)}%`;
    },
  },

  mounted() {
    if (this.isPreviewMode) {
      console.log('[EfficientFrontier] Preview mode - skipping API calls');
      this.loading = false;
      return;
    }
    this.loadFrontier();
  },
};
</script>

<style scoped>
/* Custom tooltip styles */
:deep(.apexcharts-tooltip-custom) {
  background: white;
  border: 1px solid #E5E7EB;
  border-radius: 0.5rem;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}
</style>
