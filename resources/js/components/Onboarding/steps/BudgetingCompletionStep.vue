<template>
  <div class="max-w-3xl mx-auto text-center">
    <!-- Checkmark Animation -->
    <div class="mb-8">
      <div class="inline-flex items-center justify-center w-20 h-20 bg-spring-100 rounded-full mb-6 journey-checkmark">
        <svg class="w-10 h-10 text-spring-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2.5"
            d="M5 13l4 4L19 7"
            class="checkmark-path"
          />
        </svg>
      </div>

      <h2 class="text-h1 font-display text-horizon-500 mb-2">
        Congratulations on completing your first journey!
      </h2>
      <p class="text-body text-neutral-500">
        You have taken the first step towards better financial clarity. Here is what you will get from this.
      </p>
    </div>

    <!-- What You Will Get -->
    <div class="bg-white rounded-lg shadow-sm border border-light-gray p-6 mb-8 text-left">
      <h3 class="text-h4 font-display text-horizon-500 mb-4">
        What this unlocks for you
      </h3>
      <ul class="space-y-4">
        <li class="flex items-start">
          <div class="flex-shrink-0 w-8 h-8 bg-spring-100 rounded-full flex items-center justify-center mt-0.5 mr-3">
            <svg class="w-4 h-4 text-spring-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
          </div>
          <div>
            <p class="text-body font-medium text-horizon-500">Better understanding of your spending habits</p>
            <p class="text-body-sm text-neutral-500">See exactly where your money goes each month and identify areas to improve</p>
          </div>
        </li>
        <li class="flex items-start">
          <div class="flex-shrink-0 w-8 h-8 bg-spring-100 rounded-full flex items-center justify-center mt-0.5 mr-3">
            <svg class="w-4 h-4 text-spring-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
            </svg>
          </div>
          <div>
            <p class="text-body font-medium text-horizon-500">Clearer financial goals</p>
            <p class="text-body-sm text-neutral-500">Know how much surplus income you have available to put towards savings and goals</p>
          </div>
        </li>
        <li class="flex items-start">
          <div class="flex-shrink-0 w-8 h-8 bg-spring-100 rounded-full flex items-center justify-center mt-0.5 mr-3">
            <svg class="w-4 h-4 text-spring-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
            </svg>
          </div>
          <div>
            <p class="text-body font-medium text-horizon-500">Emergency fund tracking</p>
            <p class="text-body-sm text-neutral-500">Monitor whether your savings provide enough cover for unexpected expenses</p>
          </div>
        </li>
        <li class="flex items-start">
          <div class="flex-shrink-0 w-8 h-8 bg-spring-100 rounded-full flex items-center justify-center mt-0.5 mr-3">
            <svg class="w-4 h-4 text-spring-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div>
            <p class="text-body font-medium text-horizon-500">Personalised savings recommendations</p>
            <p class="text-body-sm text-neutral-500">Get tailored advice on how to make the most of your money, including tax-free options</p>
          </div>
        </li>
      </ul>
    </div>

    <!-- Action Buttons -->
    <div class="space-y-4">
      <button
        @click="handleViewSavings"
        class="btn-primary btn-lg w-full sm:w-auto"
      >
        View your Cash Dashboard
      </button>

      <div v-if="hasNextJourney" class="mt-3">
        <button
          @click="handleNextJourney"
          :disabled="loading"
          class="inline-flex items-center text-body font-medium text-raspberry-500 hover:text-raspberry-600 transition-colors"
        >
          Continue to next journey
          <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
          </svg>
        </button>
      </div>

      <div class="mt-3">
        <button
          @click="handleGoToDashboard"
          class="inline-flex items-center text-body-sm font-medium text-neutral-500 hover:text-raspberry-500 transition-colors"
        >
          Go to Dashboard
          <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
          </svg>
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed } from 'vue';
import { useStore } from 'vuex';
import { useRouter } from 'vue-router';

export default {
  name: 'BudgetingCompletionStep',

  emits: ['next'],

  setup(props, { emit }) {
    const store = useStore();
    const router = useRouter();
    const loading = ref(false);

    const hasNextJourney = computed(() => {
      const selections = store.state.journeys?.selections || [];
      const states = store.state.journeys?.journeyStates || {};

      return selections.some(
        (j) => j !== 'budgeting' && states[j] !== 'completed'
      );
    });

    const handleViewSavings = () => {
      router.push({ path: '/net-worth/cash' });
    };

    const handleNextJourney = async () => {
      loading.value = true;
      const selections = store.state.journeys?.selections || [];
      const states = store.state.journeys?.journeyStates || {};

      const nextJourney = selections.find(
        (j) => j !== 'budgeting' && states[j] !== 'completed'
      );

      if (nextJourney) {
        await store.dispatch('journeys/startJourney', nextJourney);
        await store.dispatch('journeys/fetchSteps', nextJourney);
        router.push({ name: 'OnboardingJourney', params: { journey: nextJourney } });
      }
      loading.value = false;
    };

    const handleGoToDashboard = async () => {
      await store.dispatch('auth/fetchUser', null, { root: true });
      router.push({ name: 'Dashboard' });
    };

    return {
      loading,
      hasNextJourney,
      handleViewSavings,
      handleNextJourney,
      handleGoToDashboard,
    };
  },
};
</script>

<style scoped>
.journey-checkmark {
  animation: checkmark-scale 0.5s ease-out;
}

.checkmark-path {
  stroke-dasharray: 30;
  stroke-dashoffset: 30;
  animation: checkmark-draw 0.4s ease-out 0.3s forwards;
}
</style>
