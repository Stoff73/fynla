<template>
  <OnboardingStep
    title="Your Financial Picture"
    description="Let us know which areas apply to you so we can tailor your dashboard"
    :can-go-back="true"
    :can-skip="false"
    :loading="loading"
    :error="error"
    next-button-text="Go to Dashboard"
    @next="handleNext"
    @back="handleBack"
  >
    <div class="space-y-6">
      <div class="bg-violet-50 border border-violet-200 rounded-lg p-4">
        <p class="text-body-sm text-violet-800">
          <strong>No detail needed yet.</strong> Just let us know which areas are relevant to you. You can add the detail from your dashboard at any time.
        </p>
      </div>

      <div class="space-y-3">
        <div
          v-for="category in categories"
          :key="category.id"
          class="flex items-center justify-between p-4 bg-eggshell-500 rounded-lg border border-light-gray hover:border-horizon-300 transition-colors cursor-pointer"
          @click="toggleCategory(category.id)"
        >
          <div class="flex items-center gap-3">
            <div
              class="w-10 h-10 rounded-full flex items-center justify-center"
              :class="category.iconBgClass"
            >
              <svg class="w-5 h-5" :class="category.iconClass" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="category.iconPath" />
              </svg>
            </div>
            <div>
              <p class="text-body font-medium text-horizon-500">{{ category.label }}</p>
              <p class="text-body-sm text-neutral-500">{{ category.description }}</p>
            </div>
          </div>

          <!-- Toggle -->
          <button
            type="button"
            class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2"
            :class="flags[category.id] ? 'bg-spring-500' : 'bg-horizon-300'"
            role="switch"
            :aria-checked="flags[category.id]"
            @click.stop="toggleCategory(category.id)"
          >
            <span
              class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
              :class="flags[category.id] ? 'translate-x-5' : 'translate-x-0'"
            ></span>
          </button>
        </div>
      </div>

      <p class="text-body-sm text-neutral-500 text-center">
        You can always add or update these areas from your dashboard later.
      </p>
    </div>
  </OnboardingStep>
</template>

<script>
import { ref } from 'vue';
import { useStore } from 'vuex';
import OnboardingStep from '../OnboardingStep.vue';

export default {
  name: 'QuickAssetsStep',

  components: {
    OnboardingStep,
  },

  emits: ['next', 'back'],

  setup(props, { emit }) {
    const store = useStore();
    const loading = ref(false);
    const error = ref(null);

    const flags = ref({
      properties: false,
      savings: false,
      investments: false,
      pensions: false,
      protection: false,
    });

    const categories = [
      {
        id: 'properties',
        label: 'Properties',
        description: 'Main residence, buy-to-let, or other properties',
        iconBgClass: 'bg-spring-100',
        iconClass: 'text-spring-600',
        iconPath: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
      },
      {
        id: 'savings',
        label: 'Savings',
        description: 'Current accounts, savings accounts, cash ISAs',
        iconBgClass: 'bg-violet-100',
        iconClass: 'text-violet-600',
        iconPath: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
      },
      {
        id: 'investments',
        label: 'Investments',
        description: 'Stocks and Shares ISAs, general investment accounts',
        iconBgClass: 'bg-raspberry-100',
        iconClass: 'text-raspberry-600',
        iconPath: 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6',
      },
      {
        id: 'pensions',
        label: 'Pensions',
        description: 'Workplace pensions, personal pensions',
        iconBgClass: 'bg-savannah-100',
        iconClass: 'text-neutral-600',
        iconPath: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
      },
      {
        id: 'protection',
        label: 'Life Insurance & Protection',
        description: 'Life insurance, critical illness, income protection',
        iconBgClass: 'bg-horizon-100',
        iconClass: 'text-horizon-600',
        iconPath: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
      },
    ];

    const toggleCategory = (id) => {
      flags.value[id] = !flags.value[id];
    };

    const handleNext = async () => {
      loading.value = true;
      error.value = null;

      try {
        await store.dispatch('onboarding/saveStepData', {
          stepName: 'quick_assets',
          data: { asset_flags: flags.value },
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

    return {
      flags,
      categories,
      loading,
      error,
      toggleCategory,
      handleNext,
      handleBack,
    };
  },
};
</script>
