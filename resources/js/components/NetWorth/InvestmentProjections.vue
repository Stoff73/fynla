<template>
  <div v-if="account" class="investment-detail w-full max-w-full overflow-hidden">
    <!-- Back Button -->
    <button @click="handleBackClick" class="detail-inline-back mb-4">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
      </svg>
      {{ backButtonText }}
    </button>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin mx-auto"></div>
      <p class="mt-4 text-neutral-500">Loading account details...</p>
    </div>

    <!-- Specialized Detail Views -->
    <EmployeeShareSchemeDetail
      v-else-if="detailComponentType === 'employee-share-scheme'"
      :account="account"
    />

    <PrivateInvestmentDetail
      v-else-if="detailComponentType === 'private-investment'"
      :account="account"
    />

    <!-- Standard Account Content -->
    <div v-else class="space-y-6">
      <!-- ============================================ -->
      <!-- HEADER CARD -->
      <!-- ============================================ -->
      <div class="bg-white rounded-lg shadow-md p-4 sm:p-6 overflow-hidden">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4 max-w-full">
          <div>
            <div class="flex flex-wrap items-center gap-2 sm:gap-3 mb-2">
              <span :class="['badge', getOwnershipBadgeClass(account.ownership_type)]">
                {{ formatOwnershipType(account.ownership_type) }}
              </span>
              <span :class="['badge', accountTypeBadgeClass(account.account_type)]">
                {{ formatAccountType(account.account_type) }}
              </span>
            </div>
            <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold text-horizon-500">{{ account.provider }}</h1>
            <p class="text-base sm:text-lg text-neutral-500 mt-1">{{ account.account_name }}</p>
          </div>
          <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto shrink-0">
            <button
              v-preview-disabled="'edit'"
              @click="showEditModal = true"
              class="btn-primary whitespace-nowrap"
            >
              Edit
            </button>
            <button
              v-preview-disabled="'delete'"
              @click="confirmDelete"
              class="btn-danger whitespace-nowrap"
            >
              Delete
            </button>
          </div>
        </div>

        <!-- Key Metrics -->
        <div v-if="activeView === 'fees'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mt-6">
          <div class="bg-violet-50 rounded-lg p-4 border border-violet-200">
            <p class="text-sm text-neutral-500">Platform Fee</p>
            <p class="text-2xl font-bold text-violet-600">{{ platformFeeDisplay }}</p>
          </div>
          <div class="bg-violet-50 rounded-lg p-4 border border-violet-200">
            <p class="text-sm text-neutral-500">Average Fund Fee (OCF)</p>
            <p class="text-2xl font-bold text-violet-600">{{ weightedAverageOCF.toFixed(2) }}%</p>
          </div>
          <div class="bg-purple-50 rounded-lg p-4 border border-purple-200">
            <p class="text-sm text-neutral-500">Advisor Fee</p>
            <p class="text-2xl font-bold text-purple-600">{{ (advisorFeePercent || 0).toFixed(2) }}%</p>
          </div>
          <div class="bg-raspberry-50 rounded-lg p-4 border border-raspberry-200">
            <p class="text-sm text-neutral-500">Total Annual Cost</p>
            <p class="text-2xl font-bold text-raspberry-600">{{ totalFeePercent.toFixed(2) }}%</p>
            <p class="text-xs text-neutral-500 mt-1">{{ formatCurrency(totalAnnualFeeCost) }}/year</p>
          </div>
        </div>
        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mt-6">
          <div class="bg-violet-50 rounded-lg p-4 border border-violet-200">
            <p class="text-sm text-neutral-500">Current Value</p>
            <p class="text-2xl font-bold text-violet-600">{{ formatCurrency(account.current_value) }}</p>
            <p v-if="account.ownership_type === 'joint'" class="text-xs text-violet-600 mt-1">
              Your {{ account.ownership_percentage ?? 50 }}%: {{ formatCurrency(userShareValue) }}
            </p>
          </div>
          <div class="bg-savannah-100 rounded-lg p-4">
            <p class="text-sm text-neutral-500">Annualised Return</p>
            <p class="text-2xl font-bold" :class="getReturnColorClass(grossReturnPercent)">
              {{ formatReturnPercent(grossReturnPercent) }}
            </p>
            <p v-if="grossReturnPercent !== null" class="text-xs text-neutral-500 mt-1">
              {{ formatReturnPercent(netReturnPercent) }} net of fees
            </p>
          </div>
          <div class="bg-savannah-100 rounded-lg p-4">
            <p class="text-sm text-neutral-500">Monthly Contribution</p>
            <p class="text-2xl font-bold" :class="estimatedMonthlyContribution > 0 ? 'text-spring-600' : 'text-horizon-500'">
              {{ estimatedMonthlyContribution > 0 ? formatCurrency(estimatedMonthlyContribution) : '—' }}
            </p>
          </div>
          <!-- ISA Allowance (for ISA accounts) -->
          <div v-if="account.account_type === 'isa'" class="bg-spring-50 rounded-lg p-4 border border-spring-200">
            <p class="text-sm text-neutral-500">ISA Allowance Used</p>
            <p class="text-2xl font-bold text-spring-600">{{ formatCurrency(account.isa_subscription_current_year || 0) }}</p>
            <p class="text-xs text-neutral-500 mt-1">{{ formatCurrency(isaRemaining) }} remaining</p>
          </div>
          <!-- Joint Owner (for joint non-ISA accounts) -->
          <div v-else-if="account.ownership_type === 'joint' && account.joint_owner_name" class="bg-purple-50 rounded-lg p-4 border border-purple-200">
            <p class="text-sm text-neutral-500">Joint Owner</p>
            <p class="text-xl font-bold text-purple-700">{{ account.joint_owner_name }}</p>
            <p class="text-xs text-neutral-500 mt-1">{{ 100 - (account.ownership_percentage ?? 50) }}% share</p>
          </div>
          <!-- Holdings count (fallback) -->
          <div v-else class="bg-savannah-100 rounded-lg p-4">
            <p class="text-sm text-neutral-500">Holdings</p>
            <p class="text-2xl font-bold text-horizon-500">{{ holdingsCount }}</p>
          </div>
        </div>
      </div>

      <!-- ============================================ -->
      <!-- MAIN VIEW: Card Layout -->
      <!-- ============================================ -->
      <template v-if="activeView === 'main'">
        <!-- Two Column Layout -->
        <div class="top-section">
          <!-- LEFT: Monte Carlo Chart + Tax Treatment -->
          <div class="left-column">
          <div class="chart-card">
            <div class="chart-header">
              <h3 class="chart-title">Account Projection</h3>
              <div class="chart-header-right">
                <span v-if="projectionRiskLevel" class="risk-badge-corner">
                  {{ formatRiskLevel(projectionRiskLevel) }} Risk
                </span>
                <!-- Retirement-linked: fixed label -->
                <span
                  v-if="isIncludedInRetirement && yearsToRetirement"
                  class="px-2 py-1 text-xs bg-teal-100 text-teal-700 rounded font-medium"
                >
                  To Retirement ({{ yearsToRetirement }} yrs)
                </span>
                <!-- Non-retirement: dropdown -->
                <select
                  v-else
                  v-model="selectedProjectionYears"
                  @change="updateProjectionData"
                  class="period-selector"
                >
                  <option :value="5">5 Years</option>
                  <option :value="10">10 Years</option>
                  <option :value="20">20 Years</option>
                  <option :value="30">30 Years</option>
                </select>
              </div>
            </div>

            <!-- Summary Row -->
            <div class="summary-row">
              <div class="summary-item blue">
                <span class="summary-item-label">Current Value</span>
                <span class="summary-item-value">{{ formatCurrency(account.current_value) }}</span>
              </div>
              <div class="summary-item purple">
                <span class="summary-item-label">Projected Value (80%)</span>
                <span class="summary-item-value">{{ formatProjectedValue80 }}</span>
              </div>
            </div>

            <!-- Monte Carlo Chart -->
            <div v-if="dataLoading" class="flex justify-center items-center py-12">
              <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
              <span class="ml-3 text-neutral-500">Running Monte Carlo simulation...</span>
            </div>
            <div v-else-if="hasProjectionData">
              <apexchart
                v-if="isChartReady"
                type="area"
                :options="chartOptions"
                :series="series"
                height="400"
              />
            </div>
            <div v-else class="bg-eggshell-500 border-2 border-dashed border-horizon-300 rounded-lg p-8 text-center">
              <p class="text-sm text-neutral-500">{{ projectionError || 'No projection data available' }}</p>
            </div>
          </div>

          <!-- Tax Treatment Card (below Monte Carlo, left column) -->
          <div
            v-if="taxInfo"
            class="analysis-card clickable-card"
            @click="activeView = 'tax-status'"
          >
            <div class="mb-4">
              <h4 class="text-sm font-semibold text-horizon-500">Tax Treatment</h4>
              <p class="text-xs text-neutral-500">{{ taxInfo.product_type_label }}</p>
            </div>
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

          <!-- RIGHT: Analysis Cards -->
          <div class="analysis-panels">
            <!-- Holdings Card -->
            <div class="analysis-card clickable-card" @click="activeView = 'holdings'">
              <div class="analysis-header">
                <h3 class="analysis-title">Holdings ({{ holdingsCount }})</h3>
              </div>
              <div v-if="hasHoldings && hasAllocationData" class="donut-container">
                <apexchart
                  type="donut"
                  :options="allocationChartOptions"
                  :series="allocationSeries"
                  height="140"
                />
              </div>
              <div v-else class="no-allocation">
                <span>Add holdings to see allocation</span>
              </div>
            </div>

            <!-- Total Fees Card -->
            <div class="analysis-card clickable-card" @click="activeView = 'fees'">
              <div class="analysis-header">
                <h3 class="analysis-title">Total Fees</h3>
                <span class="fee-badge">{{ totalFeePercent.toFixed(2) }}%</span>
              </div>
              <div class="analysis-content">
                <div class="analysis-row">
                  <span class="row-label">Annual Fees</span>
                  <span class="row-value text-violet-600">{{ formatCurrency(totalAnnualFeeCost) }}/yr</span>
                </div>
                <div class="analysis-row">
                  <span class="row-label">Platform Fee</span>
                  <span class="row-value">{{ platformFeePercent.toFixed(2) }}%</span>
                </div>
                <div class="analysis-row">
                  <span class="row-label">Avg Fund Fee (OCF)</span>
                  <span class="row-value">{{ weightedAverageOCF.toFixed(2) }}%</span>
                </div>
                <div v-if="advisorFeePercent > 0" class="analysis-row">
                  <span class="row-label">Advisor Fee</span>
                  <span class="row-value">{{ advisorFeePercent.toFixed(2) }}%</span>
                </div>
              </div>
            </div>

            <!-- Diversification Insights Card -->
            <div
              class="analysis-card clickable-card"
              @click="hasHoldings ? activeView = 'diversification' : null"
            >
              <div class="analysis-header">
                <h3 class="analysis-title">Diversification Insights</h3>
              </div>
              <div v-if="!hasHoldings" class="text-center py-4">
                <p class="text-sm text-neutral-500">Add holdings to see insights</p>
              </div>
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

            <!-- Rebalancing Status Card -->
            <div
              class="analysis-card clickable-card"
              @click="hasHoldings ? activeView = 'rebalancing' : null"
            >
              <div class="analysis-header">
                <h3 class="analysis-title">Rebalancing Status</h3>
              </div>
              <div v-if="!hasHoldings" class="text-center py-4">
                <p class="text-sm text-neutral-500">Add holdings to see rebalancing</p>
              </div>
              <template v-else-if="rebalancingData">
                <div class="text-center p-3 rounded-lg mb-3" :class="getDriftBgClass()">
                  <p class="text-xs text-neutral-500 mb-1">Portfolio Drift</p>
                  <p class="text-lg font-bold" :class="getDriftStatusClass()">
                    {{ getDriftLabel() }}
                  </p>
                  <p class="text-xs mt-1" :class="rebalancingData.drift_analysis?.needs_rebalancing ? 'text-violet-600 font-medium' : 'text-spring-600'">
                    {{ rebalancingData.drift_analysis?.needs_rebalancing ? 'Rebalancing Recommended' : 'On Track' }}
                  </p>
                </div>
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
              <div v-else class="text-center py-4">
                <div class="w-6 h-6 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin mx-auto"></div>
                <p class="text-xs text-neutral-500 mt-2">Loading...</p>
              </div>
            </div>
          </div>
        </div>

      </template>

      <!-- ============================================ -->
      <!-- DRILL-DOWN VIEWS -->
      <!-- ============================================ -->
      <div v-else class="bg-white rounded-lg shadow-md">
        <div class="p-6">
          <AccountFeesPanel
            v-if="activeView === 'fees'"
            :account="account"
          />
          <AccountHoldingsPanel
            v-else-if="activeView === 'holdings'"
            :account="account"
            @open-holding-modal="openHoldingModal"
          />
          <DiversificationTab
            v-else-if="activeView === 'diversification'"
            :account-id="account.id"
            account-type="investment"
            @add-holdings="openHoldingModal(null)"
          />
          <AccountRebalancingPanel
            v-else-if="activeView === 'rebalancing'"
            :account="account"
          />
          <TaxStatusPanel
            v-else-if="activeView === 'tax-status'"
            product-category="investment"
            :product-type="account.account_type"
          />
          <AccountSummaryPanel
            v-else-if="activeView === 'overview'"
            :account="account"
            @add-holding="openHoldingModal(null)"
          />
        </div>
      </div>
    </div>

    <!-- Edit Modal -->
    <AccountForm
      :show="showEditModal"
      :account="account"
      :is-edit="true"
      @close="showEditModal = false"
      @save="handleUpdate"
    />

    <!-- Delete Confirmation -->
    <ConfirmDialog
      :show="showDeleteConfirm"
      title="Delete Investment Account"
      message="Are you sure you want to delete this investment account? This will also delete all associated holdings. This action cannot be undone."
      @confirm="handleDelete"
      @cancel="showDeleteConfirm = false"
    />

    <!-- Holding Form Modal -->
    <HoldingForm
      :show="showHoldingModal"
      :holding="editingHolding"
      :accounts="[account]"
      :default-account-id="account.id"
      @close="closeHoldingModal"
      @save="handleHoldingSave"
    />
  </div>
