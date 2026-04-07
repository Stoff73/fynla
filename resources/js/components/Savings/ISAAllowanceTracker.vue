<template>
  <div class="isa-allowance-tracker bg-white rounded-lg border border-light-gray p-6">
    <div class="flex justify-between items-center mb-4">
      <h3 class="text-lg font-semibold text-horizon-500">Tax-Free Savings Allowance {{ currentTaxYear }}</h3>
      <span class="text-sm text-neutral-500">{{ formatCurrency(overallAllowance) }} total</span>
    </div>

    <!-- Lifetime ISA Section (eligible users only) -->
    <div v-if="lisaEligible" class="mb-4 p-3 bg-eggshell-500 rounded-lg border border-light-gray">
      <div class="flex justify-between items-baseline mb-2">
        <span class="text-sm font-semibold text-neutral-500">Lifetime ISA</span>
        <span class="text-xs text-neutral-500">{{ formatCurrency(4000) }} limit</span>
      </div>
      <div class="w-full bg-savannah-200 rounded-full h-2 mb-2">
        <div
          class="h-2 rounded-full transition-all"
          :class="lisaBarClass"
          :style="{ width: Math.min(lisaPercentUsed, 100) + '%' }"
        ></div>
      </div>
      <div class="flex justify-between text-sm">
        <span class="text-neutral-500">{{ formatCurrency(lisaUsed) }} used</span>
        <span class="text-spring-600 font-medium">{{ formatCurrency(lisaRemaining) }} remaining</span>
      </div>
      <div class="text-xs text-neutral-500 mt-1">
        25% bonus: {{ formatCurrency(lisaBonusEarned) }} earned of {{ formatCurrency(1000) }} max
      </div>
    </div>

    <!-- ISA Allowance label when LISA eligible -->
    <div v-if="lisaEligible" class="flex justify-between items-baseline mb-2">
      <span class="text-sm font-semibold text-neutral-500">Other ISAs</span>
      <span class="text-xs text-neutral-500">{{ formatCurrency(totalAllowance) }} limit</span>
    </div>

    <!-- Progress Bar -->
    <div class="mb-4">
      <div class="w-full bg-savannah-200 rounded-full h-4 overflow-hidden">
        <div class="h-full flex">
          <!-- Cash ISA -->
          <div
            v-if="cashISAUsed > 0"
            class="bg-violet-500 flex items-center justify-center text-xs text-white font-medium"
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
      <div class="text-center p-3 bg-eggshell-500 rounded-lg">
        <p class="text-sm text-neutral-500 mb-1">Cash ISA</p>
        <p class="text-lg font-bold text-violet-700">{{ formatCurrency(cashISAUsed) }}</p>
        <p v-if="projectedCashISA > cashISAUsed" class="text-xs text-neutral-500 mt-1">
          Projected: {{ formatCurrency(projectedCashISA) }}
        </p>
      </div>

      <div class="text-center p-3 bg-eggshell-500 rounded-lg">
        <p class="text-sm text-neutral-500 mb-1">Stocks & Shares ISA</p>
        <p class="text-lg font-bold text-purple-700">{{ formatCurrency(stocksISAUsed) }}</p>
      </div>

      <div class="text-center p-3 bg-eggshell-500 rounded-lg">
        <p class="text-sm text-neutral-500 mb-1">Remaining</p>
        <p class="text-lg font-bold text-spring-700">{{ formatCurrency(remaining) }}</p>
        <p v-if="projectedRemaining !== null && projectedRemaining < remaining" class="text-xs text-neutral-500 mt-1">
          Projected: {{ formatCurrency(projectedRemaining) }}
        </p>
      </div>
    </div>

    <!-- Info Message -->
    <div class="p-3 bg-eggshell-500 rounded-lg">
      <p class="text-sm text-neutral-500">
        <span class="font-medium">Tax year {{ currentTaxYear }}:</span>
        <template v-if="lisaEligible">
          You can save up to {{ formatCurrency(overallAllowance) }} across all ISAs ({{ formatCurrency(4000) }} Lifetime ISA + {{ formatCurrency(totalAllowance) }} other ISAs).
        </template>
        <template v-else>
          You can save up to {{ formatCurrency(totalAllowance) }} across all tax-free savings accounts (ISAs).
        </template>
        Any unused allowance cannot be carried forward to the next year.
      </p>
    </div>
  </div>
</template>

<script>
import { mapState, mapGetters } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';
import { ISA_ANNUAL_ALLOWANCE } from '@/constants/taxConfig';

export default {
  name: 'ISAAllowanceTracker',
  mixins: [currencyMixin],

  computed: {
    ...mapState('savings', ['isaAllowance']),
    ...mapGetters('savings', ['isaAllowanceRemaining']),
    ...mapGetters('auth', ['currentUser']),
    ...mapState('netWorth', ['overview']),
    ...mapState('investment', { investmentAccounts: 'accounts' }),

    userAge() {
      const dob = this.currentUser?.date_of_birth;
      if (!dob) return null;
      const birth = new Date(dob);
      const now = new Date();
      let age = now.getFullYear() - birth.getFullYear();
      const m = now.getMonth() - birth.getMonth();
      if (m < 0 || (m === 0 && now.getDate() < birth.getDate())) age--;
      return age;
    },

    lisaEligible() {
      if (this.userAge === null) return false;
      if (this.userAge >= 40) return false;
      const overviewData = this.overview || {};
      return !(overviewData.breakdown?.property > 0);
    },

    // The full ISA allowance from store (API-backed) or fallback
    overallAllowance() {
      return this.isaAllowance?.total_allowance || ISA_ANNUAL_ALLOWANCE;
    },

    // The ISA allowance excluding LISA when LISA-eligible
    totalAllowance() {
      const full = this.overallAllowance;
      return this.lisaEligible ? full - 4000 : full;
    },

    currentTaxYear() {
      const now = new Date();
      const year = now.getFullYear();
      const month = now.getMonth();
      const day = now.getDate();
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

    projectedCashISA() {
      return this.isaAllowance?.projected_usage?.cash_isa_projected || this.cashISAUsed;
    },

    projectedRemaining() {
      const projected = this.isaAllowance?.projected_usage?.projected_remaining;
      return projected !== undefined ? projected : null;
    },

    // LISA computeds
    lisaUsed() {
      const lisaAccounts = (this.investmentAccounts || []).filter(a => {
        const type = (a.account_type || '').toLowerCase();
        return type === 'lisa' || type === 'lifetime_isa';
      });
      const used = lisaAccounts.reduce((sum, a) => {
        return sum + parseFloat(a.isa_subscription_current_year || a.annual_contribution || 0);
      }, 0);
      return Math.min(used, 4000);
    },

    lisaRemaining() {
      return 4000 - this.lisaUsed;
    },

    lisaPercentUsed() {
      return (this.lisaUsed / 4000) * 100;
    },

    lisaBonusEarned() {
      return this.lisaUsed * 0.25;
    },

    lisaBarClass() {
      if (this.lisaPercentUsed >= 95) return 'bg-raspberry-500';
      if (this.lisaPercentUsed >= 75) return 'bg-violet-500';
      return 'bg-spring-500';
    },
  },
};
</script>

<style scoped>
.isa-allowance-tracker {
  /* Custom styling if needed */
}
</style>
