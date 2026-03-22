<template>
  <!-- Onboarding: inline form, no modal. Regular: full modal wrapper. -->
  <div :class="context === 'onboarding' ? '' : 'fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4 animate-fade-in'">
    <div :class="context === 'onboarding' ? '' : 'bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto scrollbar-thin'">
      <!-- Header -->
      <div :class="context === 'onboarding' ? 'mb-4' : 'sticky top-0 bg-white border-b border-light-gray px-6 py-4 flex items-center justify-between'">
        <h3 class="text-xl font-semibold text-horizon-500">
          {{ isEdit ? 'Edit' : 'Add' }} Money Purchase Pension
        </h3>
        <button
          v-if="context !== 'onboarding'"
          @click="$emit('close')"
          class="text-horizon-400 hover:text-neutral-500 transition-colors"
        >
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>

      <!-- Form -->
      <form @submit.prevent="handleSubmit" :class="context === 'onboarding' ? '' : 'p-6'">
        <div class="space-y-6">
          <!-- Pension Type -->
          <div :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'pension_type' }">
            <label for="pension_type" class="block text-sm font-medium text-neutral-500 mb-2">
              Pension Type
            </label>
            <select
              id="pension_type"
              v-model="formData.pension_type"
              class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
              @change="handlePensionTypeChange"
            >
              <option value="">Select pension type...</option>
              <option value="occupational">Occupational (Workplace)</option>
              <option value="sipp">Self-Invested Personal Pension</option>
              <option value="personal">Personal Pension</option>
              <option value="stakeholder">Stakeholder Pension</option>
            </select>
            <p class="text-xs text-neutral-500 mt-1">
              Workplace pensions use % of salary contributions. Personal pensions use fixed monthly amounts
            </p>
          </div>

          <!-- Scheme Name -->
          <div :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'scheme_name' }">
            <label for="scheme_name" class="block text-sm font-medium text-neutral-500 mb-2">
              Scheme Name
            </label>
            <input
              id="scheme_name"
              v-model="formData.scheme_name"
              type="text"
              class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
              placeholder="e.g., Aviva Master Trust"
            />
          </div>

          <!-- Provider and Policy Number -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'provider' }">
              <label for="provider" class="block text-sm font-medium text-neutral-500 mb-2">
                Provider
              </label>
              <input
                id="provider"
                v-model="formData.provider"
                type="text"
                class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                placeholder="e.g., Aviva"
              />
            </div>
            <div>
              <label for="policy_number" class="block text-sm font-medium text-neutral-500 mb-2">
                Policy Number
              </label>
              <input
                id="policy_number"
                v-model="formData.policy_number"
                type="text"
                class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                placeholder="e.g., DC123456"
              />
            </div>
          </div>

          <!-- Current Fund Value -->
          <div :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'current_fund_value' }">
            <label for="current_fund_value" class="block text-sm font-medium text-neutral-500 mb-2">
              Current Fund Value (£)
            </label>
            <input
              id="current_fund_value"
              v-model.number="formData.current_fund_value"
              type="number"
              step="0.01"
              min="0"
              class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
              placeholder="e.g., 50000.00"
            />
          </div>

          <!-- Workplace Pension: Annual Salary -->
          <div v-if="isWorkplacePension">
            <label for="annual_salary" class="block text-sm font-medium text-neutral-500 mb-2">
              Annual Salary (£)
            </label>
            <input
              id="annual_salary"
              v-model.number="formData.annual_salary"
              type="number"
              step="0.01"
              min="0"
              class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
              placeholder="e.g., 50000.00"
            />
            <p class="text-xs text-neutral-500 mt-1">Required to calculate percentage contributions</p>
          </div>

          <!-- Workplace Pension: Percentage Contributions -->
          <div v-if="isWorkplacePension" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'employee_contribution_percent' }">
              <label for="employee_contribution_percent" class="block text-sm font-medium text-neutral-500 mb-2">
                Employee Contribution (%)
              </label>
              <input
                id="employee_contribution_percent"
                v-model.number="formData.employee_contribution_percent"
                type="number"
                step="0.01"
                min="0"
                class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                :class="{ 'border-raspberry-500': validationErrors.employee_contribution_percent }"
                placeholder="e.g., 5.00"
                @blur="validateEmployeeContribution"
              />
              <p v-if="validationErrors.employee_contribution_percent" class="text-xs text-raspberry-500 mt-1">
                {{ validationErrors.employee_contribution_percent }}
              </p>
              <p v-else-if="calculatedEmployeeContribution" class="text-xs text-neutral-500 mt-1">
                = {{ formatCurrency(calculatedEmployeeContribution) }}/month
              </p>
              <p v-else class="text-xs text-neutral-500 mt-1">Enter as percentage of salary (0-100)</p>
            </div>
            <div :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'employer_contribution_percent' }">
              <label for="employer_contribution_percent" class="block text-sm font-medium text-neutral-500 mb-2">
                Employer Contribution (%)
              </label>
              <input
                id="employer_contribution_percent"
                v-model.number="formData.employer_contribution_percent"
                type="number"
                step="0.01"
                min="0"
                class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                :class="{ 'border-raspberry-500': validationErrors.employer_contribution_percent }"
                placeholder="e.g., 3.00"
                @blur="validateEmployerContribution"
              />
              <p v-if="validationErrors.employer_contribution_percent" class="text-xs text-raspberry-500 mt-1">
                {{ validationErrors.employer_contribution_percent }}
              </p>
              <p v-else-if="calculatedEmployerContribution" class="text-xs text-neutral-500 mt-1">
                = {{ formatCurrency(calculatedEmployerContribution) }}/month
              </p>
              <p v-else class="text-xs text-neutral-500 mt-1">Enter as percentage of salary (0-100)</p>
            </div>
          </div>

          <!-- Personal/SIPP: Fixed Monthly Contribution -->
          <div v-if="isPersonalPension">
            <label for="monthly_contribution_amount" class="block text-sm font-medium text-neutral-500 mb-2">
              Monthly Contribution (£)
            </label>
            <input
              id="monthly_contribution_amount"
              v-model.number="formData.monthly_contribution_amount"
              type="number"
              step="0.01"
              min="0"
              class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
              placeholder="e.g., 500.00"
            />
            <p class="text-xs text-neutral-500 mt-1">Fixed monthly contribution amount</p>
          </div>

          <!-- Personal/SIPP: Lump Sum Contribution (Carry Forward) -->
          <div v-if="isPersonalPension">
            <label for="lump_sum_contribution" class="block text-sm font-medium text-neutral-500 mb-2">
              Lump Sum Contribution (£) <span class="text-neutral-500 text-xs">(Optional)</span>
            </label>
            <input
              id="lump_sum_contribution"
              v-model.number="formData.lump_sum_contribution"
              type="number"
              step="0.01"
              min="0"
              class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
              placeholder="e.g., 10000.00"
            />
            <p class="text-xs text-neutral-500 mt-1">
              One-off lump sum payment to take advantage of carry forward allowances
            </p>
          </div>

          <!-- Expected Return -->
          <div>
            <label for="expected_return_percent" class="block text-sm font-medium text-neutral-500 mb-2">
              Expected Return (% p.a.)
            </label>
            <input
              id="expected_return_percent"
              v-model.number="formData.expected_return_percent"
              type="number"
              step="0.01"
              min="0"
              max="20"
              class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
              placeholder="e.g., 5.00"
            />
            <p class="text-xs text-neutral-500 mt-1">Typical: 4-6% for balanced funds</p>
          </div>

          <!-- Pension Access Age (SIPP and personal pensions only) -->
          <div v-if="isPersonalPension">
            <label for="retirement_age" class="block text-sm font-medium text-neutral-500 mb-2">
              Planned Access Age
            </label>
            <input
              id="retirement_age"
              v-model.number="formData.retirement_age"
              type="number"
              min="55"
              max="75"
              class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
              :placeholder="profileRetirementAge ? `Default: ${profileRetirementAge}` : 'e.g., 60'"
            />
            <p class="text-xs text-neutral-500 mt-1">
              When you plan to access this pension (minimum 55).
              <span v-if="profileRetirementAge"> Your profile retirement age is {{ profileRetirementAge }}.</span>
            </p>
            <p v-if="formData.retirement_age && formData.retirement_age < 55" class="text-xs text-raspberry-500 mt-1">
              Minimum pension access age is 55
            </p>
          </div>

          <!-- Risk Level Section (hidden during onboarding) -->
          <template v-if="!isOnboarding">
            <div v-if="hasRiskProfile" class="pt-4 border-t border-light-gray">
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
              <p class="mt-2 text-xs text-neutral-500">
                Your main risk profile is <strong>{{ mainRiskLevelDisplay }}</strong>.
                You can choose a different risk level for this pension if needed.
              </p>
            </div>
            <div v-else class="pt-4 border-t border-light-gray">
              <div class="bg-savannah-100 rounded-md p-3">
                <div class="flex items-start gap-2">
                  <svg class="w-5 h-5 text-violet-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  <div>
                    <p class="text-sm text-violet-800">
                      <router-link to="/risk-profile" class="font-medium underline hover:text-violet-900">
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
              class="h-4 w-4 text-violet-600 focus:ring-violet-500 border-horizon-300 rounded"
            />
            <label for="salary_sacrifice" class="ml-2 block text-sm text-neutral-500">
              Using salary sacrifice arrangement
            </label>
          </div>

          <!-- Beneficiary Section -->
          <div class="space-y-4 p-4 bg-violet-50 border border-violet-200 rounded-lg">
            <p class="text-sm text-violet-800 font-medium">Beneficiary Details</p>

            <!-- Beneficiary Selection -->
            <div>
              <label for="beneficiary_selection" class="block text-sm font-medium text-neutral-500 mb-1">
                Beneficiary
              </label>
              <select
                id="beneficiary_selection"
                v-model="beneficiarySelection"
                @change="handleBeneficiarySelection"
                class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
              >
                <option value="">Select beneficiary...</option>
                <option v-if="spouseOption" :value="'linked_' + spouseOption.id">
                  {{ spouseOption.name }} (Spouse - Linked Account)
                </option>
                <option value="other">Other (enter name)</option>
              </select>
              <p class="text-xs text-neutral-500 mt-1">
                Who should receive this pension if you pass away?
              </p>
            </div>

            <!-- Custom Beneficiary Name (when "Other" selected) -->
            <div v-if="beneficiarySelection === 'other'">
              <label for="beneficiary_name" class="block text-sm font-medium text-neutral-500 mb-1">
                Beneficiary Name
              </label>
              <input
                id="beneficiary_name"
                v-model="formData.beneficiary_name"
                type="text"
                placeholder="Enter beneficiary's full name"
                class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
              />
              <p class="text-xs text-neutral-500 mt-1">
                This person doesn't have an account in the system.
              </p>
            </div>
          </div>

          <!-- Notes -->
          <div>
            <label for="notes" class="block text-sm font-medium text-neutral-500 mb-2">
              Notes
            </label>
            <textarea
              id="notes"
              v-model="formData.notes"
              rows="3"
              class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
              placeholder="Any additional notes about this pension..."
            ></textarea>
          </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-end space-x-3 mt-8 pt-6 border-t border-light-gray">
          <p v-if="validationError" class="text-sm text-raspberry-500 mt-2 mr-auto">{{ validationError }}</p>
          <button
            v-if="context !== 'onboarding'"
            type="button"
            @click="$emit('close')"
            class="px-4 py-2 text-neutral-500 bg-white border border-horizon-300 rounded-lg hover:bg-savannah-100 transition-colors duration-200"
          >
            Cancel
          </button>
          <button
            type="submit"
            class="px-6 py-2 bg-violet-600 text-white rounded-lg hover:bg-violet-700 transition-colors duration-200"
          >
            {{ isEdit ? 'Update' : 'Add' }} Pension
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
import { mapState, mapGetters } from 'vuex';
import RiskLevelSelector from '@/components/Shared/RiskLevelSelector.vue';
import riskService from '@/services/riskService';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'DCPensionForm',

  emits: ['save', 'close'],

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
    context: {
      type: String,
      default: 'standalone',
      validator: (value) => ['standalone', 'onboarding'].includes(value),
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
        retirement_age: null,
        salary_sacrifice: false,
        notes: '',
        risk_preference: null,
        beneficiary_id: null,
        beneficiary_name: '',
      },
      validationError: null,
      validationErrors: {
        employee_contribution_percent: '',
        employer_contribution_percent: '',
      },
      // Risk profile state
      mainRiskLevel: null,
      allowedRiskLevels: ['low', 'lower_medium', 'medium', 'upper_medium', 'high'],
      // Beneficiary state
      beneficiarySelection: '',
    };
  },

  computed: {
    ...mapState('aiFormFill', ['pendingFill', 'highlightedField', 'filling']),
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

    profileRetirementAge() {
      return this.$store.state.userProfile?.profile?.target_retirement_age || null;
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

    spouseOption() {
      const spouse = this.$store.getters['userProfile/spouse'];
      return spouse ? { id: spouse.id, name: spouse.name } : null;
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
            beneficiary_id: newPension.beneficiary_id || null,
            beneficiary_name: newPension.beneficiary_name || '',
          };
          this.$nextTick(() => {
            this.initializeBeneficiarySelection();
          });
        }
      },
    },
    pendingFill: {
      handler(fill) {
        if (fill && fill.entityType === 'dc_pension' && fill.fields) {
          // Set pension_type immediately — it controls which conditional fields render
          // Also set scheme_type which is what validation checks (handlePensionTypeChange syncs these normally)
          if (fill.fields.pension_type) {
            this.formData.pension_type = fill.fields.pension_type;
            this.handlePensionTypeChange();
          }
          // Pre-set scheme_name — required for validation, may come from provider fallback
          if (fill.fields.scheme_name) {
            this.formData.scheme_name = fill.fields.scheme_name;
          } else if (fill.fields.provider) {
            this.formData.scheme_name = fill.fields.provider;
          }
          const fieldOrder = Object.keys(fill.fields).filter(k => fill.fields[k] !== null && fill.fields[k] !== '');
          this.$store.dispatch('aiFormFill/beginFieldSequence', fieldOrder);
        }
      },
      immediate: true,
    },
    highlightedField(fieldKey) {
      if (fieldKey && this.pendingFill?.fields) {
        const value = this.pendingFill.fields[fieldKey];
        if (value !== undefined && value !== null) {
          this.formData[fieldKey] = value;
        }
      }
    },
    filling(isFilling) {
      if (isFilling === false && this.pendingFill?.entityType === 'dc_pension') {
        setTimeout(() => {
          this.handleSubmit();
        }, 250);
      }
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

      // Default retirement age from profile for personal pensions
      if (this.isPersonalPension && !this.formData.retirement_age && this.profileRetirementAge) {
        this.formData.retirement_age = this.profileRetirementAge;
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

    handleBeneficiarySelection() {
      if (this.beneficiarySelection.startsWith('linked_')) {
        // Linked spouse selected
        const id = parseInt(this.beneficiarySelection.replace('linked_', ''));
        this.formData.beneficiary_id = id;
        this.formData.beneficiary_name = this.spouseOption?.name || '';
      } else if (this.beneficiarySelection === 'other') {
        // Custom beneficiary
        this.formData.beneficiary_id = null;
        this.formData.beneficiary_name = '';
      } else {
        // No beneficiary selected
        this.formData.beneficiary_id = null;
        this.formData.beneficiary_name = '';
      }
    },

    initializeBeneficiarySelection() {
      // Set beneficiarySelection based on existing data
      if (this.formData.beneficiary_id && this.spouseOption && this.formData.beneficiary_id === this.spouseOption.id) {
        this.beneficiarySelection = `linked_${this.formData.beneficiary_id}`;
      } else if (this.formData.beneficiary_name) {
        this.beneficiarySelection = 'other';
      } else {
        this.beneficiarySelection = '';
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

    handleSubmit() {
      this.validationError = null;

      if (this.isWorkplacePension) {
        this.validateEmployeeContribution();
        this.validateEmployerContribution();

        // Check if there are any validation errors
        if (this.validationErrors.employee_contribution_percent || this.validationErrors.employer_contribution_percent) {
          return;
        }
      }

      // Basic validation
      if (!this.formData.scheme_type) {
        this.validationError = 'Please select a pension type';
        return;
      }

      if (!this.formData.scheme_name) {
        this.validationError = 'Please enter a scheme name';
        return;
      }

      if (!this.formData.current_fund_value || this.formData.current_fund_value < 0) {
        this.validationError = 'Please enter a valid current fund value';
        return;
      }

      if (this.isPersonalPension && this.formData.retirement_age && this.formData.retirement_age < 55) {
        this.validationError = 'Minimum pension access age is 55';
        return;
      }

      this.$emit('save', this.formData);
    },
  },
};
</script>

