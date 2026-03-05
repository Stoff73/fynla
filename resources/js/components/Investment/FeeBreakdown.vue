<template>
  <div class="fee-breakdown">
    <!-- No Accounts State -->
    <div v-if="accounts.length === 0" class="text-center py-12 text-neutral-500">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12 mx-auto mb-4 text-horizon-400">
        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
      </svg>
      <p class="text-lg font-medium">No Investment Accounts</p>
      <p class="text-sm">Add investment accounts to see fee analysis.</p>
    </div>

    <!-- Fee Analysis Content -->
    <div v-else class="space-y-6">
      <!-- Summary Cards -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-eggshell-500 rounded-lg p-4">
          <p class="text-sm text-neutral-500 mb-1">Platform Fees</p>
          <p class="text-2xl font-bold text-violet-600">{{ formatPercent(weightedPlatformFee) }}</p>
          <p class="text-sm text-neutral-500">{{ formatCurrency(annualPlatformFees) }}/year</p>
        </div>
        <div class="bg-eggshell-500 rounded-lg p-4">
          <p class="text-sm text-neutral-500 mb-1">Fund Fees (OCF)</p>
          <p class="text-2xl font-bold text-raspberry-600">{{ formatPercent(weightedOCF) }}</p>
          <p class="text-sm text-neutral-500">{{ formatCurrency(annualFundFees) }}/year</p>
        </div>
        <div class="bg-eggshell-500 rounded-lg p-4">
          <p class="text-sm text-neutral-500 mb-1">Advisor Fees</p>
          <p class="text-2xl font-bold text-violet-600">{{ formatPercent(weightedAdvisorFee) }}</p>
          <p class="text-sm text-neutral-500">{{ formatCurrency(annualAdvisorFees) }}/year</p>
        </div>
        <div class="bg-savannah-100 rounded-lg p-4 border border-horizon-300">
          <p class="text-sm text-neutral-500 mb-1">Total Annual Cost</p>
          <p class="text-2xl font-bold text-horizon-500">{{ formatPercent(totalFeePercent) }}</p>
          <p class="text-sm text-neutral-500">{{ formatCurrency(totalAnnualFees) }}/year</p>
        </div>
      </div>

      <!-- Fee Breakdown by Account -->
      <div class="bg-white rounded-lg border border-light-gray overflow-hidden">
        <div class="px-4 py-3 bg-eggshell-500 border-b border-light-gray">
          <h3 class="text-lg font-semibold text-horizon-500">Fee Breakdown by Account</h3>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-light-gray bg-eggshell-500">
                <th class="text-left py-3 px-4 font-semibold text-neutral-500">Account</th>
                <th class="text-right py-3 px-4 font-semibold text-neutral-500">Value</th>
                <th class="text-right py-3 px-4 font-semibold text-neutral-500">Platform</th>
                <th class="text-right py-3 px-4 font-semibold text-neutral-500">Avg OCF</th>
                <th class="text-right py-3 px-4 font-semibold text-neutral-500">Advisor</th>
                <th class="text-right py-3 px-4 font-semibold text-neutral-500">Total %</th>
                <th class="text-right py-3 px-4 font-semibold text-neutral-500">Annual Cost</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="account in accountFeeData"
                :key="account.id"
                class="border-b border-savannah-100 hover:bg-eggshell-500"
              >
                <td class="py-3 px-4">
                  <div class="font-medium text-horizon-500">{{ account.provider }}</div>
                  <div class="text-xs text-neutral-500">{{ account.name }}</div>
                </td>
                <td class="text-right py-3 px-4 font-medium">{{ formatCurrency(account.value) }}</td>
                <td class="text-right py-3 px-4">{{ formatPercent(account.platformFee) }}</td>
                <td class="text-right py-3 px-4">{{ formatPercent(account.avgOCF) }}</td>
                <td class="text-right py-3 px-4">{{ formatPercent(account.advisorFee) }}</td>
                <td class="text-right py-3 px-4 font-semibold" :class="getFeeClass(account.totalPercent)">
                  {{ formatPercent(account.totalPercent) }}
                </td>
                <td class="text-right py-3 px-4 font-semibold text-raspberry-600">
                  {{ formatCurrency(account.annualCost) }}
                </td>
              </tr>
            </tbody>
            <tfoot>
              <tr class="bg-savannah-100 font-semibold">
                <td class="py-3 px-4">Total Portfolio</td>
                <td class="text-right py-3 px-4">{{ formatCurrency(totalPortfolioValue) }}</td>
                <td class="text-right py-3 px-4">{{ formatPercent(weightedPlatformFee) }}</td>
                <td class="text-right py-3 px-4">{{ formatPercent(weightedOCF) }}</td>
                <td class="text-right py-3 px-4">{{ formatPercent(weightedAdvisorFee) }}</td>
                <td class="text-right py-3 px-4" :class="getFeeClass(totalFeePercent)">
                  {{ formatPercent(totalFeePercent) }}
                </td>
                <td class="text-right py-3 px-4 text-raspberry-600">{{ formatCurrency(totalAnnualFees) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      <!-- 10-Year Fee Impact -->
      <div class="bg-white rounded-lg border border-light-gray p-6">
        <h3 class="text-lg font-semibold text-horizon-500 mb-4">10-Year Fee Impact</h3>
        <p class="text-sm text-neutral-500 mb-4">
          Projected cumulative fees over 10 years, assuming 5% annual portfolio growth.
        </p>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div class="text-center p-4 bg-eggshell-500 rounded-lg">
            <p class="text-sm text-neutral-500 mb-1">Cumulative Fees Paid</p>
            <p class="text-3xl font-bold text-raspberry-600">{{ formatCurrency(tenYearTotalFees) }}</p>
          </div>
          <div class="text-center p-4 bg-eggshell-500 rounded-lg">
            <p class="text-sm text-neutral-500 mb-1">Fee Drag (Lost Growth)</p>
            <p class="text-3xl font-bold text-violet-600">{{ formatCurrency(tenYearFeeDrag) }}</p>
          </div>
          <div class="text-center p-4 bg-savannah-100 rounded-lg border border-horizon-300">
            <p class="text-sm text-neutral-500 mb-1">Total Impact</p>
            <p class="text-3xl font-bold text-horizon-500">{{ formatCurrency(tenYearTotalImpact) }}</p>
          </div>
        </div>
        <p class="text-xs text-neutral-500 mt-4 text-center">
          Fee drag represents the additional growth you would have earned if fees were reinvested.
        </p>
      </div>

      <!-- Fee Comparison Guide -->
      <div class="bg-eggshell-500 rounded-lg p-4">
        <h4 class="font-semibold text-violet-800 mb-2">Fee Benchmarks</h4>
        <div class="grid grid-cols-3 gap-4 text-sm">
          <div>
            <span class="inline-block w-3 h-3 rounded-full bg-spring-500 mr-2"></span>
            <span class="text-neutral-500">Low: &lt; 0.5%</span>
          </div>
          <div>
            <span class="inline-block w-3 h-3 rounded-full bg-violet-500 mr-2"></span>
            <span class="text-neutral-500">Medium: 0.5% - 1.5%</span>
          </div>
          <div>
            <span class="inline-block w-3 h-3 rounded-full bg-raspberry-500 mr-2"></span>
            <span class="text-neutral-500">High: &gt; 1.5%</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { mapGetters } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'FeeBreakdown',

  mixins: [currencyMixin],

  computed: {
    ...mapGetters('investment', ['accounts', 'totalPortfolioValue']),

    // Calculate fee data for each account
    accountFeeData() {
      return this.accounts.map(account => {
        const value = parseFloat(account.current_value) || 0;
        let platformFee = 0;
        if (account.platform_fee_type === 'fixed') {
          const feeAmt = parseFloat(account.platform_fee_amount) || 0;
          let annual = feeAmt;
          if (account.platform_fee_frequency === 'monthly') annual = feeAmt * 12;
          else if (account.platform_fee_frequency === 'quarterly') annual = feeAmt * 4;
          platformFee = value > 0 ? (annual / value) * 100 : 0;
        } else {
          platformFee = parseFloat(account.platform_fee_percent) || 0;
        }
        const advisorFee = parseFloat(account.advisor_fee_percent) || 0;

        // Calculate weighted average OCF for this account's holdings
        const holdings = account.holdings || [];
        let avgOCF = 0;
        if (holdings.length > 0) {
          const totalHoldingValue = holdings.reduce((sum, h) => sum + (parseFloat(h.current_value) || 0), 0);
          if (totalHoldingValue > 0) {
            avgOCF = holdings.reduce((sum, h) => {
              const hValue = parseFloat(h.current_value) || 0;
              const hOCF = parseFloat(h.ocf_percent) || 0;
              return sum + (hValue * hOCF);
            }, 0) / totalHoldingValue;
          }
        }

        const totalPercent = platformFee + avgOCF + advisorFee;
        const annualCost = value * (totalPercent / 100);

        return {
          id: account.id,
          provider: account.provider,
          name: account.account_name,
          value,
          platformFee,
          avgOCF,
          advisorFee,
          totalPercent,
          annualCost,
        };
      });
    },

    // Weighted average platform fee across all accounts
    weightedPlatformFee() {
      if (this.totalPortfolioValue === 0) return 0;
      return this.accountFeeData.reduce((sum, a) => sum + (a.value * a.platformFee), 0) / this.totalPortfolioValue;
    },

    // Weighted average OCF across all accounts
    weightedOCF() {
      if (this.totalPortfolioValue === 0) return 0;
      return this.accountFeeData.reduce((sum, a) => sum + (a.value * a.avgOCF), 0) / this.totalPortfolioValue;
    },

    // Weighted average advisor fee across all accounts
    weightedAdvisorFee() {
      if (this.totalPortfolioValue === 0) return 0;
      return this.accountFeeData.reduce((sum, a) => sum + (a.value * a.advisorFee), 0) / this.totalPortfolioValue;
    },

    // Total fee percentage
    totalFeePercent() {
      return this.weightedPlatformFee + this.weightedOCF + this.weightedAdvisorFee;
    },

    // Annual fee amounts
    annualPlatformFees() {
      return this.totalPortfolioValue * (this.weightedPlatformFee / 100);
    },

    annualFundFees() {
      return this.totalPortfolioValue * (this.weightedOCF / 100);
    },

    annualAdvisorFees() {
      return this.totalPortfolioValue * (this.weightedAdvisorFee / 100);
    },

    totalAnnualFees() {
      return this.annualPlatformFees + this.annualFundFees + this.annualAdvisorFees;
    },

    // 10-year projections
    tenYearTotalFees() {
      const growthRate = 0.05;
      const feeRate = this.totalFeePercent / 100;
      let totalFees = 0;
      let portfolioValue = this.totalPortfolioValue;

      for (let year = 1; year <= 10; year++) {
        const feesThisYear = portfolioValue * feeRate;
        totalFees += feesThisYear;
        portfolioValue = (portfolioValue - feesThisYear) * (1 + growthRate);
      }

      return totalFees;
    },

    tenYearFeeDrag() {
      const growthRate = 0.05;
      const feeRate = this.totalFeePercent / 100;

      // Value with fees
      let valueWithFees = this.totalPortfolioValue;
      for (let year = 1; year <= 10; year++) {
        const feesThisYear = valueWithFees * feeRate;
        valueWithFees = (valueWithFees - feesThisYear) * (1 + growthRate);
      }

      // Value without fees
      const valueWithoutFees = this.totalPortfolioValue * Math.pow(1 + growthRate, 10);

      // Fee drag is the difference minus fees paid
      return (valueWithoutFees - valueWithFees) - this.tenYearTotalFees;
    },

    tenYearTotalImpact() {
      return this.tenYearTotalFees + this.tenYearFeeDrag;
    },
  },

  methods: {
    formatPercent(value) {
      if (value === null || value === undefined || isNaN(value)) return '0.00%';
      return value.toFixed(2) + '%';
    },

    getFeeClass(percent) {
      if (percent < 0.5) return 'text-spring-600';
      if (percent < 1.5) return 'text-violet-600';
      return 'text-raspberry-600';
    },
  },
};
</script>

<style scoped>
/* Component-specific styles */
</style>
