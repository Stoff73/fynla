<template>
  <div class="bg-white rounded-lg border border-light-gray shadow-sm p-6">
    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-semibold text-horizon-500">Your Goals</h3>
      <button
        class="text-sm font-semibold text-raspberry-500 hover:text-raspberry-600 transition-colors"
        @click="$emit('add-goal')"
      >
        + Add goal
      </button>
    </div>

    <!-- Active goals list -->
    <div v-if="activeGoals.length > 0" class="space-y-4">
      <div
        v-for="goal in displayedGoals"
        :key="goal.id"
        class="cursor-pointer hover:bg-savannah-100 rounded-lg p-2 -mx-2 transition-colors"
        @click="navigateToGoal(goal)"
      >
        <div class="flex items-center justify-between mb-1.5">
          <span class="text-sm font-medium text-horizon-500 truncate mr-2">{{ goal.goal_name || goal.name }}</span>
          <span class="text-xs text-neutral-500 whitespace-nowrap">{{ goalProgress(goal) }}%</span>
        </div>

        <!-- Progress bar -->
        <div class="h-1.5 bg-eggshell-500 rounded-full overflow-hidden mb-1.5">
          <div
            class="h-full rounded-full transition-all duration-300"
            :class="goalBarClass(goal)"
            :style="{ width: Math.min(goalProgress(goal), 100) + '%' }"
          ></div>
        </div>

        <!-- Amount and timeline -->
        <div class="flex items-center justify-between text-xs">
          <span class="text-neutral-500">
            {{ formatCurrency(parseFloat(goal.current_amount || 0)) }}
            <span class="text-neutral-500"> of </span>
            {{ formatCurrency(parseFloat(goal.target_amount || 0)) }}
          </span>
          <span v-if="goal.target_date" class="text-neutral-500">
            {{ formatTargetDate(goal.target_date) }}
          </span>
        </div>
      </div>

      <!-- Show more link if there are many goals -->
      <button
        v-if="activeGoals.length > 3"
        class="text-xs font-medium text-raspberry-500 hover:text-raspberry-600 transition-colors"
        @click="$router.push('/goals')"
      >
        View all {{ activeGoals.length }} goals
      </button>
    </div>

    <!-- Empty state when no goals -->
    <div v-else class="text-center py-4">
      <div class="mx-auto w-12 h-12 rounded-full bg-raspberry-100 flex items-center justify-center mb-3">
        <svg class="w-6 h-6 text-raspberry-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
        </svg>
      </div>
      <h4 class="text-sm font-semibold text-horizon-500 mb-1">Set Your First Goal</h4>
      <p class="text-xs text-neutral-500">Track your financial goals and milestones</p>
    </div>

  </div>
</template>

<script>
import { mapGetters } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'GoalsCard',

  mixins: [currencyMixin],

  emits: ['add-goal'],

  computed: {
    ...mapGetters('goals', ['activeGoals']),

    displayedGoals() {
      return this.activeGoals.slice(0, 3);
    },
  },

  methods: {
    goalProgress(goal) {
      const target = parseFloat(goal.target_amount || 0);
      const current = parseFloat(goal.current_amount || 0);
      if (target <= 0) return 0;
      return Math.round((current / target) * 100);
    },

    goalBarClass(goal) {
      const progress = this.goalProgress(goal);
      if (progress >= 100) return 'bg-spring-500';
      if (goal.is_on_track) return 'bg-spring-500';
      if (progress >= 50) return 'bg-violet-500';
      return 'bg-raspberry-500';
    },

    formatTargetDate(dateStr) {
      if (!dateStr) return '';
      const date = new Date(dateStr);
      const now = new Date();
      const diffMs = date - now;
      const diffDays = Math.ceil(diffMs / (1000 * 60 * 60 * 24));

      if (diffDays < 0) return 'Overdue';
      if (diffDays < 30) return `${diffDays} days left`;
      if (diffDays < 365) {
        const months = Math.round(diffDays / 30);
        return `${months} month${months !== 1 ? 's' : ''} left`;
      }
      const years = Math.round(diffDays / 365);
      return `${years} year${years !== 1 ? 's' : ''} left`;
    },

    navigateToGoal(goal) {
      this.$router.push(`/goals/${goal.id}`);
    },
  },
};
</script>
