<template>
  <div class="fee-analysis-section">
    <h4 class="text-md font-semibold text-horizon-500 mb-4">Fee Analysis</h4>

    <div v-if="!data" class="text-center py-8 text-neutral-500">
      <p>No fee analysis data available</p>
    </div>

    <div v-else class="space-y-6">
      <!-- Total Fee Summary -->
      <div class="bg-eggshell-500 rounded-lg p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <h5 class="text-sm font-semibold text-neutral-500 mb-2">Total Annual Fees</h5>
            <p class="text-4xl font-bold text-horizon-500">£{{ formatNumber(data.total_annual_fees || 0) }}</p>
            <p class="text-sm text-neutral-500 mt-2">{{ formatPercentage(data.total_fee_percentage || 0) }}% of portfolio</p>
            <div class="mt-3">
              <span class="px-3 py-1 text-xs font-semibold rounded-full" :class="getFeeStatusClass(data.total_fee_percentage)">
                {{ getFeeStatusLabel(data.total_fee_percentage) }}
              </span>
            </div>
          </div>
          <div>
            <h5 class="text-sm font-semibold text-neutral-500 mb-2">Fee Efficiency Score</h5>
            <p class="text-4xl font-bold" :class="getEfficiencyColour(data.efficiency_score)">
              {{ formatPercentage(data.efficiency_score || 0) }}%
            </p>
            <p class="text-sm text-neutral-500 mt-2">{{ getEfficiencyLabel(data.efficiency_score) }}</p>
          </div>
        </div>
      </div>

      <!-- Fee Breakdown -->
      <div class="bg-white border border-light-gray rounded-lg p-5">
        <h5 class="text-sm font-semibold text-neutral-500 mb-4">Fee Breakdown by Type</h5>
        <div v-if="data.fee_breakdown" class="space-y-3">
          <div v-for="(fee, type) in data.fee_breakdown" :key="type" class="flex justify-between items-center p-3 bg-eggshell-500 rounded-md">
            <div class="flex-1">
              <p class="text-sm font-medium text-horizon-500">{{ formatFeeType(type) }}</p>
              <p class="text-xs text-neutral-500 mt-1">{{ formatPercentage(fee.percentage) }}% annual</p>
            </div>
            <div class="text-right">
              <p class="text-lg font-semibold text-horizon-500">£{{ formatNumber(fee.amount) }}</p>
              <p class="text-xs text-neutral-500">/year</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Fee Impact Projections -->
      <div class="bg-white border border-light-gray rounded-lg p-5">
        <h5 class="text-sm font-semibold text-neutral-500 mb-4">Compound Fee Impact</h5>
        <p class="text-sm text-neutral-500 mb-4">
          How fees reduce your portfolio value over time
        </p>
        <div class="grid grid-cols-3 gap-6">
          <div class="text-center">
            <p class="text-sm text-neutral-500 mb-1">10 Years</p>
            <p class="text-2xl font-bold text-raspberry-600">-£{{ formatNumber(data.fee_impact_10_year || 0) }}</p>
            <p class="text-xs text-neutral-500 mt-1">Lost to fees</p>
          </div>
          <div class="text-center">
            <p class="text-sm text-neutral-500 mb-1">20 Years</p>
            <p class="text-2xl font-bold text-raspberry-600">-£{{ formatNumber(data.fee_impact_20_year || 0) }}</p>
            <p class="text-xs text-neutral-500 mt-1">Lost to fees</p>
          </div>
          <div class="text-center">
            <p class="text-sm text-neutral-500 mb-1">30 Years</p>
            <p class="text-2xl font-bold text-raspberry-600">-£{{ formatNumber(data.fee_impact_30_year || 0) }}</p>
            <p class="text-xs text-neutral-500 mt-1">Lost to fees</p>
          </div>
        </div>
        <div class="mt-4 pt-4 border-t border-light-gray">
          <p class="text-xs text-neutral-500 text-center">
            Based on current portfolio value and {{ formatPercentage(data.assumed_return || 7) }}% assumed annual return
          </p>
        </div>
      </div>

      <!-- Fee Reduction Opportunities -->
      <div v-if="data.opportunities && data.opportunities.length > 0" class="bg-white border border-light-gray rounded-lg p-5">
        <h5 class="text-sm font-semibold text-neutral-500 mb-4">Fee Reduction Opportunities</h5>
        <div class="space-y-4">
          <div
            v-for="(opp, index) in data.opportunities"
            :key="index"
            class="p-4 bg-eggshell-500 rounded-md"
          >
            <div class="flex justify-between items-start mb-2">
              <h6 class="text-sm font-semibold text-horizon-500">{{ opp.title }}</h6>
              <span class="px-2 py-1 text-xs font-semibold bg-spring-600 text-white rounded-full">
                Save £{{ formatNumber(opp.annual_saving) }}/yr
              </span>
            </div>
            <p class="text-sm text-neutral-500 mb-3">{{ opp.description }}</p>
            <div class="grid grid-cols-2 gap-4 text-sm">
              <div>
                <p class="text-xs text-neutral-500">Current Fee</p>
                <p class="font-medium text-horizon-500">{{ formatPercentage(opp.current_fee) }}%</p>
              </div>
              <div>
                <p class="text-xs text-neutral-500">Potential Fee</p>
                <p class="font-medium text-spring-600">{{ formatPercentage(opp.potential_fee) }}%</p>
              </div>
            </div>
            <div class="mt-3 pt-3 border-t border-spring-200">
              <p class="text-xs text-neutral-500">
                <strong>10-year impact:</strong> Save £{{ formatNumber(opp.ten_year_saving) }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- High-Fee Holdings -->
      <div v-if="data.high_fee_holdings && data.high_fee_holdings.length > 0" class="bg-white border border-light-gray rounded-lg p-5">
        <h5 class="text-sm font-semibold text-neutral-500 mb-4">High-Fee Holdings</h5>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-light-gray">
            <thead class="bg-eggshell-500">
              <tr>
                <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500 uppercase">Holding</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500 uppercase">Type</th>
                <th class="px-4 py-2 text-right text-xs font-medium text-neutral-500 uppercase">Value</th>
                <th class="px-4 py-2 text-right text-xs font-medium text-neutral-500 uppercase">Fee %</th>
                <th class="px-4 py-2 text-right text-xs font-medium text-neutral-500 uppercase">Annual Cost</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-light-gray">
              <tr v-for="(holding, index) in data.high_fee_holdings" :key="index">
                <td class="px-4 py-2 text-sm text-horizon-500">{{ holding.name }}</td>
                <td class="px-4 py-2 text-sm text-neutral-500">{{ holding.type }}</td>
                <td class="px-4 py-2 text-sm text-right font-medium text-horizon-500">
                  £{{ formatNumber(holding.value) }}
                </td>
                <td class="px-4 py-2 text-sm text-right font-medium text-raspberry-600">
                  {{ formatPercentage(holding.fee_percentage) }}%
                </td>
                <td class="px-4 py-2 text-sm text-right font-medium text-horizon-500">
                  £{{ formatNumber(holding.annual_fee) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="mt-3 p-3 bg-eggshell-500 rounded-md">
          <p class="text-sm text-violet-800">
            <strong>Note:</strong> Consider reviewing alternatives for holdings with fees above 1.0%
          </p>
        </div>
      </div>

      <!-- Fee Comparison -->
      <div class="bg-eggshell-500 rounded-lg p-5">
        <h5 class="text-sm font-semibold text-neutral-500 mb-4">Fee Comparison</h5>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <p class="text-xs text-neutral-500 mb-1">Your Fees</p>
            <p class="text-xl font-bold text-horizon-500">{{ formatPercentage(data.total_fee_percentage || 0) }}%</p>
          </div>
          <div>
            <p class="text-xs text-neutral-500 mb-1">Industry Average</p>
            <p class="text-xl font-bold text-horizon-500">{{ formatPercentage(data.industry_average || 1.5) }}%</p>
          </div>
          <div>
            <p class="text-xs text-neutral-500 mb-1">Low-Cost Benchmark</p>
            <p class="text-xl font-bold text-spring-600">{{ formatPercentage(data.low_cost_benchmark || 0.5) }}%</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'FeeAnalysisSection',

  mixins: [currencyMixin],

  props: {
    data: {
      type: Object,
      default: null,
    },
  },

  methods: {
    formatPercentage(value) {
      if (value === null || value === undefined) return '0.00';
      return value.toFixed(2);
    },

    formatFeeType(type) {
      const names = {
        management: 'Management Fees',
        platform: 'Platform Fees',
        advisory: 'Advisory Fees',
        transaction: 'Transaction Fees',
        fund: 'Fund Fees (OCF/TER)',
        other: 'Other Fees',
      };
      return names[type] || type.charAt(0).toUpperCase() + type.slice(1);
    },

    getFeeStatusClass(percentage) {
      if (percentage <= 0.5) return 'bg-spring-500 text-white';
      if (percentage <= 1.0) return 'bg-violet-500 text-white';
      if (percentage <= 1.5) return 'bg-violet-500 text-white';
      return 'bg-raspberry-500 text-white';
    },

    getFeeStatusLabel(percentage) {
      if (percentage <= 0.5) return 'Excellent - Low Cost';
      if (percentage <= 1.0) return 'Good - Competitive';
      if (percentage <= 1.5) return 'Fair - Room for improvement';
      return 'High - Consider alternatives';
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
  },
};
</script>

<style scoped>
/* Component-specific styles */
</style>
