<template>
  <OnboardingStep
    title="Employment & Income"
    description="Your income and employment details help us understand your financial position"
    :can-go-back="true"
    :can-skip="false"
    :loading="loading"
    :error="error"
    @next="handleNext"
    @back="handleBack"
  >
    <div class="space-y-6">
      <!-- Employment Details Section -->
      <div class="border-t pt-4">
        <h4 class="text-body font-medium text-horizon-500 mb-4">
          Employment Details
        </h4>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label for="employment_status" class="label">
              Employment Status <span class="q-icon" @click="emitWhyField($event,'employment_status')" title="Why we ask this">?</span>
            </label>
            <select
              id="employment_status"
              v-model="formData.employment_status"
              class="input-field"
              @focus="emitWhyField($event,'employment_status')"
            >
              <option value="">Select status</option>
              <option value="employed">Employed</option>
              <option value="part_time">Part-Time</option>
              <option value="self_employed">Self-Employed</option>
              <option value="unemployed">Unemployed</option>
              <option value="retired">Retired</option>
              <option value="other">Other</option>
            </select>
          </div>

          <template v-if="showEmploymentFields">
            <div>
              <label for="occupation" class="label">
                Occupation <span class="q-icon" @click="emitWhyField($event,'occupation')" title="Why we ask this">?</span>
              </label>
              <OccupationAutocomplete
                id="occupation"
                v-model="formData.occupation"
                placeholder="Start typing your job title..."
                :show-hint="true"
              />
            </div>

            <div>
              <label for="employer" class="label">
                Employer <span class="q-icon" @click="emitWhyField($event,'employer')" title="Why we ask this">?</span>
              </label>
              <input
                id="employer"
                v-model="formData.employer"
                type="text"
                class="input-field"
                placeholder="Tech Company Ltd"
                @focus="emitWhyField($event,'employer')"
              >
            </div>

            <div>
              <label for="industry" class="label">
                Industry <span class="q-icon" @click="emitWhyField($event,'industry')" title="Why we ask this">?</span>
              </label>
              <input
                id="industry"
                v-model="formData.industry"
                type="text"
                class="input-field"
                placeholder="Technology"
                @focus="emitWhyField($event,'industry')"
              >
            </div>

            <!-- Retirement Age (for non-retired) -->
            <div v-if="formData.employment_status && formData.employment_status !== 'retired'">
              <label for="target_retirement_age" class="label">
                Retirement Age <span class="q-icon" @click="emitWhyField($event,'target_retirement_age')" title="Why we ask this">?</span>
              </label>
              <input
                id="target_retirement_age"
                v-model.number="formData.target_retirement_age"
                type="number"
                min="30"
                max="75"
                class="input-field"
                placeholder="65"
                @focus="emitWhyField($event,'target_retirement_age')"
              >
              <p class="mt-1 text-body-sm text-neutral-500">
                Planned retirement age, used for all pension forecast calculations. Use the <a :href="LINKS.HMRC_TAX_CALC" target="_blank" rel="noopener noreferrer" class="underline font-medium text-violet-500 hover:text-violet-700">HMRC tax calculator</a> to estimate your tax
              </p>
            </div>
          </template>
        </div>

        <!-- Early Retirement Warning -->
        <div
          v-if="showEarlyRetirementWarning"
          class="mt-4 bg-violet-50 border border-violet-200 rounded-lg p-4"
        >
          <div class="flex">
            <svg class="h-5 w-5 text-violet-400 mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
            </svg>
            <p class="text-body-sm text-violet-800">
              <strong>Early Retirement:</strong> In most circumstances you are only able to access retirement benefits from the age of 55. You retired at age {{ retirementAge }}.
            </p>
          </div>
        </div>
      </div>

      <!-- Income Section -->
      <div v-if="formData.employment_status" class="border-t pt-4">
        <h4 class="text-body font-medium text-horizon-500 mb-4">
          Income Sources
        </h4>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <!-- Rental Income (conditional - only shown if properties with rental income exist) -->
          <div v-if="hasRentalIncome">
            <label for="annual_rental_income" class="label">
              Annual Rental Income
            </label>
            <div class="relative">
              <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-neutral-500">£</span>
              <input
                id="annual_rental_income"
                :value="annualRentalIncome"
                type="number"
                class="input-field pl-8 bg-eggshell-500"
                readonly
                disabled
              >
            </div>
            <p class="mt-1 text-body-sm text-neutral-500">
              From properties entered in Assets & Wealth (read-only)
            </p>
          </div>

          <!-- Retired: Info message -->
          <div v-if="formData.employment_status === 'retired'" class="md:col-span-2">
            <div class="bg-violet-50 border border-violet-200 rounded-lg p-4">
              <p class="text-body-sm text-violet-800">
                Income from retirement funds (pensions, annuities) is automatically calculated from the pensions you add in the Retirement module.
              </p>
            </div>
          </div>

          <!-- Employment Income (employed/part_time only) -->
          <div v-if="formData.employment_status === 'employed' || formData.employment_status === 'part_time'">
            <label for="annual_employment_income" class="label">
              Annual Employment Income <span class="q-icon" @click="emitWhyField($event,'annual_employment_income')" title="Why we ask this">?</span>
            </label>
            <div class="relative">
              <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-neutral-500">£</span>
              <input
                id="annual_employment_income"
                v-model.number="formData.annual_employment_income"
                type="number"
                min="0"
                step="1000"
                class="input-field pl-8"
                placeholder="50000"
                @focus="emitWhyField($event,'annual_employment_income')"
              >
            </div>
            <p class="mt-1 text-body-sm text-neutral-500">
              Salary, bonuses, and other employment income (before tax)
            </p>
          </div>

          <!-- Self-Employment Income (self_employed only) -->
          <div v-if="formData.employment_status === 'self_employed'">
            <label for="annual_self_employment_income" class="label">
              Annual Self-Employment Income <span class="q-icon" @click="emitWhyField($event,'annual_self_employment_income')" title="Why we ask this">?</span>
            </label>
            <div class="relative">
              <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-neutral-500">£</span>
              <input
                id="annual_self_employment_income"
                v-model.number="formData.annual_self_employment_income"
                type="number"
                min="0"
                step="1000"
                class="input-field pl-8"
                placeholder="0"
                @focus="emitWhyField($event,'annual_self_employment_income')"
              >
            </div>
            <p class="mt-1 text-body-sm text-neutral-500">
              Income from business or freelancing
            </p>
          </div>

          <!-- Benefit Income (unemployed only) -->
          <div v-if="formData.employment_status === 'unemployed'">
            <label for="annual_benefit_income" class="label">
              Annual Benefit Income <span class="q-icon" @click="emitWhyField($event,'annual_benefit_income')" title="Why we ask this">?</span>
            </label>
            <div class="relative">
              <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-neutral-500">£</span>
              <input
                id="annual_benefit_income"
                v-model.number="formData.annual_benefit_income"
                type="number"
                min="0"
                step="100"
                class="input-field pl-8"
                placeholder="0"
                @focus="emitWhyField($event,'annual_benefit_income')"
              >
            </div>
            <p class="mt-1 text-body-sm text-neutral-500">
              Universal Credit, JSA, ESA, or other state benefits
            </p>
          </div>

          <!-- Dividend Income (always shown when status is selected) -->
          <div v-if="formData.employment_status">
            <label for="annual_dividend_income" class="label">
              Annual Dividend Income <span class="q-icon" @click="emitWhyField($event,'annual_dividend_income')" title="Why we ask this">?</span>
            </label>
            <div class="relative">
              <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-neutral-500">£</span>
              <input
                id="annual_dividend_income"
                v-model.number="formData.annual_dividend_income"
                type="number"
                min="0"
                step="100"
                class="input-field pl-8"
                placeholder="0"
                @focus="emitWhyField($event,'annual_dividend_income')"
              >
            </div>
            <p class="mt-1 text-body-sm text-neutral-500">
              Income from shares and investments
            </p>
          </div>

          <!-- Interest Income (always shown when status is selected) -->
          <div v-if="formData.employment_status">
            <label for="annual_interest_income" class="label">
              Annual Interest Income <span class="q-icon" @click="emitWhyField($event,'annual_interest_income')" title="Why we ask this">?</span>
            </label>
            <div class="relative">
              <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-neutral-500">£</span>
              <input
                id="annual_interest_income"
                v-model.number="formData.annual_interest_income"
                type="number"
                min="0"
                step="100"
                class="input-field pl-8"
                placeholder="0"
                @focus="emitWhyField($event,'annual_interest_income')"
              >
            </div>
            <p class="mt-1 text-body-sm text-neutral-500">
              Interest from savings accounts and bonds
            </p>
          </div>

          <!-- Other Income (always shown when status is selected) -->
          <div v-if="formData.employment_status">
            <label for="annual_other_income" class="label">
              Annual Other Income <span class="q-icon" @click="emitWhyField($event,'annual_other_income')" title="Why we ask this">?</span>
            </label>
            <div class="relative">
              <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-neutral-500">£</span>
              <input
                id="annual_other_income"
                v-model.number="formData.annual_other_income"
                type="number"
                min="0"
                step="1000"
                class="input-field pl-8"
                placeholder="0"
                @focus="emitWhyField($event,'annual_other_income')"
              >
            </div>
            <p class="mt-1 text-body-sm text-neutral-500">
              Any other income sources
            </p>
          </div>

          <!-- Total Income (calculated) -->
          <div v-if="formData.employment_status" class="bg-eggshell-500 rounded-lg p-4">
            <p class="text-body-sm text-neutral-500">Total Annual Income</p>
            <p class="text-h3 font-display text-horizon-500">
              {{ formatCurrency(totalIncome) }}
            </p>
            <p v-if="hasRentalIncome" class="text-body-sm text-neutral-500 mt-2">
              Includes {{ formatCurrency(annualRentalIncome) }} rental income
            </p>
          </div>
        </div>
      </div>

      <!-- Registered Blind -->
      <div class="border-t pt-4">
        <div class="flex items-center gap-3">
          <input
            id="is_registered_blind"
            v-model="formData.is_registered_blind"
            type="checkbox"
            class="h-4 w-4 rounded border-light-gray text-violet-500 focus:ring-violet-500"
          >
          <label for="is_registered_blind" class="text-body-sm text-horizon-500">
            I am registered blind or severely sight impaired
          </label>
        </div>
        <p class="mt-1 ml-7 text-body-sm text-neutral-500">
          This qualifies you for the Blind Person's Allowance, which reduces your taxable income
        </p>
      </div>

    </div>
  </OnboardingStep>
