<template>
  <div class="performance">
    <!-- Loading State -->
    <div v-if="loading" class="flex justify-center items-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-gray-50 rounded-lg p-4 mb-6">
      <div class="flex items-center">
        <svg class="h-5 w-5 text-red-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
        </svg>
        <span class="text-sm font-medium text-red-800">{{ error }}</span>
      </div>
    </div>

    <!-- Empty State - No Accounts -->
    <div v-else-if="!hasAccounts" class="flex flex-col items-center justify-center py-16 px-4">
      <div class="bg-white border-2 border-gray-200 rounded-lg p-8 max-w-md w-full text-center shadow-sm">
        <svg class="mx-auto h-16 w-16 text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
        </svg>
        <h2 class="text-2xl font-bold text-gray-900 mb-2">No Performance Data Yet</h2>
        <p class="text-gray-600 mb-6">
          Add investment accounts to start tracking your portfolio performance
        </p>
        <button
          @click="navigateToTab('accounts')"
          class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium"
        >
          Add Investment Account
        </button>
      </div>
    </div>

    <!-- Main Content - Performance Data Exists -->
    <div v-else class="space-y-6">
      <!-- Future Value Projections Section -->
      <div class="bg-white rounded-lg shadow-md p-6">

        <!-- Projections Loading State -->
        <div v-if="projectionsLoading" class="flex justify-center items-center py-8">
          <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
          <span class="ml-3 text-gray-600">Running Monte Carlo simulation...</span>
        </div>

        <!-- Projections Error State -->
        <div v-else-if="projectionsError" class="bg-gray-50 rounded-lg p-4">
          <p class="text-sm text-red-800">{{ projectionsError }}</p>
        </div>

        <!-- Portfolio Projection Chart -->
        <div v-else-if="portfolioProjection && selectedProjectionData">
          <!-- Summary Cards -->
          <div class="grid grid-cols-2 gap-4 mb-6">
            <!-- Current Portfolio Card -->
            <div class="bg-gray-50 rounded-lg p-4">
              <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Current Portfolio</p>
              <p class="text-2xl font-bold text-gray-900">{{ formatCurrency(totalPortfolioValue) }}</p>
              <p class="text-sm text-gray-500 mt-1">
                <span class="text-green-600 font-medium">+{{ formatCurrency(portfolioProjection.estimated_monthly_contribution) }}</span> /month
              </p>
            </div>

            <!-- Future Value Card -->
            <div class="bg-blue-50 rounded-lg p-4">
              <div class="flex items-center justify-between mb-1">
                <p class="text-xs text-blue-600 uppercase tracking-wide">Projected Value (95%)</p>
                <select
                  v-model="selectedProjectionYears"
                  @change="loadProjections"
                  class="px-2 py-1 text-xs border border-blue-200 rounded bg-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                >
                  <option :value="5">5 Years</option>
                  <option :value="10">10 Years</option>
                  <option :value="20">20 Years</option>
                  <option :value="30">30 Years</option>
                </select>
              </div>
              <p class="text-2xl font-bold text-blue-900">{{ formatCurrency(selectedProjectionData?.percentiles?.p5) }}</p>
              <p class="text-sm text-blue-600 mt-1">
                in {{ selectedProjectionYears }} years
              </p>
            </div>
          </div>

          <InvestmentProjectionChart
            :data="selectedProjectionData"
            title="Portfolio Value"
          />

          <!-- Per-Account Projections -->
          <div v-if="accountProjections && accountProjections.length > 1" class="mt-8">
            <button
              @click="showAccountProjections = !showAccountProjections"
              class="flex items-center gap-2 text-md font-medium text-gray-700 hover:text-gray-900"
            >
              <svg
                class="w-5 h-5 transition-transform"
                :class="{ 'rotate-90': showAccountProjections }"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
              Per-Account Projections ({{ accountProjections.length }} accounts)
            </button>

            <div v-if="showAccountProjections" class="mt-4 grid grid-cols-1 lg:grid-cols-2 gap-6">
              <div
                v-for="account in accountProjections"
                :key="account.account_id"
                class="border border-gray-200 rounded-lg p-4"
              >
                <div class="flex items-center justify-between mb-3">
                  <h5 class="font-medium text-gray-800">{{ account.account_name }}</h5>
                  <span class="text-sm text-gray-500">{{ formatCurrency(account.current_value) }}</span>
                </div>

                <InvestmentProjectionChart
                  v-if="account.projections[selectedProjectionYears]"
                  :data="account.projections[selectedProjectionYears]"
                  :title="account.account_name"
                  :compact="true"
                />

                <div class="mt-3 flex justify-between text-sm">
                  <div>
                    <span class="text-gray-500">Median in {{ selectedProjectionYears }} years:</span>
                    <span class="font-medium text-gray-900 ml-1">
                      {{ formatCurrency(account.projections[selectedProjectionYears]?.median_value) }}
                    </span>
                  </div>
                  <div>
                    <span class="text-gray-500">Est. contribution:</span>
                    <span class="font-medium text-gray-900 ml-1">
                      {{ formatCurrency(account.estimated_monthly_contribution) }}/mo
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- No projection data yet -->
        <div v-else class="bg-gray-50 border-2 border-dashed border-gray-300 rounded-lg p-8 text-center">
          <svg class="mx-auto h-12 w-12 text-gray-400 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
          </svg>
          <p class="text-sm text-gray-600">
            Loading projections...
          </p>
        </div>
      </div>

      <!-- Asset Allocation Overview -->
      <div v-if="analysis && analysis.allocation" class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-6">Current Asset Allocation</h3>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div v-for="(value, type) in analysis.allocation" :key="type" class="text-center">
            <div class="text-2xl font-bold text-gray-800 mb-1">{{ value }}%</div>
            <div class="text-sm text-gray-600 capitalize">{{ formatAssetType(type) }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { mapState, mapGetters, mapActions } from 'vuex';
import InvestmentProjectionChart from './InvestmentProjectionChart.vue';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'Performance',

  mixins: [currencyMixin],

  components: {
    InvestmentProjectionChart,
  },

  data() {
    return {
      selectedProjectionYears: 10,
      showAccountProjections: false,
    };
  },

  computed: {
    ...mapState('investment', [
      'accounts',
      'holdings',
      'analysis',
      'loading',
      'error',
      'portfolioProjections',
      'projectionsLoading',
      'projectionsError',
    ]),
    ...mapGetters('investment', ['totalPortfolioValue']),

    hasAccounts() {
      return this.accounts && this.accounts.length > 0;
    },

    hasHoldings() {
      return this.holdings && this.holdings.length > 0;
    },

    accountCount() {
      return this.accounts ? this.accounts.length : 0;
    },

    holdingsCount() {
      return this.holdings ? this.holdings.length : 0;
    },

    portfolioHealthScore() {
      if (this.analysis && this.analysis.portfolio_health_score !== undefined) {
        return this.analysis.portfolio_health_score;
      }
      // Default score based on basic diversification
      if (this.holdingsCount < 3) return 45;
      if (this.holdingsCount < 5) return 60;
      if (this.holdingsCount < 10) return 75;
      return 85;
    },

    portfolioHealthColour() {
      const score = this.portfolioHealthScore;
      if (score >= 80) return 'text-green-600';
      if (score >= 60) return 'text-yellow-600';
      return 'text-red-600';
    },

    diversificationScore() {
      if (this.analysis && this.analysis.diversification_score !== undefined) {
        return Math.round(this.analysis.diversification_score);
      }
      // Basic diversification based on holdings count
      const count = this.holdingsCount;
      if (count >= 10) return 90;
      if (count >= 7) return 75;
      if (count >= 5) return 60;
      if (count >= 3) return 45;
      return 25;
    },

    diversificationColour() {
      const score = this.diversificationScore;
      if (score >= 70) return 'text-green-600';
      if (score >= 50) return 'text-yellow-600';
      return 'text-red-600';
    },

    portfolioProjection() {
      return this.portfolioProjections?.portfolio;
    },

    accountProjections() {
      return this.portfolioProjections?.accounts || [];
    },

    selectedProjectionData() {
      if (!this.portfolioProjection?.projections) return null;
      return this.portfolioProjection.projections[this.selectedProjectionYears];
    },
  },

  async mounted() {
    if (this.hasAccounts) {
      await this.loadProjections();
    }
  },

  watch: {
    hasAccounts(newVal) {
      if (newVal && !this.portfolioProjections) {
        this.loadProjections();
      }
    },
  },

  methods: {
    ...mapActions('investment', ['fetchPortfolioProjections']),

    async loadProjections() {
      try {
        await this.fetchPortfolioProjections({
          selectedPeriod: this.selectedProjectionYears,
        });
      } catch (error) {
        console.error('Failed to load projections:', error);
      }
    },

    formatAssetType(type) {
      return type.replace(/_/g, ' ');
    },

    navigateToTab(tabId) {
      this.$emit('navigate-to-tab', tabId);
    },
  },
};
</script>

<style scoped>
/* Add any specific styles here */
</style>
