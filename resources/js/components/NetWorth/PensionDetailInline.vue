<template>
  <div class="pension-detail-inline">
    <!-- Back Button -->
    <button @click="$emit('back')" class="back-button mb-4">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
      </svg>
      Back to Pensions
    </button>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
      <p class="mt-4 text-gray-600">Loading pension details...</p>
    </div>

    <!-- Pension Content -->
    <div v-else class="space-y-6">
      <!-- Header -->
      <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex justify-between items-start">
          <div>
            <div class="flex items-center gap-3 mb-2">
              <span :class="['badge', badgeClass]">{{ pensionTypeLabel }}</span>
            </div>
            <h1 class="text-3xl font-bold text-gray-900">{{ pensionName }}</h1>
            <p class="text-lg text-gray-600 mt-1">{{ providerName }}</p>
          </div>
          <div class="flex space-x-2">
            <button
              @click="showEditModal = true"
              class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
            >
              Edit
            </button>
            <button
              v-if="pensionType !== 'state'"
              @click="confirmDelete"
              class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors"
            >
              Delete
            </button>
          </div>
        </div>

        <!-- Key Metrics -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
          <!-- DC Pension Metrics -->
          <template v-if="pensionType === 'dc'">
            <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
              <p class="text-sm text-gray-600">Current Fund Value</p>
              <p class="text-2xl font-bold text-blue-600">{{ formatCurrency(pension.current_fund_value) }}</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-4">
              <p class="text-sm text-gray-600">Monthly Contribution</p>
              <p class="text-2xl font-bold text-gray-900">{{ formatCurrency(totalMonthlyContribution) }}</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-4">
              <p class="text-sm text-gray-600">Retirement Age</p>
              <p class="text-2xl font-bold text-gray-900">{{ pension.retirement_age || 67 }}</p>
            </div>
          </template>

          <!-- DB Pension Metrics -->
          <template v-else-if="pensionType === 'db'">
            <div class="bg-purple-50 rounded-lg p-4 border border-purple-200">
              <p class="text-sm text-gray-600">Annual Pension</p>
              <p class="text-2xl font-bold text-purple-600">{{ formatCurrency(pension.accrued_annual_pension) }}<span class="text-sm">/yr</span></p>
            </div>
            <div class="bg-gray-50 rounded-lg p-4">
              <p class="text-sm text-gray-600">Lump Sum Entitlement</p>
              <p class="text-2xl font-bold text-gray-900">{{ formatCurrency(pension.lump_sum_entitlement || 0) }}</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-4">
              <p class="text-sm text-gray-600">Payment Start Age</p>
              <p class="text-2xl font-bold text-gray-900">{{ pension.payment_start_age || pension.normal_retirement_age || 65 }}</p>
            </div>
          </template>

          <!-- State Pension Metrics -->
          <template v-else-if="pensionType === 'state'">
            <div class="bg-green-50 rounded-lg p-4 border border-green-200">
              <p class="text-sm text-gray-600">Forecast Annual Amount</p>
              <p class="text-2xl font-bold text-green-600">{{ formatCurrency(pension.state_pension_forecast_annual || 0) }}<span class="text-sm">/yr</span></p>
            </div>
            <div class="bg-gray-50 rounded-lg p-4">
              <p class="text-sm text-gray-600">NI Years Completed</p>
              <p class="text-2xl font-bold text-gray-900">{{ pension.ni_years_completed || 0 }} / 35</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-4">
              <p class="text-sm text-gray-600">State Pension Age</p>
              <p class="text-2xl font-bold text-gray-900">{{ pension.state_pension_age || 67 }}</p>
            </div>
          </template>
        </div>
      </div>

      <!-- Tabs -->
      <div class="bg-white rounded-lg shadow-md">
        <div class="border-b border-gray-200">
          <nav class="flex -mb-px overflow-x-auto">
            <button
              v-for="tab in tabs"
              :key="tab.id"
              @click="activeTab = tab.id"
              class="px-6 py-3 border-b-2 font-medium text-sm transition-colors whitespace-nowrap"
              :class="
                activeTab === tab.id
                  ? 'border-blue-600 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              "
            >
              {{ tab.label }}
            </button>
          </nav>
        </div>

        <div class="p-6">
          <!-- Overview Tab -->
          <div v-show="activeTab === 'overview'" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <!-- DC Pension Details -->
              <template v-if="pensionType === 'dc'">
                <div>
                  <h3 class="text-lg font-semibold text-gray-800 mb-3">Pension Details</h3>
                  <dl class="space-y-2">
                    <div class="flex justify-between">
                      <dt class="text-sm text-gray-600">Scheme Name:</dt>
                      <dd class="text-sm font-medium text-gray-900 text-right">{{ pension.scheme_name || 'N/A' }}</dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="text-sm text-gray-600">Provider:</dt>
                      <dd class="text-sm font-medium text-gray-900">{{ pension.provider || 'N/A' }}</dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="text-sm text-gray-600">Pension Type:</dt>
                      <dd class="text-sm font-medium text-gray-900 capitalize">{{ pension.pension_type || 'N/A' }}</dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="text-sm text-gray-600">Policy Number:</dt>
                      <dd class="text-sm font-medium text-gray-900">{{ pension.policy_number || 'N/A' }}</dd>
                    </div>
                  </dl>
                </div>

                <div>
                  <h3 class="text-lg font-semibold text-gray-800 mb-3">Fund Value</h3>
                  <dl class="space-y-2">
                    <div class="flex justify-between">
                      <dt class="text-sm text-gray-600">Current Fund Value:</dt>
                      <dd class="text-sm font-medium text-blue-600 font-semibold">{{ formatCurrency(pension.current_fund_value) }}</dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="text-sm text-gray-600">Valuation Date:</dt>
                      <dd class="text-sm font-medium text-gray-900">{{ formatDate(pension.valuation_date) || 'N/A' }}</dd>
                    </div>
                  </dl>
                </div>

                <div>
                  <h3 class="text-lg font-semibold text-gray-800 mb-3">Contributions</h3>
                  <dl class="space-y-2">
                    <div v-if="pension.employee_contribution_percent" class="flex justify-between">
                      <dt class="text-sm text-gray-600">Employee Rate:</dt>
                      <dd class="text-sm font-medium text-gray-900">{{ pension.employee_contribution_percent }}% ({{ formatCurrency(monthlyEmployeeContribution) }}/mo)</dd>
                    </div>
                    <div v-if="pension.employer_contribution_percent" class="flex justify-between">
                      <dt class="text-sm text-gray-600">Employer Rate:</dt>
                      <dd class="text-sm font-medium text-gray-900">{{ pension.employer_contribution_percent }}% ({{ formatCurrency(monthlyEmployerContribution) }}/mo)</dd>
                    </div>
                    <div v-if="pension.employer_matching_limit" class="flex justify-between">
                      <dt class="text-sm text-gray-600">Employer Matching:</dt>
                      <dd class="text-sm font-medium text-gray-900">{{ pension.employer_matching_limit == 100 ? 'Full matching' : 'Up to ' + pension.employer_matching_limit + '%' }}</dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="text-sm text-gray-600">Total Monthly:</dt>
                      <dd class="text-sm font-medium text-gray-900">{{ formatCurrency(totalMonthlyContribution) }}</dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="text-sm text-gray-600">Annual Contribution:</dt>
                      <dd class="text-sm font-medium text-gray-900">{{ formatCurrency(annualContribution) }}</dd>
                    </div>
                  </dl>
                </div>

                <div>
                  <h3 class="text-lg font-semibold text-gray-800 mb-3">Retirement</h3>
                  <dl class="space-y-2">
                    <div class="flex justify-between">
                      <dt class="text-sm text-gray-600">Target Retirement Age:</dt>
                      <dd class="text-sm font-medium text-gray-900">{{ pension.retirement_age || 67 }}</dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="text-sm text-gray-600">Growth Rate Assumption:</dt>
                      <dd class="text-sm font-medium text-gray-900">{{ pension.growth_rate ? (pension.growth_rate * 100).toFixed(1) + '%' : 'N/A' }}</dd>
                    </div>
                  </dl>
                </div>
              </template>

              <!-- DB Pension Details -->
              <template v-else-if="pensionType === 'db'">
                <div>
                  <h3 class="text-lg font-semibold text-gray-800 mb-3">Scheme Details</h3>
                  <dl class="space-y-2">
                    <div class="flex justify-between">
                      <dt class="text-sm text-gray-600">Scheme Name:</dt>
                      <dd class="text-sm font-medium text-gray-900 text-right">{{ pension.scheme_name || 'N/A' }}</dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="text-sm text-gray-600">Employer:</dt>
                      <dd class="text-sm font-medium text-gray-900">{{ pension.employer || 'N/A' }}</dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="text-sm text-gray-600">Scheme Type:</dt>
                      <dd class="text-sm font-medium text-gray-900 capitalize">{{ formatDBSchemeType(pension.scheme_type) }}</dd>
                    </div>
                  </dl>
                </div>

                <div>
                  <h3 class="text-lg font-semibold text-gray-800 mb-3">Benefits</h3>
                  <dl class="space-y-2">
                    <div class="flex justify-between">
                      <dt class="text-sm text-gray-600">Annual Pension:</dt>
                      <dd class="text-sm font-medium text-purple-600 font-semibold">{{ formatCurrency(pension.accrued_annual_pension) }}/yr</dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="text-sm text-gray-600">Lump Sum Entitlement:</dt>
                      <dd class="text-sm font-medium text-gray-900">{{ formatCurrency(pension.lump_sum_entitlement || 0) }}</dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="text-sm text-gray-600">Revaluation Rate:</dt>
                      <dd class="text-sm font-medium text-gray-900">{{ pension.revaluation_rate ? (pension.revaluation_rate * 100).toFixed(1) + '%' : 'N/A' }}</dd>
                    </div>
                  </dl>
                </div>

                <div>
                  <h3 class="text-lg font-semibold text-gray-800 mb-3">Payment Details</h3>
                  <dl class="space-y-2">
                    <div class="flex justify-between">
                      <dt class="text-sm text-gray-600">Normal Retirement Age:</dt>
                      <dd class="text-sm font-medium text-gray-900">{{ pension.normal_retirement_age || 65 }}</dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="text-sm text-gray-600">Payment Start Age:</dt>
                      <dd class="text-sm font-medium text-gray-900">{{ pension.payment_start_age || pension.normal_retirement_age || 65 }}</dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="text-sm text-gray-600">Spouse Pension:</dt>
                      <dd class="text-sm font-medium text-gray-900">{{ pension.spouse_pension_percentage ? pension.spouse_pension_percentage + '%' : 'N/A' }}</dd>
                    </div>
                  </dl>
                </div>

                <div>
                  <h3 class="text-lg font-semibold text-gray-800 mb-3">Service Details</h3>
                  <dl class="space-y-2">
                    <div class="flex justify-between">
                      <dt class="text-sm text-gray-600">Date Joined:</dt>
                      <dd class="text-sm font-medium text-gray-900">{{ formatDate(pension.date_joined) || 'N/A' }}</dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="text-sm text-gray-600">Date Left:</dt>
                      <dd class="text-sm font-medium text-gray-900">{{ formatDate(pension.date_left) || 'Current' }}</dd>
                    </div>
                  </dl>
                </div>
              </template>

              <!-- State Pension Details -->
              <template v-else-if="pensionType === 'state'">
                <div>
                  <h3 class="text-lg font-semibold text-gray-800 mb-3">State Pension Details</h3>
                  <dl class="space-y-2">
                    <div class="flex justify-between">
                      <dt class="text-sm text-gray-600">Forecast Annual Amount:</dt>
                      <dd class="text-sm font-medium text-green-600 font-semibold">{{ formatCurrency(pension.state_pension_forecast_annual || 0) }}/yr</dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="text-sm text-gray-600">Weekly Amount:</dt>
                      <dd class="text-sm font-medium text-gray-900">{{ formatCurrency((pension.state_pension_forecast_annual || 0) / 52) }}/wk</dd>
                    </div>
                  </dl>
                </div>

                <div>
                  <h3 class="text-lg font-semibold text-gray-800 mb-3">National Insurance Record</h3>
                  <dl class="space-y-2">
                    <div class="flex justify-between">
                      <dt class="text-sm text-gray-600">NI Years Completed:</dt>
                      <dd class="text-sm font-medium text-gray-900">{{ pension.ni_years_completed || 0 }}</dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="text-sm text-gray-600">Years to Full Pension:</dt>
                      <dd class="text-sm font-medium text-gray-900">{{ Math.max(0, 35 - (pension.ni_years_completed || 0)) }}</dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="text-sm text-gray-600">State Pension Age:</dt>
                      <dd class="text-sm font-medium text-gray-900">{{ pension.state_pension_age || 67 }}</dd>
                    </div>
                  </dl>
                </div>
              </template>
            </div>

            <!-- Notes -->
            <div v-if="pension.notes" class="mt-6">
              <h3 class="text-lg font-semibold text-gray-800 mb-3">Notes</h3>
              <p class="text-gray-700 whitespace-pre-wrap">{{ pension.notes }}</p>
            </div>
          </div>

          <!-- Projections Tab (placeholder) -->
          <div v-show="activeTab === 'projections'" class="text-center py-12 text-gray-500">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12 mx-auto mb-4 text-gray-400">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
            </svg>
            <p class="text-lg font-medium">Projections Coming Soon</p>
            <p class="text-sm">Detailed pension projections will be available in a future update.</p>
          </div>

          <!-- Documents Tab (placeholder) -->
          <div v-show="activeTab === 'documents'" class="text-center py-12 text-gray-500">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12 mx-auto mb-4 text-gray-400">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
            </svg>
            <p class="text-lg font-medium">Documents Coming Soon</p>
            <p class="text-sm">Upload and manage pension documents in a future update.</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Edit Modal -->
    <UnifiedPensionForm
      v-if="showEditModal"
      :pension="pension"
      :state-pension="pensionType === 'state' ? pension : null"
      :is-edit="true"
      :initial-pension-type="pensionType"
      @close="showEditModal = false"
      @save="handleUpdate"
    />

    <!-- Delete Confirmation -->
    <ConfirmationModal
      v-if="showDeleteConfirm"
      title="Delete Pension"
      message="Are you sure you want to delete this pension? This action cannot be undone."
      @confirm="handleDelete"
      @cancel="showDeleteConfirm = false"
    />
  </div>
