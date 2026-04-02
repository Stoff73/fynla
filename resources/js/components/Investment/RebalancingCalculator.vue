<template>
  <div class="rebalancing-calculator">
    <!-- Header -->
    <div class="mb-6">
      <h2 class="text-2xl font-bold text-horizon-500 mb-2">Portfolio Rebalancing</h2>
      <p class="text-neutral-500">
        Calculate the specific trades needed to reach your target portfolio allocation,
        with automatic Capital Gains Tax optimization.
      </p>
    </div>

    <!-- Configuration Panel -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
      <h3 class="text-lg font-semibold text-horizon-500 mb-4">Configuration</h3>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Source Selection -->
        <div>
          <label class="block text-sm font-medium text-neutral-500 mb-2">
            Rebalancing Source
          </label>
          <select
            v-model="source"
            class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500"
            @change="onSourceChange"
          >
            <option value="optimization">From Portfolio Optimisation</option>
            <option value="manual">Manual Target Weights</option>
          </select>
          <p class="mt-1 text-xs text-neutral-500">
            {{ source === 'optimization' ? 'Use optimal weights from MPT analysis' : 'Enter custom target allocation' }}
          </p>
        </div>

        <!-- Minimum Trade Size -->
        <div>
          <label class="block text-sm font-medium text-neutral-500 mb-2">
            Minimum Trade Size (£)
          </label>
          <input
            v-model.number="minTradeSize"
            type="number"
            min="0"
            step="50"
            class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500"
          />
          <p class="mt-1 text-xs text-neutral-500">
            Ignore trades smaller than this amount
          </p>
        </div>

        <!-- CGT Optimization -->
        <div class="md:col-span-2">
          <div class="flex items-center">
            <input
              v-model="optimiseForCGT"
              type="checkbox"
              id="optimiseCGT"
              class="h-4 w-4 text-violet-600 focus:ring-violet-500 border-horizon-300 rounded"
            />
            <label for="optimiseCGT" class="ml-2 block text-sm font-medium text-neutral-500">
              Optimise for Capital Gains Tax
            </label>
          </div>
          <p class="mt-1 ml-6 text-xs text-neutral-500">
            Minimize Capital Gains Tax liability by optimising the order of buy/sell actions
          </p>
        </div>

        <!-- CGT Settings (shown if optimization enabled) -->
        <template v-if="optimiseForCGT">
          <div>
            <label class="block text-sm font-medium text-neutral-500 mb-2">
              Capital Gains Tax Annual Allowance (£)
            </label>
            <input
              v-model.number="cgtAllowance"
              type="number"
              min="0"
              step="100"
              class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500"
            />
            <p class="mt-1 text-xs text-neutral-500">
              UK: £{{ cgtAllowance.toLocaleString('en-GB') }} for {{ currentTaxYear }}
            </p>
          </div>

          <div>
            <label class="block text-sm font-medium text-neutral-500 mb-2">
              Capital Gains Tax Rate (%)
            </label>
            <select
              v-model.number="taxRate"
              class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500"
            >
              <option :value="0.10">10% (Basic Rate)</option>
              <option :value="0.20">20% (Higher Rate)</option>
            </select>
          </div>

          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-neutral-500 mb-2">
              Loss Carryforward (£)
            </label>
            <input
              v-model.number="lossCarryforward"
              type="number"
              min="0"
              step="100"
              class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500"
            />
            <p class="mt-1 text-xs text-neutral-500">
              Capital losses carried forward from previous tax years
            </p>
          </div>
        </template>
      </div>

      <!-- Calculate Button -->
      <div class="mt-6">
        <button
          @click="calculateRebalancing"
          :disabled="loading || !canCalculate"
          class="w-full px-4 py-2 text-white bg-raspberry-500 rounded-button hover:bg-raspberry-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-violet-500 disabled:bg-savannah-300 disabled:cursor-not-allowed"
        >
          <span v-if="loading" class="flex items-center justify-center">
            <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Calculating...
          </span>
          <span v-else>Calculate Rebalancing</span>
        </button>
      </div>
    </div>

    <!-- Error Message -->
    <div
      v-if="error"
      class="mb-6 bg-white border-l-4 border-raspberry-500 p-4"
    >
      <div class="flex">
        <div class="flex-shrink-0">
          <svg class="h-5 w-5 text-raspberry-400" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
          </svg>
        </div>
        <div class="ml-3">
          <p class="text-sm text-raspberry-700">{{ error }}</p>
        </div>
      </div>
    </div>

    <!-- Results -->
    <div v-if="result" class="space-y-6">
      <!-- Allocation Comparison -->
      <AllocationComparison
        :current-allocations="result.current_allocations"
        :target-allocations="result.target_allocations"
        :metrics="result.metrics"
      />

      <!-- Rebalancing Actions -->
      <RebalancingActions
        :actions="result.actions"
        :summary="result.summary"
        :cgt-analysis="result.cgt_analysis"
        :cgt-summary="result.cgt_summary"
        :tax-loss-opportunities="result.tax_loss_opportunities"
        :show-c-g-t="optimiseForCGT"
        @save-actions="saveActions"
        @remove-action="removeAction"
      />

      <!-- Action Buttons -->
      <div class="flex justify-end space-x-3">
        <button
          @click="clearResults"
          class="px-4 py-2 text-neutral-500 bg-white border border-horizon-300 rounded-button hover:bg-eggshell-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-violet-500"
        >
          Clear
        </button>
        <button
          @click="exportResults"
          class="px-4 py-2 text-neutral-500 bg-white border border-horizon-300 rounded-button hover:bg-eggshell-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-violet-500"
        >
          Export to CSV
        </button>
      </div>
    </div>

    <!-- Empty State -->
    <div
      v-else-if="!loading"
      class="bg-white rounded-lg shadow p-12 text-center"
    >
      <svg
        class="mx-auto h-16 w-16 text-horizon-400"
        fill="none"
        stroke="currentColor"
        viewBox="0 0 24 24"
      >
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          stroke-width="2"
          d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
        />
      </svg>
      <h3 class="mt-4 text-lg font-medium text-horizon-500">
        Ready to Calculate Rebalancing
      </h3>
      <p class="mt-2 text-sm text-neutral-500">
        Configure your settings above and click "Calculate Rebalancing" to see the trades needed to reach your target allocation.
      </p>
    </div>
  </div>
