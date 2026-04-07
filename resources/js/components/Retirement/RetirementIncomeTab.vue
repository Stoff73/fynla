<template>
  <div class="retirement-income-tab">
    <!-- Back Button -->
    <button
      @click="$emit('back')"
      class="detail-inline-back mb-4"
    >
      <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
      </svg>
      Back to Pensions
    </button>

    <!-- Loading State -->
    <div v-if="loading" class="loading-state">
      <div class="w-12 h-12 border-4 border-light-gray border-t-raspberry-500 rounded-full animate-spin mb-4"></div>
      <p>Calculating tax-optimised income...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="error-state">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="error-icon">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
      </svg>
      <p>{{ error }}</p>
      <button class="retry-button" @click="loadData">Try Again</button>
    </div>

    <!-- No Accounts State - only show after data has loaded -->
    <div v-else-if="dataLoaded && !hasAccounts" class="empty-state">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="empty-icon">
        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
      </svg>
      <p>No retirement accounts found</p>
      <p class="empty-subtitle">Add pensions, ISAs, or other investment accounts to model your retirement income</p>
    </div>

    <!-- Initial Loading State (before first load completes) -->
    <div v-else-if="!dataLoaded" class="loading-state">
      <div class="w-12 h-12 border-4 border-light-gray border-t-raspberry-500 rounded-full animate-spin mb-4"></div>
      <p>Loading retirement income data...</p>
    </div>

    <!-- Main Content -->
    <template v-else>
      <!-- Header Card with summary metrics inside (matches pension detail pattern) -->
      <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <div>
          <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold text-horizon-500">Income Planner</h1>
          <p class="text-base sm:text-lg text-neutral-500 mt-1">Model your tax-optimised drawdown strategy from age {{ retirementAge }}</p>
        </div>

        <!-- State Pension Warning (inside card) -->
        <div v-if="statePensionStatus && !statePensionStatus.has_data" class="mt-4 p-3 bg-violet-50 rounded-lg flex items-start gap-3">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-violet-500 mt-0.5 flex-shrink-0">
            <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
          </svg>
          <div class="text-sm">
            <p class="text-neutral-600">{{ statePensionStatus.message }}</p>
            <div class="mt-1 flex items-center gap-2">
              <button type="button" @click="$emit('add-state-pension')" class="text-raspberry-500 font-medium hover:text-raspberry-600">Add State Pension</button>
              <span class="text-neutral-400">or</span>
              <a :href="statePensionStatus.link" target="_blank" rel="noopener noreferrer" class="text-raspberry-500 font-medium hover:text-raspberry-600">{{ statePensionStatus.link_text }}</a>
            </div>
          </div>
        </div>

        <!-- Income Adjusted Notice (inside card) -->
        <div v-if="incomeWasAdjusted" class="mt-4 p-3 bg-violet-50 rounded-lg text-sm text-neutral-600">
          Income adjusted from {{ formatCurrency(displayTargetIncome) }} to {{ formatCurrency(optimisedIncome) }}/year to ensure funds last to age 100.
        </div>

        <!-- Summary Metrics Grid (inside card) -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mt-6 pt-6 border-t border-light-gray">
          <!-- Target Income -->
          <div>
            <div class="flex items-center gap-1">
              <p class="text-xs text-neutral-500 uppercase tracking-wider">{{ incomeWasAdjusted ? 'Optimised Income' : 'Target Annual Income' }}</p>
              <button class="text-neutral-400 hover:text-raspberry-500 transition-colors" @click="showTargetModal = true">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                </svg>
              </button>
            </div>
            <p class="text-lg font-bold text-horizon-500 mt-1">{{ formatCurrency(incomeWasAdjusted ? optimisedIncome : displayTargetIncome) }}</p>
            <p class="text-xs text-neutral-400">
              <template v-if="incomeWasAdjusted">Adjusted to last until age 100</template>
              <template v-else>{{ customTargetIncome ? 'Custom target' : 'From retirement profile' }}</template>
            </p>
          </div>

          <!-- Projected Gross Income (clickable for breakdown) -->
          <div class="cursor-pointer" @click="showIncomeBreakdown = !showIncomeBreakdown">
            <p class="text-xs text-neutral-500 uppercase tracking-wider">
              Projected Gross Income
              <svg class="inline w-3 h-3 ml-0.5 text-neutral-400 transition-transform" :class="{ 'rotate-180': showIncomeBreakdown }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
            </p>
            <p class="text-lg font-bold mt-1" :class="firstYearGrossIncome >= displayTargetIncome ? 'text-spring-600' : 'text-raspberry-500'">{{ formatCurrency(firstYearGrossIncome) }}</p>
            <p class="text-xs text-neutral-400">Before tax ({{ formatPercent(firstYearEffectiveRate) }} rate)</p>
            <div v-if="showIncomeBreakdown && firstYearBreakdown" class="mt-2 pt-2 border-t border-light-gray space-y-1">
              <div v-if="firstYearBreakdown.dcWithdrawal > 0" class="flex justify-between text-xs">
                <span class="text-neutral-500">Defined Contribution</span>
                <span class="font-medium">{{ formatCurrency(firstYearBreakdown.dcWithdrawal) }}</span>
              </div>
              <div v-if="firstYearBreakdown.dbPension > 0" class="flex justify-between text-xs">
                <span class="text-neutral-500">Defined Benefit</span>
                <span class="font-medium">{{ formatCurrency(firstYearBreakdown.dbPension) }}</span>
              </div>
              <div v-if="firstYearBreakdown.statePension > 0" class="flex justify-between text-xs">
                <span class="text-neutral-500">State Pension</span>
                <span class="font-medium">{{ formatCurrency(firstYearBreakdown.statePension) }}</span>
              </div>
              <div class="flex justify-between text-xs text-raspberry-500 pt-1 border-t border-light-gray">
                <span>Tax</span>
                <span>-{{ formatCurrency(firstYearBreakdown.tax) }}</span>
              </div>
              <div class="flex justify-between text-xs font-bold">
                <span>Net income</span>
                <span>{{ formatCurrency(firstYearNetIncome) }}</span>
              </div>
            </div>
          </div>

          <!-- Pension Capital -->
          <div>
            <p class="text-xs text-neutral-500 uppercase tracking-wider">Pension Capital</p>
            <p class="text-lg font-bold text-horizon-500 mt-1">{{ formatCurrency(projectedPotAtRetirement) }}</p>
            <p class="text-xs text-neutral-400">At age {{ retirementAge }} (80% confidence)</p>
          </div>

          <!-- Other Assets -->
          <div>
            <p class="text-xs text-neutral-500 uppercase tracking-wider">Other Assets</p>
            <p class="text-lg font-bold text-horizon-500 mt-1">{{ formatCurrency(totalProjectedOtherAssets) }}</p>
            <p class="text-xs text-neutral-400">Investments + Cash projected</p>
          </div>
        </div>
      </div>

      <!-- Old sources position (removed — now below chart) -->
      <div v-if="false" class="sources-assets-row">
        <div class="sources-section">
          <div class="sources-header">
            <h4 class="sources-title">Income Sources</h4>
          </div>

          <div class="sources-list">
            <!-- Pension Sources -->
            <IncomeSourceSlider
              v-for="allocation in displayAllocations"
              :key="`${allocation.source_type}-${allocation.source_id}`"
              :allocation="allocation"
              :account="getAccountForAllocation(allocation)"
            />

            <!-- Included Other Assets (ISA, Bond, etc.) - shown inline with toggle -->
            <div
              v-for="allocation in includedOtherAllocations"
              :key="'other-' + allocation.source_id"
              class="account-card"
            >
              <!-- Type Badge -->
              <span :class="['account-type-badge', getAccountTypeBadgeClass(allocation.source_type)]">
                {{ formatSourceType(allocation.source_type) }}
              </span>

              <!-- Account Name -->
              <h4 class="account-name">{{ allocation.name }}</h4>

              <!-- Value and Toggle Row -->
              <div class="account-value-row">
                <div class="account-values">
                  <p class="account-value">{{ formatCurrency(allocation.starting_balance) }}</p>
                  <p class="account-detail">Annual draw: {{ formatCurrency(allocation.annual_amount) }}</p>
                </div>
                <button
                  type="button"
                  class="account-toggle active"
                  @click="toggleAllocation(allocation)"
                  title="Click to exclude from retirement income"
                >
                  <span>Included</span>
                  <span class="toggle-switch on"></span>
                </button>
              </div>
            </div>
          </div>

          <div v-if="displayAllocations.length === 0 && includedOtherAllocations.length === 0" class="no-allocations">
            <p>No income allocations configured</p>
          </div>
        </div>

        <!-- Other Assets Section (Excluded Only) -->
        <div v-if="excludedInvestments.length > 0 || excludedCash.length > 0" class="assets-section">
          <div class="assets-header">
            <h4 class="assets-title">Other Assets</h4>
            <p class="assets-subtitle">Toggle to include in retirement income planning</p>
          </div>

          <div class="asset-cards">
            <div v-for="account in excludedInvestments" :key="'inv-ex-' + account.id" class="account-card">
              <!-- Type Badge -->
              <span :class="['account-type-badge', getAccountTypeBadgeClass(account.account_type)]">
                {{ formatAccountType(account.account_type) }}
              </span>

              <!-- Account Name -->
              <h4 class="account-name">{{ account.account_name || account.provider || formatAccountType(account.account_type) }}</h4>

              <!-- Value and Toggle Row -->
              <div class="account-value-row">
                <div class="account-values">
                  <p class="account-value">{{ formatCurrency(getProjectedValue(account)) }}</p>
                  <p class="account-detail">Projected at retirement (80%)</p>
                </div>
                <button
                  type="button"
                  class="account-toggle"
                  @click="toggleAsset('investment', account.id)"
                  title="Click to include in retirement income"
                >
                  <span>Excluded</span>
                  <span class="toggle-switch"></span>
                </button>
              </div>
            </div>
            <div v-for="account in excludedCash" :key="'cash-ex-' + account.id" class="account-card">
              <!-- Type Badge -->
              <span :class="['account-type-badge', account.is_isa ? 'badge-emerald' : 'badge-gray']">
                {{ account.is_isa ? 'Cash ISA' : 'Savings' }}
              </span>

              <!-- Account Name -->
              <h4 class="account-name">{{ account.institution || 'Cash Account' }}</h4>

              <!-- Value and Toggle Row -->
              <div class="account-value-row">
                <div class="account-values">
                  <p class="account-value">{{ formatCurrency(getProjectedCashValue(account)) }}</p>
                  <p class="account-detail">Projected at retirement</p>
                </div>
                <button
                  type="button"
                  class="account-toggle"
                  @click="toggleAsset('cash', account.id)"
                  title="Click to include in retirement income"
                >
                  <span>Excluded</span>
                  <span class="toggle-switch"></span>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Fund Depletion Chart — click to reveal income sources below -->
      <div v-if="fundProjections.length > 0" class="cursor-pointer" @click="showSourcesDetail = !showSourcesDetail">
        <FundDepletionChart
          :projections="fundProjections"
          :depletion-ages="depletionAges"
          :retirement-age="retirementAge"
        />
        <p class="text-center text-sm text-neutral-500 mt-2 mb-4">
          <span class="text-raspberry-500 font-medium">{{ showSourcesDetail ? 'Hide' : 'View' }} income sources and assets</span>
          <svg class="inline w-4 h-4 ml-1 text-raspberry-500 transition-transform" :class="{ 'rotate-180': showSourcesDetail }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
        </p>
      </div>

      <!-- Income Sources and Other Assets — revealed by clicking chart -->
      <div v-if="showSourcesDetail" class="sources-assets-row">
        <div class="sources-section">
          <div class="sources-header">
            <h4 class="sources-title">Income Sources</h4>
          </div>

          <div class="sources-list">
            <IncomeSourceSlider
              v-for="allocation in displayAllocations"
              :key="`${allocation.source_type}-${allocation.source_id}`"
              :allocation="allocation"
              :account="getAccountForAllocation(allocation)"
            />

            <div
              v-for="allocation in includedOtherAllocations"
              :key="'other-' + allocation.source_id"
              class="account-card"
            >
              <span :class="['account-type-badge', getAccountTypeBadgeClass(allocation.source_type)]">
                {{ formatSourceType(allocation.source_type) }}
              </span>
              <h4 class="account-name">{{ allocation.name }}</h4>
              <div class="account-value-row">
                <div class="account-values">
                  <p class="account-value">{{ formatCurrency(allocation.starting_balance) }}</p>
                  <p class="account-detail">Annual draw: {{ formatCurrency(allocation.annual_amount) }}</p>
                </div>
                <button type="button" class="account-toggle active" @click.stop="toggleAllocation(allocation)" title="Click to exclude from retirement income">
                  <span>Included</span>
                  <span class="toggle-switch on"></span>
                </button>
              </div>
            </div>
          </div>

          <div v-if="displayAllocations.length === 0 && includedOtherAllocations.length === 0" class="no-allocations">
            <p>No income allocations configured</p>
          </div>
        </div>

        <div v-if="excludedInvestments.length > 0 || excludedCash.length > 0" class="assets-section">
          <div class="assets-header">
            <h4 class="assets-title">Other Assets</h4>
            <p class="assets-subtitle">Toggle to include in retirement income planning</p>
          </div>

          <div class="asset-cards">
            <div v-for="account in excludedInvestments" :key="'inv-ex-' + account.id" class="account-card">
              <span :class="['account-type-badge', getAccountTypeBadgeClass(account.account_type)]">
                {{ formatAccountType(account.account_type) }}
              </span>
              <h4 class="account-name">{{ account.account_name || account.provider || formatAccountType(account.account_type) }}</h4>
              <div class="account-value-row">
                <div class="account-values">
                  <p class="account-value">{{ formatCurrency(getProjectedValue(account)) }}</p>
                  <p class="account-detail">Projected at retirement (80%)</p>
                </div>
                <button type="button" class="account-toggle" @click.stop="toggleAsset('investment', account.id)" title="Click to include in retirement income">
                  <span>Excluded</span>
                  <span class="toggle-switch"></span>
                </button>
              </div>
            </div>
            <div v-for="account in excludedCash" :key="'cash-ex-' + account.id" class="account-card">
              <span :class="['account-type-badge', account.is_isa ? 'badge-emerald' : 'badge-gray']">
                {{ account.is_isa ? 'Cash ISA' : 'Savings' }}
              </span>
              <h4 class="account-name">{{ account.institution || 'Cash Account' }}</h4>
              <div class="account-value-row">
                <div class="account-values">
                  <p class="account-value">{{ formatCurrency(getProjectedCashValue(account)) }}</p>
                  <p class="account-detail">Projected at retirement</p>
                </div>
                <button type="button" class="account-toggle" @click.stop="toggleAsset('cash', account.id)" title="Click to include in retirement income">
                  <span>Excluded</span>
                  <span class="toggle-switch"></span>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Year-by-Year Projection Table (Hidden from view - logic retained) -->
      <div v-if="false && requiredCapital?.year_by_year?.length > 0" class="table-section">
        <h4 class="table-title">Year-by-Year Projection</h4>
        <div class="table-container">
          <table class="projection-table">
            <thead>
              <tr>
                <th>Year</th>
                <th>Age</th>
                <th>Projected Pot Value</th>
                <th>Pot in Today's Money</th>
                <th>Target in Today's Money</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in requiredCapital.year_by_year"
                :key="row.year_number"
                :class="{ 'retirement-row': row.is_retirement_year }"
              >
                <td>{{ row.calendar_year }}</td>
                <td>{{ row.age }}</td>
                <td>{{ formatCurrency(row.accumulated_value) }}</td>
                <td>{{ formatCurrency(row.present_value_today) }}</td>
                <td>{{ formatCurrency(row.target_in_today_money) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Target Income Modal -->
      <div v-if="showTargetModal" class="modal-overlay" @click.self="showTargetModal = false">
        <div class="modal-content">
          <h4 class="modal-title">Set Target Income</h4>
          <p class="modal-description">Enter your desired annual retirement income</p>
          <div class="input-group">
            <span class="input-prefix">£</span>
            <input
              v-model.number="tempTargetIncome"
              type="number"
              min="0"
              step="1000"
              class="target-input"
              placeholder="35000"
            />
            <span class="input-suffix">/year</span>
          </div>
          <div class="modal-actions">
            <button class="btn-secondary" @click="resetTarget">Use Profile Default</button>
            <button class="btn-primary" @click="applyTarget">Apply</button>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script>
import { mapState, mapGetters, mapActions } from 'vuex';
import IncomeSourceSlider from './IncomeSourceSlider.vue';
import FundDepletionChart from './FundDepletionChart.vue';
import { currencyMixin } from '@/mixins/currencyMixin';

import logger from '@/utils/logger';
export default {
  name: 'RetirementIncomeTab',

  mixins: [currencyMixin],

  emits: ['back', 'add-state-pension'],

  components: {
    IncomeSourceSlider,
    FundDepletionChart,
  },

  data() {
    return {
      showTargetModal: false,
      showIncomeBreakdown: false,
      showSourcesDetail: false,
      tempTargetIncome: null,
      calculateTimeout: null,
    };
  },

  computed: {
    ...mapState('retirement', [
      'retirementIncome',
      'retirementIncomeLoading',
      'incomeAllocations',
      'includeSpouseAssets',
      'customTargetIncome',
      'error',
      'profile',
      'requiredCapital',
      'projections',
      'dcPensions',
      'includedInvestmentIds',
      'includedCashIds',
    ]),
    ...mapGetters('retirement', [
      'retirementIncomeData',
      'retirementIncomeTaxBreakdown',
      'retirementIncomeFundProjections',
      'retirementIncomeDepletionAges',
      'retirementIncomeAvailableAccounts',
    ]),
    ...mapState('investment', ['accounts']),
    ...mapState('savings', { savingsAccounts: 'accounts' }),

    loading() {
      return this.retirementIncomeLoading;
    },

    dataLoaded() {
      // Data has been loaded if retirementIncome is not null
      return this.retirementIncome !== null;
    },

    includeSpouse() {
      return this.includeSpouseAssets;
    },

    retirementAge() {
      return this.retirementIncome?.retirement_age || this.profile?.target_retirement_age || 68;
    },

    displayTargetIncome() {
      // Priority: custom target > requiredCapital (centralised) > retirementIncome API > 0
      return this.customTargetIncome ||
             this.requiredCapital?.required_income ||
             this.retirementIncome?.target_income ||
             0;
    },

    taxBreakdown() {
      return this.retirementIncome?.tax_breakdown || null;
    },

    fundProjections() {
      return this.retirementIncome?.fund_projections || [];
    },

    // Calculate net income from first year's fund projection (matches Withdrawal column)
    firstYearNetIncome() {
      if (this.fundProjections.length === 0) return 0;
      const firstYear = this.fundProjections[0];
      // Gross income = fund withdrawals + guaranteed pensions
      const grossIncome = (firstYear.total_income || 0) + (firstYear.state_pension || 0) + (firstYear.db_pension || 0);
      // Net = gross - tax
      return grossIncome - (firstYear.tax_paid || 0);
    },

    firstYearBreakdown() {
      if (this.fundProjections.length === 0) return null;
      const firstYear = this.fundProjections[0];
      const dcWithdrawal = firstYear.total_income || 0;
      const dbPension = firstYear.db_pension || 0;
      const statePension = firstYear.state_pension || 0;
      const grossTotal = dcWithdrawal + dbPension + statePension;
      const tax = firstYear.tax_paid || 0;
      return { dcWithdrawal, dbPension, statePension, grossTotal, tax };
    },

    // Calculate effective tax rate from first year's projection
    firstYearEffectiveRate() {
      if (this.fundProjections.length === 0) return 0;
      const firstYear = this.fundProjections[0];
      const grossIncome = (firstYear.total_income || 0) + (firstYear.state_pension || 0) + (firstYear.db_pension || 0);
      if (grossIncome <= 0) return 0;
      return (firstYear.tax_paid || 0) / grossIncome;
    },

    // First year gross income — single source: income_drawdown from projections store
    firstYearGrossIncome() {
      const firstYear = this.projections?.income_drawdown?.yearly_income?.[0];
      if (!firstYear) return 0;
      return (firstYear.total_income || 0) + (firstYear.state_pension || 0) + (firstYear.db_pension || 0);
    },

    // First year tax paid (matches Fund Depletion table)
    firstYearTaxPaid() {
      if (this.fundProjections.length === 0) return 0;
      return this.fundProjections[0].tax_paid || 0;
    },

    depletionAges() {
      return this.retirementIncome?.depletion_ages || {};
    },

    availableAccounts() {
      return this.retirementIncome?.available_accounts || [];
    },

    statePensionStatus() {
      return this.retirementIncome?.state_pension_status || null;
    },

    optimisedIncome() {
      return this.retirementIncome?.optimised_income || this.displayTargetIncome;
    },

    incomeWasAdjusted() {
      return this.retirementIncome?.income_was_adjusted || false;
    },

    displayAllocations() {
      // Use incomeAllocations if it has items, otherwise fall back to API response
      let allocations = [];
      if (this.incomeAllocations && this.incomeAllocations.length > 0) {
        allocations = this.incomeAllocations;
      } else {
        allocations = this.retirementIncome?.allocations || [];
      }
      // Filter to show ONLY pension sources in Income Sources section
      // Other assets (ISA, Bond, GIA, Savings) are shown separately with toggles
      const pensionTypes = ['pension_pot', 'pension_pot_pcls', 'pension_pot_drawdown', 'db_pension', 'state_pension'];
      const pensionAllocations = allocations.filter(a => pensionTypes.includes(a.source_type));

      // Deduplicate by source_type + source_id, keeping the one with highest annual_amount
      const seen = new Map();
      for (const alloc of pensionAllocations) {
        const key = `${alloc.source_type}-${alloc.source_id}`;
        const existing = seen.get(key);
        if (!existing || (alloc.annual_amount || 0) > (existing.annual_amount || 0)) {
          seen.set(key, alloc);
        }
      }
      return Array.from(seen.values());
    },

    // Allocations for non-pension sources (ISA, Bond, GIA, Savings) that are included
    includedOtherAllocations() {
      // Build list from included investments - this ensures we always show them
      // even if the API hasn't returned allocations yet
      const result = [];
      const seenIds = new Set();

      // First, add allocations from API response for included assets
      let allocations = [];
      if (this.incomeAllocations && this.incomeAllocations.length > 0) {
        allocations = this.incomeAllocations;
      } else {
        allocations = this.retirementIncome?.allocations || [];
      }

      const pensionTypes = ['pension_pot', 'pension_pot_pcls', 'pension_pot_drawdown', 'db_pension', 'state_pension'];
      const nonPensionAllocations = allocations.filter(a => !pensionTypes.includes(a.source_type));

      // Add allocations that match included IDs (use numeric comparison for type safety)
      for (const a of nonPensionAllocations) {
        const investmentTypes = ['isa', 'isa_investment', 'stocks_shares_isa', 'gia', 'onshore_bond', 'offshore_bond'];
        const cashTypes = ['isa_cash', 'savings', 'cash_isa'];
        const sourceIdNum = parseInt(a.source_id, 10);

        if (investmentTypes.includes(a.source_type) && this.storeIncludedInvestmentIds.some(id => parseInt(id, 10) === sourceIdNum)) {
          result.push(a);
          seenIds.add(sourceIdNum);
        } else if (cashTypes.includes(a.source_type) && this.storeIncludedCashIds.some(id => parseInt(id, 10) === sourceIdNum)) {
          result.push(a);
          seenIds.add(sourceIdNum);
        }
      }

      // Add included investments that don't have allocations yet
      for (const account of this.includedInvestments) {
        const accountIdNum = parseInt(account.id, 10);
        if (!seenIds.has(accountIdNum)) {
          // Create a pseudo-allocation for display
          result.push({
            source_id: account.id,
            source_type: account.account_type,
            name: account.account_name || account.provider || this.formatAccountType(account.account_type),
            starting_balance: this.getProjectedValue(account),
            annual_amount: account.account_type?.includes('bond') ? this.getProjectedValue(account) * 0.05 : 0,
          });
        }
      }

      // Add included cash that don't have allocations yet
      for (const account of this.includedCash) {
        const accountIdNum = parseInt(account.id, 10);
        if (!seenIds.has(accountIdNum)) {
          result.push({
            source_id: account.id,
            source_type: account.is_isa ? 'isa_cash' : 'savings',
            name: account.institution || 'Cash Account',
            starting_balance: this.getProjectedCashValue(account),
            annual_amount: 0,
          });
        }
      }

      return result;
    },

    hasAccounts() {
      return this.availableAccounts.length > 0;
    },

    netIncomeClass() {
      if (this.fundProjections.length === 0) return '';
      const netIncome = this.firstYearGrossIncome;
      const target = this.displayTargetIncome;
      if (netIncome >= target) return 'green';
      if (netIncome >= target * 0.9) return 'yellow';
      return 'red';
    },

    // Required Capital computed properties
    investmentAccounts() {
      // Exclude illiquid investments and employee share schemes from retirement capital
      const excludedTypes = [
        'vct', 'eis', 'private_company', 'crowdfunding',
        'saye', 'csop', 'emi', 'unapproved_options', 'rsu', 'other',
      ];
      return (this.accounts || []).filter(
        a => this.getDisplayValue(a) > 0 && !excludedTypes.includes(a.account_type)
      );
    },

    cashAccounts() {
      return (this.savingsAccounts || []).filter(a => parseFloat(a.current_balance) > 0);
    },

    storeIncludedInvestmentIds() {
      return this.includedInvestmentIds || [];
    },

    storeIncludedCashIds() {
      return this.includedCashIds || [];
    },

    includedInvestments() {
      // Use numeric comparison for type safety
      return this.investmentAccounts.filter(a =>
        this.storeIncludedInvestmentIds.some(id => parseInt(id, 10) === parseInt(a.id, 10))
      );
    },

    excludedInvestments() {
      // Use numeric comparison for type safety
      return this.investmentAccounts.filter(a =>
        !this.storeIncludedInvestmentIds.some(id => parseInt(id, 10) === parseInt(a.id, 10))
      );
    },

    includedCash() {
      // Use numeric comparison for type safety
      return this.cashAccounts.filter(a =>
        this.storeIncludedCashIds.some(id => parseInt(id, 10) === parseInt(a.id, 10))
      );
    },

    excludedCash() {
      // Use numeric comparison for type safety
      return this.cashAccounts.filter(a =>
        !this.storeIncludedCashIds.some(id => parseInt(id, 10) === parseInt(a.id, 10))
      );
    },

    projectedPotAtRetirement() {
      return this.projections?.pension_pot_projection?.percentile_20_at_retirement || 0;
    },

    totalProjectedInvestments() {
      return this.includedInvestments.reduce((sum, a) => sum + this.getProjectedValue(a), 0);
    },

    totalProjectedCash() {
      return this.includedCash.reduce((sum, a) => sum + this.getProjectedCashValue(a), 0);
    },

    totalProjectedOtherAssets() {
      return this.totalProjectedInvestments + this.totalProjectedCash;
    },

    gapToTarget() {
      const projectedTotal = this.projectedPotAtRetirement + this.totalProjectedOtherAssets;
      return (this.requiredCapital?.required_capital_at_retirement || 0) - projectedTotal;
    },

    totalIncludedAssets() {
      const totalPensions = (this.dcPensions || []).reduce((sum, p) => sum + (parseFloat(p.current_fund_value) || 0), 0);
      const totalInvestments = this.includedInvestments.reduce((sum, a) => sum + this.getDisplayValue(a), 0);
      const totalCash = this.includedCash.reduce((sum, a) => sum + (parseFloat(a.current_balance) || 0), 0);
      return totalPensions + totalInvestments + totalCash;
    },

    progressPercentage() {
      if (!this.requiredCapital?.required_capital_today) return 0;
      return Math.round((this.totalIncludedAssets / this.requiredCapital.required_capital_today) * 100);
    },

    progressColorClass() {
      const pct = this.progressPercentage;
      if (pct >= 80) return 'green';
      if (pct >= 50) return 'blue';
      return 'red';
    },

    forecastedProgressPercentage() {
      if (!this.requiredCapital?.required_capital_at_retirement) return 0;
      const projectedTotal = this.projectedPotAtRetirement + this.totalProjectedOtherAssets;
      return Math.round((projectedTotal / this.requiredCapital.required_capital_at_retirement) * 100);
    },

    forecastedProgressColorClass() {
      const pct = this.forecastedProgressPercentage;
      if (pct >= 80) return 'green';
      if (pct >= 50) return 'blue';
      return 'red';
    },

    compoundingLabel() {
      const periods = this.requiredCapital?.assumptions?.compound_periods || 4;
      switch (periods) {
        case 1: return 'Annually';
        case 2: return 'Semi-annually';
        case 4: return 'Quarterly';
        case 12: return 'Monthly';
        case 365: return 'Daily';
        default: return `${periods}x/year`;
      }
    },

    isDefaultFees() {
      const fees = this.requiredCapital?.assumptions?.fees_total;
      return !fees || fees === 0;
    },

    displayFees() {
      const fees = this.requiredCapital?.assumptions?.fees_total;
      return (!fees || fees === 0) ? 1 : fees;
    },

    hasOtherAssets() {
      // Only shows "Other Assets" section if there are EXCLUDED assets
      return this.excludedInvestments.length > 0 || this.excludedCash.length > 0;
    },

    hasIncludedOtherAssets() {
      return this.includedInvestments.length > 0 || this.includedCash.length > 0;
    },

    yearsToRetirement() {
      return this.requiredCapital?.retirement_info?.years_to_retirement || 0;
    },
  },

  mounted() {
    this.loadData();
  },

  beforeUnmount() {
    if (this.calculateTimeout) {
      clearTimeout(this.calculateTimeout);
    }
  },

  methods: {
    ...mapActions('retirement', [
      'fetchRetirementIncome',
      'fetchRequiredCapital',
      'fetchRetirementData',
      'calculateRetirementIncome',
      'toggleSpouseAssets',
      'setCustomTargetIncome',
      'toggleIncludedInvestment',
      'toggleIncludedCash',
      'setIncludedInvestmentIds',
      'setIncludedCashIds',
    ]),
    ...mapActions('investment', { fetchInvestmentAccounts: 'fetchAccounts' }),
    ...mapActions('savings', { fetchSavingsAccounts: 'fetchSavingsData' }),

    async loadData() {
      try {
        // Fetch all data in parallel
        const promises = [
          this.fetchRetirementIncome(),
          this.fetchRequiredCapital(),
          this.fetchInvestmentAccounts(),
          this.fetchSavingsAccounts(),
        ];
        // Only fetch retirement data if dcPensions not yet loaded
        if (!this.dcPensions || this.dcPensions.length === 0) {
          promises.push(this.fetchRetirementData());
        }
        await Promise.all(promises);
        this.tempTargetIncome = this.displayTargetIncome;

        // Initialize includedInvestmentIds from accounts with include_in_retirement = true
        const includedInvIds = (this.accounts || [])
          .filter(a => a.include_in_retirement)
          .map(a => a.id);
        this.setIncludedInvestmentIds(includedInvIds);

        // Initialize includedCashIds from savings accounts with include_in_retirement = true
        const includedCashIds = (this.savingsAccounts || [])
          .filter(a => a.include_in_retirement)
          .map(a => a.id);
        this.setIncludedCashIds(includedCashIds);
      } catch (error) {
        logger.error('Failed to load retirement income data:', error);
      }
    },

    async toggleSpouse() {
      await this.toggleSpouseAssets(!this.includeSpouse);
    },

    debouncedCalculate() {
      if (this.calculateTimeout) {
        clearTimeout(this.calculateTimeout);
      }
      this.calculateTimeout = setTimeout(() => {
        this.calculateRetirementIncome();
      }, 300);
    },

    applyTarget() {
      this.setCustomTargetIncome(this.tempTargetIncome);
      this.showTargetModal = false;
      this.debouncedCalculate();
    },

    resetTarget() {
      this.setCustomTargetIncome(null);
      this.tempTargetIncome = this.retirementIncome?.target_income || 0;
      this.showTargetModal = false;
      this.debouncedCalculate();
    },

    getAccountForAllocation(allocation) {
      const sourceType = allocation.source_type.replace('_pcls', '').replace('_drawdown', '');

      return this.availableAccounts.find(a => {
        // Match by source_id
        const idMatch = a.id === allocation.source_id || a.source_id === allocation.source_id;
        if (!idMatch) return false;

        // Match type - handle ISA variants (source_type='isa', account.type='isa_investment' or 'isa_cash')
        if (sourceType === 'isa') {
          return a.type === 'isa' || a.type === 'isa_investment' || a.type === 'isa_cash';
        }

        // Match type - handle bond variants
        if (sourceType === 'onshore_bond' || sourceType === 'offshore_bond') {
          return a.type === sourceType;
        }

        // Match type - handle pension pot
        if (sourceType === 'pension_pot') {
          return a.type === 'pension_pot';
        }

        // Default exact match
        return a.type === sourceType;
      }) || null;
    },

    formatPercent(value) {
      return (value * 100).toFixed(1) + '%';
    },

    async toggleAsset(type, id) {
      if (type === 'investment') {
        await this.toggleIncludedInvestment(id);
      } else if (type === 'cash') {
        await this.toggleIncludedCash(id);
      }
      // Fetch fresh data with updated allocations after toggle
      // This gets new allocations from backend that include/exclude the toggled asset
      await this.fetchRetirementIncome();
    },

    async toggleAllocation(allocation) {
      // Toggle an allocation based on its source_type
      // Map allocation source_type to the corresponding asset type
      const sourceType = allocation.source_type;
      const sourceId = parseInt(allocation.source_id, 10); // Ensure numeric for comparison
      let toggled = false;

      const investmentTypes = ['isa', 'isa_investment', 'stocks_shares_isa', 'onshore_bond', 'offshore_bond', 'gia'];
      const cashTypes = ['isa_cash', 'savings', 'cash_isa'];

      if (investmentTypes.includes(sourceType)) {
        // Find the account using numeric comparison
        const account = this.investmentAccounts.find(a => parseInt(a.id, 10) === sourceId);
        if (account) {
          await this.toggleIncludedInvestment(account.id);
          toggled = true;
        }
      } else if (cashTypes.includes(sourceType)) {
        await this.toggleIncludedCash(sourceId);
        toggled = true;
      }

      // Always fetch fresh data after toggle attempt
      // This gets new allocations from backend that include/exclude the toggled asset
      await this.fetchRetirementIncome();
    },

    formatSourceType(type) {
      if (!type) return 'Investment';
      const typeMap = {
        isa: 'ISA',
        isa_investment: 'Stocks & Shares ISA',
        isa_cash: 'Cash ISA',
        onshore_bond: 'Onshore Bond',
        offshore_bond: 'Offshore Bond',
        gia: 'General Investment Account',
        savings: 'Savings',
      };
      return typeMap[type] || type.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
    },

    formatAccountType(type) {
      if (!type) return 'Investment';
      const typeMap = {
        isa: 'ISA',
        stocks_shares_isa: 'Stocks & Shares ISA',
        lifetime_isa: 'Lifetime ISA',
        sipp: 'Self-Invested Personal Pension',
        gia: 'General Investment Account',
        onshore_bond: 'Onshore Bond',
        offshore_bond: 'Offshore Bond',
      };
      return typeMap[type] || type.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
    },

    getAccountTypeBadgeClass(type) {
      // Returns badge color class based on account type
      const badgeClasses = {
        isa: 'badge-green',
        stocks_shares_isa: 'badge-green',
        isa_investment: 'badge-green',
        lifetime_isa: 'badge-green',
        isa_cash: 'badge-emerald',
        cash_isa: 'badge-emerald',
        sipp: 'badge-blue',
        gia: 'badge-gray',
        onshore_bond: 'badge-green',
        offshore_bond: 'badge-green',
        savings: 'badge-gray',
      };
      return badgeClasses[type] || 'badge-gray';
    },

    getDisplayValue(account) {
      // For standard accounts, use current_value
      return parseFloat(account.current_value) || 0;
    },

    getProjectedValue(account) {
      // Look up Monte Carlo 80% projected value from retirement income data
      const availableAccounts = this.retirementIncomeAvailableAccounts || [];
      const projected = availableAccounts.find(a =>
        a.id === account.id || a.source_id === account.id
      );
      if (projected && projected.value) {
        return parseFloat(projected.value);
      }
      // If not in available accounts (excluded), calculate projection using assumptions
      // Use 80% confidence approximation: 4% net return (5% gross - 1% fees)
      const currentValue = this.getDisplayValue(account);
      const years = this.yearsToRetirement || 0;
      if (years <= 0) return currentValue;
      const netReturnRate = 0.04; // 5% return - 1% fees = 4% net
      return currentValue * Math.pow(1 + netReturnRate, years);
    },

    getProjectedCashValue(account) {
      // Look up from retirement income available accounts
      const availableAccounts = this.retirementIncomeAvailableAccounts || [];
      const projected = availableAccounts.find(a =>
        a.id === account.id || a.source_id === account.id
      );
      if (projected && projected.value) {
        return parseFloat(projected.value);
      }
      // If not in available accounts (excluded), calculate projection
      // Cash grows at lower rate (2% savings rate)
      const currentBalance = parseFloat(account.current_balance) || 0;
      const years = this.yearsToRetirement || 0;
      if (years <= 0) return currentBalance;
      const savingsRate = 0.02; // 2% for cash
      return currentBalance * Math.pow(1 + savingsRate, years);
    },
  },
};
</script>

<style scoped>
.retirement-income-tab {
  animation: fadeInSlideUp 0.3s ease-out;
}

/* Loading State */
.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 80px 20px;
  text-align: center;
}

.loading-state p {
  @apply text-neutral-500;
  font-size: 16px;
  margin: 0;
}

/* Error State */
.error-state {
  text-align: center;
  padding: 60px 40px;
  background: white;
  border-radius: 12px;
  @apply border border-raspberry-200;
}

.error-icon {
  width: 48px;
  height: 48px;
  @apply text-raspberry-500;
  margin: 0 auto 16px;
}

.error-state p {
  @apply text-neutral-500;
  font-size: 16px;
  margin: 0 0 16px 0;
}

.retry-button {
  @apply bg-raspberry-500;
  color: white;
  border: none;
  padding: 10px 24px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}

.retry-button:hover {
  @apply bg-raspberry-500;
}

/* Empty State */
.empty-state {
  text-align: center;
  padding: 80px 40px;
  border-radius: 12px;
  @apply bg-light-blue-100 border border-light-gray;
}

.empty-icon {
  width: 64px;
  height: 64px;
  @apply text-horizon-400;
  margin: 0 auto 16px;
}

.empty-state p {
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

/* Header Section */
.header-section {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 24px;
  gap: 16px;
  flex-wrap: wrap;
}

.header-left {
  flex: 1;
}

.section-title {
  font-size: 20px;
  font-weight: 700;
  @apply text-horizon-500;
  margin: 0 0 4px 0;
}

.section-subtitle {
  font-size: 14px;
  @apply text-neutral-500;
  margin: 0;
}

/* Spouse Toggle */
.spouse-toggle {
  display: flex;
  align-items: center;
  gap: 12px;
  cursor: pointer;
}

.toggle-label {
  font-size: 14px;
  @apply text-neutral-500;
  font-weight: 500;
}

.toggle-switch {
  position: relative;
  width: 48px;
  height: 26px;
  @apply bg-horizon-300;
  border: none;
  border-radius: 13px;
  cursor: pointer;
  transition: background 0.2s;
  padding: 0;
}

.toggle-switch.active {
  @apply bg-raspberry-500;
}

.toggle-slider {
  position: absolute;
  top: 3px;
  left: 3px;
  width: 20px;
  height: 20px;
  background: white;
  border-radius: 50%;
  transition: transform 0.2s;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
}

.toggle-switch.active .toggle-slider {
  transform: translateX(22px);
}

/* Summary Cards */
.summary-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 20px;
  margin-bottom: 32px;
}

.summary-card {
  background: white;
  border-radius: 12px;
  padding: 20px;
  @apply border border-light-gray;
}

.card-header-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.edit-btn {
  background: transparent;
  border: none;
  padding: 4px;
  cursor: pointer;
  @apply text-neutral-500;
  transition: color 0.2s;
}

.edit-btn:hover {
  @apply text-raspberry-500;
}

.edit-btn svg {
  width: 16px;
  height: 16px;
}

.summary-label {
  font-size: 14px;
  @apply text-neutral-500;
  margin: 0 0 8px 0;
  font-weight: 500;
}

.summary-value {
  font-size: 28px;
  font-weight: 700;
  @apply text-horizon-500;
  margin: 0;
}

.summary-subtitle {
  font-size: 13px;
  @apply text-neutral-500;
  margin: 8px 0 0 0;
}

/* Sources and Assets Row */
.sources-assets-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 24px;
  margin-bottom: 24px;
}

