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

      <!-- Quick Setup Explanation -->
      <div class="bg-raspberry-50 rounded-lg p-4 mb-6 border border-raspberry-100">
        <h3 class="text-body font-semibold text-horizon-500 mb-2 flex items-center">
          <svg class="w-5 h-5 text-raspberry-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
          </svg>
          Quick Setup - 3 Steps to Get Started
        </h3>
        <p class="text-body-sm text-horizon-500">
          Get to your personalised dashboard in under 2 minutes. We just need some basic information to get started, then you can add detail from your dashboard at your own pace.
        </p>
      </div>

      <!-- 3-Step Quick Overview -->
      <div class="mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
          <div class="flex items-start p-4 bg-eggshell-500 rounded-lg border border-light-gray">
            <span class="flex items-center justify-center w-8 h-8 bg-raspberry-500 text-white rounded-full text-sm font-bold mr-3 flex-shrink-0">1</span>
            <div>
              <p class="text-body-sm font-semibold text-horizon-500">Personal Information</p>
              <p class="text-caption text-neutral-500">Date of birth, gender, address</p>
            </div>
          </div>

          <div class="flex items-start p-4 bg-eggshell-500 rounded-lg border border-light-gray">
            <span class="flex items-center justify-center w-8 h-8 bg-raspberry-500 text-white rounded-full text-sm font-bold mr-3 flex-shrink-0">2</span>
            <div>
              <p class="text-body-sm font-semibold text-horizon-500">Income Details</p>
              <p class="text-caption text-neutral-500">Employment and salary</p>
            </div>
          </div>

          <div class="flex items-start p-4 bg-eggshell-500 rounded-lg border border-light-gray">
            <span class="flex items-center justify-center w-8 h-8 bg-raspberry-500 text-white rounded-full text-sm font-bold mr-3 flex-shrink-0">3</span>
            <div>
              <p class="text-body-sm font-semibold text-horizon-500">Financial Overview</p>
              <p class="text-caption text-neutral-500">What areas apply to you</p>
            </div>
          </div>
        </div>
      </div>

      <!-- What You Get -->
      <div class="bg-eggshell-500 rounded-lg p-4 mb-6 border border-light-gray">
        <p class="text-body-sm text-horizon-500 flex items-start">
          <svg class="w-5 h-5 text-spring-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
          </svg>
          <span>After these 3 steps, your dashboard will show personalised cards guiding you to add more detail for each financial area at your own pace.</span>
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

      <!-- Action Buttons -->
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
          {{ loading ? 'Loading...' : 'Quick Setup (2 minutes)' }}
        </button>

        <div class="flex items-center justify-center gap-6">
          <button
            @click="startFullOnboarding"
            :disabled="loading"
            class="inline-flex items-center text-body-sm font-medium text-horizon-500 hover:text-raspberry-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors underline"
          >
            Complete Full Setup Instead
          </button>

          <button
            @click="skipOnboarding"
            :disabled="loading"
            class="inline-flex items-center text-body-sm font-medium text-neutral-500 hover:text-horizon-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          >
            Skip to dashboard
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

    const startFullOnboarding = async () => {
      loading.value = true;
      error.value = null;

      try {
        await store.dispatch('onboarding/setFocusArea', 'estate');
        // Navigate to full mode onboarding
        router.push({ name: 'OnboardingFull' });
      } catch (err) {
        error.value = err.message || 'Failed to start. Please try again.';
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
      startFullOnboarding,
      skipOnboarding,
      logoImage: '/images/logos/LogoHiResFynlaDark.png',
    };
  },
};
</script>
