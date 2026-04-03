<template>
  <div class="pension-list">
    <ModuleStatusBar />
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
      <!-- Loading State -->
      <div v-if="loading" class="flex justify-center items-center py-12">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-violet-600"></div>
      </div>

      <!-- Error State -->
      <div
        v-else-if="error"
        class="bg-raspberry-50 border border-raspberry-200 rounded-lg p-4 mb-6"
      >
        <div class="flex">
          <div class="flex-shrink-0">
            <svg class="h-5 w-5 text-raspberry-400" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
            </svg>
          </div>
          <div class="ml-3">
            <p class="text-sm text-raspberry-700">{{ error }}</p>
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
        <button v-preview-disabled="'add'" @click="editingPension = null; showPensionForm = true;" class="add-first-button">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
          </svg>
          Add Your First Pension
        </button>
      </div>

      <!-- Current Pensions Tab - New 3-Column Layout -->
      <template v-else-if="activeTab === 'current'">
        <!-- Full-width layout: Projections first, pension cards below -->
        <div class="space-y-6">
          <!-- Projections & Strategies -->
            <div v-if="projectionsLoading" class="projection-loading">
              <div class="w-12 h-12 border-4 border-light-gray border-t-raspberry-500 rounded-full animate-spin mb-4"></div>
              <p>Running Monte Carlo simulation...</p>
            </div>

            <!-- No DC Pensions - Show Guaranteed Income Summary Instead -->
            <div v-else-if="!projections || !projections.pension_pot_projection?.dc_pension_count" class="guaranteed-income-summary">
              <!-- If user has DB/State pensions, show detailed summary -->
              <template v-if="hasOnlyGuaranteedPensions">
                <div class="guaranteed-income-header">
                  <div class="guaranteed-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                    </svg>
                  </div>
                  <div>
                    <h3 class="guaranteed-title">Guaranteed Retirement Income</h3>
                    <p class="guaranteed-subtitle">Your secure pension income from Defined Benefit schemes and State Pension</p>
                  </div>
                </div>

                <!-- Total Guaranteed Income -->
                <div class="guaranteed-total">
                  <span class="guaranteed-total-label">Total Annual Income</span>
                  <span class="guaranteed-total-value">{{ formatCurrency(guaranteedIncome) }}/year</span>
                </div>

                <!-- Income Breakdown -->
                <div class="guaranteed-breakdown">
                  <!-- DB Pensions Detail -->
                  <div v-for="pension in dbPensions" :key="'detail-db-' + pension.id" class="guaranteed-item">
                    <div class="guaranteed-item-header">
                      <span class="badge badge-db">{{ formatDBPensionType(pension.scheme_type) }}</span>
                      <span class="guaranteed-item-name">{{ pension.scheme_name || 'Defined Benefit Pension' }}</span>
                    </div>
                    <div class="guaranteed-item-details">
                      <div class="guaranteed-detail-row">
                        <span>Annual Pension</span>
                        <span class="font-semibold">{{ formatCurrency(pension.accrued_annual_pension) }}</span>
                      </div>
                      <div v-if="pension.lump_sum_entitlement" class="guaranteed-detail-row">
                        <span>Tax-Free Lump Sum</span>
                        <span class="font-semibold text-purple-600">{{ formatCurrency(pension.lump_sum_entitlement) }}</span>
                      </div>
                      <div v-if="pension.normal_retirement_age" class="guaranteed-detail-row">
                        <span>Normal Retirement Age</span>
                        <span class="font-semibold">{{ pension.normal_retirement_age }}</span>
                      </div>
                      <div v-if="pension.spouse_pension_percentage" class="guaranteed-detail-row">
                        <span>Spouse Pension</span>
                        <span class="font-semibold">{{ pension.spouse_pension_percentage }}%</span>
                      </div>
                    </div>
                  </div>

                  <!-- State Pension Detail -->
                  <div v-if="statePension" class="guaranteed-item">
                    <div class="guaranteed-item-header">
                      <span class="badge badge-state">State Pension</span>
                      <span class="guaranteed-item-name">UK State Pension</span>
                    </div>
                    <div class="guaranteed-item-details">
                      <div class="guaranteed-detail-row">
                        <span>Annual Pension</span>
                        <span class="font-semibold">{{ formatCurrency(statePension.state_pension_forecast_annual || 0) }}</span>
                      </div>
                      <div v-if="statePension.state_pension_age" class="guaranteed-detail-row">
                        <span>State Pension Age</span>
                        <span class="font-semibold">{{ statePension.state_pension_age }}</span>
                      </div>
                      <div v-if="statePension.ni_years" class="guaranteed-detail-row">
                        <span>National Insurance Years</span>
                        <span class="font-semibold">{{ statePension.ni_years }} years</span>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Income vs Need -->
                <div v-if="targetIncome > 0" class="guaranteed-comparison">
                  <div class="comparison-row">
                    <span>Income Need</span>
                    <span class="font-semibold">{{ formatCurrency(targetIncome) }}/year</span>
                  </div>
                  <div class="comparison-row" :class="guaranteedIncome >= targetIncome ? 'text-spring-600' : 'text-violet-600'">
                    <span>{{ guaranteedIncome >= targetIncome ? 'Surplus' : 'Shortfall' }}</span>
                    <span class="font-semibold">{{ formatCurrency(Math.abs(guaranteedIncome - targetIncome)) }}/year</span>
                  </div>
                </div>
              </template>

              <!-- No pensions at all - show add message -->
              <template v-else>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="empty-icon">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                </svg>
                <p>Add Defined Contribution pensions to see projections</p>
                <p class="empty-subtitle">Monte Carlo simulations show how your pension pot may grow over time</p>
              </template>
            </div>

            <!-- Projections Content -->
            <template v-else>
              <!-- Retirement Planner Cards Row -->
              <div class="planner-cards-row">
                <!-- Retirement Income Planner Card -->
                <div class="planner-card income clickable module-gradient" @click="setActiveTab('income')">
                  <div class="planner-card-header">
                    <div class="planner-card-icon income">
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
                      </svg>
                    </div>
                    <h3 class="planner-card-title">Will I have enough income for retirement?</h3>
                  </div>
                  <div class="planner-card-metrics">
                    <div class="planner-metric">
                      <span class="planner-metric-label">Target Income</span>
                      <span class="planner-metric-value">{{ formatCurrency(targetIncome) }}</span>
                    </div>
                    <div class="planner-metric">
                      <span class="planner-metric-label">Projected Gross Income</span>
                      <span class="planner-metric-value" :class="projectedNetIncome >= targetIncome * 0.9 ? 'green' : 'red'">{{ formatCurrency(projectedNetIncome) }}</span>
                    </div>
                  </div>
                </div>

                <!-- Capital Adequacy Planner Card -->
                <div class="planner-card capital clickable module-gradient" @click="setActiveTab('capital')">
                  <div class="planner-card-header">
                    <div class="planner-card-icon capital">
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                      </svg>
                    </div>
                    <h3 class="planner-card-title">Am I saving enough for retirement?</h3>
                  </div>
                  <div class="planner-card-metrics">
                    <div class="planner-metric">
                      <span class="planner-metric-label">Required Capital</span>
                      <span class="planner-metric-value">{{ formatCurrency(requiredCapitalValue) }}</span>
                    </div>
                    <div class="planner-metric">
                      <span class="planner-metric-label">Projected Capital</span>
                      <span class="planner-metric-value" :class="projectedCapitalClass">{{ formatCurrency(projectedCapitalValue) }}</span>
                    </div>
                  </div>
                </div>

                <!-- Drawdown Strategy Card (shown when within 10 years of retirement) -->
                <div v-if="showDrawdownCard" class="planner-card drawdown clickable module-gradient" @click="setActiveTab('drawdown')">
                  <div class="planner-card-header">
                    <div class="planner-card-icon drawdown">
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                      </svg>
                    </div>
                    <h3 class="planner-card-title">How should I draw down my pension?</h3>
                  </div>
                  <div class="planner-card-metrics">
                    <div class="planner-metric">
                      <span class="planner-metric-label">Pension Pot</span>
                      <span class="planner-metric-value">{{ formatCurrency(dcPensionValue) }}</span>
                    </div>
                    <div class="planner-metric">
                      <span class="planner-metric-label">Years to Retirement</span>
                      <span class="planner-metric-value">{{ yearsToRetirementComputed }}</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Two-column layout: Pension cards left, Monte Carlo right -->
              <div class="pension-chart-layout">

              <!-- Pension Cards (left column) -->
              <div class="pension-cards-column">
                <!-- DC Pensions -->
                <div
                  v-for="pension in dcPensions"
                  :key="'dc-' + pension.id"
                  @click="selectPension(pension, 'dc')"
                  class="pension-card-standalone module-gradient"
                >
                  <div class="card-header">
                    <span class="badge badge-dc">{{ formatDCPensionType(pension.pension_type) }}</span>
                  </div>
                  <div class="card-content">
                    <h4 class="pension-provider">{{ pension.scheme_name || 'Defined Contribution Pension' }}</h4>
                    <div class="pension-details">
                      <div class="detail-row">
                        <span class="detail-label">Current Value</span>
                        <span class="detail-value">{{ formatCurrency(pension.current_fund_value) }}</span>
                      </div>
                      <div v-if="pension.monthly_contribution_amount" class="detail-row">
                        <span class="detail-label">Monthly Contribution</span>
                        <span class="detail-value text-spring-600">{{ formatCurrency(pension.monthly_contribution_amount) }}</span>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- DB Pensions -->
                <div
                  v-for="pension in dbPensions"
                  :key="'db-' + pension.id"
                  @click="selectPension(pension, 'db')"
                  class="pension-card-standalone module-gradient"
                >
                  <div class="card-header">
                    <span class="badge badge-db">{{ formatDBPensionType(pension.scheme_type) }}</span>
                  </div>
                  <div class="card-content">
                    <h4 class="pension-provider">{{ pension.scheme_name || 'Defined Benefit Pension' }}</h4>
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
                  class="pension-card-standalone module-gradient"
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

                <!-- Fund Depletion Warning -->
                <div v-if="fundDepletionAge && hasDCPensions && !isRetired" class="depletion-warning-standalone">
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                  </svg>
                  <span>Defined Contribution fund depletes at age {{ fundDepletionAge }}</span>
                </div>
              </div>

              <!-- Monte Carlo Chart (right column) -->
              <div class="chart-card module-gradient">
                <div class="chart-header">
                  <h3 class="chart-title">Pension Pot Projection <span class="text-sm font-normal">(using high probability of 80% of achieving {{ projections.pension_pot_projection?.expected_return }}% returns)</span></h3>
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
                  :life-events="projections.life_events_applied || []"
                />
              </div>
              </div>
            </template>
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


      <!-- Retirement Income Tab -->
      <RetirementIncomeTab
        v-else-if="activeTab === 'income'"
        @back="setActiveTab('current')"
        @add-state-pension="openStatePensionForm"
      />

      <!-- Capital Adequacy Tab -->
      <CapitalAdequacyTab
        v-else-if="activeTab === 'capital'"
        @back="setActiveTab('current')"
      />

      <!-- Drawdown Strategy Tab -->
      <DecumulationStrategyCard
        v-else-if="activeTab === 'drawdown'"
        @back="setActiveTab('current')"
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
    <div v-if="successMessage" class="notification success animate-slide-in-right">
      {{ successMessage }}
    </div>
    <div v-if="errorMessage" class="notification error animate-slide-in-right">
      {{ errorMessage }}
    </div>
  </div>
