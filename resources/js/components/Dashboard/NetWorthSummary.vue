<template>
  <div class="net-worth-summary bg-white rounded-lg shadow-md p-6">
    <div class="flex justify-between items-start mb-4">
      <h3 class="text-xl font-semibold text-horizon-500">Total Net Worth</h3>
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
            d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
          />
        </svg>
      </div>
    </div>

    <!-- Total Net Worth -->
    <div class="mb-6">
      <div class="flex items-baseline mb-2">
        <span class="text-4xl font-bold text-horizon-500">
          {{ formattedNetWorth }}
        </span>
        <div v-if="trend" class="ml-3 flex items-center">
          <svg
            v-if="trend > 0"
            xmlns="http://www.w3.org/2000/svg"
            class="h-5 w-5 text-spring-600"
            viewBox="0 0 20 20"
            fill="currentColor"
          >
            <path
              fill-rule="evenodd"
              d="M12 7a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0V8.414l-4.293 4.293a1 1 0 01-1.414 0L8 10.414l-4.293 4.293a1 1 0 01-1.414-1.414l5-5a1 1 0 011.414 0L11 10.586 14.586 7H12z"
              clip-rule="evenodd"
            />
          </svg>
          <svg
            v-else-if="trend < 0"
            xmlns="http://www.w3.org/2000/svg"
            class="h-5 w-5 text-raspberry-600"
            viewBox="0 0 20 20"
            fill="currentColor"
          >
            <path
              fill-rule="evenodd"
              d="M12 13a1 1 0 100 2h5a1 1 0 001-1V9a1 1 0 10-2 0v2.586l-4.293-4.293a1 1 0 00-1.414 0L8 9.586 3.707 5.293a1 1 0 00-1.414 1.414l5 5a1 1 0 001.414 0L11 9.414 14.586 13H12z"
              clip-rule="evenodd"
            />
          </svg>
          <span class="text-sm ml-1" :class="trendClass">
            {{ formattedTrend }}
          </span>
        </div>
      </div>
      <p class="text-sm text-neutral-500">Assets minus Liabilities</p>
    </div>

    <!-- Assets Breakdown -->
    <div class="mb-6">
      <h4 class="text-sm font-semibold text-horizon-500 mb-3">Assets</h4>
      <div class="space-y-2">
        <div class="flex justify-between items-center">
          <div class="flex items-center">
            <div class="w-3 h-3 rounded-full bg-violet-500 mr-2"></div>
            <span class="text-sm text-neutral-500">Savings</span>
          </div>
          <span class="text-sm font-semibold text-horizon-500">
            {{ formattedSavings }}
          </span>
        </div>
        <div class="flex justify-between items-center">
          <div class="flex items-center">
            <div class="w-3 h-3 rounded-full bg-spring-500 mr-2"></div>
            <span class="text-sm text-neutral-500">Investments</span>
          </div>
          <span class="text-sm font-semibold text-horizon-500">
            {{ formattedInvestments }}
          </span>
        </div>
        <div class="flex justify-between items-center">
          <div class="flex items-center">
            <div class="w-3 h-3 rounded-full bg-purple-500 mr-2"></div>
            <span class="text-sm text-neutral-500">Pensions</span>
          </div>
          <span class="text-sm font-semibold text-horizon-500">
            {{ formattedPensions }}
          </span>
        </div>
        <div class="flex justify-between items-center">
          <div class="flex items-center">
            <div class="w-3 h-3 rounded-full bg-violet-500 mr-2"></div>
            <span class="text-sm text-neutral-500">Other Assets</span>
          </div>
          <span class="text-sm font-semibold text-horizon-500">
            {{ formattedOtherAssets }}
          </span>
        </div>
        <div class="border-t pt-2 flex justify-between items-center">
          <span class="text-sm font-semibold text-horizon-500">Total Assets</span>
          <span class="text-sm font-bold text-horizon-500">
            {{ formattedTotalAssets }}
          </span>
        </div>
      </div>
    </div>

    <!-- Liabilities -->
    <div class="mb-6">
      <h4 class="text-sm font-semibold text-horizon-500 mb-3">Liabilities</h4>
      <div class="space-y-2">
        <div class="flex justify-between items-center">
          <span class="text-sm text-neutral-500">Total Liabilities</span>
          <span class="text-sm font-semibold text-raspberry-600">
            {{ formattedLiabilities }}
          </span>
        </div>
      </div>
    </div>

    <!-- View Details Button -->
    <button
      @click="navigateToEstate"
      class="w-full px-4 py-2 text-sm font-medium text-horizon-500 bg-savannah-100 rounded-button hover:bg-savannah-200 transition-colors"
    >
      View Detailed Breakdown
    </button>
  </div>
