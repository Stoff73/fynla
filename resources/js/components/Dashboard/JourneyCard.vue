<template>
  <div
    class="rounded-lg border p-5 transition-all duration-200"
    :class="cardClasses"
  >
    <div class="flex items-start gap-3">
      <!-- Journey icon -->
      <div
        class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0"
        :class="iconBgClass"
      >
        <svg
          v-if="journey.status === 'completed'"
          class="w-5 h-5 text-spring-600"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
        <svg
          v-else
          class="w-5 h-5"
          :class="iconClass"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="journeyIcon" />
        </svg>
      </div>

      <!-- Content -->
      <div class="flex-1 min-w-0">
        <h4 class="text-sm font-semibold text-horizon-500">{{ journeyLabel }}</h4>
        <p class="text-xs text-neutral-500 mt-0.5">{{ statusText }}</p>

        <!-- Progress bar (in progress only) -->
        <div v-if="journey.status === 'in_progress' && journey.progress != null" class="mt-3">
          <div class="w-full bg-savannah-200 rounded-full h-1.5">
            <div
              class="h-1.5 rounded-full bg-raspberry-500 transition-all duration-300"
              :style="{ width: Math.min(journey.progress, 100) + '%' }"
            ></div>
          </div>
          <span class="text-xs text-neutral-500 mt-1 block">{{ journey.progress }}% complete</span>
        </div>

        <!-- CTA -->
        <router-link
          :to="ctaRoute"
          v-preview-disabled
          class="inline-flex items-center mt-3 px-4 py-2 text-sm font-medium rounded-button transition-colors"
          :class="ctaClasses"
        >
          {{ ctaText }}
          <svg class="w-4 h-4 ml-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
        </router-link>
      </div>
    </div>
  </div>
</template>

<script>
const MODULE_ROUTES = {
  budgeting: '/net-worth/cash',
  protection: '/protection',
  investment: '/net-worth/investments',
  retirement: '/retirement',
  estate: '/estate',
  family: '/profile',
  business: '/net-worth/business',
  goals: '/goals',
};

const JOURNEY_LABELS = {
  budgeting: 'Budgeting',
  protection: 'Protection',
  investment: 'Investment',
  retirement: 'Retirement',
  estate: 'Estate Planning',
  family: 'Family',
  business: 'Business',
  goals: 'Goals',
};

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
  name: 'JourneyCard',

  props: {
    journey: {
      type: Object,
      required: true,
      validator: (v) => v && v.name && v.status,
    },
  },

  computed: {
    journeyLabel() {
      return JOURNEY_LABELS[this.journey.name] || this.journey.name;
    },

    journeyIcon() {
      return JOURNEY_ICONS[this.journey.name] || JOURNEY_ICONS.goals;
    },

    cardClasses() {
      switch (this.journey.status) {
        case 'completed':
          return 'bg-spring-50 border-spring-200';
        case 'in_progress':
          return 'bg-savannah-100 border-savannah-200';
        default:
          return 'bg-white border-light-gray';
      }
    },

    iconBgClass() {
      switch (this.journey.status) {
        case 'completed':
          return 'bg-spring-100';
        case 'in_progress':
          return 'bg-raspberry-100';
        default:
          return 'bg-savannah-100';
      }
    },

    iconClass() {
      switch (this.journey.status) {
        case 'in_progress':
          return 'text-raspberry-500';
        default:
          return 'text-neutral-500';
      }
    },

    statusText() {
      switch (this.journey.status) {
        case 'completed':
          return `${this.journeyLabel} journey complete`;
        case 'in_progress':
          return `Continue your ${this.journeyLabel.toLowerCase()} journey`;
        default:
          return `Start your ${this.journeyLabel.toLowerCase()} journey`;
      }
    },

    ctaRoute() {
      if (this.journey.status === 'completed') {
        return MODULE_ROUTES[this.journey.name] || '/dashboard';
      }
      return `/onboarding/journey/${this.journey.name}`;
    },

    ctaText() {
      switch (this.journey.status) {
        case 'completed':
          return 'View Dashboard';
        case 'in_progress':
          return 'Continue';
        default:
          return 'Start';
      }
    },

    ctaClasses() {
      if (this.journey.status === 'completed') {
        return 'text-spring-700 bg-spring-100 hover:bg-spring-200';
      }
      return 'text-white bg-raspberry-500 hover:bg-raspberry-600';
    },
  },
};
</script>