.sources-assets-row > * {
  min-width: 0;
}

/* Sources Section */
.sources-section {
  background: white;
  border-radius: 12px;
  padding: 24px;
  @apply border border-light-gray;
  margin-bottom: 0;
}

.sources-header {
  margin-bottom: 20px;
}

.sources-title {
  font-size: 18px;
  font-weight: 600;
  @apply text-horizon-500;
  margin: 0;
}

.sources-list {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 16px;
}

/* Account Card - matches Pension/Investment Dashboard styling */
.account-card {
  background: white;
  @apply border border-light-gray;
  border-radius: 8px;
  padding: 16px;
  transition: box-shadow 0.2s ease;
}

.account-card:hover {
  @apply shadow-md;
}

/* Account Type Badge */
.account-type-badge {
  display: inline-flex;
  align-items: center;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.02em;
  margin-bottom: 8px;
}

.badge-green {
  @apply bg-spring-100 text-spring-800;
}

.badge-emerald {
  @apply bg-emerald-100 text-emerald-800;
}

.badge-blue {
  @apply bg-violet-100 text-violet-800;
}

.badge-gray {
  @apply bg-savannah-100 text-neutral-500;
}

/* Account Name */
.account-name {
  font-size: 15px;
  font-weight: 600;
  @apply text-horizon-500;
  margin: 0 0 12px 0;
}