</template>

<script>
import { mapActions, mapState } from 'vuex';
import VueApexCharts from 'vue3-apexcharts';
import { currencyMixin } from '@/mixins/currencyMixin';
import { TAX_CONFIG } from '@/constants/taxConfig';
import { CHART_COLORS, ASSET_COLORS, PRIMARY_COLORS, SUCCESS_COLORS, BORDER_COLORS } from '@/constants/designSystem';

// Data loading services
import investmentService from '@/services/investmentService';
import diversificationService from '@/services/diversificationService';
import rebalancingService from '@/services/rebalancingService';
import api from '@/services/api';

// Modal components
import AccountForm from '@/components/Investment/AccountForm.vue';
import ConfirmDialog from '@/components/Common/ConfirmDialog.vue';
import HoldingForm from '@/components/Investment/HoldingForm.vue';

// Drill-down panels
import AccountSummaryPanel from '@/views/Investment/AccountSummaryPanel.vue';
import AccountHoldingsPanel from '@/views/Investment/AccountHoldingsPanel.vue';
import AccountFeesPanel from '@/views/Investment/AccountFeesPanel.vue';
import AccountRebalancingPanel from '@/views/Investment/AccountRebalancingPanel.vue';
import DiversificationTab from '@/components/Investment/DiversificationTab.vue';
import TaxStatusPanel from '@/components/Common/TaxStatusPanel.vue';

