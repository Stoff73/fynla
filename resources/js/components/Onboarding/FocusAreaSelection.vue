<template>
  <div class="max-w-5xl mx-auto">
    <!-- Main Welcome Card -->
    <div class="bg-white rounded-lg border border-light-gray shadow-sm p-6 mb-6">
      <!-- Header with Welcome and Logo -->
      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-h2 font-display text-horizon-500 mb-2">
            Welcome {{ userName }}
          </h1>
          <p class="text-body text-raspberry-500 font-medium">
            Thank you for registering with Fynla!
          </p>
        </div>
        <img :src="logoImage" alt="Fynla" class="h-36 w-auto">
      </div>

      <!-- Why Complete Onboarding -->
      <div class="bg-raspberry-50 rounded-lg p-4 mb-6 border border-raspberry-100">
        <h3 class="text-body font-semibold text-horizon-500 mb-2 flex items-center">
          <svg class="w-5 h-5 text-raspberry-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
          </svg>
          Why Complete the Onboarding?
        </h3>
        <p class="text-body-sm text-horizon-500">
          The more information you provide, the better quality your personalised financial strategies and the more comprehensive your dashboards will be. Our planning tools use your data to generate tailored strategies for protection, savings, investments, retirement, and estate planning.
        </p>
      </div>

      <!-- What's Involved Section -->
      <div class="mb-6">
        <h3 class="text-body font-semibold text-horizon-500 mb-3 flex items-center">
          <svg class="w-5 h-5 text-neutral-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
          </svg>
          What's Involved in Onboarding?
        </h3>
        <p class="text-body-sm text-neutral-500 mb-4">
          The onboarding process collects information to power your financial planning dashboards. Here are the steps:
        </p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div class="flex items-start p-3 bg-eggshell-500 rounded-lg">
            <span class="flex items-center justify-center w-6 h-6 bg-raspberry-100 text-raspberry-700 rounded-full text-sm font-semibold mr-3 flex-shrink-0">1</span>
            <div>
              <p class="text-body-sm font-medium text-horizon-500">Personal Information</p>
              <p class="text-caption text-neutral-500">Name, date of birth, contact details</p>
            </div>
          </div>

          <div class="flex items-start p-3 bg-eggshell-500 rounded-lg">
            <span class="flex items-center justify-center w-6 h-6 bg-raspberry-100 text-raspberry-700 rounded-full text-sm font-semibold mr-3 flex-shrink-0">2</span>
            <div>
              <p class="text-body-sm font-medium text-horizon-500">Income Details</p>
              <p class="text-caption text-neutral-500">Employment, salary, other income sources</p>
            </div>
          </div>

          <div class="flex items-start p-3 bg-eggshell-500 rounded-lg">
            <span class="flex items-center justify-center w-6 h-6 bg-raspberry-100 text-raspberry-700 rounded-full text-sm font-semibold mr-3 flex-shrink-0">3</span>
            <div>
              <p class="text-body-sm font-medium text-horizon-500">Monthly Expenditure</p>
              <p class="text-caption text-neutral-500">Regular outgoings and commitments</p>
            </div>
          </div>

          <div class="flex items-start p-3 bg-eggshell-500 rounded-lg">
            <span class="flex items-center justify-center w-6 h-6 bg-raspberry-100 text-raspberry-700 rounded-full text-sm font-semibold mr-3 flex-shrink-0">4</span>
            <div>
              <p class="text-body-sm font-medium text-horizon-500">Assets</p>
              <p class="text-caption text-neutral-500">Properties, savings, investments, pensions</p>
            </div>
          </div>

          <div class="flex items-start p-3 bg-eggshell-500 rounded-lg">
            <span class="flex items-center justify-center w-6 h-6 bg-raspberry-100 text-raspberry-700 rounded-full text-sm font-semibold mr-3 flex-shrink-0">5</span>
            <div>
              <p class="text-body-sm font-medium text-horizon-500">Liabilities</p>
              <p class="text-caption text-neutral-500">Mortgages, loans, credit cards</p>
            </div>
          </div>

          <div class="flex items-start p-3 bg-eggshell-500 rounded-lg">
            <span class="flex items-center justify-center w-6 h-6 bg-raspberry-100 text-raspberry-700 rounded-full text-sm font-semibold mr-3 flex-shrink-0">6</span>
            <div>
              <p class="text-body-sm font-medium text-horizon-500">Protection Policies</p>
              <p class="text-caption text-neutral-500">Life insurance, income protection, critical illness</p>
            </div>
          </div>

          <div class="flex items-start p-3 bg-eggshell-500 rounded-lg">
            <span class="flex items-center justify-center w-6 h-6 bg-raspberry-100 text-raspberry-700 rounded-full text-sm font-semibold mr-3 flex-shrink-0">7</span>
            <div>
              <p class="text-body-sm font-medium text-horizon-500">Family Information</p>
              <p class="text-caption text-neutral-500">Spouse/partner, dependants</p>
            </div>
          </div>

          <div class="flex items-start p-3 bg-eggshell-500 rounded-lg">
            <span class="flex items-center justify-center w-6 h-6 bg-raspberry-100 text-raspberry-700 rounded-full text-sm font-semibold mr-3 flex-shrink-0">8</span>
            <div>
              <p class="text-body-sm font-medium text-horizon-500">Estate Planning</p>
              <p class="text-caption text-neutral-500">Will status, trusts, domicile information</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Skip Ahead Note -->
      <div class="bg-eggshell-500 rounded-lg p-4 mb-6 border border-light-gray">
        <p class="text-body-sm text-horizon-500 flex items-start">
          <svg class="w-5 h-5 text-neutral-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 9l3 3m0 0l-3 3m3-3H8m13 0a9 9 0 11-18 0 9 9 0 0118 0z"></path>
          </svg>
          <span><strong>Skip ahead options available:</strong> Each step has a skip option if you don't have certain information to hand. You can always return to complete sections later from your profile or the relevant dashboard.</span>
        </p>
      </div>

      <!-- Trial Information -->
      <div class="bg-violet-100 rounded-lg p-4 mb-6">
        <h3 class="text-body font-semibold text-horizon-500 mb-2 flex items-center">
          <svg class="w-5 h-5 text-violet-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
          </svg>
          Your Free Trial
        </h3>
        <ul class="text-body-sm text-horizon-500 space-y-2">
          <li class="flex">
            <span class="font-semibold text-violet-700 flex-shrink-0 w-32">7-Day Trial:</span>
            <span>You have full access to all features for 7 days from registration.</span>
          </li>
          <li class="flex">
            <span class="font-semibold text-violet-700 flex-shrink-0 w-32">30-Day Window:</span>
            <span>After your trial ends, you have 30 days to subscribe. If you don't subscribe within this period, your data will be permanently deleted from our database.</span>
          </li>
        </ul>
      </div>

      <!-- Continue Button -->
      <div class="text-center space-y-4">
        <button
          @click="selectFocusArea('estate')"
          :disabled="loading"
          class="inline-flex items-center px-8 py-3 border border-transparent text-body font-medium rounded-button text-white bg-raspberry-500 hover:bg-raspberry-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-violet-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
        >
          <svg v-if="!loading" class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
          </svg>
          <svg v-else class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          {{ loading ? 'Loading...' : 'Start Onboarding' }}
        </button>

        <div>
          <button
            @click="skipOnboarding"
            :disabled="loading"
            class="inline-flex items-center text-body-sm font-medium text-neutral-500 hover:text-horizon-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          >
            Skip onboarding and go to dashboard
            <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
            </svg>
          </button>
        </div>
      </div>
    </div>

    <div v-if="error" class="mt-6 p-4 bg-error-50 border border-error-200 rounded-lg">
      <p class="text-body-sm text-error-700">{{ error }}</p>
    </div>
  </div>
</template>

<script>
import { ref, computed } from 'vue';
import { useStore } from 'vuex';
import { useRouter } from 'vue-router';

export default {
  name: 'FocusAreaSelection',

  emits: ['selected'],

  setup(props, { emit }) {
    const store = useStore();
    const router = useRouter();
    const loading = ref(false);
    const error = ref(null);

    const userName = computed(() => {
      const user = store.state.auth.user;
      if (user && user.name) {
        // Get first name only
        return user.name.split(' ')[0];
      }
      return '';
    });

    const selectFocusArea = async (focusArea) => {
      loading.value = true;
      error.value = null;

      try {
        await store.dispatch('onboarding/setFocusArea', focusArea);
        emit('selected', focusArea);
      } catch (err) {
        error.value = err.message || 'Failed to set focus area. Please try again.';
      } finally {
        loading.value = false;
      }
    };

    const skipOnboarding = () => {
      router.push({ name: 'Dashboard' });
    };

    return {
      loading,
      error,
      userName,
      selectFocusArea,
      skipOnboarding,
      logoImage: '/images/logos/logoTransparent.png',
    };
  },
};
</script>
