<template>
  <div class="account-performance-panel">
    <!-- Loading State -->
    <div v-if="loading" class="flex justify-center items-center py-8">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-raspberry-500"></div>
      <span class="ml-3 text-neutral-500">Running Monte Carlo simulation...</span>
    </div>

    <!-- Content -->
    <div v-else>
      <!-- Two-column layout: Sidebar Cards + Chart -->
      <div class="chart-with-sidebar">
        <!-- Left Sidebar: Insight Cards -->
        <div class="sidebar-cards">
          <!-- Diversification Insights Card -->
          <div
            class="insight-card cursor-pointer hover:shadow-md transition-shadow"
            @click="hasHoldings ? goToDiversificationTab() : $emit('add-holding')"
          >
            <h4 class="text-sm font-semibold text-horizon-500 mb-3">Diversification Insights</h4>
            <!-- No Holdings State -->
            <div v-if="!hasHoldings" class="text-center py-4">
              <p class="text-lg font-semibold text-violet-600 hover:underline">Enter Holdings</p>
              <p class="text-xs text-neutral-500 mt-1">Add holdings to see diversification analysis</p>
            </div>
            <!-- Has Holdings -->
            <div v-else-if="recommendations.length > 0" class="space-y-2">
              <div
                v-for="(rec, index) in recommendations.slice(0, 3)"
                :key="index"
                class="recommendation-item border rounded-lg p-2"
                :class="getRecommendationClass(rec.type)"
              >
                <div class="flex items-start gap-2">
                  <span class="text-sm font-medium">{{ getRecommendationIcon(rec.type) }}</span>
                  <p class="text-xs leading-relaxed">{{ rec.message }}</p>
                </div>
              </div>
              <p v-if="recommendations.length > 3" class="text-xs text-neutral-500 text-center pt-1">
                +{{ recommendations.length - 3 }} more insights
              </p>
            </div>
            <div v-else class="text-center py-4">
              <p class="text-sm text-spring-600 font-medium">Well Diversified</p>
              <p class="text-xs text-neutral-500 mt-1">No recommendations at this time</p>
            </div>
          </div>

          <!-- Rebalancing Summary Card -->
          <div
            class="insight-card cursor-pointer hover:shadow-md transition-shadow"
            @click="hasHoldings ? goToRebalancingTab() : $emit('add-holding')"
          >
            <h4 class="text-sm font-semibold text-horizon-500 mb-3">Rebalancing Status</h4>

            <!-- No Holdings State -->
            <div v-if="!hasHoldings" class="text-center py-4">
              <p class="text-lg font-semibold text-violet-600 hover:underline">Enter Holdings</p>
              <p class="text-xs text-neutral-500 mt-1">Add holdings to get rebalancing strategies</p>
            </div>

            <!-- Has Holdings + Rebalancing Data -->
            <template v-else-if="rebalancingData">
              <!-- Portfolio Drift Status -->
              <div class="text-center p-3 rounded-lg mb-3" :class="getDriftBgClass()">
                <p class="text-xs text-neutral-500 mb-1">Portfolio Drift</p>
                <p class="text-lg font-bold" :class="getDriftStatusClass()">
                  {{ getDriftLabel() }}
                </p>
                <p class="text-xs mt-1" :class="rebalancingData.drift_analysis?.needs_rebalancing ? 'text-violet-600 font-medium' : 'text-spring-600'">
                  {{ rebalancingData.drift_analysis?.needs_rebalancing ? 'Rebalancing Recommended' : 'On Track' }}
                </p>
              </div>

              <!-- Current vs Target (Top 2 Asset Classes) -->
              <div class="space-y-2">
                <div v-if="rebalancingData.current_allocation?.equities !== undefined" class="allocation-row">
                  <div class="flex justify-between text-xs mb-1">
                    <span class="font-medium text-neutral-500">Equities</span>
                    <span class="text-neutral-500">
                      {{ formatAllocation(rebalancingData.current_allocation.equities) }}% → {{ formatAllocation(rebalancingData.target_allocation?.equities) }}%
                    </span>
                  </div>
                  <div class="h-2 bg-savannah-200 rounded overflow-hidden relative">
                    <div
                      class="absolute h-full w-0.5 bg-horizon-500 z-10"
                      :style="{ left: formatAllocation(rebalancingData.target_allocation?.equities) + '%' }"
                    ></div>
                    <div
                      class="h-full bg-violet-500 rounded"
                      :style="{ width: formatAllocation(rebalancingData.current_allocation.equities) + '%' }"
                    ></div>
                  </div>
                </div>
                <div v-if="rebalancingData.current_allocation?.bonds !== undefined" class="allocation-row">
                  <div class="flex justify-between text-xs mb-1">
                    <span class="font-medium text-neutral-500">Bonds</span>
                    <span class="text-neutral-500">
                      {{ formatAllocation(rebalancingData.current_allocation.bonds) }}% → {{ formatAllocation(rebalancingData.target_allocation?.bonds) }}%
                    </span>
                  </div>
                  <div class="h-2 bg-savannah-200 rounded overflow-hidden relative">
                    <div
                      class="absolute h-full w-0.5 bg-horizon-500 z-10"
                      :style="{ left: formatAllocation(rebalancingData.target_allocation?.bonds) + '%' }"
                    ></div>
                    <div
                      class="h-full bg-spring-500 rounded"
                      :style="{ width: formatAllocation(rebalancingData.current_allocation.bonds) + '%' }"
                    ></div>
                  </div>
                </div>
              </div>
            </template>

            <!-- Has Holdings but Loading -->
            <div v-else class="text-center py-4">
              <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-raspberry-500 mx-auto"></div>
              <p class="text-xs text-neutral-500 mt-2">Loading...</p>
            </div>
          </div>

          <!-- Fees Summary Card -->
          <div
            class="insight-card cursor-pointer hover:shadow-md transition-shadow"
            @click="handleFeesClick"
          >
            <h4 class="text-sm font-semibold text-horizon-500 mb-3">Total Fees</h4>

            <!-- No Fees Entered State -->
            <div v-if="!hasHoldings && totalFeePercent === 0" class="text-center py-4">
              <p class="text-lg font-semibold text-violet-600 hover:underline">Add Fees</p>
              <p class="text-xs text-neutral-500 mt-1">Add fees to get fee optimisation strategies</p>
            </div>

            <!-- Has Fees (from platform fee or holdings) -->
            <div v-else class="text-center p-3 rounded-lg" :class="getTotalFeeBgClass()">
              <p class="text-xs text-neutral-500 mb-1">Annual Fee Rate</p>
              <p class="text-2xl font-bold" :class="getTotalFeeClass()">
                {{ formatPercentage(totalFeePercent) }}
              </p>
              <p class="text-xs text-neutral-500 mt-1">
                {{ formatCurrency(totalAnnualFees) }} / year
              </p>
            </div>
          </div>
        </div>

        <!-- Chart Area (Right) -->
        <div class="chart-container">
          <!-- Projected Value Card -->
          <div class="bg-violet-50 rounded-lg p-4 mb-4">
            <div class="flex items-center justify-between mb-1">
              <p class="text-xs text-violet-600 uppercase tracking-wide">Projected Value (80%)</p>
              <!-- Show fixed "To Retirement" text for retirement-included accounts -->
              <span
                v-if="isIncludedInRetirement && yearsToRetirement"
                class="px-2 py-1 text-xs bg-teal-100 text-teal-700 rounded font-medium"
              >
                To Retirement ({{ yearsToRetirement }} yrs)
              </span>
              <!-- Show dropdown for non-retirement accounts -->
              <select
                v-else
                v-model="selectedProjectionYears"
                @change="updateProjectionData"
                class="px-2 py-1 text-xs border border-violet-200 rounded bg-white focus:ring-2 focus:ring-violet-500 focus:border-transparent"
              >
                <option
                  v-for="option in projectionYearOptions"
                  :key="option.value"
                  :value="option.value"
                >
                  {{ option.label }}
                </option>
              </select>
            </div>
            <p class="text-2xl font-bold text-violet-900">{{ formatProjectedValue80 }}</p>
            <p class="text-sm text-violet-600 mt-1">
              <template v-if="isIncludedInRetirement && yearsToRetirement">
                at retirement in {{ yearsToRetirement }} years
              </template>
              <template v-else>
                in {{ selectedProjectionYears }} years
              </template>
            </p>
          </div>

          <!-- Monte Carlo Projection Chart -->
          <div v-if="hasProjectionData">
            <apexchart
              v-if="isChartReady"
              type="area"
              :options="chartOptions"
              :series="series"
              height="400"
            />
          </div>
          <div v-else class="bg-light-blue-100 border border-light-gray rounded-lg p-8 text-center">
            <svg class="mx-auto h-12 w-12 text-horizon-400 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
            <p class="text-sm text-neutral-500">{{ error || 'No projection data available' }}</p>
          </div>

          <!-- Asset Allocation Summary Card -->
          <div
            v-if="hasHoldings && assetAllocationSummary.length > 0"
            class="asset-allocation-card mt-4 cursor-pointer hover:shadow-md transition-shadow"
            @click="goToHoldingsTab"
          >
            <h4 class="text-sm font-semibold text-horizon-500 mb-3">Asset Allocation</h4>
            <div class="allocation-bars">
              <!-- Stacked bar -->
              <div class="stacked-bar">
                <div
                  v-for="(allocation, index) in assetAllocationSummary"
                  :key="index"
                  class="bar-segment"
                  :style="'width: ' + allocation.percentage + '%; background-color: ' + getAssetColor(allocation.type) + ';'"
                  :title="formatAssetType(allocation.type) + ': ' + allocation.percentage.toFixed(1) + '%'"
                ></div>
              </div>
              <!-- Legend -->
              <div class="allocation-legend">
                <div
                  v-for="(allocation, index) in assetAllocationSummary"
                  :key="index"
                  class="legend-item-inline"
                >
                  <span
                    class="legend-dot"
                    :style="'background-color: ' + getAssetColor(allocation.type) + ';'"
                  ></span>
                  <span class="legend-text">{{ formatAssetType(allocation.type) }}</span>
                  <span class="legend-value">{{ allocation.percentage.toFixed(1) }}%</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Tax Status Summary -->
      <div
        v-if="taxInfo"
        class="tax-status-card cursor-pointer hover:shadow-md transition-shadow"
        @click="goToTaxStatusTab"
      >
        <div class="mb-4">
          <h4 class="text-sm font-semibold text-horizon-500">Tax Treatment</h4>
          <p class="text-xs text-neutral-500">{{ taxInfo.product_type_label }}</p>
        </div>

        <!-- Tax Items Grid -->
        <div class="tax-items-grid">
          <div
            v-for="item in taxInfo.tax_items?.slice(0, 4)"
            :key="item.aspect"
            class="tax-item-mini"
            :class="getTaxStatusBgClass(item.status)"
          >
            <span
              class="tax-status-icon"
              :class="getTaxStatusIconClass(item.status)"
            >{{ getTaxStatusIcon(item.status) }}</span>
            <div class="tax-item-content">
              <span class="tax-item-title">{{ item.title }}</span>
              <span class="tax-item-summary">{{ item.summary }}</span>
            </div>
          </div>
        </div>

        <!-- Status Legend -->
        <div class="tax-legend">
          <div class="tax-legend-item">
            <span class="tax-legend-dot bg-spring-500"></span>
            <span>Tax-Free</span>
          </div>
          <div class="tax-legend-item">
            <span class="tax-legend-dot bg-slate-500"></span>
            <span>Taxable</span>
          </div>
          <div class="tax-legend-item">
            <span class="tax-legend-dot bg-violet-500"></span>
            <span>Deferred</span>
          </div>
          <div class="tax-legend-item">
            <span class="tax-legend-dot bg-violet-500"></span>
            <span>Relief</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import VueApexCharts from 'vue3-apexcharts';
