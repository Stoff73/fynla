<template>
  <transition name="expand">
    <div
      v-if="visible"
      class="bg-violet-50 border-b border-violet-200"
    >
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 relative">
        <!-- Close button -->
        <button
          class="absolute top-2 right-2 text-neutral-400 hover:text-horizon-500 transition-colors p-1"
          aria-label="Dismiss survey"
          @click="dismiss"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>

        <div class="flex flex-col sm:flex-row sm:items-center sm:gap-4 pr-6">
          <!-- Question text -->
          <p class="text-horizon-600 text-sm font-medium mb-2 sm:mb-0">
            Would you use a Fynla mobile app?
          </p>

          <!-- Response buttons -->
          <div class="flex gap-2">
            <button
              class="px-3 py-1 text-xs font-semibold rounded-md bg-spring-500 text-white hover:bg-spring-600 transition-colors"
              @click="respond('yes')"
            >
              Yes
            </button>
            <button
              class="px-3 py-1 text-xs font-semibold rounded-md bg-savannah-300 text-horizon-600 hover:bg-savannah-400 transition-colors"
              @click="respond('maybe')"
            >
              Maybe
            </button>
            <button
              class="px-3 py-1 text-xs font-semibold rounded-md bg-neutral-200 text-horizon-600 hover:bg-neutral-300 transition-colors"
              @click="respond('no')"
            >
              No
            </button>
          </div>
        </div>
      </div>
    </div>
  </transition>
</template>

<script>
import analyticsService from '@/services/analyticsService';

const STORAGE_KEY = 'fynla_mobile_survey_dismissed';

export default {
  name: 'MobileSurveyBanner',

  data() {
    return {
      visible: false,
    };
  },

  mounted() {
    if (!localStorage.getItem(STORAGE_KEY)) {
      this.visible = true;
    }
  },

  methods: {
    respond(answer) {
      this.trackResponse(answer);
      this.hideBanner();
    },

    dismiss() {
      this.trackResponse('dismissed');
      this.hideBanner();
    },

    hideBanner() {
      this.visible = false;
      localStorage.setItem(STORAGE_KEY, 'true');
    },

    trackResponse(answer) {
      analyticsService.trackEvent('mobile_survey_response', { answer });
    },
  },
};
</script>
