<template>
  <div class="benchmark-comparison">
    <!-- Loading State -->
    <div v-if="loading" class="flex justify-center items-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-gray-50 rounded-lg p-4 mb-6">
      <div class="flex items-center">
        <svg class="h-5 w-5 text-red-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
        </svg>
        <span class="text-sm font-medium text-red-800">{{ error }}</span>
      </div>
    </div>

    <!-- Main Content -->
    <div v-else class="space-y-6">
      <!-- Benchmark Selector & Period -->
      <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
          <h2 class="text-xl font-semibold text-gray-800">Benchmark Comparison</h2>

          <div class="flex items-center space-x-4">
            <select
              v-model="selectedBenchmarks"
              multiple
              size="4"
              class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            >
              <option value="ftse_all_share">FTSE All-Share</option>
              <option value="ftse_100">FTSE 100</option>
              <option value="sp_500">S&P 500</option>
              <option value="msci_world">MSCI World</option>
              <option value="60_40">60/40 Portfolio</option>
              <option value="uk_bonds">UK Bonds</option>
            </select>

            <select
              v-model="selectedPeriod"
              @change="loadComparisonData"
              class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            >
              <option value="1m">1 Month</option>
              <option value="3m">3 Months</option>
              <option value="6m">6 Months</option>
              <option value="1y">1 Year</option>
              <option value="3y">3 Years</option>
              <option value="5y">5 Years</option>
            </select>

            <button
              @click="compareSelected"
              :disabled="selectedBenchmarks.length === 0"
              class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
              Compare
            </button>
          </div>
        </div>
      </div>

      <!-- Performance Comparison Chart -->
      <div v-if="comparisonData" class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-6">Performance Comparison</h3>

        <apexchart
          type="line"
          :options="performanceChartOptions"
          :series="performanceChartSeries"
          height="400"
        />
      </div>

      <!-- Comparison Table -->
      <div v-if="comparisonData" class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-6">Detailed Metrics Comparison</h3>

        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-200">
                <th class="text-left py-3 px-4 font-semibold text-gray-700">Metric</th>
                <th class="text-right py-3 px-4 font-semibold text-blue-700">Your Portfolio</th>
                <th
                  v-for="benchmark in comparisonData.benchmarks"
                  :key="benchmark.id"
                  class="text-right py-3 px-4 font-semibold text-gray-700"
                >
                  {{ benchmark.name }}
                </th>
              </tr>
            </thead>
            <tbody>
              <!-- Total Return -->
              <tr class="border-b border-gray-100">
                <td class="py-3 px-4 font-medium">Total Return</td>
                <td class="text-right py-3 px-4 font-semibold" :class="comparisonData.portfolio.total_return >= 0 ? 'text-green-600' : 'text-red-600'">
                  {{ formatPercent(comparisonData.portfolio.total_return) }}
                </td>
                <td
                  v-for="benchmark in comparisonData.benchmarks"
                  :key="`return-${benchmark.id}`"
                  class="text-right py-3 px-4"
                  :class="benchmark.total_return >= 0 ? 'text-green-600' : 'text-red-600'"
                >
                  {{ formatPercent(benchmark.total_return) }}
                </td>
              </tr>

              <!-- Volatility -->
              <tr class="border-b border-gray-100">
                <td class="py-3 px-4 font-medium">Volatility</td>
                <td class="text-right py-3 px-4 font-semibold text-gray-800">
                  {{ formatPercent(comparisonData.portfolio.volatility) }}
                </td>
                <td
                  v-for="benchmark in comparisonData.benchmarks"
                  :key="`vol-${benchmark.id}`"
                  class="text-right py-3 px-4 text-gray-800"
                >
                  {{ formatPercent(benchmark.volatility) }}
                </td>
              </tr>

              <!-- Sharpe Ratio -->
              <tr class="border-b border-gray-100">
                <td class="py-3 px-4 font-medium">Sharpe Ratio</td>
                <td class="text-right py-3 px-4 font-semibold" :class="getSharpeColour(comparisonData.portfolio.sharpe_ratio)">
                  {{ formatDecimal(comparisonData.portfolio.sharpe_ratio) }}
                </td>
                <td
                  v-for="benchmark in comparisonData.benchmarks"
                  :key="`sharpe-${benchmark.id}`"
                  class="text-right py-3 px-4"
                  :class="getSharpeColour(benchmark.sharpe_ratio)"
                >
                  {{ formatDecimal(benchmark.sharpe_ratio) }}
                </td>
              </tr>

              <!-- Max Drawdown -->
              <tr class="border-b border-gray-100">
                <td class="py-3 px-4 font-medium">Max Drawdown</td>
                <td class="text-right py-3 px-4 font-semibold text-red-600">
                  {{ formatPercent(comparisonData.portfolio.max_drawdown) }}
                </td>
                <td
                  v-for="benchmark in comparisonData.benchmarks"
                  :key="`dd-${benchmark.id}`"
                  class="text-right py-3 px-4 text-red-600"
                >
                  {{ formatPercent(benchmark.max_drawdown) }}
                </td>
              </tr>

              <!-- Beta -->
              <tr class="border-b border-gray-100">
                <td class="py-3 px-4 font-medium">Beta</td>
                <td class="text-right py-3 px-4 font-semibold text-gray-800">
                  {{ formatDecimal(comparisonData.portfolio.beta || 1.0) }}
                </td>
                <td
                  v-for="benchmark in comparisonData.benchmarks"
                  :key="`beta-${benchmark.id}`"
                  class="text-right py-3 px-4 text-gray-800"
                >
                  {{ formatDecimal(benchmark.beta || 1.0) }}
                </td>
              </tr>

              <!-- Alpha -->
              <tr class="border-b border-gray-100 bg-gray-50">
                <td class="py-3 px-4 font-medium">Alpha (vs FTSE All-Share)</td>
                <td class="text-right py-3 px-4 font-semibold" :class="comparisonData.portfolio.alpha >= 0 ? 'text-green-600' : 'text-red-600'">
                  {{ formatPercent(comparisonData.portfolio.alpha) }}
                </td>
                <td
                  v-for="benchmark in comparisonData.benchmarks"
                  :key="`alpha-${benchmark.id}`"
                  class="text-right py-3 px-4"
                  :class="benchmark.alpha >= 0 ? 'text-green-600' : 'text-red-600'"
                >
                  {{ formatPercent(benchmark.alpha || 0) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Risk-Return Scatter Plot -->
      <div v-if="comparisonData" class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-6">Risk vs Return Analysis</h3>

        <apexchart
          type="scatter"
          :options="riskReturnChartOptions"
          :series="riskReturnChartSeries"
          height="400"
        />

        <div class="mt-4 p-4 bg-gray-50 rounded-lg">
          <p class="text-sm text-gray-700">
            <strong>Interpretation:</strong> Points further to the left and higher up represent better risk-adjusted performance.
            Your portfolio is shown in blue.
          </p>
        </div>
      </div>

      <!-- Performance Summary Cards -->
      <div v-if="comparisonData" class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Relative Performance -->
        <div class="bg-white rounded-lg shadow-md p-6">
          <h4 class="text-sm font-semibold text-gray-700 mb-4">Relative Performance</h4>
          <p class="text-xs text-gray-600 mb-2">vs Best Performing Benchmark</p>
          <p class="text-2xl font-bold mb-1" :class="comparisonData.summary.vs_best >= 0 ? 'text-green-600' : 'text-red-600'">
            {{ comparisonData.summary.vs_best >= 0 ? '+' : '' }}{{ formatPercent(comparisonData.summary.vs_best) }}
          </p>
          <p class="text-xs text-gray-500">{{ comparisonData.summary.best_benchmark }}</p>
        </div>

        <!-- Risk-Adjusted Rank -->
        <div class="bg-white rounded-lg shadow-md p-6">
          <h4 class="text-sm font-semibold text-gray-700 mb-4">Sharpe Ratio Ranking</h4>
          <p class="text-xs text-gray-600 mb-2">Position by Risk-Adjusted Return</p>
          <p class="text-4xl font-bold text-blue-600 mb-1">
            {{ comparisonData.summary.sharpe_rank }}
          </p>
          <p class="text-xs text-gray-500">of {{ comparisonData.benchmarks.length + 1 }} portfolios</p>
        </div>

        <!-- Volatility Comparison -->
        <div class="bg-white rounded-lg shadow-md p-6">
          <h4 class="text-sm font-semibold text-gray-700 mb-4">Volatility Assessment</h4>
          <p class="text-xs text-gray-600 mb-2">vs Average Benchmark</p>
          <p class="text-2xl font-bold mb-1" :class="getVolatilityColour(comparisonData.summary.vs_avg_volatility)">
            {{ comparisonData.summary.vs_avg_volatility >= 0 ? '+' : '' }}{{ formatPercent(comparisonData.summary.vs_avg_volatility) }}
          </p>
          <p class="text-xs text-gray-500">
            {{ comparisonData.summary.vs_avg_volatility < 0 ? 'Less volatile' : 'More volatile' }}
          </p>
        </div>
      </div>

      <!-- Insights -->
      <div v-if="comparisonData && comparisonData.insights" class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Key Insights</h3>

        <div class="space-y-3">
          <div
            v-for="(insight, index) in comparisonData.insights"
            :key="index"
            class="border-l-4 p-4 rounded-r-lg"
            :class="getInsightClass(insight.type)"
          >
            <p class="text-sm font-medium text-gray-800 mb-1">{{ insight.title }}</p>
            <p class="text-sm text-gray-600">{{ insight.description }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import api from '@/services/api';

export default {
  name: 'BenchmarkComparison',

  data() {
    return {
      loading: false,
      error: null,
      selectedPeriod: '1y',
      selectedBenchmarks: ['ftse_all_share', 'sp_500', '60_40'],
      comparisonData: null,
    };
  },

  computed: {
    isPreviewMode() {
      return this.$store.getters['preview/isPreviewMode'];
    },

    performanceChartOptions() {
      return {
        chart: {
          type: 'line',
          toolbar: {
            show: true,
          },
          zoom: {
            enabled: true,
          },
        },
        stroke: {
          width: [3, 2, 2, 2],
          curve: 'smooth',
        },
        dataLabels: {
          enabled: false,
        },
        xaxis: {
          type: 'datetime',
          labels: {
            datetimeFormatter: {
              year: 'yyyy',
              month: 'MMM yyyy',
              day: 'dd MMM',
            },
          },
        },
        yaxis: {
          labels: {
            formatter: (val) => this.formatPercent(val / 100),
          },
        },
        legend: {
          position: 'top',
        },
        colours: ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6'],
        tooltip: {
          y: {
            formatter: (val) => this.formatPercent(val / 100),
          },
        },
      };
    },

    performanceChartSeries() {
      if (!this.comparisonData) return [];

      const series = [
        {
          name: 'Your Portfolio',
          data: this.comparisonData.portfolio.timeseries || [],
        },
      ];

      this.comparisonData.benchmarks.forEach(benchmark => {
        series.push({
          name: benchmark.name,
          data: benchmark.timeseries || [],
        });
      });

      return series;
    },

    riskReturnChartOptions() {
      return {
        chart: {
          type: 'scatter',
          toolbar: {
            show: false,
          },
        },
        xaxis: {
          title: {
            text: 'Risk (Volatility)',
          },
          labels: {
            formatter: (val) => this.formatPercent(val / 100),
          },
        },
        yaxis: {
          title: {
            text: 'Return',
          },
          labels: {
            formatter: (val) => this.formatPercent(val / 100),
          },
        },
        colours: ['#3B82F6', '#10B981', '#F59E0B', '#EF4444'],
        markers: {
          size: 8,
        },
        legend: {
          position: 'top',
        },
      };
    },

    riskReturnChartSeries() {
      if (!this.comparisonData) return [];

      const series = [
        {
          name: 'Your Portfolio',
          data: [[
            this.comparisonData.portfolio.volatility * 100,
            this.comparisonData.portfolio.total_return * 100,
          ]],
        },
      ];

      this.comparisonData.benchmarks.forEach(benchmark => {
        series.push({
          name: benchmark.name,
          data: [[
            benchmark.volatility * 100,
            benchmark.total_return * 100,
          ]],
        });
      });

      return series;
    },
  },

  mounted() {
    // Preview users are real DB users - use normal API to fetch their data
    this.compareSelected();
  },

  methods: {
    async compareSelected() {
      // Preview users are real DB users - use normal API for comparisons
      if (this.selectedBenchmarks.length === 0) return;

      this.loading = true;
      this.error = null;

      try {
        const response = await api.get('/investment/performance-attribution/multi-benchmark', {
          params: {
            period: this.selectedPeriod,
          },
        });

        this.comparisonData = response.data.data;
      } catch (err) {
        console.error('Error loading comparison data:', err);
        this.error = err.response?.data?.message || 'Failed to load benchmark comparison. Please try again.';
      } finally {
        this.loading = false;
      }
    },

    async loadComparisonData() {
      await this.compareSelected();
    },

    formatPercent(value) {
      if (value === null || value === undefined) return 'N/A';
      const formatted = (value * 100).toFixed(2);
      return `${formatted}%`;
    },

    formatDecimal(value) {
      if (value === null || value === undefined) return 'N/A';
      return value.toFixed(2);
    },

    getSharpeColour(sharpe) {
      if (sharpe >= 2) return 'text-green-600';
      if (sharpe >= 1) return 'text-blue-600';
      if (sharpe >= 0) return 'text-yellow-600';
      return 'text-red-600';
    },

    getVolatilityColour(diff) {
      return diff < 0 ? 'text-green-600' : 'text-red-600';
    },

    getInsightClass(type) {
      if (type === 'positive') return 'border-green-500 bg-white';
      if (type === 'warning') return 'border-yellow-500 bg-white';
      return 'border-blue-500 bg-white';
    },
  },
};
</script>

<style scoped>
/* Component-specific styles */
</style>
