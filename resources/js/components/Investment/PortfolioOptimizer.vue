<template>
  <div class="portfolio-optimiser">
    <div class="flex justify-between items-center mb-6">
      <div>
        <h3 class="text-lg font-semibold text-horizon-500">Portfolio Optimiser</h3>
        <p class="text-sm text-neutral-500 mt-1">
          Find optimal asset allocation based on your preferences
        </p>
      </div>
      <button
        v-if="optimizationResult"
        @click="resetOptimiser"
        class="px-4 py-2 text-sm font-medium text-neutral-500 bg-white border border-horizon-300 rounded-lg hover:bg-eggshell-500"
      >
        New Optimisation
      </button>
    </div>

    <!-- Optimization Form -->
    <div v-if="!optimizationResult" class="space-y-6">
      <!-- Strategy Selection -->
      <div class="bg-white rounded-lg border border-light-gray p-6">
        <label class="block text-sm font-medium text-horizon-500 mb-3">
          Optimisation Strategy
        </label>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <button
            v-for="strategy in strategies"
            :key="strategy.value"
            @click="selectedStrategy = strategy.value"
            :class="[
              'text-left p-4 rounded-lg border-2 transition-all',
              selectedStrategy === strategy.value
                ? 'border-violet-600 bg-violet-500 text-white'
                : 'border-light-gray bg-white hover:border-horizon-300'
            ]"
          >
            <div class="flex items-start">
              <div class="flex-shrink-0">
                <div :class="[
                  'w-5 h-5 rounded-full border-2 flex items-center justify-center',
                  selectedStrategy === strategy.value
                    ? 'border-violet-600 bg-raspberry-500'
                    : 'border-horizon-300'
                ]">
                  <div v-if="selectedStrategy === strategy.value" class="w-2 h-2 bg-white rounded-full"></div>
                </div>
              </div>
              <div class="ml-3 flex-1">
                <p :class="['text-sm font-medium', selectedStrategy === strategy.value ? 'text-white' : 'text-horizon-500']">{{ strategy.name }}</p>
                <p :class="['text-xs mt-1', selectedStrategy === strategy.value ? 'text-violet-100' : 'text-neutral-500']">{{ strategy.description }}</p>
              </div>
            </div>
          </button>
        </div>
      </div>

      <!-- Target Return Input (only for target_return strategy) -->
      <div v-if="selectedStrategy === 'target_return'" class="bg-white rounded-lg border border-light-gray p-6">
        <label class="block text-sm font-medium text-horizon-500 mb-2">
          Target Return
        </label>
        <div class="flex items-center gap-4">
          <input
            v-model.number="targetReturn"
            type="number"
            step="0.01"
            min="0"
            max="1"
            class="w-32 px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
            placeholder="0.08"
          />
          <span class="text-sm text-neutral-500">
            ({{ (targetReturn * 100).toFixed(1) }}% annual return)
          </span>
        </div>
        <p class="text-xs text-neutral-500 mt-2">
          Enter desired return as decimal (e.g., 0.08 for 8%)
        </p>
      </div>

      <!-- Constraints -->
      <div class="bg-white rounded-lg border border-light-gray p-6">
        <h4 class="text-sm font-medium text-horizon-500 mb-4">Portfolio Constraints (Optional)</h4>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <!-- Minimum Weight -->
          <div>
            <label class="block text-xs font-medium text-neutral-500 mb-2">
              Minimum Weight per Asset
            </label>
            <div class="flex items-center gap-2">
              <input
                v-model.number="constraints.minWeight"
                type="number"
                step="0.01"
                min="0"
                max="1"
                class="w-full px-3 py-2 border border-horizon-300 rounded-lg text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                placeholder="0.00"
              />
              <span class="text-sm text-neutral-500 whitespace-nowrap">
                ({{ (constraints.minWeight * 100).toFixed(0) }}%)
              </span>
            </div>
          </div>

          <!-- Maximum Weight -->
          <div>
            <label class="block text-xs font-medium text-neutral-500 mb-2">
              Maximum Weight per Asset
            </label>
            <div class="flex items-center gap-2">
              <input
                v-model.number="constraints.maxWeight"
                type="number"
                step="0.01"
                min="0"
                max="1"
                class="w-full px-3 py-2 border border-horizon-300 rounded-lg text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                placeholder="1.00"
              />
              <span class="text-sm text-neutral-500 whitespace-nowrap">
                ({{ (constraints.maxWeight * 100).toFixed(0) }}%)
              </span>
            </div>
          </div>
        </div>

        <p class="text-xs text-neutral-500 mt-3">
          Set constraints to prevent over-concentration. Leave at defaults (0% - 100%) for no restrictions.
        </p>
      </div>

      <!-- Optimise Button -->
      <div class="flex justify-end">
        <button
          @click="runOptimization"
          :disabled="loading || !isFormValid"
          class="px-6 py-3 bg-raspberry-500 text-white font-medium rounded-button hover:bg-raspberry-600 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {{ loading ? 'Optimising...' : 'Run Optimization' }}
        </button>
      </div>

      <!-- Error Display -->
      <div v-if="error" class="bg-eggshell-500 rounded-lg p-4">
        <div class="flex">
          <svg class="h-5 w-5 text-raspberry-600 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <div>
            <h4 class="text-sm font-medium text-raspberry-800 mb-1">Optimization Failed</h4>
            <p class="text-sm text-raspberry-700">{{ error }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Optimization Results -->
    <div v-else class="space-y-6">
      <!-- Results Summary -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white rounded-lg border border-light-gray p-4">
          <p class="text-xs text-neutral-500 mb-1">Expected Return</p>
          <p class="text-2xl font-bold text-horizon-500">{{ formatPercentage(optimizationResult.expected_return) }}</p>
          <p class="text-xs text-neutral-500 mt-1">Annual</p>
        </div>
        <div class="bg-white rounded-lg border border-light-gray p-4">
          <p class="text-xs text-neutral-500 mb-1">Expected Risk</p>
          <p class="text-2xl font-bold text-horizon-500">{{ formatPercentage(optimizationResult.expected_risk) }}</p>
          <p class="text-xs text-neutral-500 mt-1">Standard Deviation</p>
        </div>
        <div class="bg-white rounded-lg border border-light-gray p-4">
          <p class="text-xs text-neutral-500 mb-1">Sharpe Ratio</p>
          <p class="text-2xl font-bold text-violet-600">
            {{ optimizationResult.sharpe_ratio?.toFixed(2) || 'N/A' }}
          </p>
          <p class="text-xs text-neutral-500 mt-1">Risk-adjusted return</p>
        </div>
      </div>

      <!-- Allocation Chart -->
      <div class="bg-white rounded-lg border border-light-gray p-6">
        <h4 class="text-sm font-semibold text-horizon-500 mb-4">Optimal Allocation</h4>
        <div v-if="allocationSeries.length > 0" class="flex justify-center">
          <div class="relative" style="width: 260px; height: 260px;">
            <svg viewBox="0 0 220 220" width="260" height="260">
              <defs>
                <linearGradient
                  v-for="(seg, idx) in optimizerDonutSegments"
                  :key="'grad-' + idx"
                  :id="'opt-grad-' + idx"
                  x1="0%" y1="0%" x2="100%" y2="0%"
                >
                  <stop offset="0%" :stop-color="seg.color" />
                  <stop offset="100%" :stop-color="seg.colorLight" />
                </linearGradient>
              </defs>
              <circle
                v-for="(seg, idx) in optimizerDonutSegments"
                :key="'seg-' + idx"
                cx="110" cy="110" r="75"
                fill="none"
                :stroke="'url(#opt-grad-' + idx + ')'"
                stroke-width="40"
                stroke-linecap="round"
                :stroke-dasharray="seg.arcLength + ' ' + 471.2"
                :stroke-dashoffset="-seg.offset"
                transform="rotate(-90 110 110)"
              />
            </svg>
            <div class="absolute inset-0 flex flex-col items-center justify-center">
              <span class="text-[10px] font-semibold text-horizon-400">Total</span>
              <span class="text-xl font-bold text-horizon-700">100%</span>
            </div>
          </div>
        </div>
        <!-- Legend -->
        <div v-if="allocationSeries.length > 0" class="mt-3 flex flex-wrap justify-center gap-x-4 gap-y-1.5">
          <div v-for="(weight, idx) in optimizerFilteredWeights" :key="idx" class="flex items-center gap-2">
            <span class="w-2.5 h-2.5 rounded-full" :style="{ backgroundColor: chartColors[idx % chartColors.length] }"></span>
            <span class="text-xs text-neutral-500">Asset {{ weight.originalIndex + 1 }}: {{ (weight.value * 100).toFixed(1) }}%</span>
          </div>
        </div>
      </div>

      <!-- Allocation Table -->
      <div class="bg-white rounded-lg border border-light-gray p-6">
        <h4 class="text-sm font-semibold text-horizon-500 mb-4">Asset Weights</h4>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-light-gray">
            <thead>
              <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Asset</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-neutral-500 uppercase tracking-wider">Weight</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-neutral-500 uppercase tracking-wider">Percentage</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-light-gray">
              <tr v-for="(weight, index) in optimizationResult.weights" :key="index">
                <td class="px-4 py-3 text-sm text-horizon-500">
                  Asset {{ index + 1 }}
                </td>
                <td class="px-4 py-3 text-sm text-horizon-500 text-right font-medium">
                  {{ weight.toFixed(4) }}
                </td>
                <td class="px-4 py-3 text-sm text-horizon-500 text-right">
                  {{ (weight * 100).toFixed(2) }}%
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Optimization Details -->
      <div class="bg-eggshell-500 rounded-lg border border-light-gray p-6">
        <h4 class="text-sm font-semibold text-horizon-500 mb-3">Optimization Details</h4>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
          <div>
            <span class="text-neutral-500">Strategy:</span>
            <span class="ml-2 font-medium text-horizon-500">
              {{ strategies.find(s => s.value === selectedStrategy)?.name }}
            </span>
          </div>
          <div>
            <span class="text-neutral-500">Optimization Type:</span>
            <span class="ml-2 font-medium text-horizon-500">{{ optimizationResult.optimization_type }}</span>
          </div>
          <div v-if="optimizationResult.risk_free_rate">
            <span class="text-neutral-500">Risk-free Rate:</span>
            <span class="ml-2 font-medium text-horizon-500">{{ formatPercentage(optimizationResult.risk_free_rate) }}</span>
          </div>
          <div v-if="optimizationResult.target_return">
            <span class="text-neutral-500">Target Return:</span>
            <span class="ml-2 font-medium text-horizon-500">{{ formatPercentage(optimizationResult.target_return) }}</span>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex justify-between items-center">
        <button
          @click="$emit('view-frontier')"
          class="px-4 py-2 text-sm font-medium text-neutral-500 bg-white border border-horizon-300 rounded-lg hover:bg-eggshell-500"
        >
          View on Efficient Frontier
        </button>
        <button
          @click="applyAllocation"
          class="px-6 py-2 text-sm font-medium text-white bg-spring-600 rounded-lg hover:bg-spring-700"
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
import { CHART_COLORS, CHART_DEFAULTS } from '@/constants/designSystem';

import logger from '@/utils/logger';
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
      chartColors: CHART_COLORS,

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

    optimizerFilteredWeights() {
      if (!this.optimizationResult?.weights) return [];
      return this.optimizationResult.weights
        .map((value, originalIndex) => ({ value, originalIndex }))
        .filter(w => w.value > 0.001);
    },

    optimizerDonutSegments() {
      const series = this.allocationSeries;
      const total = series.reduce((sum, v) => sum + v, 0);
      if (total === 0) return [];

      const circumference = 471.2;
      const gap = 3;
      let offset = 0;
      return series.map((value, idx) => {
        const proportion = value / total;
        const arcLength = Math.max(proportion * circumference - gap, 2);
        const color = CHART_COLORS[idx % CHART_COLORS.length];
        const seg = {
          color,
          colorLight: this.lightenColor(color, 0.35),
          arcLength,
          offset,
        };
        offset += proportion * circumference;
        return seg;
      });
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
        } else {
          this.error = response.message || 'Optimization failed';
        }
      } catch (err) {
        logger.error('Optimization error:', err);
        this.error = err.message || 'Failed to run optimization';
      } finally {
        this.loading = false;
      }
    },

    resetOptimiser() {
      this.optimizationResult = null;
      this.error = null;
    },

    applyAllocation() {
      this.$emit('apply-allocation', this.optimizationResult);
    },

    lightenColor(hex, amount) {
      const r = parseInt(hex.slice(1, 3), 16);
      const g = parseInt(hex.slice(3, 5), 16);
      const b = parseInt(hex.slice(5, 7), 16);
      const lighten = (c) => Math.min(255, Math.round(c + (255 - c) * amount));
      return `#${lighten(r).toString(16).padStart(2, '0')}${lighten(g).toString(16).padStart(2, '0')}${lighten(b).toString(16).padStart(2, '0')}`;
    },

    formatPercentage(value) {
      if (value === null || value === undefined) return 'N/A';
      return `${(value * 100).toFixed(2)}%`;
    },
  },
};
</script>