</template>

<script>
import { mapActions } from 'vuex';
import UnifiedPensionForm from '@/components/Retirement/UnifiedPensionForm.vue';
import ConfirmationModal from '@/components/Common/ConfirmationModal.vue';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'PensionDetailInline',
  mixins: [currencyMixin],

  components: {
    UnifiedPensionForm,
    ConfirmationModal,
  },

  props: {
    pension: {
      type: Object,
      required: true,
    },
    pensionType: {
      type: String,
      required: true,
      validator: (value) => ['dc', 'db', 'state'].includes(value),
    },
  },

  emits: ['back', 'deleted', 'pension-updated'],

  data() {
    return {
      activeTab: 'overview',
      loading: false,
      showEditModal: false,
      showDeleteConfirm: false,
    };
  },

  computed: {
    tabs() {
      return [
        { id: 'overview', label: 'Overview' },
        { id: 'projections', label: 'Projections' },
        { id: 'documents', label: 'Documents' },
      ];
    },

    pensionName() {
      if (this.pensionType === 'dc') {
        return this.pension.scheme_name || 'Defined Contribution Pension';
      } else if (this.pensionType === 'db') {
        return this.pension.scheme_name || 'Defined Benefit Pension';
      }
      return 'UK State Pension';
    },

    providerName() {
      if (this.pensionType === 'dc') {
        return this.pension.provider || '';
      } else if (this.pensionType === 'db') {
        return this.pension.employer || '';
      }
      return 'State Retirement Pension';
    },

    pensionTypeLabel() {
      if (this.pensionType === 'dc') {
        return this.formatDCPensionType(this.pension.pension_type);
      } else if (this.pensionType === 'db') {
        return this.formatDBSchemeType(this.pension.scheme_type);
      }
      return 'State Pension';
    },

    badgeClass() {
      const classes = {
        dc: 'badge-dc',
        db: 'badge-db',
        state: 'badge-state',
      };
      return classes[this.pensionType] || 'badge-dc';
    },

    // Calculate employee contribution from percentage or fixed amount
    monthlyEmployeeContribution() {
      if (this.pension.employee_contribution_percent && this.pension.annual_salary) {
        return (this.pension.annual_salary * this.pension.employee_contribution_percent / 100) / 12;
      }
      return this.pension.monthly_contribution_amount || 0;
    },

    // Calculate employer contribution from percentage
    monthlyEmployerContribution() {
      if (this.pension.employer_contribution_percent && this.pension.annual_salary) {
        return (this.pension.annual_salary * this.pension.employer_contribution_percent / 100) / 12;
      }
      return 0;
    },

    // Total monthly contribution (employee + employer)
    totalMonthlyContribution() {
      return this.monthlyEmployeeContribution + this.monthlyEmployerContribution;
    },

    // Annual contribution
    annualContribution() {
      return this.totalMonthlyContribution * 12;
    },
  },

  methods: {
    ...mapActions('retirement', [
      'updateDCPension',
      'updateDBPension',
      'updateStatePension',
      'deleteDCPension',
      'deleteDBPension',
      'fetchRetirementData',
    ]),

    formatDate(date) {
      if (!date) return '';
      return new Date(date).toLocaleDateString('en-GB', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
      });
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

    formatDBSchemeType(type) {
      const types = {
        final_salary: 'Final Salary',
        career_average: 'Career Average',
        public_sector: 'Public Sector',
      };
      return types[type] || 'DB Pension';
    },

    confirmDelete() {
      this.showDeleteConfirm = true;
    },

    async handleUpdate(data) {
      try {
        const pensionType = data._pensionType || this.pensionType;
        delete data._pensionType;

        if (pensionType === 'dc') {
          await this.updateDCPension({ id: this.pension.id, data });
        } else if (pensionType === 'db') {
          await this.updateDBPension({ id: this.pension.id, data });
        } else if (pensionType === 'state') {
          await this.updateStatePension(data);
        }

        this.showEditModal = false;

        // In preview mode, update local state only (API returned fake success, DB not updated)
        const isPreview = this.$store.getters['preview/isPreviewMode'];
        if (isPreview) {
          // Emit updated pension data to parent so it can update local state
          this.$emit('pension-updated', { ...this.pension, ...data });
        } else {
          // Normal mode: reload from API
          await this.fetchRetirementData();
        }

        this.$emit('back'); // Return to list to show updated data
      } catch (error) {
        console.error('Failed to update pension:', error);
      }
    },

    async handleDelete() {
      try {
        if (this.pensionType === 'dc') {
          await this.deleteDCPension(this.pension.id);
        } else if (this.pensionType === 'db') {
          await this.deleteDBPension(this.pension.id);
        }

        this.showDeleteConfirm = false;
        this.$emit('deleted');
      } catch (error) {
        console.error('Failed to delete pension:', error);
      }
    },
  },
};
</script>

<style scoped>
.pension-detail-inline {
  animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

.back-button {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  color: #374151;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.back-button:hover {
  background: #f3f4f6;
  border-color: #d1d5db;
}

.badge {
  display: inline-block;
  padding: 4px 10px;
  font-size: 12px;
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
</style>
