<template>
  <div class="retirement-income-tab">
    <!-- Back Button -->
    <button
      @click="$emit('back')"
      class="back-button"
    >
      <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
      </svg>
      Back to Pensions
    </button>

    <!-- Loading State -->
    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
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
      <div class="spinner"></div>
      <p>Loading retirement income data...</p>
    </div>

    <!-- Main Content -->
    <template v-else>
      <!-- Header Section -->
      <div class="header-section">
        <div class="header-left">
          <h3 class="section-title">Retirement Income Planner</h3>
          <p class="section-subtitle">Model your tax-optimised drawdown strategy from age {{ retirementAge }}</p>
        </div>
        <div class="header-right">
          <label class="spouse-toggle">
            <span class="toggle-label">Include spouse's assets</span>
            <button
              type="button"
              :class="['toggle-switch', { 'active': includeSpouse }]"
              @click="toggleSpouse"
            >
              <span class="toggle-slider"></span>
            </button>
          </label>
        </div>
      </div>

      <!-- Summary Cards -->
      <div class="summary-grid">
        <!-- Target Income Card -->
        <div class="summary-card target">
          <div class="card-header-row">
            <p class="summary-label">Target Annual Income</p>
            <button class="edit-btn" @click="showTargetModal = true">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
              </svg>
            </button>
          </div>
          <p class="summary-value">{{ formatCurrency(displayTargetIncome) }}</p>
          <p class="summary-subtitle">{{ customTargetIncome ? 'Custom target' : 'From retirement profile' }}</p>
        </div>

        <!-- Net Income Card -->
        <div :class="['summary-card', netIncomeClass]">
          <p class="summary-label">Projected Net Income</p>
          <p class="summary-value">{{ formatCurrency(taxBreakdown?.net_income || 0) }}</p>
          <p class="summary-subtitle">After tax ({{ formatPercent(taxBreakdown?.effective_rate || 0) }} effective rate)</p>
        </div>

        <!-- Tax Paid Card -->
        <div class="summary-card tax">
          <p class="summary-label">Annual Tax</p>
          <p class="summary-value">{{ formatCurrency(taxBreakdown?.total_tax || 0) }}</p>
          <p class="summary-subtitle">{{ formatCurrency(taxBreakdown?.tax_free_income || 0) }} tax-free</p>
        </div>
      </div>

      <!-- Income Sources Section -->
      <div class="sources-section">
        <div class="sources-header">
          <h4 class="sources-title">Income Sources</h4>
          <p class="sources-subtitle">Adjust sliders to see real-time tax impact</p>
        </div>

        <div class="sources-list">
          <IncomeSourceSlider
            v-for="allocation in displayAllocations"
            :key="`${allocation.source_type}-${allocation.source_id}`"
            :allocation="allocation"
            :account="getAccountForAllocation(allocation)"
            @update="handleAllocationUpdate"
          />
        </div>

        <div v-if="displayAllocations.length === 0" class="no-allocations">
          <p>No income allocations configured</p>
        </div>
      </div>

      <!-- Tax Breakdown Section -->
      <TaxBreakdownCard
        v-if="taxBreakdown"
        :breakdown="taxBreakdown"
      />

      <!-- Fund Depletion Chart -->
      <FundDepletionChart
        v-if="fundProjections.length > 0"
        :projections="fundProjections"
        :depletion-ages="depletionAges"
        :retirement-age="retirementAge"
      />

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
import TaxBreakdownCard from './TaxBreakdownCard.vue';
import FundDepletionChart from './FundDepletionChart.vue';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'RetirementIncomeTab',

  mixins: [currencyMixin],

  emits: ['back'],

  components: {
    IncomeSourceSlider,
    TaxBreakdownCard,
    FundDepletionChart,
  },

  data() {
    return {
      showTargetModal: false,
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
    ]),
    ...mapGetters('retirement', [
      'retirementIncomeData',
      'retirementIncomeTaxBreakdown',
      'retirementIncomeFundProjections',
      'retirementIncomeDepletionAges',
      'retirementIncomeAvailableAccounts',
    ]),

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
      return this.customTargetIncome || this.retirementIncome?.target_income || 0;
    },

    taxBreakdown() {
      return this.retirementIncome?.tax_breakdown || null;
    },

    fundProjections() {
      return this.retirementIncome?.fund_projections || [];
    },

    depletionAges() {
      return this.retirementIncome?.depletion_ages || {};
    },

    availableAccounts() {
      return this.retirementIncome?.available_accounts || [];
    },

    displayAllocations() {
      // Use incomeAllocations if it has items, otherwise fall back to API response
      if (this.incomeAllocations && this.incomeAllocations.length > 0) {
        return this.incomeAllocations;
      }
      return this.retirementIncome?.allocations || [];
    },

    hasAccounts() {
      return this.availableAccounts.length > 0;
    },

    netIncomeClass() {
      if (!this.taxBreakdown) return '';
      const netIncome = this.taxBreakdown.net_income || 0;
      const target = this.displayTargetIncome;
      if (netIncome >= target) return 'green';
      if (netIncome >= target * 0.9) return 'amber';
      return 'red';
    },
  },

  watch: {
    incomeAllocations: {
      handler() {
        this.debouncedCalculate();
      },
      deep: true,
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
      'calculateRetirementIncome',
      'toggleSpouseAssets',
      'setCustomTargetIncome',
      'updateIncomeAllocation',
    ]),

    async loadData() {
      try {
        await this.fetchRetirementIncome();
        this.tempTargetIncome = this.displayTargetIncome;
      } catch (error) {
        console.error('Failed to load retirement income data:', error);
      }
    },

    async toggleSpouse() {
      await this.toggleSpouseAssets(!this.includeSpouse);
    },

    handleAllocationUpdate({ sourceType, sourceId, amount }) {
      this.updateIncomeAllocation({ sourceType, sourceId, amount });
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
      return this.availableAccounts.find(
        a => a.type === allocation.source_type.replace('_pcls', '').replace('_drawdown', '') &&
             a.id === allocation.source_id
      ) || null;
    },

    formatPercent(value) {
      return (value * 100).toFixed(1) + '%';
    },
  },
};
</script>