/* Value and Toggle Row */
.account-value-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
}

.account-values {
  flex: 1;
}

.account-value {
  font-size: 18px;
  font-weight: 700;
  @apply text-horizon-500;
  margin: 0;
}

.account-detail {
  font-size: 12px;
  @apply text-neutral-500;
  margin: 2px 0 0 0;
}

/* Account Toggle Button - no background color */
.account-toggle {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 6px 12px;
  border-radius: 16px;
  border: none;
  cursor: pointer;
  transition: all 0.15s ease;
  font-size: 12px;
  font-weight: 500;
  background: transparent;
  @apply text-neutral-500;
  flex-shrink: 0;
}

.account-toggle:hover {
  @apply bg-savannah-100;
}

.account-toggle.active {
  @apply text-neutral-500;
}

.account-toggle.active:hover {
  @apply bg-savannah-100;
}

/* Toggle Switch - colored track with white slider */
.toggle-switch {
  width: 28px;
  height: 16px;
  @apply bg-violet-500;
  border-radius: 8px;
  position: relative;
  transition: all 0.15s ease;
}

.toggle-switch::after {
  content: '';
  position: absolute;
  width: 12px;
  height: 12px;
  background: white;
  border-radius: 50%;
  top: 2px;
  left: 2px;
  transition: all 0.15s ease;
  @apply shadow-sm;
}

