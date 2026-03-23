<template>
  <div class="card">
    <!-- Goals Section (clickable) -->
    <div
      class="cursor-pointer hover:bg-savannah-100 -m-6 p-6 rounded-lg transition-colors"
      @click="navigateToGoals"
    >
      <!-- Empty State -->
      <div v-if="!hasGoals" class="text-center py-4">
        <div class="mx-auto w-12 h-12 rounded-full bg-raspberry-100 flex items-center justify-center mb-3">
          <svg class="w-6 h-6 text-raspberry-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
          </svg>
        </div>
        <h3 class="text-sm font-semibold text-horizon-500 mb-1">Set Your First Goal</h3>
        <p class="text-xs text-neutral-500 mb-3">
          People with structured financial plans are 78% more likely to feel on track.
        </p>
        <button
          @click.stop="showCreateGoal"
          class="px-4 py-2 text-sm font-medium text-white bg-raspberry-500 rounded-button hover:bg-raspberry-600"
        >
          Create Goal
        </button>
      </div>

      <!-- Goals Overview -->
      <template v-else>
        <!-- Primary Value Section -->
        <div class="border-b border-light-gray pb-4 mb-4">
          <span class="text-sm text-neutral-500">Overall Progress</span>
          <div class="flex items-baseline gap-2 mt-1">
            <span class="text-3xl font-bold text-raspberry-500">
              {{ formatCurrency(totalCurrent) }}
            </span>
            <span class="text-sm text-neutral-500">of {{ formatCurrency(totalTarget) }}</span>
          </div>
          <!-- Progress Bar -->
          <div class="mt-3">
            <div class="w-full bg-savannah-200 rounded-full h-2">
              <div
                class="h-2 rounded-full transition-all duration-500"
                :class="overallProgressBarClass"
                :style="{ width: Math.min(overallProgress, 100) + '%' }"
              ></div>
            </div>
            <div class="flex justify-between mt-1">
              <span class="text-xs text-neutral-500">{{ overallProgress }}% complete</span>
              <span class="text-xs font-medium" :class="onTrackTextClass">
                {{ onTrackCount }}/{{ totalGoals }} on track
              </span>
            </div>
          </div>
        </div>

        <!-- Top Goals List -->
        <div class="space-y-3">
          <div
            v-for="goal in topGoals"
            :key="goal.id"
            class="flex items-center justify-between"
          >
            <div class="flex items-center gap-2 flex-1 min-w-0">
              <span class="text-base flex-shrink-0">{{ getGoalIcon(goal.goal_type) }}</span>
              <div class="min-w-0 flex-1">
                <div class="flex items-center gap-1.5">
                  <span class="text-sm font-medium text-horizon-500 truncate">{{ goal.name }}</span>
                  <span
                    v-if="goal.is_on_track"
                    class="flex-shrink-0 w-1.5 h-1.5 rounded-full bg-spring-500"
                    title="On track"
                  ></span>
                  <span
                    v-else
                    class="flex-shrink-0 w-1.5 h-1.5 rounded-full bg-violet-500"
                    title="Behind schedule"
                  ></span>
                </div>
                <div class="flex items-center gap-1">
                  <div class="flex-1 h-1 bg-savannah-200 rounded-full max-w-[60px]">
                    <div
                      class="h-1 rounded-full"
                      :class="goal.is_on_track ? 'bg-spring-500' : 'bg-violet-500'"
                      :style="{ width: Math.min(goal.progress_percentage, 100) + '%' }"
                    ></div>
                  </div>
                  <span class="text-xs text-neutral-500">{{ Math.round(goal.progress_percentage) }}%</span>
                </div>
              </div>
            </div>
            <div class="text-right ml-2">
              <span class="text-sm font-semibold text-horizon-500">{{ formatCurrency(goal.target_amount) }}</span>
              <p class="text-xs text-neutral-500">{{ formatTimeRemaining(goal.days_remaining) }}</p>
            </div>
          </div>

          <!-- More goals indicator -->
          <p v-if="totalGoals > 5" class="text-xs text-neutral-500 pt-2">
            +{{ totalGoals - 5 }} more {{ (totalGoals - 5) === 1 ? 'goal' : 'goals' }}
          </p>
        </div>

        <!-- Streak Banner -->
        <div
          v-if="bestStreak >= 3"
          class="mt-4 p-3 bg-violet-50 border border-violet-200 rounded-lg flex items-center gap-2"
        >
          <span class="text-lg">🔥</span>
          <span class="text-sm font-medium text-violet-700">
            {{ bestStreak }} month contribution streak!
          </span>
        </div>

        <!-- Status Banner -->
        <div
          v-if="onTrackCount === totalGoals && totalGoals > 0"
          class="mt-4 p-3 bg-white border-2 border-spring-600 rounded-lg"
        >
          <div class="flex items-center gap-2">
            <svg class="w-5 h-5 text-spring-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span class="text-sm font-medium text-spring-700">All goals on track</span>
          </div>
        </div>

        <div
          v-else-if="behindGoals.length > 0"
          class="mt-4 p-3 bg-white border-2 border-violet-500 rounded-lg"
        >
          <div class="flex items-start gap-2">
            <svg class="w-5 h-5 text-violet-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <div class="min-w-0">
              <span class="text-sm font-medium text-violet-700">
                {{ behindGoals.length }} {{ behindGoals.length === 1 ? 'goal is' : 'goals are' }} behind schedule
              </span>
              <div class="mt-1 space-y-0.5">
                <p
                  v-for="goal in behindGoals.slice(0, 3)"
                  :key="goal.id"
                  class="text-xs text-violet-600 truncate"
                >
                  {{ getGoalIcon(goal.goal_type) }} {{ goal.name }} — {{ formatCurrency(goal.target_amount - goal.current_amount) }} remaining
                </p>
              </div>
            </div>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>

