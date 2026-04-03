<template>
  <div class="isa-allowance-summary bg-white rounded-lg shadow-md p-6">
    <div class="flex justify-between items-start mb-4">
      <h3 class="text-xl font-semibold text-horizon-500">ISA Allowance {{ currentTaxYear }}</h3>
      <div class="text-sm text-neutral-500">
        <svg
          xmlns="http://www.w3.org/2000/svg"
          class="h-5 w-5"
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
          />
        </svg>
      </div>
    </div>

    <!-- Total Usage -->
    <div class="mb-6">
      <div class="flex items-baseline justify-between mb-2">
        <span class="text-3xl font-bold text-horizon-500">
          {{ formattedTotalUsed }}
        </span>
        <span class="text-sm text-neutral-500">
          of {{ formattedAllowance }}
        </span>
      </div>
      <p class="text-sm text-neutral-500">Total ISA Subscriptions</p>
    </div>

    <!-- Progress Bar -->
    <div class="mb-6">
      <div class="w-full bg-savannah-200 rounded-full h-4">
        <div
          class="h-4 rounded-full transition-all"
          :class="progressBarClass"
          :style="{ width: Math.min(usagePercent, 100) + '%' }"
        ></div>
      </div>
      <div class="flex justify-between mt-2">
        <span class="text-sm" :class="remainingClass">
          {{ formattedRemaining }} remaining
        </span>
        <span class="text-sm text-neutral-500">{{ usagePercent }}% used</span>
      </div>
    </div>

    <!-- Breakdown -->
    <div class="space-y-3 mb-6">
      <div class="flex justify-between items-center">
        <div class="flex items-center">
          <div class="w-3 h-3 rounded-full bg-violet-500 mr-2"></div>
          <span class="text-sm text-neutral-500">Cash ISA</span>
        </div>
        <span class="text-sm font-semibold text-horizon-500">
          {{ formattedCashISA }}
        </span>
      </div>
      <div class="flex justify-between items-center">
        <div class="flex items-center">
          <div class="w-3 h-3 rounded-full bg-spring-500 mr-2"></div>
          <span class="text-sm text-neutral-500">Stocks & Shares ISA</span>
        </div>
        <span class="text-sm font-semibold text-horizon-500">
          {{ formattedStocksISA }}
        </span>
      </div>
    </div>

    <!-- Manage ISAs Button -->
    <div class="flex space-x-2">
      <button
        @click="navigateToSavings"
        class="flex-1 px-4 py-2 text-sm font-medium text-violet-600 bg-violet-50 rounded-button hover:bg-violet-100 transition-colors"
      >
        Manage Cash ISAs
      </button>
      <button
        @click="navigateToInvestment"
        class="flex-1 px-4 py-2 text-sm font-medium text-spring-600 bg-spring-50 rounded-button hover:bg-spring-100 transition-colors"
      >
        Manage Stocks & Shares ISAs
      </button>
    </div>

    <!-- Warning if over limit -->
    <div
      v-if="isOverLimit"
      class="mt-4 p-3 bg-raspberry-50 rounded-md flex items-start"
    >
      <svg
        xmlns="http://www.w3.org/2000/svg"
        class="h-5 w-5 text-raspberry-600 mr-2 flex-shrink-0 mt-0.5"
        viewBox="0 0 20 20"
        fill="currentColor"
      >
        <path
          fill-rule="evenodd"
          d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
          clip-rule="evenodd"
        />
      </svg>
      <span class="text-sm text-raspberry-800">
        ISA allowance exceeded! You may face tax penalties on excess contributions.
      </span>
    </div>
  </div>
</template>

<script>
import { mapGetters } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';
import { getCurrentTaxYear } from '@/utils/dateFormatter';
import { ISA_ANNUAL_ALLOWANCE } from '@/constants/taxConfig';

export default {
  name: 'ISAAllowanceSummary',
  mixins: [currencyMixin],

  data() {
    return {
      ISA_ALLOWANCE: ISA_ANNUAL_ALLOWANCE, // Fallback from taxConfig.js; prefer API value
    };
  },

  computed: {
    currentTaxYear() {
      return getCurrentTaxYear();
    },

    ...mapGetters('savings', {
      savingsCashISASubscription: 'currentYearISASubscription',
    }),
    ...mapGetters('investment', {
      investmentISASubscription: 'investmentISASubscription',
    }),

    cashISAUsed() {
      return this.savingsCashISASubscription || 0;
    },

    stocksISAUsed() {
      return this.investmentISASubscription || 0;
    },

    totalUsed() {
      return this.cashISAUsed + this.stocksISAUsed;
    },

    remaining() {
      return Math.max(0, this.ISA_ALLOWANCE - this.totalUsed);
    },

    usagePercent() {
      return Math.round((this.totalUsed / this.ISA_ALLOWANCE) * 100);
    },

    isOverLimit() {
      return this.totalUsed > this.ISA_ALLOWANCE;
    },

    formattedAllowance() {
      return this.formatCurrency(this.ISA_ALLOWANCE);
    },

    formattedTotalUsed() {
      return this.formatCurrency(this.totalUsed);
    },

    formattedRemaining() {
      return this.formatCurrency(this.remaining);
    },

    formattedCashISA() {
      return this.formatCurrency(this.cashISAUsed);
    },

    formattedStocksISA() {
      return this.formatCurrency(this.stocksISAUsed);
    },

    progressBarClass() {
      if (this.isOverLimit) return 'bg-raspberry-600';
      if (this.usagePercent >= 90) return 'bg-violet-500';
      if (this.usagePercent >= 75) return 'bg-violet-500';
      return 'bg-spring-600';
    },

    remainingClass() {
      if (this.isOverLimit) return 'text-raspberry-600 font-semibold';
      if (this.remaining < 2000) return 'text-violet-600 font-semibold';
      return 'text-spring-600 font-semibold';
    },
  },

  methods: {
    navigateToSavings() {
      this.$router.push('/savings');
    },

    navigateToInvestment() {
      this.$router.push('/investment');
    },
  },
};
</script>

<style scoped>
.isa-allowance-summary {
  min-width: 280px;
  max-width: 100%;
}

@media (min-width: 640px) {
  .isa-allowance-summary {
    min-width: 320px;
  }
}

@media (min-width: 1024px) {
  .isa-allowance-summary {
    min-width: 360px;
  }
}
</style>