.toggle-switch.on {
  @apply bg-spring-500;
}

.toggle-switch.on::after {
  left: 13px;
}

.no-allocations {
  text-align: center;
  padding: 40px;
  @apply bg-savannah-100;
  border-radius: 8px;
}

.no-allocations p {
  @apply text-neutral-500;
  font-size: 14px;
  margin: 0;
}

/* Modal */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal-content {
  background: white;
  border-radius: 16px;
  padding: 32px;
  width: 100%;
  max-width: 400px;
  box-shadow: 0 20px 25px rgba(0, 0, 0, 0.15);
}

.modal-title {
  font-size: 20px;
  font-weight: 700;
  @apply text-horizon-500;
  margin: 0 0 8px 0;
}

.modal-description {
  font-size: 14px;
  @apply text-neutral-500;
  margin: 0 0 24px 0;
}

.input-group {
  display: flex;
  align-items: center;
  @apply bg-savannah-100;
  @apply border border-light-gray;
  border-radius: 8px;
  padding: 4px 12px;
  margin-bottom: 24px;
}

.input-prefix {
  font-size: 18px;
  font-weight: 600;
  @apply text-neutral-500;
}

.target-input {
  flex: 1;
  border: none;
  background: transparent;
  font-size: 24px;
  font-weight: 700;
  @apply text-horizon-500;
  padding: 12px;
  text-align: center;
}