<style scoped>
.retirement-income-tab {
  animation: fadeIn 0.3s ease-out;
}

/* Back Button */
.back-button {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  margin-bottom: 16px;
  font-size: 14px;
  font-weight: 500;
  @apply text-gray-700;
  background: white;
  @apply border border-gray-300;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.15s;
}

.back-button:hover {
  @apply bg-gray-50;
  @apply border-gray-400;
}

.back-button svg {
  width: 20px;
  height: 20px;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
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

.spinner {
  @apply w-12 h-12 border-3 border-gray-200 border-t-primary-500 rounded-full mb-4;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.loading-state p {
  @apply text-gray-500;
  font-size: 16px;
  margin: 0;
}

/* Error State */
.error-state {
  text-align: center;
  padding: 60px 40px;
  background: white;
  border-radius: 12px;
  @apply border border-red-200;
}

.error-icon {
  width: 48px;
  height: 48px;
  @apply text-red-500;
  margin: 0 auto 16px;
}

.error-state p {
  @apply text-gray-500;
  font-size: 16px;
  margin: 0 0 16px 0;
}

.retry-button {
  @apply bg-primary-500;
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
  @apply bg-blue-600;
}

/* Empty State */
.empty-state {
  text-align: center;
  padding: 80px 40px;
  background: white;
  border-radius: 12px;
  @apply border-2 border-dashed border-gray-300;
}

.empty-icon {
  width: 64px;
  height: 64px;
  @apply text-gray-400;
  margin: 0 auto 16px;
}

.empty-state p {
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
  @apply text-gray-900;
  margin: 0 0 4px 0;
}

.section-subtitle {
  font-size: 14px;
  @apply text-gray-500;
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
  @apply text-gray-700;
  font-weight: 500;
}

.toggle-switch {
  position: relative;
  width: 48px;
  height: 26px;
  @apply bg-gray-300;
  border: none;
  border-radius: 13px;
  cursor: pointer;
  transition: background 0.2s;
  padding: 0;
}

.toggle-switch.active {
  @apply bg-primary-500;
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
  @apply border border-gray-200;
}

.summary-card.target {
  background: linear-gradient(135deg, theme('colors.blue.50') 0%, theme('colors.blue.100') 100%);
  @apply border-blue-200;
}

.summary-card.green {
  background: linear-gradient(135deg, theme('colors.green.50') 0%, theme('colors.green.100') 100%);
  @apply border-green-200;
}

.summary-card.amber {
  background: linear-gradient(135deg, theme('colors.amber.50') 0%, theme('colors.amber.100') 100%);
  @apply border-amber-200;
}

.summary-card.red {
  background: linear-gradient(135deg, theme('colors.red.50') 0%, theme('colors.red.100') 100%);
  @apply border-red-200;
}

.summary-card.tax {
  background: linear-gradient(135deg, theme('colors.purple.50') 0%, theme('colors.purple.100') 100%);
  @apply border-purple-100;
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
  @apply text-gray-500;
  transition: color 0.2s;
}

.edit-btn:hover {
  @apply text-primary-500;
}

.edit-btn svg {
  width: 16px;
  height: 16px;
}

.summary-label {
  font-size: 14px;
  @apply text-gray-500;
  margin: 0 0 8px 0;
  font-weight: 500;
}

.summary-value {
  font-size: 28px;
  font-weight: 700;
  @apply text-gray-900;
  margin: 0;
}

.summary-subtitle {
  font-size: 13px;
  @apply text-gray-500;
  margin: 8px 0 0 0;
}

/* Sources Section */
.sources-section {
  background: white;
  border-radius: 12px;
  padding: 24px;
  @apply border border-gray-200;
  margin-bottom: 24px;
}

.sources-header {
  margin-bottom: 20px;
}

.sources-title {
  font-size: 18px;
  font-weight: 600;
  @apply text-gray-900;
  margin: 0 0 4px 0;
}

.sources-subtitle {
  font-size: 14px;
  @apply text-gray-500;
  margin: 0;
}

.sources-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.no-allocations {
  text-align: center;
  padding: 40px;
  @apply bg-gray-50;
  border-radius: 8px;
}

.no-allocations p {
  @apply text-gray-500;
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
  @apply text-gray-900;
  margin: 0 0 8px 0;
}

.modal-description {
  font-size: 14px;
  @apply text-gray-500;
  margin: 0 0 24px 0;
}

.input-group {
  display: flex;
  align-items: center;
  @apply bg-gray-50;
  @apply border border-gray-200;
  border-radius: 8px;
  padding: 4px 12px;
  margin-bottom: 24px;
}

.input-prefix {
  font-size: 18px;
  font-weight: 600;
  @apply text-gray-500;
}

.target-input {
  flex: 1;
  border: none;
  background: transparent;
  font-size: 24px;
  font-weight: 700;
  @apply text-gray-900;
  padding: 12px;
  text-align: center;
}

.target-input:focus {
  outline: none;
}

.input-suffix {
  font-size: 14px;
  @apply text-gray-500;
}

.modal-actions {
  display: flex;
  gap: 12px;
}

.btn-secondary {
  flex: 1;
  padding: 12px 20px;
  @apply bg-gray-100;
  @apply text-gray-700;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}

.btn-secondary:hover {
  @apply bg-gray-200;
}

.btn-primary {
  flex: 1;
  padding: 12px 20px;
  @apply bg-primary-500;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}

.btn-primary:hover {
  @apply bg-blue-600;
}

@media (max-width: 768px) {
  .header-section {
    flex-direction: column;
  }

  .summary-grid {
    grid-template-columns: 1fr;
  }

  .modal-content {
    margin: 20px;
  }
}
</style>
