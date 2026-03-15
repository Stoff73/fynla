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
        {{ headingText }}
      </h2>
      <p class="text-body text-neutral-500">
        {{ subheadingText }}
      </p>
    </div>

    <!-- Journey Benefits — one section per completed journey -->
    <div
      v-for="journey in completedJourneyKeys"
      :key="journey"
      class="bg-white rounded-lg shadow-sm border border-light-gray p-6 mb-6 text-left"
    >
      <h3 class="text-h4 font-display text-horizon-500 mb-4">
        {{ journeyLabels[journey] }} — what this unlocks
      </h3>
      <ul class="space-y-4">
        <li
          v-for="benefit in journeyBenefits[journey]"
          :key="benefit.title"
          class="flex items-start"
        >
          <div class="flex-shrink-0 w-8 h-8 bg-spring-100 rounded-full flex items-center justify-center mt-0.5 mr-3">
            <svg class="w-4 h-4 text-spring-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="benefit.icon" />
            </svg>
          </div>
          <div>
            <p class="text-body font-medium text-horizon-500">{{ benefit.title }}</p>
            <p class="text-body-sm text-neutral-500">{{ benefit.description }}</p>
          </div>
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
import { ref, computed } from 'vue';
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

  setup(props) {
    const store = useStore();
    const router = useRouter();
    const loading = ref(false);

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
      budgeting: { path: '/net-worth/cash' },
      protection: { name: 'Protection' },
      investment: { path: '/net-worth/investments' },
      retirement: { path: '/net-worth/retirement' },
      estate: { name: 'Estate' },
      family: { name: 'UserProfile' },
      business: { path: '/net-worth/business' },
      goals: { name: 'Goals' },
    };

    // Benefits data for each journey
    const journeyBenefits = {
      budgeting: [
        {
          title: 'Better understanding of your spending habits',
          description: 'See exactly where your money goes each month and identify areas to improve',
          icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
        },
        {
          title: 'Clearer financial goals',
          description: 'Know how much surplus income you have available to put towards savings and goals',
          icon: 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6',
        },
        {
          title: 'Emergency fund tracking',
          description: 'Monitor whether your savings provide enough cover for unexpected expenses',
          icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
        },
        {
          title: 'Personalised savings recommendations',
          description: 'Get tailored advice on how to make the most of your money, including tax-free options',
          icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        },
      ],
      protection: [
        {
          title: 'Protection gap analysis',
          description: 'See how your existing cover compares to what your family would actually need',
          icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
        },
        {
          title: 'Life cover recommendations',
          description: 'Personalised calculations for how much life insurance you need based on your debts, income, and dependants',
          icon: 'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z',
        },
        {
          title: 'Income protection insights',
          description: 'Understand how much of your income would be at risk if you were unable to work',
          icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        },
        {
          title: 'Mortgage protection review',
          description: 'Ensure your mortgage would be paid off if the worst happened, protecting your family\'s home',
          icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
        },
      ],
      investment: [
        {
          title: 'Portfolio overview',
          description: 'See all your investments in one place with clear performance tracking',
          icon: 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6',
        },
        {
          title: 'Tax-efficient investing',
          description: 'Understand your Individual Savings Account and pension contribution allowances',
          icon: 'M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2zM10 8.5a.5.5 0 11-1 0 .5.5 0 011 0zm5 5a.5.5 0 11-1 0 .5.5 0 011 0z',
        },
      ],
      retirement: [
        {
          title: 'Retirement income forecast',
          description: 'See a projection of your expected retirement income from all pension sources',
          icon: 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6',
        },
        {
          title: 'Pension contribution analysis',
          description: 'Understand how much you are saving and whether it is on track for your goals',
          icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        },
      ],
      estate: [
        {
          title: 'Estate value summary',
          description: 'See the total value of your estate and how inheritance tax may apply',
          icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
        },
        {
          title: 'Tax planning opportunities',
          description: 'Identify ways to reduce your inheritance tax liability and protect your family\'s inheritance',
          icon: 'M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2zM10 8.5a.5.5 0 11-1 0 .5.5 0 011 0zm5 5a.5.5 0 11-1 0 .5.5 0 011 0z',
        },
      ],
    };

    // Determine which journeys just completed — the current journey plus any already completed
    const completedJourneyKeys = computed(() => {
      const selections = store.state.journeys?.selections || [];
      const states = store.state.journeys?.journeyStates || {};
      const current = props.journeyName;

      // Include the current journey being completed + any previously completed journeys from selections
      const completed = [];

      // Add journeys in selection order for consistency
      for (const j of selections) {
        if (j === current || states[j] === 'completed') {
          if (journeyBenefits[j]) {
            completed.push(j);
          }
        }
      }

      // Ensure current journey is included even if not in selections
      if (current && !completed.includes(current) && journeyBenefits[current]) {
        completed.push(current);
      }

      return completed;
    });

    const headingText = computed(() => {
      const keys = completedJourneyKeys.value;
      if (keys.length === 1) {
        return `Your ${journeyLabels[keys[0]] || 'Financial Planning'} is ready`;
      }
      const labels = keys.map((k) => journeyLabels[k] || k);
      return `Your ${labels.join(' & ')} setup is complete`;
    });

    const subheadingText = computed(() => {
      const keys = completedJourneyKeys.value;
      if (keys.length === 1) {
        return `You have taken the first step towards better financial clarity. Here is what ${journeyLabels[keys[0]].toLowerCase()} unlocks for you.`;
      }
      return 'You have taken a great step towards better financial clarity. Here is what your journeys unlock for you.';
    });

    const viewModuleText = computed(() => {
      // Primary CTA goes to the most recently completed journey's module
      const label = journeyLabels[props.journeyName];
      return label ? `View your ${label} Dashboard` : 'View Dashboard';
    });

    const hasNextJourney = computed(() => {
      const selections = store.state.journeys?.selections || [];
      const states = store.state.journeys?.journeyStates || {};

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
      loading.value = true;
      const selections = store.state.journeys?.selections || [];
      const states = store.state.journeys?.journeyStates || {};

      const nextJourney = selections.find(
        (j) => j !== props.journeyName && states[j] !== 'completed'
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
      journeyLabels,
      journeyBenefits,
      completedJourneyKeys,
      headingText,
      subheadingText,
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

.checkmark-path {
  stroke-dasharray: 30;
  stroke-dashoffset: 30;
  animation: checkmark-draw 0.4s ease-out 0.3s forwards;
}
</style>
