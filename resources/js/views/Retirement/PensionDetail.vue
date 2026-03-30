<template>
  <AppLayout>
    <div class="container mx-auto px-4 py-8">
      <!-- Loading State -->
      <div v-if="loading" class="text-center py-12">
        <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-raspberry-500"></div>
        <p class="mt-4 text-neutral-500">Loading pension details...</p>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="bg-raspberry-50 border border-raspberry-200 rounded-lg p-6 text-center">
        <p class="text-raspberry-600">{{ error }}</p>
        <button
          @click="loadPension"
          class="mt-4 px-4 py-2 bg-raspberry-600 text-white rounded-button hover:bg-raspberry-700 transition-colors"
        >
          Retry
        </button>
      </div>

      <!-- Pension Content -->
      <div v-else-if="pension" class="space-y-6">
        <!-- Back Button -->
        <button
          @click="goBack"
          class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-neutral-500 bg-white border border-horizon-300 rounded-lg hover:bg-savannah-100 transition-colors"
        >
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
          </svg>
          Back to Retirement
        </button>

        <!-- Header -->
        <div class="bg-white rounded-lg shadow-md p-6">
          <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
            <div>
              <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold text-horizon-500">{{ pensionTitle }}</h1>
              <p class="text-base sm:text-lg text-neutral-500 mt-1">{{ pensionTypeLabel }}</p>
            </div>
            <div class="flex flex-col sm:flex-row gap-2 sm:space-x-2 w-full sm:w-auto">
              <button
                @click="editPension"
                class="w-full sm:w-auto px-4 py-2 bg-raspberry-500 text-white rounded-button hover:bg-raspberry-600 transition-colors"
              >
                Edit
              </button>
              <button
                v-if="pensionType !== 'state'"
                @click="confirmDelete"
                class="w-full sm:w-auto px-4 py-2 bg-raspberry-600 text-white rounded-button hover:bg-raspberry-700 transition-colors"
              >
                Delete
              </button>
            </div>
          </div>

          <!-- Key Metrics - DC Pension -->
          <div v-if="pensionType === 'dc'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mt-6">
            <div class="bg-violet-50 rounded-lg p-4 border border-violet-200">
              <p class="text-sm text-neutral-500">Current Fund Value</p>
              <p class="text-2xl font-bold text-violet-600">{{ formatCurrency(pension.current_fund_value) }}</p>
            </div>
            <div class="bg-savannah-100 rounded-lg p-4">
              <p class="text-sm text-neutral-500">Projected Value</p>
              <p class="text-2xl font-bold text-spring-600">{{ formatCurrency(pension.projected_fund_value || 0) }}</p>
            </div>
            <div class="bg-savannah-100 rounded-lg p-4">
              <p class="text-sm text-neutral-500">Expected Return</p>
              <p v-if="hasHoldings" class="text-2xl font-bold text-horizon-500">{{ pension.expected_return_percent || 0 }}%</p>
              <p v-else class="text-lg font-semibold text-violet-600 cursor-pointer hover:underline" @click="addHoldings">Enter Holdings</p>
            </div>
            <div class="bg-savannah-100 rounded-lg p-4">
              <p class="text-sm text-neutral-500">Retirement Age</p>
              <p class="text-2xl font-bold text-horizon-500">{{ pension.retirement_age || 'N/A' }}</p>
            </div>
          </div>

          <!-- Key Metrics - DB Pension -->
          <div v-if="pensionType === 'db'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mt-6">
            <div class="bg-purple-50 rounded-lg p-4 border border-purple-200">
              <p class="text-sm text-neutral-500">Annual Income</p>
              <p class="text-2xl font-bold text-purple-600">{{ formatCurrency(pension.annual_income) }}</p>
            </div>
            <div class="bg-savannah-100 rounded-lg p-4">
              <p class="text-sm text-neutral-500">Service Years</p>
              <p class="text-2xl font-bold text-horizon-500">{{ pension.service_years || 0 }}</p>
            </div>
            <div class="bg-savannah-100 rounded-lg p-4">
              <p class="text-sm text-neutral-500">Normal Retirement Age</p>
              <p class="text-2xl font-bold text-horizon-500">{{ pension.normal_retirement_age || 'N/A' }}</p>
            </div>
            <div class="bg-savannah-100 rounded-lg p-4">
              <p class="text-sm text-neutral-500">PCLS Available</p>
              <p class="text-2xl font-bold text-spring-600">{{ formatCurrency(pension.pcls_available || 0) }}</p>
            </div>
          </div>

          <!-- Key Metrics - State Pension -->
          <div v-if="pensionType === 'state'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4 mt-6">
            <div class="bg-spring-50 rounded-lg p-4 border border-spring-200">
              <p class="text-sm text-neutral-500">Weekly Amount</p>
              <p class="text-2xl font-bold text-spring-600">£{{ parseFloat(pension.forecast_weekly_amount || 0).toFixed(2) }}</p>
              <p class="text-xs text-neutral-500 mt-1">{{ formatCurrency(parseFloat(pension.forecast_weekly_amount || 0) * 52) }}/year</p>
            </div>
            <div class="bg-savannah-100 rounded-lg p-4">
              <p class="text-sm text-neutral-500">Qualifying Years</p>
              <p class="text-2xl font-bold text-horizon-500">{{ pension.qualifying_years || 0 }}/35</p>
            </div>
            <div class="bg-savannah-100 rounded-lg p-4">
              <p class="text-sm text-neutral-500">State Pension Age</p>
              <p class="text-2xl font-bold text-horizon-500">{{ pension.state_pension_age || 67 }}</p>
            </div>
          </div>
        </div>

        <!-- Details Panel -->
        <div class="bg-white rounded-lg shadow-md p-6">
          <h2 class="text-xl font-bold text-horizon-500 mb-4">Pension Details</h2>

          <!-- DC Pension Details -->
          <div v-if="pensionType === 'dc'" class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
            <div>
              <h3 class="text-sm font-semibold text-neutral-500 mb-3">Scheme Information</h3>
              <dl class="space-y-2">
                <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                  <dt class="text-sm text-neutral-500">Scheme Name:</dt>
                  <dd class="text-sm font-medium text-horizon-500">{{ pension.scheme_name || 'N/A' }}</dd>
                </div>
                <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                  <dt class="text-sm text-neutral-500">Scheme Type:</dt>
                  <dd class="text-sm font-medium text-horizon-500">{{ formatSchemeType(pension.scheme_type) }}</dd>
                </div>
                <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                  <dt class="text-sm text-neutral-500">Provider:</dt>
                  <dd class="text-sm font-medium text-horizon-500">{{ pension.provider || 'N/A' }}</dd>
                </div>
                <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                  <dt class="text-sm text-neutral-500">Policy Number:</dt>
                  <dd class="text-sm font-medium text-horizon-500">{{ pension.policy_number || 'N/A' }}</dd>
                </div>
              </dl>
            </div>

            <div>
              <h3 class="text-sm font-semibold text-neutral-500 mb-3">Contribution Details</h3>
              <dl class="space-y-2">
                <div v-if="pension.scheme_type === 'workplace'" class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                  <dt class="text-sm text-neutral-500">Employee Contribution:</dt>
                  <dd class="text-sm font-medium text-horizon-500">{{ pension.employee_contribution_percent || 0 }}%</dd>
                </div>
                <div v-if="pension.scheme_type === 'workplace'" class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                  <dt class="text-sm text-neutral-500">Employer Contribution:</dt>
                  <dd class="text-sm font-medium text-horizon-500">{{ pension.employer_contribution_percent || 0 }}%</dd>
                </div>
                <div v-if="pension.scheme_type === 'workplace' && pension.annual_salary > 0" class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                  <dt class="text-sm text-neutral-500">Employee Monthly:</dt>
                  <dd class="text-sm font-medium text-horizon-500">{{ formatCurrency((pension.employee_contribution_percent * pension.annual_salary) / 100 / 12) }}</dd>
                </div>
                <div v-if="pension.scheme_type === 'workplace' && pension.annual_salary > 0" class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                  <dt class="text-sm text-neutral-500">Employer Monthly:</dt>
                  <dd class="text-sm font-medium text-horizon-500">{{ formatCurrency((pension.employer_contribution_percent * pension.annual_salary) / 100 / 12) }}</dd>
                </div>
                <div v-if="pension.scheme_type === 'workplace' && pension.annual_salary > 0" class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                  <dt class="text-sm text-neutral-500">Total Monthly:</dt>
                  <dd class="text-sm font-medium text-horizon-500">{{ formatCurrency(((pension.employee_contribution_percent + pension.employer_contribution_percent) * pension.annual_salary) / 100 / 12) }}</dd>
                </div>
                <div v-if="pension.scheme_type !== 'workplace'" class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                  <dt class="text-sm text-neutral-500">Monthly Contribution:</dt>
                  <dd class="text-sm font-medium text-horizon-500">{{ formatCurrency(pension.monthly_contribution_amount || 0) }}</dd>
                </div>
                <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                  <dt class="text-sm text-neutral-500">Current Salary:</dt>
                  <dd class="text-sm font-medium text-horizon-500">{{ formatCurrency(pension.annual_salary || 0) }}</dd>
                </div>
              </dl>
            </div>
          </div>

          <!-- DB Pension Details -->
          <div v-if="pensionType === 'db'" class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
            <div>
              <h3 class="text-sm font-semibold text-neutral-500 mb-3">Scheme Information</h3>
              <dl class="space-y-2">
                <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                  <dt class="text-sm text-neutral-500">Scheme Name:</dt>
                  <dd class="text-sm font-medium text-horizon-500">{{ pension.scheme_name || 'N/A' }}</dd>
                </div>
                <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                  <dt class="text-sm text-neutral-500">Employer:</dt>
                  <dd class="text-sm font-medium text-horizon-500">{{ pension.employer_name || 'N/A' }}</dd>
                </div>
                <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                  <dt class="text-sm text-neutral-500">Scheme Status:</dt>
                  <dd class="text-sm font-medium text-horizon-500">{{ pension.scheme_status || 'Active' }}</dd>
                </div>
                <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                  <dt class="text-sm text-neutral-500">Accrual Rate:</dt>
                  <dd class="text-sm font-medium text-horizon-500">{{ pension.accrual_rate ? `1/${pension.accrual_rate}` : 'N/A' }}</dd>
                </div>
              </dl>
            </div>

            <div>
              <h3 class="text-sm font-semibold text-neutral-500 mb-3">Benefit Details</h3>
              <dl class="space-y-2">
                <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                  <dt class="text-sm text-neutral-500">Final/Pensionable Salary:</dt>
                  <dd class="text-sm font-medium text-horizon-500">{{ formatCurrency(pension.final_salary || 0) }}</dd>
                </div>
                <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                  <dt class="text-sm text-neutral-500">Revaluation Rate:</dt>
                  <dd class="text-sm font-medium text-horizon-500">{{ pension.revaluation_rate || 0 }}%</dd>
                </div>
                <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                  <dt class="text-sm text-neutral-500">Lump Sum Entitlement:</dt>
                  <dd class="text-sm font-medium text-horizon-500">{{ formatCurrency(pension.lump_sum_entitlement || 0) }}</dd>
                </div>
                <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                  <dt class="text-sm text-neutral-500">Spouse Benefit:</dt>
                  <dd class="text-sm font-medium text-horizon-500">{{ pension.spouse_benefit_percent || 0 }}%</dd>
                </div>
              </dl>
            </div>
          </div>

          <!-- State Pension Details -->
          <div v-if="pensionType === 'state'" class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
            <div>
              <h3 class="text-sm font-semibold text-neutral-500 mb-3">Entitlement</h3>
              <dl class="space-y-2">
                <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                  <dt class="text-sm text-neutral-500">Forecast Weekly Amount:</dt>
                  <dd class="text-sm font-medium text-horizon-500">£{{ parseFloat(pension.forecast_weekly_amount || 0).toFixed(2) }}</dd>
                </div>
                <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                  <dt class="text-sm text-neutral-500">Annual Equivalent:</dt>
                  <dd class="text-sm font-medium text-horizon-500">{{ formatCurrency(parseFloat(pension.forecast_weekly_amount || 0) * 52) }}</dd>
                </div>
                <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                  <dt class="text-sm text-neutral-500">Qualifying Years:</dt>
                  <dd class="text-sm font-medium text-horizon-500">{{ pension.qualifying_years || 0 }} of 35</dd>
                </div>
              </dl>
            </div>

            <div>
              <h3 class="text-sm font-semibold text-neutral-500 mb-3">Eligibility</h3>
              <dl class="space-y-2">
                <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                  <dt class="text-sm text-neutral-500">State Pension Age:</dt>
                  <dd class="text-sm font-medium text-horizon-500">{{ pension.state_pension_age || 67 }} years</dd>
                </div>
                <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                  <dt class="text-sm text-neutral-500">Years to Retirement:</dt>
                  <dd class="text-sm font-medium text-horizon-500">{{ calculateYearsToRetirement() }}</dd>
                </div>
              </dl>
            </div>
          </div>
        </div>

        <!-- Projections Panel (DC pensions only) -->
        <div v-if="pensionType === 'dc'" class="bg-white rounded-lg shadow-md p-6">
          <h2 class="text-xl font-bold text-horizon-500 mb-4">Pension Pot Projections</h2>

          <div v-if="projectionLoading" class="text-center py-12">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-raspberry-500"></div>
            <p class="mt-4 text-neutral-500">Loading projections...</p>
          </div>

          <div v-else-if="projectionData">
            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
              <div class="bg-violet-50 rounded-lg p-4 border border-violet-200">
                <p class="text-sm text-neutral-500">Current Value</p>
                <p class="text-xl font-bold text-violet-600">{{ formatCurrency(projectionData.current_value) }}</p>
              </div>
              <div class="bg-spring-50 rounded-lg p-4 border border-spring-200">
                <p class="text-sm text-neutral-500">80% Probability at Retirement</p>
                <p class="text-xl font-bold text-spring-600">{{ formatCurrency(projectionData.percentile_20_at_retirement) }}</p>
              </div>
            </div>

            <!-- Monte Carlo Chart -->
            <div class="bg-white rounded-lg border border-light-gray p-4">
              <h3 class="text-lg font-semibold text-horizon-500 mb-4">Projected Pension Pot Growth</h3>
              <PensionPotProjectionChart :data="projectionData" />
            </div>

            <!-- Assumptions -->
            <div class="mt-4 text-sm text-neutral-500">
              <p>Based on {{ projectionData.years_to_retirement }} years to retirement age {{ projectionData.retirement_age }},
              {{ projectionData.risk_level }} risk profile ({{ projectionData.expected_return }}% expected return),
              and {{ formatCurrency(projectionData.monthly_contribution) }}/month contributions.</p>
            </div>
          </div>

          <div v-else class="text-center py-12 text-neutral-500">
            <p>Unable to load projection data</p>
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
import PensionPotProjectionChart from '@/components/Retirement/PensionPotProjectionChart.vue';
import { currencyMixin } from '@/mixins/currencyMixin';
import retirementService from '@/services/retirementService';

