<template>
  <div
    class="goal-card bg-white border border-light-gray rounded-lg p-5 hover:shadow-md hover:-translate-y-0.5 hover:bg-light-gray transition-all duration-200 cursor-pointer"
    :class="{ 'border-l-4': true, [borderColorClass]: true }"
    @click="$emit('view', goal)"
  >
    <!-- Goal Header -->
    <div class="flex justify-between items-start mb-3">
      <div class="flex-1">
        <div class="flex items-center gap-2 mb-1">
          <span class="text-lg">{{ goalTypeIcon }}</span>
          <h3 class="text-base font-semibold text-horizon-500">{{ goal.goal_name }}</h3>
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
          <span v-if="isBlocked"
            class="text-xs px-2 py-0.5 rounded-full bg-raspberry-100 text-raspberry-700 flex items-center gap-1"
            title="This goal is blocked by an incomplete dependency"
          >
            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
            Blocked
          </span>
          <span v-else-if="dependencyCount > 0"
            class="text-xs px-2 py-0.5 rounded-full bg-violet-50 text-violet-600 flex items-center gap-1"
            :title="dependencyCount + ' ' + (dependencyCount === 1 ? 'dependency' : 'dependencies')"
          >
            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
            </svg>
            {{ dependencyCount }}
          </span>
        </div>
      </div>
      <div v-if="showActions" class="flex gap-1 ml-3">
        <button
          @click.stop="$emit('edit', goal)"
          class="p-1.5 text-horizon-400 hover:text-violet-600 rounded-button hover:bg-savannah-100"
          title="Edit goal"
        >
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
          </svg>
        </button>
        <button
          @click.stop="$emit('delete', goal)"
          class="p-1.5 text-horizon-400 hover:text-raspberry-600 rounded-button hover:bg-savannah-100"
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
        <span class="text-sm text-neutral-500">{{ formatCurrency(goal.current_amount) }} of {{ formatCurrency(goal.target_amount) }}</span>
        <span class="text-sm font-semibold" :class="progressTextClass">{{ progressPercent }}%</span>
      </div>
      <div class="w-full bg-horizon-200 rounded-full h-2.5">
        <div
          class="h-2.5 rounded-full transition-all duration-500"
          :class="progressBarClass"
          :style="{ width: Math.min(progressPercent, 100) + '%' }"
        ></div>
      </div>
    </div>

    <!-- Monthly Contribution -->
    <div v-if="showActions" class="mt-3 pt-3 border-t border-light-gray">
      <div v-if="!editingContribution" class="flex items-center justify-between">
        <span class="text-xs text-neutral-500">Monthly contribution</span>
        <div class="flex items-center gap-2">
          <span class="text-sm font-medium text-horizon-500">
            {{ goal.monthly_contribution ? formatCurrency(goal.monthly_contribution) : 'Not set' }}
          </span>
          <button
            @click.stop="editingContribution = true"
            class="text-xs text-raspberry-500 hover:text-raspberry-600"
          >
            {{ goal.monthly_contribution ? 'Edit' : 'Set' }}
          </button>
        </div>
      </div>
      <div v-else class="flex items-center gap-2" @click.stop>
        <div class="relative flex-1">
          <div class="absolute inset-y-0 left-0 pl-2 flex items-center pointer-events-none">
            <span class="text-neutral-500 text-xs">&pound;</span>
          </div>
          <input
            v-model.number="contributionAmount"
            type="number"
            min="0"
            step="1"
            class="input-field pl-5 py-1 text-sm"
            placeholder="0"
            @keyup.enter="saveContribution"
          />
        </div>
        <button @click.stop="saveContribution" class="text-xs font-medium text-spring-600 hover:text-spring-700">Save</button>
        <button @click.stop="cancelContribution" class="text-xs text-neutral-500 hover:text-neutral-600">Cancel</button>
      </div>
    </div>

    <!-- Metrics Grid -->
    <div class="grid grid-cols-2 gap-3 text-sm mb-4 mt-3">
      <div>
        <p class="text-xs text-neutral-500">Time Remaining</p>
        <p class="font-medium text-horizon-500">{{ timeRemaining }}</p>
      </div>
      <div v-if="!showActions">
        <p class="text-xs text-neutral-500">Monthly Contribution</p>
        <p class="font-medium text-horizon-500">{{ goal.monthly_contribution ? formatCurrency(goal.monthly_contribution) : 'Not set' }}</p>
      </div>
    </div>

    <!-- Streak Display (if available) -->
    <div v-if="goal.contribution_streak > 0" class="flex items-center gap-2 mb-4 py-2 px-3 bg-violet-50 rounded-lg">
      <span class="text-lg">🔥</span>
      <span class="text-sm font-medium text-violet-700">{{ goal.contribution_streak }} month streak!</span>
    </div>

    <!-- Status Badge -->
    <div class="flex items-center justify-between pt-3 border-t border-savannah-100">
      <span
        class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium"
        :class="statusBadgeClass"
      >
        <span class="w-1.5 h-1.5 rounded-full mr-1.5" :class="statusDotClass"></span>
        {{ statusText }}
      </span>
      <button
        v-if="goal.status === 'active'"
        @click.stop="$emit('add-contribution', goal)"
        class="text-xs font-medium text-raspberry-600 hover:text-raspberry-700"
      >
        + Add Contribution
      </button>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';
