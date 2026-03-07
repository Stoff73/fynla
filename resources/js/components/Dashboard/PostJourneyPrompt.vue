<template>
  <transition
    enter-active-class="transition-all duration-300 ease-out"
    enter-from-class="opacity-0 -translate-y-2"
    enter-to-class="opacity-100 translate-y-0"
    leave-active-class="transition-all duration-300 ease-in"
    leave-from-class="opacity-100 translate-y-0"
    leave-to-class="opacity-0 -translate-y-2"
  >
    <div
      v-if="visible"
      class="mb-6 bg-spring-50 border border-spring-200 rounded-lg p-4 shadow-sm"
    >
      <div class="flex items-start gap-4">
        <!-- Journey icon -->
        <div class="flex-shrink-0">
          <div class="w-10 h-10 bg-spring-100 rounded-full flex items-center justify-center">
            <svg class="w-5 h-5 text-spring-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="journeyIcon" />
            </svg>
          </div>
        </div>

        <!-- Content -->
        <div class="flex-1">
          <p class="text-sm text-horizon-500">{{ prompt.message }}</p>
          <div class="mt-3">
            <router-link
              :to="prompt.cta_link"
              v-preview-disabled
              class="inline-flex items-center px-4 py-2 bg-raspberry-500 text-white text-sm font-medium rounded-button hover:bg-raspberry-600 transition-colors"
            >
              {{ prompt.cta_text }}
              <svg class="w-4 h-4 ml-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
            </router-link>
          </div>
        </div>

        <!-- Dismiss button -->
        <button
          @click="dismiss"
          class="flex-shrink-0 text-spring-400 hover:text-spring-600 transition-colors"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
    </div>
  </transition>
</template>

<script>
import { mapActions } from 'vuex';

const JOURNEY_ICONS = {
  budgeting: 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z',
  protection: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
  investment: 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6',
  retirement: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
  estate: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
  family: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
  business: 'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
  goals: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
};

export default {
  name: 'PostJourneyPrompt',

  props: {
    prompt: {
      type: Object,
      required: true,
      validator: (v) => v && v.id && v.message && v.cta_text && v.cta_link,
    },
  },

  data() {
    return {
      visible: true,
    };
  },

  computed: {
    journeyIcon() {
      return JOURNEY_ICONS[this.prompt.journey] || JOURNEY_ICONS.goals;
    },
  },

  methods: {
    ...mapActions('journeys', ['dismissPrompt']),

    async dismiss() {
      this.visible = false;
      try {
        await this.dismissPrompt(this.prompt.id);
      } catch (error) {
        // Prompt already hidden visually; failure is non-critical
      }
      this.$emit('dismissed', this.prompt.id);
    },
  },
};
</script>
