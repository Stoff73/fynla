<template>
  <div
    class="bg-white rounded-lg border border-gray-200 p-4 hover:shadow-md transition-shadow cursor-pointer"
    @click="$emit('click', event)"
  >
    <!-- Header with type badge and certainty -->
    <div class="flex items-start justify-between mb-3">
      <span
        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold"
        :class="impactBadgeClass"
      >
        {{ event.impact_type === 'income' ? '+' : '-' }} {{ displayEventType }}
      </span>
      <span
        class="text-xs font-medium"
        :class="certaintyClass"
      >
        {{ certaintyLabel }}
      </span>
    </div>

    <!-- Event Name -->
    <h4 class="text-base font-semibold text-gray-900 mb-2">{{ event.event_name }}</h4>

    <!-- Details -->
    <div class="space-y-1">
      <div class="flex justify-between">
        <span class="text-sm text-gray-500">Amount</span>
        <span
          class="text-sm font-semibold"
          :class="event.impact_type === 'income' ? 'text-green-600' : 'text-red-600'"
        >
          {{ event.impact_type === 'income' ? '+' : '-' }}{{ formatCurrency(event.amount) }}
        </span>
      </div>
      <div class="flex justify-between">
        <span class="text-sm text-gray-500">Expected</span>
        <span class="text-sm font-medium text-gray-900">{{ formatDate(event.expected_date) }}</span>
      </div>
      <div v-if="yearsUntil !== null" class="flex justify-between">
        <span class="text-sm text-gray-500">In</span>
        <span class="text-sm font-medium text-gray-900">{{ yearsUntil }} {{ yearsUntil === 1 ? 'year' : 'years' }}</span>
      </div>
    </div>

    <!-- Description preview -->
    <p v-if="event.description" class="mt-2 text-xs text-gray-500 line-clamp-2">
      {{ event.description }}
    </p>

    <!-- Actions -->
    <div class="flex items-center justify-end gap-2 mt-3 pt-3 border-t border-gray-100">
      <button
        @click.stop="$emit('edit', event)"
        class="text-xs text-primary-600 hover:text-primary-700 font-medium"
      >
        Edit
      </button>
      <button
        @click.stop="$emit('delete', event)"
        class="text-xs text-red-600 hover:text-red-700 font-medium"
      >
        Delete
      </button>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';
import { LIFE_EVENT_ICONS } from '@/constants/eventIcons';

export default {
  name: 'LifeEventCard',
  mixins: [currencyMixin],

  props: {
    event: {
      type: Object,
      required: true,
    },
  },

  emits: ['click', 'edit', 'delete'],

  computed: {
    displayEventType() {
      const config = LIFE_EVENT_ICONS[this.event.event_type];
      return config?.label || this.formatEventType(this.event.event_type);
    },

    impactBadgeClass() {
      return this.event.impact_type === 'income'
        ? 'bg-green-100 text-green-800'
        : 'bg-red-100 text-red-800';
    },

    certaintyLabel() {
      const labels = {
        confirmed: 'Confirmed',
        likely: 'Likely',
        possible: 'Possible',
        speculative: 'Speculative',
      };
      return labels[this.event.certainty] || 'Likely';
    },

    certaintyClass() {
      const classes = {
        confirmed: 'text-green-600',
        likely: 'text-blue-600',
        possible: 'text-blue-500',
        speculative: 'text-gray-500',
      };
      return classes[this.event.certainty] || 'text-gray-500';
    },

    yearsUntil() {
      return this.event.years_until_event ?? null;
    },
  },

  methods: {
    formatEventType(type) {
      return type
        .split('_')
        .map(word => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
    },

    formatDate(date) {
      if (!date) return '-';
      return new Date(date).toLocaleDateString('en-GB', {
        month: 'short',
        year: 'numeric',
      });
    },
  },
};
</script>
