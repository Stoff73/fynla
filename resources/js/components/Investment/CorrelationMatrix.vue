<template>
  <div class="correlation-matrix">
    <div class="flex justify-between items-center mb-6">
      <div>
        <h3 class="text-lg font-semibold text-horizon-500">Correlation Matrix</h3>
        <p class="text-sm text-neutral-500 mt-1">
          Understand how your assets move together
        </p>
      </div>
      <button
        @click="loadCorrelationData"
        :disabled="loading"
        class="px-4 py-2 text-sm font-medium text-neutral-500 bg-white border border-horizon-300 rounded-lg hover:bg-eggshell-500 disabled:opacity-50"
      >
        {{ loading ? 'Refreshing...' : 'Refresh' }}
      </button>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <div class="text-center">
        <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-raspberry-500"></div>
        <p class="mt-2 text-sm text-neutral-500">Calculating correlations...</p>
      </div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-eggshell-500 rounded-lg p-4">
      <div class="flex">
        <svg class="h-5 w-5 text-raspberry-600 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <div>
          <h4 class="text-sm font-medium text-raspberry-800">Failed to Load Correlation Data</h4>
          <p class="text-sm text-raspberry-700 mt-1">{{ error }}</p>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="!correlationData || !correlationData.matrix" class="bg-eggshell-500 border border-light-gray rounded-lg p-8 text-center">
      <svg class="mx-auto h-12 w-12 text-horizon-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
      </svg>
      <h3 class="mt-2 text-sm font-medium text-horizon-500">No Correlation Data</h3>
      <p class="mt-1 text-sm text-neutral-500">Add investment holdings to see correlation analysis</p>
    </div>

    <!-- Correlation Matrix Display -->
    <div v-else class="space-y-6">
      <!-- Summary Statistics -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-lg border border-light-gray p-4">
          <p class="text-xs text-neutral-500 mb-1">Average Correlation</p>
          <p class="text-2xl font-bold text-horizon-500">
            {{ formatCorrelation(correlationData.statistics?.average_correlation) }}
          </p>
          <p class="text-xs text-neutral-500 mt-1">
            {{ getCorrelationLabel(correlationData.statistics?.average_correlation) }}
          </p>
        </div>

        <div class="bg-white rounded-lg border border-light-gray p-4">
          <p class="text-xs text-neutral-500 mb-1">Highest Correlation</p>
          <p class="text-2xl font-bold" :class="getCorrelationColour(correlationData.statistics?.max_correlation)">
            {{ formatCorrelation(correlationData.statistics?.max_correlation) }}
          </p>
          <p class="text-xs text-neutral-500 mt-1" v-if="correlationData.statistics?.max_pair">
            {{ correlationData.statistics.max_pair.join(' & ') }}
          </p>
        </div>

        <div class="bg-white rounded-lg border border-light-gray p-4">
          <p class="text-xs text-neutral-500 mb-1">Lowest Correlation</p>
          <p class="text-2xl font-bold" :class="getCorrelationColour(correlationData.statistics?.min_correlation)">
            {{ formatCorrelation(correlationData.statistics?.min_correlation) }}
          </p>
          <p class="text-xs text-neutral-500 mt-1" v-if="correlationData.statistics?.min_pair">
            {{ correlationData.statistics.min_pair.join(' & ') }}
          </p>
        </div>

        <div class="bg-white rounded-lg border border-light-gray p-4">
          <p class="text-xs text-neutral-500 mb-1">Diversification</p>
          <p class="text-2xl font-bold" :class="getDiversificationColour">
            {{ diversificationLabel }}
          </p>
          <p class="text-xs text-neutral-500 mt-1">
            {{ getDiversificationSummary }}
          </p>
        </div>
      </div>

      <!-- Heatmap Chart -->
      <div class="bg-white rounded-lg border border-light-gray p-6">
        <h4 class="text-sm font-semibold text-horizon-500 mb-4">Correlation Heatmap</h4>
        <apexchart
          v-if="heatmapReady"
          type="heatmap"
          :options="heatmapOptions"
          :series="heatmapSeries"
          :height="chartHeight"
        />
      </div>

      <!-- Correlation Insights -->
      <div class="bg-white rounded-lg border border-light-gray p-6">
        <h4 class="text-sm font-semibold text-horizon-500 mb-4">Correlation Insights</h4>

        <!-- High Correlations (Redundancy) -->
        <div v-if="highCorrelations.length > 0" class="mb-4">
          <div class="flex items-center mb-2">
            <svg class="h-5 w-5 text-raspberry-600 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <h5 class="text-sm font-medium text-horizon-500">Highly Correlated Pairs (>0.90)</h5>
          </div>
          <div class="bg-eggshell-500 rounded-lg p-3">
            <p class="text-xs text-raspberry-800 mb-2">
              These assets move very similarly, reducing diversification benefits.
            </p>
            <ul class="space-y-1">
              <li v-for="pair in highCorrelations" :key="pair.key" class="text-sm text-raspberry-900">
                <span class="font-medium">{{ pair.asset1 }}</span> &
                <span class="font-medium">{{ pair.asset2 }}</span>:
                <span class="font-bold">{{ formatCorrelation(pair.correlation) }}</span>
              </li>
            </ul>
          </div>
        </div>

        <!-- Low Correlations (Diversification) -->
        <div v-if="lowCorrelations.length > 0" class="mb-4">
          <div class="flex items-center mb-2">
            <svg class="h-5 w-5 text-spring-600 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <h5 class="text-sm font-medium text-horizon-500">Well Diversified Pairs (<0.30)</h5>
          </div>
          <div class="bg-eggshell-500 rounded-lg p-3">
            <p class="text-xs text-spring-800 mb-2">
              These assets move independently, providing good diversification.
            </p>
            <ul class="space-y-1">
              <li v-for="pair in lowCorrelations" :key="pair.key" class="text-sm text-spring-900">
                <span class="font-medium">{{ pair.asset1 }}</span> &
                <span class="font-medium">{{ pair.asset2 }}</span>:
                <span class="font-bold">{{ formatCorrelation(pair.correlation) }}</span>
              </li>
            </ul>
          </div>
        </div>

        <!-- No Issues -->
        <div v-if="highCorrelations.length === 0 && lowCorrelations.length === 0" class="bg-eggshell-500 border border-light-gray rounded-lg p-4 text-center">
          <p class="text-sm text-neutral-500">
            Your portfolio has moderate correlations across all holdings. Consider adding more diversified assets.
          </p>
        </div>
      </div>

      <!-- Correlation Matrix Table -->
      <div class="bg-white rounded-lg border border-light-gray p-6">
        <h4 class="text-sm font-semibold text-horizon-500 mb-4">Correlation Matrix Table</h4>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-light-gray">
            <thead>
              <tr>
                <th class="px-3 py-2 text-left text-xs font-medium text-neutral-500 uppercase">Asset</th>
                <th
                  v-for="(label, index) in labels"
                  :key="index"
                  class="px-3 py-2 text-center text-xs font-medium text-neutral-500 uppercase"
                  :title="label"
                >
                  {{ truncateLabel(label, 10) }}
                </th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-light-gray">
              <tr v-for="(row, rowIndex) in correlationData.matrix" :key="rowIndex">
                <td class="px-3 py-2 text-sm font-medium text-horizon-500" :title="labels[rowIndex]">
                  {{ truncateLabel(labels[rowIndex], 15) }}
                </td>
                <td
                  v-for="(value, colIndex) in row"
                  :key="colIndex"
                  class="px-3 py-2 text-sm text-center font-medium"
                  :class="getCellColourClass(value, rowIndex, colIndex)"
                  :title="`${labels[rowIndex]} vs ${labels[colIndex]}: ${formatCorrelation(value)}`"
                >
                  {{ formatCorrelation(value) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Legend -->
      <div class="bg-eggshell-500 rounded-lg border border-light-gray p-4">
        <h4 class="text-sm font-semibold text-horizon-500 mb-3">Understanding Correlations</h4>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
          <div>
            <p class="font-medium text-horizon-500 mb-1">High (0.70 to 1.00)</p>
            <p class="text-neutral-500">Assets move together closely. May indicate redundancy.</p>
          </div>
          <div>
            <p class="font-medium text-horizon-500 mb-1">Moderate (0.30 to 0.70)</p>
            <p class="text-neutral-500">Some relationship but still provide diversification.</p>
          </div>
          <div>
            <p class="font-medium text-horizon-500 mb-1">Low (-1.00 to 0.30)</p>
            <p class="text-neutral-500">Assets move independently or inversely. Good for diversification.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import VueApexCharts from 'vue3-apexcharts';
import portfolioOptimizationService from '@/services/portfolioOptimizationService';
import { SUCCESS_COLORS, WARNING_COLORS, ERROR_COLORS, PRIMARY_COLORS, CHART_DEFAULTS, TEXT_COLORS, BORDER_COLORS } from '@/constants/designSystem';

import logger from '@/utils/logger';
export default {
  name: 'CorrelationMatrix',

  components: {
    apexchart: VueApexCharts,
  },

  data() {
    return {
      loading: false,
      error: null,
      correlationData: null,
      heatmapReady: false,
    };
  },

  computed: {
    isPreviewMode() {
      return this.$store.getters['preview/isPreviewMode'];
    },
    labels() {
      return this.correlationData?.labels || [];
    },

    chartHeight() {
      const numAssets = this.labels.length;
      return Math.max(300, numAssets * 40);
    },

    heatmapSeries() {
      if (!this.correlationData || !this.correlationData.matrix) {
        return [];
      }

      return this.correlationData.matrix.map((row, index) => ({
        name: this.labels[index],
        data: row.map((value, colIndex) => ({
          x: this.labels[colIndex],
          y: parseFloat((value * 100).toFixed(1)),
        })),
      }));
    },

    heatmapOptions() {
      return {
        chart: {
          ...CHART_DEFAULTS.chart,
          type: 'heatmap',
          toolbar: {
            show: true,
          },
        },
        dataLabels: {
          enabled: true,
          formatter: (val) => val.toFixed(0),
        },
        colours: [PRIMARY_COLORS[500]],
        plotOptions: {
          heatmap: {
            shadeIntensity: 0.5,
            radius: 0,
            colourScale: {
              ranges: [
                { from: -100, to: 0, color: SUCCESS_COLORS[500], name: 'Negative' },
                { from: 0, to: 30, color: SUCCESS_COLORS[600], name: 'Low (0-0.3)' },
                { from: 30, to: 70, color: WARNING_COLORS[500], name: 'Moderate (0.3-0.7)' },
                { from: 70, to: 90, color: WARNING_COLORS[600], name: 'High (0.7-0.9)' },
                { from: 90, to: 100, color: ERROR_COLORS[500], name: 'Very High (>0.9)' },
              ],
            },
          },
        },
        tooltip: {
          y: {
            formatter: (val) => (val / 100).toFixed(3),
          },
        },
        xaxis: {
          labels: {
            rotate: -45,
            style: {
              fontSize: '10px',
            },
          },
        },
        yaxis: {
          labels: {
            style: {
              fontSize: '10px',
            },
          },
        },
      };
    },

    diversificationScore() {
      if (!this.correlationData?.statistics?.average_correlation) {
        return 0;
      }
      const avgCorr = this.correlationData.statistics.average_correlation;
      return Math.max(0, Math.min(100, Math.round((1 - avgCorr) * 100)));
    },

    diversificationLabel() {
      const score = this.diversificationScore;
      if (score >= 80) return 'Excellent';
      if (score >= 60) return 'Good';
      if (score >= 40) return 'Moderate';
      if (score >= 20) return 'Limited';
      return 'Poor';
    },

    getDiversificationColour() {
      const score = this.diversificationScore;
      if (score >= 80) return 'text-spring-600';
      if (score >= 60) return 'text-spring-600';
      if (score >= 40) return 'text-violet-600';
      return 'text-raspberry-600';
    },

    getDiversificationSummary() {
      const score = this.diversificationScore;
      if (score >= 80) return 'Low average correlation';
      if (score >= 60) return 'Moderate average correlation';
      if (score >= 40) return 'Elevated average correlation';
      return 'High average correlation';
    },

    highCorrelations() {
      if (!this.correlationData?.matrix) return [];

      const pairs = [];
      const matrix = this.correlationData.matrix;

      for (let i = 0; i < matrix.length; i++) {
        for (let j = i + 1; j < matrix[i].length; j++) {
          const correlation = matrix[i][j];
          if (correlation > 0.90) {
            pairs.push({
              key: `${i}-${j}`,
              asset1: this.labels[i],
              asset2: this.labels[j],
              correlation: correlation,
            });
          }
        }
      }

      return pairs.sort((a, b) => b.correlation - a.correlation).slice(0, 5);
    },

    lowCorrelations() {
      if (!this.correlationData?.matrix) return [];

      const pairs = [];
      const matrix = this.correlationData.matrix;

      for (let i = 0; i < matrix.length; i++) {
        for (let j = i + 1; j < matrix[i].length; j++) {
          const correlation = matrix[i][j];
          if (correlation < 0.30) {
            pairs.push({
              key: `${i}-${j}`,
              asset1: this.labels[i],
              asset2: this.labels[j],
              correlation: correlation,
            });
          }
        }
      }

      return pairs.sort((a, b) => a.correlation - b.correlation).slice(0, 5);
    },
  },

  methods: {
    async loadCorrelationData() {
      this.loading = true;
      this.error = null;
      this.heatmapReady = false;

      try {
        const response = await portfolioOptimizationService.getCorrelationMatrix();

        if (response.success) {
          this.correlationData = response.data;
          this.$nextTick(() => {
            this.heatmapReady = true;
          });
        } else {
          this.error = response.message || 'Failed to load correlation data';
        }
      } catch (err) {
        logger.error('Correlation matrix error:', err);
        this.error = err.message || 'Failed to load correlation matrix';
      } finally {
        this.loading = false;
      }
    },

    formatCorrelation(value) {
      if (value === null || value === undefined) return 'N/A';
      return value.toFixed(3);
    },

    getCorrelationLabel(value) {
      if (value === null || value === undefined) return '';
      if (value >= 0.70) return 'High correlation';
      if (value >= 0.30) return 'Moderate correlation';
      if (value >= 0) return 'Low correlation';
      return 'Negative correlation';
    },

    getCorrelationColour(value) {
      if (value === null || value === undefined) return 'text-horizon-500';
      if (value >= 0.90) return 'text-raspberry-600';
      if (value >= 0.70) return 'text-violet-600';
      if (value >= 0.30) return 'text-violet-600';
      if (value >= 0) return 'text-spring-600';
      return 'text-violet-600';
    },

    getCellColourClass(value, rowIndex, colIndex) {
      if (rowIndex === colIndex) {
        return 'bg-savannah-100 text-neutral-500';
      }

      if (value >= 0.90) return 'bg-raspberry-500 text-white';
      if (value >= 0.70) return 'bg-violet-500 text-white';
      if (value >= 0.30) return 'bg-violet-500 text-white';
      if (value >= 0) return 'bg-spring-500 text-white';
      return 'bg-violet-500 text-white';
    },

    truncateLabel(label, maxLength) {
      if (!label) return '';
      if (label.length <= maxLength) return label;
      return label.substring(0, maxLength - 3) + '...';
    },
  },

  mounted() {
    // Preview users are real DB users - use normal API to fetch their data
    this.loadCorrelationData();
  },
};
</script>
