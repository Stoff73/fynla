<template>
  <AppLayout>
    <div class="container mx-auto px-4 py-8">
      <!-- Loading State -->
      <div v-if="loading" class="text-center py-12">
        <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
        <p class="mt-4 text-gray-600">Loading pension details...</p>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-lg p-6 text-center">
        <p class="text-red-600">{{ error }}</p>
        <button
          @click="loadPension"
          class="mt-4 px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors"
        >
          Retry
        </button>
      </div>

      <!-- Pension Content -->
      <div v-else-if="pension" class="space-y-6">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-md p-6">
          <div class="flex justify-between items-start">
            <div>
              <h1 class="text-3xl font-bold text-gray-900">{{ pensionTitle }}</h1>
              <p class="text-lg text-gray-600 mt-1">{{ pensionTypeLabel }}</p>
            </div>
            <div class="flex space-x-2">
              <button
                @click="editPension"
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

          <!-- Key Metrics - DC Pension -->
          <div v-if="pensionType === 'dc'" class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-6">
            <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
              <p class="text-sm text-gray-600">Current Fund Value</p>
              <p class="text-2xl font-bold text-blue-600">{{ formatCurrency(pension.current_fund_value) }}</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-4">
              <p class="text-sm text-gray-600">Projected Value</p>
              <p class="text-2xl font-bold text-green-600">{{ formatCurrency(pension.projected_fund_value || 0) }}</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-4">
              <p class="text-sm text-gray-600">Expected Return</p>
              <p class="text-2xl font-bold text-gray-900">{{ pension.expected_return_percent || 0 }}%</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-4">
              <p class="text-sm text-gray-600">Retirement Age</p>
              <p class="text-2xl font-bold text-gray-900">{{ pension.retirement_age || 'N/A' }}</p>
            </div>
          </div>

          <!-- Key Metrics - DB Pension -->
          <div v-if="pensionType === 'db'" class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-6">
            <div class="bg-purple-50 rounded-lg p-4 border border-purple-200">
              <p class="text-sm text-gray-600">Annual Income</p>
              <p class="text-2xl font-bold text-purple-600">{{ formatCurrency(pension.annual_income) }}</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-4">
              <p class="text-sm text-gray-600">Service Years</p>
              <p class="text-2xl font-bold text-gray-900">{{ pension.service_years || 0 }}</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-4">
              <p class="text-sm text-gray-600">Normal Retirement Age</p>
              <p class="text-2xl font-bold text-gray-900">{{ pension.normal_retirement_age || 'N/A' }}</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-4">
              <p class="text-sm text-gray-600">PCLS Available</p>
              <p class="text-2xl font-bold text-green-600">{{ formatCurrency(pension.pcls_available || 0) }}</p>
            </div>
          </div>

          <!-- Key Metrics - State Pension -->
          <div v-if="pensionType === 'state'" class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
            <div class="bg-green-50 rounded-lg p-4 border border-green-200">
              <p class="text-sm text-gray-600">Weekly Amount</p>
              <p class="text-2xl font-bold text-green-600">£{{ parseFloat(pension.forecast_weekly_amount || 0).toFixed(2) }}</p>
              <p class="text-xs text-gray-500 mt-1">{{ formatCurrency(parseFloat(pension.forecast_weekly_amount || 0) * 52) }}/year</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-4">
              <p class="text-sm text-gray-600">Qualifying Years</p>
              <p class="text-2xl font-bold text-gray-900">{{ pension.qualifying_years || 0 }}/35</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-4">
              <p class="text-sm text-gray-600">State Pension Age</p>
              <p class="text-2xl font-bold text-gray-900">{{ pension.state_pension_age || 67 }}</p>
            </div>
          </div>
        </div>

        <!-- Details Panel -->
        <div class="bg-white rounded-lg shadow-md p-6">
          <h2 class="text-xl font-bold text-gray-900 mb-4">Pension Details</h2>

          <!-- DC Pension Details -->
          <div v-if="pensionType === 'dc'" class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <h3 class="text-sm font-semibold text-gray-700 mb-3">Scheme Information</h3>
              <dl class="space-y-2">
                <div class="flex justify-between">
                  <dt class="text-sm text-gray-600">Scheme Name:</dt>
                  <dd class="text-sm font-medium text-gray-900">{{ pension.scheme_name || 'N/A' }}</dd>
                </div>
                <div class="flex justify-between">
                  <dt class="text-sm text-gray-600">Scheme Type:</dt>
                  <dd class="text-sm font-medium text-gray-900">{{ formatSchemeType(pension.scheme_type) }}</dd>
                </div>
                <div class="flex justify-between">
                  <dt class="text-sm text-gray-600">Provider:</dt>
                  <dd class="text-sm font-medium text-gray-900">{{ pension.provider || 'N/A' }}</dd>
                </div>
                <div class="flex justify-between">
                  <dt class="text-sm text-gray-600">Policy Number:</dt>
                  <dd class="text-sm font-medium text-gray-900">{{ pension.policy_number || 'N/A' }}</dd>
                </div>
              </dl>
            </div>

            <div>
              <h3 class="text-sm font-semibold text-gray-700 mb-3">Contribution Details</h3>
              <dl class="space-y-2">
                <div v-if="pension.scheme_type === 'workplace'" class="flex justify-between">
                  <dt class="text-sm text-gray-600">Employee Contribution:</dt>
                  <dd class="text-sm font-medium text-gray-900">{{ pension.employee_contribution_percent || 0 }}%</dd>
                </div>
                <div v-if="pension.scheme_type === 'workplace'" class="flex justify-between">
                  <dt class="text-sm text-gray-600">Employer Contribution:</dt>
                  <dd class="text-sm font-medium text-gray-900">{{ pension.employer_contribution_percent || 0 }}%</dd>
                </div>
                <div v-if="pension.scheme_type !== 'workplace'" class="flex justify-between">
                  <dt class="text-sm text-gray-600">Monthly Contribution:</dt>
                  <dd class="text-sm font-medium text-gray-900">{{ formatCurrency(pension.monthly_contribution_amount || 0) }}</dd>
                </div>
                <div class="flex justify-between">
                  <dt class="text-sm text-gray-600">Current Salary:</dt>
                  <dd class="text-sm font-medium text-gray-900">{{ formatCurrency(pension.current_salary || 0) }}</dd>
                </div>
              </dl>
            </div>
          </div>

          <!-- DB Pension Details -->
          <div v-if="pensionType === 'db'" class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <h3 class="text-sm font-semibold text-gray-700 mb-3">Scheme Information</h3>
              <dl class="space-y-2">
                <div class="flex justify-between">
                  <dt class="text-sm text-gray-600">Scheme Name:</dt>
                  <dd class="text-sm font-medium text-gray-900">{{ pension.scheme_name || 'N/A' }}</dd>
                </div>
                <div class="flex justify-between">
                  <dt class="text-sm text-gray-600">Employer:</dt>
                  <dd class="text-sm font-medium text-gray-900">{{ pension.employer_name || 'N/A' }}</dd>
                </div>
                <div class="flex justify-between">
                  <dt class="text-sm text-gray-600">Scheme Status:</dt>
                  <dd class="text-sm font-medium text-gray-900">{{ pension.scheme_status || 'Active' }}</dd>
                </div>
                <div class="flex justify-between">
                  <dt class="text-sm text-gray-600">Accrual Rate:</dt>
                  <dd class="text-sm font-medium text-gray-900">{{ pension.accrual_rate ? `1/${pension.accrual_rate}` : 'N/A' }}</dd>
                </div>
              </dl>
            </div>

            <div>
              <h3 class="text-sm font-semibold text-gray-700 mb-3">Benefit Details</h3>
              <dl class="space-y-2">
                <div class="flex justify-between">
                  <dt class="text-sm text-gray-600">Final/Pensionable Salary:</dt>
                  <dd class="text-sm font-medium text-gray-900">{{ formatCurrency(pension.final_salary || 0) }}</dd>
                </div>
                <div class="flex justify-between">
                  <dt class="text-sm text-gray-600">Revaluation Rate:</dt>
                  <dd class="text-sm font-medium text-gray-900">{{ pension.revaluation_rate || 0 }}%</dd>
                </div>
                <div class="flex justify-between">
                  <dt class="text-sm text-gray-600">Lump Sum Entitlement:</dt>
                  <dd class="text-sm font-medium text-gray-900">{{ formatCurrency(pension.lump_sum_entitlement || 0) }}</dd>
                </div>
                <div class="flex justify-between">
                  <dt class="text-sm text-gray-600">Spouse Benefit:</dt>
                  <dd class="text-sm font-medium text-gray-900">{{ pension.spouse_benefit_percent || 0 }}%</dd>
                </div>
              </dl>
            </div>
          </div>

          <!-- State Pension Details -->
          <div v-if="pensionType === 'state'" class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <h3 class="text-sm font-semibold text-gray-700 mb-3">Entitlement</h3>
              <dl class="space-y-2">
                <div class="flex justify-between">
                  <dt class="text-sm text-gray-600">Forecast Weekly Amount:</dt>
                  <dd class="text-sm font-medium text-gray-900">£{{ parseFloat(pension.forecast_weekly_amount || 0).toFixed(2) }}</dd>
                </div>
                <div class="flex justify-between">
                  <dt class="text-sm text-gray-600">Annual Equivalent:</dt>
                  <dd class="text-sm font-medium text-gray-900">{{ formatCurrency(parseFloat(pension.forecast_weekly_amount || 0) * 52) }}</dd>
                </div>
                <div class="flex justify-between">
                  <dt class="text-sm text-gray-600">Qualifying Years:</dt>
                  <dd class="text-sm font-medium text-gray-900">{{ pension.qualifying_years || 0 }} of 35</dd>
                </div>
              </dl>
            </div>

            <div>
              <h3 class="text-sm font-semibold text-gray-700 mb-3">Eligibility</h3>
              <dl class="space-y-2">
                <div class="flex justify-between">
                  <dt class="text-sm text-gray-600">State Pension Age:</dt>
                  <dd class="text-sm font-medium text-gray-900">{{ pension.state_pension_age || 67 }} years</dd>
                </div>
                <div class="flex justify-between">
                  <dt class="text-sm text-gray-600">Years to Retirement:</dt>
                  <dd class="text-sm font-medium text-gray-900">{{ calculateYearsToRetirement() }}</dd>
                </div>
              </dl>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Edit Modal (if needed) -->
    <UnifiedPensionForm
      v-if="showEditModal"
      :initial-type="pensionType"
      :editing-pension="pension"
      @close="showEditModal = false"
      @save="handleSave"
    />
  </AppLayout>
