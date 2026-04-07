<template>
  <div v-if="hasEvents" class="estate-life-events-impact">
    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-h5 font-semibold text-horizon-500">Life Events Impact on Estate</h3>
      <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-raspberry-100 text-raspberry-700">
        {{ events.length }} {{ events.length === 1 ? 'event' : 'events' }}
      </span>
    </div>

    <!-- Event Impact Cards -->
    <div class="space-y-3">
      <div
        v-for="(event, index) in sortedEvents"
        :key="event.event_name + '-' + index"
        :class="[
          'rounded-lg p-4 border',
          event.impact_type === 'income' ? 'bg-spring-50 border-spring-200' : 'bg-raspberry-50 border-raspberry-200'
        ]"
      >
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
          <div class="flex items-center gap-2">
            <p class="text-sm font-medium text-horizon-500">{{ event.event_name }}</p>
            <span :class="[
              'inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium',
              certaintyClass(event.certainty)
            ]">
              {{ event.certainty }}
            </span>
          </div>
          <p class="text-sm text-neutral-500">{{ formatDate(event.expected_date) }}</p>
        </div>

        <p v-if="event.module_context" class="text-xs text-neutral-500 mt-0.5">{{ event.module_context }}</p>

        <div class="mt-2 flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4">
          <span :class="[
            'text-sm font-semibold',
            event.impact_type === 'income' ? 'text-spring-700' : 'text-raspberry-700'
          ]">
            {{ event.impact_type === 'income' ? '+' : '-' }}{{ formatCurrency(event.amount) }}
          </span>

          <span v-if="event.projected_iht_change !== undefined && event.projected_iht_change !== 0" :class="[
            'inline-flex items-center text-xs font-medium px-2 py-0.5 rounded',
            event.projected_iht_change > 0
              ? 'bg-raspberry-50 text-raspberry-700'
              : 'bg-spring-50 text-spring-700'
          ]">
            Inheritance Tax {{ event.projected_iht_change > 0 ? '+' : '' }}{{ formatCurrency(event.projected_iht_change) }}
          </span>

        </div>
      </div>
    </div>

    <!-- View all link -->
    <div class="mt-4 pt-4 border-t border-light-gray">
      <router-link to="/goals?tab=events" class="text-sm text-raspberry-500 hover:text-raspberry-700 font-medium">
        View all life events &rarr;
      </router-link>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'EstateLifeEventsImpact',

  mixins: [currencyMixin],

  props: {
    events: {
      type: Array,
      default: () => [],
    },
    summary: {
      type: Object,
      default: null,
    },
    reviewTriggers: {
      type: Array,
      default: () => [],
    },
  },

  computed: {
    hasEvents() {
      return this.events && this.events.length > 0;
    },

    sortedEvents() {
      return [...this.events].sort((a, b) => {
        const dateA = new Date(a.expected_date);
        const dateB = new Date(b.expected_date);
        return dateA - dateB;
      });
    },
  },

  methods: {
    formatDate(dateString) {
      if (!dateString) return '';
      const date = new Date(dateString);
      return date.toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
      });
    },

    certaintyClass(certainty) {
      switch (certainty) {
        case 'confirmed':
          return 'bg-spring-100 text-spring-700';
        case 'likely':
          return 'bg-violet-100 text-violet-700';
        case 'possible':
          return 'bg-savannah-100 text-neutral-500';
        case 'speculative':
          return 'bg-savannah-100 text-neutral-500';
        default:
          return 'bg-savannah-100 text-neutral-500';
      }
    },
  },
};
</script>
