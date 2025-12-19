<template>
  <div>
    <div class="mb-6 flex justify-between items-start">
      <div>
        <h2 class="text-h4 font-semibold text-gray-900">Income & Occupation</h2>
        <p class="mt-1 text-body-sm text-gray-600">
          Update your employment and income information
        </p>
      </div>
      <button
        v-if="!isEditing"
        type="button"
        @click="isEditing = true"
        class="btn-primary"
      >
        Edit Information
      </button>
    </div>

    <!-- Success Message -->
    <div v-if="successMessage" class="rounded-md bg-success-50 p-4 mb-6">
      <div class="flex">
        <div class="ml-3">
          <p class="text-body-sm font-medium text-success-800">
            {{ successMessage }}
          </p>
        </div>
      </div>
    </div>

    <!-- Error Message -->
    <div v-if="errorMessage" class="rounded-md bg-error-50 p-4 mb-6">
      <div class="flex">
        <div class="ml-3">
          <h3 class="text-body-sm font-medium text-error-800">Error updating information</h3>
          <div class="mt-2 text-body-sm text-error-700">
            <p>{{ errorMessage }}</p>
          </div>
        </div>
      </div>
    </div>

    <form @submit.prevent="handleSubmit" class="space-y-6">
      <!-- Employment Information -->
      <div class="card p-6">
        <h3 class="text-h5 font-semibold text-gray-900 mb-4">Employment Information</h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- Occupation -->
          <div>
            <label for="occupation" class="block text-body-sm font-medium text-gray-700 mb-1">
              Occupation
            </label>
            <input
              id="occupation"
              v-model="form.occupation"
              type="text"
              class="input-field"
              :disabled="!isEditing"
              placeholder="e.g., Software Engineer"
            />
          </div>

          <!-- Employer -->
          <div>
            <label for="employer" class="block text-body-sm font-medium text-gray-700 mb-1">
              Employer
            </label>
            <input
              id="employer"
              v-model="form.employer"
              type="text"
              class="input-field"
              :disabled="!isEditing"
              placeholder="e.g., Tech Corp Ltd"
            />
          </div>

          <!-- Industry -->
          <div>
            <label for="industry" class="block text-body-sm font-medium text-gray-700 mb-1">
              Industry
            </label>
            <input
              id="industry"
              v-model="form.industry"
              type="text"
              class="input-field"
              :disabled="!isEditing"
              placeholder="e.g., Technology"
            />
          </div>

          <!-- Employment Status -->
          <div>
            <label for="employment_status" class="block text-body-sm font-medium text-gray-700 mb-1">
              Employment Status
            </label>
            <select
              id="employment_status"
              v-model="form.employment_status"
              class="input-field"
              :disabled="!isEditing"
            >
              <option value="">Select status</option>
              <option value="employed">Employed</option>
              <option value="part_time">Part-Time</option>
              <option value="self_employed">Self-Employed</option>
              <option value="retired">Retired</option>
              <option value="unemployed">Unemployed</option>
              <option value="other">Other</option>
            </select>
          </div>

          <!-- Target Retirement Age (for non-retired) -->
          <div v-if="form.employment_status && form.employment_status !== 'retired'">
            <label for="target_retirement_age" class="block text-body-sm font-medium text-gray-700 mb-1">
              What age do you want to retire?
            </label>
            <input
              id="target_retirement_age"
              v-model.number="form.target_retirement_age"
              type="number"
              min="55"
              max="75"
              class="input-field"
              :disabled="!isEditing"
              placeholder="65"
            />
            <p class="mt-1 text-body-xs text-gray-500">Your planned retirement age (minimum 55)</p>
          </div>

          <!-- Retirement Date (for retired users) -->
          <div v-if="form.employment_status === 'retired'">
            <label for="retirement_date" class="block text-body-sm font-medium text-gray-700 mb-1">
              When did you retire?
            </label>
            <input
              id="retirement_date"
              v-model="form.retirement_date"
              type="date"
              :max="today"
              class="input-field"
              :disabled="!isEditing"
            />
            <p class="mt-1 text-body-xs text-gray-500">The date you retired from work</p>
          </div>
        </div>

        <!-- Early Retirement Warning -->
        <div
          v-if="showEarlyRetirementWarning"
          class="mt-4 bg-amber-50 border border-amber-200 rounded-lg p-4"
        >
          <div class="flex">
            <svg class="h-5 w-5 text-amber-400 mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
            </svg>
            <p class="text-body-sm text-amber-800">
              <strong>Early Retirement:</strong> In most circumstances you are only able to access retirement benefits from the age of 55. You retired at age {{ retirementAge }}.
            </p>
          </div>
        </div>
      </div>

      <!-- Income Information -->
      <div class="card p-6">
        <h3 class="text-h5 font-semibold text-gray-900 mb-4">Annual Income</h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- Annual Employment Income -->
          <div v-if="isEditing || form.annual_employment_income > 0">
            <label for="annual_employment_income" class="block text-body-sm font-medium text-gray-700 mb-1">
              Employment Income (Full-Time/Part-Time)
            </label>
            <div class="relative rounded-md shadow-sm">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <span class="text-gray-500 sm:text-sm">£</span>
              </div>
              <input
                v-if="isEditing"
                id="annual_employment_income"
                v-model.number="form.annual_employment_income"
                type="number"
                step="0.01"
                min="0"
                class="input-field pl-7"
                placeholder="0.00"
              />
              <input
                v-else
                :value="formatNumber(form.annual_employment_income)"
                type="text"
                class="input-field pl-7 bg-gray-50"
                disabled
              />
            </div>
          </div>

          <!-- Annual Self-Employment Income -->
          <div v-if="isEditing || form.annual_self_employment_income > 0">
            <label for="annual_self_employment_income" class="block text-body-sm font-medium text-gray-700 mb-1">
              Self-Employment Income
            </label>
            <div class="relative rounded-md shadow-sm">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <span class="text-gray-500 sm:text-sm">£</span>
              </div>
              <input
                v-if="isEditing"
                id="annual_self_employment_income"
                v-model.number="form.annual_self_employment_income"
                type="number"
                step="0.01"
                min="0"
                class="input-field pl-7"
                placeholder="0.00"
              />
              <input
                v-else
                :value="formatNumber(form.annual_self_employment_income)"
                type="text"
                class="input-field pl-7 bg-gray-50"
                disabled
              />
            </div>
          </div>

          <!-- Annual Rental Income (Auto-calculated from Properties) -->
          <div v-if="form.annual_rental_income > 0">
            <label for="annual_rental_income" class="block text-body-sm font-medium text-gray-700 mb-1">
              Rental Income
            </label>
            <div class="relative rounded-md shadow-sm">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <span class="text-gray-500 sm:text-sm">£</span>
              </div>
              <input
                :value="formatNumber(form.annual_rental_income)"
                type="text"
                class="input-field pl-7 bg-gray-50"
                disabled
              />
            </div>
            <p class="mt-1 text-body-xs text-gray-500">Automatically calculated from your properties</p>
          </div>

          <!-- Annual Dividend Income -->
          <div v-if="isEditing || form.annual_dividend_income > 0">
            <label for="annual_dividend_income" class="block text-body-sm font-medium text-gray-700 mb-1">
              Dividend Income
            </label>
            <div class="relative rounded-md shadow-sm">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <span class="text-gray-500 sm:text-sm">£</span>
              </div>
              <input
                v-if="isEditing"
                id="annual_dividend_income"
                v-model.number="form.annual_dividend_income"
                type="number"
                step="0.01"
                min="0"
                class="input-field pl-7"
                placeholder="0.00"
              />
              <input
                v-else
                :value="formatNumber(form.annual_dividend_income)"
                type="text"
                class="input-field pl-7 bg-gray-50"
                disabled
              />
            </div>
          </div>

          <!-- Annual Interest Income -->
          <div v-if="isEditing || form.annual_interest_income > 0">
            <label for="annual_interest_income" class="block text-body-sm font-medium text-gray-700 mb-1">
              Interest Income
            </label>
            <div class="relative rounded-md shadow-sm">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <span class="text-gray-500 sm:text-sm">£</span>
              </div>
              <input
                v-if="isEditing"
                id="annual_interest_income"
                v-model.number="form.annual_interest_income"
                type="number"
                step="0.01"
                min="0"
                class="input-field pl-7"
                placeholder="0.00"
              />
              <input
                v-else
                :value="formatNumber(form.annual_interest_income)"
                type="text"
                class="input-field pl-7 bg-gray-50"
                disabled
              />
            </div>
          </div>

          <!-- Annual Pension Income (Auto-calculated from Retirement module) -->
          <div v-if="form.annual_pension_income > 0">
            <label for="annual_pension_income" class="block text-body-sm font-medium text-gray-700 mb-1">
              Pension Income
            </label>
            <div class="relative rounded-md shadow-sm">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <span class="text-gray-500 sm:text-sm">£</span>
              </div>
              <input
                :value="formatNumber(form.annual_pension_income)"
                type="text"
                class="input-field pl-7 bg-gray-50"
                disabled
              />
            </div>
            <p class="mt-1 text-body-xs text-gray-500">Calculated from DB pensions and state pension in payment</p>
          </div>

          <!-- Annual Trust Income -->
          <div v-if="isEditing || form.annual_trust_income > 0">
            <label for="annual_trust_income" class="block text-body-sm font-medium text-gray-700 mb-1">
              Trust Income
            </label>
            <div class="relative rounded-md shadow-sm">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <span class="text-gray-500 sm:text-sm">£</span>
              </div>
              <input
                v-if="isEditing"
                id="annual_trust_income"
                v-model.number="form.annual_trust_income"
                type="number"
                step="0.01"
                min="0"
                class="input-field pl-7"
                placeholder="0.00"
              />
              <input
                v-else
                :value="formatNumber(form.annual_trust_income)"
                type="text"
                class="input-field pl-7 bg-gray-50"
                disabled
              />
            </div>
            <p v-if="isEditing" class="mt-1 text-body-xs text-gray-500">Income received from trusts (taxable)</p>
          </div>

          <!-- Total Annual Income (Calculated) - Always show -->
          <div>
            <label class="block text-body-sm font-medium text-gray-700 mb-1">
              Total Annual Income
            </label>
            <div class="relative rounded-md shadow-sm">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <span class="text-gray-500 sm:text-sm">£</span>
              </div>
              <input
                :value="totalIncome"
                type="text"
                class="input-field pl-7 bg-gray-50"
                disabled
              />
            </div>
          </div>
        </div>
      </div>

      <!-- Tax Calculations -->
      <div v-if="detailedTaxBreakdown?.summary" class="card p-6">
        <h3 class="text-h5 font-semibold text-gray-900 mb-4">UK Tax & NI Calculations</h3>

        <!-- Summary Card -->
        <TaxSummaryCard
          :summary="{
            ...detailedTaxBreakdown.summary,
            tax_year: detailedTaxBreakdown.tax_year
          }"
          class="mb-6"
        />

        <!-- Income Type Cards -->
        <div
          v-if="detailedTaxBreakdown.income_breakdowns?.length > 0"
          class="grid grid-cols-1 md:grid-cols-2 gap-4"
        >
          <TaxIncomeCard
            v-for="(breakdown, index) in detailedTaxBreakdown.income_breakdowns"
            :key="breakdown.income_type + '-' + index"
            :breakdown="breakdown"
          />
        </div>

        <!-- Info Note -->
        <div class="mt-4 p-3 bg-blue-100 rounded-lg">
          <p class="text-body-xs text-blue-800">
            <strong>Note:</strong> Tax calculations use {{ detailedTaxBreakdown.tax_year }} UK tax rates.
            Income is taxed in priority order: employment income uses the Personal Allowance first,
            with other income types taxed at remaining band positions.
          </p>
        </div>
      </div>

      <!-- Action Buttons -->
      <div v-if="isEditing" class="flex justify-end space-x-4">
          <button
            type="button"
            @click="handleCancel"
            class="btn-secondary"
            :disabled="submitting"
          >
            Cancel
          </button>
          <button
            type="submit"
            class="btn-primary"
            :disabled="submitting"
          >
            <span v-if="!submitting">Save Changes</span>
            <span v-else>Saving...</span>
          </button>
      </div>
    </form>
  </div>
