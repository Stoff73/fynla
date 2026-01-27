<template>
  <div class="investment-detail">
    <!-- Header -->
    <div class="page-header">
      <button @click="goBack" class="back-button">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="back-icon">
          <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
        </svg>
        Back to Investments
      </button>
      <h1 class="page-title">Investment Detail</h1>
      <p class="page-subtitle">Portfolio projections and analysis summary</p>
    </div>

    <!-- Loading State -->
    <div v-if="projectionsLoading" class="loading-state">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
      <p class="mt-4 text-gray-600">Loading investment data...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="projectionsError" class="error-state">
      <p class="text-red-600">{{ projectionsError }}</p>
      <button @click="loadProjections" class="retry-button">Try Again</button>
    </div>

    <!-- Main Content -->
    <div v-else-if="portfolioProjection" class="content">
      <!-- Two Column Layout: Projection + Analysis Summaries -->
      <div class="top-section">
        <!-- Left: Portfolio Projection - Matching pension chart style -->
        <div class="chart-card">
          <div class="chart-header">
            <h3 class="chart-title">Portfolio Projection</h3>
            <div class="chart-header-right">
              <span v-if="portfolioProjection.risk_level" class="risk-badge-corner">
                {{ formatRiskLevel(portfolioProjection.risk_level) }} Risk
              </span>
              <select
                v-model="selectedProjectionYears"
                @change="onPeriodChange"
                class="period-selector"
              >
                <option :value="5">5 Years</option>
                <option :value="10">10 Years</option>
                <option :value="20">20 Years</option>
                <option :value="30">30 Years</option>
              </select>
            </div>
          </div>
          <div class="summary-row">
            <div class="summary-item blue">
              <span class="summary-item-label">Current Portfolio</span>
              <span class="summary-item-value">{{ formatCurrency(totalPortfolioValue) }}</span>
            </div>
            <div class="summary-item purple">
              <span class="summary-item-label">Projected Value (95%)</span>
              <span class="summary-item-value">{{ formatCurrency(selectedProjectionData?.percentiles?.p5) }}</span>
            </div>
          </div>
          <InvestmentProjectionChart
            v-if="selectedProjectionData"
            :data="selectedProjectionData"
            title="Portfolio Value"
            :risk-source="portfolioProjection.risk_source"
            :expected-return="portfolioProjection.expected_return"
            :risk-level="portfolioProjection.risk_level"
          />
        </div>

        <!-- Right: Analysis Summaries -->
        <div class="analysis-panels">
          <!-- Tax Efficiency Summary -->
          <div class="analysis-card clickable-card" @click="goToDetailPage('tax-efficiency')">
            <div class="analysis-header">
              <h3 class="analysis-title">Tax Efficiency</h3>
              <span :class="['score-badge', taxEfficiencyScoreClass]">
                {{ taxEfficiencyScore }}%
              </span>
            </div>
            <div class="analysis-content">
              <div class="analysis-row">
                <span class="row-label">Tax-Sheltered</span>
                <span class="row-value">{{ formatCurrency(taxShelteredValue) }}</span>
              </div>
              <div class="analysis-row">
                <span class="row-label">Taxable (GIA)</span>
                <span class="row-value">{{ formatCurrency(taxableValue) }}</span>
              </div>
              <div class="analysis-row">
                <span class="row-label">Unrealised Gains</span>
                <span class="row-value" :class="unrealisedGains >= 0 ? 'text-green-600' : 'text-red-600'">
                  {{ formatCurrency(unrealisedGains) }}
                </span>
              </div>
            </div>
          </div>

          <!-- Holdings Summary -->
          <div class="analysis-card holdings-card clickable-card" @click="goToDetailPage('holdings-detail')">
            <div class="analysis-header">
              <h3 class="analysis-title">{{ accountCount > 1 ? 'Holdings (across all accounts)' : 'Holdings' }}</h3>
            </div>
            <div class="holdings-content">
              <div v-if="analysisLoading" class="allocation-loading">
                <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600 mx-auto"></div>
              </div>
              <div v-else-if="hasAllocationData" class="donut-container">
                <apexchart
                  type="donut"
                  :options="allocationChartOptions"
                  :series="allocationSeries"
                  height="140"
                />
              </div>
              <div v-else class="no-allocation">
                <span>Set asset types on holdings to see allocation</span>
              </div>
            </div>
          </div>

          <!-- Fees Summary -->
          <div class="analysis-card clickable-card" @click="goToDetailPage('fees-detail')">
            <div class="analysis-header">
              <h3 class="analysis-title">Fees</h3>
              <span class="fee-badge">{{ totalFeePercent.toFixed(2) }}% TER</span>
            </div>
            <div class="analysis-content">
              <div class="analysis-row">
                <span class="row-label">Annual Fees</span>
                <span class="row-value text-amber-600">{{ formatCurrency(totalAnnualFees) }}/yr</span>
              </div>
              <div class="analysis-row">
                <span class="row-label">Fee Drag (10yr)</span>
                <span class="row-value">{{ formatCurrency(feeDrag10Year) }}</span>
              </div>
              <div class="analysis-row">
                <span class="row-label">Avg Platform Fee</span>
                <span class="row-value">{{ avgPlatformFee.toFixed(2) }}%</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Per-Account Projections -->
      <div v-if="accountProjections && accountProjections.length > 0" class="accounts-section">
        <h2 class="section-title">Individual Account Projections</h2>
        <p class="section-subtitle">Projections for each of your {{ accountProjections.length }} investment accounts</p>

        <div class="accounts-grid">
          <div
            v-for="account in accountProjections"
            :key="account.account_id"
            class="account-card"
          >
            <div class="account-header">
              <div>
                <h3 class="account-name">{{ account.account_name }}</h3>
                <span :class="['account-type-badge', accountTypeBadgeClass(account.account_type)]">
                  {{ formatAccountType(account.account_type) }}
                </span>
              </div>
              <div class="account-value">
                <span class="value-label">Current</span>
                <span class="value-amount">{{ formatCurrency(account.current_value) }}</span>
              </div>
            </div>

            <div class="account-stats">
              <div class="stat">
                <span class="stat-label">Monthly contribution</span>
                <span class="stat-value">{{ formatCurrency(account.estimated_monthly_contribution) }}</span>
              </div>
              <div class="stat">
                <span class="stat-label">Projected ({{ selectedProjectionYears }}yr)</span>
                <span class="stat-value text-blue-600">{{ formatCurrency(account.projections[selectedProjectionYears]?.median_value) }}</span>
              </div>
            </div>

            <div class="account-chart">
              <InvestmentProjectionChart
                v-if="account.projections[selectedProjectionYears]"
                :data="account.projections[selectedProjectionYears]"
                :title="account.account_name"
                :compact="true"
              />
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- No Data State -->
    <div v-else class="empty-state">
      <svg class="empty-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
      </svg>
      <p class="empty-text">No investment data available</p>
      <p class="empty-subtext">Add investment accounts to see portfolio analysis</p>
    </div>
  </div>
