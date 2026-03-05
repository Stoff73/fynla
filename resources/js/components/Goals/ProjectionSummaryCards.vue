<template>
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    <!-- Current Net Worth -->
    <div class="bg-savannah-100 border border-light-gray rounded-lg p-4">
      <p class="text-sm text-neutral-500 font-medium">Current Net Worth</p>
      <p class="text-xl sm:text-2xl font-bold text-horizon-500 mt-1">
        {{ formatCurrency(summary.starting_net_worth) }}
      </p>
      <p class="text-xs text-neutral-500 mt-1">Age {{ projection.current_age }}</p>
    </div>

    <!-- Projected Net Worth at Retirement -->
    <div class="bg-violet-50 border border-violet-200 rounded-lg p-4">
      <p class="text-sm text-violet-600 font-medium">Projected Net Worth at {{ summary.retirement_age }}</p>
      <p class="text-xl sm:text-2xl font-bold text-violet-900 mt-1">
        {{ formatCurrency(summary.retirement_net_worth) }}
      </p>
      <p class="text-xs text-violet-600 mt-1">Retirement age</p>
    </div>

    <!-- Projected Net Worth at 90 -->
    <div class="bg-spring-50 border border-spring-200 rounded-lg p-4">
      <p class="text-sm text-spring-600 font-medium">Projected Net Worth at {{ projection.projection_end_age }}</p>
      <p class="text-xl sm:text-2xl font-bold text-spring-900 mt-1">
        {{ formatCurrency(summary.ending_net_worth) }}
      </p>
    </div>

    <!-- Events Summary -->
    <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
      <p class="text-sm text-purple-600 font-medium">Life Events</p>
      <div class="mt-2 space-y-1">
        <div class="flex justify-between text-sm">
          <span>
            <span class="font-semibold text-spring-700">{{ summary.income_event_count || 0 }}</span>
            <span class="text-neutral-500"> cash inflow events</span>
          </span>
          <span class="font-semibold text-spring-700">{{ formatCompact(summary.total_income_events) }}</span>
        </div>
        <div class="flex justify-between text-sm">
          <span>
            <span class="font-semibold text-raspberry-700">{{ summary.expense_event_count || 0 }}</span>
            <span class="text-neutral-500"> cash outflow events</span>
          </span>
          <span class="font-semibold text-raspberry-700">{{ formatCompact(summary.total_expense_events) }}</span>
        </div>
      </div>
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
        income_event_count: 0,
        expense_event_count: 0,
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