</template>

<script>
// DEPRECATED: Will be replaced by unified form with context="onboarding". See life-stage-journey-design.md §11.7
import { ref, computed, onMounted } from 'vue';
import { useStore } from 'vuex';
import OnboardingStep from '../OnboardingStep.vue';
import UsefulResources from '../UsefulResources.vue';
import { LINKS, STEP_RESOURCES } from '@/constants/onboardingLinks';
import OccupationAutocomplete from '@/components/Shared/OccupationAutocomplete.vue';
import propertyService from '@/services/propertyService';
import { formatCurrency } from '@/utils/currency';

import logger from '@/utils/logger';
export default {
  name: 'IncomeStep',

  components: {
    OnboardingStep,
    UsefulResources,
    OccupationAutocomplete,
  },

  props: {
    savedData: { type: Object, default: null },
    context: { type: String, default: null },
  },

  emits: ['next', 'back', 'skip', 'sidebar-update'],

  setup(props, { emit }) {
    const store = useStore();

    const formData = ref({
      occupation: '',
      employer: '',
      industry: '',
      employment_status: '',
      target_retirement_age: null,
      retirement_date: '',
      annual_employment_income: 0,
      annual_self_employment_income: 0,
      annual_benefit_income: 0,
      annual_dividend_income: 0,
      annual_interest_income: 0,
      annual_other_income: 0,
      is_registered_blind: false,
    });

    const loading = ref(false);
    const error = ref(null);
    const annualRentalIncome = ref(0);

    const today = computed(() => {
      return new Date().toISOString().split('T')[0];
    });

    const showEmploymentFields = computed(() => {
      return formData.value.employment_status !== 'retired' &&
             formData.value.employment_status !== 'unemployed';
    });

    const hasRentalIncome = computed(() => {
      return annualRentalIncome.value > 0;
    });

    const totalIncome = computed(() => {
      return (
        (formData.value.annual_employment_income || 0) +
        (formData.value.annual_self_employment_income || 0) +
        (formData.value.annual_benefit_income || 0) +
        (formData.value.annual_dividend_income || 0) +
        (formData.value.annual_interest_income || 0) +
        (annualRentalIncome.value || 0)
      );
    });

    const retirementAge = computed(() => {
      if (!formData.value.retirement_date) return null;

      const currentUser = store.getters['auth/currentUser'];
      if (!currentUser?.date_of_birth) return null;

      const birthDate = new Date(currentUser.date_of_birth);
      const retireDate = new Date(formData.value.retirement_date);

      let age = retireDate.getFullYear() - birthDate.getFullYear();
      const monthDiff = retireDate.getMonth() - birthDate.getMonth();

      if (monthDiff < 0 || (monthDiff === 0 && retireDate.getDate() < birthDate.getDate())) {
        age--;
      }

      return age;
    });

    const showEarlyRetirementWarning = computed(() => {
      return formData.value.employment_status === 'retired' &&
             retirementAge.value !== null &&
             retirementAge.value < 55;
    });

    const WHY_FIELD_DATA = {
      employment_status: { whyWeAsk: 'Your employment status determines which income fields are relevant and affects pension contribution limits.' },
      occupation: { whyWeAsk: 'Your occupation helps us estimate income growth and assess protection insurance eligibility and premiums.' },
      employer: { whyWeAsk: 'Knowing your employer helps us identify workplace pension schemes and employee benefits you may have access to.' },
      industry: { whyWeAsk: 'Your industry can affect income stability projections and risk profiling for your financial plan.' },
      target_retirement_age: { whyWeAsk: 'Your target retirement age drives all pension forecasts, State Pension timing, and long-term savings projections.' },
      annual_employment_income: { whyWeAsk: 'Your employment income is the foundation for tax calculations, pension contribution limits, and savings capacity.' },
      annual_self_employment_income: { whyWeAsk: 'Self-employment income affects your tax liability, National Insurance contributions, and pension Annual Allowance.' },
      annual_benefit_income: { whyWeAsk: 'State benefits may affect your overall tax position and eligibility for other financial products.' },
      annual_dividend_income: { whyWeAsk: 'Dividend income is taxed differently from employment income and affects your overall tax band calculations.' },
      annual_interest_income: { whyWeAsk: 'Interest income affects your Personal Savings Allowance and overall tax position.' },
      annual_other_income: { whyWeAsk: 'All income sources contribute to your total taxable income and affect tax band thresholds.' },
    };

    let lastEmittedField = null;

    const emitWhyField = (event, fieldName) => {
      const data = WHY_FIELD_DATA[fieldName];
      if (!data) return;

      if (event?.type === 'focus' && lastEmittedField === fieldName) {
        lastEmittedField = null;
        return;
      }

      const el = event?.target;
      const fieldDiv = el?.closest?.('div') || null;
      const inputEl = fieldDiv?.querySelector('input:not(.prepop-input), select');

      if (event?.type === 'click' && inputEl && !inputEl.disabled) {
        lastEmittedField = fieldName;
        inputEl.focus();
      }

      let fieldOffsetY = 0;
      if (inputEl) {
        const formCol = inputEl.closest('.flex-1');
        if (formCol) {
          const colRect = formCol.getBoundingClientRect();
          const inputRect = inputEl.getBoundingClientRect();
          fieldOffsetY = inputRect.top - colRect.top + (inputRect.height / 2);
        }
      }

      emit('sidebar-update', { whyWeAsk: data.whyWeAsk, fieldOffsetY });
    };

    const handleNext = async () => {
      loading.value = true;
      error.value = null;

      try {
        await store.dispatch('onboarding/saveStepData', {
          stepName: 'income',
          data: formData.value,
        });

        emit('next');
      } catch (err) {
        error.value = err.message || 'Failed to save income information. Please try again.';
      } finally {
        loading.value = false;
      }
    };

    const handleBack = () => {
      emit('back');
    };

    onMounted(async () => {
      // Ensure we have latest user data
      if (!store.getters['auth/currentUser']) {
        try {
          await store.dispatch('auth/fetchUser');
        } catch (err) {
          logger.error('Failed to fetch current user:', err);
        }
      }

      // Get current user from store
      const currentUser = store.getters['auth/currentUser'];

      // Pre-populate from user table if data exists
      if (currentUser) {
        // Check both field names for backwards compatibility
        if (currentUser.annual_employment_income) {
          formData.value.annual_employment_income = currentUser.annual_employment_income;
        } else if (currentUser.employment_income) {
          formData.value.annual_employment_income = currentUser.employment_income;
        }
      }

      // Load from backend API
      try {
        const stepData = await store.dispatch('onboarding/fetchStepData', 'income');
        if (stepData && Object.keys(stepData).length > 0) {
          formData.value = { ...formData.value, ...stepData };
        }
      } catch (err) {
        // No existing data, use pre-populated values from user table
      }

      // Pre-populate employment details from profile (saved in About You step)
      const profileData = store.getters['userProfile/incomeOccupation'];
      if (profileData) {
        if (!formData.value.employment_status && profileData.employment_status) formData.value.employment_status = profileData.employment_status;
        if (!formData.value.occupation && profileData.occupation) formData.value.occupation = profileData.occupation;
        if (!formData.value.employer && profileData.employer) formData.value.employer = profileData.employer;
        if (!formData.value.industry && profileData.industry) formData.value.industry = profileData.industry;
        if (!formData.value.target_retirement_age && profileData.target_retirement_age) formData.value.target_retirement_age = profileData.target_retirement_age;
      }

      // Fetch rental income directly from properties API using propertyService
      // Properties are loaded fresh to ensure we have the latest rental income data
      try {
        const response = await propertyService.getProperties();
        const properties = Array.isArray(response) ? response : (response.data?.properties || response.data || []);

        if (properties.length > 0) {
          const totalRentalIncome = properties.reduce((total, property) => {
            let monthlyRental = property.monthly_rental_income || 0;
            // monthly_rental_income stores FULL rental amount
            // Apply ownership percentage for joint/tenants_in_common
            if (property.ownership_type === 'joint' || property.ownership_type === 'tenants_in_common') {
              const percentage = property.ownership_percentage || 50;
              monthlyRental = monthlyRental * (percentage / 100);
            }
            return total + (monthlyRental * 12);
          }, 0);
          annualRentalIncome.value = totalRentalIncome;
        }
      } catch (err) {
        console.warn('Failed to fetch rental income from properties:', err.message || err);
        // No rental income displayed, but error logged for debugging
      }
    });

    return {
      formData,
      loading,
      error,
      today,
      showEmploymentFields,
      totalIncome,
      retirementAge,
      showEarlyRetirementWarning,
      annualRentalIncome,
      hasRentalIncome,
      handleNext,
      handleBack,
      emitWhyField,
      formatCurrency,
      LINKS,
      STEP_RESOURCES,
    };
  },
};
</script>
