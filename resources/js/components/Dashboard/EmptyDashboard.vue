<template>
  <div class="col-span-full">
    <div class="bg-white rounded-lg border border-light-gray p-8 text-center">
      <!-- Illustration area -->
      <div class="flex items-center justify-center gap-4 mb-6">
        <!-- Chart icon -->
        <svg class="w-10 h-10 text-raspberry-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
        </svg>
        <!-- Shield icon -->
        <svg class="w-12 h-12 text-violet-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
        </svg>
        <!-- Currency icon -->
        <svg class="w-10 h-10 text-spring-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      </div>

      <!-- Heading -->
      <h2 class="text-2xl font-bold text-horizon-500 mb-2">Welcome to Fynla</h2>
      <p class="text-sm text-neutral-500 mb-6">
        Let's get started with your financial plan
      </p>

      <!-- CTA Buttons -->
      <div class="flex flex-col sm:flex-row items-center justify-center gap-3 mb-6">
        <router-link
          :to="hasJourney ? '/onboarding' : '/onboarding/welcome'"
          v-preview-disabled
          class="inline-flex items-center px-6 py-3 bg-spring-500 text-white text-sm font-medium rounded-button hover:bg-spring-600 transition-colors"
        >
          {{ hasJourney ? 'Continue Journey' : 'Start a Planning Journey' }}
          <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
        </router-link>
        <router-link
          to="/goals"
          v-preview-disabled
          class="inline-flex items-center px-6 py-3 bg-light-pink-100 text-raspberry-500 text-sm font-medium rounded-button border border-light-pink-100 hover:bg-light-pink-200 transition-colors"
        >
          Set a Financial Goal
        </router-link>
      </div>

      <!-- Subtle explore hint -->
      <p class="text-xs text-neutral-500">
        Or explore on your own using the navigation menu
      </p>
    </div>
  </div>
</template>

<script>
import { mapState } from 'vuex';

export default {
  name: 'EmptyDashboard',
  computed: {
    ...mapState('lifeStage', ['currentStage']),
    hasJourney() {
      return !!this.currentStage;
    },
  },
};
</script>