.target-input:focus {
  outline: none;
}

.input-suffix {
  font-size: 14px;
  @apply text-neutral-500;
}

.modal-actions {
  display: flex;
  gap: 12px;
}

.btn-primary {
  flex: 1;
  padding: 12px 20px;
  @apply bg-raspberry-500;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}

.btn-primary:hover {
  @apply bg-raspberry-500;
}

@media (max-width: 1024px) {
  .sources-list {
    grid-template-columns: repeat(2, 1fr);
  }
}

/* Info Banner */
.info-banner {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 16px;
  @apply bg-violet-50;
  @apply border border-violet-200;
  border-radius: 12px;
  margin-bottom: 20px;
}

.info-banner.adjusted {
  @apply bg-violet-50;
  @apply border-violet-200;
}

.info-icon {
  width: 20px;
  height: 20px;
  @apply text-violet-600;
  flex-shrink: 0;
  margin-top: 2px;
}

.info-banner.adjusted .info-icon {
  @apply text-violet-600;
}

.info-content {
  flex: 1;
}

.info-message {
  font-size: 14px;
  @apply text-neutral-500;
  margin: 0 0 8px 0;
  line-height: 1.5;
}

.info-banner.adjusted .info-message {
  margin: 0;
}

.info-link {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 14px;
  font-weight: 500;
  @apply text-violet-600;
  text-decoration: none;
  transition: color 0.2s;
}

