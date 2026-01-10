<template>
  <div class="goal-progress-section">
    <h4 class="text-md font-semibold text-gray-800 mb-4">Investment Goal Progress</h4>

    <div v-if="!data || !data.goals || data.goals.length === 0" class="text-center py-8 text-gray-500">
      <p>No investment goals configured</p>
    </div>

    <div v-else class="space-y-4">
      <!-- Goal Cards -->
      <div v-for="(goal, index) in data.goals" :key="index" class="bg-white border border-gray-200 rounded-lg p-5 shadow-sm">
        <div class="flex justify-between items-start mb-3">
          <div class="flex-1">
            <h5 class="text-md font-semibold text-gray-800">{{ goal.name }}</h5>
            <p class="text-sm text-gray-600 mt-1">{{ goal.description }}</p>
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
            <span class="text-gray-600">Progress to target</span>
            <span class="font-medium text-gray-800">
              £{{ formatNumber(goal.current_value) }} / £{{ formatNumber(goal.target_value) }}
            </span>
          </div>
          <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
            <div
              class="h-3 rounded-full transition-all duration-500"
              :class="getProgressBarClass(goal.progress_percentage)"
              :style="{ width: Math.min(goal.progress_percentage, 100) + '%' }"
            ></div>
          </div>
          <div class="flex justify-between items-center mt-2">
            <span class="text-xs text-gray-600">
              {{ formatPercentage(goal.progress_percentage) }}% complete
            </span>
            <span class="text-xs text-gray-600">
              Target date: {{ formatDate(goal.target_date) }}
            </span>
          </div>
        </div>

        <!-- Goal Metrics -->
        <div class="grid grid-cols-3 gap-4 pt-3 border-t border-gray-200">
          <div>
            <p class="text-xs text-gray-600 mb-1">Monthly Contribution</p>
            <p class="text-sm font-semibold text-gray-800">£{{ formatNumber(goal.monthly_contribution || 0) }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-600 mb-1">Required Return</p>
            <p class="text-sm font-semibold text-gray-800">{{ formatPercentage(goal.required_return || 0) }}%</p>
          </div>
          <div>
            <p class="text-xs text-gray-600 mb-1">Time Remaining</p>
            <p class="text-sm font-semibold text-gray-800">{{ goal.months_remaining || 0 }} months</p>
          </div>
        </div>

        <!-- On-Track/Off-Track Message -->
        <div v-if="goal.on_track === false" class="mt-3 p-3 bg-gray-50 rounded-md">
          <p class="text-sm text-amber-800">
            <strong>Action needed:</strong> {{ goal.recommendation }}
          </p>
        </div>
        <div v-else-if="goal.on_track === true" class="mt-3 p-3 bg-gray-50 rounded-md">
          <p class="text-sm text-green-800">
            <strong>On track:</strong> Goal is progressing well towards target.
          </p>
        </div>
      </div>

      <!-- Summary -->
      <div v-if="data.summary" class="bg-gray-50 rounded-lg p-4 mt-6">
        <h5 class="text-sm font-semibold text-gray-700 mb-3">Goal Summary</h5>
        <div class="grid grid-cols-3 gap-4">
          <div>
            <p class="text-xs text-gray-600 mb-1">Total Goals</p>
            <p class="text-lg font-bold text-gray-800">{{ data.summary.total_goals || 0 }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-600 mb-1">On Track</p>
            <p class="text-lg font-bold text-green-600">{{ data.summary.on_track_count || 0 }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-600 mb-1">Needs Attention</p>
            <p class="text-lg font-bold text-amber-600">{{ data.summary.off_track_count || 0 }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GoalProgressSection',

  props: {
    data: {
      type: Object,
      default: null,
    },
  },

  methods: {
    formatNumber(value) {
      if (value === null || value === undefined) return '0';
      return Math.round(value).toLocaleString('en-GB');
    },

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
        'on-track': 'bg-green-500 text-white',
        'at-risk': 'bg-amber-500 text-white',
        'off-track': 'bg-red-500 text-white',
        'achieved': 'bg-blue-500 text-white',
      };
      return classes[status] || 'bg-gray-100 text-gray-800';
    },

    getProgressBarClass(percentage) {
      if (percentage >= 80) return 'bg-green-600';
      if (percentage >= 50) return 'bg-blue-600';
      if (percentage >= 30) return 'bg-amber-600';
      return 'bg-red-600';
    },
  },
};
</script>

<style scoped>
/* Component-specific styles */
</style>
