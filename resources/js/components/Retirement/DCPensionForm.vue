<template>
  <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" @click.self="$emit('close')">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
      <!-- Header -->
      <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
        <h3 class="text-xl font-semibold text-gray-900">
          {{ isEdit ? 'Edit' : 'Add' }} Money Purchase Pension
        </h3>
        <button @click="$emit('close')" class="text-gray-400 hover:text-gray-600 transition-colors">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>

      <!-- Form -->
      <form @submit.prevent="handleSubmit" class="p-6">
        <div class="space-y-6">
          <!-- Pension Type -->
          <div>
            <label for="pension_type" class="block text-sm font-medium text-gray-700 mb-2">
              Pension Type
            </label>
            <select
              id="pension_type"
              v-model="formData.pension_type"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
              @change="handlePensionTypeChange"
            >
              <option value="">Select pension type...</option>
              <option value="occupational">Occupational (Workplace)</option>
              <option value="sipp">Self-Invested Personal Pension (SIPP)</option>
              <option value="personal">Personal Pension</option>
              <option value="stakeholder">Stakeholder Pension</option>
            </select>
            <p class="text-xs text-gray-500 mt-1">
              Workplace pensions use % of salary contributions. Personal pensions use fixed monthly amounts
            </p>
          </div>

          <!-- Scheme Name -->
          <div>
            <label for="scheme_name" class="block text-sm font-medium text-gray-700 mb-2">
              Scheme Name
            </label>
            <input
              id="scheme_name"
              v-model="formData.scheme_name"
              type="text"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
              placeholder="e.g., Aviva Master Trust"
            />
          </div>

          <!-- Provider and Policy Number -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label for="provider" class="block text-sm font-medium text-gray-700 mb-2">
                Provider
              </label>
              <input
                id="provider"
                v-model="formData.provider"
                type="text"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                placeholder="e.g., Aviva"
              />
            </div>
            <div>
              <label for="policy_number" class="block text-sm font-medium text-gray-700 mb-2">
                Policy Number
              </label>
              <input
                id="policy_number"
                v-model="formData.policy_number"
                type="text"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                placeholder="e.g., DC123456"
              />
            </div>
          </div>

          <!-- Current Fund Value -->
          <div>
            <label for="current_fund_value" class="block text-sm font-medium text-gray-700 mb-2">
              Current Fund Value (£)
            </label>
            <input
              id="current_fund_value"
              v-model.number="formData.current_fund_value"
              type="number"
              step="0.01"
              min="0"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
              placeholder="e.g., 50000.00"
            />
          </div>

          <!-- Workplace Pension: Annual Salary -->
          <div v-if="isWorkplacePension">
            <label for="annual_salary" class="block text-sm font-medium text-gray-700 mb-2">
              Annual Salary (£)
            </label>
            <input
              id="annual_salary"
              v-model.number="formData.annual_salary"
              type="number"
              step="0.01"
              min="0"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
              placeholder="e.g., 50000.00"
            />
            <p class="text-xs text-gray-500 mt-1">Required to calculate percentage contributions</p>
          </div>

          <!-- Workplace Pension: Percentage Contributions -->
          <div v-if="isWorkplacePension" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label for="employee_contribution_percent" class="block text-sm font-medium text-gray-700 mb-2">
                Employee Contribution (%)
              </label>
              <input
                id="employee_contribution_percent"
                v-model.number="formData.employee_contribution_percent"
                type="number"
                step="0.01"
                min="0"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                :class="{ 'border-red-500': validationErrors.employee_contribution_percent }"
                placeholder="e.g., 5.00"
                @blur="validateEmployeeContribution"
              />
              <p v-if="validationErrors.employee_contribution_percent" class="text-xs text-red-500 mt-1">
                {{ validationErrors.employee_contribution_percent }}
              </p>
              <p v-else-if="calculatedEmployeeContribution" class="text-xs text-gray-500 mt-1">
                = {{ formatCurrency(calculatedEmployeeContribution) }}/month
              </p>
              <p v-else class="text-xs text-gray-500 mt-1">Enter as percentage of salary (0-100)</p>
            </div>
            <div>
              <label for="employer_contribution_percent" class="block text-sm font-medium text-gray-700 mb-2">
                Employer Contribution (%)
              </label>
              <input
                id="employer_contribution_percent"
                v-model.number="formData.employer_contribution_percent"
                type="number"
                step="0.01"
                min="0"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                :class="{ 'border-red-500': validationErrors.employer_contribution_percent }"
                placeholder="e.g., 3.00"
                @blur="validateEmployerContribution"
              />
              <p v-if="validationErrors.employer_contribution_percent" class="text-xs text-red-500 mt-1">
                {{ validationErrors.employer_contribution_percent }}
              </p>
              <p v-else-if="calculatedEmployerContribution" class="text-xs text-gray-500 mt-1">
                = {{ formatCurrency(calculatedEmployerContribution) }}/month
              </p>
              <p v-else class="text-xs text-gray-500 mt-1">Enter as percentage of salary (0-100)</p>
            </div>
          </div>

          <!-- Personal/SIPP: Fixed Monthly Contribution -->
          <div v-if="isPersonalPension">
            <label for="monthly_contribution_amount" class="block text-sm font-medium text-gray-700 mb-2">
              Monthly Contribution (£)
            </label>
            <input
              id="monthly_contribution_amount"
              v-model.number="formData.monthly_contribution_amount"
              type="number"
              step="0.01"
              min="0"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
              placeholder="e.g., 500.00"
            />
            <p class="text-xs text-gray-500 mt-1">Fixed monthly contribution amount</p>
          </div>

          <!-- Personal/SIPP: Lump Sum Contribution (Carry Forward) -->
          <div v-if="isPersonalPension">
            <label for="lump_sum_contribution" class="block text-sm font-medium text-gray-700 mb-2">
              Lump Sum Contribution (£) <span class="text-gray-500 text-xs">(Optional)</span>
            </label>
            <input
              id="lump_sum_contribution"
              v-model.number="formData.lump_sum_contribution"
              type="number"
              step="0.01"
              min="0"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
              placeholder="e.g., 10000.00"
            />
            <p class="text-xs text-gray-500 mt-1">
              One-off lump sum payment to take advantage of carry forward allowances
            </p>
          </div>

          <!-- Expected Return and Retirement Age -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label for="expected_return_percent" class="block text-sm font-medium text-gray-700 mb-2">
                Expected Return (% p.a.)
              </label>
              <input
                id="expected_return_percent"
                v-model.number="formData.expected_return_percent"
                type="number"
                step="0.01"
                min="0"
                max="20"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                placeholder="e.g., 5.00"
              />
              <p class="text-xs text-gray-500 mt-1">Typical: 4-6% for balanced funds</p>
            </div>
            <div>
              <label for="retirement_age" class="block text-sm font-medium text-gray-700 mb-2">
                Planned Retirement Age
              </label>
              <input
                id="retirement_age"
                v-model.number="formData.retirement_age"
                type="number"
                min="55"
                max="75"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                :class="{ 'border-red-500': validationErrors.retirement_age }"
                placeholder="e.g., 67"
                @blur="validateRetirementAge"
              />
              <p v-if="validationErrors.retirement_age" class="text-xs text-red-500 mt-1">
                {{ validationErrors.retirement_age }}
              </p>
              <p v-else class="text-xs text-gray-500 mt-1">Pensions can usually be accessed from age 55</p>
            </div>
          </div>

          <!-- Risk Level Section (hidden during onboarding) -->
          <template v-if="!isOnboarding">
            <div v-if="hasRiskProfile" class="pt-4 border-t border-gray-200">
              <RiskLevelSelector
                v-model="formData.risk_preference"
                :allowed-levels="allowedRiskLevels"
                :profile-level="mainRiskLevel"
                :compact="true"
                :show-allocation="false"
                :show-returns="false"
                :collapsible="true"
                label="Risk Level for This Pension"
              />
              <p class="mt-2 text-xs text-gray-500">
                Your main risk profile is <strong>{{ mainRiskLevelDisplay }}</strong>.
                You can adjust this pension within one level of your main preference.
              </p>
            </div>
            <div v-else class="pt-4 border-t border-gray-200">
              <div class="bg-gray-50 rounded-md p-3">
                <div class="flex items-start gap-2">
                  <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  <div>
                    <p class="text-sm text-blue-800">
                      <router-link to="/risk-profile" class="font-medium underline hover:text-blue-900">
                        Set your risk profile
                      </router-link>
                      to get personalised risk guidance for your pension investments.
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </template>

          <!-- Salary Sacrifice (Workplace Pensions Only) -->
          <div v-if="isWorkplacePension" class="flex items-center">
            <input
              id="salary_sacrifice"
              v-model="formData.salary_sacrifice"
              type="checkbox"
              class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
            />
            <label for="salary_sacrifice" class="ml-2 block text-sm text-gray-700">
              Using salary sacrifice arrangement
            </label>
          </div>

          <!-- Notes -->
          <div>
            <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">
              Notes
            </label>
            <textarea
              id="notes"
              v-model="formData.notes"
              rows="3"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
              placeholder="Any additional notes about this pension..."
            ></textarea>
          </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-end space-x-3 mt-8 pt-6 border-t border-gray-200">
          <button
            type="button"
            @click="$emit('close')"
            class="px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors duration-200"
          >
            Cancel
          </button>
          <button
            type="submit"
            class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors duration-200"
          >
            {{ isEdit ? 'Update' : 'Add' }} Pension
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
import { mapGetters } from 'vuex';
import RiskLevelSelector from '@/components/Shared/RiskLevelSelector.vue';
import riskService from '@/services/riskService';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'DCPensionForm',

  mixins: [currencyMixin],

  components: {
    RiskLevelSelector,
  },

  props: {
    pension: {
      type: Object,
      default: null,
    },
    isOnboarding: {
      type: Boolean,
      default: false,
    },
  },

  data() {
    return {
      formData: {
        pension_type: '',
        scheme_type: '', // Keep for backward compatibility
        scheme_name: '',
        provider: '',
        policy_number: '',
        current_fund_value: null,
        annual_salary: null,
        employee_contribution_percent: null,
        employer_contribution_percent: null,
        monthly_contribution_amount: null,
        lump_sum_contribution: null,
        expected_return_percent: 5.0,
        retirement_age: null, // Will be populated from user profile
        salary_sacrifice: false,
        notes: '',
        risk_preference: null,
      },
      validationErrors: {
        employee_contribution_percent: '',
        employer_contribution_percent: '',
        retirement_age: '',
      },
      // Risk profile state
      mainRiskLevel: null,
      allowedRiskLevels: ['low', 'lower_medium', 'medium', 'upper_medium', 'high'],
    };
  },

  computed: {
    ...mapGetters('auth', ['currentUser']),

    isEdit() {
      return !!this.pension;
    },

    hasRiskProfile() {
      return !!this.mainRiskLevel;
    },

    mainRiskLevelDisplay() {
      return riskService.getDisplayName(this.mainRiskLevel);
    },

    isWorkplacePension() {
      return this.formData.pension_type === 'occupational';
    },

    isPersonalPension() {
      return this.formData.pension_type === 'sipp' || this.formData.pension_type === 'personal' || this.formData.pension_type === 'stakeholder';
    },

    calculatedEmployeeContribution() {
      if (!this.isWorkplacePension || !this.formData.annual_salary || !this.formData.employee_contribution_percent) {
        return null;
      }
      return Math.round((this.formData.annual_salary * this.formData.employee_contribution_percent / 100) / 12);
    },

    calculatedEmployerContribution() {
      if (!this.isWorkplacePension || !this.formData.annual_salary || !this.formData.employer_contribution_percent) {
        return null;
      }
      return Math.round((this.formData.annual_salary * this.formData.employer_contribution_percent / 100) / 12);
    },
  },

  watch: {
    pension: {
      immediate: true,
      handler(newPension) {
        if (newPension) {
          // Editing existing pension - populate form with pension data
          this.formData = {
            ...newPension,
            risk_preference: newPension.risk_preference || null,
          };
        } else {
          // Adding new pension - populate retirement age from user profile
          if (this.currentUser && this.currentUser.target_retirement_age) {
            this.formData.retirement_age = this.currentUser.target_retirement_age;
          }
        }
      },
    },
  },

  async mounted() {
    // Load risk profile when component mounts (auto-calculated if none exists)
    await this.loadRiskProfile();
  },

  methods: {
    async loadRiskProfile() {
      try {
        const [profileResponse, allowedResponse] = await Promise.all([
          riskService.getProfile(),
          riskService.getAllowedLevels(),
        ]);

        if (profileResponse.data?.risk_level) {
          this.mainRiskLevel = profileResponse.data.risk_level;
        }

        if (allowedResponse.data?.allowed_levels) {
          this.allowedRiskLevels = allowedResponse.data.allowed_levels;
        }
      } catch (error) {
        // Silently fail - risk profile is optional
      }
    },

    handlePensionTypeChange() {
      // Clear fields that don't apply to the selected pension type
      if (this.isWorkplacePension) {
        this.formData.monthly_contribution_amount = null;
      } else {
        this.formData.annual_salary = null;
        this.formData.employee_contribution_percent = null;
        this.formData.employer_contribution_percent = null;
      }

      // Set scheme_type for backward compatibility
      if (this.formData.pension_type === 'occupational') {
        this.formData.scheme_type = 'workplace';
      } else if (this.formData.pension_type === 'stakeholder') {
        // Stakeholder pensions map to 'personal' scheme_type
        // (stakeholder is a regulated type of personal pension in UK)
        this.formData.scheme_type = 'personal';
      } else {
        // sipp, personal map directly
        this.formData.scheme_type = this.formData.pension_type;
      }
    },

    validateEmployeeContribution() {
      this.validationErrors.employee_contribution_percent = '';
      const value = this.formData.employee_contribution_percent;

      if (value !== null && value !== '') {
        if (value < 0) {
          this.validationErrors.employee_contribution_percent = 'Cannot be negative';
        } else if (value > 100) {
          this.validationErrors.employee_contribution_percent = 'Cannot exceed 100%';
        }
      }
    },

    validateEmployerContribution() {
      this.validationErrors.employer_contribution_percent = '';
      const value = this.formData.employer_contribution_percent;

      if (value !== null && value !== '') {
        if (value < 0) {
          this.validationErrors.employer_contribution_percent = 'Cannot be negative';
        } else if (value > 100) {
          this.validationErrors.employer_contribution_percent = 'Cannot exceed 100%';
        }
      }
    },

    validateRetirementAge() {
      this.validationErrors.retirement_age = '';
      const age = this.formData.retirement_age;

      if (age !== null && age !== '' && age < 55) {
        this.validationErrors.retirement_age = 'Pensions can only be accessed from age 55, so this is the youngest age you can enter';
      }
    },

    handleSubmit() {
      // Validate all fields before submitting
      this.validateRetirementAge();

      if (this.isWorkplacePension) {
        this.validateEmployeeContribution();
        this.validateEmployerContribution();

        // Check if there are any validation errors
        if (this.validationErrors.employee_contribution_percent || this.validationErrors.employer_contribution_percent) {
          return;
        }
      }

      // Check retirement age validation
      if (this.validationErrors.retirement_age) {
        return;
      }

      // Basic validation
      if (!this.formData.scheme_type) {
        alert('Please select a pension type');
        return;
      }

      if (!this.formData.scheme_name) {
        alert('Please enter a scheme name');
        return;
      }

      if (!this.formData.current_fund_value || this.formData.current_fund_value < 0) {
        alert('Please enter a valid current fund value');
        return;
      }

      if (this.isWorkplacePension && (!this.formData.annual_salary || this.formData.annual_salary <= 0)) {
        alert('Please enter your annual salary for workplace pension');
        return;
      }

      this.$emit('save', this.formData);
    },
  },
};
</script>

<style scoped>
/* Modal animation */
@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

.fixed {
  animation: fadeIn 0.3s ease-out;
}

/* Scrollbar styling */
.overflow-y-auto::-webkit-scrollbar {
  width: 6px;
}

.overflow-y-auto::-webkit-scrollbar-track {
  background: #f1f1f1;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
  background: #888;
  border-radius: 3px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
  background: #555;
}
</style>