.info-link:hover {
  @apply text-violet-700;
  text-decoration: underline;
}

.external-link-icon {
  width: 14px;
  height: 14px;
}

.info-links {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.info-link-button {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 14px;
  font-weight: 600;
  @apply text-white;
  @apply bg-raspberry-500;
  padding: 6px 12px;
  border-radius: 6px;
  border: none;
  cursor: pointer;
  transition: background 0.2s;
}

.info-link-button:hover {
  @apply bg-violet-700;
}

.info-separator {
  font-size: 13px;
  @apply text-horizon-400;
}

/* Extended Summary Grid (6 cards) */
.summary-grid-extended {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 32px;
}

/* Summary Card Colors */
.summary-card.teal {
  @apply border-teal-200;
  @apply bg-teal-50;
}

.summary-card.teal .summary-value {
  @apply text-teal-700;
}

.summary-card.indigo {
  @apply border-violet-200;
  @apply bg-violet-50;
}

.summary-card.indigo .summary-value {
  @apply text-violet-700;
}

.summary-card.green {
  @apply border-spring-200;
  @apply bg-spring-50;
}

.summary-card.green .summary-value {
  @apply text-spring-700;
}

.summary-card.yellow {
  @apply border-violet-200;
  @apply bg-violet-50;
}

.summary-card.yellow .summary-value {
  @apply text-violet-700;
}

.summary-card.red {
  @apply border-raspberry-200;
  @apply bg-raspberry-50;
}

.summary-card.red .summary-value {
  @apply text-raspberry-700;
}

/* Other Assets Section */
.assets-section {
  background: white;
  border-radius: 12px;
  padding: 24px;
  @apply border border-light-gray;
  margin-bottom: 0;
}

.included-assets {
  @apply bg-teal-50;
  @apply border-teal-200;
}

.included-assets .sources-title {
  @apply text-teal-800;
}

.assets-header {
  margin-bottom: 20px;
}

.assets-title {
  font-size: 18px;
  font-weight: 600;
  @apply text-horizon-500;
  margin: 0 0 4px 0;
}

.assets-subtitle {
  font-size: 14px;
  @apply text-neutral-500;
  margin: 0;
}

.assets-group {
  margin-bottom: 20px;
}

.assets-group:last-child {
  margin-bottom: 0;
}

.assets-group-label {
  font-size: 13px;
  font-weight: 600;
  @apply text-neutral-500;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin: 0 0 12px 0;
}

.asset-cards {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
}

.asset-card {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px;
  border-radius: 8px;
  @apply border border-light-gray;
  background: white;
}

.asset-card.investment {
  @apply border-l-4 border-l-indigo-500;
}

.asset-card.cash {
  @apply border-l-4 border-l-emerald-500;
}

.asset-card.excluded {
  @apply bg-savannah-100;
  opacity: 0.8;
}

.asset-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.asset-name {
  font-size: 14px;
  font-weight: 600;
  @apply text-horizon-500;
}

.asset-value {
  font-size: 16px;
  font-weight: 700;
  @apply text-horizon-500;
}

.asset-type {
  font-size: 12px;
  @apply text-neutral-500;
}

.toggle-row {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
}

.toggle-text {
  font-size: 13px;
  @apply text-neutral-500;
}

.toggle-checkbox {
  width: 18px;
  height: 18px;
  cursor: pointer;
  accent-color: theme('colors.raspberry.500');
}

/* Progress and Assumptions Row */
.progress-assumptions-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 24px;
  margin-bottom: 24px;
}

