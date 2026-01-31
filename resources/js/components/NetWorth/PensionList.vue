<template>
  <div class="pension-list">
    <!-- Pension Detail View (when a pension is selected) -->
    <PensionDetailInline
      v-if="selectedPension"
      :pension="selectedPension"
      :pension-type="selectedPensionType"
      @back="clearSelection"
      @deleted="handlePensionDeleted"
      @pension-updated="handlePensionUpdated"
    />

    <!-- Main Dashboard View -->
    <template v-else>
      <!-- Header -->
      <div class="list-header">
        <div class="title-row">
          <h2 class="list-title">Pensions</h2>
          <router-link
            to="/risk-profile"
            class="risk-profile-link"
          >
            <svg class="risk-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
            Risk Profile
          </router-link>
        </div>
        <div v-if="activeTab === 'current'" class="header-buttons">
          <button v-preview-disabled="'add'" @click="showPensionForm = true" class="add-pension-button">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="button-icon">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            Add Pension
          </button>
          <button v-preview-disabled="'upload'" @click="showUploadModal = true" class="upload-button">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="button-icon">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
            </svg>
            Upload Statement
          </button>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="flex justify-center items-center py-12">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
      </div>

      <!-- Error State -->
      <div
        v-else-if="error"
        class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6"
      >
        <div class="flex">
          <div class="flex-shrink-0">
            <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
            </svg>
          </div>
          <div class="ml-3">
            <p class="text-sm text-red-700">{{ error }}</p>
          </div>
        </div>
      </div>

      <!-- Empty State - No Pensions -->
      <div v-else-if="allPensions.length === 0" class="empty-state">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="empty-icon">
          <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941" />
        </svg>
        <p>No pensions found</p>
        <p class="empty-subtitle">Add your first pension to track your retirement planning</p>
      </div>

      <!-- Current Pensions Tab - New 3-Column Layout -->
      <template v-else-if="activeTab === 'current'">
        <!-- Main 3-Column Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
          <!-- Left Panel - Pension Cards (3 cols) -->
          <div class="lg:col-span-3 pension-cards-column">
            <!-- DC Pensions -->
            <div
              v-for="pension in dcPensions"
              :key="'dc-' + pension.id"
              @click="selectPension(pension, 'dc')"
              class="pension-card-standalone"
            >
              <div class="card-header">
                <span class="badge badge-dc">{{ formatDCPensionType(pension.pension_type) }}</span>
              </div>
              <div class="card-content">
                <h4 class="pension-provider">{{ pension.scheme_name || 'DC Pension' }}</h4>
                <div class="pension-details">
                  <div class="detail-row">
                    <span class="detail-label">Current Value</span>
                    <span class="detail-value">{{ formatCurrency(pension.current_fund_value) }}</span>
                  </div>
                  <div v-if="pension.monthly_contribution_amount" class="detail-row">
                    <span class="detail-label">Monthly Contribution</span>
                    <span class="detail-value text-green-600">{{ formatCurrency(pension.monthly_contribution_amount) }}</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- DB Pensions -->
            <div
              v-for="pension in dbPensions"
              :key="'db-' + pension.id"
              @click="selectPension(pension, 'db')"
              class="pension-card-standalone"
            >
              <div class="card-header">
                <span class="badge badge-db">{{ formatDBPensionType(pension.scheme_type) }}</span>
              </div>
              <div class="card-content">
                <h4 class="pension-provider">{{ pension.scheme_name || 'DB Pension' }}</h4>
                <div class="pension-details">
                  <div class="detail-row">
                    <span class="detail-label">Annual Pension</span>
                    <span class="detail-value">{{ formatCurrency(pension.accrued_annual_pension) }}</span>
                  </div>
                  <div v-if="pension.lump_sum_entitlement" class="detail-row">
                    <span class="detail-label">Lump Sum</span>
                    <span class="detail-value text-purple-600">{{ formatCurrency(pension.lump_sum_entitlement) }}</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- State Pension -->
            <div
              v-if="statePension"
              @click="selectPension(statePension, 'state')"
              class="pension-card-standalone"
            >
              <div class="card-header">
                <span class="badge badge-state">State Pension</span>
              </div>
              <div class="card-content">
                <h4 class="pension-provider">UK State Pension</h4>
                <div class="pension-details">
                  <div class="detail-row">
                    <span class="detail-label">Annual Pension</span>
                    <span class="detail-value">{{ formatCurrency(statePension.state_pension_forecast_annual || 0) }}</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Retirement Income Planner Card -->
            <div class="income-card-standalone target clickable" @click="setActiveTab('income')">
              <div class="income-card-heading">Retirement Income Planner</div>
              <div class="income-card-label">Target Annual Income</div>
              <div class="income-card-value">{{ formatCurrency(targetIncome) }}</div>
              <div class="income-card-divider"></div>
              <div class="income-card-label">Required Capital</div>
              <div class="income-card-value-secondary">{{ formatCurrency(requiredCapitalValue) }}</div>
              <div class="income-card-sublabel">Based on 4.7% withdrawal rate</div>
              <div class="income-card-divider"></div>
              <div class="income-card-label">Projected Net Income</div>
              <div class="income-card-value-green">{{ formatCurrency(projectedNetIncome) }}</div>
              <div class="income-card-sublabel">After tax from all sources</div>
            </div>

            <!-- Fund Depletion Warning -->
            <div v-if="fundDepletionAge" class="depletion-warning-standalone">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
              </svg>
              <span>DC fund depletes at age {{ fundDepletionAge }}</span>
            </div>
          </div>

          <!-- Center/Right Panel - Projections & Strategies (9 cols) -->
          <div class="lg:col-span-9 space-y-6">
            <!-- Projections Loading -->
            <div v-if="projectionsLoading" class="projection-loading">
              <div class="spinner"></div>
              <p>Running Monte Carlo simulation...</p>
            </div>

            <!-- No DC Pensions for Projections -->
            <div v-else-if="!projections || !projections.pension_pot_projection?.dc_pension_count" class="empty-projections">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="empty-icon">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
              </svg>
              <p>Add DC pensions to see projections</p>
              <p class="empty-subtitle">Monte Carlo simulations show how your pension pot may grow over time</p>
            </div>

            <!-- Projections Content -->
            <template v-else>
              <!-- Monte Carlo Chart - Clickable to Income Planner -->
              <div class="chart-card clickable" @click="setActiveTab('income')">
                <div class="chart-header">
                  <h3 class="chart-title">Pension Pot Projection</h3>
                  <span class="risk-badge-corner">{{ formatRiskLevel(projections.pension_pot_projection?.risk_level) }} Risk</span>
                </div>
                <div class="summary-row three-col">
                  <div class="summary-item blue">
                    <span class="summary-item-label">Pension Pot Value</span>
                    <span class="summary-item-value">{{ formatCurrency(dcPensionValue) }}</span>
                  </div>
                  <div class="summary-item purple">
                    <span class="summary-item-label">Projected Value (80%)</span>
                    <span class="summary-item-value">{{ formatCurrency(projections.pension_pot_projection?.percentile_20_at_retirement) }}</span>
                  </div>
                  <div class="summary-item teal">
                    <div class="retirement-age-inline">
                      <div class="retirement-inline-item">
                        <span class="summary-item-label">Retirement Age</span>
                        <span class="summary-item-value">{{ projections.pension_pot_projection?.retirement_age }}</span>
                      </div>
                      <div class="retirement-inline-divider"></div>
                      <div class="retirement-inline-item">
                        <span class="summary-item-label">Years to Go</span>
                        <span class="summary-item-value">{{ projections.pension_pot_projection?.years_to_retirement }}</span>
                      </div>
                    </div>
                  </div>
                </div>
                <PensionPotProjectionChart
                  :data="projections.pension_pot_projection"
                  :risk-source="projections.pension_pot_projection?.risk_source"
                  :expected-return="projections.pension_pot_projection?.expected_return"
                  :risk-level="projections.pension_pot_projection?.risk_level"
                />
              </div>
            </template>
          </div>
        </div>

        <!-- Strategies Section - Full Width -->
        <div v-if="showStrategies && !projectionsLoading" class="strategies-section clickable" @click="setActiveTab('strategies')">
          <div class="strategies-header">
            <div class="strategies-header-left">
              <h3 class="strategies-title">Recommended Strategies</h3>
              <p class="strategies-subtitle">Actions to improve your retirement readiness</p>
            </div>
            <div class="view-more">
              <span>View all</span>
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
              </svg>
            </div>
          </div>

          <!-- Strategy Loading -->
          <div v-if="strategiesLoading" class="strategies-loading">
            <div class="spinner-small"></div>
            <span>Analysing strategies...</span>
          </div>

          <!-- DOB Required -->
          <div v-else-if="strategiesRequiresDob" class="dob-required-preview">
            <div class="dob-required-icon">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
              </svg>
            </div>
            <div class="dob-required-text">
              <h4>Date of Birth Required</h4>
              <p>Enter your date of birth in your profile to calculate pension strategies.</p>
            </div>
          </div>

          <!-- On Track Banner -->
          <div v-else-if="strategiesOnTrack" class="on-track-banner">
            <div class="on-track-icon">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <div class="on-track-text">
              <h4>You're On Track!</h4>
              <p>{{ strategiesProbability }}% probability of achieving your retirement goals</p>
            </div>
          </div>

          <!-- Strategy Summary -->
          <div v-else-if="applicableStrategies.length > 0" class="strategy-summary">
            <div class="strategy-summary-content">
              <div class="strategy-summary-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 18v-5.25m0 0a6.01 6.01 0 001.5-.189m-1.5.189a6.01 6.01 0 01-1.5-.189m3.75 7.478a12.06 12.06 0 01-4.5 0m3.75 2.383a14.406 14.406 0 01-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 10-7.517 0c.85.493 1.509 1.333 1.509 2.316V18" />
                </svg>
              </div>
              <div class="strategy-summary-text">
                <h4>{{ primaryStrategyMessage }}</h4>
                <p class="strategy-probability">
                  This would increase your success probability from
                  <span class="prob-current">{{ strategiesProbability }}%</span>
                  to
                  <span class="prob-projected">{{ primaryStrategyProbability }}%</span>
                </p>
              </div>
            </div>
            <div class="strategy-cta">
              <span>View strategies</span>
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
              </svg>
            </div>
          </div>
        </div>
      </template>

      <!-- Future Value Tab -->
      <FutureValueTab
        v-else-if="activeTab === 'future'"
        :projections="projections"
        :loading="projectionsLoading"
        @back="setActiveTab('current')"
        @show-income="setActiveTab('income')"
      />


      <!-- Strategies Tab -->
      <StrategiesTab
        v-else-if="activeTab === 'strategies'"
        @back="setActiveTab('current')"
      />

      <!-- Retirement Income Tab -->
      <RetirementIncomeTab
        v-else-if="activeTab === 'income'"
        @back="setActiveTab('current')"
        @add-state-pension="openStatePensionForm"
      />
    </template>

    <!-- Pension Form Modal -->
    <UnifiedPensionForm
      v-if="showPensionForm"
      :pension="editingPension"
      :state-pension="statePension"
      :is-edit="!!editingPension"
      :initial-pension-type="initialPensionType"
      @close="closePensionForm"
      @save="handlePensionSave"
    />

    <!-- Document Upload Modal -->
    <DocumentUploadModal
      v-if="showUploadModal"
      document-type="pension_statement"
      @close="showUploadModal = false"
      @saved="handleDocumentSaved"
      @manual-entry="showUploadModal = false; showPensionForm = true;"
    />

    <!-- Success/Error Messages -->
    <div v-if="successMessage" class="notification success">
      {{ successMessage }}
    </div>
    <div v-if="errorMessage" class="notification error">
      {{ errorMessage }}
    </div>
  </div>
