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
      <!-- Goal Type -->
      <div>
        <label for="goal_type" class="label">
          What are you saving for? <span class="q-icon" @click="emitWhyField($event,'goal_type')" title="Why we ask this">?</span>
        </label>
        <select
          id="goal_type"
          v-model="formData.goal_type"
          class="input-field"
          @focus="emitWhyField($event,'goal_type')"
        >
          <option value="">Select a goal type</option>
          <option value="emergency_fund">Emergency Fund</option>
          <option value="home_deposit">House Deposit</option>
          <option value="holiday">Holiday</option>
          <option value="education">Education</option>
          <option value="wedding">Wedding</option>
          <option value="car_purchase">Car Purchase</option>
          <option value="debt_repayment">Debt Repayment</option>
          <option value="custom">Other</option>
        </select>
      </div>

      <!-- Goal Name (for "Other" type) -->
      <div v-if="formData.goal_type === 'custom'">
        <label for="goal_name" class="label">
          Goal Name <span class="q-icon" @click="emitWhyField($event,'name')" title="Why we ask this">?</span>
        </label>
        <input
          id="goal_name"
          v-model="formData.name"
          type="text"
          class="input-field"
          placeholder="e.g. New Kitchen"
          @focus="emitWhyField($event,'name')"
        >
      </div>

      <!-- Target Amount -->
      <div v-if="formData.goal_type">
        <label for="target_amount" class="label">
          Target Amount <span class="q-icon" @click="emitWhyField($event,'target_amount')" title="Why we ask this">?</span>
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
            @focus="emitWhyField($event,'target_amount')"
          >
        </div>
        <p v-if="formData.goal_type === 'emergency_fund'" class="mt-1 text-body-sm text-neutral-500">
          A common guideline is 3 to 6 months of essential spending
        </p>
      </div>

      <!-- Target Date -->
      <div v-if="formData.goal_type">
        <label for="target_date" class="label">
          Target Date <span class="q-icon" @click="emitWhyField($event,'target_date')" title="Why we ask this">?</span>
        </label>
        <input
          id="target_date"
          v-model="formData.target_date"
          type="date"
          class="input-field"
          :min="today"
          @focus="emitWhyField($event,'target_date')"
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

    <!-- Skip confirmation modal -->
    <Teleport to="body">
      <div v-if="showSkipConfirm" class="fixed inset-0 bg-horizon-600/50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4 p-6">
          <div class="flex items-start gap-3 mb-4">
            <div class="w-10 h-10 rounded-full bg-violet-100 flex items-center justify-center flex-shrink-0">
              <svg class="w-5 h-5 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
              </svg>
            </div>
            <div>
              <h3 class="text-lg font-bold text-horizon-500">Skip setting a goal?</h3>
              <p class="text-sm text-neutral-500 mt-1">Setting a financial goal helps us tailor your plan and track your progress. You can always add goals later from your dashboard.</p>
            </div>
          </div>
          <div class="flex justify-end gap-3">
            <button
              type="button"
              class="px-4 py-2 text-sm font-medium text-neutral-500 hover:text-horizon-500 transition-colors"
              @click="showSkipConfirm = false"
            >
              Go Back
            </button>
            <button
              type="button"
              class="px-4 py-2 text-sm font-medium text-white bg-raspberry-500 hover:bg-raspberry-600 rounded-button transition-colors"
              @click="showSkipConfirm = false; $emit('skip')"
            >
              Skip Anyway
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </OnboardingStep>
</template>

<script>
// DEPRECATED: Will be replaced by unified form with context="onboarding". See life-stage-journey-design.md §11.7
import { ref, computed, onMounted } from 'vue';
import { useStore } from 'vuex';
import OnboardingStep from '../OnboardingStep.vue';
import UsefulResources from '@/components/Onboarding/UsefulResources.vue';
import { STEP_RESOURCES } from '@/constants/onboardingLinks';
import goalsService from '@/services/goalsService';
import { formatCurrency } from '@/utils/currency';

export default {
  name: 'GoalSetupStep',

  components: {
    OnboardingStep,
    UsefulResources,
  },

  props: {
    savedData: { type: Object, default: null },
    context: { type: String, default: null },
  },

  emits: ['next', 'back', 'skip', 'sidebar-update'],

  setup(props, { emit }) {
    const store = useStore();

    const WHY_FIELD_DATA = {
      goal_type: { whyWeAsk: 'Knowing what you are saving for helps us tailor strategies and suggest the best savings vehicles for your goal.' },
      name: { whyWeAsk: 'A personalised goal name helps you track multiple goals and stay motivated.' },
      target_amount: { whyWeAsk: 'Your target amount lets us calculate the monthly contributions needed and track your progress.' },
      target_date: { whyWeAsk: 'A target date helps us calculate the required savings rate and suggest appropriate investment timelines.' },
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
      home_deposit: 'House Deposit',
      holiday: 'Holiday',
      education: 'Education',
      wedding: 'Wedding',
      car_purchase: 'Car Purchase',
      debt_repayment: 'Debt Repayment',
      custom: 'Other',
    };

    const showSkipConfirm = ref(false);

    const handleNext = async () => {
      // If no goal entered, show skip confirmation
      if (!formData.value.goal_type) {
        showSkipConfirm.value = true;
        return;
      }

      loading.value = true;
      error.value = null;

      try {
        // Build goal data for API (matches StoreGoalRequest validation)
        const goalName = formData.value.name || goalTypeLabels[formData.value.goal_type];
        const goalData = {
          goal_name: goalName,
          goal_type: formData.value.goal_type,
          target_amount: formData.value.target_amount || 0,
          target_date: formData.value.target_date || null,
          priority: 'medium',
        };
        // Backend requires custom_goal_type_name when goal_type is 'custom'
        if (formData.value.goal_type === 'custom') {
          goalData.custom_goal_type_name = formData.value.name;
        }

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
      // Restore from wizard cache first (back navigation), then from store
      if (props.savedData && Object.keys(props.savedData).length > 0) {
        formData.value = { ...formData.value, ...props.savedData };
      } else {
        try {
          const stepData = await store.dispatch('onboarding/fetchStepData', 'goals');
          if (stepData && Object.keys(stepData).length > 0) {
            formData.value = { ...formData.value, ...stepData };
          }
        } catch {
          // No existing data
        }
      }
    });

    return {
      formData,
      loading,
      error,
      today,
      monthsRemaining,
      monthlyContribution,
      showSkipConfirm,
      handleNext,
      handleBack,
      handleSkip,
      emitWhyField,
      formatCurrency,
      STEP_RESOURCES,
    };
  },
};
</script>
