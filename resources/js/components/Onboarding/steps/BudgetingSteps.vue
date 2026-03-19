<template>
  <OnboardingStep
    title="Income & Spending"
    description="Understanding your income and spending helps us build an accurate financial picture"
    :can-go-back="true"
    :can-skip="true"
    :loading="loading"
    :error="error"
    @next="handleNext"
    @back="handleBack"
    @skip="handleSkip"
  >
    <div class="space-y-6">
      <!-- Monthly Income -->
      <div>
        <h4 class="text-body font-medium text-horizon-500 mb-4">Monthly Income</h4>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label for="monthly_income" class="label">
              Total Monthly Income (after tax)
            </label>
            <div class="relative">
              <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-neutral-500">&pound;</span>
              <input
                id="monthly_income"
                v-model.number="formData.monthly_income"
                type="number"
                min="0"
                step="100"
                class="input-field pl-8"
                placeholder="3000"
              >
            </div>
            <p class="mt-1 text-body-sm text-neutral-500">
              Your take-home pay each month
            </p>
          </div>
        </div>
      </div>

      <!-- Monthly Expenditure -->
      <div class="border-t pt-4">
        <h4 class="text-body font-medium text-horizon-500 mb-4">Monthly Expenditure</h4>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label for="monthly_expenditure" class="label">
              Total Monthly Spending
            </label>
            <div class="relative">
              <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-neutral-500">&pound;</span>
              <input
                id="monthly_expenditure"
                v-model.number="formData.monthly_expenditure"
                type="number"
                min="0"
                step="100"
                class="input-field pl-8"
                placeholder="2000"
              >
            </div>
            <p class="mt-1 text-body-sm text-neutral-500">
              Include rent/mortgage, bills, food, transport, and other regular costs
            </p>
          </div>
        </div>
      </div>

      <!-- Savings Summary -->
      <div v-if="monthlySurplus !== null" class="border-t pt-4">
        <div class="bg-eggshell-500 rounded-lg p-4">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-body-sm text-neutral-500">Estimated Monthly Surplus</p>
              <p
                class="text-h3 font-display"
                :class="monthlySurplus >= 0 ? 'text-spring-600' : 'text-raspberry-500'"
              >
                {{ formatCurrency(monthlySurplus) }}
              </p>
            </div>
            <div v-if="monthlySurplus > 0" class="text-right">
              <p class="text-body-sm text-neutral-500">Potential Annual Savings</p>
              <p class="text-body font-medium text-horizon-500">
                {{ formatCurrency(monthlySurplus * 12) }}
              </p>
            </div>
          </div>
          <p v-if="monthlySurplus < 0" class="mt-2 text-body-sm text-raspberry-500">
            Your spending exceeds your income. Consider reviewing your budget.
          </p>
        </div>
      </div>
    </div>
  </OnboardingStep>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import { useStore } from 'vuex';
import OnboardingStep from '../OnboardingStep.vue';
import { formatCurrency } from '@/utils/currency';

export default {
  name: 'BudgetingSteps',

  components: {
    OnboardingStep,
  },

  emits: ['next', 'back', 'skip'],

  setup(props, { emit }) {
    const store = useStore();

    const formData = ref({
      monthly_income: null,
      monthly_expenditure: null,
    });

    const loading = ref(false);
    const error = ref(null);

    const monthlySurplus = computed(() => {
      if (formData.value.monthly_income === null && formData.value.monthly_expenditure === null) {
        return null;
      }
      return (formData.value.monthly_income || 0) - (formData.value.monthly_expenditure || 0);
    });

    const handleNext = async () => {
      loading.value = true;
      error.value = null;

      try {
        await store.dispatch('onboarding/saveStepData', {
          stepName: 'budgeting',
          data: formData.value,
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
      // Pre-populate from user data if available
      const currentUser = store.getters['auth/currentUser'];
      if (currentUser) {
        if (currentUser.annual_employment_income) {
          formData.value.monthly_income = Math.round(currentUser.annual_employment_income / 12);
        }
        if (currentUser.monthly_expenditure) {
          formData.value.monthly_expenditure = currentUser.monthly_expenditure;
        }
      }

      // Load any previously saved step data
      try {
        const stepData = await store.dispatch('onboarding/fetchStepData', 'budgeting');
        if (stepData && Object.keys(stepData).length > 0) {
          formData.value = { ...formData.value, ...stepData };
        }
      } catch {
        // No existing data
      }
    });

    return {
      formData,
      loading,
      error,
      monthlySurplus,
      handleNext,
      handleBack,
      handleSkip,
      formatCurrency,
    };
  },
};
</script>
