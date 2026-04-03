<template>
  <div class="tax-fees">
    <h2 class="text-2xl font-bold text-horizon-500 mb-4">Tax & Fees</h2>
    <p class="text-neutral-500 mb-6">Monitor fees and optimise tax efficiency</p>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
      <!-- Fee Summary -->
      <div class="bg-white border border-light-gray rounded-lg p-6">
        <h3 class="text-lg font-semibold text-horizon-500 mb-4">Fee Summary</h3>
        <div class="space-y-3">
          <div class="flex justify-between">
            <span class="text-sm text-neutral-500">Total Annual Fees:</span>
            <span class="text-sm font-medium text-horizon-500">{{ formatCurrency(totalFees) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-sm text-neutral-500">Fee Drag:</span>
            <span class="text-sm font-medium text-horizon-500">{{ feeDragPercent.toFixed(2) }}%</span>
          </div>
        </div>
      </div>

      <!-- Tax Summary -->
      <div class="bg-white border border-light-gray rounded-lg p-6">
        <h3 class="text-lg font-semibold text-horizon-500 mb-4">Tax Summary</h3>
        <div class="space-y-3">
          <div class="flex justify-between">
            <span class="text-sm text-neutral-500">Tax Efficiency:</span>
            <span class="text-sm font-medium text-horizon-500">{{ taxEfficiencyLabel }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-sm text-neutral-500">Unrealised Gains:</span>
            <span class="text-sm font-medium text-horizon-500">{{ formatCurrency(unrealisedGains) }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Fee Breakdown -->
    <div class="bg-white border border-light-gray rounded-lg p-6 mb-6">
      <h3 class="text-lg font-semibold text-horizon-500 mb-4">Fee Breakdown</h3>
      <div v-if="feeBreakdown && feeBreakdown.length > 0" class="space-y-4">
        <div v-for="(fee, index) in feeBreakdown" :key="index" class="border-b border-light-gray pb-3 last:border-b-0">
          <div class="flex justify-between items-center mb-2">
            <span class="text-sm font-medium text-neutral-500">{{ fee.type }}</span>
            <span class="text-sm font-semibold text-horizon-500">{{ formatCurrency(fee.amount) }}</span>
          </div>
          <div class="w-full bg-savannah-200 rounded-full h-2">
            <div
              class="bg-raspberry-500 h-2 rounded-full"
              :style="{ width: (fee.amount / totalFees * 100) + '%' }"
            ></div>
          </div>
        </div>
        <div class="pt-3 border-t-2 border-horizon-300">
          <div class="flex justify-between items-center">
            <span class="text-base font-semibold text-horizon-500">Total Annual Fees</span>
            <span class="text-lg font-bold text-horizon-500">{{ formatCurrency(totalFees) }}</span>
          </div>
        </div>
      </div>
      <p v-else class="text-neutral-500 text-center py-6">No fee data available</p>
    </div>

    <!-- Tax Wrappers -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
      <!-- ISA Allowance Tracker -->
      <div class="bg-white border border-light-gray rounded-lg p-6">
        <h3 class="text-lg font-semibold text-horizon-500 mb-4">ISA Allowance ({{ currentTaxYear }})</h3>
        <div class="mb-4">
          <div class="flex justify-between items-center mb-2">
            <span class="text-sm text-neutral-500">Used</span>
            <span class="text-sm font-medium text-horizon-500">{{ formatCurrency(isaUsed) }} / {{ formatCurrency(isaAllowanceAmount) }}</span>
          </div>
          <div class="w-full bg-savannah-200 rounded-full h-3">
            <div
              class="h-3 rounded-full transition-all"
              :class="isaPercentage >= 100 ? 'bg-raspberry-600' : isaPercentage >= 80 ? 'bg-violet-500' : 'bg-spring-600'"
              :style="{ width: Math.min(isaPercentage, 100) + '%' }"
            ></div>
          </div>
          <p class="text-xs text-neutral-500 mt-1">{{ isaPercentage.toFixed(1) }}% utilized</p>
        </div>
        <div class="text-sm text-neutral-500">
          <p>Remaining allowance: <span class="font-medium text-horizon-500">{{ formatCurrency(Math.max(0, isaAllowanceAmount - isaUsed)) }}</span></p>
        </div>
      </div>

      <!-- CGT Allowance -->
      <div class="bg-white border border-light-gray rounded-lg p-6">
        <h3 class="text-lg font-semibold text-horizon-500 mb-4">Capital Gains Tax ({{ currentTaxYear }})</h3>
        <div class="space-y-3 text-sm">
          <div class="flex justify-between">
            <span class="text-neutral-500">Annual Allowance:</span>
            <span class="font-medium text-horizon-500">{{ formatCurrency(cgtAllowanceAmount) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-neutral-500">Unrealised Gains:</span>
            <span class="font-medium text-horizon-500">{{ formatCurrency(unrealisedGains) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-neutral-500">Potential Capital Gains Tax Liability:</span>
            <span class="font-medium text-horizon-500">{{ formatCurrency(calculateCGT(unrealisedGains)) }}</span>
          </div>
        </div>
        <p class="text-xs text-neutral-500 mt-4">
          * Assumes higher rate taxpayer (20% Capital Gains Tax rate)
        </p>
      </div>
    </div>

    <!-- Tax Optimization Opportunities -->
    <div class="bg-white border border-light-gray rounded-lg p-6">
      <h3 class="text-lg font-semibold text-horizon-500 mb-4">Tax Optimisation Opportunities</h3>
      <div v-if="taxOptimizations && taxOptimizations.length > 0" class="space-y-3">
        <div
          v-for="(opportunity, index) in taxOptimizations"
          :key="index"
          class="flex items-start p-4 bg-eggshell-500 rounded-lg"
        >
          <svg class="h-5 w-5 text-violet-600 mr-3 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <div class="flex-1">
            <p class="text-sm font-medium text-violet-900">{{ opportunity.title }}</p>
            <p class="text-xs text-violet-700 mt-1">{{ opportunity.description }}</p>
            <p v-if="opportunity.potential_saving" class="text-xs font-semibold text-violet-900 mt-2">
              Potential saving: {{ formatCurrency(opportunity.potential_saving) }}
            </p>
          </div>
        </div>
      </div>
      <p v-else class="text-neutral-500 text-center py-6">No optimisation opportunities identified</p>
    </div>
  </div>
</template>

<script>
import { mapGetters } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';
import { getCurrentTaxYear } from '@/utils/dateFormatter';
import { CGT_ANNUAL_ALLOWANCE, ISA_ANNUAL_ALLOWANCE } from '@/constants/taxConfig';

export default {
  name: 'TaxFees',
  mixins: [currencyMixin],

  computed: {
    currentTaxYear() {
      return getCurrentTaxYear();
    },

    ...mapGetters('investment', [
      'totalFees',
      'feeDragPercent',
      'unrealisedGains',
      'taxEfficiencyScore',
      'totalISAContributions',
      'isaAllowancePercentage',
      'analysis',
    ]),

    taxEfficiencyLabel() {
      if (this.taxEfficiencyScore >= 80) return 'Highly Efficient';
      if (this.taxEfficiencyScore >= 60) return 'Moderately Efficient';
      if (this.taxEfficiencyScore >= 40) return 'Could Be Improved';
      return 'Needs Attention';
    },

    feeBreakdown() {
      return this.analysis?.fee_analysis?.fee_breakdown || null;
    },

    taxOptimizations() {
      return this.analysis?.tax_efficiency?.optimization_opportunities || [];
    },

    isaUsed() {
      return this.totalISAContributions || 0;
    },

    isaPercentage() {
      return this.isaAllowancePercentage || 0;
    },

    isaAllowanceAmount() {
      return ISA_ANNUAL_ALLOWANCE;
    },

    cgtAllowanceAmount() {
      return CGT_ANNUAL_ALLOWANCE;
    },
  },

  methods: {
    calculateCGT(unrealisedGain) {
      const taxableGain = Math.max(0, unrealisedGain - CGT_ANNUAL_ALLOWANCE);
      const cgtRate = 0.20; // Higher rate taxpayer
      return taxableGain * cgtRate;
    },
  },
};
</script>