</template>

<script>
import { mapState, mapActions } from 'vuex';
import PensionDetailInline from './PensionDetailInline.vue';
import UnifiedPensionForm from '@/components/Retirement/UnifiedPensionForm.vue';
import DocumentUploadModal from '@/components/Shared/DocumentUploadModal.vue';
import RiskBadge from '@/components/Shared/RiskBadge.vue';
import PensionPotProjectionChart from '@/components/Retirement/PensionPotProjectionChart.vue';
import FutureValueTab from '@/components/Retirement/FutureValueTab.vue';
import StrategiesTab from '@/components/Retirement/StrategiesTab.vue';
import RetirementIncomeTab from '@/components/Retirement/RetirementIncomeTab.vue';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'PensionList',

  mixins: [currencyMixin],

  components: {
    PensionDetailInline,
    UnifiedPensionForm,
    DocumentUploadModal,
    RiskBadge,
    PensionPotProjectionChart,
    FutureValueTab,
    StrategiesTab,
    RetirementIncomeTab,
  },

  data() {
    return {
      selectedPension: null,
      selectedPensionType: null,
      showPensionForm: false,
      showUploadModal: false,
      editingPension: null,
      initialPensionType: null,
      successMessage: null,
      errorMessage: null,
    };
  },

  computed: {
    ...mapState('retirement', [
      'dcPensions',
      'dbPensions',
      'statePension',
      'loading',
      'error',
      'projections',
      'projectionsLoading',
      'strategies',
      'strategiesLoading',
      'profile',
      'activeTab',
      'requiredCapital',
      'retirementIncome',
    ]),

    allPensions() {
      const all = [...this.dcPensions, ...this.dbPensions];
      if (this.statePension) {
        all.push(this.statePension);
      }
      return all;
    },

    dcPensionValue() {
      return this.dcPensions.reduce((sum, p) => sum + parseFloat(p.current_fund_value || 0), 0);
    },

    dbPensionIncome() {
      return this.dbPensions.reduce((sum, p) => sum + parseFloat(p.accrued_annual_pension || 0), 0);
    },

    statePensionForecast() {
      return parseFloat(this.statePension?.state_pension_forecast_annual || 0);
    },

    guaranteedIncome() {
      return this.dbPensionIncome + this.statePensionForecast;
    },

    targetIncome() {
      // Use centralised value from requiredCapital store (fetched from backend)
      if (this.requiredCapital?.required_income) {
        return this.requiredCapital.required_income;
      }
      // Fallback to projections or profile
      return this.projections?.income_drawdown?.target_income || this.profile?.target_retirement_income || 35000;
    },

    requiredCapitalValue() {
      // Use centralised value from requiredCapital store (fetched from backend)
      if (this.requiredCapital?.required_capital_at_retirement) {
        return this.requiredCapital.required_capital_at_retirement;
      }
      // Fallback: Calculate required capital based on 4.7% withdrawal rate
      const withdrawalRate = 0.047;
      return this.targetIncome / withdrawalRate;
    },

    projectedNetIncome() {
      // Get net income from retirement income planner (after tax)
      return this.retirementIncome?.tax_breakdown?.net_income || this.targetIncome;
    },

    incomeGap() {
      const sustainable = this.projections?.income_drawdown?.sustainable_income || 0;
      const total = this.guaranteedIncome + sustainable;
      return total - this.targetIncome;
    },

    incomeGapClass() {
      if (this.incomeGap >= 0) return 'surplus';
      return 'shortfall';
    },

    incomeGapLabel() {
      return this.incomeGap >= 0 ? 'Income Surplus' : 'Income Shortfall';
    },

    incomeGapDescription() {
      if (this.incomeGap >= 0) {
        return 'Above target income';
      }
      return 'Below target income';
    },

    fundDepletionAge() {
      return this.projections?.income_drawdown?.fund_depletion_age || null;
    },

    onTrackClass() {
      const status = this.projections?.income_drawdown?.on_track_status;
      if (status === 'Excellent' || status === 'On Track') return 'green';
      if (status === 'Needs Attention') return 'blue';
      return 'red';
    },

    showStrategies() {
      // Always show strategies section if we have projections
      return !!this.projections?.income_drawdown;
    },

    strategiesRequiresDob() {
      return this.strategies?.requires_dob === true;
    },

    strategiesOnTrack() {
      return (this.strategies?.current_status?.probability || 0) >= 95;
    },

    strategiesProbability() {
      return this.strategies?.current_status?.probability || 0;
    },

    applicableStrategies() {
      return this.strategies?.strategies?.filter(s => s.applicable) || [];
    },

    primaryStrategy() {
      return this.applicableStrategies[0] || null;
    },

    primaryStrategyMessage() {
      const strategy = this.primaryStrategy;
      if (!strategy) return 'No strategies available';

      // Generate human-readable message based on strategy type
      if (strategy.type === 'retirement_age') {
        const currentAge = strategy.current_value;
        const recommendedAge = strategy.recommended_value;
        const yearsDiff = recommendedAge - currentAge;
        if (yearsDiff > 0) {
          return `Retire ${yearsDiff} year${yearsDiff > 1 ? 's' : ''} later (at age ${recommendedAge}) to achieve your target`;
        }
        return strategy.description;
      }

      if (strategy.type === 'increase_contributions') {
        const additionalMonthly = strategy.impact?.additional_monthly || 0;
        return `Increase pension contributions by ${this.formatCurrency(additionalMonthly)}/month`;
      }

      if (strategy.type === 'income_target') {
        const recommendedIncome = strategy.recommended_value;
        return `Adjust your target retirement income to ${this.formatCurrency(recommendedIncome)}/year`;
      }

      // Fallback to the description from backend
      return strategy.description || strategy.title;
    },

    primaryStrategyProbability() {
      const strategy = this.primaryStrategy;
      return strategy?.impact?.new_probability || this.strategiesProbability;
    },
  },

  watch: {
    activeTab(newTab) {
      if (newTab === 'future' && !this.projections) {
        this.loadProjections();
      }
      // Scroll to top when switching to detail tabs
      if (newTab === 'strategies' || newTab === 'future' || newTab === 'income') {
        this.$nextTick(() => {
          window.scrollTo({ top: 0, behavior: 'instant' });
        });
      }
    },
  },

  methods: {
    ...mapActions('retirement', [
      'fetchRetirementData',
      'fetchProjections',
      'fetchStrategies',
      'fetchRequiredCapital',
      'fetchRetirementIncome',
      'createDCPension',
      'createDBPension',
      'updateStatePension',
      'setActiveTab',
    ]),
    ...mapActions('netWorth', ['setDetailView']),

    async loadProjections() {
      try {
        await this.fetchProjections();
      } catch (error) {
        console.error('Failed to load projections:', error);
      }
    },

    selectPension(pension, type) {
      this.selectedPension = pension;
      this.selectedPensionType = type;
      this.setDetailView(true);
    },

    clearSelection() {
      this.selectedPension = null;
      this.selectedPensionType = null;
      this.setDetailView(false);

      const isPreview = this.$store.getters['preview/isPreviewMode'];
      if (!isPreview) {
        this.fetchRetirementData();
      }
    },

    handlePensionDeleted() {
      this.selectedPension = null;
      this.selectedPensionType = null;
      this.setDetailView(false);
      this.fetchRetirementData();
      this.successMessage = 'Pension deleted successfully';
      setTimeout(() => {
        this.successMessage = null;
      }, 5000);
    },

    handlePensionUpdated(updatedPension) {
      this.selectedPension = updatedPension;
    },

    closePensionForm() {
      this.showPensionForm = false;
      this.editingPension = null;
      this.initialPensionType = null;
    },

    openStatePensionForm() {
      this.initialPensionType = 'state';
      this.editingPension = null;
      this.showPensionForm = true;
    },

    async handlePensionSave(data) {
      const pensionType = data._pensionType;
      delete data._pensionType;

      try {
        if (pensionType === 'state') {
          await this.updateStatePension(data);
        } else if (pensionType === 'dc') {
          await this.createDCPension(data);
        } else if (pensionType === 'db') {
          await this.createDBPension(data);
        }
        await this.fetchRetirementData();
        await this.loadProjectionsAndStrategies();
        this.successMessage = 'Pension saved successfully';
        setTimeout(() => {
          this.successMessage = null;
        }, 5000);
      } catch (error) {
        console.error('Failed to save pension:', error);
        this.errorMessage = 'Failed to save pension. Please try again.';
        setTimeout(() => {
          this.errorMessage = null;
        }, 5000);
      }

      this.closePensionForm();
    },

    async handleDocumentSaved() {
      this.showUploadModal = false;
      await this.fetchRetirementData();
      await this.loadProjectionsAndStrategies();
    },

    async loadProjectionsAndStrategies() {
      // Don't load projections/strategies if no pensions exist
      if (this.allPensions.length === 0) {
        return;
      }
      try {
        // Fetch projections, required capital, and retirement income in parallel
        await Promise.all([
          this.fetchProjections(),
          this.fetchRequiredCapital(),
          this.fetchRetirementIncome(),
        ]);
        if (this.showStrategies) {
          await this.fetchStrategies();
        }
      } catch (error) {
        console.error('Failed to load projections/strategies:', error);
      }
    },

    formatDCPensionType(type) {
      const types = {
        occupational: 'Occupational',
        sipp: 'SIPP',
        personal: 'Personal',
        stakeholder: 'Stakeholder',
        workplace: 'Workplace',
      };
      return types[type] || 'DC';
    },

    formatDBPensionType(type) {
      const types = {
        final_salary: 'Final Salary',
        career_average: 'Career Average',
        public_sector: 'Public Sector',
      };
      return types[type] || 'DB';
    },

    formatRiskLevel(level) {
      const levels = {
        low: 'Low',
        lower_medium: 'Lower-Medium',
        medium: 'Medium',
        upper_medium: 'Upper-Medium',
        high: 'High',
      };
      return levels[level] || 'Medium';
    },
  },

  async mounted() {
    this.setDetailView(false);
    await this.fetchRetirementData();
    await this.loadProjectionsAndStrategies();
  },
};
</script>

