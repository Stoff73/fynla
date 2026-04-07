<template>
  <div class="goal-progress-section">
    <h4 class="text-md font-semibold text-horizon-500 mb-4">Investment Goal Progress</h4>

    <div v-if="!data || !data.goals || data.goals.length === 0" class="text-center py-8 text-neutral-500">
      <p>No investment goals configured</p>
    </div>

    <div v-else class="space-y-4">
      <!-- Goal Cards -->
      <div v-for="(goal, index) in data.goals" :key="index" class="bg-white border border-light-gray rounded-lg p-5 shadow-sm">
        <div class="flex justify-between items-start mb-3">
          <div class="flex-1">
            <h5 class="text-md font-semibold text-horizon-500">{{ goal.name }}</h5>
            <p class="text-sm text-neutral-500 mt-1">{{ goal.description }}</p>
          </div>
          <span
            class="px-3 py-1 text-xs font-semibold rounded-full ml-3"
            :class="getGoalStatusClass(goal.status)"
          >
            {{ goal.status }}
          </span>
        </div>

        <!-- Goal Progress -->
        <div class="mb-4">
          <div class="flex justify-between text-sm mb-2">
            <span class="text-neutral-500">Progress to target</span>
            <span class="font-medium text-horizon-500">
              £{{ formatNumber(goal.current_value) }} / £{{ formatNumber(goal.target_value) }}
            </span>
          </div>
          <div class="w-full bg-savannah-200 rounded-full h-3 overflow-hidden">
            <div
              class="h-3 rounded-full transition-all duration-500"
              :class="getProgressBarClass(goal.progress_percentage)"
              :style="{ width: Math.min(goal.progress_percentage, 100) + '%' }"
            ></div>
          </div>
          <div class="flex justify-between items-center mt-2">
            <span class="text-xs text-neutral-500">
              {{ formatPercentage(goal.progress_percentage) }}% complete
            </span>
            <span class="text-xs text-neutral-500">
              Target date: {{ formatDate(goal.target_date) }}
            </span>
          </div>
        </div>

        <!-- Goal Metrics -->
        <div class="grid grid-cols-3 gap-4 pt-3 border-t border-light-gray">
          <div>
            <p class="text-xs text-neutral-500 mb-1">Monthly Contribution</p>
            <p class="text-sm font-semibold text-horizon-500">£{{ formatNumber(goal.monthly_contribution || 0) }}</p>
          </div>
          <div>
            <p class="text-xs text-neutral-500 mb-1">Required Return</p>
            <p class="text-sm font-semibold text-horizon-500">{{ formatPercentage(goal.required_return || 0) }}%</p>
          </div>
          <div>
            <p class="text-xs text-neutral-500 mb-1">Time Remaining</p>
            <p class="text-sm font-semibold text-horizon-500">{{ goal.months_remaining || 0 }} months</p>
          </div>
        </div>

        <!-- On-Track/Off-Track Message -->
        <div v-if="goal.on_track === false" class="mt-3 p-3 bg-eggshell-500 rounded-md">
          <p class="text-sm text-violet-800">
            <strong>Action needed:</strong> {{ goal.recommendation }}
          </p>
        </div>
        <div v-else-if="goal.on_track === true" class="mt-3 p-3 bg-eggshell-500 rounded-md">
          <p class="text-sm text-spring-800">
            <strong>On track:</strong> Goal is progressing well towards target.
          </p>
        </div>
      </div>

      <!-- Summary -->
      <div v-if="data.summary" class="bg-eggshell-500 rounded-lg p-4 mt-6">
        <h5 class="text-sm font-semibold text-neutral-500 mb-3">Goal Summary</h5>
        <div class="grid grid-cols-3 gap-4">
          <div>
            <p class="text-xs text-neutral-500 mb-1">Total Goals</p>
            <p class="text-lg font-bold text-horizon-500">{{ data.summary.total_goals || 0 }}</p>
          </div>
          <div>
            <p class="text-xs text-neutral-500 mb-1">On Track</p>
            <p class="text-lg font-bold text-spring-600">{{ data.summary.on_track_count || 0 }}</p>
          </div>
          <div>
            <p class="text-xs text-neutral-500 mb-1">Needs Attention</p>
            <p class="text-lg font-bold text-violet-600">{{ data.summary.off_track_count || 0 }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'GoalProgressSection',

  mixins: [currencyMixin],

  props: {
    data: {
      type: Object,
      default: null,
    },
  },

  methods: {
    formatPercentage(value) {
      if (value === null || value === undefined) return '0.0';
      return value.toFixed(1);
    },

    formatDate(dateString) {
      if (!dateString) return 'N/A';
      const date = new Date(dateString);
      return date.toLocaleDateString('en-GB', { year: 'numeric', month: 'short' });
    },

    getGoalStatusClass(status) {
      const classes = {
        'on-track': 'bg-spring-500 text-white',
        'at-risk': 'bg-violet-500 text-white',
        'off-track': 'bg-raspberry-500 text-white',
        'achieved': 'bg-violet-500 text-white',
      };
      return classes[status] || 'bg-savannah-100 text-horizon-500';
    },

    getProgressBarClass(percentage) {
      if (percentage >= 80) return 'bg-spring-600';
      if (percentage >= 50) return 'bg-raspberry-500';
      if (percentage >= 30) return 'bg-raspberry-500';
      return 'bg-raspberry-600';
    },
  },
};
</script>

<style scoped>
/* Component-specific styles */
</style>
