<template>
  <OnboardingStep
    title="Your Monthly Outgoings"
    description="A rough estimate is fine — you can refine this later"
    :can-go-back="true"
    :can-skip="true"
    :loading="loading"
    :error="error"
    @next="handleNext"
    @back="handleBack"
    @skip="handleSkip"
  >
    <div class="space-y-6">
      <div class="bg-violet-50 border border-violet-200 rounded-lg p-4">
        <p class="text-body-sm text-violet-800">
          <strong>Why this matters:</strong> Knowing your spending helps us calculate how much you can save each month and build a realistic budget.
        </p>
      </div>

      <!-- Expenditure Amount -->
      <div>
        <label for="expenditure" class="label">
          Total Monthly Spending
        </label>
        <div class="relative">
          <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-neutral-500">&pound;</span>
          <input
            id="expenditure"
            v-model.number="amount"
            type="number"
            min="0"
            step="100"
            class="input-field pl-8"
            placeholder="2,000"
          >
        </div>
        <p class="mt-1 text-body-sm text-neutral-500">
          Include rent or mortgage, bills, food, transport, and other regular costs
        </p>
      </div>

      <!-- Surplus Preview -->
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
  name: 'SimpleExpenditureStep',

  components: {
    OnboardingStep,
  },

  emits: ['next', 'back', 'skip'],

  setup(props, { emit }) {
    const store = useStore();

    const amount = ref(null);
    const loading = ref(false);
    const error = ref(null);

    // Store the income from previous step for surplus calculation
    const savedMonthlyIncome = ref(null);

    const monthlySurplus = computed(() => {
      if (amount.value === null) return null;

      // First check income saved from SimpleIncomeStep, then fall back to user profile
      let monthlyIncome = savedMonthlyIncome.value;

      if (monthlyIncome === null) {
        const currentUser = store.getters['auth/currentUser'];
        if (currentUser?.annual_employment_income) {
          monthlyIncome = Math.round(currentUser.annual_employment_income / 12);
        }
      }

      if (monthlyIncome === null) return null;

      return monthlyIncome - amount.value;
    });

    const handleNext = async () => {
      loading.value = true;
      error.value = null;

      try {
        await store.dispatch('onboarding/saveStepData', {
          stepName: 'simple_expenditure',
          data: {
            monthly_expenditure: amount.value,
          },
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
      if (currentUser?.monthly_expenditure) {
        amount.value = currentUser.monthly_expenditure;
      }

      // Load income from the previous step for surplus calculation
      try {
        const incomeData = await store.dispatch('onboarding/fetchStepData', 'simple_income');
        if (incomeData?.monthly_income) {
          savedMonthlyIncome.value = incomeData.monthly_income;
        }
      } catch {
        // No income data saved yet
      }

      // Load any previously saved expenditure data
      try {
        const stepData = await store.dispatch('onboarding/fetchStepData', 'simple_expenditure');
        if (stepData?.monthly_expenditure !== undefined) {
          amount.value = stepData.monthly_expenditure;
        }
      } catch {
        // No existing data
      }
    });

    return {
      amount,
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
