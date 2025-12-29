<template>
  <div class="tax-fees">
    <h2 class="text-2xl font-bold text-gray-900 mb-4">Tax & Fees</h2>
    <p class="text-gray-600 mb-6">Monitor fees and optimise tax efficiency</p>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
      <!-- Fee Summary -->
      <div class="bg-white border border-gray-200 rounded-lg p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Fee Summary</h3>
        <div class="space-y-3">
          <div class="flex justify-between">
            <span class="text-sm text-gray-600">Total Annual Fees:</span>
            <span class="text-sm font-medium text-gray-900">{{ formatCurrency(totalFees) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-sm text-gray-600">Fee Drag:</span>
            <span class="text-sm font-medium text-gray-900">{{ feeDragPercent.toFixed(2) }}%</span>
          </div>
        </div>
      </div>

      <!-- Tax Summary -->
      <div class="bg-white border border-gray-200 rounded-lg p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Tax Summary</h3>
        <div class="space-y-3">
          <div class="flex justify-between">
            <span class="text-sm text-gray-600">Tax Efficiency Score:</span>
            <span class="text-sm font-medium text-gray-900">{{ taxEfficiencyScore }}/100</span>
          </div>
          <div class="flex justify-between">
            <span class="text-sm text-gray-600">Unrealised Gains:</span>
            <span class="text-sm font-medium text-gray-900">{{ formatCurrency(unrealisedGains) }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Fee Breakdown -->
    <div class="bg-white border border-gray-200 rounded-lg p-6 mb-6">
      <h3 class="text-lg font-semibold text-gray-900 mb-4">Fee Breakdown</h3>
      <div v-if="feeBreakdown && feeBreakdown.length > 0" class="space-y-4">
        <div v-for="(fee, index) in feeBreakdown" :key="index" class="border-b border-gray-200 pb-3 last:border-b-0">
          <div class="flex justify-between items-center mb-2">
            <span class="text-sm font-medium text-gray-700">{{ fee.type }}</span>
            <span class="text-sm font-semibold text-gray-900">{{ formatCurrency(fee.amount) }}</span>
          </div>
          <div class="w-full bg-gray-200 rounded-full h-2">
            <div
              class="bg-blue-600 h-2 rounded-full"
              :style="{ width: (fee.amount / totalFees * 100) + '%' }"
            ></div>
          </div>
        </div>
        <div class="pt-3 border-t-2 border-gray-300">
          <div class="flex justify-between items-center">
            <span class="text-base font-semibold text-gray-900">Total Annual Fees</span>
            <span class="text-lg font-bold text-gray-900">{{ formatCurrency(totalFees) }}</span>
          </div>
        </div>
      </div>
      <p v-else class="text-gray-500 text-center py-6">No fee data available</p>
    </div>

    <!-- Tax Wrappers -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
      <!-- ISA Allowance Tracker -->
      <div class="bg-white border border-gray-200 rounded-lg p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">ISA Allowance (2025/26)</h3>
        <div class="mb-4">
          <div class="flex justify-between items-center mb-2">
            <span class="text-sm text-gray-600">Used</span>
            <span class="text-sm font-medium text-gray-900">{{ formatCurrency(isaUsed) }} / £20,000</span>
          </div>
          <div class="w-full bg-gray-200 rounded-full h-3">
            <div
              class="h-3 rounded-full transition-all"
              :class="isaPercentage >= 100 ? 'bg-red-600' : isaPercentage >= 80 ? 'bg-yellow-500' : 'bg-green-600'"
              :style="{ width: Math.min(isaPercentage, 100) + '%' }"
            ></div>
          </div>
          <p class="text-xs text-gray-500 mt-1">{{ isaPercentage.toFixed(1) }}% utilized</p>
        </div>
        <div class="text-sm text-gray-600">
          <p>Remaining allowance: <span class="font-medium text-gray-900">{{ formatCurrency(Math.max(0, 20000 - isaUsed)) }}</span></p>
        </div>
      </div>

      <!-- CGT Allowance -->
      <div class="bg-white border border-gray-200 rounded-lg p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Capital Gains Tax (2025/26)</h3>
        <div class="space-y-3 text-sm">
          <div class="flex justify-between">
            <span class="text-gray-600">Annual Allowance:</span>
            <span class="font-medium text-gray-900">£3,000</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-600">Unrealised Gains:</span>
            <span class="font-medium text-gray-900">{{ formatCurrency(unrealisedGains) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-600">Potential CGT Liability:</span>
            <span class="font-medium text-gray-900">{{ formatCurrency(calculateCGT(unrealisedGains)) }}</span>
          </div>
        </div>
        <p class="text-xs text-gray-500 mt-4">
          * Assumes higher rate taxpayer (20% CGT rate)
        </p>
      </div>
    </div>

    <!-- Tax Optimization Opportunities -->
    <div class="bg-white border border-gray-200 rounded-lg p-6">
      <h3 class="text-lg font-semibold text-gray-900 mb-4">Tax Optimization Opportunities</h3>
      <div v-if="taxOptimizations && taxOptimizations.length > 0" class="space-y-3">
        <div
          v-for="(opportunity, index) in taxOptimizations"
          :key="index"
          class="flex items-start p-4 bg-blue-50 border border-blue-200 rounded-lg"
        >
          <svg class="h-5 w-5 text-blue-600 mr-3 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <div class="flex-1">
            <p class="text-sm font-medium text-blue-900">{{ opportunity.title }}</p>
            <p class="text-xs text-blue-700 mt-1">{{ opportunity.description }}</p>
            <p v-if="opportunity.potential_saving" class="text-xs font-semibold text-blue-900 mt-2">
              Potential saving: {{ formatCurrency(opportunity.potential_saving) }}
            </p>
          </div>
        </div>
      </div>
      <p v-else class="text-gray-500 text-center py-6">No optimization opportunities identified</p>
    </div>
  </div>
</template>

<script>
import { mapGetters } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'TaxFees',
  mixins: [currencyMixin],

  computed: {
    ...mapGetters('investment', [
      'totalFees',
      'feeDragPercent',
      'unrealisedGains',
      'taxEfficiencyScore',
      'totalISAContributions',
      'isaAllowancePercentage',
    ]),

    feeBreakdown() {
      const analysis = this.$store.state.investment.analysis;
      return analysis?.fee_analysis?.fee_breakdown || null;
    },

    taxOptimizations() {
      const analysis = this.$store.state.investment.analysis;
      return analysis?.tax_efficiency?.optimization_opportunities || [];
    },

    isaUsed() {
      return this.totalISAContributions || 0;
    },

    isaPercentage() {
      return this.isaAllowancePercentage || 0;
    },
  },

  methods: {
    calculateCGT(unrealisedGain) {
      const cgtAllowance = 3000; // 2025/26 allowance
      const taxableGain = Math.max(0, unrealisedGain - cgtAllowance);
      const cgtRate = 0.20; // Higher rate taxpayer
      return taxableGain * cgtRate;
    },
  },
};
</script>