</template>

<script>
import { mapGetters } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'NetWorthSummary',
  mixins: [currencyMixin],

  data() {
    return {
      lastNetWorth: null, // Would be fetched from backend/storage
    };
  },

  computed: {
    ...mapGetters('savings', {
      totalSavings: 'totalSavings',
    }),
    ...mapGetters('investment', {
      totalInvestments: 'totalPortfolioValue',
    }),
    ...mapGetters('retirement', {
      totalPensionWealth: 'totalPensionWealth',
    }),
    ...mapGetters('estate', {
      estateAssets: 'totalAssets',
      estateLiabilities: 'totalLiabilities',
    }),

    savings() {
      return this.totalSavings || 0;
    },

    investments() {
      return this.totalInvestments || 0;
    },

    pensions() {
      return this.totalPensionWealth || 0;
    },

    otherAssets() {
      // Assets from estate module (excluding liquid assets already counted)
      const estateAssetsValue = this.estateAssets || 0;
      // Subtract savings and investments to avoid double counting
      return Math.max(0, estateAssetsValue - this.savings - this.investments);
    },

    totalAssets() {
      return this.savings + this.investments + this.pensions + this.otherAssets;
    },

    liabilities() {
      return this.estateLiabilities || 0;
    },

    netWorth() {
      return this.totalAssets - this.liabilities;
    },

    trend() {
      if (!this.lastNetWorth) return null;
      return this.netWorth - this.lastNetWorth;
    },

    trendPercent() {
      if (!this.lastNetWorth || this.lastNetWorth === 0) return 0;
      return ((this.trend / this.lastNetWorth) * 100).toFixed(1);
    },

    formattedNetWorth() {
      return this.formatCurrency(this.netWorth);
    },

    formattedSavings() {
      return this.formatCurrency(this.savings);
    },

    formattedInvestments() {
      return this.formatCurrency(this.investments);
    },

    formattedPensions() {
      return this.formatCurrency(this.pensions);
    },

    formattedOtherAssets() {
      return this.formatCurrency(this.otherAssets);
    },

    formattedTotalAssets() {
      return this.formatCurrency(this.totalAssets);
    },

    formattedLiabilities() {
      return this.formatCurrency(this.liabilities);
    },

    formattedTrend() {
      const sign = this.trend >= 0 ? '+' : '';
      return `${sign}${this.formatCurrency(Math.abs(this.trend))} (${sign}${this.trendPercent}%)`;
    },

    trendClass() {
      if (this.trend > 0) return 'text-spring-600';
      if (this.trend < 0) return 'text-raspberry-600';
      return 'text-neutral-500';
    },
  },

  methods: {
    navigateToEstate() {
      this.$router.push('/estate');
    },
  },

  mounted() {
    // In a real app, fetch last net worth from backend/localStorage
    // For now, we'll leave it null (no trend display)
    // this.lastNetWorth = localStorage.getItem('lastNetWorth') || null;
  },
};
</script>

<style scoped>
.net-worth-summary {
  min-width: 280px;
  max-width: 100%;
}

@media (min-width: 640px) {
  .net-worth-summary {
    min-width: 320px;
  }
}

@media (min-width: 1024px) {
  .net-worth-summary {
    min-width: 360px;
  }
}
</style>
