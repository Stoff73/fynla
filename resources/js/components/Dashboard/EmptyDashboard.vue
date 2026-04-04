<template>
  <div class="col-span-full flex justify-center">
    <div class="w-full lg:w-[60%] bg-gradient-to-br from-horizon-500 to-raspberry-500 rounded-2xl p-10 sm:p-14 text-center shadow-lg">
      <!-- Heading -->
      <h2 class="text-3xl sm:text-4xl font-display text-white mb-3">Welcome to Fynla</h2>
      <p class="text-lg text-white/70 mb-8">
        Let's get started with your financial plan
      </p>

      <!-- CTA Button -->
      <div class="mb-8">
        <router-link
          :to="hasJourney ? '/onboarding' : '/onboarding/welcome'"
          v-preview-disabled
          class="inline-flex items-center px-8 py-3.5 bg-spring-500 text-white text-base font-medium rounded-button hover:bg-spring-600 transition-colors shadow-md"
        >
          {{ hasJourney ? 'Continue Journey' : 'Start a Planning Journey' }}
          <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
        </router-link>
      </div>

      <!-- Alternative options -->
      <p class="text-sm text-white/60">
        Or
        <router-link to="/goals" v-preview-disabled class="text-white hover:text-white/80 font-medium underline">set a financial goal</router-link>,
        <button @click="openFynChat" class="text-white hover:text-white/80 font-medium underline">ask Fyn for help</button>
        or explore on your own using the navigation menu
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
  methods: {
    openFynChat() {
      window.dispatchEvent(new Event('fyn-toggle-chat'));
    },
  },
};
</script>