</template>

<script>
import { mapState, mapGetters, mapActions } from 'vuex';
import VueApexCharts from 'vue3-apexcharts';
import InvestmentProjectionChart from '@/components/Investment/InvestmentProjectionChart.vue';
import { currencyMixin } from '@/mixins/currencyMixin';
import { CHART_COLORS } from '@/constants/designSystem';

export default {
  name: 'InvestmentProjections',

  mixins: [currencyMixin],

  components: {
    InvestmentProjectionChart,
    apexchart: VueApexCharts,
  },

  data() {
    return {
      selectedProjectionYears: 10,
      analysisLoading: false,
    };
  },

  computed: {
    ...mapState('investment', [
      'accounts',
      'analysis',
      'portfolioProjections',
      'projectionsLoading',
      'projectionsError',
    ]),
    ...mapGetters('investment', [
      'totalPortfolioValue',
      'holdingsCount',
      'assetAllocation',
      'diversificationScore',
      'taxEfficiencyScore',
      'unrealisedGains',
      'totalFees',
    ]),

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

    isPreviewMode() {
      return this.$route.path.startsWith('/preview');
    },

    // Tax Efficiency
    taxEfficiencyScoreClass() {
      const score = this.taxEfficiencyScore;
      if (score >= 80) return 'score-excellent';
      if (score >= 60) return 'score-good';
      if (score >= 40) return 'score-fair';
      return 'score-poor';
    },

    taxShelteredValue() {
      // Sum of ISA and SIPP values
      if (!this.accounts) return 0;
      return this.accounts
        .filter(a => ['isa', 'sipp', 'pension'].includes(a.account_type))
        .reduce((sum, a) => sum + (parseFloat(a.current_value) || 0), 0);
    },

    taxableValue() {
      // Sum of GIA values
      if (!this.accounts) return 0;
      return this.accounts
        .filter(a => a.account_type === 'gia')
        .reduce((sum, a) => sum + (parseFloat(a.current_value) || 0), 0);
    },

    // Holdings
    accountCount() {
      return this.accounts?.length || 0;
    },

    // Check if analysis is loaded and has asset allocation
    isAnalysisReady() {
      return this.analysis && this.analysis.asset_allocation && this.analysis.asset_allocation.length > 0;
    },

    diversificationScoreClass() {
      const score = this.diversificationScore;
      if (score >= 70) return 'text-green-600';
      if (score >= 50) return 'text-amber-600';
      return 'text-red-600';
    },

    topAssetTypes() {
      if (!this.hasAllocationData) return {};
      // Array format from backend: [{ asset_type, percentage, value, count }]
      const allocation = this.analysis.asset_allocation;
      const sorted = [...allocation].sort((a, b) =>
        (b.percentage || 0) - (a.percentage || 0)
      );
      const result = {};
      sorted.slice(0, 3).forEach(item => {
        result[item.asset_type || 'Unknown'] = item.percentage || 0;
      });
      return result;
    },

    // Donut chart for holdings
    hasAllocationData() {
      // Check directly on analysis state rather than getter (which defaults to [])
      return this.isAnalysisReady;
    },

    allocationSeries() {
      if (!this.hasAllocationData) return [];
      // Array format from backend: [{ asset_type, percentage, value, count }]
      const allocation = this.analysis.asset_allocation;
      return allocation.map(item => item.percentage || 0);
    },

    allocationLabels() {
      if (!this.hasAllocationData) return [];
      // Array format from backend: [{ asset_type, percentage, value, count }]
      const allocation = this.analysis.asset_allocation;
      return allocation.map(item => this.formatAssetType(item.asset_type || 'Unknown'));
    },

    allocationChartOptions() {
      const labels = this.allocationLabels;

      return {
        chart: {
          type: 'donut',
          fontFamily: 'Inter, system-ui, sans-serif',
          toolbar: { show: false },
        },
        labels: labels,
        colors: CHART_COLORS,
        plotOptions: {
          pie: {
            donut: {
              size: '60%',
              labels: {
                show: false,
              },
            },
          },
        },
        dataLabels: { enabled: false },
        legend: {
          show: true,
          position: 'right',
          fontSize: '11px',
          fontWeight: 500,
          markers: { width: 8, height: 8, radius: 2 },
          itemMargin: { horizontal: 0, vertical: 2 },
          formatter: (seriesName, opts) => {
            const value = opts.w.globals.series[opts.seriesIndex];
            return `${seriesName} ${value.toFixed(0)}%`;
          },
        },
        tooltip: {
          enabled: true,
          y: {
            formatter: (val) => `${val.toFixed(1)}%`,
          },
        },
        stroke: {
          width: 1,
          colors: ['#fff'],
        },
      };
    },

    // Fees
    totalAnnualFees() {
      return this.analysis?.fee_analysis?.total_annual_fees || 0;
    },

    totalFeePercent() {
      if (!this.totalPortfolioValue || this.totalPortfolioValue === 0) return 0;
      return (this.totalAnnualFees / this.totalPortfolioValue) * 100;
    },

    feeDrag10Year() {
      // Estimate 10-year fee drag using compound effect
      const annualFees = this.totalAnnualFees;
      const feePercent = this.totalFeePercent / 100;
      const years = 10;
      // Simple approximation: fees * years * (1 + avg growth impact)
      return annualFees * years * 1.5;
    },

    avgPlatformFee() {
      if (!this.accounts || this.accounts.length === 0) return 0;
      const totalFees = this.accounts.reduce((sum, a) => {
        if (a.platform_fee_type === 'fixed') {
          const amount = parseFloat(a.platform_fee_amount) || 0;
          let annual = amount;
          if (a.platform_fee_frequency === 'monthly') annual = amount * 12;
          else if (a.platform_fee_frequency === 'quarterly') annual = amount * 4;
          const value = parseFloat(a.current_value) || 0;
          return sum + (value > 0 ? (annual / value) * 100 : 0);
        }
        return sum + (parseFloat(a.platform_fee_percent) || 0);
      }, 0);
      return totalFees / this.accounts.length;
    },
  },

  async mounted() {
    // Load investment data and analysis if not already loaded
    if (!this.accounts || this.accounts.length === 0) {
      await this.fetchInvestmentData();
    }
    // Always ensure analysis is loaded (contains asset allocation for donut chart)
    if (!this.analysis || !this.analysis.asset_allocation) {
      this.analysisLoading = true;
      try {
        await this.analyseInvestment();
      } catch (err) {
        console.error('Failed to load analysis:', err);
      } finally {
        this.analysisLoading = false;
      }
    }
    // Load projections
    await this.loadProjections();
  },

  methods: {
    ...mapActions('investment', ['fetchInvestmentData', 'fetchPortfolioProjections', 'analyseInvestment']),

    goBack() {
      const base = this.isPreviewMode ? '/preview' : '';
      this.$router.push(`${base}/net-worth/investments`);
    },

    goToDetailPage(page) {
      const base = this.isPreviewMode ? '/preview' : '';
      this.$router.push(`${base}/net-worth/${page}`);
    },

    async loadProjections() {
      try {
        await this.fetchPortfolioProjections({
          selectedPeriod: this.selectedProjectionYears,
        });
      } catch (error) {
        console.error('Failed to load projections:', error);
      }
    },

    onPeriodChange() {
      this.loadProjections();
    },

    formatRiskLevel(level) {
      const levels = {
        low: 'Low',
        lower_medium: 'Lower Medium',
        medium: 'Medium',
        upper_medium: 'Upper Medium',
        high: 'High',
      };
      return levels[level] || level;
    },

    formatAccountType(type) {
      const types = {
        isa: 'Stocks & Shares ISA',
        sipp: 'SIPP',
        gia: 'General Investment',
        pension: 'Pension',
        other: 'Other',
      };
      return types[type] || type;
    },

    formatAssetType(type) {
      return type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    },

    accountTypeBadgeClass(type) {
      const classes = {
        isa: 'badge-isa',
        gia: 'badge-gia',
        sipp: 'badge-sipp',
        pension: 'badge-sipp',
        other: 'badge-other',
      };
      return classes[type] || 'badge-other';
    },
  },
};
</script>

