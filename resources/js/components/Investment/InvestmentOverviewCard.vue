<template>
  <div
    class="investment-overview-card bg-white rounded-lg shadow-md p-6 cursor-pointer border border-light-gray hover:shadow-lg hover:-translate-y-0.5 hover:bg-light-gray transition-all duration-200"
    @click="navigateToInvestment"
  >
    <div class="flex justify-between items-start mb-4">
      <h3 class="text-xl font-semibold text-horizon-500">Investment</h3>
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
            d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"
          />
        </svg>
      </div>
    </div>

    <!-- Portfolio Value -->
    <div class="mb-6">
      <div class="flex items-baseline mb-2">
        <span class="text-4xl font-bold text-horizon-500">
          {{ formattedPortfolioValue }}
        </span>
      </div>
      <p class="text-sm text-neutral-500">Total Portfolio Value</p>
    </div>

    <!-- YTD Return and Holdings -->
    <div class="grid grid-cols-2 gap-4 mb-4">
      <div>
        <p class="text-sm text-neutral-500 mb-1">YTD Return</p>
        <p class="text-lg font-semibold" :class="returnColour">
          {{ formattedYtdReturn }}
        </p>
      </div>
      <div>
        <p class="text-sm text-neutral-500 mb-1">Holdings</p>
        <p class="text-lg font-semibold text-horizon-500">
          {{ holdingsCount }}
        </p>
      </div>
    </div>

    <!-- Rebalancing Alert / All Good -->
    <div
      v-if="needsRebalancing"
      class="flex items-center p-3 bg-eggshell-500 rounded-md"
    >
      <svg
        xmlns="http://www.w3.org/2000/svg"
        class="h-5 w-5 text-violet-600 mr-2"
        viewBox="0 0 20 20"
        fill="currentColor"
      >
        <path
          fill-rule="evenodd"
          d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
          clip-rule="evenodd"
        />
      </svg>
      <span class="text-sm font-medium text-violet-800">
        Portfolio needs rebalancing
      </span>
    </div>

    <div
      v-else
      class="flex items-center p-3 bg-eggshell-500 rounded-md"
    >
      <svg
        xmlns="http://www.w3.org/2000/svg"
        class="h-5 w-5 text-spring-600 mr-2"
        viewBox="0 0 20 20"
        fill="currentColor"
      >
        <path
          fill-rule="evenodd"
          d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
          clip-rule="evenodd"
        />
      </svg>
      <span class="text-sm font-medium text-spring-800">
        Portfolio well balanced
      </span>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'InvestmentOverviewCard',
  mixins: [currencyMixin],

  props: {
    portfolioValue: {
      type: Number,
      required: true,
      default: 0,
    },
    ytdReturn: {
      type: Number,
      required: true,
      default: 0,
    },
    holdingsCount: {
      type: Number,
      required: true,
      default: 0,
    },
    needsRebalancing: {
      type: Boolean,
      required: false,
      default: false,
    },
  },

  computed: {
    formattedPortfolioValue() {
      return this.formatCurrency(this.portfolioValue);
    },

    formattedYtdReturn() {
      const sign = this.ytdReturn >= 0 ? '+' : '';
      return `${sign}${this.ytdReturn.toFixed(2)}%`;
    },

    returnColour() {
      if (this.ytdReturn >= 5) {
        return 'text-spring-600';
      } else if (this.ytdReturn >= 0) {
        return 'text-spring-500';
      } else if (this.ytdReturn >= -5) {
        return 'text-raspberry-500';
      } else {
        return 'text-raspberry-600';
      }
    },
  },

  methods: {
    navigateToInvestment() {
      this.$router.push('/investment');
    },
  },
};
</script>

<style scoped>
.investment-overview-card {
  min-width: 280px;
  max-width: 100%;
}

@media (min-width: 640px) {
  .investment-overview-card {
    min-width: 320px;
  }
}

@media (min-width: 1024px) {
  .investment-overview-card {
    min-width: 360px;
  }
}
</style>
