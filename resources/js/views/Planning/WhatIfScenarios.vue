<template>
  <AppLayout>
    <div class="module-gradient py-6">
      <ModuleStatusBar />
      <div class="">
        <!-- Not married / no spouse -->
        <div v-if="!isMarriedWithSpouse" class="text-center py-16 bg-light-blue-100 border border-light-gray rounded-lg">
          <div class="mx-auto w-16 h-16 rounded-full bg-light-blue-200 flex items-center justify-center mb-4">
            <svg class="w-8 h-8 text-horizon-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 014.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0112 15a9.065 9.065 0 00-6.23.693L5 14.5m14.8.8l1.402 1.402c1.232 1.232.65 3.318-1.067 3.611A48.309 48.309 0 0112 21c-2.773 0-5.491-.235-8.135-.687-1.718-.293-2.3-2.379-1.067-3.61L5 14.5" />
            </svg>
          </div>
          <h2 class="text-lg font-bold text-horizon-500 mb-2">Household Scenarios Require a Partner</h2>
          <p class="text-neutral-500 max-w-md mx-auto">
            What If Scenarios are available for married couples with linked partner accounts. Add your spouse details in your profile to access these planning tools.
          </p>
          <div class="flex flex-col sm:flex-row items-center justify-center gap-3 mt-4">
            <router-link
              to="/profile"
              class="inline-flex items-center px-4 py-2 bg-horizon-500 text-white text-sm font-medium rounded-button hover:bg-horizon-600 transition-colors"
            >
              Go to Profile
            </router-link>
            <button
              @click="openAiChat"
              class="inline-flex items-center px-4 py-2 bg-horizon-500 text-white text-sm font-medium rounded-button hover:bg-horizon-600 transition-colors"
            >
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
              </svg>
              Start a chat
            </button>
          </div>
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

  methods: {
    openAiChat() {
      this.$store.dispatch('aiChat/open');
    },
  },
};
</script>
