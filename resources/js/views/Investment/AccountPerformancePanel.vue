<template>
  <div class="account-performance-panel">
    <!-- Loading State -->
    <div v-if="loading" class="flex justify-center items-center py-8">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
      <span class="ml-3 text-gray-600">Running Monte Carlo simulation...</span>
    </div>

    <!-- Content -->
    <div v-else>
      <!-- Two-column layout: Sidebar Cards + Chart -->
      <div class="chart-with-sidebar">
        <!-- Left Sidebar: Insight Cards -->
        <div class="sidebar-cards">
          <!-- Diversification Insights Card -->
          <div
            v-if="recommendations.length > 0"
            class="insight-card cursor-pointer hover:shadow-md transition-shadow"
            @click="goToDiversificationTab"
          >
            <h4 class="text-sm font-semibold text-gray-900 mb-3">Diversification Insights</h4>
            <div class="space-y-2">
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
              <p v-if="recommendations.length > 3" class="text-xs text-gray-500 text-center pt-1">
                +{{ recommendations.length - 3 }} more insights
              </p>
            </div>
          </div>

          <!-- Rebalancing Summary Card -->
          <div
            v-if="rebalancingData"
            class="insight-card cursor-pointer hover:shadow-md transition-shadow"
            @click="goToRebalancingTab"
          >
            <h4 class="text-sm font-semibold text-gray-900 mb-3">Rebalancing Status</h4>

            <!-- Drift Score -->
            <div class="text-center p-3 rounded-lg mb-3" :class="getDriftBgClass()">
              <p class="text-xs text-gray-600 mb-1">Portfolio Drift</p>
              <p class="text-2xl font-bold" :class="getDriftStatusClass()">
                {{ rebalancingData.drift_analysis?.drift_score?.toFixed(1) || '0.0' }}%
              </p>
              <p class="text-xs mt-1" :class="rebalancingData.drift_analysis?.needs_rebalancing ? 'text-amber-600 font-medium' : 'text-green-600'">
                {{ rebalancingData.drift_analysis?.needs_rebalancing ? 'Rebalancing Recommended' : 'On Track' }}
              </p>
            </div>

            <!-- Current vs Target (Top 2 Asset Classes) -->
            <div class="space-y-2">
              <div v-if="rebalancingData.current_allocation?.equities !== undefined" class="allocation-row">
                <div class="flex justify-between text-xs mb-1">
                  <span class="font-medium text-gray-700">Equities</span>
                  <span class="text-gray-500">
                    {{ formatAllocation(rebalancingData.current_allocation.equities) }}% → {{ formatAllocation(rebalancingData.target_allocation?.equities) }}%
                  </span>
                </div>
                <div class="h-2 bg-gray-200 rounded overflow-hidden relative">
                  <div
                    class="absolute h-full w-0.5 bg-gray-800 z-10"
                    :style="{ left: formatAllocation(rebalancingData.target_allocation?.equities) + '%' }"
                  ></div>
                  <div
                    class="h-full bg-blue-500 rounded"
                    :style="{ width: formatAllocation(rebalancingData.current_allocation.equities) + '%' }"
                  ></div>
                </div>
              </div>
              <div v-if="rebalancingData.current_allocation?.bonds !== undefined" class="allocation-row">
                <div class="flex justify-between text-xs mb-1">
                  <span class="font-medium text-gray-700">Bonds</span>
                  <span class="text-gray-500">
                    {{ formatAllocation(rebalancingData.current_allocation.bonds) }}% → {{ formatAllocation(rebalancingData.target_allocation?.bonds) }}%
                  </span>
                </div>
                <div class="h-2 bg-gray-200 rounded overflow-hidden relative">
                  <div
                    class="absolute h-full w-0.5 bg-gray-800 z-10"
                    :style="{ left: formatAllocation(rebalancingData.target_allocation?.bonds) + '%' }"
                  ></div>
                  <div
                    class="h-full bg-green-500 rounded"
                    :style="{ width: formatAllocation(rebalancingData.current_allocation.bonds) + '%' }"
                  ></div>
                </div>
              </div>
            </div>
          </div>

          <!-- Fees Summary Card -->
          <div
            class="insight-card cursor-pointer hover:shadow-md transition-shadow"
            @click="goToFeesTab"
          >
            <h4 class="text-sm font-semibold text-gray-900 mb-3">Total Fees</h4>

            <!-- Total Fee Percentage -->
            <div class="text-center p-3 rounded-lg" :class="getTotalFeeBgClass()">
              <p class="text-xs text-gray-600 mb-1">Annual Fee Rate</p>
              <p class="text-2xl font-bold" :class="getTotalFeeClass()">
                {{ formatPercentage(totalFeePercent) }}
              </p>
              <p class="text-xs text-gray-500 mt-1">
                {{ formatCurrency(totalAnnualFees) }} / year
              </p>
            </div>
          </div>
        </div>

        <!-- Chart Area (Right) -->
        <div class="chart-container">
          <!-- Projected Value Card -->
          <div class="bg-blue-50 rounded-lg p-4 mb-4">
            <div class="flex items-center justify-between mb-1">
              <p class="text-xs text-blue-600 uppercase tracking-wide">Projected Value (95%)</p>
              <select
                v-model="selectedProjectionYears"
                @change="updateProjectionData"
                class="px-2 py-1 text-xs border border-blue-200 rounded bg-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              >
                <option :value="5">5 Years</option>
                <option :value="10">10 Years</option>
                <option :value="20">20 Years</option>
                <option :value="30">30 Years</option>
              </select>
            </div>
            <p class="text-2xl font-bold text-blue-900">{{ formatProjectedValue95 }}</p>
            <p class="text-sm text-blue-600 mt-1">
              in {{ selectedProjectionYears }} years
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
          <div v-else class="bg-gray-50 border-2 border-dashed border-gray-300 rounded-lg p-8 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
            <p class="text-sm text-gray-600">{{ error || 'No projection data available' }}</p>
          </div>

          <!-- Asset Allocation Summary Card -->
          <div
            v-if="hasHoldings && assetAllocationSummary.length > 0"
            class="asset-allocation-card mt-4 cursor-pointer hover:shadow-md transition-shadow"
            @click="goToHoldingsTab"
          >
            <h4 class="text-sm font-semibold text-gray-900 mb-3">Asset Allocation</h4>
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
          <h4 class="text-sm font-semibold text-gray-900">Tax Treatment</h4>
          <p class="text-xs text-gray-500">{{ taxInfo.product_type_label }}</p>
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
            <span class="tax-legend-dot bg-green-500"></span>
            <span>Tax-Free</span>
          </div>
          <div class="tax-legend-item">
            <span class="tax-legend-dot bg-amber-500"></span>
            <span>Taxable</span>
          </div>
          <div class="tax-legend-item">
            <span class="tax-legend-dot bg-blue-500"></span>
            <span>Deferred</span>
          </div>
          <div class="tax-legend-item">
            <span class="tax-legend-dot bg-purple-500"></span>
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

  emits: ['change-tab'],

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
    };
  },

  computed: {
    hasProjectionData() {
      return this.projectionData?.year_by_year?.length > 0;
    },

    userShareValue() {
      if (this.account.ownership_type === 'joint') {
        const percentage = this.account.ownership_percentage ?? 50;
        return this.account.current_value * (percentage / 100);
      }
      return this.account.current_value;
    },

    formatProjectedValue95() {
      if (!this.hasProjectionData) return '--';
      const lastYear = this.projectionData.year_by_year[this.projectionData.year_by_year.length - 1];
      return this.formatCurrency(lastYear?.percentile_5);
    },

    years() {
      if (!this.projectionData?.year_by_year) return [];
      return this.projectionData.year_by_year.map(y => y.year);
    },

    series() {
      if (!this.hasProjectionData) return [];

      return [
        {
          name: '95% Probability',
          data: this.projectionData.year_by_year.map(y => Math.round(y.percentile_5)),
        },
        {
          name: '90% Probability',
          data: this.projectionData.year_by_year.map(y => Math.round(y.percentile_10)),
        },
        {
          name: '80% Probability',
          data: this.projectionData.year_by_year.map(y => Math.round(y.percentile_20)),
        },
      ];
    },

    chartOptions() {
      return {
        chart: {
          type: 'area',
          stacked: false,
          fontFamily: 'Inter, system-ui, sans-serif',
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
          zoom: {
            enabled: false,
          },
          animations: {
            enabled: true,
            easing: 'easeinout',
            speed: 800,
          },
        },
        colors: ['#1e3a5f', '#2563eb', '#60a5fa'],
        stroke: {
          curve: 'smooth',
          width: [2, 2, 2],
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
            width: 12,
            height: 12,
            radius: 2,
          },
        },
        grid: {
          borderColor: '#e5e7eb',
          strokeDashArray: 4,
        },
        dataLabels: {
          enabled: false,
        },
      };
    },

    // Fee computed properties
    platformFeePercent() {
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
          this.loadProjections();
          this.loadDiversification();
          this.loadRebalancing();
          this.loadTaxInfo();
        }
      },
    },
  },

  methods: {
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
        console.error('Error loading projections:', err);
        this.error = 'Failed to load projection data';
      } finally {
        this.loading = false;
        this.$nextTick(() => {
          setTimeout(() => {
            this.isChartReady = true;
          }, 100);
        });
      }
    },

    updateProjectionData() {
      if (!this.allProjections?.projections) return;

      const selectedData = this.allProjections.projections[this.selectedProjectionYears];
      if (selectedData) {
        this.projectionData = {
          year_by_year: selectedData.year_by_year,
          percentiles: selectedData.percentiles,
        };
        this.isChartReady = false;
        this.$nextTick(() => {
          setTimeout(() => {
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
        console.error('Error loading diversification:', err);
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
        case 'success': return 'text-green-600 bg-green-50 border-green-200';
        case 'warning': return 'text-amber-600 bg-amber-50 border-amber-200';
        case 'info': return 'text-blue-600 bg-blue-50 border-blue-200';
        default: return 'text-gray-600 bg-gray-50 border-gray-200';
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
        console.error('Error loading rebalancing:', err);
      } finally {
        this.loadingRebalancing = false;
      }
    },

    goToRebalancingTab() {
      this.$emit('change-tab', 'rebalancing');
    },

    getDriftStatusClass() {
      if (!this.rebalancingData?.drift_analysis) return 'text-gray-600';
      const score = this.rebalancingData.drift_analysis.drift_score;
      if (score < 5) return 'text-green-600';
      if (score < 10) return 'text-amber-600';
      return 'text-red-600';
    },

    getDriftBgClass() {
      if (!this.rebalancingData?.drift_analysis) return 'bg-gray-50';
      const score = this.rebalancingData.drift_analysis.drift_score;
      if (score < 5) return 'bg-green-50';
      if (score < 10) return 'bg-amber-50';
      return 'bg-red-50';
    },

    formatAllocation(value) {
      return (value || 0).toFixed(1);
    },

    goToFeesTab() {
      this.$emit('change-tab', 'fees');
    },

    getTotalFeeClass() {
      const fee = this.totalFeePercent;
      if (fee < 0.5) return 'text-green-600';
      if (fee < 1.0) return 'text-amber-600';
      return 'text-red-600';
    },

    getTotalFeeBgClass() {
      const fee = this.totalFeePercent;
      if (fee < 0.5) return 'bg-green-50';
      if (fee < 1.0) return 'bg-amber-50';
      return 'bg-red-50';
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
      const colors = {
        equity: '#2563eb',
        equities: '#2563eb',
        stock: '#2563eb',
        fixed_income: '#16a34a',
        bonds: '#16a34a',
        bond: '#16a34a',
        property: '#ea580c',
        real_estate: '#ea580c',
        commodities: '#eab308',
        cash: '#64748b',
        alternatives: '#db2777',
        fund: '#7c3aed',
        etf: '#0891b2',
        other: '#78716c',
      };
      return colors[type] || '#7c3aed';
    },

    async loadTaxInfo() {
      this.loadingTaxInfo = true;
      try {
        const response = await api.get(`/tax-info/investment/${this.account.account_type}`);
        this.taxInfo = response.data.data;
      } catch (err) {
        console.error('Error loading tax info:', err);
      } finally {
        this.loadingTaxInfo = false;
      }
    },

    goToTaxStatusTab() {
      this.$emit('change-tab', 'tax-status');
    },

    getTaxStatusBgClass(status) {
      const classes = {
        exempt: 'bg-green-500 border-green-500 text-white',
        taxable: 'bg-amber-500 border-amber-500 text-white',
        deferred: 'bg-blue-500 border-blue-500 text-white',
        relief: 'bg-purple-500 border-purple-500 text-white',
        limit: 'bg-gray-500 border-gray-500 text-white',
      };
      return classes[status] || 'bg-gray-500 border-gray-500 text-white';
    },

    getTaxStatusIconClass(status) {
      const classes = {
        exempt: 'bg-green-600 text-white',
        taxable: 'bg-amber-600 text-white',
        deferred: 'bg-blue-600 text-white',
        relief: 'bg-purple-600 text-white',
        limit: 'bg-gray-600 text-white',
      };
      return classes[status] || 'bg-gray-600 text-white';
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
  color: #111827;
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
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 16px;
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
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 16px;
}

.stacked-bar {
  display: flex;
  height: 28px;
  border-radius: 8px;
  overflow: hidden;
  margin-bottom: 12px;
  background-color: #e5e7eb;
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
  color: #374151;
}

.legend-value {
  font-size: 12px;
  font-weight: 600;
  color: #111827;
}

/* Tax Status Card Styles */
.tax-status-card {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 20px;
  margin-top: 24px;
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
  border-top: 1px solid #e5e7eb;
}

.tax-legend-item {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 11px;
  color: #6b7280;
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
