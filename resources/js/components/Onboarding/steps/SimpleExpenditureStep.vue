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

      <!-- Charitable Donations -->
      <div class="border-t pt-4">
        <label for="charitable_donations" class="label">
          Monthly Charitable Donations
        </label>
        <div class="relative">
          <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-neutral-500">&pound;</span>
          <input
            id="charitable_donations"
            v-model.number="charitableDonations"
            type="number"
            min="0"
            step="10"
            class="input-field pl-8"
            placeholder="0"
          >
        </div>
        <p class="mt-1 text-body-sm text-neutral-500">
          Regular monthly giving to charities
        </p>

        <!-- Gift Aid toggle - shown when charitable donations > 0 -->
        <div v-if="charitableDonations > 0" class="mt-3 bg-violet-50 border border-violet-200 rounded-lg p-3">
          <div class="flex items-center gap-3">
            <input
              id="is_gift_aid"
              v-model="isGiftAid"
              type="checkbox"
              class="h-4 w-4 rounded border-light-gray text-violet-500 focus:ring-violet-500"
            >
            <label for="is_gift_aid" class="text-body-sm text-horizon-500 font-medium">
              I use Gift Aid for my charitable donations
            </label>
          </div>
          <p class="mt-1 ml-7 text-body-sm text-neutral-500">
            Gift Aid lets charities claim 25p for every £1 you donate, and higher-rate taxpayers can claim back the difference
          </p>
        </div>
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

      <UsefulResources :links="STEP_RESOURCES.simpleExpenditure" />
    </div>
  </OnboardingStep>
</template>

<script>
// DEPRECATED: Will be replaced by unified form with context="onboarding". See life-stage-journey-design.md §11.7
import { ref, computed, onMounted } from 'vue';
import { useStore } from 'vuex';
import OnboardingStep from '../OnboardingStep.vue';
import UsefulResources from '../UsefulResources.vue';
import { STEP_RESOURCES } from '@/constants/onboardingLinks';
import { formatCurrency } from '@/utils/currency';

export default {
  name: 'SimpleExpenditureStep',

  components: {
    OnboardingStep,
    UsefulResources,
  },

  emits: ['next', 'back', 'skip'],

  setup(props, { emit }) {
    const store = useStore();

    const amount = ref(null);
    const charitableDonations = ref(0);
    const isGiftAid = ref(false);
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
        // Build user profile data — set both monthly and annual for downstream services
        const profileData = {
          monthly_expenditure: amount.value,
          annual_expenditure: amount.value * 12,
          expenditure_entry_mode: 'simple',
        };

        // Include charitable donations on user model if entered
        if (charitableDonations.value > 0) {
          profileData.annual_charitable_donations = charitableDonations.value * 12;
          profileData.is_gift_aid = isGiftAid.value;
        }

        // Save to onboarding step data + persist to user profile
        await Promise.all([
          store.dispatch('onboarding/saveStepData', {
            stepName: 'simple_expenditure',
            data: {
              monthly_expenditure: amount.value,
              charitable_donations: charitableDonations.value,
              is_gift_aid: isGiftAid.value,
            },
          }),
          store.dispatch('userProfile/updatePersonalInfo', profileData).catch((err) => {
            console.error('Failed to persist expenditure data:', err);
          }),
        ]);
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
      if (currentUser?.annual_charitable_donations) {
        charitableDonations.value = Math.round(currentUser.annual_charitable_donations / 12);
      }
      if (currentUser?.is_gift_aid) {
        isGiftAid.value = currentUser.is_gift_aid;
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
        if (stepData?.charitable_donations !== undefined) {
          charitableDonations.value = stepData.charitable_donations;
        }
        if (stepData?.is_gift_aid !== undefined) {
          isGiftAid.value = stepData.is_gift_aid;
        }
      } catch {
        // No existing data
      }
    });

    return {
      amount,
      charitableDonations,
      isGiftAid,
      loading,
      error,
      monthlySurplus,
      handleNext,
      handleBack,
      handleSkip,
      formatCurrency,
      STEP_RESOURCES,
    };
  },
};
</script>