</template>

<script>
import { ref, computed, watch } from 'vue';
import { useStore } from 'vuex';
import TaxIncomeCard from './TaxIncomeCard.vue';
import TaxSummaryCard from './TaxSummaryCard.vue';

export default {
  name: 'IncomeOccupation',

  components: {
    TaxIncomeCard,
    TaxSummaryCard,
  },

  setup() {
    const store = useStore();
    const isEditing = ref(false);
    const submitting = ref(false);
    const successMessage = ref('');
    const errorMessage = ref('');

    const incomeOccupation = computed(() => store.getters['userProfile/incomeOccupation']);
    const detailedTaxBreakdown = computed(() => incomeOccupation.value?.detailed_tax_breakdown || null);

    const form = ref({
      occupation: '',
      employer: '',
      industry: '',
      employment_status: '',
      target_retirement_age: null,
      retirement_date: '',
      annual_employment_income: 0,
      annual_self_employment_income: 0,
      annual_rental_income: 0,
      annual_dividend_income: 0,
      annual_interest_income: 0,
      annual_pension_income: 0,
      annual_trust_income: 0,
    });

    const today = computed(() => {
      return new Date().toISOString().split('T')[0];
    });

    const totalIncome = computed(() => {
      const total =
        (form.value.annual_employment_income || 0) +
        (form.value.annual_self_employment_income || 0) +
        (form.value.annual_rental_income || 0) +
        (form.value.annual_dividend_income || 0) +
        (form.value.annual_interest_income || 0) +
        (form.value.annual_pension_income || 0) +
        (form.value.annual_trust_income || 0);

      return new Intl.NumberFormat('en-GB', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
      }).format(total);
    });

    const retirementAge = computed(() => {
      if (!form.value.retirement_date) return null;

      const currentUser = store.getters['auth/currentUser'];
      if (!currentUser?.date_of_birth) return null;

      const birthDate = new Date(currentUser.date_of_birth);
      const retireDate = new Date(form.value.retirement_date);

      let age = retireDate.getFullYear() - birthDate.getFullYear();
      const monthDiff = retireDate.getMonth() - birthDate.getMonth();

      if (monthDiff < 0 || (monthDiff === 0 && retireDate.getDate() < birthDate.getDate())) {
        age--;
      }

      return age;
    });

    const showEarlyRetirementWarning = computed(() => {
      return form.value.employment_status === 'retired' &&
             retirementAge.value !== null &&
             retirementAge.value < 55;
    });

    // Initialize form from incomeOccupation
    const initializeForm = () => {
      if (incomeOccupation.value) {
        form.value = {
          occupation: incomeOccupation.value.occupation || '',
          employer: incomeOccupation.value.employer || '',
          industry: incomeOccupation.value.industry || '',
          employment_status: incomeOccupation.value.employment_status || '',
          target_retirement_age: incomeOccupation.value.target_retirement_age || null,
          retirement_date: incomeOccupation.value.retirement_date || '',
          annual_employment_income: Number(incomeOccupation.value.annual_employment_income) || 0,
          annual_self_employment_income: Number(incomeOccupation.value.annual_self_employment_income) || 0,
          annual_rental_income: Number(incomeOccupation.value.annual_rental_income) || 0,
          annual_dividend_income: Number(incomeOccupation.value.annual_dividend_income) || 0,
          annual_interest_income: Number(incomeOccupation.value.annual_interest_income) || 0,
          annual_pension_income: Number(incomeOccupation.value.annual_pension_income) || 0,
          annual_trust_income: Number(incomeOccupation.value.annual_trust_income) || 0,
        };
      }
    };

    // Watch for changes in incomeOccupation and reinitialize form
    watch(incomeOccupation, () => {
      initializeForm();
    }, { immediate: true });

    const handleSubmit = async () => {
      submitting.value = true;
      successMessage.value = '';
      errorMessage.value = '';

      try {
        // Clean up form data: convert empty strings to null for optional fields
        const cleanedData = Object.entries(form.value).reduce((acc, [key, value]) => {
          // Convert empty strings to null
          if (value === '') {
            acc[key] = null;
          } else {
            acc[key] = value;
          }
          return acc;
        }, {});

        await store.dispatch('userProfile/updateIncomeOccupation', cleanedData);
        successMessage.value = 'Income and occupation information updated successfully!';
        isEditing.value = false;

        // Trigger protection analysis refresh if user has protection module data
        // Income changes affect protection needs calculation
        try {
          await store.dispatch('protection/fetchProtectionData');
        } catch (protectionError) {
          // Silently fail - user might not have protection module set up yet
        }

        // Clear success message after 3 seconds
        setTimeout(() => {
          successMessage.value = '';
        }, 3000);
      } catch (error) {
        console.error('Update error:', error);
        // Show validation errors if available
        if (error.errors) {
          const errors = Object.values(error.errors).flat();
          errorMessage.value = errors.join('. ');
        } else {
          errorMessage.value = error.message || 'Failed to update income and occupation';
        }
      } finally {
        submitting.value = false;
      }
    };

    const handleCancel = () => {
      initializeForm();
      isEditing.value = false;
      errorMessage.value = '';
    };

    const formatCurrency = (amount) => {
      return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: 'GBP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
      }).format(amount || 0);
    };

    const formatNumber = (amount) => {
      return new Intl.NumberFormat('en-GB', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
      }).format(amount || 0);
    };

    return {
      form,
      isEditing,
      submitting,
      successMessage,
      errorMessage,
      today,
      totalIncome,
      retirementAge,
      showEarlyRetirementWarning,
      incomeOccupation,
      detailedTaxBreakdown,
      handleSubmit,
      handleCancel,
      formatCurrency,
      formatNumber,
    };
  },
};
</script>
