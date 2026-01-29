<template>
  <div class="portfolio-analysis relative">
    <!-- Coming Soon Watermark -->
    <div class="absolute inset-0 flex items-center justify-center z-10 pointer-events-none">
      <div class="bg-orange-100 border-2 border-orange-400 rounded-lg px-8 py-4 transform -rotate-12 shadow-lg">
        <p class="text-2xl font-bold text-orange-700">Coming Soon</p>
      </div>
    </div>

    <div class="mb-6 opacity-50">
      <h2 class="text-2xl font-bold text-gray-900">Portfolio Analysis</h2>
      <p class="text-gray-600 mt-1">Advanced portfolio optimisation for your DC pension holdings</p>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="flex justify-center items-center py-12 opacity-50">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600"></div>
      <span class="ml-3 text-gray-600">Loading portfolio analysis...</span>
    </div>

    <!-- No Portfolio Data State -->
    <div v-else-if="!hasPortfolioData" class="bg-blue-50 border border-blue-200 rounded-lg p-8 text-center opacity-50">
      <svg class="mx-auto h-16 w-16 text-blue-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
      </svg>
      <h3 class="text-lg font-semibold text-gray-900 mb-2">No Holdings Data</h3>
      <p class="text-gray-600 mb-4">
        Add individual holdings to your DC pensions to unlock advanced portfolio analysis including risk metrics,
        diversification scoring, and fee optimisation.
      </p>
      <router-link
        to="/retirement?tab=inventory"
        class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors font-medium"
      >
        Go to Pension Inventory
        <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
      </router-link>
    </div>

    <!-- Portfolio Analysis Content -->
    <div v-else class="space-y-8 opacity-50">
      <!-- Portfolio Summary -->
      <div class="bg-gradient-to-br from-indigo-50 to-purple-50 rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Portfolio Summary</h3>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div class="bg-white rounded-lg p-4 shadow-sm">
            <p class="text-xs text-gray-600 mb-1">Total Value</p>
            <p class="text-2xl font-bold text-gray-900">£{{ formatNumber(portfolioSummary.total_value) }}</p>
          </div>
          <div class="bg-white rounded-lg p-4 shadow-sm">
            <p class="text-xs text-gray-600 mb-1">Pensions</p>
            <p class="text-2xl font-bold text-gray-900">{{ portfolioSummary.pensions_count }}</p>
          </div>
          <div class="bg-white rounded-lg p-4 shadow-sm">
            <p class="text-xs text-gray-600 mb-1">Holdings</p>
            <p class="text-2xl font-bold text-gray-900">{{ portfolioSummary.holdings_count }}</p>
          </div>
          <div class="bg-white rounded-lg p-4 shadow-sm">
            <p class="text-xs text-gray-600 mb-1">Diversification</p>
            <p class="text-2xl font-bold" :class="getDiversificationColour(diversificationScore)">
              {{ diversificationScore }}/100
            </p>
          </div>
        </div>
      </div>

      <!-- Risk Metrics -->
      <div v-if="riskMetrics" class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Risk-Adjusted Metrics</h3>
        <div class="bg-gradient-to-br from-purple-50 to-indigo-50 border border-purple-200 rounded-lg p-6">
          <div class="grid grid-cols-2 md:grid-cols-3 gap-6">
            <div class="bg-white rounded-lg p-4 shadow-sm">
              <p class="text-xs text-gray-600 mb-1">Alpha</p>
              <p class="text-2xl font-bold" :class="getReturnColour(riskMetrics.alpha)">
                {{ formatPercentage(riskMetrics.alpha) }}%
              </p>
              <p class="text-xs text-gray-500 mt-1">Excess return vs benchmark</p>
            </div>
            <div class="bg-white rounded-lg p-4 shadow-sm">
              <p class="text-xs text-gray-600 mb-1">Beta</p>
              <p class="text-2xl font-bold text-gray-900">
                {{ formatNumber(riskMetrics.beta) || 'N/A' }}
              </p>
              <p class="text-xs text-gray-500 mt-1">Market sensitivity</p>
            </div>
            <div class="bg-white rounded-lg p-4 shadow-sm">
              <p class="text-xs text-gray-600 mb-1">Sharpe Ratio</p>
              <p class="text-2xl font-bold text-gray-900">
                {{ formatNumber(riskMetrics.sharpe_ratio) || 'N/A' }}
              </p>
              <p class="text-xs text-gray-500 mt-1">Risk-adjusted return</p>
            </div>
            <div class="bg-white rounded-lg p-4 shadow-sm">
              <p class="text-xs text-gray-600 mb-1">Volatility</p>
              <p class="text-2xl font-bold text-orange-600">
                {{ formatPercentage(riskMetrics.volatility) }}%
              </p>
              <p class="text-xs text-gray-500 mt-1">Standard deviation</p>
            </div>
            <div class="bg-white rounded-lg p-4 shadow-sm">
              <p class="text-xs text-gray-600 mb-1">Max Drawdown</p>
              <p class="text-2xl font-bold text-red-600">
                {{ formatPercentage(riskMetrics.max_drawdown) }}%
              </p>
              <p class="text-xs text-gray-500 mt-1">Largest decline</p>
            </div>
            <div class="bg-white rounded-lg p-4 shadow-sm">
              <p class="text-xs text-gray-600 mb-1">VaR (95%)</p>
              <p class="text-2xl font-bold text-gray-900">
                {{ formatPercentage(riskMetrics.var_95) }}%
              </p>
              <p class="text-xs text-gray-500 mt-1">Value at Risk</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Asset Allocation -->
      <div v-if="assetAllocation" class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Asset Allocation</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div
            v-for="(value, assetClass) in assetAllocation"
            :key="assetClass"
            class="bg-gray-50 rounded-lg p-4"
          >
            <p class="text-xs text-gray-600 mb-1 capitalize">{{ formatAssetClass(assetClass) }}</p>
            <p class="text-xl font-semibold text-gray-900">{{ formatPercentage(value) }}%</p>
            <p class="text-xs text-gray-500 mt-1">£{{ formatNumber(portfolioTotalValue * value / 100) }}</p>
          </div>
        </div>
      </div>

      <!-- Fee Analysis -->
      <div v-if="feeAnalysis" class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Fee Analysis</h3>
        <div class="bg-gradient-to-br from-orange-50 to-orange-50 border border-orange-200 rounded-lg p-6">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="bg-white rounded-lg p-4 shadow-sm">
              <p class="text-xs text-gray-600 mb-1">Total Annual Fees</p>
              <p class="text-2xl font-bold text-gray-900">£{{ formatNumber(feeAnalysis.total_annual_fees) }}</p>
              <p class="text-xs text-gray-500 mt-1">{{ formatPercentage(feeAnalysis.fee_percentage) }}% of portfolio</p>
            </div>
            <div class="bg-white rounded-lg p-4 shadow-sm">
              <p class="text-xs text-gray-600 mb-1">Platform Fees</p>
              <p class="text-xl font-semibold text-gray-900">£{{ formatNumber(feeAnalysis.platform_fees) }}</p>
            </div>
            <div class="bg-white rounded-lg p-4 shadow-sm">
              <p class="text-xs text-gray-600 mb-1">Fund OCF Fees</p>
              <p class="text-xl font-semibold text-gray-900">£{{ formatNumber(feeAnalysis.fund_ocf_fees) }}</p>
            </div>
          </div>

          <div v-if="feeAnalysis.low_cost_comparison" class="bg-white rounded-lg p-4 shadow-sm">
            <h4 class="text-sm font-semibold text-gray-900 mb-3">Low-Cost Comparison</h4>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <p class="text-xs text-gray-600 mb-1">Low-Cost Equivalent (0.20%)</p>
                <p class="text-lg font-semibold text-green-600">£{{ formatNumber(feeAnalysis.low_cost_comparison.low_cost_equivalent) }}</p>
              </div>
              <div>
                <p class="text-xs text-gray-600 mb-1">Potential Annual Saving</p>
                <p class="text-lg font-semibold text-orange-600">£{{ formatNumber(feeAnalysis.low_cost_comparison.potential_annual_saving) }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Advanced Analytics Links -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Monte Carlo -->
        <div class="bg-gradient-to-br from-blue-50 to-cyan-50 border border-blue-200 rounded-lg p-6">
          <div class="text-center py-4">
            <svg class="mx-auto h-12 w-12 text-blue-400 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
            <h4 class="text-lg font-semibold text-gray-900 mb-2">Monte Carlo Simulation</h4>
            <p class="text-sm text-gray-600 mb-4">
              Probabilistic projections of your pension portfolio's future performance
            </p>
            <router-link
              to="/investment?tab=scenarios"
              class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium text-sm"
            >
              View Simulations
              <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
            </router-link>
          </div>
        </div>

        <!-- Efficient Frontier -->
        <div class="bg-gradient-to-br from-green-50 to-emerald-50 border border-green-200 rounded-lg p-6">
          <div class="text-center py-4">
            <svg class="mx-auto h-12 w-12 text-green-400 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
            </svg>
            <h4 class="text-lg font-semibold text-gray-900 mb-2">Efficient Frontier</h4>
            <p class="text-sm text-gray-600 mb-4">
              Optimal risk-return trade-offs for your pension portfolio allocation
            </p>
            <router-link
              to="/investment?tab=optimization"
              class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors font-medium text-sm"
            >
              View Analysis
              <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
            </router-link>
          </div>
        </div>
      </div>

      <!-- Pensions Breakdown -->
      <div v-if="pensionsWithHoldings.length > 0" class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Holdings by Pension</h3>
        <div class="space-y-4">
          <div
            v-for="pension in pensionsWithHoldings"
            :key="pension.id"
            class="border border-gray-200 rounded-lg p-4"
          >
            <div class="flex justify-between items-start mb-3">
              <div>
                <h4 class="font-semibold text-gray-900">{{ pension.scheme_name }}</h4>
                <p class="text-sm text-gray-600">{{ pension.provider }} • {{ pension.scheme_type }}</p>
              </div>
              <div class="text-right">
                <p class="text-lg font-semibold text-gray-900">£{{ formatNumber(pension.total_value) }}</p>
                <p class="text-xs text-gray-600">{{ pension.holdings_count }} holdings</p>
              </div>
            </div>
            <div v-if="pension.holdings.length > 0" class="space-y-2">
              <div
                v-for="holding in pension.holdings"
                :key="holding.id"
                class="flex justify-between items-center text-sm py-2 border-t border-gray-100"
              >
                <div>
                  <span class="font-medium text-gray-900">{{ holding.security_name }}</span>
                  <span v-if="holding.ticker" class="text-gray-600 ml-2">{{ holding.ticker }}</span>
                  <span class="text-gray-500 ml-2 text-xs capitalize">{{ holding.asset_type }}</span>
                </div>
                <div class="text-right">
                  <p class="font-medium text-gray-900">£{{ formatNumber(holding.current_value) }}</p>
                  <p v-if="holding.allocation_percent" class="text-xs text-gray-600">
                    {{ formatPercentage(holding.allocation_percent) }}%
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { mapGetters } from 'vuex';

export default {
  name: 'PortfolioAnalysis',

  data() {
    return {
      loading: false,
    };
  },

  computed: {
    ...mapGetters('retirement', [
      'hasPortfolioData',
      'portfolioTotalValue',
      'portfolioRiskMetrics',
      'portfolioAssetAllocation',
      'portfolioDiversificationScore',
      'portfolioFeeAnalysis',
      'pensionsWithHoldings',
    ]),

    portfolioSummary() {
      return this.$store.state.retirement.portfolioAnalysis?.portfolio_summary || {};
    },

    riskMetrics() {
      return this.portfolioRiskMetrics;
    },

    assetAllocation() {
      return this.portfolioAssetAllocation;
    },

    diversificationScore() {
      return this.portfolioDiversificationScore;
    },

    feeAnalysis() {
      return this.portfolioFeeAnalysis;
    },
  },

  async mounted() {
    await this.loadPortfolioAnalysis();
  },

  methods: {
    async loadPortfolioAnalysis() {
      this.loading = true;
      try {
        await this.$store.dispatch('retirement/fetchPortfolioAnalysis');
      } catch (error) {
        console.error('Failed to load portfolio analysis:', error);
      } finally {
        this.loading = false;
      }
    },

    formatNumber(value) {
      if (value === null || value === undefined) return '0';
      return Number(value).toLocaleString('en-GB', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
      });
    },

    formatPercentage(value) {
      if (value === null || value === undefined) return '0.00';
      return Number(value).toFixed(2);
    },

    formatAssetClass(assetClass) {
      const labels = {
        equity: 'Equity',
        bond: 'Bonds',
        cash: 'Cash',
        property: 'Property',
        alternative: 'Alternative',
        uk_equity: 'UK Equity',
        us_equity: 'US Equity',
        international_equity: 'International Equity',
      };
      return labels[assetClass] || assetClass;
    },

    getDiversificationColour(score) {
      if (score >= 80) return 'text-green-600';
      if (score >= 60) return 'text-yellow-600';
      return 'text-orange-600';
    },

    getReturnColour(value) {
      if (value === null || value === undefined) return 'text-gray-600';
      return value >= 0 ? 'text-green-600' : 'text-red-600';
    },
  },
};
</script>

<style scoped>
/* Additional styles if needed */
</style>
