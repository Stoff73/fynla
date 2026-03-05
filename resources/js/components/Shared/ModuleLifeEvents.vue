<template>
  <div v-if="events && events.length > 0" class="module-life-events">
    <!-- Collapsible Header -->
    <button
      @click="isExpanded = !isExpanded"
      class="w-full flex items-center justify-between py-3 px-4 bg-savannah-100 rounded-lg hover:bg-savannah-100 transition-colors duration-200"
    >
      <div class="flex items-center gap-2">
        <svg class="w-5 h-5 text-neutral-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
        </svg>
        <span class="text-sm font-semibold text-horizon-500">Upcoming Life Events</span>
        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-raspberry-100 text-raspberry-700 text-xs font-bold">
          {{ events.length }}
        </span>
      </div>
      <div class="flex items-center gap-3">
        <!-- Net impact summary -->
        <span
          v-if="impactSummary && impactSummary.net_impact !== 0"
          class="text-xs font-medium"
          :class="impactSummary.net_impact > 0 ? 'text-spring-600' : 'text-raspberry-600'"
        >
          {{ impactSummary.net_impact > 0 ? '+' : '' }}{{ formatCurrency(impactSummary.net_impact) }} net
        </span>
        <svg
          class="w-4 h-4 text-horizon-400 transition-transform duration-200"
          :class="{ 'rotate-180': isExpanded }"
          xmlns="http://www.w3.org/2000/svg"
          fill="none"
          viewBox="0 0 24 24"
          stroke-width="2"
          stroke="currentColor"
        >
          <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
        </svg>
      </div>
    </button>

    <!-- Event List -->
    <transition name="expand">
      <div v-if="isExpanded" class="mt-3 space-y-2">
        <div
          v-for="event in sortedEvents"
          :key="event.id"
          class="flex items-start gap-3 p-3 rounded-lg border border-light-gray hover:border-light-gray transition-colors duration-150"
        >
          <!-- Impact indicator -->
          <div
            class="flex-shrink-0 mt-0.5 w-2 h-2 rounded-full"
            :class="event.impact_type === 'income' ? 'bg-spring-500' : 'bg-raspberry-500'"
          ></div>

          <!-- Event details -->
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 mb-0.5">
              <span class="text-sm font-medium text-horizon-500 truncate">{{ event.event_name }}</span>
              <span
                class="flex-shrink-0 text-xs px-1.5 py-0.5 rounded-full font-medium"
                :class="certaintyBadgeClass(event.certainty)"
              >
                {{ certaintyLabel(event.certainty) }}
              </span>
            </div>
            <p class="text-xs text-neutral-500 mb-1">{{ event.module_context }}</p>
            <div class="flex items-center gap-3 text-xs text-neutral-500">
              <span>{{ formatEventDate(event.expected_date) }}</span>
              <span v-if="event.years_until_event !== null && event.years_until_event !== undefined">
                {{ formatTimeUntil(event.years_until_event) }}
              </span>
            </div>
          </div>

          <!-- Amount -->
          <div class="flex-shrink-0 text-right">
            <span
              class="text-sm font-semibold"
              :class="event.impact_type === 'income' ? 'text-spring-600' : 'text-raspberry-600'"
            >
              {{ event.impact_type === 'income' ? '+' : '-' }}{{ formatCurrency(event.amount) }}
            </span>
          </div>
        </div>

        <!-- View All link -->
        <div class="pt-1 text-center">
          <router-link
            to="/goals?tab=events"
            class="text-xs font-medium text-raspberry-600 hover:text-raspberry-700"
          >
            View all life events
          </router-link>
        </div>
      </div>
    </transition>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'ModuleLifeEvents',
  mixins: [currencyMixin],

  props: {
    module: {
      type: String,
      required: true,
    },
    events: {
      type: Array,
      default: () => [],
    },
    impactSummary: {
      type: Object,
      default: null,
    },
  },

  data() {
    return {
      isExpanded: false,
    };
  },

  computed: {
    sortedEvents() {
      return [...this.events].sort((a, b) => {
        return new Date(a.expected_date) - new Date(b.expected_date);
      });
    },
  },

  methods: {
    certaintyLabel(certainty) {
      const labels = {
        confirmed: 'Confirmed',
        likely: 'Likely',
        possible: 'Possible',
        speculative: 'Speculative',
      };
      return labels[certainty] || 'Likely';
    },

    certaintyBadgeClass(certainty) {
      const classes = {
        confirmed: 'bg-spring-100 text-spring-700',
        likely: 'bg-violet-100 text-violet-700',
        possible: 'bg-savannah-100 text-neutral-500',
        speculative: 'bg-savannah-100 text-neutral-500',
      };
      return classes[certainty] || 'bg-savannah-100 text-neutral-500';
    },

    formatEventDate(date) {
      if (!date) return '-';
      return new Date(date).toLocaleDateString('en-GB', {
        month: 'short',
        year: 'numeric',
      });
    },

    formatTimeUntil(years) {
      if (years === null || years === undefined) return '';
      const rounded = Math.round(years * 10) / 10;
      if (rounded < 1) {
        const months = Math.round(years * 12);
        return `in ${months} ${months === 1 ? 'month' : 'months'}`;
      }
      return `in ${rounded} ${rounded === 1 ? 'year' : 'years'}`;
    },
  },
};
</script>

