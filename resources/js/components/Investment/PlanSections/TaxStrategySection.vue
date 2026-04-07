<template>
  <div class="tax-strategy-section">
    <h4 class="text-md font-semibold text-horizon-500 mb-4">Tax Optimisation Strategy</h4>

    <div v-if="!data" class="text-center py-8 text-neutral-500">
      <p>No tax strategy data available</p>
    </div>

    <div v-else class="space-y-6">
      <!-- Tax Efficiency Score -->
      <div class="bg-eggshell-500 rounded-lg p-6">
        <div class="flex justify-between items-center">
          <div>
            <h5 class="text-sm font-semibold text-neutral-500 mb-2">Tax Efficiency Score</h5>
            <p class="text-4xl font-bold" :class="getEfficiencyColour(data.efficiency_score)">
              {{ formatPercentage(data.efficiency_score || 0) }}%
            </p>
            <p class="text-sm text-neutral-500 mt-2">{{ getEfficiencyLabel(data.efficiency_score) }}</p>
          </div>
          <div class="text-right">
            <p class="text-sm text-neutral-500 mb-1">Potential Annual Tax Savings</p>
            <p class="text-2xl font-bold text-spring-600">£{{ formatNumber(data.potential_annual_saving || 0) }}</p>
            <p class="text-xs text-neutral-500 mt-1">Through optimization</p>
          </div>
        </div>
      </div>

      <!-- ISA Allowance Status -->
      <div class="bg-white border border-light-gray rounded-lg p-5">
        <h5 class="text-sm font-semibold text-neutral-500 mb-4">ISA Allowance ({{ currentTaxYear }})</h5>
        <div class="mb-4">
          <div class="flex justify-between text-sm mb-2">
            <span class="text-neutral-500">Allowance Usage</span>
            <span class="font-medium text-horizon-500">
              £{{ formatNumber(data.isa_used || 0) }} / £20,000
            </span>
          </div>
          <div class="w-full bg-savannah-200 rounded-full h-3 overflow-hidden">
            <div
              class="h-3 rounded-full transition-all duration-500"
              :class="getIsaBarColour(data.isa_utilization)"
              :style="{ width: Math.min(data.isa_utilization || 0, 100) + '%' }"
            ></div>
          </div>
          <div class="flex justify-between items-center mt-2">
            <span class="text-xs text-neutral-500">
              {{ formatPercentage(data.isa_utilization || 0) }}% utilized
            </span>
            <span class="text-xs font-medium text-spring-600">
              £{{ formatNumber(data.isa_remaining || 0) }} remaining
            </span>
          </div>
        </div>
      </div>

      <!-- Tax-Efficient Opportunities -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-eggshell-500 rounded-lg p-4">
          <div class="flex items-center justify-between mb-2">
            <h6 class="text-sm font-semibold text-neutral-500">Capital Gains Tax Harvesting</h6>
            <svg class="w-5 h-5 text-violet-600" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
            </svg>
          </div>
          <p class="text-2xl font-bold text-horizon-500">£{{ formatNumber(data.harvestable_losses || 0) }}</p>
          <p class="text-xs text-neutral-500 mt-1">Available losses</p>
        </div>

        <div class="bg-eggshell-500 rounded-lg p-4">
          <div class="flex items-center justify-between mb-2">
            <h6 class="text-sm font-semibold text-neutral-500">Bed & ISA</h6>
            <svg class="w-5 h-5 text-violet-600" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M7.707 3.293a1 1 0 010 1.414L5.414 7H11a7 7 0 017 7v2a1 1 0 11-2 0v-2a5 5 0 00-5-5H5.414l2.293 2.293a1 1 0 11-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
            </svg>
          </div>
          <p class="text-2xl font-bold text-horizon-500">{{ data.bed_and_isa_opportunities || 0 }}</p>
          <p class="text-xs text-neutral-500 mt-1">Transfer opportunities</p>
        </div>

        <div class="bg-eggshell-500 rounded-lg p-4">
          <div class="flex items-center justify-between mb-2">
            <h6 class="text-sm font-semibold text-neutral-500">Asset Location</h6>
            <svg class="w-5 h-5 text-violet-600" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M6 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V7.414A2 2 0 0015.414 6L12 2.586A2 2 0 0010.586 2H6zm5 6a1 1 0 10-2 0v3.586l-1.293-1.293a1 1 0 10-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 11.586V8z" clip-rule="evenodd" />
            </svg>
          </div>
          <p class="text-2xl font-bold text-horizon-500">{{ formatPercentage(data.location_efficiency || 0) }}%</p>
          <p class="text-xs text-neutral-500 mt-1">Optimisation score</p>
        </div>
      </div>

      <!-- Key Recommendations -->
      <div v-if="data.recommendations && data.recommendations.length > 0" class="bg-white border border-light-gray rounded-lg p-5">
        <h5 class="text-sm font-semibold text-neutral-500 mb-4">Tax Optimisation Actions</h5>
        <div class="space-y-3">
          <div
            v-for="(rec, index) in data.recommendations.slice(0, 5)"
            :key="index"
            class="p-4 rounded-md"
            :class="getPriorityBgClass(rec.priority)"
          >
            <div class="flex items-start">
              <span
                class="flex-shrink-0 inline-flex items-center justify-center h-6 w-6 rounded-full text-xs font-bold text-white mr-3"
                :class="getPriorityClass(rec.priority)"
              >
                {{ index + 1 }}
              </span>
              <div class="flex-1">
                <p class="text-sm font-medium text-horizon-500 mb-1">{{ rec.action }}</p>
                <p class="text-sm text-neutral-500">{{ rec.reason }}</p>
                <div v-if="rec.potential_saving" class="mt-2">
                  <span class="text-xs font-semibold text-spring-600">
                    Potential saving: £{{ formatNumber(rec.potential_saving) }}/year
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Long-term Projections -->
      <div class="bg-white border border-light-gray rounded-lg p-5">
        <h5 class="text-sm font-semibold text-neutral-500 mb-4">Projected Tax Savings</h5>
        <div class="grid grid-cols-3 gap-6">
          <div class="text-center">
            <p class="text-sm text-neutral-500 mb-1">1 Year</p>
            <p class="text-2xl font-bold text-spring-600">£{{ formatNumber(data.savings_1_year || 0) }}</p>
          </div>
          <div class="text-center">
            <p class="text-sm text-neutral-500 mb-1">5 Years</p>
            <p class="text-2xl font-bold text-spring-600">£{{ formatNumber(data.savings_5_year || 0) }}</p>
          </div>
          <div class="text-center">
            <p class="text-sm text-neutral-500 mb-1">10 Years</p>
            <p class="text-2xl font-bold text-spring-600">£{{ formatNumber(data.savings_10_year || 0) }}</p>
          </div>
        </div>
        <div class="mt-4 pt-4 border-t border-light-gray">
          <p class="text-xs text-neutral-500 text-center">
            Based on current portfolio and tax rates
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { getCurrentTaxYear } from '@/utils/dateFormatter';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'TaxStrategySection',

  mixins: [currencyMixin],

  props: {
    data: {
      type: Object,
      default: null,
    },
  },

  computed: {
    currentTaxYear() {
      return getCurrentTaxYear();
    },
  },

  methods: {
    formatPercentage(value) {
      if (value === null || value === undefined) return '0.0';
      return value.toFixed(1);
    },

    getEfficiencyColour(score) {
      if (score >= 80) return 'text-spring-600';
      if (score >= 60) return 'text-violet-600';
      if (score >= 40) return 'text-violet-600';
      return 'text-raspberry-600';
    },

    getEfficiencyLabel(score) {
      if (score >= 80) return 'Highly efficient';
      if (score >= 60) return 'Good efficiency';
      if (score >= 40) return 'Room for improvement';
      return 'Needs optimisation';
    },

    getIsaBarColour(utilization) {
      if (utilization > 100) return 'bg-raspberry-600';
      if (utilization > 80) return 'bg-raspberry-500';
      return 'bg-raspberry-500';
    },

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
        high: 'bg-eggshell-500',
        medium: 'bg-eggshell-500',
        low: 'bg-eggshell-500',
      };
      return classes[priority] || 'bg-eggshell-500 border border-light-gray';
    },
  },
};
</script>

<style scoped>
/* Component-specific styles */
</style>