</template>

<script>
import { mapState, mapActions, mapGetters } from 'vuex';
import PensionDetailInline from './PensionDetailInline.vue';
import UnifiedPensionForm from '@/components/Retirement/UnifiedPensionForm.vue';
import DocumentUploadModal from '@/components/Shared/DocumentUploadModal.vue';
import RiskBadge from '@/components/Shared/RiskBadge.vue';
import PensionPotProjectionChart from '@/components/Retirement/PensionPotProjectionChart.vue';
import FutureValueTab from '@/components/Retirement/FutureValueTab.vue';
import RetirementIncomeTab from '@/components/Retirement/RetirementIncomeTab.vue';
import CapitalAdequacyTab from '@/components/Retirement/CapitalAdequacyTab.vue';
import DecumulationStrategyCard from '@/components/Retirement/DecumulationStrategyCard.vue';
import ModuleStatusBar from '@/components/Shared/ModuleStatusBar.vue';
import { currencyMixin } from '@/mixins/currencyMixin';

import logger from '@/utils/logger';
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
    RetirementIncomeTab,
    CapitalAdequacyTab,
    DecumulationStrategyCard,
    ModuleStatusBar,
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
      successTimeout: null,
      errorTimeout: null,
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
      'profile',
      'activeTab',
      'requiredCapital',
      'retirementIncome',
    ]),
    ...mapGetters('auth', ['currentUser']),
    ...mapGetters('subNav', ['pendingAction', 'actionCounter']),

    // Check if user is retired
    isRetired() {
      return this.currentUser?.employment_status === 'retired';
    },

    // Years to retirement (for drawdown card visibility)
    yearsToRetirementComputed() {
      if (!this.profile?.target_retirement_age || !this.profile?.current_age) return null;
      return Math.max(0, this.profile.target_retirement_age - this.profile.current_age);
    },

    // Show drawdown card when within 10 years of retirement and has DC pensions
    showDrawdownCard() {
      return this.hasDCPensions && this.yearsToRetirementComputed !== null && this.yearsToRetirementComputed <= 10;
    },

    // Check if user has any DC pensions
    hasDCPensions() {
      return this.dcPensions && this.dcPensions.length > 0;
    },

    // Check if user has only DB/State pensions (no DC)
    hasOnlyGuaranteedPensions() {
      return !this.hasDCPensions && (this.dbPensions?.length > 0 || this.statePension);
    },

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
      // Use gross income from income drawdown projection (first year: DC + DB + State Pension)
      const firstYear = this.projections?.income_drawdown?.yearly_income?.[0];
      if (firstYear) {
        return (firstYear.total_income || 0) + (firstYear.state_pension || 0) + (firstYear.db_pension || 0);
      }
      return this.targetIncome;
    },

    projectedCapitalValue() {
      // Get projected pension pot at retirement (80% confidence from Monte Carlo)
      return this.projections?.pension_pot_projection?.percentile_20_at_retirement || 0;
    },

    projectedCapitalClass() {
      // Green if projected meets/exceeds required, red if shortfall
      if (this.projectedCapitalValue >= this.requiredCapitalValue) {
        return 'green';
      }
      return 'red';
    },

    allowanceUsedThisYear() {
      // Calculate pension contributions made this tax year
      // Sum of all DC pension contributions (annual basis)
      // Includes both percentage-based (occupational) and flat monthly contributions
      return this.dcPensions.reduce((sum, p) => {
        // Percentage-based contributions (occupational pensions)
        const salary = parseFloat(p.annual_salary || 0);
        const employeePercent = parseFloat(p.employee_contribution_percent || 0);
        const employerPercent = parseFloat(p.employer_contribution_percent || 0);
        const percentBasedAnnual = salary * (employeePercent + employerPercent) / 100;

        // Flat monthly contributions (personal pensions, SIPPs)
        const monthlyFlat = parseFloat(p.monthly_contribution_amount || 0);
        const flatAnnual = monthlyFlat * 12;

        return sum + percentBasedAnnual + flatAnnual;
      }, 0);
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

  },

  watch: {
    actionCounter() {
      if (this.pendingAction === 'addPension') {
        this.showPensionForm = true;
        this.$store.dispatch('subNav/consumeCta');
      } else if (this.pendingAction === 'uploadStatement') {
        this.showUploadModal = true;
        this.$store.dispatch('subNav/consumeCta');
      }
    },
    activeTab(newTab) {
      if (newTab === 'future' && !this.projections) {
        this.loadProjections();
      }
      // Scroll to top when switching to detail tabs
      if (newTab === 'future' || newTab === 'income' || newTab === 'capital') {
        this.$nextTick(() => {
          window.scrollTo({ top: 0, behavior: 'instant' });
        });
      }
    },
    '$store.state.aiFormFill.pendingFill'(fill) {
      if (fill && (fill.entityType === 'dc_pension' || fill.entityType === 'db_pension')) {
        if (fill.mode === 'edit' && fill.entityId) {
          const pensions = fill.entityType === 'dc_pension' ? this.dcPensions : this.dbPensions;
          const record = pensions.find(p => p.id === fill.entityId);
          if (record) {
            this.editingPension = record;
            this.initialPensionType = fill.entityType === 'dc_pension' ? 'dc' : 'db';
            this.showPensionForm = true;
          }
        } else {
          this.editingPension = null;
          this.initialPensionType = fill.entityType === 'dc_pension' ? 'dc' : 'db';
          this.showPensionForm = true;
        }
      }
    },
  },

  beforeUnmount() {
    if (this.successTimeout) clearTimeout(this.successTimeout);
    if (this.errorTimeout) clearTimeout(this.errorTimeout);
  },

  methods: {
    ...mapActions('retirement', [
      'fetchRetirementData',
      'fetchProjections',
      'fetchStrategies',
      'fetchRequiredCapital',
      'fetchRetirementIncome',
      'createDCPension',
      'updateDCPension',
      'createDBPension',
      'updateDBPension',
      'updateStatePension',
      'setActiveTab',
    ]),
    ...mapActions('netWorth', ['setDetailView']),

    async loadProjections() {
      try {
        await this.fetchProjections();
      } catch (error) {
        logger.error('Failed to load projections:', error);
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
      if (this.successTimeout) clearTimeout(this.successTimeout);
      this.successTimeout = setTimeout(() => {
        this.successMessage = null;
      }, 5000);
    },

    handlePensionUpdated(updatedPension) {
      this.selectedPension = updatedPension;
    },

    closePensionForm() {
      if (this.$store.state.aiFormFill.pendingFill) {
        this.$store.dispatch('aiFormFill/cancelFill');
      }
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
          if (this.editingPension) {
            await this.updateDCPension({ id: this.editingPension.id, data });
          } else {
            await this.createDCPension(data);
          }
        } else if (pensionType === 'db') {
          if (this.editingPension) {
            await this.updateDBPension({ id: this.editingPension.id, data });
          } else {
            await this.createDBPension(data);
          }
        }

        // Complete AI fill if this was an AI-driven save
        if (this.$store.state.aiFormFill.pendingFill) {
          this.$store.dispatch('aiFormFill/completeFill');
        }

        await this.fetchRetirementData();
        await this.loadProjectionsAndStrategies();
        this.successMessage = 'Pension saved successfully';
        if (this.successTimeout) clearTimeout(this.successTimeout);
        this.successTimeout = setTimeout(() => {
          this.successMessage = null;
        }, 5000);
      } catch (error) {
        logger.error('Failed to save pension:', error);
        this.errorMessage = 'Failed to save pension. Please try again.';
        if (this.errorTimeout) clearTimeout(this.errorTimeout);
        this.errorTimeout = setTimeout(() => {
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
      } catch (error) {
        logger.error('Failed to load projections:', error);
      }
    },

    formatDCPensionType(type) {
      const types = {
        occupational: 'Work Pension',
        sipp: 'Self-Invested Personal Pension',
        personal: 'Personal',
        stakeholder: 'Stakeholder',
        workplace: 'Workplace',
      };
      return types[type] || 'Defined Contribution';
    },

    formatDBPensionType(type) {
      const types = {
        final_salary: 'Final Salary',
        career_average: 'Career Average',
        public_sector: 'Public Sector',
      };
      return types[type] || 'Defined Benefit';
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
    // Check for pendingFill that was set before this component mounted
    const fill = this.$store.state.aiFormFill?.pendingFill;
    if (fill && (fill.entityType === 'dc_pension' || fill.entityType === 'db_pension') && fill.mode !== 'edit') {
      this.editingPension = null;
      this.initialPensionType = fill.entityType === 'dc_pension' ? 'dc' : 'db';
      this.showPensionForm = true;
    }

    this.setDetailView(false);
    await this.fetchRetirementData();
    await this.loadProjectionsAndStrategies();
  },
};
</script>

<style scoped>
.pension-list {
  padding: 24px;
  @apply bg-eggshell-500;
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
  @apply text-horizon-500;
  margin: 0;
}

.risk-profile-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  @apply bg-violet-50;
  @apply text-violet-600;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 500;
  text-decoration: none;
  transition: all 0.2s;
}

