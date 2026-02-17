<template>
  <div class="retirement-readiness">
    <!-- Pensions Overview -->
    <div class="pension-overview">
      <div class="section-header-row">
        <h3 class="section-title">Your Pensions</h3>
        <div class="flex gap-3">
          <button v-preview-disabled="'add'" @click="showPensionForm = true" class="add-pension-btn">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="btn-icon">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            Add Pension
          </button>
          <button v-preview-disabled="'upload'" @click="showUploadModal = true" class="upload-btn">
            <svg class="btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
            </svg>
            Upload Statement
          </button>
        </div>
      </div>

      <!-- Empty State -->
      <div v-if="allPensions.length === 0" class="empty-state">
        <p class="empty-message">No pensions added yet.</p>
        <button v-preview-disabled="'add'" @click="showPensionForm = true" class="add-pension-button">
          Add Your First Pension
        </button>
      </div>

      <!-- Pensions Grid -->
      <div v-else class="pensions-grid">
        <!-- DC Pensions -->
        <div
          v-for="pension in dcPensions"
          :key="'dc-' + pension.id"
          @click="viewPension('dc', pension.id)"
          class="pension-card"
        >
          <!-- Risk Badge - Top Right Corner -->
          <RiskBadge
            v-if="pension.risk_preference"
            :level="pension.risk_preference"
            size="sm"
            :abbreviated="true"
            :has-custom-risk="pension.has_custom_risk"
            style="position: absolute; top: 12px; right: 12px; z-index: 10;"
          />
          <div class="card-header">
            <span class="badge badge-dc">
              {{ formatDCPensionType(pension.pension_type) }}
            </span>
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
                <span class="detail-label">Retirement Age</span>
                <span class="detail-value">{{ pension.retirement_age || targetRetirementAge }}</span>
              </div>

              <div class="detail-row">
                <span class="detail-label">Monthly Contribution</span>
                <span class="detail-value">{{ formatCurrency(pension.monthly_contribution_amount || 0) }}</span>
              </div>

              <div class="detail-row">
                <span class="detail-label">Lump Sum</span>
                <span class="detail-value">{{ formatCurrency(pension.lump_sum_contribution || 0) }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- DB Pensions -->
        <div
          v-for="pension in dbPensions"
          :key="'db-' + pension.id"
          @click="viewPension('db', pension.id)"
          class="pension-card"
        >
          <div class="card-header">
            <span class="badge badge-db">
              {{ formatDBPensionType(pension.scheme_type) }}
            </span>
          </div>

          <div class="card-content">
            <h4 class="pension-scheme">{{ pension.scheme_name || 'Defined Benefit' }}</h4>
            <p class="pension-provider-text">{{ pension.provider || '' }}</p>

            <div class="pension-details">
              <div class="detail-row">
                <span class="detail-label">Annual Income</span>
                <span class="detail-value">{{ formatCurrency(pension.annual_income) }}<span class="text-xs text-gray-500">/yr</span></span>
              </div>

              <div class="detail-row">
                <span class="detail-label">Payment Start Age</span>
                <span class="detail-value">{{ pension.payment_start_age || targetRetirementAge }}</span>
              </div>

              <div class="detail-row">
                <span class="detail-label">Revaluation Rate</span>
                <span class="detail-value">{{ pension.revaluation_rate ? (pension.revaluation_rate * 100).toFixed(1) + '%' : '' }}</span>
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
          @click="viewPension('state', 'state')"
          class="pension-card"
        >
          <div class="card-header">
            <span class="badge badge-state">
              State Pension
            </span>
          </div>

          <div class="card-content">
            <h4 class="pension-scheme">UK State Pension</h4>
            <p class="pension-provider-text">State Retirement Pension</p>

            <div class="pension-details">
              <div class="detail-row">
                <span class="detail-label">Forecast</span>
                <span class="detail-value">{{ formatCurrency(statePensionForecast) }}<span class="text-xs text-gray-500">/yr</span></span>
              </div>

              <div class="detail-row">
                <span class="detail-label">National Insurance Years</span>
                <span class="detail-value">{{ niYears }} / 35</span>
              </div>

              <div class="detail-row">
                <span class="detail-label">Payment Age</span>
                <span class="detail-value">{{ statePension.state_pension_age || 67 }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Pension Wealth Summary -->
    <div class="bg-white rounded-lg shadow p-6">
      <h3 class="text-lg font-semibold text-gray-900 mb-6">Pension Wealth Summary</h3>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
        <!-- DC Pensions -->
        <div class="border-l-4 border-blue-500 pl-4">
          <p class="text-sm text-gray-600 mb-1">Money Purchase Pensions</p>
          <p class="text-2xl font-bold text-gray-900">{{ formatCurrency(dcPensionValue) }}</p>
          <p class="text-sm text-gray-500 mt-1">{{ dcPensionCount }} pension{{ dcPensionCount !== 1 ? 's' : '' }}</p>
        </div>

        <!-- DB Pensions -->
        <div class="border-l-4 border-purple-500 pl-4">
          <p class="text-sm text-gray-600 mb-1">Final Salary Pensions</p>
          <p class="text-2xl font-bold text-gray-900">{{ formatCurrency(dbPensionIncome) }}<span class="text-sm text-gray-500">/year</span></p>
          <p class="text-sm text-gray-500 mt-1">{{ dbPensionCount }} scheme{{ dbPensionCount !== 1 ? 's' : '' }}</p>
        </div>

        <!-- State Pension -->
        <div class="border-l-4 border-green-500 pl-4">
          <p class="text-sm text-gray-600 mb-1">State Pension</p>
          <p class="text-2xl font-bold text-gray-900">{{ formatCurrency(statePensionForecast) }}<span class="text-sm text-gray-500">/year</span></p>
          <p class="text-sm text-gray-500 mt-1">{{ niYears }} National Insurance years</p>
        </div>
      </div>
    </div>

    <!-- Overview Cards (moved to bottom) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6 mt-6">
      <!-- Years to Retirement -->
      <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between mb-2">
          <h3 class="text-sm font-medium text-gray-600">Years to Retirement</h3>
          <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
          </svg>
        </div>
        <p class="text-3xl font-bold text-gray-900">{{ yearsToRetirement }}</p>
        <p class="text-sm text-gray-500 mt-1">Target age: {{ targetRetirementAge }}</p>
      </div>

      <!-- Projected Income -->
      <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between mb-2">
          <h3 class="text-sm font-medium text-gray-600">Projected Income</h3>
          <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
          </svg>
        </div>
        <p class="text-xl font-semibold text-gray-500">Coming Soon</p>
        <p class="text-sm text-gray-400 mt-1">Not available in this version</p>
      </div>
    </div>

    <!-- Unified Pension Form Modal -->
    <UnifiedPensionForm
      v-if="showPensionForm"
      :pension="selectedPension"
      :state-pension="statePension"
      @close="closePensionForm"
      @save="handlePensionSave"
    />

    <!-- Document Upload Modal -->
    <DocumentUploadModal
      v-if="showUploadModal"
      document-type="pension_statement"
      @close="closeUploadModal"
      @saved="handleDocumentSaved"
      @manual-entry="closeUploadModal(); showPensionForm = true;"
    />
  </div>
</template>

<script>
import { mapState, mapGetters } from 'vuex';
import UnifiedPensionForm from '../../components/Retirement/UnifiedPensionForm.vue';
import DocumentUploadModal from '@/components/Shared/DocumentUploadModal.vue';
import RiskBadge from '@/components/Shared/RiskBadge.vue';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'RetirementReadiness',

  mixins: [currencyMixin],

  components: {
    UnifiedPensionForm,
    DocumentUploadModal,
    RiskBadge,
  },

  emits: ['select-pension'],

  data() {
    return {
      showPensionForm: false,
      showUploadModal: false,
      selectedPension: null,
    };
  },

  computed: {
    ...mapState('retirement', ['dcPensions', 'dbPensions', 'statePension', 'profile']),
    ...mapGetters('retirement', ['yearsToRetirement']),

    targetRetirementAge() {
      return this.profile?.target_retirement_age || 67;
    },

    dcPensionValue() {
      return this.dcPensions.reduce((sum, p) => sum + parseFloat(p.current_fund_value || 0), 0);
    },

    dcPensionCount() {
      return this.dcPensions.length;
    },

    dbPensionIncome() {
      return this.dbPensions.reduce((sum, p) => sum + parseFloat(p.annual_income || 0), 0);
    },

    dbPensionCount() {
      return this.dbPensions.length;
    },

    statePensionForecast() {
      return parseFloat(this.statePension?.state_pension_forecast_annual || 0);
    },

    niYears() {
      return this.statePension?.ni_years_completed || 0;
    },

    allPensions() {
      const all = [...this.dcPensions, ...this.dbPensions];
      if (this.statePension) {
        all.push(this.statePension);
      }
      return all;
    },
  },

  methods: {
    formatOwnershipType(type) {
      const types = {
        individual: 'Individual',
        joint: 'Joint',
        trust: 'Trust',
      };
      return types[type] || 'Individual';
    },

    getOwnershipBadgeClass(type) {
      const classes = {
        individual: 'bg-gray-100 text-gray-800',
        joint: 'bg-purple-100 text-purple-800',
        trust: 'bg-blue-100 text-blue-800',
      };
      return classes[type] || 'bg-gray-100 text-gray-800';
    },

    formatDCPensionType(type) {
      const types = {
        occupational: 'Occupational',
        sipp: 'Self-Invested Personal Pension',
        personal: 'Personal',
        stakeholder: 'Stakeholder',
        workplace: 'Workplace',
      };
      return types[type] || 'Defined Contribution Pension';
    },

    formatDBPensionType(type) {
      const types = {
        final_salary: 'Final Salary',
        career_average: 'Career Average',
        public_sector: 'Public Sector',
      };
      return types[type] || 'Defined Benefit Pension';
    },

    closePensionForm() {
      this.showPensionForm = false;
      this.selectedPension = null;
    },

    async handlePensionSave(data) {
      const pensionType = data._pensionType;
      delete data._pensionType;

      try {
        if (pensionType === 'state') {
          await this.$store.dispatch('retirement/updateStatePension', data);
        } else if (pensionType === 'dc') {
          await this.$store.dispatch('retirement/createDCPension', data);
        } else if (pensionType === 'db') {
          await this.$store.dispatch('retirement/createDBPension', data);
        }
        // Refresh data
        await this.$store.dispatch('retirement/fetchRetirementData');
      } catch (error) {
        console.error('Failed to save pension:', error);
        alert('Failed to save pension. Please try again.');
      }

      this.closePensionForm();
    },

    viewPension(type, id) {
      // Emit event to parent to switch to detail view
      let pension = null;
      if (type === 'dc') {
        pension = this.dcPensions.find(p => p.id === id);
      } else if (type === 'db') {
        pension = this.dbPensions.find(p => p.id === id);
      } else if (type === 'state') {
        pension = this.statePension;
      }
      if (pension) {
        this.$emit('select-pension', pension, type);
      }
    },

    closeUploadModal() {
      this.showUploadModal = false;
    },

    async handleDocumentSaved(savedData) {
      this.showUploadModal = false;
      // Refresh retirement data
      await this.$store.dispatch('retirement/fetchRetirementData');
    },
  },
};
</script>

<style scoped>
/* Animations */
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

.retirement-readiness > div {
  animation: fadeIn 0.5s ease-out;
}

/* Pension Cards Styling */
.pension-overview {
  margin-bottom: 24px;
}

.section-header-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  flex-wrap: wrap;
  gap: 16px;
}

