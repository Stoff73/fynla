<template>
  <div class="bg-white rounded-lg border border-light-gray p-6">
    <h3 class="text-lg font-semibold text-horizon-500 mb-4">
      Inheritance Tax Mitigation Strategies
      <span class="text-sm font-normal text-neutral-500">(Prioritized by Effectiveness)</span>
    </h3>

    <!-- No Inheritance Tax liability message -->
    <div v-if="ihtLiability === 0" class="bg-spring-50 border border-spring-200 p-4">
      <p class="text-sm text-spring-700">
        ✓ No Inheritance Tax liability projected - no mitigation strategies needed
      </p>
    </div>

    <!-- Strategies accordion -->
    <div v-else class="space-y-3">
      <div
        v-for="(strategy, index) in enhancedStrategies"
        :key="index"
        class="border rounded-lg overflow-hidden"
        :class="getStrategyBorderClass(strategy.priority)"
      >
        <!-- Strategy Header (clickable) -->
        <div
          class="p-4 cursor-pointer hover:bg-eggshell-500 transition"
          @click="toggleStrategy(index)"
        >
          <div class="flex items-start justify-between">
            <div class="flex-1">
              <div class="flex items-center space-x-2 mb-1">
                <!-- Priority Badge -->
                <span
                  class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                  :class="getPriorityBadgeClass(strategy.priority)"
                >
                  Priority {{ strategy.priority }}
                </span>

                <!-- Effectiveness Badge -->
                <span
                  v-if="strategy.effectiveness"
                  class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-savannah-100 text-horizon-500"
                >
                  {{ strategy.effectiveness }} Effectiveness
                </span>
              </div>

              <h4 class="text-base font-semibold text-horizon-500">
                {{ strategy.strategy_name }}
              </h4>

              <p class="text-sm text-neutral-500 mt-1">
                {{ strategy.description }}
              </p>

              <div class="mt-2 flex items-center space-x-4 text-sm">
                <div v-if="strategy.iht_saved" class="text-spring-600 font-medium">
                  Inheritance Tax Saved: {{ formatCurrency(strategy.iht_saved) }}
                </div>
                <div v-if="strategy.total_gifted" class="text-violet-600 font-medium">
                  Total Gifted: {{ formatCurrency(strategy.total_gifted) }}
                </div>
                <div v-if="strategy.reduction_percentage" class="text-violet-500 font-medium">
                  {{ strategy.reduction_percentage }}% Reduction
                </div>
                <div v-if="strategy.implementation_complexity" class="text-neutral-500">
                  Complexity: {{ strategy.implementation_complexity }}
                </div>
              </div>
            </div>

            <!-- Expand icon -->
            <svg
              class="h-5 w-5 text-horizon-400 transition-transform ml-4 flex-shrink-0"
              :class="{ 'transform rotate-180': expandedStrategies[index] }"
              xmlns="http://www.w3.org/2000/svg"
              viewBox="0 0 20 20"
              fill="currentColor"
            >
              <path
                fill-rule="evenodd"
                d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                clip-rule="evenodd"
              />
            </svg>
          </div>
        </div>

        <!-- Strategy Details (expandable) -->
        <div v-show="expandedStrategies[index]" class="px-4 pb-4 bg-eggshell-500">
          <!-- Gifting Strategy - Special Layout with Link -->
          <div v-if="strategy.strategy_name === 'Gifting Strategy'" class="space-y-3">
            <div class="bg-violet-50 border border-violet-200 rounded-lg p-4 mb-4">
              <div class="flex justify-between items-start mb-3">
                <div>
                  <h5 class="text-sm font-semibold text-violet-900 mb-1">Gifting Strategy Summary</h5>
                  <p class="text-xs text-violet-700">Strategic lifetime gifts to reduce Inheritance Tax liability</p>
                </div>
                <button
                  @click="$emit('navigate-to-gifting')"
                  class="inline-flex items-center px-3 py-1.5 border border-violet-400 rounded-md text-xs font-medium text-violet-800 bg-white hover:bg-violet-50 transition shadow-sm"
                >
                  <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                  </svg>
                  View Full Timeline
                </button>
              </div>

              <!-- Strategy breakdown -->
              <div v-if="strategy.specific_actions && strategy.specific_actions.length > 0" class="space-y-2">
                <div
                  v-for="(action, actionIndex) in strategy.specific_actions"
                  :key="actionIndex"
                  class="text-sm text-violet-900 bg-white rounded px-3 py-2 border border-violet-100"
                >
                  ✓ {{ action }}
                </div>
              </div>
            </div>
          </div>

          <!-- Will Strategy - Show Implementation Notice -->
          <div v-if="strategy.strategy_name && strategy.strategy_name.toLowerCase().includes('will')" class="mt-3">
            <div class="bg-violet-50 border border-violet-200 rounded-lg p-3">
              <p class="text-sm text-violet-800">
                <span class="font-medium">⚠️ Note:</span> Full will functionality has not been implemented.
              </p>
            </div>
          </div>

          <!-- Other Strategies - Standard List -->
          <div v-else-if="strategy.specific_actions && Array.isArray(strategy.specific_actions)" class="mt-3">
            <h5 class="text-sm font-medium text-horizon-500 mb-2">Implementation Steps:</h5>
            <ul class="space-y-2">
              <li
                v-for="(step, stepIndex) in strategy.specific_actions"
                :key="stepIndex"
                class="text-sm text-neutral-500 flex items-start"
              >
                <span class="text-violet-600 mr-2 mt-1">→</span>
                <span>{{ step }}</span>
              </li>
            </ul>
          </div>

          <!-- Cover needed (for life insurance strategy) -->
          <div v-if="strategy.cover_needed" class="mt-3 bg-violet-50 rounded p-3">
            <div class="grid grid-cols-2 gap-3 text-sm">
              <div>
                <p class="text-violet-600 font-medium">Cover Required</p>
                <p class="text-lg font-bold text-violet-900">{{ formatCurrency(strategy.cover_needed) }}</p>
              </div>
              <div>
                <p class="text-violet-600 font-medium">Annual Premium</p>
                <p class="text-lg font-bold text-violet-900">{{ formatCurrency(strategy.estimated_annual_premium) }}</p>
              </div>
            </div>
          </div>

          <!-- Charitable giving details -->
          <div v-if="strategy.charitable_amount_required" class="mt-3 bg-violet-50 rounded p-3">
            <p class="text-sm text-violet-500">
              <strong>Required charitable bequest:</strong> {{ formatCurrency(strategy.charitable_amount_required) }} (10% of estate)
            </p>
            <p class="text-xs text-violet-500 mt-1">
              This reduces Inheritance Tax rate from 40% to 36%, saving {{ formatCurrency(strategy.iht_saved) }}
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Total potential savings -->
    <div v-if="ihtLiability > 0 && enhancedStrategies.length > 0" class="mt-6 pt-6 border-t border-light-gray">
      <div class="bg-spring-50 rounded-lg p-4">
        <div class="flex justify-between items-center">
          <div>
            <p class="text-sm text-spring-600 font-medium">Total Potential Inheritance Tax Savings</p>
            <p class="text-xs text-spring-500 mt-1">By implementing all recommended strategies</p>
          </div>
          <p class="text-2xl font-bold text-spring-900">
            {{ formatCurrency(totalSavings) }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'IHTMitigationStrategies',

  emits: ['navigate-to-gifting'],

  mixins: [currencyMixin],

  props: {
    strategies: {
      type: Array,
      required: true,
    },
    ihtLiability: {
      type: Number,
      required: true,
    },
    giftingStrategyData: {
      type: Object,
      default: null,
    },
  },

  data() {
    return {
      expandedStrategies: {},
    };
  },

  computed: {
    // Merge strategies with actual gifting data from Gifting tab
    enhancedStrategies() {

      return this.strategies.map(strategy => {
        // If this is the Gifting Strategy, override with actual data from Gifting tab
        if (strategy.strategy_name === 'Gifting Strategy' && this.giftingStrategyData) {

          const petStrategy = this.giftingStrategyData.gifting_strategy?.strategies?.find(
            s => s.strategy_name === 'Potentially Exempt Transfers (PETs)'
          );


          if (petStrategy) {
            // Get Annual Exemption totals
            const annualIHTSaved = this.giftingStrategyData.annual_exemption_plan?.total_iht_saved || 0;
            const annualTotalGifted = this.giftingStrategyData.annual_exemption_plan?.total_over_lifetime || 0;


            // Calculate combined totals (Annual + PET)
            const combinedIHTSaved = petStrategy.iht_saved + annualIHTSaved;
            const combinedTotalGifted = petStrategy.total_gifted + annualTotalGifted;


            const enhanced = {
              ...strategy,
              iht_saved: combinedIHTSaved,
              total_gifted: combinedTotalGifted,
              specific_actions: this.buildGiftingActions(petStrategy),
            };
            return enhanced;
          }
        }
        return strategy;
      });
    },

    totalSavings() {
      return this.enhancedStrategies.reduce((sum, strategy) => sum + (strategy.iht_saved || 0), 0);
    },
  },

  methods: {
    buildGiftingActions(petStrategy) {
      if (!petStrategy) return [];

      const actions = [];

      // Add number of cycles info
      if (petStrategy.number_of_cycles) {
        actions.push(`Gift ${this.formatCurrency(petStrategy.amount_per_cycle)} every 7 years for ${petStrategy.number_of_cycles} cycle(s)`);
      }

      // Add total gifted
      if (petStrategy.total_gifted) {
        actions.push(`Total lifetime gifts: ${this.formatCurrency(petStrategy.total_gifted)}`);
      }

      // Add IHT saved
      if (petStrategy.iht_saved) {
        actions.push(`Inheritance Tax saved: ${this.formatCurrency(petStrategy.iht_saved)}`);
      }

      return actions;
    },

    toggleStrategy(index) {
      // Vue 3 - no need for $set, direct assignment works with Proxy-based reactivity
      this.expandedStrategies[index] = !this.expandedStrategies[index];
    },

    getStrategyBorderClass(priority) {
      const classes = {
        1: 'border-spring-300',
        2: 'border-violet-300',
        3: 'border-violet-300',
        4: 'border-horizon-300',
      };
      return classes[priority] || 'border-horizon-300';
    },

    getPriorityBadgeClass(priority) {
      const classes = {
        1: 'bg-spring-100 text-spring-800',
        2: 'bg-violet-100 text-violet-800',
        3: 'bg-violet-100 text-violet-800',
        4: 'bg-savannah-100 text-horizon-500',
      };
      return classes[priority] || 'bg-savannah-100 text-horizon-500';
    },
  },
};
</script>