.risk-profile-link:hover {
  @apply bg-violet-100;
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
  @apply bg-raspberry-500;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}

.add-pension-button:hover {
  @apply bg-raspberry-500;
}

.upload-button {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  background: white;
  @apply text-raspberry-500;
  @apply border-2 border-raspberry-500;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.upload-button:hover {
  @apply bg-light-pink-50;
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
  @apply border border-light-gray;
  border-radius: 8px;
  padding: 12px;
  cursor: pointer;
  transition: all 0.2s ease;
}


.pension-card-standalone:hover {
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
  @apply border-horizon-300;
}

/* Empty Standalone State */
.empty-standalone {
  @apply bg-light-blue-100 border border-light-gray;
  border-radius: 12px;
  padding: 32px 20px;
  text-align: center;
}

.empty-standalone p {
  @apply text-neutral-500;
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
  @apply border border-violet-200;
}

.income-card-standalone.surplus {
  background: linear-gradient(135deg, theme('colors.green.50') 0%, theme('colors.green.100') 100%);
  @apply border border-spring-200;
}

.income-card-standalone.shortfall {
  background: linear-gradient(135deg, theme('colors.red.50') 0%, theme('colors.red.100') 100%);
  @apply border border-raspberry-200;
}

.income-card-divider {
  height: 1px;
  background: rgba(0, 0, 0, 0.1);
  margin: 12px 0;
}

.income-card-value-secondary {
  font-size: 20px;
  font-weight: 700;
  @apply text-violet-800;
}

.depletion-warning-standalone {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  @apply bg-violet-100;
  @apply border border-violet-200;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.depletion-warning-standalone svg {
  width: 20px;
  height: 20px;
  @apply text-violet-700;
  flex-shrink: 0;
}

.depletion-warning-standalone span {
  font-size: 13px;
  @apply text-violet-800;
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
  @apply bg-violet-100;
  @apply text-violet-800;
}

.badge-db {
  @apply bg-purple-100;
  @apply text-purple-800;
}

.badge-state {
  @apply bg-spring-100;
  @apply text-spring-800;
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
  @apply text-horizon-500;
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
  @apply border-t border-light-gray;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.detail-label {
  font-size: 11px;
  @apply text-neutral-500;
}

.detail-value {
  font-size: 13px;
  @apply text-horizon-500;
  font-weight: 600;
}

/* Empty Compact State */
.empty-compact {
  text-align: center;
  padding: 24px 16px;
  border-radius: 8px;
  @apply bg-light-blue-100 border border-light-gray;
}

.empty-compact p {
  @apply text-neutral-500;
  font-size: 14px;
  margin: 0 0 12px 0;
}

.add-first-btn {
  @apply bg-raspberry-500;
  color: white;
  border: none;
  padding: 8px 16px;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.add-first-btn:hover {
  @apply bg-raspberry-500;
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
  @apply border border-light-gray;
}

.projection-loading p {
  @apply text-neutral-500;
  font-size: 16px;
  margin: 0;
}

.empty-projections {
  text-align: center;
  padding: 80px 40px;
  border-radius: 12px;
  @apply bg-light-blue-100 border border-light-gray;
}

/* Full-width Empty State - matches investment list */
.empty-state {
  text-align: center;
  padding: 80px 40px;
  border-radius: 12px;
  @apply bg-light-blue-100 border border-light-gray;
}

.empty-state p {
  @apply text-neutral-500;
  font-size: 18px;
  font-weight: 600;
  margin: 0 0 8px 0;
}

.empty-icon {
  width: 64px;
  height: 64px;
  @apply text-horizon-400;
  margin: 0 auto 16px;
}

.empty-projections p {
  @apply text-neutral-500;
  font-size: 18px;
  font-weight: 600;
  margin: 0 0 8px 0;
}

.empty-subtitle {
  @apply text-horizon-400;
  font-size: 14px;
  font-weight: 400;
}

.add-first-button {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  margin-top: 24px;
  padding: 12px 24px;
  @apply bg-horizon-500;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}

.add-first-button:hover {
  @apply bg-horizon-600;
}

/* Guaranteed Income Summary (for DB/State pension only users) */
.guaranteed-income-summary {
  background: white;
  border-radius: 12px;
  padding: 24px;
  @apply border border-light-gray;
}

.guaranteed-income-header {
  display: flex;
  align-items: flex-start;
  gap: 16px;
  margin-bottom: 24px;
}

.guaranteed-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  @apply bg-spring-100 text-spring-600;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.guaranteed-icon svg {
  width: 24px;
  height: 24px;
}

.guaranteed-title {
  font-size: 18px;
  font-weight: 600;
  @apply text-horizon-500;
  margin: 0 0 4px 0;
}

.guaranteed-subtitle {
  font-size: 14px;
  @apply text-neutral-500;
  margin: 0;
}

.guaranteed-total {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px;
  border-radius: 8px;
  @apply bg-spring-50 border border-spring-200;
  margin-bottom: 24px;
}

.guaranteed-total-label {
  font-size: 14px;
  font-weight: 500;
  @apply text-spring-800;
}

.guaranteed-total-value {
  font-size: 24px;
  font-weight: 700;
  @apply text-spring-600;
}

.guaranteed-breakdown {
  display: flex;
  flex-direction: column;
  gap: 16px;
  margin-bottom: 24px;
}

.guaranteed-item {
  padding: 16px;
  border-radius: 8px;
  @apply bg-savannah-100 border border-light-gray;
}

.guaranteed-item-header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
}

.guaranteed-item-name {
  font-size: 14px;
  font-weight: 600;
  @apply text-horizon-500;
}

.guaranteed-item-details {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.guaranteed-detail-row {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
  @apply text-neutral-500;
}

.guaranteed-comparison {
  padding-top: 16px;
  @apply border-t border-light-gray;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.comparison-row {
  display: flex;
  justify-content: space-between;
  font-size: 14px;
  @apply text-neutral-500;
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

/* Planner Cards Row */
.planner-cards-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
  margin-bottom: 24px;
}

.planner-card {
  background: white;
  border-radius: 12px;
  padding: 20px;
  transition: all 0.2s ease;
  @apply border border-light-gray;
}

.planner-card.income,
.planner-card.capital,
.planner-card.drawdown {
  background: white;
}

.planner-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
  @apply border-light-gray;
}

.planner-card-cta {
  margin-top: 12px;
  padding-top: 12px;
  border-top: 1px solid rgba(0, 0, 0, 0.06);
}

.view-detail-link {
  font-size: 13px;
  font-weight: 500;
  @apply text-raspberry-500;
  display: flex;
  align-items: center;
  gap: 4px;
}

.planner-card:hover .view-detail-link {
  @apply text-raspberry-600;
}

.planner-card-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
}

.planner-card-icon {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.planner-card-icon.income {
  @apply bg-violet-100;
}

.planner-card-icon.income svg {
  width: 22px;
  height: 22px;
  @apply text-violet-600;
}

.planner-card-icon.capital {
  @apply bg-teal-100;
}

.planner-card-icon.capital svg {
  width: 22px;
  height: 22px;
  @apply text-teal-600;
}

.planner-card-icon.drawdown {
  @apply bg-raspberry-100;
}

.planner-card-icon.drawdown svg {
  width: 22px;
  height: 22px;
  @apply text-raspberry-600;
}

.planner-card-title {
  flex: 1;
  font-size: 16px;
  font-weight: 600;
  @apply text-horizon-500;
  margin: 0;
}

.planner-card-arrow {
  @apply text-horizon-400;
  transition: all 0.2s;
}

.planner-card-arrow svg {
  width: 18px;
  height: 18px;
}

.planner-card:hover .planner-card-arrow {
  @apply text-neutral-500;
  transform: translateX(2px);
}

.planner-card-metrics {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 16px;
}

.planner-card-metrics.three-col {
  grid-template-columns: repeat(3, 1fr);
}

.planner-metric {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.planner-metric-label {
  font-size: 12px;
  @apply text-neutral-500;
  font-weight: 500;
}

.planner-metric-value {
  @apply text-xl sm:text-2xl font-black text-horizon-500;
}

.planner-metric-value.green {
  @apply text-spring-600;
}

.planner-metric-value.red {
  @apply text-raspberry-600;
}

@media (max-width: 1200px) {
  .planner-cards-row {
    grid-template-columns: 1fr;
  }

  .planner-card-metrics.three-col {
    grid-template-columns: repeat(3, 1fr);
  }
}

@media (max-width: 640px) {
  .planner-card-metrics,
  .planner-card-metrics.three-col {
    grid-template-columns: 1fr;
    gap: 12px;
  }
}

/* Pension + Chart Two-Column Layout */
.pension-chart-layout {
  display: grid;
  grid-template-columns: 300px 1fr;
  gap: 20px;
  align-items: start;
}

.pension-cards-column {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

@media (max-width: 1200px) {
  .pension-chart-layout {
    grid-template-columns: 1fr;
  }
}

/* Chart Card */
.chart-card {
  background: white;
  border-radius: 12px;
  padding: 24px;
  @apply border border-light-gray;
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

.risk-badge-corner {
  display: inline-block;
  padding: 4px 10px;
  @apply bg-violet-50;
  @apply text-violet-600;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
}


.chart-footer {
  font-size: 13px;
  @apply text-neutral-500;
  text-align: center;
  margin: 16px 0 0 0;
}

.view-more {
  display: flex;
  align-items: center;
  gap: 4px;
  @apply text-raspberry-500;
  font-size: 14px;
  font-weight: 500;
}

.view-more svg {
  width: 16px;
  height: 16px;
}

.view-more-small {
  @apply text-horizon-400;
}

.view-more-small svg {
  width: 20px;
  height: 20px;
}

.clickable:hover .view-more-small {
  @apply text-raspberry-500;
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
  @apply bg-violet-50;
}

.summary-item.purple {
  @apply bg-purple-50;
}

.summary-item.green {
  @apply bg-spring-50;
}

.summary-item.blue {
  @apply bg-violet-50;
}

.summary-item.red {
  @apply bg-raspberry-50;
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
  @apply text-neutral-500;
  margin-bottom: 4px;
}

.summary-item-value {
  font-size: 18px;
  font-weight: 700;
  @apply text-horizon-500;
}

/* Right Panel - Income Sidebar */
.income-sidebar {
  background: white;
  border-radius: 12px;
  @apply border border-light-gray;
  padding: 20px;
  position: sticky;
  top: 100px;
}

.income-link {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 4px;
  @apply text-raspberry-500;
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
  @apply border border-violet-200;
}

.income-card.guaranteed {
  background: linear-gradient(135deg, theme('colors.green.50') 0%, theme('colors.green.100') 100%);
  @apply border border-spring-200;
}

.income-card.surplus {
  background: linear-gradient(135deg, theme('colors.green.50') 0%, theme('colors.green.100') 100%);
  @apply border border-spring-200;
}

.income-card.shortfall {
  background: linear-gradient(135deg, theme('colors.red.50') 0%, theme('colors.red.100') 100%);
  @apply border border-raspberry-200;
}

.income-card-heading {
  font-size: 14px;
  font-weight: 600;
  @apply text-violet-800;
  margin-bottom: 12px;
  padding-bottom: 8px;
  border-bottom: 1px solid rgba(0, 0, 0, 0.1);
}

.income-card-label {
  font-size: 13px;
  @apply text-neutral-500;
  margin-bottom: 4px;
}

.income-card-value {
  font-size: 22px;
  font-weight: 700;
  @apply text-horizon-500;
}

.income-card-value-green {
  font-size: 20px;
  font-weight: 700;
  @apply text-spring-600;
}

.income-card-sublabel {
  font-size: 12px;
  @apply text-neutral-500;
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
  @apply text-neutral-500;
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
  @apply bg-violet-100;
  @apply border border-violet-200;
  border-radius: 8px;
}

.depletion-warning svg {
  width: 20px;
  height: 20px;
  @apply text-violet-700;
  flex-shrink: 0;
}

.depletion-warning span {
  font-size: 13px;
  @apply text-violet-800;
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
}

.notification.success {
  @apply bg-spring-500 text-white;
}

.notification.error {
  @apply bg-raspberry-500 text-white;
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
