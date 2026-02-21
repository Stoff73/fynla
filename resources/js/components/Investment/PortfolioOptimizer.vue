<template>
  <div class="portfolio-optimiser">
    <div class="flex justify-between items-center mb-6">
      <div>
        <h3 class="text-lg font-semibold text-gray-900">Portfolio Optimiser</h3>
        <p class="text-sm text-gray-600 mt-1">
          Find optimal asset allocation based on your preferences
        </p>
      </div>
      <button
        v-if="optimizationResult"
        @click="resetOptimiser"
        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
      >
        New Optimization
      </button>
    </div>

    <!-- Optimization Form -->
    <div v-if="!optimizationResult" class="space-y-6">
      <!-- Strategy Selection -->
      <div class="bg-white rounded-lg border border-gray-200 p-6">
        <label class="block text-sm font-medium text-gray-900 mb-3">
          Optimization Strategy
        </label>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <button
            v-for="strategy in strategies"
            :key="strategy.value"
            @click="selectedStrategy = strategy.value"
            :class="[
              'text-left p-4 rounded-lg border-2 transition-all',
              selectedStrategy === strategy.value
                ? 'border-blue-600 bg-blue-500 text-white'
                : 'border-gray-200 bg-white hover:border-gray-300'
            ]"
          >
            <div class="flex items-start">
              <div class="flex-shrink-0">
                <div :class="[
                  'w-5 h-5 rounded-full border-2 flex items-center justify-center',
                  selectedStrategy === strategy.value
                    ? 'border-blue-600 bg-primary-600'
                    : 'border-gray-300'
                ]">
                  <div v-if="selectedStrategy === strategy.value" class="w-2 h-2 bg-white rounded-full"></div>
                </div>
              </div>
              <div class="ml-3 flex-1">
                <p :class="['text-sm font-medium', selectedStrategy === strategy.value ? 'text-white' : 'text-gray-900']">{{ strategy.name }}</p>
                <p :class="['text-xs mt-1', selectedStrategy === strategy.value ? 'text-blue-100' : 'text-gray-600']">{{ strategy.description }}</p>
              </div>
            </div>
          </button>
        </div>
      </div>

      <!-- Target Return Input (only for target_return strategy) -->
      <div v-if="selectedStrategy === 'target_return'" class="bg-white rounded-lg border border-gray-200 p-6">
        <label class="block text-sm font-medium text-gray-900 mb-2">
          Target Return
        </label>
        <div class="flex items-center gap-4">
          <input
            v-model.number="targetReturn"
            type="number"
            step="0.01"
            min="0"
            max="1"
            class="w-32 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
            placeholder="0.08"
          />
          <span class="text-sm text-gray-600">
            ({{ (targetReturn * 100).toFixed(1) }}% annual return)
          </span>
        </div>
        <p class="text-xs text-gray-500 mt-2">
          Enter desired return as decimal (e.g., 0.08 for 8%)
        </p>
      </div>

      <!-- Constraints -->
      <div class="bg-white rounded-lg border border-gray-200 p-6">
        <h4 class="text-sm font-medium text-gray-900 mb-4">Portfolio Constraints (Optional)</h4>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <!-- Minimum Weight -->
          <div>
            <label class="block text-xs font-medium text-gray-700 mb-2">
              Minimum Weight per Asset
            </label>
            <div class="flex items-center gap-2">
              <input
                v-model.number="constraints.minWeight"
                type="number"
                step="0.01"
                min="0"
                max="1"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                placeholder="0.00"
              />
              <span class="text-sm text-gray-600 whitespace-nowrap">
                ({{ (constraints.minWeight * 100).toFixed(0) }}%)
              </span>
            </div>
          </div>

          <!-- Maximum Weight -->
          <div>
            <label class="block text-xs font-medium text-gray-700 mb-2">
              Maximum Weight per Asset
            </label>
            <div class="flex items-center gap-2">
              <input
                v-model.number="constraints.maxWeight"
                type="number"
                step="0.01"
                min="0"
                max="1"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                placeholder="1.00"
              />
              <span class="text-sm text-gray-600 whitespace-nowrap">
                ({{ (constraints.maxWeight * 100).toFixed(0) }}%)
              </span>
            </div>
          </div>
        </div>

        <p class="text-xs text-gray-500 mt-3">
          Set constraints to prevent over-concentration. Leave at defaults (0% - 100%) for no restrictions.
        </p>
      </div>

      <!-- Optimise Button -->
      <div class="flex justify-end">
        <button
          @click="runOptimization"
          :disabled="loading || !isFormValid"
          class="px-6 py-3 bg-primary-600 text-white font-medium rounded-button hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {{ loading ? 'Optimising...' : 'Run Optimization' }}
        </button>
      </div>

      <!-- Error Display -->
      <div v-if="error" class="bg-gray-50 rounded-lg p-4">
        <div class="flex">
          <svg class="h-5 w-5 text-red-600 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <div>
            <h4 class="text-sm font-medium text-red-800 mb-1">Optimization Failed</h4>
            <p class="text-sm text-red-700">{{ error }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Optimization Results -->
    <div v-else class="space-y-6">
      <!-- Results Summary -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white rounded-lg border border-gray-200 p-4">
          <p class="text-xs text-gray-600 mb-1">Expected Return</p>
          <p class="text-2xl font-bold text-gray-900">{{ formatPercentage(optimizationResult.expected_return) }}</p>
          <p class="text-xs text-gray-600 mt-1">Annual</p>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-4">
          <p class="text-xs text-gray-600 mb-1">Expected Risk</p>
          <p class="text-2xl font-bold text-gray-900">{{ formatPercentage(optimizationResult.expected_risk) }}</p>
          <p class="text-xs text-gray-600 mt-1">Standard Deviation</p>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-4">
          <p class="text-xs text-gray-600 mb-1">Sharpe Ratio</p>
          <p class="text-2xl font-bold text-blue-600">
            {{ optimizationResult.sharpe_ratio?.toFixed(2) || 'N/A' }}
          </p>
          <p class="text-xs text-gray-600 mt-1">Risk-adjusted return</p>
        </div>
      </div>

      <!-- Allocation Chart -->
      <div class="bg-white rounded-lg border border-gray-200 p-6">
        <h4 class="text-sm font-semibold text-gray-900 mb-4">Optimal Allocation</h4>
        <apexchart
          v-if="allocationChartReady"
          type="donut"
          :options="allocationChartOptions"
          :series="allocationSeries"
          height="300"
        />
      </div>

      <!-- Allocation Table -->
      <div class="bg-white rounded-lg border border-gray-200 p-6">
        <h4 class="text-sm font-semibold text-gray-900 mb-4">Asset Weights</h4>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead>
              <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Asset</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Weight</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Percentage</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="(weight, index) in optimizationResult.weights" :key="index">
                <td class="px-4 py-3 text-sm text-gray-900">
                  Asset {{ index + 1 }}
                </td>
                <td class="px-4 py-3 text-sm text-gray-900 text-right font-medium">
                  {{ weight.toFixed(4) }}
                </td>
                <td class="px-4 py-3 text-sm text-gray-900 text-right">
                  {{ (weight * 100).toFixed(2) }}%
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Optimization Details -->
      <div class="bg-gray-50 rounded-lg border border-gray-200 p-6">
        <h4 class="text-sm font-semibold text-gray-900 mb-3">Optimization Details</h4>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
          <div>
            <span class="text-gray-600">Strategy:</span>
            <span class="ml-2 font-medium text-gray-900">
              {{ strategies.find(s => s.value === selectedStrategy)?.name }}
            </span>
          </div>
          <div>
            <span class="text-gray-600">Optimization Type:</span>
            <span class="ml-2 font-medium text-gray-900">{{ optimizationResult.optimization_type }}</span>
          </div>
          <div v-if="optimizationResult.risk_free_rate">
            <span class="text-gray-600">Risk-free Rate:</span>
            <span class="ml-2 font-medium text-gray-900">{{ formatPercentage(optimizationResult.risk_free_rate) }}</span>
          </div>
          <div v-if="optimizationResult.target_return">
            <span class="text-gray-600">Target Return:</span>
            <span class="ml-2 font-medium text-gray-900">{{ formatPercentage(optimizationResult.target_return) }}</span>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex justify-between items-center">
        <button
          @click="$emit('view-frontier')"
          class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
        >
          View on Efficient Frontier
        </button>
        <button
          @click="applyAllocation"
          class="px-6 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700"
        >
          Apply This Allocation
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import VueApexCharts from 'vue3-apexcharts';
import portfolioOptimizationService from '@/services/portfolioOptimizationService';
import { CHART_COLORS } from '@/constants/designSystem';

export default {
  name: 'PortfolioOptimiser',

  emits: ['view-frontier', 'apply-allocation'],

  components: {
    apexchart: VueApexCharts,
  },

  data() {
    return {
      loading: false,
      error: null,
      selectedStrategy: 'max_sharpe',
      targetReturn: 0.08,
      constraints: {
        minWeight: 0.00,
        maxWeight: 1.00,
      },
      optimizationResult: null,
      allocationChartReady: false,

      strategies: [
        {
          value: 'max_sharpe',
          name: 'Maximum Sharpe Ratio',
          description: 'Best risk-adjusted returns (recommended for most investors)',
        },
        {
          value: 'min_variance',
          name: 'Minimum Variance',
          description: 'Lowest possible risk, regardless of return',
        },
        {
          value: 'target_return',
          name: 'Target Return',
          description: 'Achieve specific return with minimum risk',
        },
        {
          value: 'risk_parity',
          name: 'Risk Parity',
          description: 'Equal risk contribution from each asset',
        },
      ],
    };
  },

  computed: {
    isFormValid() {
      if (this.selectedStrategy === 'target_return') {
        return this.targetReturn > 0 && this.targetReturn <= 1;
      }
      return this.constraints.minWeight <= this.constraints.maxWeight;
    },

    allocationSeries() {
      if (!this.optimizationResult || !this.optimizationResult.weights) {
        return [];
      }
      return this.optimizationResult.weights.filter(w => w > 0.001);
    },

    allocationChartOptions() {
      const labels = this.optimizationResult.weights
        .map((w, i) => `Asset ${i + 1}`)
        .filter((_, i) => this.optimizationResult.weights[i] > 0.001);

      return {
        chart: {
          type: 'donut',
        },
        labels: labels,
        colors: CHART_COLORS,
        legend: {
          position: 'bottom',
        },
        dataLabels: {
          enabled: true,
          formatter: (val) => val.toFixed(1) + '%',
        },
        plotOptions: {
          pie: {
            donut: {
              size: '65%',
              labels: {
                show: true,
                total: {
                  show: true,
                  label: 'Total',
                  formatter: () => '100%',
                },
              },
            },
          },
        },
        tooltip: {
          y: {
            formatter: (val) => val.toFixed(2) + '%',
          },
        },
      };
    },
  },

  methods: {
    async runOptimization() {
      this.loading = true;
      this.error = null;

      try {
        const params = {
          optimization_type: this.selectedStrategy,
          constraints: {
            min_weight: this.constraints.minWeight,
            max_weight: this.constraints.maxWeight,
          },
        };

        if (this.selectedStrategy === 'target_return') {
          params.target_return = this.targetReturn;
        }

        const response = await portfolioOptimizationService.optimise(params);

        if (response.success) {
          this.optimizationResult = response.data;
          this.$nextTick(() => {
            this.allocationChartReady = true;
          });
        } else {
          this.error = response.message || 'Optimization failed';
        }
      } catch (err) {
        console.error('Optimization error:', err);
        this.error = err.message || 'Failed to run optimization';
      } finally {
        this.loading = false;
      }
    },

    resetOptimiser() {
      this.optimizationResult = null;
      this.allocationChartReady = false;
      this.error = null;
    },

    applyAllocation() {
      this.$emit('apply-allocation', this.optimizationResult);
    },

    formatPercentage(value) {
      if (value === null || value === undefined) return 'N/A';
      return `${(value * 100).toFixed(2)}%`;
    },
  },
};
</script>