// Specialized detail views
import EmployeeShareSchemeDetail from '@/views/Investment/EmployeeShareSchemeDetail.vue';
import PrivateInvestmentDetail from '@/views/Investment/PrivateInvestmentDetail.vue';

import logger from '@/utils/logger';
export default {
  name: 'InvestmentProjections',

  mixins: [currencyMixin],

  components: {
    apexchart: VueApexCharts,
    AccountForm,
    ConfirmDialog,
    HoldingForm,
    AccountSummaryPanel,
    AccountHoldingsPanel,
    AccountFeesPanel,
    AccountRebalancingPanel,
    DiversificationTab,
    TaxStatusPanel,
    EmployeeShareSchemeDetail,
    PrivateInvestmentDetail,
  },

  props: {
    account: {
      type: Object,
      default: null,
    },
  },

  emits: ['back', 'deleted', 'updated', 'account-updated'],

  created() {
    if (!this.account) {
      this.$router.replace('/net-worth/investments');
    }
  },

  data() {
    return {
      activeView: 'main',
      loading: false,

      // Modals
      showEditModal: false,
      showDeleteConfirm: false,
      showHoldingModal: false,
      editingHolding: null,

      // Projection data
      allProjections: null,
      projectionData: null,
      selectedProjectionYears: 10,
      isChartReady: false,
      projectionError: null,
      dataLoading: true,
      renderTimeout: null,

      // Diversification
      recommendations: [],

      // Rebalancing
      rebalancingData: null,

      // Tax info
      taxInfo: null,
    };
  },

  computed: {
    ...mapState('auth', ['currentUser']),
    ...mapState('retirement', ['profile']),
    ...mapState('aiFormFill', ['pendingFill']),

    // ---- Account type guards ----
    detailComponentType() {
      const type = this.account.account_type;
      const employeeShareSchemes = ['saye', 'csop', 'emi', 'unapproved_options', 'rsu'];
      const privateInvestments = ['private_company', 'crowdfunding'];
      if (employeeShareSchemes.includes(type)) return 'employee-share-scheme';
      if (privateInvestments.includes(type)) return 'private-investment';
      return 'standard';
    },

    // ---- Back button ----
    backButtonText() {
      if (this.detailComponentType !== 'standard') return 'Back to Investments';
      if (this.activeView !== 'main') return `Back to ${this.account.provider || this.account.account_name || 'Account'}`;
      return 'Back to Investments';
    },

    // ---- Header metrics ----
    userShareValue() {
      if (this.account.ownership_type === 'joint') {
        const percentage = this.account.ownership_percentage ?? 50;
        return this.account.current_value * (percentage / 100);
      }
      return this.account.current_value;
    },

    holdingsCount() {
      return this.account.holdings?.length || 0;
    },

    hasHoldings() {
      return this.holdingsCount > 0;
    },

    estimatedMonthlyContribution() {
      if (this.account.monthly_contribution_amount > 0) {
        const amount = this.account.monthly_contribution_amount;
        const frequency = this.account.contribution_frequency || 'monthly';
        switch (frequency) {
          case 'quarterly': return amount / 3;
          case 'annually': return amount / 12;
          default: return amount;
        }
      }
      const ytd = this.account.contributions_ytd || 0;
      if (ytd <= 0) return 0;
      const now = new Date();
      const currentYear = now.getFullYear();
      const taxYearStart = new Date(currentYear, 3, 6);
      if (now < taxYearStart) taxYearStart.setFullYear(currentYear - 1);
      const monthsElapsed = Math.max(1, Math.ceil((now - taxYearStart) / (1000 * 60 * 60 * 24 * 30)));
      return ytd / monthsElapsed;
    },

    isaRemaining() {
      const contributions = this.account.isa_subscription_current_year || 0;
      return Math.max(0, TAX_CONFIG.ISA_ANNUAL_ALLOWANCE - contributions);
    },

    // ---- Fee computations ----
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

    platformFeeDisplay() {
      if (this.account.platform_fee_type === 'fixed') {
        const amount = parseFloat(this.account.platform_fee_amount) || 0;
        const freq = { monthly: '/month', quarterly: '/quarter', annually: '/year' };
        return `${this.formatCurrency(amount)}${freq[this.account.platform_fee_frequency] || '/year'}`;
      }
      return `${this.platformFeePercent.toFixed(2)}%`;
    },

    advisorFeePercent() {
      return parseFloat(this.account.advisor_fee_percent) || 0;
    },

    totalHoldingsValue() {
      if (!this.account.holdings?.length) return parseFloat(this.account.current_value) || 0;
      return this.account.holdings.reduce((sum, h) => sum + (parseFloat(h.current_value) || 0), 0);
    },

    weightedAverageOCF() {
      if (!this.account.holdings?.length || this.totalHoldingsValue === 0) return 0;
      const totalWeightedOCF = this.account.holdings.reduce((sum, h) => {
        return sum + ((parseFloat(h.current_value) || 0) * (parseFloat(h.ocf_percent) || 0));
      }, 0);
      return totalWeightedOCF / this.totalHoldingsValue;
    },

    totalFeePercent() {
      return this.platformFeePercent + this.advisorFeePercent + this.weightedAverageOCF;
    },

    totalAnnualFeeCost() {
      const accountValue = parseFloat(this.account.current_value) || 0;
      return accountValue * (this.totalFeePercent / 100);
    },

    // ---- Return computations ----
    totalCostBasis() {
      if (!this.account.holdings?.length) return 0;
      return this.account.holdings.reduce((sum, h) => {
        const costBasis = h.cost_basis || ((h.quantity || 0) * (h.purchase_price || 0)) || 0;
        return sum + costBasis;
      }, 0);
    },

    totalReturnPercent() {
      if (!this.totalCostBasis || this.totalCostBasis === 0) return null;
      return ((this.totalHoldingsValue - this.totalCostBasis) / this.totalCostBasis) * 100;
    },

    weightedHoldingPeriodYears() {
      if (!this.account.holdings?.length || this.totalHoldingsValue === 0) return 3;
      const now = new Date();
      let weightedDays = 0;
      let valueWithDates = 0;
      this.account.holdings.forEach(h => {
        if (h.purchase_date && h.current_value) {
          const purchaseDate = new Date(h.purchase_date);
          const daysDiff = (now - purchaseDate) / (1000 * 60 * 60 * 24);
          if (daysDiff > 0) {
            weightedDays += daysDiff * h.current_value;
            valueWithDates += h.current_value;
          }
        }
      });
      if (valueWithDates < this.totalHoldingsValue * 0.5) return 3;
      const avgDays = weightedDays / valueWithDates;
      return Math.max(avgDays / 365.25, 30 / 365.25);
    },

    grossReturnPercent() {
      if (this.totalReturnPercent === null) return null;
      const years = this.weightedHoldingPeriodYears;
      const totalReturn = this.totalReturnPercent / 100;
      if (years < 0.25) return (totalReturn / years) * 100;
      return (Math.pow(1 + totalReturn, 1 / years) - 1) * 100;
    },

    netReturnPercent() {
      if (this.grossReturnPercent === null) return null;
      return this.grossReturnPercent - this.totalFeePercent;
    },

    // ---- Projection computations ----
    isIncludedInRetirement() {
      return this.account.include_in_retirement === true;
    },

    yearsToRetirement() {
      const retirementAge = this.profile?.target_retirement_age || this.currentUser?.target_retirement_age || 68;
      const currentAge = this.currentUser?.date_of_birth
        ? Math.floor((new Date() - new Date(this.currentUser.date_of_birth)) / (365.25 * 24 * 60 * 60 * 1000))
        : null;
      if (!currentAge) return null;
      return Math.max(1, retirementAge - currentAge);
    },

    projectionRiskLevel() {
      return this.allProjections?.risk_level;
    },

    hasProjectionData() {
      return this.projectionData?.year_by_year?.length > 0;
    },

    formatProjectedValue80() {
      if (!this.hasProjectionData) return '—';
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
        { name: '90% Probability', data: this.projectionData.year_by_year.map(y => Math.round(y.percentile_10)) },
        { name: '85% Probability', data: this.projectionData.year_by_year.map(y => Math.round(y.percentile_15)) },
        { name: '80% Probability', data: this.projectionData.year_by_year.map(y => Math.round(y.percentile_20)) },
        { name: '75% Probability', data: this.projectionData.year_by_year.map(y => Math.round(y.percentile_25)) },
      ];
    },

    chartOptions() {
      return {
        chart: {
          type: 'area',
          stacked: false,
          fontFamily: 'Segoe UI, Inter, system-ui, sans-serif',
          toolbar: { show: true, tools: { download: true, selection: false, zoom: false, zoomin: false, zoomout: false, pan: false, reset: false } },
          zoom: { enabled: false },
          animations: { enabled: true, easing: 'easeinout', speed: 800 },
        },
        colors: [PRIMARY_COLORS[900], PRIMARY_COLORS[600], SUCCESS_COLORS[500], SUCCESS_COLORS[400]],
        stroke: { curve: 'smooth', width: [1, 1, 1, 1] },
        fill: { type: 'gradient', gradient: { opacityFrom: 0.5, opacityTo: 0.1, stops: [0, 90, 100] } },
        xaxis: {
          categories: this.years,
          title: { text: 'Year', style: { fontWeight: 600, fontSize: '12px' } },
          labels: { style: { fontSize: '11px' }, rotate: -45, rotateAlways: this.years.length > 15 },
          tickAmount: Math.min(this.years.length, 10),
        },
        yaxis: {
          title: { text: 'Investment Value', style: { fontWeight: 600, fontSize: '12px' } },
          labels: { formatter: (val) => this.formatCurrencyShort(val), style: { fontSize: '11px' } },
        },
        tooltip: { shared: true, intersect: false, y: { formatter: (val) => this.formatCurrency(val) } },
        legend: { position: 'top', horizontalAlign: 'center', fontSize: '12px', markers: { width: 10, height: 10, radius: 10 }, itemMargin: { horizontal: 12 } },
        grid: { borderColor: BORDER_COLORS.default, strokeDashArray: 4 },
        dataLabels: { enabled: false },
      };
    },

    // ---- Holdings donut chart ----
    assetAllocationSummary() {
      if (!this.hasHoldings) return [];
      const allocation = {};
      this.account.holdings.forEach(holding => {
        const value = parseFloat(holding.current_value || 0);
        const assetType = holding.asset_type || 'other';
        if (!allocation[assetType]) allocation[assetType] = 0;
        allocation[assetType] += value;
      });
      return Object.entries(allocation)
        .map(([type, value]) => ({ type, value, percentage: this.totalHoldingsValue > 0 ? (value / this.totalHoldingsValue) * 100 : 0 }))
        .sort((a, b) => b.percentage - a.percentage);
    },

    hasAllocationData() {
      return this.assetAllocationSummary.length > 0;
    },

    allocationSeries() {
      return this.assetAllocationSummary.map(a => a.percentage);
    },

    allocationLabels() {
      return this.assetAllocationSummary.map(a => this.formatAssetType(a.type));
    },

    allocationChartOptions() {
      return {
        chart: { type: 'donut', fontFamily: 'Segoe UI, Inter, system-ui, sans-serif', toolbar: { show: false } },
        labels: this.allocationLabels,
        colors: CHART_COLORS,
        plotOptions: { pie: { donut: { size: '60%', labels: { show: false } } } },
        dataLabels: { enabled: false },
        legend: {
          show: true, position: 'right', fontSize: '11px', fontWeight: 500,
          markers: { width: 8, height: 8, radius: 2 },
          itemMargin: { horizontal: 0, vertical: 2 },
          formatter: (seriesName, opts) => `${seriesName} ${opts.w.globals.series[opts.seriesIndex].toFixed(0)}%`,
        },
        tooltip: { enabled: true, y: { formatter: (val) => `${val.toFixed(1)}%` } },
        stroke: { width: 1, colors: ['#fff'] },
      };
    },
  },

  watch: {
    'account.id': {
      immediate: true,
      handler(newId) {
        if (newId) {
          this.activeView = 'main';
          this.setProjectionYearsForAccount();
          this.loadAccountData();
        }
      },
    },
    profile: {
      handler() {
        if (this.isIncludedInRetirement && this.yearsToRetirement) {
          this.setProjectionYearsForAccount();
          this.updateProjectionData();
        }
      },
    },
    pendingFill: {
      handler(fill) {
        if (fill && fill.entityType === 'investment_holding' && fill.fields?.investment_account_id === this.account?.id) {
          this.openHoldingModal(null);
        }
      },
      immediate: true,
    },
  },

  mounted() {
    if (!this.profile) {
      this.$store.dispatch('retirement/fetchRetirementData').catch(() => {});
    }
  },

  beforeUnmount() {
    if (this.renderTimeout) clearTimeout(this.renderTimeout);
  },

  methods: {
    ...mapActions('investment', ['updateAccount', 'deleteAccount', 'fetchInvestmentData', 'createHolding', 'updateHolding']),

    // ---- Navigation ----
    handleBackClick() {
      if (this.detailComponentType !== 'standard') { this.$emit('back'); return; }
      if (this.activeView !== 'main') { this.activeView = 'main'; return; }
      this.$emit('back');
    },

    // ---- Data loading ----
    async loadAccountData() {
      this.dataLoading = true;
      await Promise.all([
        this.loadProjections(),
        this.loadDiversification(),
        this.loadRebalancing(),
        this.loadTaxInfo(),
      ]);
      this.dataLoading = false;
    },

    async loadProjections() {
      this.isChartReady = false;
      this.projectionError = null;
      try {
        const response = await investmentService.getAccountProjections(this.account.id);
        if (response.success) {
          this.allProjections = response.data;
          this.updateProjectionData();
        } else {
          this.projectionError = response.message || 'Failed to load projections';
        }
      } catch (err) {
        logger.error('Error loading projections:', err);
        this.projectionError = 'Failed to load projection data';
      } finally {
        this.$nextTick(() => {
          if (this.renderTimeout) clearTimeout(this.renderTimeout);
          this.renderTimeout = setTimeout(() => { this.isChartReady = true; }, 100);
        });
      }
    },

    setProjectionYearsForAccount() {
      if (this.isIncludedInRetirement && this.yearsToRetirement) {
        this.selectedProjectionYears = this.yearsToRetirement;
      } else if (this.selectedProjectionYears === 'retirement') {
        this.selectedProjectionYears = 10;
      }
    },

    updateProjectionData() {
      if (!this.allProjections?.projections) return;
      const yearsKey = this.selectedProjectionYears;
      const availableYears = Object.keys(this.allProjections.projections).map(Number).sort((a, b) => a - b);
      let selectedYear = availableYears.find(y => y >= yearsKey) || availableYears[availableYears.length - 1];
      const selectedData = this.allProjections.projections[selectedYear];
      if (selectedData) {
        if (this.isIncludedInRetirement && this.yearsToRetirement && selectedYear > this.yearsToRetirement) {
          this.projectionData = { year_by_year: selectedData.year_by_year.slice(0, this.yearsToRetirement + 1), percentiles: selectedData.percentiles };
        } else {
          this.projectionData = { year_by_year: selectedData.year_by_year, percentiles: selectedData.percentiles };
        }
        this.isChartReady = false;
        this.$nextTick(() => {
          if (this.renderTimeout) clearTimeout(this.renderTimeout);
          this.renderTimeout = setTimeout(() => { this.isChartReady = true; }, 100);
        });
      }
    },

    async loadDiversification() {
      try {
        const response = await diversificationService.getAccountDiversification(this.account.id);
        if (response.success && response.data?.recommendations) {
          this.recommendations = response.data.recommendations;
        }
      } catch (err) {
        logger.error('Error loading diversification:', err);
      }
    },

    async loadRebalancing() {
      try {
        const response = await rebalancingService.getAccountRebalancing(this.account.id);
        if (response.success && response.data) {
          this.rebalancingData = response.data;
        }
      } catch (err) {
        logger.error('Error loading rebalancing:', err);
      }
    },

    async loadTaxInfo() {
      try {
        const response = await api.get(`/tax-info/investment/${this.account.account_type}`);
        this.taxInfo = response.data.data;
      } catch (err) {
        logger.error('Error loading tax info:', err);
      }
    },

    // ---- Format helpers ----
    formatCurrencyShort(value) {
      if (value === null || value === undefined) return '£0';
      if (value >= 1000000) return '£' + (value / 1000000).toFixed(1) + 'M';
      if (value >= 1000) return '£' + (value / 1000).toFixed(0) + 'K';
      return this.formatCurrency(value);
    },

    formatReturnPercent(value) {
      if (value === null || value === undefined) return 'N/A';
      const sign = value >= 0 ? '+' : '';
      return `${sign}${this.formatPercentage(value)}`;
    },

    formatRiskLevel(level) {
      const levels = { low: 'Low', lower_medium: 'Lower Medium', medium: 'Medium', upper_medium: 'Upper Medium', high: 'High' };
      return levels[level] || level;
    },

    formatAccountType(type) {
      const types = {
        'isa': 'Stocks & Shares ISA', 'sipp': 'Self-Invested Personal Pension', 'gia': 'General Investment Account',
        'pension': 'Pension', 'nsi': 'National Savings & Investments', 'onshore_bond': 'Onshore Bond',
        'offshore_bond': 'Offshore Bond', 'vct': 'Venture Capital Trust', 'eis': 'Enterprise Investment Scheme',
        'saye': 'Save As You Earn', 'csop': 'Company Share Option Plan', 'emi': 'Enterprise Management Incentive Options',
        'unapproved_options': 'Unapproved Options', 'rsu': 'Restricted Stock Units',
        'private_company': 'Private Company', 'crowdfunding': 'Crowdfunding', 'other': 'Other',
      };
      return types[type] || type;
    },

    formatOwnershipType(type) {
      return { individual: 'Individual', joint: 'Joint', trust: 'Trust' }[type] || 'Individual';
    },

    formatAssetType(type) {
      const types = {
        equity: 'Equity', equities: 'Equities', fixed_income: 'Fixed Income', bonds: 'Bonds',
        property: 'Property', real_estate: 'Real Estate', commodities: 'Commodities', cash: 'Cash',
        alternatives: 'Alternatives', fund: 'Fund', etf: 'ETF', stock: 'Stock', bond: 'Bond', other: 'Other',
      };
      return types[type] || type?.charAt(0).toUpperCase() + type?.slice(1).replace(/_/g, ' ') || 'Other';
    },

    formatAllocation(value) {
      return (value || 0).toFixed(1);
    },

    getReturnColorClass(value) {
      if (!value && value !== 0) return 'text-neutral-500';
      return value >= 0 ? 'text-spring-600' : 'text-neutral-500';
    },

    getOwnershipBadgeClass(type) {
      return { individual: 'badge-individual', joint: 'badge-joint', trust: 'badge-trust' }[type] || 'badge-individual';
    },

    accountTypeBadgeClass(type) {
      const classes = {
        isa: 'badge-isa', gia: 'badge-gia', sipp: 'badge-sipp', pension: 'badge-sipp', nsi: 'badge-nsi',
        onshore_bond: 'badge-bond', offshore_bond: 'badge-bond', vct: 'badge-vct', eis: 'badge-vct',
        saye: 'badge-employee-scheme', csop: 'badge-employee-scheme', emi: 'badge-employee-scheme',
        unapproved_options: 'badge-employee-scheme', rsu: 'badge-employee-scheme',
        private_company: 'badge-private', crowdfunding: 'badge-private', other: 'badge-other',
      };
      return classes[type] || 'badge-other';
    },

    // ---- Recommendation helpers ----
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

    // ---- Rebalancing helpers ----
    getDriftLabel() {
      if (!this.rebalancingData?.drift_analysis) return 'N/A';
      const score = this.rebalancingData.drift_analysis.drift_score;
      if (score < 5) return 'Well aligned';
      if (score < 10) return 'Minor drift';
      return 'Significant drift — review recommended';
    },

    getDriftStatusClass() {
      if (!this.rebalancingData?.drift_analysis) return 'text-neutral-500';
      const score = this.rebalancingData.drift_analysis.drift_score;
      if (score < 5) return 'text-spring-600';
      return 'text-violet-600';
    },

    getDriftBgClass() {
      if (!this.rebalancingData?.drift_analysis) return 'bg-eggshell-500';
      const score = this.rebalancingData.drift_analysis.drift_score;
      if (score < 5) return 'bg-spring-50';
      return 'bg-violet-50';
    },

    // ---- Tax status helpers ----
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
      return { exempt: '✓', taxable: '!', deferred: '⏱', relief: '↓', limit: '⊘' }[status] || '•';
    },

    // ---- Account CRUD ----
    confirmDelete() {
      this.showDeleteConfirm = true;
    },

    async handleUpdate(data) {
      try {
        await this.updateAccount({ id: this.account.id, accountData: data });
        this.showEditModal = false;
        const isPreview = this.$store.getters['preview/isPreviewMode'];
        if (isPreview) {
          this.$emit('account-updated', { ...this.account, ...data });
        } else {
          await this.fetchInvestmentData();
        }
        this.$emit('updated');
      } catch (error) {
        logger.error('Failed to update account:', error);
      }
    },

    async handleDelete() {
      try {
        await this.deleteAccount(this.account.id);
        this.showDeleteConfirm = false;
        this.$emit('deleted');
      } catch (error) {
        logger.error('Failed to delete account:', error);
        this.showDeleteConfirm = false;
        this.error = error.message || 'Failed to delete investment account. Please try again.';
      }
    },

    openHoldingModal(holding = null) {
      this.editingHolding = holding;
      this.showHoldingModal = true;
    },

    closeHoldingModal() {
      this.showHoldingModal = false;
      this.editingHolding = null;
    },

    async handleHoldingSave(holdingData) {
      try {
        if (holdingData.id) {
          await this.updateHolding({ id: holdingData.id, data: holdingData });
        } else {
          await this.createHolding(holdingData);
        }
        if (this.$store.state.aiFormFill.pendingFill) {
          this.$store.dispatch('aiFormFill/completeFill');
        }
        this.closeHoldingModal();
        await this.fetchInvestmentData();
        this.$emit('updated');
      } catch (error) {
        logger.error('Error saving holding:', error);
      }
    },
  },
};
</script>