import logger from '@/utils/logger';
export default {
  name: 'PensionDetail',
  mixins: [currencyMixin],

  components: {
    AppLayout,
    UnifiedPensionForm,
    PensionPotProjectionChart,
  },

  data() {
    return {
      loading: true,
      error: null,
      pension: null,
      showEditModal: false,
      projectionData: null,
      projectionLoading: false,
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

    hasHoldings() {
      return this.pension?.holdings?.length > 0;
    },
  },

  methods: {
    goBack() {
      this.$router.push('/net-worth/retirement');
    },

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
        logger.error('Failed to load pension:', err);
        this.error = 'Failed to load pension details. Please try again.';
      } finally {
        this.loading = false;
      }
    },

    editPension() {
      this.showEditModal = true;
    },

    addHoldings() {
      // Open edit modal to add holdings
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
          logger.error('Failed to delete pension:', error);
          alert('Failed to delete pension. Please try again.');
        }
      }
    },

    formatSchemeType(type) {
      const types = {
        workplace: 'Workplace Pension',
        sipp: 'Self-Invested Personal Pension',
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

    async loadProjections() {
      if (this.projectionLoading || this.projectionData || this.pensionType !== 'dc') return;

      this.projectionLoading = true;
      try {
        const response = await retirementService.getDCPensionProjection(this.pension.id);
        if (response.success) {
          this.projectionData = response.data;
        }
      } catch (error) {
        logger.error('Failed to load projections:', error);
      } finally {
        this.projectionLoading = false;
      }
    },
  },

  mounted() {
    this.loadPension();
  },

  watch: {
    pension(newVal) {
      // Load projections when pension data is available and it's a DC pension
      if (newVal && this.pensionType === 'dc') {
        this.loadProjections();
      }
    },
  },
};
</script>

<style scoped>
.space-y-6 > * {
  animation: fadeInSlideUp 0.3s ease-out;
}
</style>
