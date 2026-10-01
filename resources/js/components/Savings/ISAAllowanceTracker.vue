<template>
  <!-- Every figure is the one ISA tracker's (ISATracker via GET /api/savings,
       the same /m and iOS read; CSJ 2026-10-01). This component used to work
       out its own Lifetime ISA eligibility, bonus and remaining allowance. -->
  <div class="isa-allowance-tracker bg-white rounded-lg border border-light-gray p-6">
    <div class="flex justify-between items-center mb-4">
      <h3 class="text-lg font-semibold text-horizon-500">Tax-Free Savings Allowance {{ status.tax_year }}</h3>
      <span class="text-sm text-neutral-500">{{ formatCurrency(status.total_allowance || 0) }} total</span>
    </div>

    <!-- Progress Bar -->
    <div class="mb-4">
      <div class="w-full bg-savannah-200 rounded-full h-4 overflow-hidden">
        <div class="h-full flex">
          <div
            v-if="status.cash_isa_used > 0"
            class="bg-violet-500 flex items-center justify-center text-xs text-white font-medium"
            :style="{ width: (status.cash_isa_percent || 0) + '%' }"
            :title="`Cash ISA: ${formatCurrency(status.cash_isa_used)}`"
          >
            <span v-if="status.cash_isa_percent > 10">Cash</span>
          </div>
          <div
            v-if="status.stocks_shares_isa_used > 0"
            class="bg-violet-500 flex items-center justify-center text-xs text-white font-medium"
            :style="{ width: (status.stocks_shares_isa_percent || 0) + '%' }"
            :title="`Stocks ISA: ${formatCurrency(status.stocks_shares_isa_used)}`"
          >
            <span v-if="status.stocks_shares_isa_percent > 10">Stocks</span>
          </div>
          <div
            v-if="status.lisa_used > 0"
            class="bg-violet-500 flex items-center justify-center text-xs text-white font-medium"
            :style="{ width: (status.lisa_percent || 0) + '%' }"
            :title="`Lifetime ISA: ${formatCurrency(status.lisa_used)}`"
          >
            <span v-if="status.lisa_percent > 10">Lifetime</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Breakdown -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
      <div class="text-center p-3 bg-eggshell-500 rounded-lg">
        <p class="text-sm text-neutral-500 mb-1">Cash ISA</p>
        <p class="text-lg font-bold text-violet-700">{{ formatCurrency(status.cash_isa_used || 0) }}</p>
        <p v-if="projectedCashISA > (status.cash_isa_used || 0)" class="text-xs text-neutral-500 mt-1">
          Projected: {{ formatCurrency(projectedCashISA) }}
        </p>
      </div>

      <div class="text-center p-3 bg-eggshell-500 rounded-lg">
        <p class="text-sm text-neutral-500 mb-1">Stocks & Shares ISA</p>
        <p class="text-lg font-bold text-violet-700">{{ formatCurrency(status.stocks_shares_isa_used || 0) }}</p>
        <p v-if="status.lisa_used > 0" class="text-xs text-neutral-500 mt-1">
          Lifetime ISA: {{ formatCurrency(status.lisa_used) }}
        </p>
      </div>

      <div class="text-center p-3 bg-eggshell-500 rounded-lg">
        <p class="text-sm text-neutral-500 mb-1">Remaining</p>
        <p class="text-lg font-bold text-spring-700">{{ formatCurrency(status.remaining || 0) }}</p>
        <p v-if="projectedRemaining !== null && projectedRemaining < (status.remaining || 0)" class="text-xs text-neutral-500 mt-1">
          Projected: {{ formatCurrency(projectedRemaining) }}
        </p>
      </div>
    </div>

    <!-- Info Message -->
    <div class="p-3 bg-eggshell-500 rounded-lg">
      <p class="text-sm text-neutral-500">
        <span class="font-medium">Tax year {{ status.tax_year }}:</span>
        You can save up to {{ formatCurrency(status.total_allowance || 0) }} across all tax-free savings accounts (ISAs).
        Any unused allowance cannot be carried forward to the next year.
      </p>
    </div>
  </div>
</template>

<script>
import { mapState } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'ISAAllowanceTracker',

  mixins: [currencyMixin],

  computed: {
    ...mapState('savings', ['isaAllowance']),

    status() {
      return this.isaAllowance || {};
    },

    projectedCashISA() {
      return Number(this.status.projected_usage?.cash_isa_projected) || 0;
    },

    projectedRemaining() {
      const projected = this.status.projected_usage?.projected_remaining;
      return projected !== undefined ? Number(projected) : null;
    },
  },
};
</script>

<style scoped>
.isa-allowance-tracker {
  /* Custom styling if needed */
}
</style>