.progress-section {
  background: white;
  border-radius: 12px;
  padding: 24px;
  @apply border border-light-gray;
}

.progress-title {
  font-size: 16px;
  font-weight: 600;
  @apply text-horizon-500;
  margin: 0 0 20px 0;
}

.progress-row {
  margin-bottom: 20px;
}

.progress-row:last-child {
  margin-bottom: 0;
}

.progress-label-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.progress-type {
  font-size: 14px;
  @apply text-neutral-500;
  font-weight: 500;
}

.progress-percentage {
  font-size: 14px;
  font-weight: 700;
}

.progress-percentage.green {
  @apply text-spring-600;
}

.progress-percentage.blue {
  @apply text-violet-600;
}

.progress-percentage.red {
  @apply text-raspberry-600;
}

.progress-bar-container {
  height: 8px;
  @apply bg-savannah-200;
  border-radius: 4px;
  overflow: hidden;
  margin-bottom: 8px;
}

.progress-bar {
  height: 100%;
  border-radius: 4px;
  transition: width 0.3s ease;
}

.progress-bar.green {
  @apply bg-spring-500;
}

.progress-bar.blue {
  @apply bg-violet-500;
}

.progress-bar.red {
  @apply bg-raspberry-500;
}

.progress-values {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
  @apply text-neutral-500;
}

