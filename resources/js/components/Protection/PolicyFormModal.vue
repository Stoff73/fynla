<template>
  <div :class="context === 'onboarding' ? '' : 'fixed inset-0 z-50 overflow-y-auto'" :role="context === 'onboarding' ? undefined : 'dialog'" :aria-modal="context === 'onboarding' ? undefined : 'true'">
    <!-- Background overlay (modal only) -->
    <div v-if="context !== 'onboarding'" class="fixed inset-0 bg-black/50 transition-opacity"></div>

    <!-- Container -->
    <div :class="context === 'onboarding' ? '' : 'flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0'">
      <span v-if="context !== 'onboarding'" class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

      <!-- Panel -->
      <div :class="context === 'onboarding' ? '' : 'relative inline-block align-bottom bg-white rounded-lg text-left shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full mx-4 sm:mx-0 max-h-[90vh] overflow-y-auto scrollbar-thin'">
        <!-- Header -->
        <div class="bg-white px-6 pt-6">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-semibold text-horizon-500">
              {{ isEditing ? 'Edit Policy' : 'Add New Policy' }}
            </h3>
            <button
              v-if="context !== 'onboarding'"
              @click="handleClose"
              class="text-horizon-400 hover:text-neutral-500 transition-colors"
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
        <form @submit.prevent="handleSubmit" :class="context === 'onboarding' ? '' : 'px-6 pb-6'">
          <div class="space-y-4 pr-2">
            <!-- Policy Type Selection (only for new policies) -->
            <div v-if="!isEditing" :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'policyType' }">
              <label class="block text-sm font-medium text-neutral-500 mb-1">
                Policy Type
              </label>
              <select
                v-model="formData.policyType"
                class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
              >
                <option value="">Select policy type...</option>
                <!-- Stage-suggested types shown first when in onboarding -->
                <optgroup v-if="context === 'onboarding' && stageDefaultPolicyTypes.length" label="Recommended for your stage">
                  <option v-if="stageDefaultPolicyTypes.includes('life')" value="life">Life Insurance</option>
                  <option v-if="stageDefaultPolicyTypes.includes('critical_illness')" value="criticalIllness">Critical Illness</option>
                  <option v-if="stageDefaultPolicyTypes.includes('income_protection')" value="incomeProtection">Income Protection</option>
                  <option v-if="stageDefaultPolicyTypes.includes('whole_of_life')" value="life">Whole of Life Insurance</option>
                  <option v-if="stageDefaultPolicyTypes.includes('disability')" value="disability">Disability</option>
                  <option v-if="stageDefaultPolicyTypes.includes('funeral_plan')" value="sicknessIllness">Funeral Plan</option>
                </optgroup>
                <optgroup :label="context === 'onboarding' && stageDefaultPolicyTypes.length ? 'All policy types' : 'Policy types'">
                  <option value="life">Life Insurance</option>
                  <option value="criticalIllness">Critical Illness</option>
                  <option value="incomeProtection">Income Protection</option>
                  <option value="disability">Disability</option>
                  <option value="sicknessIllness">Sickness/Illness</option>
                </optgroup>
              </select>
            </div>

            <!-- Life Policy Type (appears when Life Insurance is selected) -->
            <div v-if="showLifePolicyType" :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'life_policy_type' }">
              <label class="block text-sm font-medium text-neutral-500 mb-1">
                Life Policy Type
              </label>
              <select
                v-model="formData.life_policy_type"
                class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
              >
                <option value="">Select life policy type...</option>
                <option value="decreasing_term">Decreasing Life Policy</option>
                <option value="family_income_benefit">Family Income Benefit</option>
                <option value="level_term">Level Term Life Policy</option>
                <option value="whole_of_life">Whole of Life Policy</option>
              </select>
            </div>

            <!-- Provider -->
            <div :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'provider' }">
              <label class="block text-sm font-medium text-neutral-500 mb-1">
                Provider
              </label>
              <input
                v-model="formData.provider"
                type="text"
                class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                placeholder="e.g., Aviva, Legal & General"
              />
            </div>

            <!-- Policy Number -->
            <div>
              <label class="block text-sm font-medium text-neutral-500 mb-1">
                Policy Number
              </label>
              <input
                v-model="formData.policy_number"
                type="text"
                class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                placeholder="Policy reference number"
              />
            </div>

            <!-- Sum Assured / Benefit Amount -->
            <div :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'coverage_amount' }">
              <label class="block text-sm font-medium text-neutral-500 mb-1">
                {{ coverageLabel }}
              </label>
              <div class="relative">
                <span class="absolute left-3 top-2.5 text-neutral-500">£</span>
                <input
                  v-model.number="formData.coverage_amount"
                  type="number"
                  :step="isIncomeProtection ? 100 : 1000"
                  min="0"
                  class="w-full pl-8 pr-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                  placeholder="0"
                />
              </div>
              <p v-if="isIncomeProtection" class="text-xs text-neutral-500 mt-1">
                This is the monthly amount paid out if you are unable to work.
              </p>
            </div>

            <!-- Decreasing Policy Fields -->
            <div v-if="showDecreasingFields" class="space-y-4 p-4 bg-violet-50 border border-violet-200 rounded-lg">
              <p class="text-sm text-violet-800 font-medium">Decreasing Policy Details</p>

              <!-- Start Value -->
              <div>
                <label class="block text-sm font-medium text-neutral-500 mb-1">
                  Start Value
                </label>
                <div class="relative">
                  <span class="absolute left-3 top-2.5 text-neutral-500">£</span>
                  <input
                    v-model.number="formData.start_value"
                    type="number"
                    step="1000"
                    min="0"
                    class="w-full pl-8 pr-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                    placeholder="e.g., 500000"
                  />
                </div>
                <p class="text-xs text-neutral-500 mt-1">Initial coverage amount at policy start</p>
              </div>

              <!-- Decreasing Rate -->
              <div>
                <label class="block text-sm font-medium text-neutral-500 mb-1">
                  Decreasing Rate (Annual %)
                </label>
                <div class="relative">
                  <input
                    v-model.number="formData.decreasing_rate"
                    type="number"
                    step="0.01"
                    min="0"
                    max="100"
                    class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                    placeholder="e.g., 5.0"
                  />
                  <span class="absolute right-3 top-2.5 text-neutral-500">%</span>
                </div>
                <p class="text-xs text-neutral-500 mt-1">Annual percentage rate at which coverage decreases</p>
              </div>
            </div>

            <!-- Premium Amount -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4" :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'premium_amount' }">
              <div>
                <label class="block text-sm font-medium text-neutral-500 mb-1">
                  Premium Amount
                </label>
                <div class="relative">
                  <span class="absolute left-3 top-2.5 text-neutral-500">£</span>
                  <input
                    v-model.number="formData.premium_amount"
                    type="number"
                    step="0.01"
                    min="0"
                    class="w-full pl-8 pr-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                    placeholder="0.00"
                  />
                </div>
              </div>

              <div>
                <label class="block text-sm font-medium text-neutral-500 mb-1">
                  Frequency
                </label>
                <select
                  v-model="formData.premium_frequency"
                  class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                >
                  <option value="monthly">Monthly</option>
                  <option value="annual">Annual</option>
                </select>
              </div>
            </div>

            <!-- Start Date (every policy type carries one) -->
            <div>
              <label class="block text-sm font-medium text-neutral-500 mb-1">
                Start Date
              </label>
              <input
                v-model="formData.start_date"
                type="date"
                class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
              />
            </div>

            <!-- Term Years (for Life and Critical Illness) -->
            <div v-if="isLifeInsurance ? showTermYearsForLifePolicy : showTermYears">
              <label class="block text-sm font-medium text-neutral-500 mb-1">
                Policy Term (years)
              </label>
              <input
                v-model.number="formData.term_years"
                type="number"
                min="1"
                class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                placeholder="e.g., 20"
              />
            </div>

            <!-- End Date (for all policies - optional) -->
            <div>
              <label class="block text-sm font-medium text-neutral-500 mb-1">
                Policy End Date
              </label>
              <input
                v-model="formData.end_date"
                type="date"
                class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
              />
              <p class="text-xs text-neutral-500 mt-1">
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
                  class="h-4 w-4 text-violet-600 focus:ring-violet-500 border-horizon-300 rounded"
                />
                <label for="in_trust" class="ml-2 block text-sm font-medium text-neutral-500">
                  Is this policy in Trust?
                </label>
              </div>
              <p class="text-xs text-neutral-500 mt-1 ml-6">
                Policies held in trust can help reduce the inheritance tax your family may need to pay. If you're not sure, leave this blank
              </p>
            </div>

            <!-- Joint Life (for Life Insurance) -->
            <div v-if="formData.policyType === 'life'">
              <div class="flex items-center">
                <input
                  id="joint_life"
                  v-model="formData.joint_life"
                  type="checkbox"
                  class="h-4 w-4 text-violet-600 focus:ring-violet-500 border-horizon-300 rounded"
                />
                <label for="joint_life" class="ml-2 block text-sm font-medium text-neutral-500">
                  Is this a joint life policy?
                </label>
              </div>
              <p class="text-xs text-neutral-500 mt-1 ml-6">
                A joint life policy covers two people and pays out once, on the first death. Two separate single life policies pay out twice.
              </p>
            </div>

            <!-- Mortgage Protection (for Life Insurance) -->
            <div v-if="formData.policyType === 'life'">
              <div class="flex items-center">
                <input
                  id="is_mortgage_protection"
                  v-model="formData.is_mortgage_protection"
                  type="checkbox"
                  class="h-4 w-4 text-violet-600 focus:ring-violet-500 border-horizon-300 rounded"
                />
                <label for="is_mortgage_protection" class="ml-2 block text-sm font-medium text-neutral-500">
                  Is this to pay off your mortgage?
                </label>
              </div>
              <p class="text-xs text-neutral-500 mt-1 ml-6">
                If you are not sure leave this blank
              </p>
            </div>

            <!-- Beneficiaries (for Life Insurance) -->
            <div v-if="isLifeInsurance" class="space-y-4 p-4 bg-violet-50 border border-violet-200 rounded-lg">
              <p class="text-sm text-violet-800 font-medium">Beneficiary Details</p>

              <!-- One row per beneficiary. A policy in trust distributes by this
                   nomination, so every named person has to be recordable here. -->
              <div
                v-for="(beneficiary, index) in beneficiaryRows"
                :key="index"
                class="bg-white p-3 rounded border border-violet-300 space-y-3"
              >
                <div>
                  <label :for="`beneficiary_selection_${index}`" class="block text-sm font-medium text-neutral-500 mb-1">
                    Beneficiary {{ index + 1 }}
                  </label>
                  <select
                    :id="`beneficiary_selection_${index}`"
                    v-model="beneficiary.selection"
                    @change="handleBeneficiarySelection(index)"
                    class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                  >
                    <option value="">Select beneficiary...</option>
                    <option v-for="option in beneficiaryOptions" :key="option.value" :value="option.value">
                      {{ option.label }}
                    </option>
                    <option value="other">Someone else</option>
                  </select>
                </div>

                <!-- Free Text Beneficiary Name (when "Someone else" selected) -->
                <div v-if="beneficiary.selection === 'other'">
                  <label :for="`beneficiary_name_${index}`" class="block text-sm font-medium text-neutral-500 mb-1">
                    Beneficiary Name
                  </label>
                  <input
                    :id="`beneficiary_name_${index}`"
                    v-model="beneficiary.name"
                    type="text"
                    placeholder="Enter beneficiary's full name"
                    class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                  />
                  <p class="text-xs text-neutral-500 mt-1">
                    Note: This person doesn't have an account in the system.
                  </p>
                </div>

                <div v-if="beneficiary.selection">
                  <label :for="`beneficiary_percentage_${index}`" class="block text-sm font-medium text-neutral-500 mb-1">
                    Beneficiary Share (%)
                  </label>
                  <input
                    :id="`beneficiary_percentage_${index}`"
                    v-model.number="beneficiary.percentage"
                    type="number"
                    min="1"
                    max="100"
                    class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                  />
                </div>

                <button
                  v-if="beneficiaryRows.length > 1"
                  type="button"
                  @click="removeBeneficiary(index)"
                  class="text-sm font-medium text-raspberry-600 hover:text-raspberry-700 transition-colors"
                >
                  Remove this beneficiary
                </button>
              </div>

              <div class="flex flex-wrap items-center justify-between gap-3">
                <button
                  type="button"
                  @click="addBeneficiary"
                  class="px-3 py-2 bg-white border border-violet-300 text-violet-600 text-sm font-medium rounded-lg hover:bg-violet-100 transition-colors"
                >
                  Add another beneficiary
                </button>
                <p class="text-sm font-medium" :class="beneficiaryTotal === 100 ? 'text-neutral-500' : 'text-violet-600'">
                  Shares total {{ beneficiaryTotal }}%
                </p>
              </div>
              <p v-if="beneficiaryTotal > 0 && beneficiaryTotal !== 100" class="text-xs text-violet-600">
                Most policies expect the shares to add up to 100%.
              </p>

              <p class="text-xs text-neutral-500">
                Linked accounts will be notified and benefits will appear in their accounts.
              </p>
            </div>

            <!-- Benefit Frequency (for Income-based policies) -->
            <div v-if="showBenefitFrequency">
              <label class="block text-sm font-medium text-neutral-500 mb-1">
                Benefit Frequency
              </label>
              <select
                v-model="formData.benefit_frequency"
                class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
              >
                <option value="monthly">Monthly</option>
                <option value="weekly">Weekly</option>
                <option value="lump_sum">Lump Sum</option>
              </select>
            </div>

            <!-- Deferred Period (for Income Protection and Disability) -->
            <div v-if="showDeferredPeriod">
              <label class="block text-sm font-medium text-neutral-500 mb-1">
                Deferred Period (weeks)
              </label>
              <input
                v-model.number="formData.deferred_period_weeks"
                type="number"
                min="0"
                class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                placeholder="e.g., 4"
              />
            </div>

            <!-- Benefit Period (for Income-based policies) -->
            <div v-if="showBenefitPeriod">
              <label class="block text-sm font-medium text-neutral-500 mb-1">
                Benefit Period (months)
              </label>
              <input
                v-model.number="formData.benefit_period_months"
                type="number"
                min="1"
                class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                placeholder="e.g., 24"
              />
            </div>

            <!-- Coverage Type (for Disability) -->
            <div v-if="showCoverageType">
              <label class="block text-sm font-medium text-neutral-500 mb-1">
                Coverage Type
              </label>
              <select
                v-model="formData.coverage_type"
                class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
              >
                <option value="accident_only">Accident Only</option>
                <option value="accident_and_sickness">Accident and Sickness</option>
              </select>
            </div>

            <!-- Notes -->
            <div>
              <label class="block text-sm font-medium text-neutral-500 mb-1">
                Additional Notes
              </label>
              <textarea
                v-model="formData.notes"
                rows="3"
                class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                placeholder="Any additional information about this policy..."
              ></textarea>
            </div>
          </div>

          <!-- Form Actions -->
          <div class="mt-6 flex gap-3" :class="context === 'onboarding' ? 'justify-end' : ''">
            <button
              type="button"
              @click="handleClose"
              :class="context === 'onboarding'
                ? 'px-4 py-2 bg-light-pink-100 hover:bg-light-pink-200 text-horizon-500 rounded-lg transition-colors text-sm font-medium'
                : 'px-6 py-3 bg-savannah-100 text-neutral-500 font-medium rounded-lg hover:bg-savannah-200 transition-colors'"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="submitting"
              :class="context === 'onboarding'
                ? 'px-6 py-2 bg-raspberry-500 text-white rounded-lg hover:bg-raspberry-600 transition-colors text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed'
                : 'flex-1 px-6 py-3 bg-raspberry-500 text-white font-medium rounded-button hover:bg-raspberry-600 disabled:bg-savannah-300 disabled:cursor-not-allowed transition-colors'"
            >
              {{ submitting ? 'Saving...' : (context === 'onboarding' ? 'Save' : (isEditing ? 'Update Policy' : 'Add Policy')) }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import { mapState } from 'vuex';

import logger from '@/utils/logger';

// `life_insurance_policies.beneficiaries` is one text column holding
// "Name: 60%, Name: 40%". These two functions are the only place that shape is
// read or written, so the form can offer as many beneficiaries as the policy
// names without a schema change. A fragment that is not "Name: N%" (free text
// typed before this form supported rows) survives as a name-only row and
// serialises back unchanged.
const BENEFICIARY_SHARE_PATTERN = /^(.*?):\s*(\d+(?:\.\d+)?)\s*%$/;

function parseBeneficiaries(value) {
  if (!value) return [];

  return String(value)
    .split(',')
    .map(part => part.trim())
    .filter(part => part.length > 0)
    .map(part => {
      const match = part.match(BENEFICIARY_SHARE_PATTERN);
      return match
        ? { name: match[1].trim(), percentage: parseFloat(match[2]) }
        : { name: part, percentage: null };
    });
}

function serialiseBeneficiaries(rows) {
  return rows
    .map(row => {
      const name = (row.name || '').trim();
      if (!name) return '';
      return row.percentage === null || row.percentage === '' || isNaN(Number(row.percentage))
        ? name
        : `${name}: ${Number(row.percentage)}%`;
    })
    .filter(entry => entry.length > 0)
    .join(', ');
}

function emptyBeneficiaryRow() {
  return { selection: '', name: '', percentage: 100 };
}

export default {
  name: 'PolicyFormModal',

  emits: ['save', 'close'],

  props: {
    policy: {
      type: Object,
      default: null,
    },
    isEditing: {
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
      submitting: false,
      familyMembers: [],
      beneficiaryRows: [emptyBeneficiaryRow()],
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
        joint_life: false,
        is_mortgage_protection: false,
        benefit_frequency: 'monthly',
        deferred_period_weeks: null,
        benefit_period_months: null,
        coverage_type: 'accident_and_sickness',
        notes: '',
      },
    };
  },

  computed: {
    ...mapState('aiFormFill', ['pendingFill', 'highlightedField', 'filling']),

    stageFormConfig() {
      return this.$store.getters['lifeStage/formFields']('protection') || {};
    },

    stageDefaultPolicyTypes() {
      return this.stageFormConfig.defaultPolicyTypes || [];
    },

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

    // Everyone on the account who can be named on the policy. The family members
    // are already loaded on mount; before this they were fetched and then never
    // offered, so a policy could only ever pay the linked spouse (W-0027).
    beneficiaryOptions() {
      const options = [];
      const seen = new Set();

      if (this.spouseOption?.name) {
        options.push({
          value: `linked_${this.spouseOption.id}`,
          label: `${this.spouseOption.name} (Spouse - Linked Account)`,
          name: this.spouseOption.name,
        });
        seen.add(this.spouseOption.name);
      }

      this.familyMembers.forEach(member => {
        const name = member.name || [member.first_name, member.last_name].filter(Boolean).join(' ');
        if (!name || seen.has(name)) return;
        seen.add(name);
        const relationship = (member.relationship || '').replace(/_/g, ' ');
        options.push({
          value: `member_${member.id}`,
          label: relationship ? `${name} (${relationship})` : name,
          name,
        });
      });

      return options;
    },

    beneficiaryTotal() {
      return this.beneficiaryRows.reduce((sum, row) => sum + (Number(row.percentage) || 0), 0);
    },
  },

  watch: {
    pendingFill: {
      handler(fill, previous) {
        if (fill && fill.entityType === 'protection_policy' && fill.fields) {
          // B-1 — when the modal stays mounted between queued fills
          // (multi-entity messages like "Aviva life £300k and Vitality
          // CI £100k"), we must reset the form data so the previous
          // entity's values (provider, coverage, dates) don't bleed
          // into the new one. Only reset if the previous fill was also
          // a protection_policy — the initial mount path leaves
          // formData at its data() defaults, so no reset needed.
          if (previous && previous.entityType === 'protection_policy') {
            Object.assign(this.formData, {
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
              joint_life: false,
              is_mortgage_protection: false,
              benefit_frequency: 'monthly',
              deferred_period_weeks: null,
              benefit_period_months: null,
              coverage_type: 'accident_and_sickness',
              notes: '',
            });
            this.beneficiaryRows = [emptyBeneficiaryRow()];
            this.errors = {};
          }

          // Set policyType immediately before the field sequence starts —
          // this controls which conditional fields are visible in the form
          if (fill.fields.policyType) {
            this.formData.policyType = fill.fields.policyType;
          }
          if (fill.fields.life_policy_type) {
            this.formData.life_policy_type = fill.fields.life_policy_type;
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
      if (isFilling === false && this.pendingFill?.entityType === 'protection_policy') {
        setTimeout(() => {
          this.$nextTick(() => {
            this.handleSubmit();
            // If validation failed, report to chat
            if (this.errors && Object.keys(this.errors).length > 0) {
              const errorList = Object.values(this.errors).join(', ');
              this.$store.commit('aiChat/ADD_MESSAGE', {
                id: 'fill_error_' + Date.now(),
                role: 'assistant',
                content: `I wasn't able to save the policy — the form has validation errors: ${errorList}. Please check the form and try again.`,
                created_at: new Date().toISOString(),
              }, { root: true });
              this.$store.dispatch('aiFormFill/cancelFill');
            }
          });
        }, 500);
      }
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
      // Preview users are real DB users - use normal API to fetch their data
      try {
        const familyMembersService = (await import('@/services/familyMembersService')).default;
        const response = await familyMembersService.getFamilyMembers();
        this.familyMembers = response.data?.family_members || [];
      } catch (error) {
        logger.error('Error loading family members:', error);
        this.familyMembers = [];
      }
    },

    handleBeneficiarySelection(index) {
      const row = this.beneficiaryRows[index];
      if (!row) return;

      const option = this.beneficiaryOptions.find(o => o.value === row.selection);
      // Anyone on the account carries their own name; "Someone else" is typed in.
      row.name = option ? option.name : '';
    },

    addBeneficiary() {
      const remaining = Math.max(0, 100 - this.beneficiaryTotal);
      this.beneficiaryRows.push({ ...emptyBeneficiaryRow(), percentage: remaining });
    },

    removeBeneficiary(index) {
      this.beneficiaryRows.splice(index, 1);
      if (this.beneficiaryRows.length === 0) {
        this.beneficiaryRows.push(emptyBeneficiaryRow());
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
      const parsed = parseBeneficiaries(this.policy.beneficiaries);
      this.beneficiaryRows = parsed.length
        ? parsed.map(row => {
          const option = this.beneficiaryOptions.find(o => o.name === row.name);
          return {
            selection: option ? option.value : 'other',
            name: row.name,
            percentage: row.percentage,
          };
        })
        : [emptyBeneficiaryRow()];

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
        joint_life: this.policy.joint_life || false,
        is_mortgage_protection: this.policy.is_mortgage_protection || false,
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
        logger.error('[PolicyFormModal] handleSubmit error:', error);
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
        // The dates every policy type carries, assigned once. The three branches
        // below each used to set them, and disagreed about nulling (W-0026).
        policy_start_date: this.formData.start_date || null,
        policy_end_date: this.formData.end_date || null,
        policy_term_years: this.formData.term_years || null,
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

        data.in_trust = this.formData.in_trust || false;
        data.joint_life = this.formData.joint_life || false;
        data.is_mortgage_protection = this.formData.is_mortgage_protection || false;
        data.beneficiaries = serialiseBeneficiaries(this.beneficiaryRows) || null;
      } else if (type === 'criticalIllness') {
        data.policy_type = 'standalone'; // Default to standalone critical illness
        data.sum_assured = this.formData.coverage_amount;
        data.conditions_covered = []; // Empty array for conditions covered
      } else {
        data.benefit_amount = this.formData.coverage_amount;
        data.benefit_frequency = this.formData.benefit_frequency;
        data.benefit_period_months = this.formData.benefit_period_months;
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
      if (this.pendingFill) {
        this.$store.dispatch('aiFormFill/cancelFill');
      }
      this.$emit('close');
    },
  },
};
</script>

