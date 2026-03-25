<template>
  <AppLayout>
    <div class="module-gradient py-6">
      <ModuleStatusBar />
      <div class="">
        <!-- Header -->
        <div class="mb-8">
          <button
            @click="$router.push('/dashboard')"
            class="detail-inline-back mb-4"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Back to Dashboard
          </button>
          <h1 class="text-3xl font-black text-horizon-500 mb-2">What If Scenarios</h1>
          <p class="text-neutral-500">
            Explore household planning scenarios and understand the financial impact of life-changing events
          </p>
        </div>

        <!-- Not married / no spouse -->
        <div v-if="!isMarriedWithSpouse" class="text-center py-16">
          <div class="mx-auto w-16 h-16 rounded-full bg-savannah-100 flex items-center justify-center mb-4">
            <svg class="w-8 h-8 text-horizon-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 014.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0112 15a9.065 9.065 0 00-6.23.693L5 14.5m14.8.8l1.402 1.402c1.232 1.232.65 3.318-1.067 3.611A48.309 48.309 0 0112 21c-2.773 0-5.491-.235-8.135-.687-1.718-.293-2.3-2.379-1.067-3.61L5 14.5" />
            </svg>
          </div>
          <h2 class="text-lg font-bold text-horizon-500 mb-2">Household Scenarios Require a Partner</h2>
          <p class="text-neutral-500 max-w-md mx-auto">
            What If Scenarios are available for married couples with linked partner accounts. Add your spouse details in your profile to access these planning tools.
          </p>
          <router-link
            to="/profile"
            class="inline-flex items-center mt-4 px-4 py-2 bg-raspberry-500 text-white text-sm font-medium rounded-button hover:bg-raspberry-600 transition-colors"
          >
            Go to Profile
          </router-link>
        </div>

        <!-- Scenarios content -->
        <template v-else>
          <!-- Death of Spouse Scenario Section -->
          <section class="mb-8">
            <h2 class="text-xl font-bold text-horizon-500 mb-4">Death of Spouse Scenario</h2>
            <p class="text-sm text-neutral-500 mb-4">
              Understand the financial impact if you or your partner were to pass away, including inheritance tax implications, income changes, and pension consequences.
            </p>
            <div class="grid grid-cols-1 lg:grid-cols-1 gap-4">
              <DeathOfSpouseScenario />
            </div>
          </section>
        </template>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import { mapGetters } from 'vuex';
import AppLayout from '@/layouts/AppLayout.vue';
import DeathOfSpouseScenario from '@/components/Dashboard/DeathOfSpouseScenario.vue';
import ModuleStatusBar from '@/components/Shared/ModuleStatusBar.vue';

export default {
  name: 'WhatIfScenarios',

  components: {
    AppLayout,
    DeathOfSpouseScenario,
    ModuleStatusBar,
  },

  computed: {
    ...mapGetters('auth', ['currentUser']),

    isMarriedWithSpouse() {
      const user = this.currentUser;
      return user && user.marital_status === 'married' && user.spouse_id;
    },
  },
};
</script>
