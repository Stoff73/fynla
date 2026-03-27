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

<style scoped>
.account-holdings-panel {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.panel-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 16px;
}

.panel-title {
  font-size: 18px;
  font-weight: 600;
  @apply text-horizon-500;
  margin: 0;
}

.add-holding-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  @apply bg-raspberry-500;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}

.add-holding-btn:hover {
  @apply bg-raspberry-500;
}

.btn-icon {
  width: 20px;
  height: 20px;
}

/* Default Period Banner */
.default-period-banner {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 12px 16px;
  @apply bg-violet-50 border border-violet-500;
  border-radius: 8px;
}

.banner-icon {
  width: 20px;
  height: 20px;
  @apply text-violet-600;
  flex-shrink: 0;
  margin-top: 2px;
}

.banner-content {
  flex: 1;
}

.banner-title {
  font-size: 14px;
  font-weight: 600;
  @apply text-violet-800;
  margin: 0 0 4px 0;
}

.banner-text {
  font-size: 13px;
  @apply text-violet-700;
  margin: 0;
}

/* Holdings Table */
.holdings-table-container {
  overflow-x: auto;
  @apply border border-light-gray;
  border-radius: 12px;
}

.holdings-table {
  width: 100%;
  border-collapse: collapse;
}

.holdings-table th,
.holdings-table td {
  padding: 12px 16px;
  text-align: left;
}

.holdings-table th {
  @apply bg-eggshell-500 text-neutral-500 border-b border-light-gray;
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.holding-row {
  @apply border-b border-light-gray;
}

.holding-row:last-child {
  border-bottom: none;
}

.holding-row:hover {
  @apply bg-eggshell-500;
}

.holding-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.holding-name {
  font-weight: 600;
  @apply text-horizon-500;
}

.holding-ticker {
  font-size: 12px;
  @apply text-raspberry-500;
  font-weight: 500;
}

.holding-isin {
  font-size: 11px;
  @apply text-horizon-400;
}

.type-badge {
  display: inline-block;
  padding: 4px 8px;
  font-size: 11px;
  font-weight: 600;
  border-radius: 4px;
}

.td-units,
.td-cost,
.td-price,
.td-initial-value,
.td-value {
  font-variant-numeric: tabular-nums;
}

.td-date {
  white-space: nowrap;
}

.date-text {
  font-size: 13px;
  @apply text-neutral-500;
}

.date-default {
  font-size: 12px;
  @apply text-violet-700 bg-violet-50;
  padding: 2px 6px;
  border-radius: 4px;
  cursor: help;
}

.td-initial-value {
  @apply text-neutral-500;
}

.td-value {
  font-weight: 600;
  @apply text-horizon-500;
}

.td-initial-allocation {
  @apply text-neutral-500;
}

.totals-initial-value {
  @apply text-neutral-500;
}

.totals-initial-allocation {
  @apply text-neutral-500;
}

.allocation-text {
  font-size: 13px;
  @apply text-neutral-500;
}

.totals-row {
  @apply bg-eggshell-500;
  font-weight: 600;
}

.totals-label {
  text-align: right;
  @apply text-neutral-500;
}

.totals-value {
  @apply text-horizon-500;
  font-size: 16px;
}

.totals-allocation {
  @apply text-neutral-500;
}

/* Mobile Cards (hidden on desktop) */
.holdings-cards-mobile {
  display: none;
}

/* Empty State */
.empty-state {
  text-align: center;
  padding: 60px 20px;
  @apply bg-light-blue-100 border border-light-gray;
  border-radius: 12px;
}

.empty-icon {
  width: 48px;
  height: 48px;
  @apply text-horizon-400;
  margin: 0 auto 16px;
}

.empty-title {
  font-size: 18px;
  font-weight: 600;
  @apply text-neutral-500;
  margin: 0 0 8px 0;
}

.empty-subtitle {
  font-size: 14px;
  @apply text-neutral-500;
  margin: 0 0 20px 0;
}

.add-first-btn {
  padding: 12px 24px;
  @apply bg-raspberry-500;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}

.add-first-btn:hover {
  @apply bg-raspberry-500;
}

/* Allocation Summary */
.allocation-summary {
  @apply bg-white border border-light-gray;
  border-radius: 12px;
  padding: 20px;
}

.summary-title {
  font-size: 16px;
  font-weight: 600;
  @apply text-horizon-500;
  margin: 0 0 16px 0;
}

.allocation-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 12px;
}

.allocation-item {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 12px;
  @apply bg-eggshell-500;
  border-radius: 8px;
}

.allocation-header {
  display: flex;
  align-items: center;
  gap: 8px;
}

.allocation-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
}

.allocation-type {
  font-size: 14px;
  @apply text-neutral-500;
}

.allocation-values {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.allocation-amount {
  font-size: 16px;
  font-weight: 600;
  @apply text-horizon-500;
}

.allocation-percent {
  font-size: 14px;
  @apply text-neutral-500;
}

@media (max-width: 768px) {
  .holdings-table-container {
    display: none;
  }

  .holdings-cards-mobile {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }

  .holding-card {
    background: white;
    @apply border border-light-gray;
    border-radius: 12px;
    padding: 16px;
  }

  .card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
  }

  .card-details {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 8px;
  }

  .detail-row {
    display: flex;
    flex-direction: column;
    gap: 2px;
  }

  .detail-label {
    font-size: 12px;
    @apply text-neutral-500;
  }

  .detail-value {
    font-size: 14px;
    @apply text-horizon-500;
  }

  .panel-header {
    flex-direction: column;
    align-items: flex-start;
  }

  .add-holding-btn {
    width: 100%;
    justify-content: center;
  }
}
</style>