import { currencyMixin } from '@/mixins/currencyMixin';
import investmentService from '@/services/investmentService';
import diversificationService from '@/services/diversificationService';
import rebalancingService from '@/services/rebalancingService';
import api from '@/services/api';
import { mapState } from 'vuex';
import { PRIMARY_COLORS, SUCCESS_COLORS, BORDER_COLORS, ASSET_COLORS, CHART_DEFAULTS } from '@/constants/designSystem';

import logger from '@/utils/logger';
export default {
  name: 'AccountPerformancePanel',

  mixins: [currencyMixin],

  components: {
    apexchart: VueApexCharts,
  },

  props: {
    account: {
      type: Object,
      required: true,
    },
  },

  emits: ['change-tab', 'add-holding', 'edit-account'],

  data() {
    return {
      loading: true,
      error: null,
      allProjections: null,
      projectionData: null,
      isChartReady: false,
      selectedProjectionYears: 10,
      estimatedMonthlyContribution: 0,
      recommendations: [],
      loadingRecommendations: false,
      rebalancingData: null,
      loadingRebalancing: false,
      taxInfo: null,
      loadingTaxInfo: false,
      renderTimeout: null,
    };
  },

  computed: {
    ...mapState('auth', ['currentUser']),
    ...mapState('retirement', ['profile']),

    hasProjectionData() {
      return this.projectionData?.year_by_year?.length > 0;
    },

    // Check if account is included in retirement planning
    isIncludedInRetirement() {
      return this.account.include_in_retirement === true;
    },

    // Calculate years to retirement
    yearsToRetirement() {
      const retirementAge = this.profile?.target_retirement_age || this.currentUser?.target_retirement_age || 68;
      const currentAge = this.currentUser?.date_of_birth
        ? Math.floor((new Date() - new Date(this.currentUser.date_of_birth)) / (365.25 * 24 * 60 * 60 * 1000))
        : null;

      if (!currentAge) return null;
      return Math.max(1, retirementAge - currentAge);
    },

    // Dropdown options for non-retirement accounts
    projectionYearOptions() {
      return [
        { value: 5, label: '5 Years' },
        { value: 10, label: '10 Years' },
        { value: 20, label: '20 Years' },
        { value: 30, label: '30 Years' },
      ];
    },

    userShareValue() {
      if (this.account.ownership_type === 'joint') {
        const percentage = this.account.ownership_percentage ?? 50;
        return this.account.current_value * (percentage / 100);
      }
      return this.account.current_value;
    },

    formatProjectedValue80() {
      if (!this.hasProjectionData) return '--';
      const lastYear = this.projectionData.year_by_year[this.projectionData.year_by_year.length - 1];
      return this.formatCurrency(lastYear?.percentile_20);
    },

    years() {
      if (!this.projectionData?.year_by_year) return [];
      return this.projectionData.year_by_year.map(y => y.year);
    },

    series() {
      if (!this.hasProjectionData) return [];

      return [
        {
          name: '90% Probability',
          data: this.projectionData.year_by_year.map(y => Math.round(y.percentile_10)),
        },
        {
          name: '85% Probability',
          data: this.projectionData.year_by_year.map(y => Math.round(y.percentile_15)),
        },
        {
          name: '80% Probability',
          data: this.projectionData.year_by_year.map(y => Math.round(y.percentile_20)),
        },
        {
          name: '75% Probability',
          data: this.projectionData.year_by_year.map(y => Math.round(y.percentile_25)),
        },
      ];
    },

    chartOptions() {
      return {
        chart: {
          ...CHART_DEFAULTS.chart,
          type: 'area',
          stacked: false,
          toolbar: {
            show: true,
            tools: {
              download: true,
              selection: false,
              zoom: false,
              zoomin: false,
              zoomout: false,
              pan: false,
              reset: false,
            },
          },
          animations: {
            enabled: true,
            easing: 'easeinout',
            speed: 800,
          },
        },
        colors: [PRIMARY_COLORS[900], PRIMARY_COLORS[600], SUCCESS_COLORS[500], SUCCESS_COLORS[400]],
        stroke: {
          curve: 'smooth',
          width: [1, 1, 1, 1],
        },
        fill: {
          type: 'gradient',
          gradient: {
            opacityFrom: 0.5,
            opacityTo: 0.1,
            stops: [0, 90, 100],
          },
        },
        xaxis: {
          categories: this.years,
          title: {
            text: 'Year',
            style: {
              fontWeight: 600,
              fontSize: '12px',
            },
          },
          labels: {
            style: {
              fontSize: '11px',
            },
            rotate: -45,
            rotateAlways: this.years.length > 15,
          },
          tickAmount: Math.min(this.years.length, 10),
        },
        yaxis: {
          title: {
            text: 'Investment Value',
            style: {
              fontWeight: 600,
              fontSize: '12px',
            },
          },
          labels: {
            formatter: (val) => this.formatCurrencyShort(val),
            style: {
              fontSize: '11px',
            },
          },
        },
        tooltip: {
          shared: true,
          intersect: false,
          y: {
            formatter: (val) => this.formatCurrency(val),
          },
        },
        legend: {
          position: 'top',
          horizontalAlign: 'center',
          fontSize: '12px',
          markers: {
            width: 10,
            height: 10,
            radius: 10,
          },
          itemMargin: {
            horizontal: 12,
          },
        },
        grid: {
          borderColor: BORDER_COLORS.default,
          strokeDashArray: 4,
        },
        dataLabels: {
          enabled: false,
        },
      };
    },

    // Fee computed properties
    platformFeePercent() {
      if (this.account.platform_fee_type === 'fixed') {
        const amount = parseFloat(this.account.platform_fee_amount) || 0;
        let annualAmount = amount;
        if (this.account.platform_fee_frequency === 'monthly') annualAmount = amount * 12;
        else if (this.account.platform_fee_frequency === 'quarterly') annualAmount = amount * 4;
        const accountValue = parseFloat(this.account.current_value) || 0;
        return accountValue > 0 ? (annualAmount / accountValue) * 100 : 0;
      }
      return parseFloat(this.account.platform_fee_percent) || 0;
    },

    advisorFeePercent() {
      return parseFloat(this.account.advisor_fee_percent) || 0;
    },

    totalHoldingsValue() {
      if (!this.account.holdings?.length) return this.account.current_value || 0;
      return this.account.holdings.reduce((sum, h) => sum + (h.current_value || 0), 0);
    },

    weightedAverageOCF() {
      if (!this.account.holdings?.length || this.totalHoldingsValue === 0) return 0;
      const totalWeightedOCF = this.account.holdings.reduce((sum, h) => {
        return sum + ((h.current_value || 0) * (parseFloat(h.ocf_percent) || 0));
      }, 0);
      return totalWeightedOCF / this.totalHoldingsValue;
    },

    totalFeePercent() {
      return this.platformFeePercent + this.advisorFeePercent + this.weightedAverageOCF;
    },

    totalAnnualFees() {
      return (this.totalHoldingsValue * this.totalFeePercent) / 100;
    },

    // Asset allocation computed properties
    hasHoldings() {
      return this.account.holdings?.length > 0;
    },

    assetAllocationSummary() {
      if (!this.hasHoldings) return [];

      const allocation = {};
      const holdings = this.account.holdings;

      holdings.forEach(holding => {
        const value = parseFloat(holding.current_value || 0);
        const assetType = holding.asset_type || 'other';

        if (!allocation[assetType]) {
          allocation[assetType] = 0;
        }
        allocation[assetType] += value;
      });

      return Object.entries(allocation)
        .map(([type, value]) => ({
          type,
          value,
          percentage: this.totalHoldingsValue > 0 ? (value / this.totalHoldingsValue) * 100 : 0,
        }))
        .sort((a, b) => b.percentage - a.percentage);
    },

  },

  watch: {
    'account.id': {
      immediate: true,
      handler(newId) {
        if (newId) {
          // Set projection years based on retirement inclusion
          this.setProjectionYearsForAccount();
          this.loadProjections();
          this.loadDiversification();
          this.loadRebalancing();
          this.loadTaxInfo();
        }
      },
    },
    // Re-evaluate when profile loads (for retirement years calculation)
    profile: {
      handler() {
        if (this.isIncludedInRetirement && this.yearsToRetirement) {
          this.setProjectionYearsForAccount();
          this.updateProjectionData();
        }
      },
    },
  },

  beforeUnmount() {
    if (this.renderTimeout) clearTimeout(this.renderTimeout);
  },

  mounted() {
    // Fetch retirement data (includes profile) if not already loaded
    if (!this.profile) {
      this.$store.dispatch('retirement/fetchRetirementData').catch(() => {
        // Silently fail - profile is optional for this feature
      });
    }
  },

  methods: {
    setProjectionYearsForAccount() {
      // For retirement-included accounts, always use years to retirement
      if (this.isIncludedInRetirement && this.yearsToRetirement) {
        this.selectedProjectionYears = this.yearsToRetirement;
      } else if (this.selectedProjectionYears === 'retirement') {
        // Reset to default if was previously set to retirement but no longer applicable
        this.selectedProjectionYears = 10;
      }
    },

    async loadProjections() {
      this.loading = true;
      this.error = null;
      this.isChartReady = false;

      try {
        const response = await investmentService.getAccountProjections(this.account.id);

        if (response.success) {
          this.allProjections = response.data;
          this.estimatedMonthlyContribution = response.data.monthly_contribution || 0;
          this.updateProjectionData();
        } else {
          this.error = response.message || 'Failed to load projections';
        }
      } catch (err) {
        logger.error('Error loading projections:', err);
        this.error = 'Failed to load projection data';
      } finally {
        this.loading = false;
        this.$nextTick(() => {
          if (this.renderTimeout) clearTimeout(this.renderTimeout);
          this.renderTimeout = setTimeout(() => {
            this.isChartReady = true;
          }, 100);
        });
      }
    },

    updateProjectionData() {
      if (!this.allProjections?.projections) return;

      // Get the years to project (now always numeric)
      const yearsKey = this.selectedProjectionYears;

      // Find the closest projection data available
      const availableYears = Object.keys(this.allProjections.projections).map(Number).sort((a, b) => a - b);
      let selectedYear = availableYears.find(y => y >= yearsKey) || availableYears[availableYears.length - 1];

      const selectedData = this.allProjections.projections[selectedYear];
      if (selectedData) {
        // For retirement accounts, slice data to exact years to retirement
        if (this.isIncludedInRetirement && this.yearsToRetirement && selectedYear > this.yearsToRetirement) {
          const slicedYearByYear = selectedData.year_by_year.slice(0, this.yearsToRetirement + 1);
          this.projectionData = {
            year_by_year: slicedYearByYear,
            percentiles: selectedData.percentiles,
          };
        } else {
          this.projectionData = {
            year_by_year: selectedData.year_by_year,
            percentiles: selectedData.percentiles,
          };
        }
        this.isChartReady = false;
        this.$nextTick(() => {
          if (this.renderTimeout) clearTimeout(this.renderTimeout);
          this.renderTimeout = setTimeout(() => {
            this.isChartReady = true;
          }, 100);
        });
      }
    },

    formatCurrencyShort(value) {
      if (value === null || value === undefined) return '£0';
      if (value >= 1000000) {
        return '£' + (value / 1000000).toFixed(1) + 'M';
      }
      if (value >= 1000) {
        return '£' + (value / 1000).toFixed(0) + 'K';
      }
      return this.formatCurrency(value);
    },

    async loadDiversification() {
      this.loadingRecommendations = true;
      try {
        const response = await diversificationService.getAccountDiversification(this.account.id);
        if (response.success && response.data?.recommendations) {
          this.recommendations = response.data.recommendations;
        }
      } catch (err) {
        logger.error('Error loading diversification:', err);
      } finally {
        this.loadingRecommendations = false;
      }
    },

    goToDiversificationTab() {
      this.$emit('change-tab', 'diversification');
    },

    getRecommendationIcon(type) {
      switch (type) {
        case 'success': return '✓';
        case 'warning': return '⚠';
        case 'info': return 'ℹ';
        default: return '•';
      }
    },

    getRecommendationClass(type) {
      switch (type) {
        case 'success': return 'text-spring-600 bg-spring-50 border-spring-200';
        case 'warning': return 'text-violet-600 bg-violet-50 border-violet-200';
        case 'info': return 'text-violet-600 bg-violet-50 border-violet-200';
        default: return 'text-neutral-500 bg-eggshell-500 border-light-gray';
      }
    },

    async loadRebalancing() {
      this.loadingRebalancing = true;
      try {
        const response = await rebalancingService.getAccountRebalancing(this.account.id);
        if (response.success && response.data) {
          this.rebalancingData = response.data;
        }
      } catch (err) {
        logger.error('Error loading rebalancing:', err);
      } finally {
        this.loadingRebalancing = false;
      }
    },

    goToRebalancingTab() {
      this.$emit('change-tab', 'rebalancing');
    },

    getDriftLabel() {
      if (!this.rebalancingData?.drift_analysis) return 'N/A';
      const score = this.rebalancingData.drift_analysis.drift_score;
      // drift_score is a percentage where lower = better aligned
      if (score < 5) return 'Well aligned';
      if (score < 10) return 'Minor drift';
      return 'Significant drift \u2014 review recommended';
    },

    getDriftStatusClass() {
      if (!this.rebalancingData?.drift_analysis) return 'text-neutral-500';
      const score = this.rebalancingData.drift_analysis.drift_score;
      if (score < 5) return 'text-spring-600';
      if (score < 10) return 'text-violet-600';
      return 'text-violet-600';
    },

    getDriftBgClass() {
      if (!this.rebalancingData?.drift_analysis) return 'bg-eggshell-500';
      const score = this.rebalancingData.drift_analysis.drift_score;
      if (score < 5) return 'bg-spring-50';
      if (score < 10) return 'bg-violet-50';
      return 'bg-violet-50';
    },

    formatAllocation(value) {
      return (value || 0).toFixed(1);
    },

    goToFeesTab() {
      this.$emit('change-tab', 'fees');
    },

    handleFeesClick() {
      // If no fees entered, open edit form to add fees
      // Otherwise, navigate to fees tab to view fee details
      if (!this.hasHoldings && this.totalFeePercent === 0) {
        this.$emit('edit-account');
      } else {
        this.goToFeesTab();
      }
    },

    getTotalFeeClass() {
      const fee = this.totalFeePercent;
      if (fee < 0.8) return 'text-spring-600';
      if (fee < 1.5) return 'text-violet-600';
      return 'text-violet-600';
    },

    getTotalFeeBgClass() {
      const fee = this.totalFeePercent;
      if (fee < 0.8) return 'bg-spring-50';
      if (fee < 1.5) return 'bg-violet-50';
      return 'bg-violet-50';
    },

    goToHoldingsTab() {
      this.$emit('change-tab', 'holdings');
    },

    formatAssetType(type) {
      const types = {
        equity: 'Equity',
        equities: 'Equities',
        fixed_income: 'Fixed Income',
        bonds: 'Bonds',
        property: 'Property',
        real_estate: 'Real Estate',
        commodities: 'Commodities',
        cash: 'Cash',
        alternatives: 'Alternatives',
        fund: 'Fund',
        etf: 'ETF',
        stock: 'Stock',
        bond: 'Bond',
        other: 'Other',
      };
      return types[type] || type?.charAt(0).toUpperCase() + type?.slice(1).replace(/_/g, ' ') || 'Other';
    },

    getAssetColor(type) {
      return ASSET_COLORS[type] || ASSET_COLORS.other;
    },

    async loadTaxInfo() {
      this.loadingTaxInfo = true;
      try {
        const response = await api.get(`/tax-info/investment/${this.account.account_type}`);
        this.taxInfo = response.data.data;
      } catch (err) {
        logger.error('Error loading tax info:', err);
      } finally {
        this.loadingTaxInfo = false;
      }
    },

    goToTaxStatusTab() {
      this.$emit('change-tab', 'tax-status');
    },

    getTaxStatusBgClass(status) {
      const classes = {
        exempt: 'bg-spring-500 border-spring-500 text-white',
        taxable: 'bg-slate-500 border-slate-500 text-white',
        deferred: 'bg-violet-500 border-violet-500 text-white',
        relief: 'bg-violet-500 border-violet-500 text-white',
        limit: 'bg-eggshell-500 border-horizon-400 text-white',
      };
      return classes[status] || 'bg-eggshell-500 border-horizon-400 text-white';
    },

    getTaxStatusIconClass(status) {
      const classes = {
        exempt: 'bg-spring-600 text-white',
        taxable: 'bg-slate-600 text-white',
        deferred: 'bg-raspberry-500 text-white',
        relief: 'bg-violet-600 text-white',
        limit: 'bg-horizon-400 text-white',
      };
      return classes[status] || 'bg-horizon-400 text-white';
    },

    getTaxStatusIcon(status) {
      const icons = {
        exempt: '✓',
        taxable: '!',
        deferred: '⏱',
        relief: '↓',
        limit: '⊘',
      };
      return icons[status] || '•';
    },
  },
};
</script>