<style scoped>
.investment-detail {
  animation: fadeIn 0.3s ease-out;
  padding: 24px;
  max-width: 1400px;
  margin: 0 auto;
}

/* Badges */
.badge {
  display: inline-block;
  padding: 4px 10px;
  font-size: 12px;
  font-weight: 600;
  border-radius: 6px;
}

.badge-individual { @apply bg-savannah-100 text-neutral-500; }
.badge-joint { @apply bg-purple-50 text-purple-600; }
.badge-trust { @apply bg-violet-100 text-violet-800; }
.badge-other { @apply bg-savannah-100 text-neutral-500; }
.badge-employee-scheme { @apply bg-teal-100 text-teal-800; }
.badge-private { @apply bg-slate-100 text-slate-800; }

/* Two Column Layout */
.top-section {
  display: grid;
  grid-template-columns: 1fr 340px;
  gap: 24px;
}

/* Left Column */
.left-column {
  display: flex;
  flex-direction: column;
  gap: 16px;
  min-width: 0;
}

/* Chart Card */
.chart-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  padding: 20px;
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
  @apply bg-violet-50 text-violet-600;
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

.summary-item.blue { @apply bg-violet-50; }
.summary-item.purple { @apply bg-purple-50; }

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

/* Analysis Cards */
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
  @apply border border-light-gray;
}

.clickable-card {
  cursor: pointer;
  transition: all 0.2s ease;
}

.clickable-card:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  @apply border-raspberry-500;
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
  @apply text-horizon-500;
  margin: 0;
}

.fee-badge {
  padding: 3px 8px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 600;
  @apply bg-violet-100 text-violet-800;
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
  @apply text-neutral-500;
}

.row-value {
  font-size: 13px;
  font-weight: 600;
  @apply text-horizon-500;
}

/* Donut chart container */
.donut-container {
  margin: 0 -8px;
}

.no-allocation {
  text-align: center;
  padding: 16px;
  @apply text-horizon-400;
  font-size: 12px;
}

/* Recommendation items */
.recommendation-item {
  transition: all 0.2s ease;
}

/* Allocation bars */
.allocation-row {
  margin-bottom: 4px;
}

/* Tax Status */
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

  .tax-items-grid {
    grid-template-columns: 1fr;
  }
}
</style>