<style scoped>
.investment-detail {
  padding: 24px;
  max-width: 1400px;
  margin: 0 auto;
}

/* Header */
.page-header {
  margin-bottom: 32px;
}

.back-button {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  @apply bg-gray-100;
  border: none;
  border-radius: 8px;
  @apply text-gray-700;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  margin-bottom: 16px;
}

.back-button:hover {
  @apply bg-gray-200;
  @apply text-gray-900;
}

.back-icon {
  width: 16px;
  height: 16px;
}

.page-title {
  font-size: 28px;
  font-weight: 700;
  @apply text-gray-900;
  margin: 0 0 8px 0;
}

.page-subtitle {
  font-size: 16px;
  @apply text-gray-500;
  margin: 0;
}

/* Loading & Error States */
.loading-state,
.error-state,
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 80px 20px;
  text-align: center;
}

.retry-button {
  margin-top: 16px;
  padding: 10px 20px;
  @apply bg-primary-500;
  color: white;
  border: none;
  border-radius: 8px;
  font-weight: 500;
  cursor: pointer;
}

.retry-button:hover {
  @apply bg-blue-600;
}

.empty-icon {
  width: 64px;
  height: 64px;
  @apply text-gray-400;
  margin-bottom: 16px;
}

.empty-text {
  font-size: 18px;
  font-weight: 600;
  @apply text-gray-500;
  margin: 0 0 8px 0;
}