<style scoped>
.account-performance-panel {
  min-height: 400px;
}

.section-title {
  font-size: 16px;
  font-weight: 600;
  @apply text-horizon-500;
  margin: 0 0 4px 0;
}

.chart-with-sidebar {
  display: flex;
  gap: 20px;
  margin-bottom: 24px;
}

.sidebar-cards {
  flex: 0 0 280px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.insight-card {
  @apply bg-white border border-light-gray rounded-xl p-4;
}

.chart-container {
  flex: 1;
  min-width: 0;
}

.chart-full-width {
  flex: 1;
}

.recommendation-item {
  transition: all 0.2s ease;
}

.allocation-row {
  margin-bottom: 4px;
}

.asset-allocation-card {
  @apply bg-white border border-light-gray rounded-xl p-4;
}

.stacked-bar {
  display: flex;
  height: 28px;
  border-radius: 8px;
  overflow: hidden;
  margin-bottom: 12px;
  @apply bg-savannah-200;
}

.bar-segment {
  height: 100%;
  min-width: 2px;
  transition: width 0.3s ease;
}

.bar-segment:first-child {
  border-radius: 6px 0 0 6px;
}

.bar-segment:last-child {
  border-radius: 0 6px 6px 0;
}

.bar-segment:only-child {
  border-radius: 6px;
}

.allocation-legend {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.legend-item-inline {
  display: flex;
  align-items: center;
  gap: 6px;
}

.legend-dot {
  width: 10px;
  height: 10px;
  border-radius: 2px;
  flex-shrink: 0;
}

.legend-text {
  font-size: 12px;
  @apply text-neutral-500;
}

.legend-value {
  font-size: 12px;
  font-weight: 600;
  @apply text-horizon-500;
}

/* Tax Status Card Styles */
.tax-status-card {
  @apply bg-white border border-light-gray rounded-xl p-5 mt-6;
}

.tax-items-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 12px;
  margin-bottom: 16px;
}

.tax-item-mini {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 12px;
  border-radius: 8px;
  border: 1px solid;
}

.tax-status-icon {
  flex-shrink: 0;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: bold;
}

.tax-item-content {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.tax-item-title {
  font-size: 13px;
  font-weight: 600;
  color: white;
}

.tax-item-summary {
  font-size: 11px;
  color: rgba(255, 255, 255, 0.9);
  line-height: 1.4;
}

.tax-legend {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  padding-top: 12px;
  @apply border-t border-light-gray;
}

.tax-legend-item {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 11px;
  @apply text-neutral-500;
}

.tax-legend-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
}

@media (max-width: 1024px) {
  .chart-with-sidebar {
    flex-direction: column;
  }

  .sidebar-cards {
    flex: none;
    width: 100%;
    flex-direction: row;
    flex-wrap: wrap;
  }

  .insight-card {
    flex: 1 1 300px;
  }
}

@media (max-width: 768px) {
  .performance-summary {
    grid-template-columns: 1fr;
  }

  .tax-items-grid {
    grid-template-columns: 1fr;
  }

  .card-value {
    font-size: 20px;
  }

  .tax-legend {
    gap: 12px;
  }
}
</style>
