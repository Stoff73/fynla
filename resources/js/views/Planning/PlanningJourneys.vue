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
          <h1 class="text-3xl font-black text-horizon-500 mb-2">Your Planning Journeys</h1>
          <p class="text-neutral-500">
            Track your financial planning progress across each journey you've selected
          </p>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="flex justify-center py-12">
          <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
        </div>

        <!-- No journeys -->
        <div v-else-if="!hasJourneySelections" class="text-center py-16">
          <div class="mx-auto w-16 h-16 rounded-full bg-savannah-100 flex items-center justify-center mb-4">
            <svg class="w-8 h-8 text-horizon-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 6.75V15m0 0l-3 3m3-3l3 3m-8.25 0h13.5A2.25 2.25 0 0021 15.75V6.75A2.25 2.25 0 0018.75 4.5H5.25A2.25 2.25 0 003 6.75v9a2.25 2.25 0 002.25 2.25z" />
            </svg>
          </div>
          <h2 class="text-lg font-bold text-horizon-500 mb-2">No Journeys Selected</h2>
          <p class="text-neutral-500 max-w-md mx-auto">
            Planning journeys help you work through key financial areas step by step. Select your journeys during onboarding to get started.
          </p>
        </div>

        <!-- Journey Cards Grid -->
        <div v-if="hasJourneySelections && journeyCards.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <JourneyCard
            v-for="journey in journeyCards"
            :key="journey.name"
            :journey="journey"
          />
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import { mapGetters, mapState, mapActions } from 'vuex';
import AppLayout from '@/layouts/AppLayout.vue';
import JourneyCard from '@/components/Dashboard/JourneyCard.vue';
import ModuleStatusBar from '@/components/Shared/ModuleStatusBar.vue';

export default {
  name: 'PlanningJourneys',

  components: {
    AppLayout,
    JourneyCard,
    ModuleStatusBar,
  },

  computed: {
    ...mapGetters('auth', ['currentUser']),
    ...mapState('journeys', ['selections', 'journeyStates', 'loading']),

    hasJourneySelections() {
      return this.currentUser?.journey_selections?.length > 0;
    },

    journeyCards() {
      const selections = this.selections || [];
      const states = this.journeyStates || {};
      return selections.map((name) => {
        const stateData = states[name] || {};
        const status = typeof stateData === 'string' ? stateData : (stateData.status || 'not_started');
        return {
          name,
          status,
          progress: stateData.progress || null,
        };
      });
    },

  },

  methods: {
    ...mapActions('journeys', ['fetchSelections']),
  },

  async created() {
    if (this.hasJourneySelections) {
      try {
        await this.fetchSelections();
      } catch {
        // Journey data is non-critical
      }
    }
  },
};
</script>