<style scoped>
.pension-list {
  padding: 24px;
}

.list-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
  flex-wrap: wrap;
  gap: 16px;
}

.title-row {
  display: flex;
  align-items: center;
  gap: 16px;
}

.list-title {
  font-size: 24px;
  font-weight: 700;
  @apply text-gray-900;
  margin: 0;
}

.risk-profile-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  @apply bg-blue-50;
  @apply text-blue-600;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 500;
  text-decoration: none;
  transition: all 0.2s;
}

.risk-profile-link:hover {
  @apply bg-blue-100;
}

.risk-icon {
  width: 16px;
  height: 16px;
}

.header-buttons {
  display: flex;
  gap: 12px;
}

.add-pension-button {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  @apply bg-primary-500;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}

.add-pension-button:hover {
  @apply bg-blue-600;
}

.upload-button {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  background: white;
  @apply text-primary-500;
  @apply border-2 border-primary-500;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.upload-button:hover {
  @apply bg-blue-50;
}

.button-icon {
  width: 20px;
  height: 20px;
}

/* Left Column - Pension Cards */
.pension-cards-column {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

/* Standalone Pension Cards */
.pension-card-standalone {
  position: relative;
  background: white;
  @apply border border-gray-200;
  border-radius: 8px;
  padding: 12px;
  cursor: pointer;
  transition: all 0.2s ease;
}


.pension-card-standalone:hover {
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
  @apply border-primary-500;
}

/* Empty Standalone State */
.empty-standalone {
  background: white;
  @apply border-2 border-dashed border-gray-300;
  border-radius: 12px;
  padding: 32px 20px;
  text-align: center;
}

.empty-standalone p {
  @apply text-gray-500;
  font-size: 14px;
  margin: 0 0 12px 0;
}

/* Standalone Income Cards */
.income-card-standalone {
  background: white;
  border-radius: 12px;
  padding: 16px;
  cursor: pointer;
  transition: all 0.15s;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.income-card-standalone:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  transform: translateY(-1px);
}

.income-card-standalone.target {
  background: linear-gradient(135deg, theme('colors.blue.50') 0%, theme('colors.blue.100') 100%);
  @apply border border-blue-200;
}

.income-card-standalone.surplus {
  background: linear-gradient(135deg, theme('colors.green.50') 0%, theme('colors.green.100') 100%);
  @apply border border-green-200;
}

.income-card-standalone.shortfall {
  background: linear-gradient(135deg, theme('colors.red.50') 0%, theme('colors.red.100') 100%);
  @apply border border-red-200;
}

.income-card-divider {
  height: 1px;
  background: rgba(0, 0, 0, 0.1);
  margin: 12px 0;
}

.income-card-value-secondary {
  font-size: 20px;
  font-weight: 700;
  @apply text-blue-800;
}

.depletion-warning-standalone {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  @apply bg-blue-100;
  @apply border border-blue-200;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.depletion-warning-standalone svg {
  width: 20px;
  height: 20px;
  @apply text-blue-700;
  flex-shrink: 0;
}

.depletion-warning-standalone span {
  font-size: 13px;
  @apply text-blue-800;
  font-weight: 500;
}

/* Card Header - matches investment cards */
.card-header {
  display: flex;
  justify-content: flex-start;
  align-items: center;
  margin-bottom: 6px;
  flex-wrap: wrap;
  gap: 6px;
}

.badge {
  display: inline-block;
  padding: 2px 6px;
  font-size: 9px;
  font-weight: 600;
  border-radius: 4px;
}

.badge-dc {
  @apply bg-blue-100;
  @apply text-blue-800;
}

.badge-db {
  @apply bg-purple-100;
  @apply text-purple-800;
}

.badge-state {
  @apply bg-green-100;
  @apply text-green-800;
}

/* Card Content - matches investment cards */
.card-content {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.pension-provider {
  font-size: 14px;
  font-weight: 600;
  @apply text-gray-900;
  margin: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* Pension Details - matches investment account-details */
.pension-details {
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin-top: 6px;
  padding-top: 8px;
  @apply border-t border-gray-200;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.detail-label {
  font-size: 11px;
  @apply text-gray-500;
}

.detail-value {
  font-size: 13px;
  @apply text-gray-900;
  font-weight: 600;
}

/* Empty Compact State */
.empty-compact {
  text-align: center;
  padding: 24px 16px;
  @apply bg-gray-50;
  border-radius: 8px;
  @apply border-2 border-dashed border-gray-300;
}

.empty-compact p {
  @apply text-gray-500;
  font-size: 14px;
  margin: 0 0 12px 0;
}

.add-first-btn {
  @apply bg-primary-500;
  color: white;
  border: none;
  padding: 8px 16px;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.add-first-btn:hover {
  @apply bg-blue-600;
}

/* Center Panel - Projections */
.projection-loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 80px 20px;
  background: white;
  border-radius: 12px;
  @apply border border-gray-200;
}

.spinner {
  width: 48px;
  height: 48px;
  @apply border-4 border-gray-200 border-t-primary-500;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-bottom: 16px;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.projection-loading p {
  @apply text-gray-500;
  font-size: 16px;
  margin: 0;
}

.empty-projections {
  text-align: center;
  padding: 80px 40px;
  background: white;
  border-radius: 12px;
  @apply border-2 border-dashed border-gray-300;
}

/* Full-width Empty State - matches investment list */
.empty-state {
  text-align: center;
  padding: 80px 40px;
  background: white;
  border-radius: 12px;
  @apply border-2 border-dashed border-gray-300;
}

.empty-state p {
  @apply text-gray-500;
  font-size: 18px;
  font-weight: 600;
  margin: 0 0 8px 0;
}

.empty-icon {
  width: 64px;
  height: 64px;
  @apply text-gray-400;
  margin: 0 auto 16px;
}

.empty-projections p {
  @apply text-gray-500;
  font-size: 18px;
  font-weight: 600;
  margin: 0 0 8px 0;
}

.empty-subtitle {
  @apply text-gray-400;
  font-size: 14px;
  font-weight: 400;
}

/* Clickable Cards */
.clickable {
  cursor: pointer;
  transition: all 0.2s;
}

.clickable:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  transform: translateY(-1px);
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

.risk-badge-corner {
  display: inline-block;
  padding: 4px 10px;
  @apply bg-blue-50;
  @apply text-blue-600;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
}


.chart-footer {
  font-size: 13px;
  @apply text-gray-500;
  text-align: center;
  margin: 16px 0 0 0;
}

.view-more {
  display: flex;
  align-items: center;
  gap: 4px;
  @apply text-primary-500;
  font-size: 14px;
  font-weight: 500;
}

.view-more svg {
  width: 16px;
  height: 16px;
}

.view-more-small {
  @apply text-gray-400;
}

.view-more-small svg {
  width: 20px;
  height: 20px;
}

.clickable:hover .view-more-small {
  @apply text-primary-500;
}

/* Summary Row */
.summary-row {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 16px;
  margin-bottom: 20px;
}

.summary-row.three-col {
  grid-template-columns: repeat(3, 1fr);
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

.summary-item.green {
  @apply bg-green-50;
}

.summary-item.blue {
  @apply bg-blue-50;
}

.summary-item.red {
  @apply bg-red-50;
}

.summary-item.teal {
  @apply bg-teal-50;
}

/* Inline Retirement Age */
.retirement-age-inline {
  display: flex;
  align-items: center;
  gap: 16px;
}

.retirement-inline-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.retirement-inline-divider {
  width: 1px;
  height: 32px;
  @apply bg-teal-300;
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

/* Strategies Section - Full Width */
.strategies-section {
  background: white;
  border-radius: 12px;
  padding: 24px;
  @apply border border-gray-200;
  margin-top: 24px;
}

.strategies-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 20px;
}

.strategies-header-left {
  flex: 1;
}

.strategies-title {
  font-size: 18px;
  font-weight: 600;
  @apply text-gray-900;
  margin: 0 0 4px 0;
}

.strategies-subtitle {
  font-size: 14px;
  @apply text-gray-500;
  margin: 0;
}

.strategies-loading {
  display: flex;
  align-items: center;
  gap: 12px;
  @apply text-gray-500;
  font-size: 14px;
  padding: 20px;
}

.spinner-small {
  width: 20px;
  height: 20px;
  @apply border-2 border-gray-200 border-t-primary-500;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

/* DOB Required Preview */
.dob-required-preview {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 20px;
  background: linear-gradient(135deg, theme('colors.blue.50') 0%, theme('colors.blue.100') 100%);
  @apply border border-blue-200;
  border-radius: 10px;
}

.dob-required-icon {
  @apply w-12 h-12 bg-blue-500 rounded-full flex items-center justify-center flex-shrink-0;
}

.dob-required-icon svg {
  width: 28px;
  height: 28px;
  color: white;
}

.dob-required-text h4 {
  font-size: 16px;
  font-weight: 700;
  @apply text-blue-800;
  margin: 0 0 4px 0;
}

.dob-required-text p {
  font-size: 14px;
  @apply text-blue-700;
  margin: 0;
}

.on-track-banner {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 20px;
  background: linear-gradient(135deg, theme('colors.green.50') 0%, theme('colors.green.100') 100%);
  @apply border border-green-200;
  border-radius: 10px;
}

.on-track-icon {
  @apply w-12 h-12 bg-green-500 rounded-full flex items-center justify-center flex-shrink-0;
}

.on-track-icon svg {
  width: 28px;
  height: 28px;
  color: white;
}

.on-track-text h4 {
  font-size: 16px;
  font-weight: 700;
  @apply text-green-800;
  margin: 0 0 4px 0;
}

.on-track-text p {
  font-size: 14px;
  @apply text-green-700;
  margin: 0;
}

/* Strategy Summary */
.strategy-summary {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  padding: 20px;
  background: linear-gradient(135deg, theme('colors.blue.50') 0%, theme('colors.blue.100') 100%);
  @apply border border-blue-200;
  border-radius: 10px;
}

.strategy-summary-content {
  display: flex;
  align-items: center;
  gap: 16px;
  flex: 1;
}

.strategy-summary-icon {
  width: 48px;
  height: 48px;
  @apply bg-primary-500;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.strategy-summary-icon svg {
  width: 26px;
  height: 26px;
  color: white;
}

.strategy-summary-text h4 {
  font-size: 16px;
  font-weight: 600;
  @apply text-blue-800;
  margin: 0 0 6px 0;
}

.strategy-probability {
  font-size: 14px;
  @apply text-primary-500;
  margin: 0;
}

.prob-current {
  font-weight: 600;
  @apply text-red-500;
}

.prob-projected {
  font-weight: 600;
  @apply text-green-500;
}

.strategy-cta {
  display: flex;
  align-items: center;
  gap: 6px;
  @apply text-blue-600;
  font-size: 14px;
  font-weight: 600;
  flex-shrink: 0;
}

.strategy-cta svg {
  width: 16px;
  height: 16px;
}

@media (max-width: 768px) {
  .strategy-summary {
    flex-direction: column;
    align-items: flex-start;
    gap: 16px;
  }

  .strategy-cta {
    align-self: flex-end;
  }
}

/* Right Panel - Income Sidebar */
.income-sidebar {
  background: white;
  border-radius: 12px;
  @apply border border-gray-200;
  padding: 20px;
  position: sticky;
  top: 100px;
}

.income-link {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 4px;
  @apply text-primary-500;
  font-size: 13px;
  font-weight: 500;
  margin-bottom: 12px;
}

.income-link svg {
  width: 16px;
  height: 16px;
}

.income-card {
  border-radius: 10px;
  padding: 16px;
  margin-bottom: 12px;
}

.income-card.target {
  background: linear-gradient(135deg, theme('colors.blue.50') 0%, theme('colors.blue.100') 100%);
  @apply border border-blue-200;
}

.income-card.guaranteed {
  background: linear-gradient(135deg, theme('colors.green.50') 0%, theme('colors.green.100') 100%);
  @apply border border-green-200;
}

.income-card.surplus {
  background: linear-gradient(135deg, theme('colors.green.50') 0%, theme('colors.green.100') 100%);
  @apply border border-green-200;
}

.income-card.shortfall {
  background: linear-gradient(135deg, theme('colors.red.50') 0%, theme('colors.red.100') 100%);
  @apply border border-red-200;
}

.income-card-heading {
  font-size: 14px;
  font-weight: 600;
  @apply text-blue-800;
  margin-bottom: 12px;
  padding-bottom: 8px;
  border-bottom: 1px solid rgba(0, 0, 0, 0.1);
}

.income-card-label {
  font-size: 13px;
  @apply text-gray-500;
  margin-bottom: 4px;
}

.income-card-value {
  font-size: 22px;
  font-weight: 700;
  @apply text-gray-900;
}

.income-card-value-green {
  font-size: 20px;
  font-weight: 700;
  @apply text-green-600;
}

.income-card-sublabel {
  font-size: 12px;
  @apply text-gray-500;
  margin-top: 4px;
}

.income-breakdown {
  margin-top: 12px;
  padding-top: 12px;
  border-top: 1px solid rgba(0, 0, 0, 0.1);
}

.breakdown-row {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
  @apply text-gray-700;
  margin-bottom: 4px;
}

.breakdown-row:last-child {
  margin-bottom: 0;
}

/* Depletion Warning */
.depletion-warning {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px;
  @apply bg-blue-100;
  @apply border border-blue-200;
  border-radius: 8px;
}

.depletion-warning svg {
  width: 20px;
  height: 20px;
  @apply text-blue-700;
  flex-shrink: 0;
}

.depletion-warning span {
  font-size: 13px;
  @apply text-blue-800;
  font-weight: 500;
}

/* Notifications */
.notification {
  position: fixed;
  top: 20px;
  right: 20px;
  padding: 16px 20px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  z-index: 100;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  animation: slideIn 0.3s ease-out;
}

.notification.success {
  @apply bg-green-500 text-white;
}

.notification.error {
  @apply bg-red-500 text-white;
}

@keyframes slideIn {
  from {
    transform: translateX(400px);
    opacity: 0;
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
}

/* Mobile responsive */
@media (max-width: 1024px) {
  .pension-sidebar,
  .income-sidebar {
    position: static;
  }
}

@media (max-width: 768px) {
  .pension-list {
    padding: 16px;
  }

  .list-header {
    flex-direction: column;
    align-items: flex-start;
  }

  .header-buttons {
    width: 100%;
    flex-direction: column;
  }

  .add-pension-button,
  .upload-button {
    width: 100%;
    justify-content: center;
  }

  .summary-row,
  .summary-row.three-col {
    grid-template-columns: 1fr;
  }
}
</style>
