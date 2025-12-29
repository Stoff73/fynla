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

    <!-- Pension List View (default) -->
    <template v-else>
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
          <button @click="showPensionForm = true" class="add-pension-button">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="button-icon">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            Add Pension
          </button>
          <button @click="showUploadModal = true" class="upload-button">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="button-icon">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
            </svg>
            Upload Statement
          </button>
        </div>
      </div>

      <!-- Tab Navigation -->
      <div class="tab-navigation">
        <nav class="tabs-nav">
          <button
            v-for="tab in tabs"
            :key="tab.id"
            @click="activeTab = tab.id"
            :class="['tab-button', activeTab === tab.id ? 'active' : '']"
          >
            {{ tab.label }}
          </button>
        </nav>
      </div>

      <!-- Current Pensions Tab -->
      <template v-if="activeTab === 'current'">
        <div v-if="loading" class="loading-state">
          <p>Loading pensions...</p>
        </div>

        <div v-else-if="error" class="error-state">
          <p>{{ error }}</p>
        </div>

        <div v-else-if="allPensions.length === 0" class="empty-state">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="empty-icon">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <p>No pensions found</p>
        <p class="empty-subtitle">Add your first pension to track your retirement savings</p>
      </div>

      <div v-else class="pensions-grid">
        <!-- DC Pensions -->
        <div
          v-for="pension in dcPensions"
          :key="'dc-' + pension.id"
          @click="selectPension(pension, 'dc')"
          class="pension-card"
        >
          <div class="card-header">
            <span class="badge badge-dc">
              {{ formatDCPensionType(pension.pension_type) }}
            </span>
            <RiskBadge
              v-if="pension.risk_preference"
              :level="pension.risk_preference"
              size="sm"
              :abbreviated="true"
              :has-custom-risk="pension.has_custom_risk"
              class="risk-badge-right"
            />
          </div>
          <div class="card-content">
            <h4 class="pension-scheme">{{ pension.scheme_name || 'Defined Contribution' }}</h4>
            <p class="pension-provider-text">{{ pension.provider || '' }}</p>
            <div class="pension-details">
              <div class="detail-row">
                <span class="detail-label">Current Value</span>
                <span class="detail-value">{{ formatCurrency(pension.current_fund_value) }}</span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Monthly Contribution</span>
                <span class="detail-value">{{ formatCurrency(calculateMonthlyContribution(pension)) }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- DB Pensions -->
        <div
          v-for="pension in dbPensions"
          :key="'db-' + pension.id"
          @click="selectPension(pension, 'db')"
          class="pension-card"
        >
          <div class="card-header">
            <span class="badge badge-db">
              {{ formatDBPensionType(pension.scheme_type) }}
            </span>
          </div>
          <div class="card-content">
            <h4 class="pension-scheme">{{ pension.scheme_name || 'Defined Benefit' }}</h4>
            <p class="pension-provider-text">{{ pension.employer || '' }}</p>
            <div class="pension-details">
              <div class="detail-row">
                <span class="detail-label">Annual Income</span>
                <span class="detail-value">{{ formatCurrency(pension.accrued_annual_pension) }}<span class="text-xs text-gray-500">/yr</span></span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Lump Sum</span>
                <span class="detail-value">{{ formatCurrency(pension.lump_sum_entitlement || 0) }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- State Pension -->
        <div
          v-if="statePension"
          @click="selectPension(statePension, 'state')"
          class="pension-card"
        >
          <div class="card-header">
            <span class="badge badge-state">State Pension</span>
          </div>
          <div class="card-content">
            <h4 class="pension-scheme">UK State Pension</h4>
            <p class="pension-provider-text">State Retirement Pension</p>
            <div class="pension-details">
              <div class="detail-row">
                <span class="detail-label">Forecast</span>
                <span class="detail-value">{{ formatCurrency(statePension.state_pension_forecast_annual || 0) }}<span class="text-xs text-gray-500">/yr</span></span>
              </div>
              <div class="detail-row">
                <span class="detail-label">NI Years</span>
                <span class="detail-value">{{ statePension.ni_years_completed || 0 }} / 35</span>
              </div>
            </div>
          </div>
        </div>
      </div>

        <!-- Pension Wealth Summary -->
        <div v-if="allPensions.length > 0" class="wealth-summary">
          <h3 class="summary-title">Pension Wealth Summary</h3>
          <div class="summary-grid">
            <div class="summary-item dc">
              <p class="summary-label">Money Purchase Pensions</p>
              <p class="summary-value">{{ formatCurrency(dcPensionValue) }}</p>
              <p class="summary-count">{{ dcPensions.length }} pension{{ dcPensions.length !== 1 ? 's' : '' }}</p>
            </div>
            <div class="summary-item db">
              <p class="summary-label">Final Salary Pensions</p>
              <p class="summary-value">{{ formatCurrency(dbPensionIncome) }}<span class="text-sm text-gray-500">/year</span></p>
              <p class="summary-count">{{ dbPensions.length }} scheme{{ dbPensions.length !== 1 ? 's' : '' }}</p>
            </div>
            <div class="summary-item state">
              <p class="summary-label">State Pension</p>
              <p class="summary-value">{{ formatCurrency(statePensionForecast) }}<span class="text-sm text-gray-500">/year</span></p>
              <p class="summary-count">{{ statePension?.ni_years_completed || 0 }} NI years</p>
            </div>
          </div>
        </div>
      </template>

      <!-- Future Value Tab -->
      <FutureValueTab
        v-else-if="activeTab === 'future'"
        :projections="projections"
        :loading="projectionsLoading"
      />

      <!-- Strategies Tab -->
      <StrategiesTab v-else-if="activeTab === 'strategies'" />

      <!-- Retirement Income Tab -->
      <RetirementIncomeTab v-else-if="activeTab === 'income'" />
    </template>

    <!-- Pension Form Modal -->
    <UnifiedPensionForm
      v-if="showPensionForm"
      :pension="editingPension"
      :state-pension="statePension"
      :is-edit="!!editingPension"
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
import FutureValueTab from '@/components/Retirement/FutureValueTab.vue';
import StrategiesTab from '@/components/Retirement/StrategiesTab.vue';
import RetirementIncomeTab from '@/components/Retirement/RetirementIncomeTab.vue';

export default {
  name: 'PensionList',

  components: {
    PensionDetailInline,
    UnifiedPensionForm,
    DocumentUploadModal,
    RiskBadge,
    FutureValueTab,
    StrategiesTab,
    RetirementIncomeTab,
  },

  data() {
    return {
      activeTab: 'current',
      tabs: [
        { id: 'current', label: 'Pensions' },
        { id: 'future', label: 'Future Value' },
        { id: 'strategies', label: 'Strategies' },
        { id: 'income', label: 'Retirement Income' },
      ],
      selectedPension: null,
      selectedPensionType: null,
      showPensionForm: false,
      showUploadModal: false,
      editingPension: null,
      successMessage: null,
      errorMessage: null,
    };
  },

  computed: {
    ...mapState('retirement', ['dcPensions', 'dbPensions', 'statePension', 'loading', 'error', 'projections', 'projectionsLoading']),

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
  },

  watch: {
    activeTab(newTab) {
      if (newTab === 'future' && !this.projections) {
        this.loadProjections();
      }
    },
  },

  methods: {
    ...mapActions('retirement', ['fetchRetirementData', 'createDCPension', 'createDBPension', 'updateStatePension', 'fetchProjections']),
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

      // In preview mode, don't reload from API (changes are session-only)
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
      // In preview mode, update the selected pension locally
      // This keeps the changes visible in the UI until page refresh
      this.selectedPension = updatedPension;
    },

    closePensionForm() {
      this.showPensionForm = false;
      this.editingPension = null;
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
    },

    formatCurrency(value) {
      if (value === null || value === undefined) return '£0';
      return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: 'GBP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
      }).format(value);
    },

    calculateMonthlyContribution(pension) {
      // For occupational pensions, calculate from percentages
      if (pension.employee_contribution_percent && pension.annual_salary) {
        const employeeMonthly = (pension.annual_salary * pension.employee_contribution_percent / 100) / 12;
        const employerMonthly = pension.employer_contribution_percent
          ? (pension.annual_salary * pension.employer_contribution_percent / 100) / 12
          : 0;
        return employeeMonthly + employerMonthly;
      }
      // For SIPPs and personal pensions, use the fixed monthly amount
      return pension.monthly_contribution_amount || 0;
    },

    formatDCPensionType(type) {
      const types = {
        occupational: 'Occupational',
        sipp: 'SIPP',
        personal: 'Personal',
        stakeholder: 'Stakeholder',
        workplace: 'Workplace',
      };
      return types[type] || 'DC Pension';
    },

    formatDBPensionType(type) {
      const types = {
        final_salary: 'Final Salary',
        career_average: 'Career Average',
        public_sector: 'Public Sector',
      };
      return types[type] || 'DB Pension';
    },
  },

  async mounted() {
    this.setDetailView(false);
    await this.fetchRetirementData();
  },
};
</script>