.empty-subtext {
  font-size: 14px;
  @apply text-gray-400;
  margin: 0;
}

/* Two Column Layout */
.top-section {
  display: grid;
  grid-template-columns: 1fr 340px;
  gap: 24px;
  margin-bottom: 32px;
}

/* Chart Card - matching pension chart style exactly */
.chart-card {
  background: white;
  border-radius: 12px;
  padding: 24px;
  @apply border border-gray-200;
}

.chart-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.chart-title {
  font-size: 18px;
  font-weight: 600;
  @apply text-gray-900;
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
  @apply bg-blue-50;
  @apply text-blue-600;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
}

.period-selector {
  padding: 6px 12px;
  @apply border border-gray-300;
  border-radius: 6px;
  font-size: 13px;
  @apply text-gray-700;
  background: white;
  cursor: pointer;
}

.period-selector:focus {
  outline: none;
  @apply border-primary-500;
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
  @apply bg-blue-50;
}

.summary-item.purple {
  @apply bg-purple-50;
}

.summary-item-label {
  display: block;
  font-size: 12px;
  @apply text-gray-500;
  margin-bottom: 4px;
}

.summary-item-value {
  font-size: 18px;
  font-weight: 700;
  @apply text-gray-900;
}

/* Analysis Panels */
.analysis-panels {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.analysis-card {
  background: white;
  border-radius: 12px;
  padding: 16px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  @apply border border-gray-200;
}

.clickable-card {
  cursor: pointer;
  transition: all 0.2s ease;
}

.clickable-card:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  @apply border-primary-500;
}

