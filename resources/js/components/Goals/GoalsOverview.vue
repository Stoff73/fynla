<template>
  <div class="goals-overview">
    <!-- Empty State -->
    <div v-if="!hasGoals" class="text-center py-12">
      <div class="mx-auto w-16 h-16 rounded-full bg-primary-100 flex items-center justify-center mb-4">
        <svg class="w-8 h-8 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
        </svg>
      </div>
      <h3 class="text-lg font-semibold text-gray-900 mb-2">Set Your First Financial Goal</h3>
      <p class="text-gray-600 mb-6 max-w-md mx-auto">
        People with structured financial plans are 78% more likely to feel on track.
        Start by setting a clear goal with a timeline.
      </p>
      <button
        @click="$emit('create-goal')"
        class="px-6 py-3 text-base font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 transition-colors"
      >
        Create Your First Goal
      </button>
    </div>

    <!-- Overview Content -->
    <div v-else>
      <!-- Summary Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <!-- Total Goals -->
        <div class="bg-gray-50 rounded-lg p-4">
          <p class="text-sm text-gray-500 mb-1">Total Goals</p>
          <p class="text-2xl font-bold text-gray-900">{{ summary.total_goals }}</p>
        </div>

        <!-- On Track -->
        <div class="bg-green-50 rounded-lg p-4">
          <p class="text-sm text-green-600 mb-1">On Track</p>
          <p class="text-2xl font-bold text-green-700">{{ summary.on_track_count }}</p>
        </div>

        <!-- Total Target -->
        <div class="bg-blue-50 rounded-lg p-4">
          <p class="text-sm text-blue-600 mb-1">Total Target</p>
          <p class="text-2xl font-bold text-blue-700">{{ formatCurrency(summary.total_target) }}</p>
        </div>

        <!-- Current Progress -->
        <div class="bg-primary-50 rounded-lg p-4">
          <p class="text-sm text-primary-600 mb-1">Current Progress</p>
          <p class="text-2xl font-bold text-primary-700">{{ formatCurrency(summary.total_current) }}</p>
        </div>
      </div>

      <!-- Overall Progress Bar -->
      <div class="bg-white border border-gray-200 rounded-lg p-6 mb-8">
        <div class="flex justify-between items-center mb-2">
          <span class="text-sm font-medium text-gray-700">Overall Progress</span>
          <span class="text-sm font-semibold" :class="progressTextClass">{{ overallProgress }}%</span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-3">
          <div
            class="h-3 rounded-full transition-all duration-500"
            :class="progressBarClass"
            :style="{ width: Math.min(overallProgress, 100) + '%' }"
          ></div>
        </div>
        <div class="flex justify-between mt-2 text-xs text-gray-500">
          <span>{{ formatCurrency(summary.total_current) }} saved</span>
          <span>{{ formatCurrency(summary.total_target) }} target</span>
        </div>
      </div>

      <!-- Streak Banner -->
      <div
        v-if="bestStreak >= 3"
        class="mb-8 p-4 bg-orange-50 border border-orange-200 rounded-lg flex items-center gap-3"
      >
        <span class="text-2xl">🔥</span>
        <div>
          <p class="text-base font-semibold text-orange-700">
            {{ bestStreak }} month contribution streak!
          </p>
          <p class="text-sm text-orange-600">Keep up the great work!</p>
        </div>
      </div>

      <!-- Top Goals -->
      <div class="mb-8">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-semibold text-gray-900">Top Goals</h3>
          <button
            @click="$emit('create-goal')"
            class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 transition-colors flex items-center gap-2"
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
            class="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md hover:border-primary-300 cursor-pointer transition-all"
          >
            <div class="flex items-center gap-2 mb-2">
              <span class="text-lg">{{ getGoalIcon(goal.goal_type) }}</span>
              <h4 class="font-medium text-gray-900 truncate">{{ goal.name }}</h4>
            </div>

            <div class="flex items-center gap-2 mb-3">
              <span class="text-xs px-2 py-0.5 rounded-full" :class="getModuleTagClass(goal.assigned_module)">
                {{ getModuleLabel(goal.assigned_module) }}
              </span>
              <span
                class="w-2 h-2 rounded-full"
                :class="getGoalStatusDotClass(goal)"
              ></span>
              <span class="text-xs text-gray-500">
                {{ getGoalStatusLabel(goal) }}
              </span>
            </div>

            <div class="mb-2">
              <div class="w-full bg-gray-200 rounded-full h-2">
                <div
                  class="h-2 rounded-full"
                  :class="getGoalProgressBarClass(goal)"
                  :style="{ width: Math.min(goal.progress_percentage, 100) + '%' }"
                ></div>
              </div>
            </div>

            <div class="flex justify-between text-sm">
              <span class="text-gray-600">{{ Math.round(goal.progress_percentage) }}% complete</span>
              <span class="font-medium text-gray-900">{{ formatCurrency(goal.target_amount) }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Status Summary -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <!-- On Track Status -->
        <div
          v-if="summary.on_track_count === summary.total_goals"
          class="p-4 bg-white border-2 border-green-600 rounded-lg"
        >
          <div class="flex items-center gap-3">
            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div>
              <p class="font-semibold text-green-700">All goals on track!</p>
              <p class="text-sm text-green-600">Keep up the great progress</p>
            </div>
          </div>
        </div>

        <!-- Needs Attention Status -->
        <div
          v-else-if="summary.on_track_count < summary.total_goals"
          class="p-4 bg-white border-2 border-orange-500 rounded-lg"
        >
          <div class="flex items-center gap-3">
            <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <div>
              <p class="font-semibold text-orange-700">
                {{ summary.total_goals - summary.on_track_count }}
                {{ (summary.total_goals - summary.on_track_count) === 1 ? 'goal needs' : 'goals need' }} attention
              </p>
              <p class="text-sm text-orange-600">Consider increasing contributions</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'GoalsOverview',
  mixins: [currencyMixin],

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

  emits: ['create-goal', 'view-goal'],

  computed: {
    hasGoals() {
      return this.summary.total_goals > 0;
    },

    overallProgress() {
      return Math.round(this.summary.overall_progress || 0);
    },

    progressTextClass() {
      if (this.overallProgress >= 75) return 'text-green-600';
      if (this.overallProgress >= 50) return 'text-blue-600';
      return 'text-orange-600';
    },

    progressBarClass() {
      if (this.overallProgress >= 75) return 'bg-green-500';
      if (this.overallProgress >= 50) return 'bg-blue-500';
      return 'bg-orange-500';
    },
  },

  methods: {
    getGoalIcon(goalType) {
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
      return icons[goalType] || '🎯';
    },

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
        investment: 'bg-blue-100 text-blue-700',
        property: 'bg-purple-100 text-purple-700',
        retirement: 'bg-orange-100 text-orange-700',
      };
      return classes[module] || 'bg-gray-100 text-gray-700';
    },

    isNotStarted(goal) {
      return parseFloat(goal.current_amount) <= 0;
    },

    getGoalStatusDotClass(goal) {
      if (this.isNotStarted(goal)) return 'bg-gray-400';
      if (goal.is_on_track) return 'bg-green-500';
      return 'bg-orange-500';
    },

    getGoalStatusLabel(goal) {
      if (this.isNotStarted(goal)) return 'Not started';
      if (goal.is_on_track) return 'On track';
      return 'Behind';
    },

    getGoalProgressBarClass(goal) {
      if (this.isNotStarted(goal)) return 'bg-gray-300';
      if (goal.is_on_track) return 'bg-blue-500';
      return 'bg-orange-500';
    },
  },
};
</script>