<style scoped>
.pension-list {
  padding: 24px;
}

/* Tab Navigation */
.tab-navigation {
  margin-bottom: 24px;
}

.tabs-nav {
  display: flex;
  gap: 8px;
  border-bottom: 1px solid #e5e7eb;
  padding-bottom: 0;
}

.tab-button {
  padding: 12px 24px;
  font-size: 14px;
  font-weight: 600;
  color: #6b7280;
  background: transparent;
  border: none;
  border-bottom: 2px solid transparent;
  cursor: pointer;
  transition: all 0.2s;
  margin-bottom: -1px;
}

.tab-button:hover {
  color: #3b82f6;
}

.tab-button.active {
  color: #3b82f6;
  border-bottom-color: #3b82f6;
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
  color: #111827;
  margin: 0;
}

.risk-profile-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  background: #eff6ff;
  color: #2563eb;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 500;
  text-decoration: none;
  transition: all 0.2s;
}

.risk-profile-link:hover {
  background: #dbeafe;
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
  background: #3b82f6;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}

.add-pension-button:hover {
  background: #2563eb;
}

.upload-button {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  background: white;
  color: #3b82f6;
  border: 2px solid #3b82f6;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.upload-button:hover {
  background: #eff6ff;
}

.button-icon {
  width: 20px;
  height: 20px;
}

.pensions-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 20px;
  margin-bottom: 24px;
}

