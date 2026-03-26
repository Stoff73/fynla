<template>
  <div class="space-y-4">
    <!-- Header with Add Button -->
    <div class="flex justify-between items-center">
      <h3 class="text-lg font-semibold text-horizon-500">Holdings in {{ account.account_name || account.provider }}</h3>
      <button v-preview-disabled="'add'" @click="$emit('open-holding-modal')" class="inline-flex items-center gap-2 px-4 py-2 bg-raspberry-500 text-white rounded-lg text-sm font-semibold hover:bg-raspberry-600 transition-colors">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
        </svg>
        Add Holding
      </button>
    </div>

    <!-- Holdings Table -->
    <div v-if="hasHoldings" class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-light-gray">
            <th class="text-left py-2 text-neutral-500 font-medium">Fund Name</th>
            <th class="text-left py-2 text-neutral-500 font-medium">Type</th>
            <th class="text-right py-2 text-neutral-500 font-medium">Allocation</th>
            <th class="text-right py-2 text-neutral-500 font-medium">Value</th>
            <th class="text-right py-2 text-neutral-500 font-medium">OCF</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="holding in sortedHoldings" :key="holding.id" class="border-b border-light-gray last:border-0">
            <td class="py-2 text-horizon-500 font-medium">{{ holding.security_name || 'Unnamed' }}</td>
            <td class="py-2 text-neutral-500 capitalize">{{ formatAssetType(holding.asset_type) }}</td>
            <td class="py-2 text-right text-horizon-500">{{ holding.allocation_percent || 0 }}%</td>
            <td class="py-2 text-right text-horizon-500">{{ formatCurrency(holdingValue(holding)) }}</td>
            <td class="py-2 text-right text-neutral-500">{{ holding.ocf_percent ? parseFloat(holding.ocf_percent).toFixed(2) + '%' : '—' }}</td>
          </tr>
        </tbody>
        <tfoot v-if="cashPercent > 0">
          <tr class="border-t border-light-gray">
            <td class="py-2 text-neutral-500 italic">Cash (unallocated)</td>
            <td></td>
            <td class="py-2 text-right text-neutral-500">{{ cashPercent.toFixed(1) }}%</td>
            <td class="py-2 text-right text-neutral-500">{{ formatCurrency(cashValue) }}</td>
            <td></td>
          </tr>
        </tfoot>
      </table>
    </div>

    <!-- Empty State -->
    <div v-else class="text-center py-12 bg-white border-2 border-dashed border-horizon-300 rounded-lg">
      <p class="text-lg font-semibold text-neutral-500 mb-2">No holdings yet</p>
      <p class="text-sm text-neutral-500 mb-5">Add your first holding to track your investments</p>
      <button v-preview-disabled="'add'" @click="$emit('open-holding-modal')" class="px-6 py-3 bg-raspberry-500 text-white rounded-lg text-sm font-semibold hover:bg-raspberry-600 transition-colors">
        Add First Holding
      </button>
    </div>

    <!-- Fee summary tied to holdings -->
    <div v-if="hasHoldings" class="bg-savannah-100 rounded-lg p-4">
      <div class="flex justify-between text-sm">
        <span class="text-neutral-500">Weighted Avg Fund Fee (OCF)</span>
        <span class="font-medium text-horizon-500">{{ weightedAverageOCF.toFixed(2) }}%</span>
      </div>
      <div class="flex justify-between text-sm mt-1">
        <span class="text-neutral-500">Total Annual Cost (platform + fund fees)</span>
        <span class="font-semibold text-horizon-500">{{ totalFeePercent.toFixed(2) }}%</span>
      </div>
    </div>

    <!-- 10-Year Fee Impact -->
    <div v-if="annualFeeCost > 0" class="bg-white border border-light-gray rounded-lg p-4">
      <h4 class="text-sm font-semibold text-horizon-500 mb-3">10-Year Fee Impact</h4>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
          <p class="text-xs text-neutral-500">Cumulative Fees Paid</p>
          <p class="text-base font-semibold text-raspberry-600">{{ formatCurrency(feeImpact10yr.totalFees) }}</p>
        </div>
        <div>
          <p class="text-xs text-neutral-500">Lost Growth (Fee Drag)</p>
          <p class="text-base font-semibold text-raspberry-600">{{ formatCurrency(feeImpact10yr.lostGrowth) }}</p>
        </div>
        <div>
          <p class="text-xs text-neutral-500">Total Impact</p>
          <p class="text-base font-semibold text-horizon-500">{{ formatCurrency(feeImpact10yr.totalImpact) }}</p>
        </div>
      </div>
      <p class="text-xs text-neutral-500 mt-2">
        Assuming 5% growth rate and current contribution levels.
      </p>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'AccountHoldingsPanel',

  mixins: [currencyMixin],

  props: {
    account: {
      type: Object,
      required: true,
    },
  },

  emits: ['open-holding-modal'],

  computed: {
    holdings() {
      return (this.account.holdings || []).filter(h => h.asset_type !== 'cash');
    },

    hasHoldings() {
      return this.holdings.length > 0;
    },

    sortedHoldings() {
      return [...this.holdings].sort((a, b) => (b.current_value || 0) - (a.current_value || 0));
    },

    fundValue() {
      return parseFloat(this.account.current_value) || 0;
    },

    totalAllocation() {
      return this.holdings.reduce((sum, h) => sum + (parseFloat(h.allocation_percent) || 0), 0);
    },

    cashPercent() {
      return Math.max(0, 100 - this.totalAllocation);
    },

    cashValue() {
      return this.fundValue * (this.cashPercent / 100);
    },

    platformFeePercent() {
      if (this.account.platform_fee_type === 'fixed' && this.fundValue > 0) {
        const amount = parseFloat(this.account.platform_fee_amount) || 0;
        let annualAmount = amount;
        if (this.account.platform_fee_frequency === 'monthly') annualAmount = amount * 12;
        else if (this.account.platform_fee_frequency === 'quarterly') annualAmount = amount * 4;
        return (annualAmount / this.fundValue) * 100;
      }
      return parseFloat(this.account.platform_fee_percent) || 0;
    },

    weightedAverageOCF() {
      if (!this.hasHoldings || this.fundValue <= 0) return 0;
      const totalWeightedOCF = this.holdings.reduce((sum, h) => {
        const value = this.holdingValue(h);
        return sum + (value * (parseFloat(h.ocf_percent) || 0));
      }, 0);
      return totalWeightedOCF / this.fundValue;
    },

    totalFeePercent() {
      return this.platformFeePercent + this.weightedAverageOCF;
    },

    annualFeeCost() {
      return this.fundValue * (this.totalFeePercent / 100);
    },

    feeImpact10yr() {
      const feeRate = this.totalFeePercent / 100;
      const grossGrowth = 0.05;
      const years = 10;
      const monthlyContribution = parseFloat(this.account.monthly_contribution_amount) || 0;
      const annualContribution = monthlyContribution * 12;

      const netGrowth = grossGrowth - feeRate;
      let valueWithFees = this.fundValue;
      for (let i = 0; i < years; i++) {
        valueWithFees = (valueWithFees + annualContribution) * (1 + netGrowth);
      }

      let valueWithoutFees = this.fundValue;
      for (let i = 0; i < years; i++) {
        valueWithoutFees = (valueWithoutFees + annualContribution) * (1 + grossGrowth);
      }

      const totalFees = this.annualFeeCost * years;
      const lostGrowth = Math.max(0, valueWithoutFees - valueWithFees - totalFees);
      const totalImpact = totalFees + lostGrowth;

      return { totalFees, lostGrowth, totalImpact };
    },
  },

  methods: {
    holdingValue(holding) {
      if (holding.current_value) return parseFloat(holding.current_value);
      return this.fundValue * ((parseFloat(holding.allocation_percent) || 0) / 100);
    },

    formatAssetType(type) {
      const types = {
        equity: 'Equity',
        uk_equity: 'UK Equity',
        us_equity: 'US Equity',
        international_equity: 'Int\'l Equity',
        bond: 'Bond',
        fund: 'Fund',
        etf: 'ETF',
        cash: 'Cash',
        alternative: 'Alternative',
        property: 'Property',
      };
      return types[type] || type?.charAt(0).toUpperCase() + type?.slice(1) || 'Other';
    },
  },
};
</script>