.section-title {
  font-size: 20px;
  font-weight: 600;
  color: #111827;
  margin: 0;
}

.add-pension-btn {
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

.add-pension-btn:hover {
  background: #2563eb;
}

.upload-btn {
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

.upload-btn:hover {
  background: #eff6ff;
}

.btn-icon {
  width: 20px;
  height: 20px;
}

.pensions-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 16px;
}

.pension-card {
  background: white;
  border-radius: 12px;
  border: 1px solid #e5e7eb;
  padding: 20px;
  cursor: pointer;
  transition: all 0.2s ease;
  position: relative;
}

.pension-card :deep(.risk-badge-corner) {
  position: absolute;
  top: 12px;
  right: 12px;
  z-index: 10;
}

.pension-card:hover {
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  transform: translateY(-2px);
  @apply border-primary-500;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 16px;
  flex-wrap: wrap;
  gap: 8px;
}

.ownership-badge {
  display: inline-block;
  padding: 4px 12px;
  font-size: 12px;
  font-weight: 600;
  border-radius: 6px;
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
  line-height: 1.3;
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
  gap: 10px;
  margin-top: 4px;
  padding-top: 12px;
  border-top: 1px solid #e5e7eb;
}

.detail-row {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

@media (min-width: 640px) {
  .detail-row {
    flex-direction: row;
    justify-content: space-between;
    align-items: center;
    gap: 0;
  }
}

.detail-label {
  font-size: 14px;
  color: #6b7280;
  font-weight: 500;
}

.detail-value {
  font-size: 16px;
  color: #111827;
  font-weight: 700;
}

.empty-state {
  text-align: center;
  padding: 60px 20px;
  background: white;
  border-radius: 12px;
  border: 2px dashed #d1d5db;
}

.empty-message {
  color: #6b7280;
  font-size: 16px;
  margin-bottom: 20px;
}

.add-pension-button {
  padding: 12px 24px;
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
  background: #2563eb;
}

@media (max-width: 768px) {
  .section-header-row {
    flex-direction: column;
    align-items: flex-start;
  }

  .add-pension-btn {
    width: 100%;
    justify-content: center;
  }

  .pensions-grid {
    grid-template-columns: 1fr;
  }
}
</style>
