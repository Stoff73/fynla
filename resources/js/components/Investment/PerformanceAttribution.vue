<template>
  <div class="performance-attribution">
    <!-- Loading State -->
    <div v-if="loading" class="flex justify-center items-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-raspberry-500"></div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-eggshell-500 rounded-lg p-4 mb-6">
      <div class="flex items-center">
        <svg class="h-5 w-5 text-raspberry-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
        </svg>
        <span class="text-sm font-medium text-raspberry-800">{{ error }}</span>
      </div>
    </div>

    <!-- Main Content -->
    <div v-else class="space-y-6">
      <!-- Period Selector -->
      <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
          <h2 class="text-xl font-semibold text-horizon-500">Performance Attribution Analysis</h2>

          <div class="flex items-center space-x-4">
            <select
              v-model="selectedPeriod"
              @change="loadPerformanceData"
              class="px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
            >
              <option value="1m">1 Month</option>
              <option value="3m">3 Months</option>
              <option value="6m">6 Months</option>
              <option value="1y">1 Year</option>
              <option value="3y">3 Years</option>
              <option value="5y">5 Years</option>
              <option value="ytd">Year to Date</option>
            </select>

            <button
              @click="refreshData"
              class="px-4 py-2 bg-raspberry-500 text-white rounded-button hover:bg-raspberry-600 transition-colors"
            >
              Refresh
            </button>
          </div>
        </div>
      </div>

      <!-- Performance Summary Cards -->
      <div v-if="performanceData" class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <!-- Total Return -->
        <div class="bg-white rounded-lg shadow-md p-6">
          <p class="text-sm text-neutral-500 mb-2">Total Return</p>
          <p class="text-3xl font-bold mb-1" :class="performanceData.total_return >= 0 ? 'text-spring-600' : 'text-raspberry-600'">
            {{ formatPercent(performanceData.total_return) }}
          </p>
          <p class="text-xs text-neutral-500">{{ selectedPeriod.toUpperCase() }}</p>
        </div>

        <!-- Benchmark Return -->
        <div class="bg-white rounded-lg shadow-md p-6">
          <p class="text-sm text-neutral-500 mb-2">Benchmark Return</p>
          <p class="text-3xl font-bold mb-1" :class="performanceData.benchmark_return >= 0 ? 'text-spring-600' : 'text-raspberry-600'">
            {{ formatPercent(performanceData.benchmark_return) }}
          </p>
          <p class="text-xs text-neutral-500">{{ performanceData.benchmark_name || 'FTSE All-Share' }}</p>
        </div>

        <!-- Alpha (Excess Return) -->
        <div class="bg-white rounded-lg shadow-md p-6">
          <p class="text-sm text-neutral-500 mb-2">Alpha (Excess Return)</p>
          <p class="text-3xl font-bold mb-1" :class="performanceData.alpha >= 0 ? 'text-spring-600' : 'text-raspberry-600'">
            {{ formatPercent(performanceData.alpha) }}
          </p>
          <p class="text-xs text-neutral-500">vs Benchmark</p>
        </div>

        <!-- Sharpe Ratio -->
        <div class="bg-white rounded-lg shadow-md p-6">
          <p class="text-sm text-neutral-500 mb-2">Sharpe Ratio</p>
          <p class="text-3xl font-bold mb-1" :class="getSharpeColour(performanceData.sharpe_ratio)">
            {{ formatDecimal(performanceData.sharpe_ratio) }}
          </p>
          <p class="text-xs text-neutral-500">Risk-Adjusted Return</p>
        </div>
      </div>

      <!-- Performance Attribution Breakdown -->
      <div v-if="performanceData" class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-horizon-500 mb-6">Performance Attribution Breakdown</h3>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <!-- Attribution Chart -->
          <div>
            <h4 class="text-sm font-semibold text-neutral-500 mb-3">Attribution by Source</h4>
            <apexchart
              type="bar"
              :options="attributionChartOptions"
              :series="attributionChartSeries"
              height="300"
            />
          </div>

          <!-- Attribution Details Table -->
          <div>
            <h4 class="text-sm font-semibold text-neutral-500 mb-3">Detailed Breakdown</h4>
            <div class="space-y-3">
              <div class="border-b border-light-gray pb-2">
                <div class="flex justify-between items-center mb-1">
                  <span class="text-sm text-neutral-500">Asset Allocation Effect</span>
                  <span class="font-semibold" :class="performanceData.attribution.allocation >= 0 ? 'text-spring-600' : 'text-raspberry-600'">
                    {{ formatPercent(performanceData.attribution.allocation) }}
                  </span>
                </div>
                <p class="text-xs text-neutral-500">
                  Impact of being over/underweight in asset classes
                </p>
              </div>

              <div class="border-b border-light-gray pb-2">
                <div class="flex justify-between items-center mb-1">
                  <span class="text-sm text-neutral-500">Security Selection Effect</span>
                  <span class="font-semibold" :class="performanceData.attribution.selection >= 0 ? 'text-spring-600' : 'text-raspberry-600'">
                    {{ formatPercent(performanceData.attribution.selection) }}
                  </span>
                </div>
                <p class="text-xs text-neutral-500">
                  Impact of selecting specific holdings within asset classes
                </p>
              </div>

              <div class="border-b border-light-gray pb-2">
                <div class="flex justify-between items-center mb-1">
                  <span class="text-sm text-neutral-500">Interaction Effect</span>
                  <span class="font-semibold" :class="performanceData.attribution.interaction >= 0 ? 'text-spring-600' : 'text-raspberry-600'">
                    {{ formatPercent(performanceData.attribution.interaction) }}
                  </span>
                </div>
                <p class="text-xs text-neutral-500">
                  Combined impact of allocation and selection decisions
                </p>
              </div>

              <div class="border-b border-light-gray pb-2">
                <div class="flex justify-between items-center mb-1">
                  <span class="text-sm text-neutral-500">Currency Effect</span>
                  <span class="font-semibold" :class="performanceData.attribution.currency >= 0 ? 'text-spring-600' : 'text-raspberry-600'">
                    {{ formatPercent(performanceData.attribution.currency || 0) }}
                  </span>
                </div>
                <p class="text-xs text-neutral-500">
                  Impact of foreign exchange movements
                </p>
              </div>

              <div class="pt-2 bg-eggshell-500 p-3 rounded">
                <div class="flex justify-between items-center">
                  <span class="font-semibold text-horizon-500">Total Attribution</span>
                  <span class="text-lg font-bold" :class="performanceData.alpha >= 0 ? 'text-spring-600' : 'text-raspberry-600'">
                    {{ formatPercent(performanceData.alpha) }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Risk-Adjusted Metrics -->
      <div v-if="riskMetrics" class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-horizon-500 mb-6">Risk-Adjusted Performance Metrics</h3>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <!-- Beta -->
          <div class="border border-light-gray rounded-lg p-4">
            <p class="text-sm text-neutral-500 mb-2">Beta</p>
            <p class="text-2xl font-bold text-horizon-500 mb-1">{{ formatDecimal(riskMetrics.beta) }}</p>
            <p class="text-xs text-neutral-500">
              {{ riskMetrics.beta > 1 ? 'More volatile' : riskMetrics.beta < 1 ? 'Less volatile' : 'Same volatility' }} than market
            </p>
          </div>

          <!-- Sharpe Ratio -->
          <div class="border border-light-gray rounded-lg p-4">
            <p class="text-sm text-neutral-500 mb-2">Sharpe Ratio</p>
            <p class="text-2xl font-bold mb-1" :class="getSharpeColour(riskMetrics.sharpe_ratio)">
              {{ formatDecimal(riskMetrics.sharpe_ratio) }}
            </p>
            <p class="text-xs text-neutral-500">Return per unit of risk</p>
          </div>

          <!-- Sortino Ratio -->
          <div class="border border-light-gray rounded-lg p-4">
            <p class="text-sm text-neutral-500 mb-2">Sortino Ratio</p>
            <p class="text-2xl font-bold mb-1" :class="getSharpeColour(riskMetrics.sortino_ratio)">
              {{ formatDecimal(riskMetrics.sortino_ratio) }}
            </p>
            <p class="text-xs text-neutral-500">Downside risk-adjusted</p>
          </div>

          <!-- Information Ratio -->
          <div class="border border-light-gray rounded-lg p-4">
            <p class="text-sm text-neutral-500 mb-2">Information Ratio</p>
            <p class="text-2xl font-bold mb-1" :class="getSharpeColour(riskMetrics.information_ratio)">
              {{ formatDecimal(riskMetrics.information_ratio) }}
            </p>
            <p class="text-xs text-neutral-500">Active return per tracking error</p>
          </div>

          <!-- Max Drawdown -->
          <div class="border border-light-gray rounded-lg p-4">
            <p class="text-sm text-neutral-500 mb-2">Maximum Drawdown</p>
            <p class="text-2xl font-bold text-raspberry-600 mb-1">{{ formatPercent(riskMetrics.max_drawdown) }}</p>
            <p class="text-xs text-neutral-500">Largest peak-to-trough decline</p>
          </div>

          <!-- Volatility -->
          <div class="border border-light-gray rounded-lg p-4">
            <p class="text-sm text-neutral-500 mb-2">Volatility (Std Dev)</p>
            <p class="text-2xl font-bold text-horizon-500 mb-1">{{ formatPercent(riskMetrics.volatility) }}</p>
            <p class="text-xs text-neutral-500">Annualized</p>
          </div>

          <!-- Downside Deviation -->
          <div class="border border-light-gray rounded-lg p-4">
            <p class="text-sm text-neutral-500 mb-2">Downside Deviation</p>
            <p class="text-2xl font-bold text-horizon-500 mb-1">{{ formatPercent(riskMetrics.downside_deviation) }}</p>
            <p class="text-xs text-neutral-500">Below-target volatility</p>
          </div>

          <!-- Tracking Error -->
          <div class="border border-light-gray rounded-lg p-4">
            <p class="text-sm text-neutral-500 mb-2">Tracking Error</p>
            <p class="text-2xl font-bold text-horizon-500 mb-1">{{ formatPercent(riskMetrics.tracking_error) }}</p>
            <p class="text-xs text-neutral-500">Deviation from benchmark</p>
          </div>
        </div>
      </div>

      <!-- Asset Class Performance -->
      <div v-if="performanceData && performanceData.asset_class_breakdown" class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-horizon-500 mb-6">Performance by Asset Class</h3>

        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-light-gray">
                <th class="text-left py-3 px-4 font-semibold text-neutral-500">Asset Class</th>
                <th class="text-right py-3 px-4 font-semibold text-neutral-500">Weight (%)</th>
                <th class="text-right py-3 px-4 font-semibold text-neutral-500">Return (%)</th>
                <th class="text-right py-3 px-4 font-semibold text-neutral-500">Contribution (%)</th>
                <th class="text-right py-3 px-4 font-semibold text-neutral-500">vs Benchmark</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(assetClass, index) in performanceData.asset_class_breakdown"
                :key="index"
                class="border-b border-savannah-100"
              >
                <td class="py-3 px-4 font-medium">{{ assetClass.name }}</td>
                <td class="text-right py-3 px-4">{{ formatDecimal(assetClass.weight * 100) }}%</td>
                <td class="text-right py-3 px-4" :class="assetClass.return >= 0 ? 'text-spring-600' : 'text-raspberry-600'">
                  {{ formatPercent(assetClass.return) }}
                </td>
                <td class="text-right py-3 px-4 font-semibold">
                  {{ formatPercent(assetClass.contribution) }}
                </td>
                <td class="text-right py-3 px-4" :class="assetClass.vs_benchmark >= 0 ? 'text-spring-600' : 'text-raspberry-600'">
                  {{ assetClass.vs_benchmark >= 0 ? '+' : '' }}{{ formatPercent(assetClass.vs_benchmark) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Performance Insights -->
      <div v-if="performanceData && performanceData.insights" class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-horizon-500 mb-4">Performance Insights</h3>

        <div class="space-y-3">
          <div
            v-for="(insight, index) in performanceData.insights"
            :key="index"
            class="border-l-4 p-4 rounded-r-lg"
            :class="getInsightClass(insight.type)"
          >
            <div class="flex items-start">
              <svg v-if="insight.type === 'positive'" class="h-5 w-5 text-spring-600 mr-3 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
              </svg>
              <svg v-else-if="insight.type === 'warning'" class="h-5 w-5 text-violet-600 mr-3 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
              </svg>
              <svg v-else class="h-5 w-5 text-violet-600 mr-3 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
              </svg>
              <div class="flex-1">
                <p class="text-sm font-medium text-horizon-500">{{ insight.title }}</p>
                <p class="text-sm text-neutral-500 mt-1">{{ insight.description }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import api from '@/services/api';
import { PRIMARY_COLORS, CHART_DEFAULTS, TEXT_COLORS, BORDER_COLORS } from '@/constants/designSystem';

import logger from '@/utils/logger';
export default {
  name: 'PerformanceAttribution',

  data() {
    return {
      loading: true,
      error: null,
      selectedPeriod: '1y',
      performanceData: null,
      riskMetrics: null,
    };
  },

  computed: {
    isPreviewMode() {
      return this.$store.getters['preview/isPreviewMode'];
    },

    attributionChartOptions() {
      return {
        chart: {
          ...CHART_DEFAULTS.chart,
          type: 'bar',
        },
        plotOptions: {
          bar: {
            horizontal: true,
            dataLabels: {
              position: 'top',
            },
          },
        },
        dataLabels: {
          enabled: true,
          formatter: (val) => this.formatPercent(val),
          offsetX: 30,
          style: {
            fontSize: '11px',
            fontWeight: 'bold',
          },
        },
        xaxis: {
          categories: ['Asset Allocation', 'Security Selection', 'Interaction', 'Currency'],
          labels: {
            formatter: (val) => this.formatPercent(val),
          },
        },
        colors: [PRIMARY_COLORS[500]],
        tooltip: {
          y: {
            formatter: (val) => this.formatPercent(val),
          },
        },
      };
    },

    attributionChartSeries() {
      if (!this.performanceData) return [];

      return [{
        name: 'Attribution',
        data: [
          this.performanceData.attribution.allocation,
          this.performanceData.attribution.selection,
          this.performanceData.attribution.interaction,
          this.performanceData.attribution.currency || 0,
        ],
      }];
    },
  },

  mounted() {
    // Preview users are real DB users - use normal API to fetch their data
    this.loadPerformanceData();
  },

  methods: {
    async loadPerformanceData() {
      // Preview users are real DB users - use normal API to fetch their data
      this.loading = true;
      this.error = null;

      try {
        // Fetch performance analysis
        const perfResponse = await api.get('/investment/performance-attribution/analyze', {
          params: {
            period: this.selectedPeriod,
          },
        });
        this.performanceData = perfResponse.data.data;

        // Fetch risk metrics
        const riskResponse = await api.get('/investment/performance-attribution/risk-metrics', {
          params: {
            period: this.selectedPeriod,
          },
        });
        this.riskMetrics = riskResponse.data.data;
      } catch (err) {
        logger.error('Error loading performance data:', err);
        this.error = err.response?.data?.message || 'Failed to load performance data. Please try again.';
      } finally {
        this.loading = false;
      }
    },

    async refreshData() {
      await this.loadPerformanceData();
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
      if (sharpe >= 2) return 'text-spring-600';
      if (sharpe >= 1) return 'text-violet-600';
      if (sharpe >= 0) return 'text-violet-600';
      return 'text-raspberry-600';
    },

    getInsightClass(type) {
      if (type === 'positive') return 'border-spring-500 bg-white';
      if (type === 'warning') return 'border-violet-500 bg-white';
      return 'border-violet-500 bg-white';
    },
  },
};
</script>

<style scoped>
/* Component-specific styles */
</style>
