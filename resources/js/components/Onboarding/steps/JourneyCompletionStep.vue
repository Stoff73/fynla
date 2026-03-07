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
        Your {{ journeyLabel }} is ready
      </h2>
      <p class="text-body text-neutral-500">
        Thank you for providing your information. Your {{ journeyLabel.toLowerCase() }} dashboard is now set up.
      </p>
    </div>

    <!-- Summary -->
    <div class="bg-white rounded-lg shadow-sm border border-light-gray p-6 mb-8 text-left">
      <h3 class="text-h4 font-display text-horizon-500 mb-4">
        What we captured
      </h3>
      <ul class="space-y-3">
        <li
          v-for="step in completedSteps"
          :key="step.name"
          class="flex items-center"
        >
          <svg class="h-5 w-5 text-spring-600 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
          </svg>
          <span class="text-body text-horizon-500">{{ step.name }}</span>
        </li>
      </ul>
    </div>

    <!-- What Happens Next -->
    <div class="bg-white rounded-lg shadow-sm border border-light-gray p-6 mb-8 text-left">
      <h3 class="text-h4 font-display text-horizon-500 mb-4">
        What happens next
      </h3>
      <ul class="space-y-3">
        <li class="flex items-start">
          <svg class="h-5 w-5 text-raspberry-500 mt-0.5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <span class="text-body text-horizon-500">
            Your dashboard will show your current {{ journeyLabel.toLowerCase() }} position
          </span>
        </li>
        <li class="flex items-start">
          <svg class="h-5 w-5 text-raspberry-500 mt-0.5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <span class="text-body text-horizon-500">
            You will receive personalised recommendations based on your data
          </span>
        </li>
        <li class="flex items-start">
          <svg class="h-5 w-5 text-raspberry-500 mt-0.5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <span class="text-body text-horizon-500">
            You can update your information at any time
          </span>
        </li>
      </ul>
    </div>

    <!-- Action Buttons -->
    <div class="space-y-4">
      <button
        @click="handleViewModule"
        class="btn-primary btn-lg w-full sm:w-auto"
      >
        {{ viewModuleText }}
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
import { computed } from 'vue';
import { useStore } from 'vuex';
import { useRouter } from 'vue-router';

export default {
  name: 'JourneyCompletionStep',

  props: {
    journeyName: {
      type: String,
      default: '',
    },
    completedSteps: {
      type: Array,
      default: () => [],
    },
  },

  emits: ['next', 'back'],

  setup(props, { emit }) {
    const store = useStore();
    const router = useRouter();

    const loading = computed(() => store.state.journeys.loading);

    const journeyLabels = {
      budgeting: 'Budgeting',
      protection: 'Protection',
      investment: 'Investment',
      retirement: 'Retirement',
      estate: 'Estate Planning',
      family: 'Family Planning',
      business: 'Business Planning',
      goals: 'Goal Tracking',
    };

    const moduleRoutes = {
      budgeting: { name: 'Savings' },
      protection: { name: 'Protection' },
      investment: { path: '/net-worth/investments' },
      retirement: { path: '/net-worth/retirement' },
      estate: { name: 'Estate' },
      family: { name: 'UserProfile' },
      business: { path: '/net-worth/business' },
      goals: { name: 'Goals' },
    };

    const journeyLabel = computed(() => {
      return journeyLabels[props.journeyName] || 'Financial Planning';
    });

    const viewModuleText = computed(() => {
      const label = journeyLabels[props.journeyName];
      return label ? `View your ${label} Dashboard` : 'View Dashboard';
    });

    const hasNextJourney = computed(() => {
      const selections = store.state.journeys.selections;
      const states = store.state.journeys.journeyStates;

      return selections.some(
        (j) => j !== props.journeyName && states[j] !== 'completed'
      );
    });

    const handleViewModule = () => {
      const route = moduleRoutes[props.journeyName];
      if (route) {
        router.push(route);
      } else {
        router.push({ name: 'Dashboard' });
      }
    };

    const handleNextJourney = async () => {
      const selections = store.state.journeys.selections;
      const states = store.state.journeys.journeyStates;

      const nextJourney = selections.find(
        (j) => j !== props.journeyName && states[j] !== 'completed'
      );

      if (nextJourney) {
        await store.dispatch('journeys/startJourney', nextJourney);
        await store.dispatch('journeys/fetchSteps', nextJourney);
        router.push({ name: 'OnboardingJourney', params: { journey: nextJourney } });
      }
    };

    const handleGoToDashboard = async () => {
      await store.dispatch('auth/fetchUser', null, { root: true });
      router.push({ name: 'Dashboard' });
    };

    return {
      loading,
      journeyLabel,
      viewModuleText,
      hasNextJourney,
      handleViewModule,
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

@keyframes checkmark-scale {
  0% {
    transform: scale(0);
    opacity: 0;
  }
  50% {
    transform: scale(1.2);
  }
  100% {
    transform: scale(1);
    opacity: 1;
  }
}

.checkmark-path {
  stroke-dasharray: 30;
  stroke-dashoffset: 30;
  animation: checkmark-draw 0.4s ease-out 0.3s forwards;
}

@keyframes checkmark-draw {
  to {
    stroke-dashoffset: 0;
  }
}
</style>
