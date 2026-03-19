<template>
  <OnboardingStep
    title="Your Income"
    description="Tell us about your employment and take-home pay"
    :can-go-back="true"
    :can-skip="true"
    :loading="loading"
    :error="error"
    @next="handleNext"
    @back="handleBack"
    @skip="handleSkip"
  >
    <div class="space-y-6">
      <!-- Employment Status -->
      <div>
        <label for="employment_status" class="label">
          Employment Status
        </label>
        <select
          id="employment_status"
          v-model="formData.employment_status"
          class="input-field"
        >
          <option value="">Select status</option>
          <option value="employed">Employed</option>
          <option value="part_time">Part-Time</option>
          <option value="self_employed">Self-Employed</option>
          <option value="student">Student</option>
          <option value="unemployed">Unemployed</option>
          <option value="retired">Retired</option>
          <option value="other">Other</option>
        </select>
      </div>

      <!-- Conditional Occupation (shown for protection journey) -->
      <div v-if="showOccupation">
        <label for="occupation" class="label">
          Occupation
        </label>
        <OccupationAutocomplete
          id="occupation"
          v-model="formData.occupation"
          placeholder="e.g., Software Engineer"
          :show-hint="true"
        />
        <p class="mt-1 text-body-sm text-neutral-500">
          Your occupation affects insurance premiums and income protection eligibility
        </p>
      </div>

      <!-- After-Tax Income (shown when employed/part-time/self-employed/other) -->
      <div v-if="showIncomeField">
        <label for="after_tax_income" class="label">
          Monthly Take-Home Pay
        </label>
        <div class="relative">
          <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-neutral-500">&pound;</span>
          <input
            id="after_tax_income"
            v-model.number="formData.monthly_income"
            type="number"
            min="0"
            step="100"
            class="input-field pl-8"
            placeholder="2,500"
          >
        </div>
        <p class="mt-1 text-body-sm text-neutral-500">
          The amount that actually comes into your bank account each month, after tax and deductions
        </p>
      </div>

      <!-- Target Retirement Age (shown only if retirement journey selected and not already retired) -->
      <div v-if="showRetirementAge">
        <label for="target_retirement_age" class="label">
          When do you want to retire?
        </label>
        <input
          id="target_retirement_age"
          v-model.number="formData.target_retirement_age"
          type="number"
          min="30"
          max="75"
          class="input-field"
          placeholder="65"
        >
        <p class="mt-1 text-body-sm text-neutral-500">
          Your planned retirement age, used for pension forecasts and investment timelines
        </p>
      </div>
    </div>
  </OnboardingStep>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import { useStore } from 'vuex';
import OnboardingStep from '../OnboardingStep.vue';
import OccupationAutocomplete from '@/components/Shared/OccupationAutocomplete.vue';

export default {
  name: 'SimpleIncomeStep',

  components: {
    OnboardingStep,
    OccupationAutocomplete,
  },

  emits: ['next', 'back', 'skip'],

  setup(props, { emit }) {
    const store = useStore();

    const formData = ref({
      employment_status: '',
      occupation: '',
      monthly_income: null,
      target_retirement_age: null,
    });

    const loading = ref(false);
    const error = ref(null);

    const showIncomeField = computed(() => {
      const status = formData.value.employment_status;
      return status && status !== 'unemployed' && status !== 'retired';
    });

    // Show occupation for protection journey
    const showOccupation = computed(() => {
      const selections = store.state.journeys?.selections || [];
      return selections.includes('protection');
    });

    const showRetirementAge = computed(() => {
      const selections = store.state.journeys?.selections || [];
      const hasRetirement = selections.includes('retirement');
      const notRetired = formData.value.employment_status !== 'retired';
      return hasRetirement && notRetired;
    });

    const handleNext = async () => {
      loading.value = true;
      error.value = null;

      try {
        // Convert monthly income to annual for backend storage
        const dataToSave = {
          employment_status: formData.value.employment_status,
          monthly_income: formData.value.monthly_income,
          annual_employment_income: formData.value.monthly_income
            ? formData.value.monthly_income * 12
            : null,
          target_retirement_age: formData.value.target_retirement_age,
        };

        if (showOccupation.value) {
          dataToSave.occupation = formData.value.occupation;
        }

        await store.dispatch('onboarding/saveStepData', {
          stepName: 'simple_income',
          data: dataToSave,
        });
        emit('next');
      } catch (err) {
        error.value = err.message || 'Failed to save. Please try again.';
      } finally {
        loading.value = false;
      }
    };

    const handleBack = () => {
      emit('back');
    };

    const handleSkip = () => {
      emit('skip');
    };

    onMounted(async () => {
      // Pre-populate from user data
      const currentUser = store.getters['auth/currentUser'];
      if (currentUser) {
        formData.value.employment_status = currentUser.employment_status || '';
        formData.value.occupation = currentUser.occupation || '';
        formData.value.target_retirement_age = currentUser.target_retirement_age || null;
        if (currentUser.annual_employment_income) {
          formData.value.monthly_income = Math.round(currentUser.annual_employment_income / 12);
        }
      }

      // Load any previously saved step data
      try {
        const stepData = await store.dispatch('onboarding/fetchStepData', 'simple_income');
        if (stepData && Object.keys(stepData).length > 0) {
          if (stepData.monthly_income) formData.value.monthly_income = stepData.monthly_income;
          if (stepData.employment_status) formData.value.employment_status = stepData.employment_status;
          if (stepData.occupation) formData.value.occupation = stepData.occupation;
          if (stepData.target_retirement_age) formData.value.target_retirement_age = stepData.target_retirement_age;
        }
      } catch {
        // No existing data
      }
    });

    return {
      formData,
      loading,
      error,
      showIncomeField,
      showOccupation,
      showRetirementAge,
      handleNext,
      handleBack,
      handleSkip,
    };
  },
};
</script>
