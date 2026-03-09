<template>
  <div class="max-w-5xl mx-auto">
    <div class="bg-white rounded-lg border border-light-gray shadow-sm p-6 mb-6">
      <!-- Welcome Header -->
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

      <!-- Focus Area Section -->
      <div class="mb-6">
        <h3 class="text-h3 font-semibold text-horizon-500 mb-2">What would you like to focus on?</h3>
        <p class="text-body-sm text-neutral-500">Choose one or more areas — we'll only ask for the information you need.</p>
      </div>

      <!-- Focus Area Grid -->
      <FocusAreaGrid
        :selections="selectedJourneys"
        @update:selections="handleSelectionsChange"
      />

      <!-- Journey Preview -->
      <JourneyPreview
        v-if="selectedJourneys.length > 0"
        :selections="selectedJourneys"
      />

      <!-- Error Message -->
      <div v-if="error" class="mb-4 p-3 bg-raspberry-50 border border-raspberry-200 rounded-lg">
        <p class="text-body-sm text-raspberry-600">{{ error }}</p>
      </div>

      <!-- Action Buttons -->
      <div class="text-center space-y-4 mt-6">
        <button
          @click="startJourneys"
          :disabled="selectedJourneys.length === 0 || loading"
          class="inline-flex items-center px-8 py-3 border border-transparent text-body font-medium rounded-button text-white bg-raspberry-500 hover:bg-raspberry-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-violet-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
        >
          <svg v-if="loading" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          {{ startButtonText }}
        </button>

        <div>
          <button
            @click="skipOnboarding"
            :disabled="loading"
            class="inline-flex items-center text-body-sm font-medium text-neutral-500 hover:text-raspberry-500 transition-colors"
          >
            Skip to Dashboard
            <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
            </svg>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed } from 'vue';
import { useStore } from 'vuex';
import { useRouter } from 'vue-router';
import FocusAreaGrid from './FocusAreaGrid.vue';
import JourneyPreview from './JourneyPreview.vue';

export default {
  name: 'FocusAreaSelection',
  components: { FocusAreaGrid, JourneyPreview },
  emits: ['selected'],

  setup(props, { emit }) {
    const store = useStore();
    const router = useRouter();
    const loading = ref(false);
    const error = ref(null);
    const selectedJourneys = ref([]);

    const userName = computed(() => {
      const user = store.state.auth.user;
      return user?.name?.split(' ')[0] || '';
    });

    const startButtonText = computed(() => {
      if (loading.value) return 'Loading...';
      if (selectedJourneys.value.length === 0) return 'Select a focus area to begin';
      if (selectedJourneys.value.length === 1) return 'Start Your Journey';
      return 'Start Your Journeys';
    });

    const handleSelectionsChange = (selections) => {
      selectedJourneys.value = selections;
    };

    const startJourneys = async () => {
      if (selectedJourneys.value.length === 0) return;
      loading.value = true;
      error.value = null;

      try {
        // Save selections to the journeys store
        await store.dispatch('journeys/saveSelections', selectedJourneys.value);

        // Start the first selected journey
        const firstJourney = selectedJourneys.value[0];
        await store.dispatch('journeys/startJourney', firstJourney);
        await store.dispatch('journeys/fetchSteps', firstJourney);

        // Also set focus area in old onboarding store for compatibility
        store.commit('onboarding/SET_FOCUS_AREA', firstJourney);

        // Navigate to the journey wizard
        router.push({ name: 'OnboardingJourney', params: { journey: firstJourney } });
      } catch (err) {
        error.value = err.message || 'Failed to save. Please try again.';
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
      selectedJourneys,
      userName,
      startButtonText,
      handleSelectionsChange,
      startJourneys,
      skipOnboarding,
      logoImage: '/images/logos/LogoHiResFynlaDark.png',
    };
  },
};
</script>
