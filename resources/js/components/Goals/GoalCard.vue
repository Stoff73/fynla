<template>
  <div
    class="goal-card bg-white border border-gray-200 rounded-lg p-5 hover:shadow-md hover:-translate-y-0.5 hover:border-primary-500 transition-all duration-200"
    :class="{ 'border-l-4': true, [borderColorClass]: true }"
  >
    <!-- Goal Header -->
    <div class="flex justify-between items-start mb-3">
      <div class="flex-1">
        <div class="flex items-center gap-2 mb-1">
          <span class="text-lg">{{ goalTypeIcon }}</span>
          <h3 class="text-base font-semibold text-gray-900">{{ goal.goal_name }}</h3>
        </div>
        <div class="flex items-center gap-2">
          <span class="text-xs px-2 py-0.5 rounded-full" :class="moduleTagClass">
            {{ moduleLabel }}
          </span>
          <span v-if="goal.priority === 'critical' || goal.priority === 'high'"
            class="text-xs px-2 py-0.5 rounded-full"
            :class="priorityTagClass"
          >
            {{ goal.priority }}
          </span>
        </div>
      </div>
      <div v-if="showActions" class="flex gap-1 ml-3">
        <button
          @click="$emit('edit', goal)"
          class="p-1.5 text-gray-400 hover:text-blue-600 rounded-button hover:bg-gray-100"
          title="Edit goal"
        >
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
          </svg>
        </button>
        <button
          @click="$emit('delete', goal)"
          class="p-1.5 text-gray-400 hover:text-red-600 rounded-button hover:bg-gray-100"
          title="Delete goal"
        >
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Progress Section -->
    <div class="mb-4">
      <div class="flex justify-between items-center mb-1.5">
        <span class="text-sm text-gray-600">{{ formatCurrency(goal.current_amount) }} of {{ formatCurrency(goal.target_amount) }}</span>
        <span class="text-sm font-semibold" :class="progressTextClass">{{ progressPercent }}%</span>
      </div>
      <div class="w-full bg-gray-200 rounded-full h-2.5">
        <div
          class="h-2.5 rounded-full transition-all duration-500"
          :class="progressBarClass"
          :style="{ width: Math.min(progressPercent, 100) + '%' }"
        ></div>
      </div>
    </div>

    <!-- Metrics Grid -->
    <div class="grid grid-cols-2 gap-3 text-sm mb-4">
      <div>
        <p class="text-xs text-gray-500">Time Remaining</p>
        <p class="font-medium text-gray-900">{{ timeRemaining }}</p>
      </div>
      <div>
        <p class="text-xs text-gray-500">Monthly Contribution</p>
        <p class="font-medium text-gray-900">{{ goal.monthly_contribution ? formatCurrency(goal.monthly_contribution) : 'Not set' }}</p>
      </div>
    </div>

    <!-- Streak Display (if available) -->
    <div v-if="goal.contribution_streak > 0" class="flex items-center gap-2 mb-4 py-2 px-3 bg-blue-50 rounded-lg">
      <span class="text-lg">🔥</span>
      <span class="text-sm font-medium text-blue-700">{{ goal.contribution_streak }} month streak!</span>
    </div>

    <!-- Status Badge -->
    <div class="flex items-center justify-between pt-3 border-t border-gray-100">
      <span
        class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium"
        :class="statusBadgeClass"
      >
        <span class="w-1.5 h-1.5 rounded-full mr-1.5" :class="statusDotClass"></span>
        {{ statusText }}
      </span>
      <button
        v-if="goal.status === 'active'"
        @click="$emit('add-contribution', goal)"
        class="text-xs font-medium text-primary-600 hover:text-primary-700"
      >
        + Add Contribution
      </button>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'GoalCard',
  mixins: [currencyMixin],

  props: {
    goal: {
      type: Object,
      required: true,
    },
    showActions: {
      type: Boolean,
      default: true,
    },
  },

  emits: ['edit', 'delete', 'add-contribution'],

  computed: {
    progressPercent() {
      if (!this.goal.target_amount) return 0;
      return Math.round((parseFloat(this.goal.current_amount) / parseFloat(this.goal.target_amount)) * 100);
    },

    isNotStarted() {
      return parseFloat(this.goal.current_amount) <= 0;
    },

    isOnTrack() {
      return this.goal.is_on_track;
    },

    progressTextClass() {
      if (this.progressPercent >= 100) return 'text-green-600';
      if (this.isNotStarted) return 'text-gray-500';
      if (this.isOnTrack) return 'text-blue-600';
      return 'text-blue-600';
    },

    progressBarClass() {
      if (this.progressPercent >= 100) return 'bg-green-500';
      if (this.isNotStarted) return 'bg-gray-300';
      if (this.isOnTrack) return 'bg-blue-500';
      return 'bg-blue-500';
    },

    borderColorClass() {
      if (this.goal.status === 'completed') return 'border-l-green-500';
      if (this.isNotStarted) return 'border-l-gray-300';
      if (this.isOnTrack) return 'border-l-blue-500';
      return 'border-l-blue-500';
    },

    timeRemaining() {
      const days = this.goal.days_remaining;
      if (days === undefined || days === null) return 'N/A';
      if (days < 0) return 'Overdue';
      if (days === 0) return 'Today';
      if (days === 1) return '1 day';
      if (days < 30) return `${days} days`;
      if (days < 365) {
        const months = Math.floor(days / 30);
        return `${months} ${months === 1 ? 'month' : 'months'}`;
      }
      const years = Math.floor(days / 365);
      const months = Math.floor((days % 365) / 30);
      if (months === 0) return `${years} ${years === 1 ? 'year' : 'years'}`;
      return `${years}y ${months}m`;
    },

    statusText() {
      if (this.goal.status === 'completed') return 'Completed';
      if (this.goal.status === 'paused') return 'Paused';
      if (this.progressPercent >= 100) return 'Goal Achieved';
      if (this.isNotStarted) return 'Not Started';
      if (this.isOnTrack) return 'On Track';
      return 'Behind Schedule';
    },

    statusBadgeClass() {
      if (this.goal.status === 'completed' || this.progressPercent >= 100) return 'bg-green-100 text-green-800';
      if (this.goal.status === 'paused') return 'bg-gray-100 text-gray-800';
      if (this.isNotStarted) return 'bg-gray-100 text-gray-700';
      if (this.isOnTrack) return 'bg-blue-100 text-blue-800';
      return 'bg-blue-100 text-blue-800';
    },

    statusDotClass() {
      if (this.goal.status === 'completed' || this.progressPercent >= 100) return 'bg-green-500';
      if (this.goal.status === 'paused') return 'bg-gray-500';
      if (this.isNotStarted) return 'bg-gray-400';
      if (this.isOnTrack) return 'bg-blue-500';
      return 'bg-blue-500';
    },

    moduleLabel() {
      const labels = {
        savings: 'Savings',
        investment: 'Investment',
        property: 'Property',
        retirement: 'Retirement',
      };
      return labels[this.goal.assigned_module] || this.goal.assigned_module;
    },

    moduleTagClass() {
      const classes = {
        savings: 'bg-emerald-100 text-emerald-700',
        investment: 'bg-blue-100 text-blue-700',
        property: 'bg-purple-100 text-purple-700',
        retirement: 'bg-blue-100 text-blue-700',
      };
      return classes[this.goal.assigned_module] || 'bg-gray-100 text-gray-700';
    },

    priorityTagClass() {
      if (this.goal.priority === 'critical') return 'bg-red-100 text-red-700';
      if (this.goal.priority === 'high') return 'bg-blue-100 text-blue-700';
      return 'bg-gray-100 text-gray-700';
    },

    goalTypeIcon() {
      const icons = {
        emergency_fund: '🛡️',
        property_purchase: '🏠',
        home_deposit: '🔑',
        education: '🎓',
        retirement: '☀️',
        wealth_accumulation: '📈',
        wedding: '💍',
        holiday: '✈️',
        car_purchase: '🚗',
        debt_repayment: '💳',
        custom: '⭐',
      };
      return icons[this.goal.goal_type] || '🎯';
    },
  },
};
</script>
