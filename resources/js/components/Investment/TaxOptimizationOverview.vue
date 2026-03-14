<template>
  <div class="tax-optimization-overview">
    <h3 class="text-lg font-semibold text-horizon-500 mb-4">Tax Optimisation Overview</h3>

    <!-- No Data State -->
    <div v-if="!analysis" class="text-center py-12 text-neutral-500">
      <p>No tax optimisation analysis available</p>
    </div>

    <!-- Opportunities Summary -->
    <div v-else>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <!-- ISA Opportunity -->
        <div class="bg-white rounded-lg p-4 border-l-4 border-violet-500">
          <div class="flex items-center justify-between mb-2">
            <h4 class="text-sm font-medium text-neutral-500">ISA Allowance</h4>
            <svg class="w-5 h-5 text-violet-600" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd" />
            </svg>
          </div>
          <p class="text-2xl font-bold text-horizon-500 mb-1">
            £{{ formatNumber(analysis.opportunities?.isa_remaining_allowance || 0) }}
          </p>
          <p class="text-xs text-neutral-500">Remaining allowance</p>
        </div>

        <!-- CGT Harvesting -->
        <div class="bg-white rounded-lg p-4 border-l-4 border-violet-500">
          <div class="flex items-center justify-between mb-2">
            <h4 class="text-sm font-medium text-neutral-500">Capital Gains Tax Harvesting</h4>
            <svg class="w-5 h-5 text-violet-600" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
            </svg>
          </div>
          <p class="text-2xl font-bold text-horizon-500 mb-1">
            £{{ formatNumber(analysis.opportunities?.harvestable_losses || 0) }}
          </p>
          <p class="text-xs text-neutral-500">Available losses</p>
        </div>

        <!-- Bed & ISA -->
        <div class="bg-white rounded-lg p-4 border-l-4 border-spring-500">
          <div class="flex items-center justify-between mb-2">
            <h4 class="text-sm font-medium text-neutral-500">Bed & ISA</h4>
            <svg class="w-5 h-5 text-spring-600" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M7.707 3.293a1 1 0 010 1.414L5.414 7H11a7 7 0 017 7v2a1 1 0 11-2 0v-2a5 5 0 00-5-5H5.414l2.293 2.293a1 1 0 11-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
            </svg>
          </div>
          <p class="text-2xl font-bold text-horizon-500 mb-1">
            {{ analysis.opportunities?.bed_and_isa_opportunities || 0 }}
          </p>
          <p class="text-xs text-neutral-500">Transfer opportunities</p>
        </div>

        <!-- Dividend Optimization -->
        <div class="bg-white rounded-lg p-4 border-l-4 border-violet-500">
          <div class="flex items-center justify-between mb-2">
            <h4 class="text-sm font-medium text-neutral-500">Dividend Tax</h4>
            <svg class="w-5 h-5 text-violet-600" fill="currentColor" viewBox="0 0 20 20">
              <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z" />
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd" />
            </svg>
          </div>
          <p class="text-2xl font-bold text-horizon-500 mb-1">
            £{{ formatNumber(analysis.opportunities?.excess_dividend_income || 0) }}
          </p>
          <p class="text-xs text-neutral-500">Above allowance</p>
        </div>
      </div>

      <!-- Recommendations Summary -->
      <div class="bg-white border border-light-gray rounded-lg p-6 mb-6">
        <h4 class="text-md font-semibold text-horizon-500 mb-4">Top Recommendations</h4>
        <div v-if="analysis.recommendations && analysis.recommendations.length > 0" class="space-y-3">
          <div
            v-for="(rec, index) in topRecommendations"
            :key="index"
            class="flex items-start p-3 rounded-md"
            :class="getPriorityBgClass(rec.priority)"
          >
            <div class="flex-shrink-0 mr-3">
              <span
                class="inline-flex items-center justify-center h-6 w-6 rounded-full text-xs font-bold text-white"
                :class="getPriorityClass(rec.priority)"
              >
                {{ index + 1 }}
              </span>
            </div>
            <div class="flex-1">
              <p class="text-sm font-medium text-horizon-500 mb-1">{{ rec.action }}</p>
              <p class="text-xs text-neutral-500">{{ rec.reason }}</p>
              <div v-if="rec.potential_saving" class="mt-2">
                <span class="text-xs font-semibold text-spring-600">
                  Potential saving: £{{ formatNumber(rec.potential_saving) }}/year
                </span>
              </div>
            </div>
          </div>
        </div>
        <div v-else class="text-center py-6 text-neutral-500">
          <p>No recommendations available</p>
        </div>
      </div>

      <!-- Tax Efficiency Breakdown -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Efficiency Components -->
        <div class="bg-white border border-light-gray rounded-lg p-6">
          <h4 class="text-md font-semibold text-horizon-500 mb-4">Efficiency Breakdown</h4>
          <div class="space-y-3">
            <div>
              <div class="flex justify-between text-sm mb-1">
                <span class="text-neutral-500">ISA Utilization</span>
                <span class="font-medium">{{ getComponentScore('isa') }}%</span>
              </div>
              <div class="w-full bg-savannah-200 rounded-full h-2">
                <div
                  class="bg-raspberry-500 h-2 rounded-full"
                  :style="{ width: getComponentScore('isa') + '%' }"
                ></div>
              </div>
            </div>
            <div>
              <div class="flex justify-between text-sm mb-1">
                <span class="text-neutral-500">Loss Management</span>
                <span class="font-medium">{{ getComponentScore('cgt') }}%</span>
              </div>
              <div class="w-full bg-savannah-200 rounded-full h-2">
                <div
                  class="bg-raspberry-500 h-2 rounded-full"
                  :style="{ width: getComponentScore('cgt') + '%' }"
                ></div>
              </div>
            </div>
            <div>
              <div class="flex justify-between text-sm mb-1">
                <span class="text-neutral-500">Dividend Efficiency</span>
                <span class="font-medium">{{ getComponentScore('dividend') }}%</span>
              </div>
              <div class="w-full bg-savannah-200 rounded-full h-2">
                <div
                  class="bg-violet-600 h-2 rounded-full"
                  :style="{ width: getComponentScore('dividend') + '%' }"
                ></div>
              </div>
            </div>
            <div>
              <div class="flex justify-between text-sm mb-1">
                <span class="text-neutral-500">Asset Location</span>
                <span class="font-medium">{{ getComponentScore('location') }}%</span>
              </div>
              <div class="w-full bg-savannah-200 rounded-full h-2">
                <div
                  class="bg-spring-600 h-2 rounded-full"
                  :style="{ width: getComponentScore('location') + '%' }"
                ></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Summary Stats -->
        <div class="bg-white border border-light-gray rounded-lg p-6">
          <h4 class="text-md font-semibold text-horizon-500 mb-4">Summary</h4>
          <div class="space-y-4">
            <div class="flex justify-between items-center">
              <span class="text-sm text-neutral-500">Current Tax Year</span>
              <span class="text-sm font-semibold text-horizon-500">{{ analysis.current_position?.tax_year }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-sm text-neutral-500">Total Portfolio (General Investment Account)</span>
              <span class="text-sm font-semibold text-horizon-500">
                £{{ formatNumber(analysis.current_position?.gia_total_value || 0) }}
              </span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-sm text-neutral-500">Total ISA Holdings</span>
              <span class="text-sm font-semibold text-horizon-500">
                £{{ formatNumber(analysis.current_position?.isa_total_value || 0) }}
              </span>
            </div>
            <div class="flex justify-between items-center pt-4 border-t border-light-gray">
              <span class="text-sm font-medium text-neutral-500">Potential Annual Savings</span>
              <span class="text-lg font-bold text-spring-600">
                £{{ formatNumber(analysis.potential_savings?.annual || 0) }}
              </span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-sm text-neutral-500">5-Year Projection</span>
              <span class="text-sm font-semibold text-spring-600">
                £{{ formatNumber(analysis.potential_savings?.five_year || 0) }}
              </span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-sm text-neutral-500">10-Year Projection</span>
              <span class="text-sm font-semibold text-spring-600">
                £{{ formatNumber(analysis.potential_savings?.ten_year || 0) }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Action Button -->
      <div class="mt-6 flex justify-end">
        <button
          @click="$emit('refresh')"
          class="px-4 py-2 bg-raspberry-500 text-white text-sm font-medium rounded-button hover:bg-raspberry-600 transition-colors duration-200"
        >
          Refresh Analysis
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'TaxOptimizationOverview',

  mixins: [currencyMixin],

  emits: ['refresh'],

  props: {
    analysis: {
      type: Object,
      default: null,
    },
  },

  computed: {
    topRecommendations() {
      if (!this.analysis?.recommendations) return [];
      return this.analysis.recommendations.slice(0, 5);
    },
  },

  methods: {
    getPriorityClass(priority) {
      const classes = {
        high: 'bg-raspberry-600',
        medium: 'bg-raspberry-500',
        low: 'bg-raspberry-500',
      };
      return classes[priority] || 'bg-horizon-400';
    },

    getPriorityBgClass(priority) {
      const classes = {
        high: 'bg-white border-l-4 border-raspberry-500',
        medium: 'bg-white border-l-4 border-violet-500',
        low: 'bg-white border-l-4 border-violet-500',
      };
      return classes[priority] || 'bg-white border-l-4 border-horizon-400';
    },

    getComponentScore(component) {
      // Simplified - in reality would calculate from analysis data
      const score = this.analysis?.efficiency_score?.score || 0;
      // Return component-specific score or overall score
      return Math.round(score);
    },
  },
};
</script>

<style scoped>
/* Add any scoped styles here if needed */
</style>