.pension-card {
  background: white;
  border-radius: 12px;
  border: 1px solid #e5e7eb;
  padding: 20px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.pension-card:hover {
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  transform: translateY(-2px);
  border-color: #3b82f6;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 12px;
  gap: 8px;
}

.risk-badge-right {
  margin-left: auto;
}

.badge {
  display: inline-block;
  padding: 4px 10px;
  font-size: 11px;
  font-weight: 600;
  border-radius: 6px;
}

.badge-dc {
  background: #dbeafe;
  color: #1e40af;
}

.badge-db {
  background: #e9d5ff;
  color: #6b21a8;
}

.badge-state {
  background: #d1fae5;
  color: #065f46;
}

.card-content {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.pension-scheme {
  font-size: 18px;
  font-weight: 700;
  color: #111827;
  margin: 0;
}

.pension-provider-text {
  font-size: 14px;
  color: #6b7280;
  margin: 0;
  min-height: 20px;
}

.pension-details {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 8px;
  padding-top: 12px;
  border-top: 1px solid #e5e7eb;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.detail-label {
  font-size: 14px;
  color: #6b7280;
}

.detail-value {
  font-size: 16px;
  color: #111827;
  font-weight: 700;
}

.loading-state,
.error-state,
.empty-state {
  text-align: center;
  padding: 60px 20px;
}

.loading-state p,
.error-state p {
  color: #6b7280;
  font-size: 16px;
  margin: 0;
}

.error-state p {
  color: #ef4444;
}

.empty-state {
  background: white;
  border-radius: 12px;
  padding: 80px 40px;
  border: 2px dashed #d1d5db;
}

.empty-icon {
  width: 64px;
  height: 64px;
  color: #9ca3af;
  margin: 0 auto 16px;
}

.empty-state p {
  color: #6b7280;
  font-size: 18px;
  font-weight: 600;
  margin: 0 0 8px 0;
}

.empty-subtitle {
  color: #9ca3af;
  font-size: 14px;
  font-weight: 400;
}

/* Wealth Summary */
.wealth-summary {
  background: white;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  border: 1px solid #e5e7eb;
}

.summary-title {
  font-size: 18px;
  font-weight: 600;
  color: #111827;
  margin: 0 0 20px 0;
}

.summary-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 24px;
}

.summary-item {
  padding-left: 16px;
  border-left: 4px solid;
}

.summary-item.dc {
  border-left-color: #3b82f6;
}

.summary-item.db {
  border-left-color: #8b5cf6;
}

.summary-item.state {
  border-left-color: #10b981;
}

.summary-label {
  font-size: 14px;
  color: #6b7280;
  margin: 0 0 4px 0;
}

.summary-value {
  font-size: 24px;
  font-weight: 700;
  color: #111827;
  margin: 0;
}

.summary-count {
  font-size: 13px;
  color: #9ca3af;
  margin: 4px 0 0 0;
}

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
  background: #10b981;
  color: white;
}

.notification.error {
  background: #ef4444;
  color: white;
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

  .pensions-grid {
    grid-template-columns: 1fr;
  }
}
</style>