.analysis-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}

.analysis-title {
  font-size: 14px;
  font-weight: 600;
  @apply text-gray-900;
  margin: 0;
}

.score-badge {
  padding: 3px 8px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 600;
}

.score-excellent {
  @apply bg-green-100;
  @apply text-green-800;
}

.score-good {
  @apply bg-blue-100;
  @apply text-blue-800;
}

.score-fair {
  @apply bg-amber-100;
  @apply text-amber-800;
}

.score-poor {
  @apply bg-red-100;
  @apply text-red-800;
}

.count-badge {
  padding: 3px 8px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 500;
  @apply bg-gray-100;
  @apply text-gray-700;
}

.fee-badge {
  padding: 3px 8px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 600;
  @apply bg-amber-100;
  @apply text-amber-800;
}

.analysis-content {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.analysis-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.row-label {
  font-size: 13px;
  @apply text-gray-500;
}

.row-value {
  font-size: 13px;
  font-weight: 600;
  @apply text-gray-900;
}

.score-text {
  font-weight: 600;
}

/* Holdings Card with Donut */
.holdings-card {
  padding: 16px;
}

.holdings-content {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.holdings-stats {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.donut-container {
  margin: 0 -8px;
}

.no-allocation {
  text-align: center;
  padding: 16px;
  @apply text-gray-400;
  font-size: 12px;
}

.allocation-loading {
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 24px;
}

/* Accounts Section */
.accounts-section {
  margin-top: 32px;
}

.section-title {
  font-size: 20px;
  font-weight: 600;
  @apply text-gray-900;
  margin: 0;
}

.section-subtitle {
  font-size: 14px;
  @apply text-gray-500;
  margin: 8px 0 24px 0;
}

.accounts-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 24px;
}

.account-card {
  background: white;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  @apply border border-gray-200;
}

.account-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 16px;
}

.account-name {
  font-size: 16px;
  font-weight: 600;
  @apply text-gray-900;
  margin: 0 0 8px 0;
}

.account-type-badge {
  display: inline-block;
  padding: 4px 10px;
  font-size: 11px;
  font-weight: 600;
  border-radius: 4px;
}

.badge-isa {
  @apply bg-green-100;
  @apply text-green-800;
}

.badge-gia {
  @apply bg-blue-100;
  @apply text-blue-800;
}

.badge-sipp {
  @apply bg-purple-100;
  @apply text-purple-800;
}

.badge-other {
  @apply bg-gray-100;
  @apply text-gray-700;
}

.account-value {
  text-align: right;
}

.value-label {
  display: block;
  font-size: 11px;
  @apply text-gray-500;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.value-amount {
  font-size: 18px;
  font-weight: 700;
  @apply text-gray-900;
}

.account-stats {
  display: flex;
  gap: 24px;
  padding: 12px 0;
  @apply border-t border-b border-gray-200;
  margin-bottom: 16px;
}

.stat {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.stat-label {
  font-size: 12px;
  @apply text-gray-500;
}

.stat-value {
  font-size: 14px;
  font-weight: 600;
  @apply text-gray-900;
}

.account-chart {
  margin-top: 8px;
}

/* Responsive */
@media (max-width: 1024px) {
  .top-section {
    grid-template-columns: 1fr;
  }

  .analysis-panels {
    flex-direction: row;
    flex-wrap: wrap;
  }

  .analysis-card {
    flex: 1;
    min-width: 200px;
  }

  .accounts-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 768px) {
  .investment-detail {
    padding: 16px;
  }

  .summary-row {
    grid-template-columns: 1fr;
  }

  .analysis-panels {
    flex-direction: column;
  }

  .analysis-card {
    min-width: 100%;
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

  .summary-item-value {
    font-size: 16px;
  }
}
</style>
