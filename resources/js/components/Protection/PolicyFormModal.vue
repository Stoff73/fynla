<template>
  <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <!-- Background overlay -->
    <div
      class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
      @click="handleClose"
    ></div>

    <!-- Modal container -->
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
      <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

      <!-- Modal panel -->
      <div
        class="relative inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full"
      >
        <!-- Header -->
        <div class="bg-white px-6 pt-6">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-semibold text-gray-900">
              {{ isEditing ? 'Edit Policy' : 'Add New Policy' }}
            </h3>
            <button
              @click="handleClose"
              class="text-gray-400 hover:text-gray-500 transition-colors"
            >
              <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M6 18L18 6M6 6l12 12"
                />
              </svg>
            </button>
          </div>
        </div>

        <!-- Form -->
        <form @submit.prevent="handleSubmit" class="px-6 pb-6">
          <div class="space-y-4 max-h-[calc(100vh-300px)] overflow-y-auto pr-2">
            <!-- Policy Type Selection (only for new policies) -->
            <div v-if="!isEditing">
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Policy Type <span class="text-red-500">*</span>
              </label>
              <select
                v-model="formData.policyType"
                required
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              >
                <option value="">Select policy type...</option>
                <option value="life">Life Insurance</option>
                <option value="criticalIllness">Critical Illness</option>
                <option value="incomeProtection">Income Protection</option>
                <option value="disability">Disability</option>
                <option value="sicknessIllness">Sickness/Illness</option>
              </select>
            </div>

            <!-- Life Policy Type (appears when Life Insurance is selected) -->
            <div v-if="showLifePolicyType">
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Life Policy Type <span class="text-red-500">*</span>
              </label>
              <select
                v-model="formData.life_policy_type"
                required
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              >
                <option value="">Select life policy type...</option>
                <option value="decreasing_term">Decreasing Life Policy</option>
                <option value="level_term">Level Term Life Policy</option>
                <option value="whole_of_life">Whole of Life Policy</option>
              </select>
            </div>

            <!-- Provider -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Provider <span class="text-red-500">*</span>
              </label>
              <input
                v-model="formData.provider"
                type="text"
                required
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                placeholder="e.g., Aviva, Legal & General"
              />
            </div>

            <!-- Policy Number -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Policy Number
              </label>
              <input
                v-model="formData.policy_number"
                type="text"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                placeholder="Policy reference number"
              />
            </div>

            <!-- Sum Assured / Benefit Amount -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                {{ coverageLabel }} <span class="text-red-500">*</span>
              </label>
              <div class="relative">
                <span class="absolute left-3 top-2.5 text-gray-500">£</span>
                <input
                  v-model.number="formData.coverage_amount"
                  type="number"
                  :step="isIncomeProtection ? 100 : 1000"
                  required
                  :min="isIncomeProtection ? 1000 : 0"
                  class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  placeholder="0"
                />
              </div>
              <p v-if="isIncomeProtection" class="text-xs text-gray-500 mt-1">
                This is the monthly amount paid out if you are unable to work. Minimum £1,000 per month.
              </p>
            </div>

            <!-- Decreasing Policy Fields -->
            <div v-if="showDecreasingFields" class="space-y-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
              <p class="text-sm text-blue-800 font-medium">Decreasing Policy Details</p>

              <!-- Start Value -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                  Start Value <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                  <span class="absolute left-3 top-2.5 text-gray-500">£</span>
                  <input
                    v-model.number="formData.start_value"
                    type="number"
                    step="1000"
                    required
                    min="1000"
                    class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="e.g., 500000"
                  />
                </div>
                <p class="text-xs text-gray-500 mt-1">Initial coverage amount at policy start</p>
              </div>

              <!-- Decreasing Rate -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                  Decreasing Rate (Annual %) <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                  <input
                    v-model.number="formData.decreasing_rate"
                    type="number"
                    step="0.01"
                    required
                    min="0"
                    max="100"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="e.g., 5.0"
                  />
                  <span class="absolute right-3 top-2.5 text-gray-500">%</span>
                </div>
                <p class="text-xs text-gray-500 mt-1">Annual percentage rate at which coverage decreases</p>
              </div>
            </div>

            <!-- Premium Amount -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                  Premium Amount <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                  <span class="absolute left-3 top-2.5 text-gray-500">£</span>
                  <input
                    v-model.number="formData.premium_amount"
                    type="number"
                    step="0.01"
                    required
                    min="0"
                    class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="0.00"
                  />
                </div>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                  Frequency <span class="text-red-500">*</span>
                </label>
                <select
                  v-model="formData.premium_frequency"
                  required
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                >
                  <option value="monthly">Monthly</option>
                  <option value="annual">Annual</option>
                </select>
              </div>
            </div>

            <!-- Start Date (conditional for life insurance) -->
            <div v-if="showStartDate">
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Start Date
              </label>
              <input
                v-model="formData.start_date"
                type="date"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              />
            </div>

            <!-- Term Years (for Life and Critical Illness) -->
            <div v-if="isLifeInsurance ? showTermYearsForLifePolicy : showTermYears">
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Policy Term (years)
              </label>
              <input
                v-model.number="formData.term_years"
                type="number"
                min="1"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                placeholder="e.g., 20"
              />
            </div>

            <!-- End Date (for all policies - optional) -->
            <div v-if="showEndDate">
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Policy End Date
              </label>
              <input
                v-model="formData.end_date"
                type="date"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              />
              <p class="text-xs text-gray-500 mt-1">
                When does this policy expire? Leave blank if policy has no end date.
              </p>
            </div>

            <!-- In Trust (for Life Insurance) -->
            <div v-if="formData.policyType === 'life'">
              <div class="flex items-center">
                <input
                  id="in_trust"
                  v-model="formData.in_trust"
                  type="checkbox"
                  class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                />
                <label for="in_trust" class="ml-2 block text-sm font-medium text-gray-700">
                  Is this policy in Trust?
                </label>
              </div>
              <p class="text-xs text-gray-500 mt-1 ml-6">
                Policies held in trust can help reduce the inheritance tax your family may need to pay. If you're not sure, leave this blank
              </p>
            </div>

            <!-- Mortgage Protection (for Life Insurance) -->
            <div v-if="formData.policyType === 'life'">
              <div class="flex items-center">
                <input
                  id="is_mortgage_protection"
                  v-model="formData.is_mortgage_protection"
                  type="checkbox"
                  class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                />
                <label for="is_mortgage_protection" class="ml-2 block text-sm font-medium text-gray-700">
                  Is this to pay off your mortgage?
                </label>
              </div>
              <p class="text-xs text-gray-500 mt-1 ml-6">
                If you are not sure leave this blank
              </p>
            </div>

            <!-- Beneficiaries (for Life Insurance) -->
            <div v-if="isLifeInsurance" class="space-y-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
              <p class="text-sm text-blue-800 font-medium">Beneficiary Details</p>

              <!-- Beneficiary Selection -->
              <div>
                <label for="beneficiary_selection" class="block text-sm font-medium text-gray-700 mb-1">
                  Beneficiary <span class="text-red-500">*</span>
                </label>
                <select
                  id="beneficiary_selection"
                  v-model="beneficiarySelection"
                  @change="handleBeneficiarySelection"
                  required
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                >
                  <option value="">Select beneficiary...</option>
                  <option v-if="spouseOption" :value="'linked_' + spouseOption.id">
                    {{ spouseOption.name }} (Spouse - Linked Account)
                  </option>
                  <option value="other">Add Beneficiary</option>
                </select>
              </div>

              <!-- Free Text Beneficiary Name (when "Add Beneficiary" selected) -->
              <div v-if="beneficiarySelection === 'other'">
                <label for="beneficiary_name" class="block text-sm font-medium text-gray-700 mb-1">
                  Beneficiary Name <span class="text-red-500">*</span>
                </label>
                <input
                  id="beneficiary_name"
                  v-model="formData.beneficiary_name"
                  type="text"
                  required
                  placeholder="Enter beneficiary's full name"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                />
                <p class="text-xs text-gray-500 mt-1">
                  Note: This person doesn't have an account in the system.
                </p>
              </div>

              <!-- Beneficiary Percentage (shows when beneficiary selected) -->
              <div v-if="beneficiarySelection">
                <label for="beneficiary_percentage" class="block text-sm font-medium text-gray-700 mb-1">
                  Beneficiary Share (%) <span class="text-red-500">*</span>
                </label>
                <input
                  id="beneficiary_percentage"
                  v-model.number="formData.beneficiary_percentage"
                  type="number"
                  min="1"
                  max="100"
                  required
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                />
                <p class="text-xs text-gray-500 mt-1">
                  Enter the percentage share for this beneficiary (1-100%).
                </p>
              </div>

              <!-- Percentage Split Display -->
              <div v-if="beneficiarySelection" class="bg-white p-3 rounded border border-blue-300">
                <div class="flex justify-between items-center">
                  <div>
                    <p class="text-sm font-medium text-gray-700">Primary Beneficiary</p>
                    <p class="text-2xl font-bold text-blue-600">{{ formData.beneficiary_percentage || 0 }}%</p>
                  </div>
                  <div v-if="remainingBeneficiaryPercentage > 0" class="text-gray-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                    </svg>
                  </div>
                  <div v-if="remainingBeneficiaryPercentage > 0" class="text-right">
                    <p class="text-sm font-medium text-gray-700">Remaining</p>
                    <p class="text-2xl font-bold text-blue-600">{{ remainingBeneficiaryPercentage }}%</p>
                  </div>
                </div>
              </div>

              <!-- Additional Beneficiaries (only shows when percentage < 100%) -->
              <div v-if="showAdditionalBeneficiaries">
                <label for="additional_beneficiaries" class="block text-sm font-medium text-gray-700 mb-1">
                  Additional Beneficiaries
                </label>
                <textarea
                  id="additional_beneficiaries"
                  v-model="formData.additional_beneficiaries"
                  rows="2"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  placeholder="e.g., Children: 30% split, Charity: 10%"
                ></textarea>
                <p class="text-xs text-gray-500 mt-1">
                  Specify additional beneficiaries and their share of the remaining {{ remainingBeneficiaryPercentage }}%.
                </p>
              </div>

              <p class="text-xs text-gray-500">
                Linked accounts will be notified and benefits will appear in their accounts.
              </p>
            </div>

            <!-- Benefit Frequency (for Income-based policies) -->
            <div v-if="showBenefitFrequency">
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Benefit Frequency
              </label>
              <select
                v-model="formData.benefit_frequency"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              >
                <option value="monthly">Monthly</option>
                <option value="weekly">Weekly</option>
                <option value="lump_sum">Lump Sum</option>
              </select>
            </div>

            <!-- Deferred Period (for Income Protection and Disability) -->
            <div v-if="showDeferredPeriod">
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Deferred Period (weeks)
              </label>
              <input
                v-model.number="formData.deferred_period_weeks"
                type="number"
                min="0"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                placeholder="e.g., 4"
              />
            </div>

            <!-- Benefit Period (for Income-based policies) -->
            <div v-if="showBenefitPeriod">
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Benefit Period (months)
              </label>
              <input
                v-model.number="formData.benefit_period_months"
                type="number"
                min="1"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                placeholder="e.g., 24"
              />
            </div>

            <!-- Coverage Type (for Disability) -->
            <div v-if="showCoverageType">
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Coverage Type
              </label>
              <select
                v-model="formData.coverage_type"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              >
                <option value="accident_only">Accident Only</option>
                <option value="accident_and_sickness">Accident and Sickness</option>
              </select>
            </div>

            <!-- Notes -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Additional Notes
              </label>
              <textarea
                v-model="formData.notes"
                rows="3"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                placeholder="Any additional information about this policy..."
              ></textarea>
            </div>
          </div>

          <!-- Form Actions -->
          <div class="mt-6 flex gap-3">
            <button
              type="submit"
              :disabled="submitting"
              class="flex-1 px-6 py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 disabled:bg-gray-300 disabled:cursor-not-allowed transition-colors"
            >
              {{ submitting ? 'Saving...' : (isEditing ? 'Update Policy' : 'Add Policy') }}
            </button>
            <button
              type="button"
              @click="handleClose"
              class="px-6 py-3 bg-gray-100 text-gray-700 font-medium rounded-lg hover:bg-gray-200 transition-colors"
            >
              Cancel
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'PolicyFormModal',

  props: {
    policy: {
      type: Object,
      default: null,
    },
    isEditing: {
      type: Boolean,
      default: false,
    },
  },

  data() {
    return {
      submitting: false,
      familyMembers: [],
      beneficiarySelection: '',
      formData: {
        policyType: '',
        life_policy_type: '',
        provider: '',
        policy_number: '',
        coverage_amount: 0,
        start_value: 0,
        decreasing_rate: 0,
        premium_amount: 0,
        premium_frequency: 'monthly',
        start_date: '',
        end_date: '',
        term_years: null,
        in_trust: false,
        is_mortgage_protection: false,
        beneficiary_name: '',
        beneficiary_percentage: 100,
        additional_beneficiaries: '',
        benefit_frequency: 'monthly',
        deferred_period_weeks: null,
        benefit_period_months: null,
        coverage_type: 'accident_and_sickness',
        notes: '',
      },
    };
  },

  computed: {
    isPreviewMode() {
      return this.$store.getters['preview/isPreviewMode'];
    },
    coverageLabel() {
      const type = this.formData.policyType || this.policy?.policy_type;
      if (type === 'life' || type === 'criticalIllness') {
        return 'Sum Assured';
      }
      return 'Benefit Amount';
    },

    showTermYears() {
      const type = this.formData.policyType || this.policy?.policy_type;
      return type === 'life' || type === 'criticalIllness';
    },

    showBenefitFrequency() {
      const type = this.formData.policyType || this.policy?.policy_type;
      return type === 'incomeProtection' || type === 'disability' || type === 'sicknessIllness';
    },

    showDeferredPeriod() {
      const type = this.formData.policyType || this.policy?.policy_type;
      return type === 'incomeProtection' || type === 'disability';
    },

    showBenefitPeriod() {
      const type = this.formData.policyType || this.policy?.policy_type;
      return type === 'incomeProtection' || type === 'disability' || type === 'sicknessIllness';
    },

    showCoverageType() {
      const type = this.formData.policyType || this.policy?.policy_type;
      return type === 'disability';
    },

    isLifeInsurance() {
      const type = this.formData.policyType || this.policy?.policy_type;
      return type === 'life';
    },

    isIncomeProtection() {
      const type = this.formData.policyType || this.policy?.policy_type;
      return type === 'incomeProtection';
    },

    showLifePolicyType() {
      return this.isLifeInsurance;
    },

    showDecreasingFields() {
      return this.isLifeInsurance && this.formData.life_policy_type === 'decreasing_term';
    },

    showStartDate() {
      if (!this.isLifeInsurance) return true; // Other policies always show start date
      const lifeType = this.formData.life_policy_type;
      // Show for decreasing_term and term, hide for whole_of_life
      return lifeType === 'decreasing_term' || lifeType === 'term' || lifeType === 'level_term';
    },

    showTermYearsForLifePolicy() {
      if (!this.isLifeInsurance) return false;
      const lifeType = this.formData.life_policy_type;
      // Show for all except whole_of_life
      return lifeType !== 'whole_of_life';
    },

    spouseOption() {
      // Use the same pattern as PropertyForm - get spouse from store
      const spouse = this.$store.getters['userProfile/spouse'];
      return spouse ? { id: spouse.id, name: spouse.name } : null;
    },

    remainingBeneficiaryPercentage() {
      return 100 - (this.formData.beneficiary_percentage || 0);
    },

    showAdditionalBeneficiaries() {
      return this.isLifeInsurance &&
             this.beneficiarySelection &&
             this.formData.beneficiary_percentage < 100;
    },

    showEndDate() {
      // All policy types can have an optional end date
      if (!this.isLifeInsurance) return true;

      // For life insurance, show for term-based policies, hide for whole_of_life
      const lifeType = this.formData.life_policy_type;
      return lifeType === 'decreasing_term' || lifeType === 'term' || lifeType === 'level_term';
    },
  },

  async mounted() {
    await this.loadFamilyMembers();
    if (this.isEditing && this.policy) {
      this.loadPolicyData();
    }
  },

  methods: {
    async loadFamilyMembers() {
      if (this.isPreviewMode) {
        console.log('[PolicyFormModal] Preview mode - using store data for family members');
        this.familyMembers = this.$store.state.userProfile?.familyMembers || [];
        return;
      }
      try {
        const familyMembersService = (await import('@/services/familyMembersService')).default;
        const response = await familyMembersService.getFamilyMembers();
        this.familyMembers = response.data?.family_members || [];
      } catch (error) {
        console.error('Error loading family members:', error);
        this.familyMembers = [];
      }
    },

    handleBeneficiarySelection() {
      // When user selects a linked spouse, populate the name
      if (this.beneficiarySelection.startsWith('linked_') && this.spouseOption) {
        this.formData.beneficiary_name = this.spouseOption.name;
      } else if (this.beneficiarySelection === 'other') {
        // Clear the name so user can enter their own
        this.formData.beneficiary_name = '';
      }
    },

    formatDateForInput(date) {
      if (!date) return '';
      try {
        // If it's already in YYYY-MM-DD format, return it
        if (typeof date === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(date)) {
          return date;
        }
        // Parse and format the date
        const dateObj = new Date(date);
        if (isNaN(dateObj.getTime())) return '';
        const year = dateObj.getFullYear();
        const month = String(dateObj.getMonth() + 1).padStart(2, '0');
        const day = String(dateObj.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
      } catch (e) {
        return '';
      }
    },

    loadPolicyData() {
      // Parse beneficiaries string (format: "Name: 60%, Additional text")
      let beneficiary_name = '';
      let beneficiary_percentage = 100;
      let additional_beneficiaries = '';

      if (this.policy.beneficiaries) {
        const beneficiariesStr = this.policy.beneficiaries;

        // Split by first comma to separate primary beneficiary from additional
        const parts = beneficiariesStr.split(',');

        if (parts.length > 0 && parts[0].includes(':')) {
          // Parse primary beneficiary (format: "Name: 60%")
          const primaryParts = parts[0].split(':');
          beneficiary_name = primaryParts[0].trim();

          // Extract percentage
          if (primaryParts[1]) {
            const percentMatch = primaryParts[1].match(/(\d+)%/);
            if (percentMatch) {
              beneficiary_percentage = parseInt(percentMatch[1], 10);
            }
          }
        }

        // If there are additional parts, combine them as additional beneficiaries
        if (parts.length > 1) {
          additional_beneficiaries = parts.slice(1).join(',').trim();
        }
      }

      // Check if beneficiary matches a linked spouse
      if (beneficiary_name && this.spouseOption && beneficiary_name === this.spouseOption.name) {
        this.beneficiarySelection = `linked_${this.spouseOption.id}`;
      } else if (beneficiary_name) {
        this.beneficiarySelection = 'other';
      }

      this.formData = {
        policyType: this.policy.policy_type,
        life_policy_type: this.policy.policy_subtype || this.policy.life_policy_type || '',
        provider: this.policy.provider || '',
        policy_number: this.policy.policy_number || '',
        coverage_amount: this.policy.sum_assured || this.policy.benefit_amount || 0,
        start_value: this.policy.start_value || 0,
        decreasing_rate: this.policy.decreasing_rate ? this.policy.decreasing_rate * 100 : 0, // Convert decimal to percentage
        premium_amount: this.policy.premium_amount || 0,
        premium_frequency: this.policy.premium_frequency || 'monthly',
        start_date: this.formatDateForInput(this.policy.start_date || this.policy.policy_start_date),
        end_date: this.formatDateForInput(this.policy.end_date || this.policy.policy_end_date),
        term_years: this.policy.term_years || this.policy.policy_term_years || null,
        in_trust: this.policy.in_trust || false,
        is_mortgage_protection: this.policy.is_mortgage_protection || false,
        beneficiary_name: beneficiary_name,
        beneficiary_percentage: beneficiary_percentage,
        additional_beneficiaries: additional_beneficiaries,
        benefit_frequency: this.policy.benefit_frequency || 'monthly',
        deferred_period_weeks: this.policy.deferred_period_weeks || null,
        benefit_period_months: this.policy.benefit_period_months || null,
        coverage_type: this.policy.coverage_type || 'accident_and_sickness',
        notes: this.policy.notes || '',
      };
    },

    async handleSubmit() {
      this.submitting = true;

      try {
        const policyData = this.preparePolicyData();
        this.$emit('save', policyData);
      } catch (error) {
        console.error('Form submission error:', error);
      } finally {
        this.submitting = false;
      }
    },

    preparePolicyData() {
      const type = this.formData.policyType || this.policy?.policy_type;
      const data = {
        policyType: type,
        provider: this.formData.provider,
        policy_number: this.formData.policy_number,
        premium_amount: this.formData.premium_amount,
        premium_frequency: this.formData.premium_frequency === 'annual' ? 'annually' : this.formData.premium_frequency,
      };

      // Add coverage amount with correct field name
      if (type === 'life') {
        data.policy_type = this.formData.life_policy_type || 'term'; // Use selected life policy type
        data.sum_assured = this.formData.coverage_amount;

        // Add decreasing policy fields
        if (this.formData.life_policy_type === 'decreasing_term') {
          data.start_value = this.formData.start_value;
          // Convert percentage to decimal (e.g., 5% becomes 0.05)
          data.decreasing_rate = this.formData.decreasing_rate / 100;
        }

        // Add dates and term based on policy type
        if (this.formData.life_policy_type === 'whole_of_life') {
          // Whole of life policies: use start date or today, and set term to 50 years (max allowed, represents lifetime coverage)
          data.policy_start_date = this.formData.start_date || null;
          data.policy_end_date = this.formData.end_date || null;
          data.policy_term_years = this.formData.term_years || null;
        } else {
          // Term-based policies require end_date
          data.policy_start_date = this.formData.start_date || null;
          data.policy_end_date = this.formData.end_date;
          data.policy_term_years = this.formData.term_years || null;
        }

        data.in_trust = this.formData.in_trust || false;
        data.is_mortgage_protection = this.formData.is_mortgage_protection || false;

        // Build beneficiaries string
        let beneficiaries = '';
        if (this.formData.beneficiary_name) {
          beneficiaries = `${this.formData.beneficiary_name}: ${this.formData.beneficiary_percentage}%`;
        }
        if (this.formData.additional_beneficiaries) {
          beneficiaries = beneficiaries
            ? `${beneficiaries}, ${this.formData.additional_beneficiaries}`
            : this.formData.additional_beneficiaries;
        }
        data.beneficiaries = beneficiaries || null;
      } else if (type === 'criticalIllness') {
        data.policy_type = 'standalone'; // Default to standalone critical illness
        data.sum_assured = this.formData.coverage_amount;
        data.policy_start_date = this.formData.start_date;
        data.policy_end_date = this.formData.end_date || null;
        data.policy_term_years = this.formData.term_years;
        data.conditions_covered = []; // Empty array for conditions covered
      } else {
        data.benefit_amount = this.formData.coverage_amount;
        data.benefit_frequency = this.formData.benefit_frequency;
        data.benefit_period_months = this.formData.benefit_period_months;
        data.policy_start_date = this.formData.start_date;
        data.policy_end_date = this.formData.end_date || null;
        data.policy_term_years = this.formData.term_years || null;
      }

      // Add deferred period for income protection and disability
      if (type === 'incomeProtection' || type === 'disability') {
        data.deferred_period_weeks = this.formData.deferred_period_weeks || 0;
      }

      // Add coverage type for disability
      if (type === 'disability') {
        data.coverage_type = this.formData.coverage_type;
      }

      return data;
    },

    handleClose() {
      this.$emit('close');
    },
  },
};
</script>

<style scoped>
/* Custom scrollbar for form content */
.overflow-y-auto {
  scrollbar-width: thin;
  scrollbar-color: #CBD5E0 #F7FAFC;
}

.overflow-y-auto::-webkit-scrollbar {
  width: 6px;
}

.overflow-y-auto::-webkit-scrollbar-track {
  background: #F7FAFC;
  border-radius: 3px;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
  background: #CBD5E0;
  border-radius: 3px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
  background: #A0AEC0;
}
</style>