.target-label {
  @apply text-horizon-400;
}

/* Assumptions Panel */
.assumptions-panel {
  background: white;
  border-radius: 12px;
  padding: 24px;
  @apply border border-light-gray;
}

.assumptions-header-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.assumptions-header-row h4 {
  font-size: 16px;
  font-weight: 600;
  @apply text-horizon-500;
  margin: 0;
}

.edit-link {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 13px;
  font-weight: 500;
  @apply text-raspberry-500;
  text-decoration: none;
}

.edit-link:hover {
  @apply text-raspberry-600;
}

.link-icon {
  width: 14px;
  height: 14px;
}

.assumptions-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.assumption-item {
  font-size: 13px;
  @apply text-neutral-500;
}

.assumption-item .label {
  @apply text-neutral-500;
  margin-right: 4px;
}

.assumption-item .note {
  @apply text-horizon-400;
  font-size: 12px;
}

/* Year-by-Year Table Section */
.table-section {
  background: white;
  border-radius: 12px;
  padding: 24px;
  @apply border border-light-gray;
  margin-bottom: 24px;
}

.table-title {
  font-size: 18px;
  font-weight: 600;
  @apply text-horizon-500;
  margin: 0 0 16px 0;
}

.table-container {
  overflow-x: auto;
}

.projection-table {
  width: 100%;
  border-collapse: collapse;
}

.projection-table th,
.projection-table td {
  padding: 12px 16px;
  text-align: right;
  @apply border-b border-light-gray;
}

.projection-table th:first-child,
.projection-table td:first-child {
  text-align: left;
}

.projection-table th {
  font-size: 12px;
  font-weight: 600;
  @apply text-neutral-500;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  @apply bg-savannah-100;
}

.projection-table td {
  font-size: 14px;
  @apply text-neutral-500;
}

.projection-table tr.retirement-row {
  @apply bg-teal-50;
}

.projection-table tr.retirement-row td {
  @apply text-teal-800;
  font-weight: 600;
}

@media (max-width: 1024px) {
  .summary-grid {
    grid-template-columns: repeat(2, 1fr);
  }

  .summary-grid-extended {
    grid-template-columns: repeat(2, 1fr);
  }

  .progress-assumptions-row {
    grid-template-columns: 1fr;
  }

  .sources-assets-row {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 768px) {
  .header-section {
    flex-direction: column;
  }

  .summary-grid,
  .summary-grid-extended {
    grid-template-columns: 1fr;
  }

  .sources-assets-row {
    grid-template-columns: 1fr;
  }

  .sources-list {
    grid-template-columns: 1fr;
  }

  .modal-content {
    margin: 20px;
  }

  .info-banner {
    flex-direction: column;
    gap: 8px;
  }

  .info-icon {
    margin-top: 0;
  }

  .asset-cards {
    grid-template-columns: 1fr;
  }

  .projection-table th,
  .projection-table td {
    padding: 8px 12px;
    font-size: 12px;
  }
}
</style>
