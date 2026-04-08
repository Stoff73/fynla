<template>
  <div class="performance">
    <!-- Loading State -->
    <div v-if="loading" class="flex justify-center items-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-raspberry-500"></div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-eggshell-500 rounded-lg p-4 mb-6">
      <div class="flex items-center">
        <svg class="h-5 w-5 text-raspberry-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
        </svg>
        <span class="text-sm font-medium text-raspberry-800">{{ error }}</span>
      </div>
    </div>

    <!-- Empty State - No Accounts -->
    <div v-else-if="!hasAccounts" class="flex flex-col items-center justify-center py-16 px-4">
      <div class="bg-white border-2 border-light-gray rounded-lg p-8 max-w-md w-full text-center shadow-sm">
        <svg class="mx-auto h-16 w-16 text-horizon-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
        </svg>
        <h2 class="text-2xl font-bold text-horizon-500 mb-2">No Performance Data Yet</h2>
        <p class="text-neutral-500 mb-6">
          Add investment accounts to start tracking your portfolio performance
        </p>
        <button
          @click="navigateToTab('accounts')"
          class="px-6 py-3 bg-raspberry-500 text-white rounded-button hover:bg-raspberry-600 transition-colors font-medium"
        >
          Add Investment Account
        </button>
      </div>
    </div>

    <!-- Main Content - Performance Data Exists -->
    <div v-else class="space-y-6">
      <!-- Future Value Projections Section (Clickable) -->
      <div
        class="chart-card border-0"
        @click="goToProjections"
        role="button"
        tabindex="0"
        @keydown.enter="goToProjections"
      >
        <!-- Projections Loading State -->
        <div v-if="projectionsLoading" class="flex justify-center items-center py-8">
          <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-raspberry-500"></div>
          <span class="ml-3 text-neutral-500">Running Monte Carlo simulation...</span>
        </div>

        <!-- Projections Error State -->
        <div v-else-if="projectionsError" class="bg-eggshell-500 rounded-lg p-4">
          <p class="text-sm text-raspberry-800">{{ projectionsError }}</p>
        </div>

        <!-- Portfolio Projection Chart - Matching pension chart style exactly -->
        <div v-else-if="portfolioProjection && selectedProjectionData">
          <!-- Header Row -->
          <div class="chart-header">
            <h3 class="chart-title">Portfolio Projection</h3>
            <div class="chart-header-right">
              <span v-if="portfolioProjection.risk_level" class="risk-badge-corner">
                {{ formatRiskLevel(portfolioProjection.risk_level) }} Risk
              </span>
              <select
                v-model="selectedProjectionYears"
                @click.stop
                @change="loadProjections"
                class="period-selector"
              >
                <option :value="5">5 Years</option>
                <option :value="10">10 Years</option>
                <option :value="20">20 Years</option>
                <option :value="30">30 Years</option>
              </select>
            </div>
          </div>

          <!-- Summary Cards - Compact style matching pension chart -->
          <div class="summary-row">
            <div class="summary-item blue">
              <span class="summary-item-label">Current Portfolio</span>
              <span class="summary-item-value">{{ formatCurrency(totalPortfolioValue) }}</span>
            </div>
            <div class="summary-item purple">
              <span class="summary-item-label">Projected Value (80%)</span>
              <span class="summary-item-value">{{ formatCurrency(selectedProjectionData?.percentiles?.p20) }}</span>
            </div>
          </div>

          <InvestmentProjectionChart
            :data="selectedProjectionData"
            title="Portfolio Value"
            :risk-source="portfolioProjection.risk_source"
            :expected-return="portfolioProjection.expected_return"
            :risk-level="portfolioProjection.risk_level"
            :life-events="portfolioProjections?.life_events_applied || []"
          />
        </div>

        <!-- No projection data yet -->
        <div v-else class="bg-light-blue-100 border border-light-gray rounded-lg p-8 text-center">
          <svg class="mx-auto h-12 w-12 text-horizon-400 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
          </svg>
          <p class="text-sm text-neutral-500">
            Loading projections...
          </p>
        </div>
      </div>

      <!-- Asset Allocation Overview -->
      <div v-if="analysis && analysis.allocation" class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-horizon-500 mb-6">Current Asset Allocation</h3>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div v-for="(value, type) in analysis.allocation" :key="type" class="text-center">
            <div class="text-2xl font-bold text-horizon-500 mb-1">{{ value }}%</div>
            <div class="text-sm text-neutral-500 capitalize">{{ formatAssetType(type) }}</div>
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

import logger from '@/utils/logger';
export default {
  name: 'InvestmentPerformance',

  emits: ['navigate-to-tab'],

  mixins: [currencyMixin],

  components: {
    InvestmentProjectionChart,
  },

  data() {
    return {
      selectedProjectionYears: 10,
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

    portfolioProjection() {
      return this.portfolioProjections?.portfolio;
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
        logger.error('Failed to load projections:', error);
      }
    },

    formatAssetType(type) {
      return type.replace(/_/g, ' ');
    },

    navigateToTab(tabId) {
      this.$emit('navigate-to-tab', tabId);
    },

    goToProjections() {
      const base = this.$route.path.startsWith('/preview') ? '/preview' : '';
      this.$router.push(`${base}/net-worth/investment-detail`);
    },

    formatRiskLevel(level) {
      const levels = {
        low: 'Low',
        lower_medium: 'Lower-Medium',
        medium: 'Medium',
        upper_medium: 'Upper-Medium',
        high: 'High',
      };
      return levels[level] || level || 'Unknown';
    },
  },
};
</script>

<style scoped>
/* Chart styles - matching pension chart exactly */
.chart-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.chart-title {
  font-size: 18px;
  font-weight: 600;
  @apply text-horizon-500;
  margin: 0;
}

.chart-header-right {
  display: flex;
  align-items: center;
  gap: 12px;
}

.risk-badge-corner {
  display: inline-block;
  padding: 4px 10px;
  @apply bg-violet-50;
  @apply text-violet-600;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
}

.period-selector {
  padding: 6px 12px;
  @apply border border-horizon-300;
  border-radius: 6px;
  font-size: 13px;
  @apply text-neutral-500;
  background: white;
  cursor: pointer;
}

.period-selector:focus {
  outline: none;
  @apply border-raspberry-500;
}

/* Summary Row - matching pension chart style exactly */
.summary-row {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 16px;
  margin-bottom: 20px;
}

.summary-item {
  padding: 12px 16px;
  border-radius: 8px;
}

.summary-item.blue {
  @apply bg-violet-50;
}

.summary-item.purple {
  @apply bg-violet-50;
}

.summary-item-label {
  display: block;
  font-size: 12px;
  @apply text-neutral-500;
  margin-bottom: 4px;
}

.summary-item-value {
  font-size: 18px;
  font-weight: 700;
  @apply text-horizon-500;
}

@media (max-width: 640px) {
  .summary-row {
    grid-template-columns: 1fr;
  }

  .chart-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
  }

  .chart-header-right {
    width: 100%;
    justify-content: space-between;
  }
}
</style>
