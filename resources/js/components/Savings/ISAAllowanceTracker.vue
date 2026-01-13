<template>
  <div class="isa-allowance-tracker bg-white rounded-lg border border-gray-200 p-6">
    <div class="flex justify-between items-center mb-4">
      <h3 class="text-lg font-semibold text-gray-900">Tax-Free Savings Allowance {{ currentTaxYear }}</h3>
      <span class="text-sm text-gray-600">{{ formatCurrency(totalAllowance) }} total</span>
    </div>

    <!-- Progress Bar -->
    <div class="mb-4">
      <div class="w-full bg-gray-200 rounded-full h-4 overflow-hidden">
        <div class="h-full flex">
          <!-- Cash ISA -->
          <div
            v-if="cashISAUsed > 0"
            class="bg-blue-500 flex items-center justify-center text-xs text-white font-medium"
            :style="{ width: cashISAPercent + '%' }"
            :title="`Cash ISA: ${formatCurrency(cashISAUsed)}`"
          >
            <span v-if="cashISAPercent > 10">Cash</span>
          </div>
          <!-- Stocks & Shares ISA -->
          <div
            v-if="stocksISAUsed > 0"
            class="bg-purple-500 flex items-center justify-center text-xs text-white font-medium"
            :style="{ width: stocksISAPercent + '%' }"
            :title="`Stocks ISA: ${formatCurrency(stocksISAUsed)}`"
          >
            <span v-if="stocksISAPercent > 10">Stocks</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Breakdown -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
      <div class="text-center p-3 bg-gray-50 rounded-lg">
        <p class="text-sm text-gray-600 mb-1">Cash ISA</p>
        <p class="text-lg font-bold text-blue-700">{{ formatCurrency(cashISAUsed) }}</p>
      </div>

      <div class="text-center p-3 bg-gray-50 rounded-lg">
        <p class="text-sm text-gray-600 mb-1">Stocks & Shares ISA</p>
        <p class="text-lg font-bold text-purple-700">{{ formatCurrency(stocksISAUsed) }}</p>
      </div>

      <div class="text-center p-3 bg-gray-50 rounded-lg">
        <p class="text-sm text-gray-600 mb-1">Remaining</p>
        <p class="text-lg font-bold text-green-700">{{ formatCurrency(remaining) }}</p>
      </div>
    </div>

    <!-- Info Message -->
    <div class="p-3 bg-gray-50 rounded-lg">
      <p class="text-sm text-gray-700">
        <span class="font-medium">Tax year {{ currentTaxYear }}:</span> You can save up to £20,000 across all tax-free savings accounts (ISAs).
        Any unused allowance cannot be carried forward to the next year.
      </p>
    </div>
  </div>
</template>

<script>
import { mapState, mapGetters } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'ISAAllowanceTracker',
  mixins: [currencyMixin],

  computed: {
    ...mapState('savings', ['isaAllowance']),
    ...mapGetters('savings', ['isaAllowanceRemaining']),

    totalAllowance() {
      return 20000; // UK ISA allowance
    },

    currentTaxYear() {
      // Calculate current UK tax year (April 6 - April 5)
      const now = new Date();
      const year = now.getFullYear();
      const month = now.getMonth(); // 0-indexed
      const day = now.getDate();

      // If before April 6, we're in previous tax year
      if (month < 3 || (month === 3 && day < 6)) {
        return `${year - 1}/${String(year).slice(-2)}`;
      }
      return `${year}/${String(year + 1).slice(-2)}`;
    },

    cashISAUsed() {
      return this.isaAllowance?.cash_isa_used || 0;
    },

    stocksISAUsed() {
      return this.isaAllowance?.stocks_shares_isa_used || 0;
    },

    remaining() {
      return Math.max(0, this.totalAllowance - this.cashISAUsed - this.stocksISAUsed);
    },

    cashISAPercent() {
      return (this.cashISAUsed / this.totalAllowance) * 100;
    },

    stocksISAPercent() {
      return (this.stocksISAUsed / this.totalAllowance) * 100;
    },
  },

  // formatCurrency provided by currencyMixin
};
</script>

<style scoped>
.isa-allowance-tracker {
  /* Custom styling if needed */
}
</style>
