<template>
  <OnboardingStep
    title="Set a Financial Goal"
    description="Define a goal to work towards -- we will track your progress and suggest strategies"
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
          <strong>Why this matters:</strong> Setting clear goals helps you stay on track and gives you measurable targets to work towards. You can add more goals later from your Goals dashboard.
        </p>
      </div>

      <!-- Goal Type -->
      <div>
        <label for="goal_type" class="label">
          What are you saving for?
        </label>
        <select
          id="goal_type"
          v-model="formData.goal_type"
          class="input-field"
        >
          <option value="">Select a goal type</option>
          <option value="emergency_fund">Emergency Fund</option>
          <option value="house_deposit">House Deposit</option>
          <option value="holiday">Holiday</option>
          <option value="education">Education</option>
          <option value="wedding">Wedding</option>
          <option value="car">Car Purchase</option>
          <option value="home_improvement">Home Improvement</option>
          <option value="other">Other</option>
        </select>
      </div>

      <!-- Goal Name (for "Other" type) -->
      <div v-if="formData.goal_type === 'other'">
        <label for="goal_name" class="label">
          Goal Name
        </label>
        <input
          id="goal_name"
          v-model="formData.name"
          type="text"
          class="input-field"
          placeholder="e.g. New Kitchen"
        >
      </div>

      <!-- Target Amount -->
      <div v-if="formData.goal_type">
        <label for="target_amount" class="label">
          Target Amount
        </label>
        <div class="relative">
          <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-neutral-500">&pound;</span>
          <input
            id="target_amount"
            v-model.number="formData.target_amount"
            type="number"
            min="0"
            step="100"
            class="input-field pl-8"
            placeholder="10000"
          >
        </div>
        <p v-if="formData.goal_type === 'emergency_fund'" class="mt-1 text-body-sm text-neutral-500">
          A common guideline is 3 to 6 months of essential spending
        </p>
      </div>

      <!-- Target Date -->
      <div v-if="formData.goal_type">
        <label for="target_date" class="label">
          Target Date
        </label>
        <input
          id="target_date"
          v-model="formData.target_date"
          type="date"
          class="input-field"
          :min="today"
        >
        <p class="mt-1 text-body-sm text-neutral-500">
          When would you like to reach this goal?
        </p>
      </div>

      <!-- Monthly Contribution Estimate -->
      <div v-if="monthlyContribution" class="bg-eggshell-500 rounded-lg p-4">
        <p class="text-body-sm text-neutral-500">Estimated Monthly Contribution Needed</p>
        <p class="text-h3 font-display text-horizon-500">
          {{ formatCurrency(monthlyContribution) }}
        </p>
        <p class="text-body-sm text-neutral-500 mt-1">
          Based on {{ monthsRemaining }} {{ monthsRemaining === 1 ? 'month' : 'months' }} to your target date
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
import goalsService from '@/services/goalsService';
import { formatCurrency } from '@/utils/currency';

export default {
  name: 'GoalSetupStep',

  components: {
    OnboardingStep,
  },

  emits: ['next', 'back', 'skip'],

  setup(props, { emit }) {
    const store = useStore();

    const formData = ref({
      goal_type: '',
      name: '',
      target_amount: null,
      target_date: '',
    });

    const loading = ref(false);
    const error = ref(null);

    const today = computed(() => {
      return new Date().toISOString().split('T')[0];
    });

    const monthsRemaining = computed(() => {
      if (!formData.value.target_date) return 0;
      const now = new Date();
      const target = new Date(formData.value.target_date);
      const months = (target.getFullYear() - now.getFullYear()) * 12 + (target.getMonth() - now.getMonth());
      return Math.max(1, months);
    });

    const monthlyContribution = computed(() => {
      if (!formData.value.target_amount || !formData.value.target_date) return null;
      if (monthsRemaining.value <= 0) return null;
      return Math.ceil(formData.value.target_amount / monthsRemaining.value);
    });

    const goalTypeLabels = {
      emergency_fund: 'Emergency Fund',
      house_deposit: 'House Deposit',
      holiday: 'Holiday',
      education: 'Education',
      wedding: 'Wedding',
      car: 'Car Purchase',
      home_improvement: 'Home Improvement',
      other: 'Other',
    };

    const handleNext = async () => {
      if (!formData.value.goal_type) {
        error.value = 'Please select a goal type';
        return;
      }

      loading.value = true;
      error.value = null;

      try {
        // Build goal data for API
        const goalData = {
          name: formData.value.name || goalTypeLabels[formData.value.goal_type],
          goal_type: formData.value.goal_type,
          target_amount: formData.value.target_amount || 0,
          target_date: formData.value.target_date || null,
          status: 'active',
          priority: 'medium',
        };

        await goalsService.createGoal(goalData);

        // Also save step data for onboarding tracking
        await store.dispatch('onboarding/saveStepData', {
          stepName: 'goals',
          data: formData.value,
        });

        emit('next');
      } catch (err) {
        error.value = err.message || 'Failed to save goal. Please try again.';
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
      // Load any previously saved step data
      try {
        const stepData = await store.dispatch('onboarding/fetchStepData', 'goals');
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
      today,
      monthsRemaining,
      monthlyContribution,
      handleNext,
      handleBack,
      handleSkip,
      formatCurrency,
    };
  },
};
</script>