import { GOAL_TYPE_ICONS } from '@/constants/goalIcons';

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
    dependencyCount: {
      type: Number,
      default: 0,
    },
    isBlocked: {
      type: Boolean,
      default: false,
    },
  },

  emits: ['view', 'edit', 'delete', 'add-contribution', 'update-contribution'],

  data() {
    return {
      editingContribution: false,
      contributionAmount: this.goal.monthly_contribution || 0,
    };
  },

  methods: {
    saveContribution() {
      this.$emit('update-contribution', {
        goalId: this.goal.id,
        monthly_contribution: this.contributionAmount,
      });
      this.editingContribution = false;
    },
    cancelContribution() {
      this.contributionAmount = this.goal.monthly_contribution || 0;
      this.editingContribution = false;
    },
  },

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
      if (this.progressPercent >= 100) return 'text-spring-600';
      if (this.isNotStarted) return 'text-neutral-500';
      if (this.isOnTrack) return 'text-violet-600';
      return 'text-violet-600';
    },

    progressBarClass() {
      if (this.progressPercent >= 100) return 'bg-spring-500';
      if (this.isNotStarted) return 'bg-horizon-300';
      if (this.isOnTrack) return 'bg-violet-500';
      return 'bg-violet-500';
    },

    borderColorClass() {
      if (this.goal.status === 'completed') return 'border-l-green-500';
      if (this.isNotStarted) return 'border-l-horizon-300';
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
      if (this.goal.status === 'completed' || this.progressPercent >= 100) return 'bg-spring-100 text-spring-800';
      if (this.goal.status === 'paused') return 'bg-savannah-100 text-horizon-500';
      if (this.isNotStarted) return 'bg-savannah-100 text-neutral-500';
      if (this.isOnTrack) return 'bg-violet-100 text-violet-800';
      return 'bg-violet-100 text-violet-800';
    },

    statusDotClass() {
      if (this.goal.status === 'completed' || this.progressPercent >= 100) return 'bg-spring-500';
      if (this.goal.status === 'paused') return 'bg-savannah-1000';
      if (this.isNotStarted) return 'bg-horizon-400';
      if (this.isOnTrack) return 'bg-violet-500';
      return 'bg-violet-500';
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
        investment: 'bg-violet-100 text-violet-700',
        property: 'bg-purple-100 text-purple-700',
        retirement: 'bg-violet-100 text-violet-700',
      };
      return classes[this.goal.assigned_module] || 'bg-savannah-100 text-neutral-500';
    },

    priorityTagClass() {
      if (this.goal.priority === 'critical') return 'bg-raspberry-100 text-raspberry-700';
      if (this.goal.priority === 'high') return 'bg-violet-100 text-violet-700';
      return 'bg-savannah-100 text-neutral-500';
    },

    goalTypeIcon() {
      return GOAL_TYPE_ICONS[this.goal.goal_type] || '🎯';
    },
  },
};
</script>
