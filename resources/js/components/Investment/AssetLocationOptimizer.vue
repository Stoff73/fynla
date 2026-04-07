<template>
  <div class="asset-location-optimiser">
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
      <!-- Header with Optimization Score -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Optimization Score Card -->
        <div class="bg-gradient-to-br from-violet-50 to-violet-100 rounded-lg shadow-md p-6">
          <h3 class="text-lg font-semibold text-horizon-500 mb-4">Asset Location Score</h3>
          <div class="flex items-center justify-center mb-4">
            <apexchart
              v-if="analysis"
              type="radialBar"
              :options="optimizationScoreChartOptions"
              :series="[analysis.optimization_score?.score || 0]"
              height="200"
            />
          </div>
          <div class="text-center">
            <p class="text-2xl font-bold mb-1" :class="getScoreColour(analysis?.optimization_score?.score)">
              {{ optimisationLabel }}
            </p>
            <p class="text-sm text-neutral-500">{{ analysis?.optimization_score?.grade || 'N/A' }}</p>
          </div>
        </div>

        <!-- Tax Drag Summary -->
        <div class="bg-white rounded-lg shadow-md p-6 lg:col-span-2">
          <h3 class="text-lg font-semibold text-horizon-500 mb-4">Tax Drag Analysis</h3>
          <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
            <div>
              <p class="text-sm text-neutral-500 mb-1">Total Annual Tax Drag</p>
              <p class="text-2xl font-bold text-raspberry-600">
                £{{ formatNumber(analysis?.tax_drag?.total_annual_drag || 0) }}
              </p>
              <p class="text-xs text-neutral-500">{{ analysis?.tax_drag?.drag_percent || 0 }}% of returns</p>
            </div>
            <div>
              <p class="text-sm text-neutral-500 mb-1">Potential Savings</p>
              <p class="text-2xl font-bold text-spring-600">
                £{{ formatNumber(analysis?.tax_drag?.potential_savings || 0) }}
              </p>
              <p class="text-xs text-neutral-500">per year</p>
            </div>
            <div>
              <p class="text-sm text-neutral-500 mb-1">20-Year Impact</p>
              <p class="text-2xl font-bold text-violet-600">
                £{{ formatNumber(analysis?.tax_drag?.long_term_impact || 0) }}
              </p>
              <p class="text-xs text-neutral-500">compound savings</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Current Allocation Breakdown -->
      <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-horizon-500 mb-4">Current Asset Location</h3>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <!-- ISA Allocation -->
          <div class="border border-light-gray rounded-lg p-4">
            <div class="flex items-center justify-between mb-3">
              <h4 class="text-md font-semibold text-horizon-500">ISA (Tax-Free)</h4>
              <span class="px-2 py-1 bg-spring-500 text-white text-xs font-semibold rounded">OPTIMAL</span>
            </div>
            <p class="text-2xl font-bold text-horizon-500 mb-2">
              £{{ formatNumber(analysis?.current_allocation?.isa_value || 0) }}
            </p>
            <div class="space-y-1">
              <div class="flex justify-between text-sm">
                <span class="text-neutral-500">Equities:</span>
                <span class="font-medium">£{{ formatNumber(analysis?.current_allocation?.isa_breakdown?.equities || 0) }}</span>
              </div>
              <div class="flex justify-between text-sm">
                <span class="text-neutral-500">Bonds:</span>
                <span class="font-medium">£{{ formatNumber(analysis?.current_allocation?.isa_breakdown?.bonds || 0) }}</span>
              </div>
              <div class="flex justify-between text-sm">
                <span class="text-neutral-500">Cash:</span>
                <span class="font-medium">£{{ formatNumber(analysis?.current_allocation?.isa_breakdown?.cash || 0) }}</span>
              </div>
            </div>
          </div>

          <!-- GIA Allocation -->
          <div class="border border-light-gray rounded-lg p-4">
            <div class="flex items-center justify-between mb-3">
              <h4 class="text-md font-semibold text-horizon-500">General Investment Account (Taxable)</h4>
              <span v-if="analysis?.current_allocation?.gia_tax_drag > 1" class="px-2 py-1 bg-raspberry-500 text-white text-xs font-semibold rounded">HIGH TAX</span>
              <span v-else class="px-2 py-1 bg-violet-500 text-white text-xs font-semibold rounded">MODERATE</span>
            </div>
            <p class="text-2xl font-bold text-horizon-500 mb-2">
              £{{ formatNumber(analysis?.current_allocation?.gia_value || 0) }}
            </p>
            <div class="space-y-1">
              <div class="flex justify-between text-sm">
                <span class="text-neutral-500">Equities:</span>
                <span class="font-medium">£{{ formatNumber(analysis?.current_allocation?.gia_breakdown?.equities || 0) }}</span>
              </div>
              <div class="flex justify-between text-sm">
                <span class="text-neutral-500">Bonds:</span>
                <span class="font-medium">£{{ formatNumber(analysis?.current_allocation?.gia_breakdown?.bonds || 0) }}</span>
              </div>
              <div class="flex justify-between text-sm text-raspberry-600">
                <span>Annual Tax Drag:</span>
                <span class="font-semibold">£{{ formatNumber(analysis?.current_allocation?.gia_tax_drag || 0) }}</span>
              </div>
            </div>
          </div>

          <!-- Pension Allocation -->
          <div class="border border-light-gray rounded-lg p-4">
            <div class="flex items-center justify-between mb-3">
              <h4 class="text-md font-semibold text-horizon-500">Pension (Tax-Deferred)</h4>
              <span class="px-2 py-1 bg-violet-500 text-white text-xs font-semibold rounded">LONG-TERM</span>
            </div>
            <p class="text-2xl font-bold text-horizon-500 mb-2">
              £{{ formatNumber(analysis?.current_allocation?.pension_value || 0) }}
            </p>
            <div class="space-y-1">
              <div class="flex justify-between text-sm">
                <span class="text-neutral-500">Equities:</span>
                <span class="font-medium">£{{ formatNumber(analysis?.current_allocation?.pension_breakdown?.equities || 0) }}</span>
              </div>
              <div class="flex justify-between text-sm">
                <span class="text-neutral-500">Bonds:</span>
                <span class="font-medium">£{{ formatNumber(analysis?.current_allocation?.pension_breakdown?.bonds || 0) }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Recommendations -->
      <div v-if="recommendations && recommendations.length > 0" class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-horizon-500 mb-4">Optimisation Recommendations</h3>

        <div class="space-y-4">
          <div v-for="(rec, index) in recommendations" :key="index" class="border-l-4 p-4 rounded-r-lg" :class="getRecommendationClass(rec.priority)">
            <div class="flex items-start justify-between mb-2">
              <h4 class="font-semibold text-horizon-500">{{ rec.title }}</h4>
              <div class="flex items-center space-x-2">
                <span class="px-2 py-1 text-xs font-semibold rounded uppercase" :class="getPriorityBadgeClass(rec.priority)">
                  {{ rec.priority }}
                </span>
                <span v-if="rec.tax_saving" class="px-2 py-1 bg-spring-500 text-white text-xs font-semibold rounded">
                  Save £{{ formatNumber(rec.tax_saving) }}/yr
                </span>
              </div>
            </div>
            <p class="text-sm text-neutral-500 mb-3">{{ rec.description }}</p>

            <div v-if="rec.action_details" class="bg-eggshell-500 rounded p-3 text-sm space-y-2">
              <p class="font-medium text-neutral-500">Recommended Action:</p>
              <ul class="space-y-1">
                <li v-for="(action, idx) in rec.action_details" :key="idx" class="flex items-start">
                  <svg class="h-4 w-4 text-violet-600 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                  </svg>
                  <span class="text-neutral-500">{{ action }}</span>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>

      <!-- Asset Type Suitability Matrix -->
      <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-horizon-500 mb-4">Asset Type Suitability by Account</h3>

        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-light-gray">
                <th class="text-left py-3 px-4 font-semibold text-neutral-500">Asset Type</th>
                <th class="text-center py-3 px-4 font-semibold text-spring-700">ISA (Tax-Free)</th>
                <th class="text-center py-3 px-4 font-semibold text-neutral-500">General Account (Taxable)</th>
                <th class="text-center py-3 px-4 font-semibold text-violet-700">Pension (Deferred)</th>
              </tr>
            </thead>
            <tbody>
              <tr class="border-b border-savannah-100">
                <td class="py-3 px-4 font-medium">Dividend-Paying Stocks</td>
                <td class="text-center py-3 px-4">
                  <span class="inline-block w-full px-2 py-1 bg-spring-500 text-white rounded font-semibold">BEST</span>
                </td>
                <td class="text-center py-3 px-4">
                  <span class="inline-block w-full px-2 py-1 bg-raspberry-500 text-white rounded font-semibold">POOR</span>
                </td>
                <td class="text-center py-3 px-4">
                  <span class="inline-block w-full px-2 py-1 bg-spring-500 text-white rounded font-semibold">GOOD</span>
                </td>
              </tr>
              <tr class="border-b border-savannah-100">
                <td class="py-3 px-4 font-medium">Growth Stocks</td>
                <td class="text-center py-3 px-4">
                  <span class="inline-block w-full px-2 py-1 bg-spring-500 text-white rounded font-semibold">GOOD</span>
                </td>
                <td class="text-center py-3 px-4">
                  <span class="inline-block w-full px-2 py-1 bg-violet-500 text-white rounded font-semibold">OK</span>
                </td>
                <td class="text-center py-3 px-4">
                  <span class="inline-block w-full px-2 py-1 bg-spring-500 text-white rounded font-semibold">BEST</span>
                </td>
              </tr>
              <tr class="border-b border-savannah-100">
                <td class="py-3 px-4 font-medium">Corporate Bonds</td>
                <td class="text-center py-3 px-4">
                  <span class="inline-block w-full px-2 py-1 bg-spring-500 text-white rounded font-semibold">BEST</span>
                </td>
                <td class="text-center py-3 px-4">
                  <span class="inline-block w-full px-2 py-1 bg-raspberry-500 text-white rounded font-semibold">POOR</span>
                </td>
                <td class="text-center py-3 px-4">
                  <span class="inline-block w-full px-2 py-1 bg-spring-500 text-white rounded font-semibold">GOOD</span>
                </td>
              </tr>
              <tr class="border-b border-savannah-100">
                <td class="py-3 px-4 font-medium">Index Funds (Low Turnover)</td>
                <td class="text-center py-3 px-4">
                  <span class="inline-block w-full px-2 py-1 bg-spring-500 text-white rounded font-semibold">GOOD</span>
                </td>
                <td class="text-center py-3 px-4">
                  <span class="inline-block w-full px-2 py-1 bg-spring-500 text-white rounded font-semibold">GOOD</span>
                </td>
                <td class="text-center py-3 px-4">
                  <span class="inline-block w-full px-2 py-1 bg-spring-500 text-white rounded font-semibold">GOOD</span>
                </td>
              </tr>
              <tr>
                <td class="py-3 px-4 font-medium">Property Investment Trusts (REITs)</td>
                <td class="text-center py-3 px-4">
                  <span class="inline-block w-full px-2 py-1 bg-spring-500 text-white rounded font-semibold">BEST</span>
                </td>
                <td class="text-center py-3 px-4">
                  <span class="inline-block w-full px-2 py-1 bg-raspberry-500 text-white rounded font-semibold">POOR</span>
                </td>
                <td class="text-center py-3 px-4">
                  <span class="inline-block w-full px-2 py-1 bg-violet-500 text-white rounded font-semibold">OK</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="mt-4 p-4 bg-eggshell-500 rounded-lg">
          <h4 class="text-sm font-semibold text-horizon-500 mb-2">Key Principles:</h4>
          <ul class="space-y-1 text-sm text-neutral-500">
            <li class="flex items-start">
              <svg class="h-4 w-4 text-violet-600 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
              </svg>
              Prioritize high-income assets (bonds, dividend stocks, REITs) in tax-advantaged accounts
            </li>
            <li class="flex items-start">
              <svg class="h-4 w-4 text-violet-600 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
              </svg>
              Growth stocks can be tax-efficient in General Investment Accounts (capital gains tax allowance, lower rates than income tax)
            </li>
            <li class="flex items-start">
              <svg class="h-4 w-4 text-violet-600 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
              </svg>
              Pensions are ideal for long-term growth with highest expected returns
            </li>
          </ul>
        </div>
      </div>

      <!-- Action Plan -->
      <div v-if="analysis?.action_plan && analysis.action_plan.length > 0" class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-horizon-500 mb-4">Optimisation Action Plan</h3>

        <div class="space-y-3">
          <div v-for="(step, index) in analysis.action_plan" :key="index" class="flex items-start p-4 bg-eggshell-500 rounded-lg border border-light-gray">
            <div class="flex-shrink-0 w-8 h-8 bg-raspberry-500 text-white rounded-full flex items-center justify-center font-semibold mr-4">
              {{ index + 1 }}
            </div>
            <div class="flex-1">
              <h4 class="font-semibold text-horizon-500 mb-1">{{ step.title }}</h4>
              <p class="text-sm text-neutral-500 mb-2">{{ step.description }}</p>
              <div v-if="step.savings" class="text-sm font-medium text-spring-600">
                Estimated Annual Savings: £{{ formatNumber(step.savings) }}
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
import { SUCCESS_COLORS, BORDER_COLORS, CHART_DEFAULTS, CHART_COLORS } from '@/constants/designSystem';
import { currencyMixin } from '@/mixins/currencyMixin';

import logger from '@/utils/logger';
export default {
  name: 'AssetLocationOptimiser',

  mixins: [currencyMixin],

  data() {
    return {
      loading: true,
      error: null,
      analysis: null,
      recommendations: null,
    };
  },

  computed: {
    isPreviewMode() {
      return this.$store.getters['preview/isPreviewMode'];
    },

    optimisationLabel() {
      const score = this.analysis?.optimization_score?.score || 0;
      if (score >= 80) return 'Well Optimised';
      if (score >= 60) return 'Partially Optimised';
      if (score >= 40) return 'Optimisation Possible';
      return 'Needs Optimisation';
    },

    optimizationScoreChartOptions() {
      return {
        chart: {
          ...CHART_DEFAULTS.chart,
          type: 'radialBar',
          sparkline: {
            enabled: true,
          },
        },
        plotOptions: {
          radialBar: {
            startAngle: -90,
            endAngle: 90,
            hollow: {
              size: '60%',
            },
            track: {
              background: BORDER_COLORS.default,
              strokeWidth: '100%',
            },
            dataLabels: {
              name: {
                show: false,
              },
              value: {
                offsetY: -10,
                fontSize: '28px',
                fontWeight: 'bold',
                formatter: function(val) {
                  return Math.round(val);
                },
              },
            },
          },
        },
        fill: {
          type: 'gradient',
          gradient: {
            shade: 'dark',
            type: 'horizontal',
            shadeIntensity: 0.5,
            gradientToColors: [SUCCESS_COLORS[500]],
            inverseColors: false,
            opacityFrom: 1,
            opacityTo: 1,
            stops: [0, 100],
          },
        },
        colors: [CHART_COLORS[5]], // Purple
      };
    },
  },

  mounted() {
    // Preview users are real DB users - use normal API to fetch their data
    this.loadAnalysis();
  },

  methods: {
    async loadAnalysis() {
      // Preview users are real DB users - use normal API to fetch their data
      this.loading = true;
      this.error = null;

      try {
        // Fetch asset location analysis
        const analysisResponse = await api.get('/investment/asset-location/analyze');
        this.analysis = analysisResponse.data.data;

        // Fetch recommendations
        const recResponse = await api.get('/investment/asset-location/recommendations');
        this.recommendations = recResponse.data.recommendations || [];
      } catch (err) {
        logger.error('Error loading asset location analysis:', err);
        this.error = err.response?.data?.message || 'Failed to load asset location analysis. Please try again.';
      } finally {
        this.loading = false;
      }
    },

    getScoreColour(score) {
      if (score >= 80) return 'text-spring-600';
      if (score >= 60) return 'text-violet-600';
      if (score >= 40) return 'text-violet-600';
      return 'text-raspberry-600';
    },

    getRecommendationClass(priority) {
      const classes = {
        high: 'border-raspberry-500 bg-white',
        medium: 'border-violet-500 bg-white',
        low: 'border-violet-500 bg-white',
      };
      return classes[priority] || classes.low;
    },

    getPriorityBadgeClass(priority) {
      const classes = {
        high: 'bg-raspberry-500 text-white',
        medium: 'bg-violet-500 text-white',
        low: 'bg-violet-500 text-white',
      };
      return classes[priority] || classes.low;
    },
  },
};
</script>

<style scoped>
/* Component-specific styles */
</style>
