<template>
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    <!-- Current Net Worth -->
    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
      <p class="text-sm text-gray-600 font-medium">Current Net Worth</p>
      <p class="text-xl sm:text-2xl font-bold text-gray-900 mt-1">
        {{ formatCurrency(summary.starting_net_worth) }}
      </p>
      <p class="text-xs text-gray-500 mt-1">Age {{ projection.current_age }}</p>
    </div>

    <!-- Projected Net Worth -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
      <p class="text-sm text-blue-600 font-medium">Projected at {{ projection.projection_end_age }}</p>
      <p class="text-xl sm:text-2xl font-bold text-blue-900 mt-1">
        {{ formatCurrency(summary.ending_net_worth) }}
      </p>
      <p class="text-xs text-blue-600 mt-1">
        {{ growthPercentage > 0 ? '+' : '' }}{{ growthPercentage }}% growth
      </p>
    </div>

    <!-- Peak Net Worth -->
    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
      <p class="text-sm text-green-600 font-medium">Peak Net Worth</p>
      <p class="text-xl sm:text-2xl font-bold text-green-900 mt-1">
        {{ formatCurrency(summary.peak_net_worth) }}
      </p>
      <p class="text-xs text-green-600 mt-1">At age {{ summary.peak_age }}</p>
    </div>

    <!-- Events Summary -->
    <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
      <p class="text-sm text-purple-600 font-medium">Life Events</p>
      <div class="flex items-baseline gap-2 mt-1">
        <p class="text-xl sm:text-2xl font-bold text-purple-900">
          {{ totalEventCount }}
        </p>
        <span class="text-xs text-purple-600">planned</span>
      </div>
      <p class="text-xs text-purple-600 mt-1">
        <span v-if="summary.total_income_events > 0" class="text-green-600">
          +{{ formatCompact(summary.total_income_events) }}
        </span>
        <span v-if="summary.total_income_events > 0 && summary.total_expense_events > 0"> / </span>
        <span v-if="summary.total_expense_events > 0" class="text-red-600">
          -{{ formatCompact(summary.total_expense_events) }}
        </span>
        <span v-if="!summary.total_income_events && !summary.total_expense_events">
          {{ summary.goal_count }} goals
        </span>
      </p>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'ProjectionSummaryCards',
  mixins: [currencyMixin],

  props: {
    projection: {
      type: Object,
      required: true,
    },
    view: {
      type: String,
      default: 'net_worth',
    },
  },

  computed: {
    summary() {
      return this.projection?.summary || {
        starting_net_worth: 0,
        ending_net_worth: 0,
        peak_net_worth: 0,
        peak_age: 0,
        total_income_events: 0,
        total_expense_events: 0,
        goal_count: 0,
        life_event_count: 0,
      };
    },

    growthPercentage() {
      const start = this.summary.starting_net_worth || 1;
      const end = this.summary.ending_net_worth || 0;
      return Math.round(((end - start) / start) * 100);
    },

    totalEventCount() {
      return (this.summary.goal_count || 0) + (this.summary.life_event_count || 0);
    },
  },

  methods: {
    formatCompact(value) {
      if (value >= 1000000) {
        return `£${(value / 1000000).toFixed(1)}M`;
      }
      if (value >= 1000) {
        return `£${Math.round(value / 1000)}K`;
      }
      return this.formatCurrency(value);
    },
  },
};
</script>
