<template>
  <div class="goals-overview">
    <!-- Quick Add Buttons -->
    <div class="flex flex-wrap gap-3 mb-6">
      <button
        @click="$emit('create-goal')"
        class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-raspberry-600 rounded-button hover:bg-raspberry-700 transition-colors"
      >
        <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Add Goal
      </button>
      <button
        @click="$emit('create-event')"
        class="inline-flex items-center px-4 py-2 text-sm font-medium text-raspberry-600 bg-raspberry-50 border border-raspberry-200 rounded-lg hover:bg-raspberry-100 transition-colors"
      >
        <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
        Add Life Event
      </button>
    </div>

    <!-- Projection Chart - Always shown -->
    <div class="mb-8">
      <GoalsProjectionChart />
    </div>

    <!-- Empty State Prompt - shown below chart when no goals -->
    <div v-if="!hasGoals" class="mb-8 p-6 bg-violet-50 border border-violet-200 rounded-lg text-center">
      <div class="mx-auto w-12 h-12 rounded-full bg-violet-100 flex items-center justify-center mb-3">
        <svg class="w-6 h-6 text-violet-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
        </svg>
      </div>
      <h3 class="text-base font-semibold text-horizon-500 mb-1">Add Your First Goal or Life Event</h3>
      <p class="text-sm text-neutral-500 mb-4 max-w-md mx-auto">
        The chart above shows your projected net worth. Add goals and life events to see how they impact your future finances.
      </p>
      <button
        @click="$emit('create-goal')"
        class="px-4 py-2 text-sm font-medium text-white bg-raspberry-600 rounded-button hover:bg-raspberry-700 transition-colors"
      >
        Create Your First Goal
      </button>
    </div>

    <!-- Goals Content - shown when goals exist -->
    <div v-if="hasGoals">

      <!-- Streak Banner -->
      <div
        v-if="bestStreak >= 3"
        class="mb-8 p-4 bg-violet-50 border border-violet-200 rounded-lg flex items-center gap-3"
      >
        <span class="text-2xl">🔥</span>
        <div>
          <p class="text-base font-semibold text-violet-700">
            {{ bestStreak }} month contribution streak!
          </p>
          <p class="text-sm text-violet-600">Keep up the great work!</p>
        </div>
      </div>

      <!-- Top Goals -->
      <div class="mb-8">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-semibold text-horizon-500">Top Goals</h3>
          <button
            @click="$emit('create-goal')"
            class="px-4 py-2 text-sm font-medium text-white bg-raspberry-600 rounded-button hover:bg-raspberry-700 transition-colors flex items-center gap-2"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Add Goal
          </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <div
            v-for="goal in topGoals"
            :key="goal.id"
            @click="$emit('view-goal', goal)"
            class="bg-white border border-light-gray rounded-lg p-4 hover:shadow-md hover:bg-light-gray cursor-pointer transition-all"
          >
            <div class="flex items-center gap-2 mb-2">
              <span class="text-lg">{{ getGoalIcon(goal.goal_type) }}</span>
              <h4 class="font-medium text-horizon-500 truncate">{{ goal.name }}</h4>
            </div>

            <div class="flex items-center gap-2 mb-3">
              <span class="text-xs px-2 py-0.5 rounded-full" :class="getModuleTagClass(goal.assigned_module)">
                {{ getModuleLabel(goal.assigned_module) }}
              </span>
              <span
                class="w-2 h-2 rounded-full"
                :class="getGoalStatusDotClass(goal)"
              ></span>
              <span class="text-xs text-neutral-500">
                {{ getGoalStatusLabel(goal) }}
              </span>
            </div>

            <div class="mb-2">
              <div class="w-full bg-horizon-200 rounded-full h-2">
                <div
                  class="h-2 rounded-full"
                  :class="getGoalProgressBarClass(goal)"
                  :style="{ width: Math.min(goal.progress_percentage, 100) + '%' }"
                ></div>
              </div>
            </div>

            <div class="flex justify-between text-sm">
              <span class="text-neutral-500">{{ Math.round(goal.progress_percentage) }}% complete</span>
              <span class="font-medium text-horizon-500">{{ formatCurrency(goal.target_amount) }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Status Summary -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <!-- On Track Status -->
        <div
          v-if="summary.on_track_count === summary.total_goals"
          class="p-4 bg-white border-2 border-spring-600 rounded-lg"
        >
          <div class="flex items-center gap-3">
            <svg class="w-6 h-6 text-spring-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div>
              <p class="font-semibold text-spring-700">All goals on track!</p>
              <p class="text-sm text-spring-600">Keep up the great progress</p>
            </div>
          </div>
        </div>

        <!-- Behind Schedule Goals -->
        <div
          v-else-if="behindGoals.length > 0"
          class="p-4 bg-white border-2 border-violet-500 rounded-lg sm:col-span-2"
        >
          <div class="flex items-start gap-3">
            <svg class="w-6 h-6 text-violet-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <div class="flex-1 min-w-0">
              <p class="font-semibold text-violet-700 mb-2">
                {{ behindGoals.length }} {{ behindGoals.length === 1 ? 'goal is' : 'goals are' }} behind schedule
              </p>
              <div class="space-y-1.5">
                <div
                  v-for="goal in behindGoals"
                  :key="goal.id"
                  class="flex items-center justify-between text-sm"
                >
                  <div class="flex items-center gap-2 min-w-0">
                    <span class="text-base flex-shrink-0">{{ getGoalIcon(goal.goal_type) }}</span>
                    <span class="text-violet-700 truncate">{{ goal.name }}</span>
                  </div>
                  <span class="text-violet-600 flex-shrink-0 ml-2">
                    {{ formatCurrency(goal.target_amount - goal.current_amount) }} remaining
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';
import { getGoalIcon } from '@/constants/goalIcons';
import GoalsProjectionChart from '@/components/Goals/GoalsProjectionChart.vue';

export default {
  name: 'GoalsOverview',
  mixins: [currencyMixin],

  components: {
    GoalsProjectionChart,
  },

  props: {
    summary: {
      type: Object,
      default: () => ({
        total_goals: 0,
        on_track_count: 0,
        total_target: 0,
        total_current: 0,
        overall_progress: 0,
      }),
    },
    topGoals: {
      type: Array,
      default: () => [],
    },
    bestStreak: {
      type: Number,
      default: 0,
    },
  },

  emits: ['create-goal', 'create-event', 'view-goal'],

  computed: {
    hasGoals() {
      return this.summary.total_goals > 0;
    },

    behindGoals() {
      return this.topGoals.filter(g => !g.is_on_track && parseFloat(g.current_amount) > 0);
    },
  },

  methods: {
    getGoalIcon,

    getModuleLabel(module) {
      const labels = {
        savings: 'Savings',
        investment: 'Investment',
        property: 'Property',
        retirement: 'Retirement',
      };
      return labels[module] || module;
    },

    getModuleTagClass(module) {
      const classes = {
        savings: 'bg-emerald-100 text-emerald-700',
        investment: 'bg-violet-100 text-violet-700',
        property: 'bg-purple-100 text-purple-700',
        retirement: 'bg-violet-100 text-violet-700',
      };
      return classes[module] || 'bg-savannah-100 text-neutral-500';
    },

    isNotStarted(goal) {
      return parseFloat(goal.current_amount) <= 0;
    },

    getGoalStatusDotClass(goal) {
      if (this.isNotStarted(goal)) return 'bg-horizon-400';
      if (goal.is_on_track) return 'bg-spring-500';
      return 'bg-violet-500';
    },

    getGoalStatusLabel(goal) {
      if (this.isNotStarted(goal)) return 'Not started';
      if (goal.is_on_track) return 'On track';
      return 'Behind';
    },

    getGoalProgressBarClass(goal) {
      if (this.isNotStarted(goal)) return 'bg-horizon-300';
      if (goal.is_on_track) return 'bg-violet-500';
      return 'bg-violet-500';
    },
  },
};
</script>