</template>

<script>
import { mapState } from 'vuex';
import AppLayout from '@/layouts/AppLayout.vue';
import UnifiedPensionForm from '@/components/Retirement/UnifiedPensionForm.vue';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'PensionDetail',
  mixins: [currencyMixin],

  components: {
    AppLayout,
    UnifiedPensionForm,
  },

  data() {
    return {
      loading: true,
      error: null,
      pension: null,
      showEditModal: false,
    };
  },

  computed: {
    ...mapState('retirement', ['dcPensions', 'dbPensions', 'statePension']),
    ...mapState('auth', ['user']),

    pensionType() {
      return this.$route.params.type; // 'dc', 'db', or 'state'
    },

    pensionId() {
      return parseInt(this.$route.params.id);
    },

    pensionTypeLabel() {
      const labels = {
        dc: 'Defined Contribution Pension',
        db: 'Defined Benefit Pension',
        state: 'State Pension',
      };
      return labels[this.pensionType] || 'Pension';
    },

    pensionTitle() {
      if (this.pensionType === 'state') {
        return 'State Pension';
      }
      return this.pension?.scheme_name || this.pension?.employer_name || 'Pension';
    },
  },

  methods: {
    async loadPension() {
      this.loading = true;
      this.error = null;

      try {
        // Fetch retirement data if not already loaded
        if (!this.dcPensions.length && !this.dbPensions.length && !this.statePension) {
          await this.$store.dispatch('retirement/fetchRetirementData');
        }

        // Find the pension based on type
        if (this.pensionType === 'dc') {
          this.pension = this.dcPensions.find(p => p.id === this.pensionId);
        } else if (this.pensionType === 'db') {
          this.pension = this.dbPensions.find(p => p.id === this.pensionId);
        } else if (this.pensionType === 'state') {
          this.pension = this.statePension;
        }

        if (!this.pension) {
          this.error = 'Pension not found';
        }
      } catch (err) {
        console.error('Failed to load pension:', err);
        this.error = 'Failed to load pension details. Please try again.';
      } finally {
        this.loading = false;
      }
    },

    editPension() {
      this.showEditModal = true;
    },

    async handleSave() {
      this.showEditModal = false;
      await this.loadPension(); // Reload to get updated data
    },

    async confirmDelete() {
      if (confirm('Are you sure you want to delete this pension? This action cannot be undone.')) {
        try {
          if (this.pensionType === 'dc') {
            await this.$store.dispatch('retirement/deleteDCPension', this.pensionId);
          } else if (this.pensionType === 'db') {
            await this.$store.dispatch('retirement/deleteDBPension', this.pensionId);
          }
          // Navigate back to retirement dashboard
          this.$router.push('/net-worth/retirement');
        } catch (error) {
          console.error('Failed to delete pension:', error);
          alert('Failed to delete pension. Please try again.');
        }
      }
    },

    formatSchemeType(type) {
      const types = {
        workplace: 'Workplace Pension',
        sipp: 'SIPP',
        personal: 'Personal Pension',
        stakeholder: 'Stakeholder Pension',
      };
      return types[type] || type;
    },

    calculateYearsToRetirement() {
      if (!this.user?.date_of_birth || !this.pension?.state_pension_age) {
        return 'N/A';
      }
      const dob = new Date(this.user.date_of_birth);
      const today = new Date();
      const currentAge = Math.floor((today - dob) / (365.25 * 24 * 60 * 60 * 1000));
      const yearsToRetirement = this.pension.state_pension_age - currentAge;
      return yearsToRetirement > 0 ? `${yearsToRetirement} years` : 'Reached';
    },
  },

  mounted() {
    this.loadPension();
  },
};
</script>

<style scoped>
/* Animations for smooth transitions */
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

.space-y-6 > * {
  animation: fadeIn 0.3s ease-out;
}
</style>