<script>
import { mapState, mapGetters, mapActions } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';
import { getGoalIcon } from '@/constants/goalIcons';

export default {
  name: 'GoalsOverviewCard',
  mixins: [currencyMixin],

  computed: {
    ...mapState('goals', ['dashboardOverview', 'loading']),
    ...mapGetters('goals', ['dashboardData']),

    hasGoals() {
      return this.dashboardData?.has_goals || false;
    },

    totalGoals() {
      return this.dashboardData?.total_goals || 0;
    },

    onTrackCount() {
      return this.dashboardData?.on_track_count || 0;
    },

    totalTarget() {
      return this.dashboardData?.total_target || 0;
    },

    totalCurrent() {
      return this.dashboardData?.total_current || 0;
    },

    overallProgress() {
      return Math.round(this.dashboardData?.overall_progress || 0);
    },

    topGoals() {
      return this.dashboardData?.top_goals || [];
    },

    behindGoals() {
      return this.topGoals.filter(g => !g.is_on_track && parseFloat(g.current_amount) > 0);
    },

    bestStreak() {
      return this.dashboardData?.best_streak || 0;
    },

    overallProgressBarClass() {
      if (this.overallProgress >= 75) return 'bg-spring-500';
      if (this.overallProgress >= 50) return 'bg-violet-500';
      return 'bg-violet-500';
    },

    onTrackTextClass() {
      if (this.onTrackCount === this.totalGoals) return 'text-spring-600';
      if (this.onTrackCount >= this.totalGoals / 2) return 'text-violet-600';
      return 'text-violet-600';
    },
  },

  mounted() {
    this.fetchDashboardOverview();
  },

  methods: {
    ...mapActions('goals', ['fetchDashboardOverview']),

    navigateToGoals() {
      this.$router.push('/goals');
    },

    showCreateGoal() {
      // Navigate to goals page with create modal open
      this.$router.push({ path: '/goals', query: { action: 'create' } });
    },

    getGoalIcon,

    formatTimeRemaining(days) {
      if (days === undefined || days === null) return '';
      if (days < 0) return 'Overdue';
      if (days === 0) return 'Today';
      if (days === 1) return '1 day';
      if (days < 30) return `${days}d`;
      if (days < 365) {
        const months = Math.floor(days / 30);
        return `${months}m`;
      }
      const years = Math.floor(days / 365);
      const months = Math.floor((days % 365) / 30);
      if (months === 0) return `${years}y`;
      return `${years}y ${months}m`;
    },
  },
};
</script>
