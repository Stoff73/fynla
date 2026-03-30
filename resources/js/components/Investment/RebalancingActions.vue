<template>
  <div class="rebalancing-actions">
    <div class="bg-white rounded-lg shadow">
      <!-- Header -->
      <div class="px-6 py-4 border-b border-light-gray">
        <div class="flex items-center justify-between">
          <h3 class="text-lg font-semibold text-horizon-500">
            Rebalancing Actions
          </h3>
          <div v-if="actions.length > 0" class="flex items-center space-x-2">
            <span class="text-sm text-neutral-500">
              {{ actions.length }} action{{ actions.length !== 1 ? 's' : '' }}
            </span>
            <button
              v-if="!hideControls && !readonly"
              @click="$emit('save-actions')"
              class="px-3 py-1.5 text-sm font-medium text-white bg-raspberry-500 rounded-button hover:bg-raspberry-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-violet-500"
            >
              Save Actions
            </button>
          </div>
        </div>

        <!-- Summary -->
        <div v-if="summary" class="mt-3 text-sm text-neutral-500">
          {{ summary }}
        </div>
      </div>

      <!-- Empty State -->
      <div
        v-if="actions.length === 0"
        class="px-6 py-12 text-center"
      >
        <svg
          class="mx-auto h-12 w-12 text-horizon-400"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
          />
        </svg>
        <h3 class="mt-2 text-sm font-medium text-horizon-500">No rebalancing needed</h3>
        <p class="mt-1 text-sm text-neutral-500">
          Your portfolio is already well-balanced.
        </p>
      </div>

      <!-- Actions List -->
      <div v-else class="divide-y divide-light-gray">
        <div
          v-for="(action, index) in sortedActions"
          :key="index"
          class="px-6 py-4 hover:bg-eggshell-500 transition-colors"
        >
          <div class="flex items-start justify-between">
            <!-- Action Details -->
            <div class="flex-1">
              <div class="flex items-center space-x-3">
                <!-- Action Type Badge -->
                <span
                  :class="[
                    'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                    action.action_type === 'buy'
                      ? 'bg-spring-500 text-white'
                      : 'bg-raspberry-500 text-white'
                  ]"
                >
                  {{ action.action_type === 'buy' ? 'BUY' : 'SELL' }}
                </span>

                <!-- Priority Badge -->
                <span
                  :class="[
                    'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium',
                    getPriorityClass(action.priority)
                  ]"
                >
                  Priority {{ action.priority }}
                </span>

                <!-- Security Name -->
                <span class="text-sm font-semibold text-horizon-500">
                  {{ action.security_name }}
                </span>

                <span v-if="action.ticker" class="text-sm text-neutral-500">
                  ({{ action.ticker }})
                </span>
              </div>

              <!-- Trade Details -->
              <div class="mt-2 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div>
                  <p class="text-neutral-500">Shares</p>
                  <p class="font-medium text-horizon-500">
                    {{ formatShares(action.shares_to_trade) }}
                  </p>
                </div>
                <div>
                  <p class="text-neutral-500">Trade Value</p>
                  <p class="font-medium text-horizon-500">
                    £{{ formatCurrency(action.trade_value) }}
                  </p>
                </div>
                <div>
                  <p class="text-neutral-500">Current Price</p>
                  <p class="font-medium text-horizon-500">
                    £{{ action.current_price.toFixed(2) }}
                  </p>
                </div>
                <div>
                  <p class="text-neutral-500">Target Weight</p>
                  <p class="font-medium text-horizon-500">
                    {{ (action.target_weight * 100).toFixed(1) }}%
                  </p>
                </div>
              </div>

              <!-- CGT Information (if available) -->
              <div
                v-if="showCGT && action.cgt_gain_or_loss !== undefined"
                class="mt-3 p-3 bg-eggshell-500 rounded-md"
              >
                <div class="grid grid-cols-3 gap-4 text-sm">
                  <div>
                    <p class="text-neutral-500">Cost Basis</p>
                    <p class="font-medium text-horizon-500">
                      £{{ formatCurrency(action.cgt_cost_basis || 0) }}
                    </p>
                  </div>
                  <div>
                    <p class="text-neutral-500">Gain/Loss</p>
                    <p
                      :class="[
                        'font-medium',
                        action.cgt_gain_or_loss >= 0 ? 'text-spring-600' : 'text-raspberry-600'
                      ]"
                    >
                      {{ action.cgt_gain_or_loss >= 0 ? '+' : '' }}£{{ formatCurrency(Math.abs(action.cgt_gain_or_loss)) }}
                    </p>
                  </div>
                  <div>
                    <p class="text-neutral-500">Capital Gains Tax Liability</p>
                    <p class="font-medium text-horizon-500">
                      £{{ formatCurrency(action.cgt_liability || 0) }}
                    </p>
                  </div>
                </div>
              </div>

              <!-- Rationale -->
              <div v-if="action.rationale" class="mt-2">
                <p class="text-sm text-neutral-500 italic">
                  {{ action.rationale }}
                </p>
              </div>
            </div>

            <!-- Action Controls -->
            <div v-if="!hideControls && !readonly" class="ml-4 flex-shrink-0">
              <button
                @click="$emit('remove-action', index)"
                class="text-horizon-400 hover:text-raspberry-600 focus:outline-none"
                title="Remove action"
              >
                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                  <path
                    fill-rule="evenodd"
                    d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                    clip-rule="evenodd"
                  />
                </svg>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- CGT Summary (if available) -->
      <div
        v-if="showCGT && cgtAnalysis"
        class="px-6 py-4 bg-white border-l-4 border-violet-500"
      >
        <h4 class="text-sm font-semibold text-violet-900 mb-3">
          Capital Gains Tax Analysis
        </h4>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div>
            <p class="text-xs text-violet-600 uppercase tracking-wide">Total Gains</p>
            <p class="text-lg font-semibold text-violet-900 mt-1">
              £{{ formatCurrency(cgtAnalysis.total_gains) }}
            </p>
          </div>
          <div v-if="cgtAnalysis.total_losses > 0">
            <p class="text-xs text-violet-600 uppercase tracking-wide">Total Losses</p>
            <p class="text-lg font-semibold text-violet-900 mt-1">
              £{{ formatCurrency(cgtAnalysis.total_losses) }}
            </p>
          </div>
          <div>
            <p class="text-xs text-violet-600 uppercase tracking-wide">Net Gains</p>
            <p class="text-lg font-semibold text-violet-900 mt-1">
              £{{ formatCurrency(cgtAnalysis.net_gains) }}
            </p>
          </div>
          <div>
            <p class="text-xs text-violet-600 uppercase tracking-wide">Allowance Used</p>
            <p class="text-lg font-semibold text-violet-900 mt-1">
              £{{ formatCurrency(cgtAnalysis.allowance_used) }}
            </p>
          </div>
          <div>
            <p class="text-xs text-violet-600 uppercase tracking-wide">Allowance Remaining</p>
            <p class="text-lg font-semibold text-violet-900 mt-1">
              £{{ formatCurrency(cgtAnalysis.allowance_remaining) }}
            </p>
          </div>
          <div>
            <p class="text-xs text-violet-600 uppercase tracking-wide">Taxable Gains</p>
            <p class="text-lg font-semibold text-violet-900 mt-1">
              £{{ formatCurrency(cgtAnalysis.taxable_gains) }}
            </p>
          </div>
          <div>
            <p class="text-xs text-violet-600 uppercase tracking-wide">Capital Gains Tax Liability</p>
            <p
              :class="[
                'text-lg font-semibold mt-1',
                cgtAnalysis.cgt_liability > 0 ? 'text-raspberry-600' : 'text-spring-600'
              ]"
            >
              £{{ formatCurrency(cgtAnalysis.cgt_liability) }}
            </p>
          </div>
          <div v-if="cgtAnalysis.effective_tax_rate !== undefined">
            <p class="text-xs text-violet-600 uppercase tracking-wide">Effective Rate</p>
            <p class="text-lg font-semibold text-violet-900 mt-1">
              {{ cgtAnalysis.effective_tax_rate.toFixed(2) }}%
            </p>
          </div>
        </div>

        <!-- CGT Summary Text -->
        <div v-if="cgtSummary" class="mt-4 text-sm text-violet-800">
          {{ cgtSummary }}
        </div>
      </div>

      <!-- Tax Loss Harvesting Opportunities -->
      <div
        v-if="showTaxLossOpportunities && taxLossOpportunities && taxLossOpportunities.opportunities.length > 0"
        class="px-6 py-4 bg-white border-l-4 border-violet-500"
      >
        <h4 class="text-sm font-semibold text-violet-900 mb-2">
          Tax-Loss Harvesting Opportunities
        </h4>
        <p class="text-sm text-violet-800 mb-3">
          {{ taxLossOpportunities.message }}
        </p>
        <div class="text-xs text-violet-700">
          Potential tax saving: £{{ formatCurrency(taxLossOpportunities.potential_tax_saving) }}
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'RebalancingActions',
  mixins: [currencyMixin],

  props: {
    actions: {
      type: Array,
      required: true,
    },
    summary: {
      type: String,
      default: null,
    },
    cgtAnalysis: {
      type: Object,
      default: null,
    },
    cgtSummary: {
      type: String,
      default: null,
    },
    taxLossOpportunities: {
      type: Object,
      default: null,
    },
    showCGT: {
      type: Boolean,
      default: true,
    },
    showTaxLossOpportunities: {
      type: Boolean,
      default: true,
    },
    hideControls: {
      type: Boolean,
      default: false,
    },
    readonly: {
      type: Boolean,
      default: false,
    },
  },

  emits: ['remove-action', 'save-actions'],

  computed: {
    sortedActions() {
      // Actions are already sorted by the backend (sells first, then buys)
      return this.actions;
    },
  },

  methods: {
    formatShares(value) {
      return Number(value || 0).toLocaleString('en-GB', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 6,
      });
    },

    getPriorityClass(priority) {
      const classes = {
        1: 'bg-raspberry-500 text-white',
        2: 'bg-violet-500 text-white',
        3: 'bg-violet-500 text-white',
        4: 'bg-violet-500 text-white',
        5: 'bg-savannah-100 text-horizon-500',
      };
      return classes[priority] || classes[5];
    },
  },
};
</script>

<style scoped>
/* Smooth transitions */
.transition-colors {
  transition-property: background-color, border-color, color, fill, stroke;
  transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
  transition-duration: 150ms;
}
</style>
