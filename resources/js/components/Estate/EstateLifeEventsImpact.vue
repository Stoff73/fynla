<template>
  <div v-if="hasEvents" class="estate-life-events-impact">
    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-h5 font-semibold text-gray-900">Life Events Impact on Estate</h3>
      <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary-100 text-primary-700">
        {{ events.length }} {{ events.length === 1 ? 'event' : 'events' }}
      </span>
    </div>

    <!-- Impact Summary -->
    <div v-if="summary" class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
      <div class="bg-success-50 rounded-lg p-4 border border-success-200">
        <p class="text-xs text-success-600 font-medium mb-1">Incoming to Estate</p>
        <p class="text-lg font-bold text-success-700">{{ formatCurrency(summary.total_incoming) }}</p>
      </div>
      <div class="bg-error-50 rounded-lg p-4 border border-error-200">
        <p class="text-xs text-error-600 font-medium mb-1">Outgoing from Estate</p>
        <p class="text-lg font-bold text-error-700">{{ formatCurrency(summary.total_outgoing) }}</p>
      </div>
      <div :class="[
        'rounded-lg p-4 border',
        summary.net_estate_impact >= 0
          ? 'bg-success-50 border-success-200'
          : 'bg-error-50 border-error-200'
      ]">
        <p class="text-xs text-gray-600 font-medium mb-1">Net Estate Impact</p>
        <p :class="[
          'text-lg font-bold',
          summary.net_estate_impact >= 0 ? 'text-success-700' : 'text-error-700'
        ]">
          {{ summary.net_estate_impact >= 0 ? '+' : '' }}{{ formatCurrency(summary.net_estate_impact) }}
        </p>
      </div>
    </div>

    <!-- Review Triggers (high priority warnings) -->
    <div v-if="reviewTriggers.length > 0" class="mb-6 space-y-3">
      <div
        v-for="(trigger, index) in reviewTriggers"
        :key="'trigger-' + index"
        :class="[
          'rounded-lg p-4 border-l-4',
          trigger.priority === 'high' ? 'bg-error-50 border-error-500' : 'bg-blue-50 border-blue-500'
        ]"
      >
        <div class="flex items-start">
          <svg class="h-5 w-5 text-gray-600 mt-0.5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
          </svg>
          <div class="ml-3">
            <p class="text-sm font-medium text-gray-900">{{ trigger.event_name }}</p>
            <p class="text-sm text-gray-700 mt-1">{{ trigger.reason }}</p>
            <p class="text-sm text-primary-700 mt-1 font-medium">{{ trigger.recommendation }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Timeline of Events -->
    <div class="space-y-0">
      <div
        v-for="(event, index) in sortedEvents"
        :key="event.event_name + '-' + index"
        class="relative flex items-start pb-6 last:pb-0"
      >
        <!-- Timeline line -->
        <div v-if="index < sortedEvents.length - 1" class="absolute top-6 left-4 w-0.5 h-full bg-gray-200"></div>

        <!-- Timeline dot -->
        <div :class="[
          'relative z-10 flex items-center justify-center w-8 h-8 rounded-full flex-shrink-0',
          event.impact_type === 'income' ? 'bg-success-100' : 'bg-error-100'
        ]">
          <div :class="[
            'w-3 h-3 rounded-full',
            event.impact_type === 'income' ? 'bg-success-500' : 'bg-error-500'
          ]"></div>
        </div>

        <!-- Event content -->
        <div class="ml-4 flex-1 min-w-0">
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
            <div class="flex items-center gap-2">
              <p class="text-sm font-medium text-gray-900">{{ event.event_name }}</p>
              <span :class="[
                'inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium',
                certaintyClass(event.certainty)
              ]">
                {{ event.certainty }}
              </span>
            </div>
            <p class="text-sm text-gray-500">{{ formatDate(event.expected_date) }}</p>
          </div>

          <!-- Module context -->
          <p v-if="event.module_context" class="text-xs text-gray-500 mt-0.5">{{ event.module_context }}</p>

          <!-- Financial impact row -->
          <div class="mt-2 flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4">
            <span :class="[
              'text-sm font-semibold',
              event.impact_type === 'income' ? 'text-success-700' : 'text-error-700'
            ]">
              {{ event.impact_type === 'income' ? '+' : '-' }}{{ formatCurrency(event.amount) }}
            </span>

            <!-- IHT change indicator -->
            <span v-if="event.projected_iht_change !== undefined && event.projected_iht_change !== 0" :class="[
              'inline-flex items-center text-xs font-medium px-2 py-0.5 rounded',
              event.projected_iht_change > 0
                ? 'bg-error-50 text-error-700'
                : 'bg-success-50 text-success-700'
            ]">
              Inheritance Tax {{ event.projected_iht_change > 0 ? '+' : '' }}{{ formatCurrency(event.projected_iht_change) }}
            </span>

            <span v-if="event.projected_iht_after_event !== undefined" class="text-xs text-gray-500">
              (Projected liability: {{ formatCurrency(event.projected_iht_after_event) }})
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- View all link -->
    <div class="mt-4 pt-4 border-t border-gray-200">
      <router-link to="/goals?tab=events" class="text-sm text-primary-600 hover:text-primary-700 font-medium">
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
          return 'bg-success-100 text-success-700';
        case 'likely':
          return 'bg-blue-100 text-blue-700';
        case 'possible':
          return 'bg-gray-100 text-gray-700';
        case 'speculative':
          return 'bg-gray-100 text-gray-500';
        default:
          return 'bg-gray-100 text-gray-700';
      }
    },
  },
};
</script>