</template>

<script>
import { mapState } from 'vuex';
import rebalancingService from '@/services/rebalancingService';
import AllocationComparison from './AllocationComparison.vue';
import RebalancingActions from './RebalancingActions.vue';
import { getCurrentTaxYear } from '@/utils/dateFormatter';
import { CGT_ANNUAL_ALLOWANCE } from '@/constants/taxConfig';

import logger from '@/utils/logger';
export default {
  name: 'RebalancingCalculator',

  emits: ['actions-saved'],

  components: {
    AllocationComparison,
    RebalancingActions,
  },

  data() {
    return {
      source: 'optimization',
      minTradeSize: 100,
      optimiseForCGT: true,
      cgtAllowance: CGT_ANNUAL_ALLOWANCE,
      taxRate: 0.20,
      lossCarryforward: 0,
      loading: false,
      error: null,
      result: null,
    };
  },

  computed: {
    ...mapState('investment', ['optimizationResult']),

    currentTaxYear() {
      return getCurrentTaxYear();
    },

    canCalculate() {
      if (this.source === 'optimization') {
        return this.optimizationResult && this.optimizationResult.weights;
      }
      return true;
    },
  },

  methods: {
    onSourceChange() {
      this.result = null;
      this.error = null;
    },

    async calculateRebalancing() {
      this.loading = true;
      this.error = null;
      this.result = null;

      try {
        let response;

        if (this.source === 'optimization') {
          // Use optimization result
          if (!this.optimizationResult || !this.optimizationResult.weights) {
            throw new Error('No optimization result available. Please run portfolio optimization first.');
          }

          response = await rebalancingService.calculateFromOptimization({
            weights: this.optimizationResult.weights,
            labels: this.optimizationResult.labels,
            min_trade_size: this.minTradeSize,
            optimise_for_cgt: this.optimiseForCGT,
            cgt_allowance: this.cgtAllowance,
            tax_rate: this.taxRate,
            loss_carryforward: this.lossCarryforward,
          });
        } else {
          // Manual weights (would need UI for this)
          throw new Error('Manual weight entry not yet implemented');
        }

        if (response.success) {
          this.result = response.data;
        } else {
          this.error = response.message || 'Failed to calculate rebalancing';
        }
      } catch (err) {
        logger.error('Rebalancing calculation error:', err);
        this.error = err.response?.data?.message || err.message || 'Failed to calculate rebalancing';
      } finally {
        this.loading = false;
      }
    },

    async saveActions() {
      if (!this.result || !this.result.actions || this.result.actions.length === 0) {
        return;
      }

      try {
        const response = await rebalancingService.saveRebalancingActions(this.result.actions);

        if (response.success) {
          this.$emit('actions-saved', response.data);
          // Show success message (could use a toast notification)
          alert(`${response.data.length} rebalancing action(s) saved successfully`);
        } else {
          throw new Error(response.message || 'Failed to save actions');
        }
      } catch (err) {
        logger.error('Failed to save rebalancing actions:', err);
        this.error = err.response?.data?.message || err.message || 'Failed to save actions';
      }
    },

    removeAction(index) {
      if (this.result && this.result.actions) {
        this.result.actions.splice(index, 1);
      }
    },

    clearResults() {
      this.result = null;
      this.error = null;
    },

    exportResults() {
      if (!this.result || !this.result.actions || this.result.actions.length === 0) {
        return;
      }

      // Generate CSV
      const headers = [
        'Action',
        'Security',
        'Ticker',
        'Shares',
        'Trade Value',
        'Current Price',
        'Target Weight',
        'Priority',
        'Rationale',
      ];

      if (this.optimiseForCGT) {
        headers.push('Capital Gains Tax Cost Basis', 'Capital Gains Tax Gain/Loss', 'Capital Gains Tax Liability');
      }

      const rows = this.result.actions.map(action => {
        const row = [
          action.action_type.toUpperCase(),
          action.security_name,
          action.ticker || '',
          action.shares_to_trade.toFixed(6),
          action.trade_value.toFixed(2),
          action.current_price.toFixed(2),
          (action.target_weight * 100).toFixed(2) + '%',
          action.priority,
          action.rationale || '',
        ];

        if (this.optimiseForCGT) {
          row.push(
            (action.cgt_cost_basis || 0).toFixed(2),
            (action.cgt_gain_or_loss || 0).toFixed(2),
            (action.cgt_liability || 0).toFixed(2)
          );
        }

        return row;
      });

      const csv = [
        headers.join(','),
        ...rows.map(row => row.map(cell => `"${cell}"`).join(',')),
      ].join('\n');

      // Download CSV
      const blob = new Blob([csv], { type: 'text/csv' });
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = `rebalancing-actions-${new Date().toISOString().split('T')[0]}.csv`;
      link.click();
      window.URL.revokeObjectURL(url);
    },
  },
};
</script>

<style scoped>
</style>
