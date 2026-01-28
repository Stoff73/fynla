<template>
  <div class="space-y-6">
    <!-- Success Message -->
    <div v-if="successMessage" class="rounded-md bg-success-50 p-4">
      <div class="flex">
        <div class="ml-3">
          <p class="text-body-sm font-medium text-success-800">
            {{ successMessage }}
          </p>
        </div>
      </div>
    </div>

    <!-- Error Message -->
    <div v-if="errorMessage" class="rounded-md bg-error-50 p-4">
      <div class="flex">
        <div class="ml-3">
          <h3 class="text-body-sm font-medium text-error-800">Error updating information</h3>
          <div class="mt-2 text-body-sm text-error-700">
            <p>{{ errorMessage }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Income Needs Update Banner -->
    <div v-if="incomeNeedsUpdate" class="rounded-md bg-amber-50 border border-amber-200 p-4">
      <div class="flex">
        <div class="flex-shrink-0">
          <svg class="h-5 w-5 text-amber-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
          </svg>
        </div>
        <div class="ml-3">
          <h3 class="text-body-sm font-medium text-amber-800">Employment Status Changed</h3>
          <div class="mt-1 text-body-sm text-amber-700">
            <p>
              You recently changed your employment status{{ previousStatusLabel ? ` from ${previousStatusLabel}` : '' }}.
              Please update your income below to reflect your current earnings.
            </p>
          </div>
          <div class="mt-2">
            <button
              type="button"
              @click="isEditing = true"
              class="text-body-sm font-medium text-amber-800 underline hover:text-amber-900"
            >
              Update income now
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Income and Tax Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Income Information Card -->
      <form @submit.prevent="handleSubmit">
        <div class="bg-white rounded-lg border border-gray-200 p-6 h-full">
          <div class="flex justify-between items-start mb-6">
            <div>
              <h3 class="text-h4 font-semibold text-gray-900">Income</h3>
              <p class="mt-1 text-body-sm text-gray-600">
                Your annual income from all sources
              </p>
            </div>
            <button
              v-if="!isEditing"
              type="button"
              @click="isEditing = true"
              class="btn-secondary"
            >
              Edit
            </button>
          </div>

          <!-- VIEW MODE -->
          <div v-if="!isEditing">
            <div class="space-y-3">
              <div class="flex justify-between">
                <span class="text-body-sm text-gray-600">Employment Income:</span>
                <span class="text-body-sm text-gray-900 text-right">{{ formatCurrency(form.annual_employment_income) }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-body-sm text-gray-600">Self-Employment Income:</span>
                <span class="text-body-sm text-gray-900 text-right">{{ formatCurrency(form.annual_self_employment_income) }}</span>
              </div>
              <div v-if="form.annual_rental_income > 0" class="flex justify-between">
                <span class="text-body-sm text-gray-600">Rental Income:</span>
                <span class="text-body-sm text-gray-900 text-right">{{ formatCurrency(form.annual_rental_income) }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-body-sm text-gray-600">Dividend Income:</span>
                <span class="text-body-sm text-gray-900 text-right">{{ formatCurrency(form.annual_dividend_income) }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-body-sm text-gray-600">Interest Income:</span>
                <span class="text-body-sm text-gray-900 text-right">{{ formatCurrency(form.annual_interest_income) }}</span>
              </div>
              <div v-if="form.annual_pension_income > 0" class="flex justify-between">
                <span class="text-body-sm text-gray-600">Pension Income:</span>
                <span class="text-body-sm text-gray-900 text-right">{{ formatCurrency(form.annual_pension_income) }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-body-sm text-gray-600">Trust Income:</span>
                <span class="text-body-sm text-gray-900 text-right">{{ formatCurrency(form.annual_trust_income) }}</span>
              </div>
            </div>

            <!-- Total Annual Income -->
            <div class="mt-6 pt-4 border-t border-gray-200">
              <div class="flex justify-between items-center">
                <span class="text-body-sm font-semibold text-gray-900">Total Annual Income:</span>
                <span class="text-h4 font-semibold text-gray-900">{{ formatCurrency(totalIncomeValue) }}</span>
              </div>
            </div>

            <!-- Disposable Income Section -->
            <div v-if="incomeOccupation?.net_income" class="mt-4 pt-4 border-t border-gray-200 space-y-3">
              <div class="flex justify-between">
                <span class="text-body-sm text-gray-600">Net Income (after tax):</span>
                <span class="text-body-sm text-gray-900 text-right">{{ formatCurrency(incomeOccupation.net_income) }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-body-sm text-gray-600">Annual Expenditure:</span>
                <span class="text-body-sm text-gray-900 text-right">{{ formatCurrency(totalAnnualExpenditure) }}</span>
              </div>
              <div class="flex justify-between items-center pt-2 border-t border-gray-100">
                <span class="text-body-sm font-semibold" :class="disposableIncome >= 0 ? 'text-green-700' : 'text-red-700'">Disposable Income:</span>
                <span class="text-body font-semibold" :class="disposableIncome >= 0 ? 'text-green-700' : 'text-red-700'">{{ formatCurrency(disposableIncome) }}</span>
              </div>
            </div>
          </div>

          <!-- EDIT MODE -->
          <div v-else class="space-y-4">
            <!-- Annual Employment Income -->
            <div>
              <label class="block text-body-sm font-medium text-gray-700 mb-1">
                Employment Income
              </label>
              <div class="relative rounded-md shadow-sm">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <span class="text-gray-500 sm:text-sm">£</span>
                </div>
                <input
                  id="annual_employment_income"
                  v-model.number="form.annual_employment_income"
                  type="number"
                  step="0.01"
                  min="0"
                  class="input-field pl-7"
                  placeholder="0.00"
                />
              </div>
            </div>

            <!-- Annual Self-Employment Income -->
            <div>
              <label class="block text-body-sm font-medium text-gray-700 mb-1">
                Self-Employment Income
              </label>
              <div class="relative rounded-md shadow-sm">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <span class="text-gray-500 sm:text-sm">£</span>
                </div>
                <input
                  id="annual_self_employment_income"
                  v-model.number="form.annual_self_employment_income"
                  type="number"
                  step="0.01"
                  min="0"
                  class="input-field pl-7"
                  placeholder="0.00"
                />
              </div>
            </div>

            <!-- Annual Rental Income (Auto-calculated from Properties) -->
            <div v-if="form.annual_rental_income > 0">
              <label class="block text-body-sm font-medium text-gray-700 mb-1">
                Rental Income
              </label>
              <p class="text-body-base text-gray-900 py-2">{{ formatCurrency(form.annual_rental_income) }}</p>
              <p class="text-body-xs text-gray-500">Automatically calculated from your properties</p>
            </div>

            <!-- Annual Dividend Income -->
            <div>
              <label class="block text-body-sm font-medium text-gray-700 mb-1">
                Dividend Income
              </label>
              <div class="relative rounded-md shadow-sm">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <span class="text-gray-500 sm:text-sm">£</span>
                </div>
                <input
                  id="annual_dividend_income"
                  v-model.number="form.annual_dividend_income"
                  type="number"
                  step="0.01"
                  min="0"
                  class="input-field pl-7"
                  placeholder="0.00"
                />
              </div>
            </div>

            <!-- Annual Interest Income -->
            <div>
              <label class="block text-body-sm font-medium text-gray-700 mb-1">
                Interest Income
              </label>
              <div class="relative rounded-md shadow-sm">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <span class="text-gray-500 sm:text-sm">£</span>
                </div>
                <input
                  id="annual_interest_income"
                  v-model.number="form.annual_interest_income"
                  type="number"
                  step="0.01"
                  min="0"
                  class="input-field pl-7"
                  placeholder="0.00"
                />
              </div>
            </div>

            <!-- Annual Pension Income (Auto-calculated from Retirement module) -->
            <div v-if="form.annual_pension_income > 0">
              <label class="block text-body-sm font-medium text-gray-700 mb-1">
                Pension Income
              </label>
              <p class="text-body-base text-gray-900 py-2">{{ formatCurrency(form.annual_pension_income) }}</p>
              <p class="text-body-xs text-gray-500">Calculated from DB pensions and state pension in payment</p>
            </div>

            <!-- Annual Trust Income -->
            <div>
              <label class="block text-body-sm font-medium text-gray-700 mb-1">
                Trust Income
              </label>
              <div class="relative rounded-md shadow-sm">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <span class="text-gray-500 sm:text-sm">£</span>
                </div>
                <input
                  id="annual_trust_income"
                  v-model.number="form.annual_trust_income"
                  type="number"
                  step="0.01"
                  min="0"
                  class="input-field pl-7"
                  placeholder="0.00"
                />
              </div>
              <p class="text-body-xs text-gray-500">Income received from trusts (taxable)</p>
            </div>

            <!-- Total Annual Income -->
            <div class="pt-4 border-t border-gray-200">
              <div class="flex justify-between items-center">
                <span class="text-body-sm font-semibold text-gray-900">Total Annual Income:</span>
                <span class="text-h4 font-semibold text-gray-900">{{ formatCurrency(totalIncomeValue) }}</span>
              </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex justify-end space-x-4 pt-4 border-t border-gray-200">
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
          </div>
        </div>
      </form>

      <!-- Tax Calculations Card -->
      <div v-if="detailedTaxBreakdown?.summary" class="bg-white rounded-lg border border-gray-200 p-6 h-full">
        <h3 class="text-h4 font-semibold text-gray-900 mb-4">Tax & NI</h3>

        <!-- Income Type Cards -->
        <div
          v-if="detailedTaxBreakdown.income_breakdowns?.length > 0"
          class="space-y-4"
        >
          <TaxIncomeCard
            v-for="(breakdown, index) in detailedTaxBreakdown.income_breakdowns"
            :key="breakdown.income_type + '-' + index"
            :breakdown="breakdown"
            :rental-breakdown="breakdown.income_type === 'earned' ? rentalBreakdown : null"
            :section24="breakdown.income_type === 'earned' ? detailedTaxBreakdown.section_24 : null"
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
    </div>
  </div>
</template>

<script>
import { ref, computed, watch } from 'vue';
import { useStore } from 'vuex';
import TaxIncomeCard from './TaxIncomeCard.vue';

export default {
  name: 'IncomeOccupation',

  components: {
    TaxIncomeCard,
  },

  setup() {
    const store = useStore();
    const isEditing = ref(false);
    const submitting = ref(false);
    const successMessage = ref('');
    const errorMessage = ref('');

    const incomeOccupation = computed(() => store.getters['userProfile/incomeOccupation']);
    const detailedTaxBreakdown = computed(() => incomeOccupation.value?.detailed_tax_breakdown || null);
    const rentalBreakdown = computed(() => incomeOccupation.value?.rental_breakdown || null);

    // Check if income needs updating due to employment status change
    const incomeNeedsUpdate = computed(() => incomeOccupation.value?.income_needs_update || false);
    const previousEmploymentStatus = computed(() => incomeOccupation.value?.previous_employment_status || null);

    // Format previous status for display
    const previousStatusLabel = computed(() => {
      const statusMap = {
        'employed': 'Employed',
        'part_time': 'Part-Time',
        'self_employed': 'Self-Employed',
        'retired': 'Retired',
        'unemployed': 'Unemployed',
        'other': 'Other',
      };
      return previousEmploymentStatus.value ? statusMap[previousEmploymentStatus.value] || previousEmploymentStatus.value : null;
    });

    const form = ref({
      annual_employment_income: 0,
      annual_self_employment_income: 0,
      annual_rental_income: 0,
      annual_dividend_income: 0,
      annual_interest_income: 0,
      annual_pension_income: 0,
      annual_trust_income: 0,
    });

    const totalIncomeValue = computed(() => {
      return (form.value.annual_employment_income || 0) +
        (form.value.annual_self_employment_income || 0) +
        (form.value.annual_rental_income || 0) +
        (form.value.annual_dividend_income || 0) +
        (form.value.annual_interest_income || 0) +
        (form.value.annual_pension_income || 0) +
        (form.value.annual_trust_income || 0);
    });

    // Use saved expenditure directly (Expenditure form saves total including commitments)
    const totalMonthlyExpenditure = computed(() => {
      return Number(incomeOccupation.value?.monthly_expenditure || 0);
    });

    const totalAnnualExpenditure = computed(() => {
      return Number(incomeOccupation.value?.annual_expenditure || 0) ||
             totalMonthlyExpenditure.value * 12;
    });

    const disposableIncome = computed(() => {
      if (!incomeOccupation.value) return 0;
      const netIncome = incomeOccupation.value.net_income || 0;
      return netIncome - totalAnnualExpenditure.value;
    });

    const monthlyDisposable = computed(() => {
      return disposableIncome.value / 12;
    });

    const disposableIncomeClass = computed(() => {
      return disposableIncome.value >= 0 ? 'bg-green-50' : 'bg-red-50';
    });

    // Initialize form from incomeOccupation
    const initializeForm = () => {
      if (incomeOccupation.value) {
        form.value = {
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
      if (!isEditing.value) {
        initializeForm();
      }
    }, { immediate: true });

    const handleSubmit = async () => {
      submitting.value = true;
      successMessage.value = '';
      errorMessage.value = '';

      try {
        // Preserve existing occupation values when updating income
        const updateData = {
          // Preserve occupation fields
          occupation: incomeOccupation.value?.occupation || null,
          employer: incomeOccupation.value?.employer || null,
          industry: incomeOccupation.value?.industry || null,
          employment_status: incomeOccupation.value?.employment_status || null,
          target_retirement_age: incomeOccupation.value?.target_retirement_age || null,
          retirement_date: incomeOccupation.value?.retirement_date || null,
          // Update income fields
          annual_employment_income: form.value.annual_employment_income || 0,
          annual_self_employment_income: form.value.annual_self_employment_income || 0,
          annual_dividend_income: form.value.annual_dividend_income || 0,
          annual_interest_income: form.value.annual_interest_income || 0,
          annual_trust_income: form.value.annual_trust_income || 0,
          // Clear the income needs update flag since user is updating their income
          income_needs_update: false,
          previous_employment_status: null,
        };

        await store.dispatch('userProfile/updateIncomeOccupation', updateData);

        // Refresh profile data to get updated tax calculations
        await store.dispatch('userProfile/fetchProfile');

        successMessage.value = 'Income information updated successfully!';
        isEditing.value = false;

        // Trigger protection analysis refresh if user has protection module data
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
        if (error.errors) {
          const errors = Object.values(error.errors).flat();
          errorMessage.value = errors.join('. ');
        } else {
          errorMessage.value = error.message || 'Failed to update income information';
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

    return {
      form,
      isEditing,
      submitting,
      successMessage,
      errorMessage,
      totalIncomeValue,
      incomeOccupation,
      detailedTaxBreakdown,
      rentalBreakdown,
      totalMonthlyExpenditure,
      totalAnnualExpenditure,
      disposableIncome,
      monthlyDisposable,
      disposableIncomeClass,
      incomeNeedsUpdate,
      previousStatusLabel,
      handleSubmit,
      handleCancel,
      formatCurrency,
    };
  },
};
</script>
