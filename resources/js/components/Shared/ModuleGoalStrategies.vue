<template>
  <div class="module-goal-strategies">
    <!-- Empty state -->
    <div v-if="!strategies || strategies.length === 0" class="text-center py-6">
      <svg class="mx-auto h-10 w-10 text-horizon-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3 3v1.5M3 21v-6m0 0l2.77-.693a9 9 0 016.208.682l.108.054a9 9 0 006.086.71l3.114-.732a48.524 48.524 0 01-.005-10.499l-3.11.732a9 9 0 01-6.085-.711l-.108-.054a9 9 0 00-6.208-.682L3 4.5M3 15V4.5" />
      </svg>
      <p class="mt-2 text-sm text-neutral-500">No goals assigned to this section.</p>
      <router-link
        to="/goals"
        class="mt-1 inline-block text-sm font-medium text-raspberry-600 hover:text-raspberry-700"
      >
        Create a Goal
      </router-link>
    </div>

    <!-- Goal strategies list -->
    <div v-else class="space-y-3">
      <!-- Summary bar -->
      <div v-if="summary && summary.total_goals > 1" class="flex items-center justify-between px-4 py-2.5 bg-savannah-100 rounded-lg">
        <div class="flex items-center gap-4 text-xs text-neutral-500">
          <span>{{ summary.total_goals }} {{ summary.total_goals === 1 ? 'goal' : 'goals' }}</span>
          <span>{{ formatCurrency(summary.total_monthly_commitment) }}/month committed</span>
        </div>
        <div class="flex items-center gap-1.5">
          <div class="w-24 h-1.5 bg-savannah-200 rounded-full overflow-hidden">
            <div
              class="h-full rounded-full transition-all duration-500"
              :class="overallProgressClass"
              :style="{ width: Math.min(summary.overall_progress || 0, 100) + '%' }"
            ></div>
          </div>
          <span class="text-xs font-medium text-neutral-500">{{ Math.round(summary.overall_progress || 0) }}%</span>
        </div>
      </div>

      <!-- Individual goal cards -->
      <div
        v-for="strategy in strategies"
        :key="strategy.goal.id"
        class="border border-light-gray rounded-lg overflow-hidden hover:border-horizon-300 transition-colors duration-150"
      >
        <!-- Collapsed header (always visible) -->
        <button
          @click="toggleGoal(strategy.goal.id)"
          class="w-full flex items-center gap-3 p-4 text-left hover:bg-savannah-100 transition-colors duration-150"
        >
          <!-- Progress indicator dot -->
          <div
            class="flex-shrink-0 w-2.5 h-2.5 rounded-full"
            :class="statusDotClass(strategy)"
          ></div>

          <!-- Goal info -->
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
              <span class="text-sm font-semibold text-horizon-500 truncate">{{ strategy.goal.name }}</span>
              <span
                v-if="strategy.goal.priority === 'critical' || strategy.goal.priority === 'high'"
                class="flex-shrink-0 text-xs px-1.5 py-0.5 rounded-full font-medium bg-raspberry-100 text-raspberry-700"
              >
                {{ strategy.goal.priority }}
              </span>
            </div>
            <div class="flex items-center gap-2 mt-0.5">
              <span class="text-xs text-neutral-500">
                {{ formatCurrency(strategy.goal.current_amount) }} of {{ formatCurrency(strategy.goal.target_amount) }}
              </span>
              <span class="text-xs text-horizon-400">|</span>
              <span class="text-xs text-neutral-500">
                {{ formatCurrency(strategy.contribution_plan.monthly_amount) }}/month
              </span>
            </div>
          </div>

          <!-- Progress bar + badge -->
          <div class="flex items-center gap-3 flex-shrink-0">
            <div class="w-20 h-1.5 bg-savannah-200 rounded-full overflow-hidden">
              <div
                class="h-full rounded-full transition-all duration-500"
                :class="progressBarClass(strategy)"
                :style="{ width: Math.min(strategy.goal.progress_percentage || 0, 100) + '%' }"
              ></div>
            </div>
            <span
              class="text-xs font-medium px-2 py-0.5 rounded-full"
              :class="onTrackBadgeClass(strategy)"
            >
              {{ strategy.goal.is_on_track ? 'On Track' : 'Behind' }}
            </span>
            <svg
              class="w-4 h-4 text-horizon-400 transition-transform duration-200"
              :class="{ 'rotate-180': expandedGoals[strategy.goal.id] }"
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
              stroke-width="2"
              stroke="currentColor"
            >
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
            </svg>
          </div>
        </button>

        <!-- Expanded details -->
        <transition name="expand">
          <div v-if="expandedGoals[strategy.goal.id]" class="px-4 pb-4 border-t border-light-gray">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-3">
              <!-- Contribution Plan -->
              <div>
                <h5 class="text-xs font-semibold text-neutral-500 uppercase tracking-wide mb-2">Contribution Plan</h5>
                <div class="space-y-1.5">
                  <div class="flex justify-between text-sm">
                    <span class="text-neutral-500">Monthly Amount</span>
                    <span class="font-medium text-horizon-500">{{ formatCurrency(strategy.contribution_plan.monthly_amount) }}</span>
                  </div>
                  <div class="flex justify-between text-sm">
                    <span class="text-neutral-500">Required to Stay On Track</span>
                    <span class="font-medium text-horizon-500">{{ formatCurrency(strategy.contribution_plan.required_monthly_to_stay_on_track) }}</span>
                  </div>
                  <div v-if="strategy.contribution_plan.streak && strategy.contribution_plan.streak.current_streak > 0" class="flex justify-between text-sm">
                    <span class="text-neutral-500">Streak</span>
                    <span class="font-medium text-horizon-500">{{ strategy.contribution_plan.streak.streak_label }}</span>
                  </div>
                  <div v-if="strategy.contribution_plan.next_due" class="flex justify-between text-sm">
                    <span class="text-neutral-500">Next Due</span>
                    <span class="font-medium text-horizon-500">{{ formatDate(strategy.contribution_plan.next_due) }}</span>
                  </div>
                </div>
              </div>

              <!-- Affordability -->
              <div>
                <h5 class="text-xs font-semibold text-neutral-500 uppercase tracking-wide mb-2">Affordability</h5>
                <div class="space-y-1.5">
                  <div class="flex justify-between text-sm">
                    <span class="text-neutral-500">Assessment</span>
                    <span
                      class="font-medium px-2 py-0.5 rounded-full text-xs"
                      :class="affordabilityBadgeClass(strategy.affordability.category)"
                    >
                      {{ strategy.affordability.category_label }}
                    </span>
                  </div>
                  <div class="flex justify-between text-sm">
                    <span class="text-neutral-500">Surplus After Goal</span>
                    <span class="font-medium text-horizon-500">{{ formatCurrency(strategy.affordability.monthly_surplus_after_goal) }}</span>
                  </div>
                </div>
              </div>

              <!-- Projections (investment/retirement goals) -->
              <div v-if="strategy.projections" class="sm:col-span-2">
                <h5 class="text-xs font-semibold text-neutral-500 uppercase tracking-wide mb-2">Projections</h5>
                <div class="flex gap-6">
                  <div v-if="strategy.projections.probability_of_success !== null" class="text-sm">
                    <span class="text-neutral-500">Success Probability</span>
                    <span class="ml-2 font-semibold text-horizon-500">{{ Math.round(strategy.projections.probability_of_success) }}%</span>
                  </div>
                  <div v-if="strategy.projections.expected_completion_date" class="text-sm">
                    <span class="text-neutral-500">Expected Completion</span>
                    <span class="ml-2 font-medium text-horizon-500">{{ formatDate(strategy.projections.expected_completion_date) }}</span>
                  </div>
                </div>
              </div>

              <!-- Recommendations -->
              <div v-if="strategy.recommendations && strategy.recommendations.length > 0" class="sm:col-span-2">
                <h5 class="text-xs font-semibold text-neutral-500 uppercase tracking-wide mb-2">Recommendations</h5>
                <ul class="space-y-1">
                  <li
                    v-for="(rec, idx) in strategy.recommendations"
                    :key="idx"
                    class="flex items-start gap-2 text-sm text-neutral-500"
                  >
                    <svg class="flex-shrink-0 w-4 h-4 text-raspberry-500 mt-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 18v-5.25m0 0a6.01 6.01 0 001.5-.189m-1.5.189a6.01 6.01 0 01-1.5-.189m3.75 7.478a12.06 12.06 0 01-4.5 0m3.75 2.383a14.406 14.406 0 01-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 10-7.517 0c.85.493 1.509 1.333 1.509 2.316V18" />
                    </svg>
                    <span>{{ rec }}</span>
                  </li>
                </ul>
              </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-end gap-3 mt-4 pt-3 border-t border-light-gray">
              <router-link
                :to="`/goals?goal=${strategy.goal.id}`"
                class="text-xs font-medium text-raspberry-600 hover:text-raspberry-700"
              >
                View Full Details
              </router-link>
            </div>
          </div>
        </transition>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'ModuleGoalStrategies',
  mixins: [currencyMixin],

  props: {
    module: {
      type: String,
      required: true,
    },
    strategies: {
      type: Array,
      default: () => [],
    },
    summary: {
      type: Object,
      default: null,
    },
  },

  data() {
    return {
      expandedGoals: {},
    };
  },

  computed: {
    overallProgressClass() {
      const progress = this.summary?.overall_progress || 0;
      if (progress >= 75) return 'bg-spring-500';
      if (progress >= 50) return 'bg-violet-500';
      if (progress >= 25) return 'bg-violet-400';
      return 'bg-horizon-400';
    },
  },

  methods: {
    toggleGoal(goalId) {
      this.expandedGoals = {
        ...this.expandedGoals,
        [goalId]: !this.expandedGoals[goalId],
      };
    },

    statusDotClass(strategy) {
      if (strategy.goal.status === 'completed') return 'bg-spring-500';
      if (strategy.goal.is_on_track) return 'bg-spring-500';
      return 'bg-raspberry-500';
    },

    progressBarClass(strategy) {
      if (strategy.goal.is_on_track) return 'bg-spring-500';
      return 'bg-raspberry-400';
    },

    onTrackBadgeClass(strategy) {
      if (strategy.goal.is_on_track) return 'bg-spring-100 text-spring-700';
      return 'bg-raspberry-100 text-raspberry-700';
    },

    affordabilityBadgeClass(category) {
      const classes = {
        comfortable: 'bg-spring-100 text-spring-700',
        moderate: 'bg-violet-100 text-violet-700',
        challenging: 'bg-violet-100 text-violet-700',
        stretch: 'bg-violet-100 text-violet-700',
        overcommitted: 'bg-raspberry-100 text-raspberry-700',
        unaffordable: 'bg-raspberry-100 text-raspberry-700',
        completed: 'bg-spring-100 text-spring-700',
      };
      return classes[category] || 'bg-savannah-100 text-neutral-500';
    },

    formatDate(date) {
      if (!date) return '-';
      return new Date(date).toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
      });
    },
  },
};
</script>

