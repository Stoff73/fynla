<template>
  <div class="goals-by-module">
    <!-- Module Sections -->
    <div v-for="(config, module) in moduleConfigs" :key="module" class="mb-8">
      <div class="flex items-center gap-3 mb-4">
        <span class="text-2xl">{{ config.icon }}</span>
        <h3 class="text-lg font-semibold text-horizon-500">{{ config.label }}</h3>
        <span
          class="px-2 py-0.5 text-xs font-medium rounded-full"
          :class="config.tagClass"
        >
          {{ getModuleGoals(module).length }} {{ getModuleGoals(module).length === 1 ? 'goal' : 'goals' }}
        </span>
      </div>

      <!-- Empty State for Module -->
      <div v-if="getModuleGoals(module).length === 0" class="bg-savannah-100 rounded-lg p-6 text-center">
        <p class="text-neutral-500">No goals assigned to {{ config.label.toLowerCase() }} yet</p>
      </div>

      <!-- Goals for Module -->
      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div
          v-for="goal in getModuleGoals(module)"
          :key="goal.id"
          @click="$emit('view-goal', goal)"
          class="bg-white border border-light-gray rounded-lg p-4 hover:shadow-md hover:bg-light-gray cursor-pointer transition-all"
        >
          <div class="flex items-center gap-2 mb-2">
            <span class="text-lg">{{ getGoalIcon(goal.goal_type) }}</span>
            <h4 class="font-medium text-horizon-500 truncate flex-1">{{ goal.goal_name }}</h4>
            <button
              @click.stop="$emit('edit-goal', goal)"
              class="p-1 text-horizon-400 hover:text-raspberry-600 rounded"
            >
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
              </svg>
            </button>
          </div>

          <!-- Progress -->
          <div class="mb-3">
            <div class="flex justify-between text-xs mb-1">
              <span class="text-neutral-500">{{ formatCurrency(goal.current_amount) }}</span>
              <span class="font-medium" :class="getProgressTextClass(goal)">
                {{ Math.round(goal.progress_percentage || 0) }}%
              </span>
            </div>
            <div class="w-full bg-horizon-200 rounded-full h-2">
              <div
                class="h-2 rounded-full transition-all"
                :class="getProgressBarClass(goal)"
                :style="{ width: Math.min(goal.progress_percentage || 0, 100) + '%' }"
              ></div>
            </div>
          </div>

          <!-- Meta Info -->
          <div class="flex justify-between items-center text-sm">
            <span class="text-neutral-500">{{ formatTimeRemaining(goal.days_remaining) }}</span>
            <span class="font-semibold text-horizon-500">{{ formatCurrency(goal.target_amount) }}</span>
          </div>

          <!-- Status Badge -->
          <div class="mt-3 flex items-center gap-2">
            <span
              class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
              :class="getStatusBadgeClass(goal)"
            >
              <span
                class="w-1.5 h-1.5 rounded-full mr-1"
                :class="getStatusDotClass(goal)"
              ></span>
              {{ getStatusLabel(goal) }}
            </span>
            <span
              v-if="goal.priority === 'critical' || goal.priority === 'high'"
              class="px-2 py-0.5 text-xs font-medium rounded-full"
              :class="goal.priority === 'critical' ? 'bg-raspberry-100 text-raspberry-700' : 'bg-violet-100 text-violet-700'"
            >
              {{ goal.priority }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Module Assignment Info -->
    <div class="mt-8 p-4 bg-violet-50 rounded-lg">
      <h4 class="text-sm font-semibold text-violet-900 mb-2">How goals are assigned to modules</h4>
      <ul class="text-sm text-violet-800 space-y-1">
        <li><strong>Savings:</strong> Emergency fund goals and short-term goals (≤3 years)</li>
        <li><strong>Investment:</strong> Long-term goals (>3 years) with target ≥ £5,000</li>
        <li><strong>Property:</strong> Property purchase and home deposit goals</li>
        <li><strong>Retirement:</strong> Retirement-specific goals</li>
      </ul>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'GoalsByModule',
  mixins: [currencyMixin],

  props: {
    goalsByModule: {
      type: Object,
      default: () => ({
        savings: [],
        investment: [],
        property: [],
        retirement: [],
      }),
    },
  },

  emits: ['edit-goal', 'view-goal'],

  data() {
    return {
      moduleConfigs: {
        savings: {
          label: 'Savings',
          icon: '💰',
          tagClass: 'bg-emerald-100 text-emerald-700',
        },
        investment: {
          label: 'Investment',
          icon: '📈',
          tagClass: 'bg-violet-100 text-violet-700',
        },
        property: {
          label: 'Property',
          icon: '🏠',
          tagClass: 'bg-purple-100 text-purple-700',
        },
        retirement: {
          label: 'Retirement',
          icon: '☀️',
          tagClass: 'bg-violet-100 text-violet-700',
        },
      },
    };
  },

  methods: {
    getModuleGoals(module) {
      return this.goalsByModule[module] || [];
    },

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

    isNotStarted(goal) {
      return parseFloat(goal.current_amount) <= 0;
    },

    getProgressTextClass(goal) {
      if (this.isNotStarted(goal)) return 'text-neutral-500';
      if (goal.is_on_track) return 'text-violet-600';
      return 'text-violet-600';
    },

    getProgressBarClass(goal) {
      if (this.isNotStarted(goal)) return 'bg-horizon-300';
      if (goal.is_on_track) return 'bg-violet-500';
      return 'bg-violet-500';
    },

    getStatusBadgeClass(goal) {
      if (this.isNotStarted(goal)) return 'bg-savannah-100 text-neutral-500';
      if (goal.is_on_track) return 'bg-spring-100 text-spring-700';
      return 'bg-violet-100 text-violet-700';
    },

    getStatusDotClass(goal) {
      if (this.isNotStarted(goal)) return 'bg-horizon-400';
      if (goal.is_on_track) return 'bg-spring-500';
      return 'bg-violet-500';
    },

    getStatusLabel(goal) {
      if (this.isNotStarted(goal)) return 'Not Started';
      if (goal.is_on_track) return 'On Track';
      return 'Behind';
    },

    formatTimeRemaining(days) {
      if (days === undefined || days === null) return '';
      if (days < 0) return 'Overdue';
      if (days === 0) return 'Today';
      if (days === 1) return '1 day left';
      if (days < 30) return `${days} days left`;
      if (days < 365) {
        const months = Math.floor(days / 30);
        return `${months} ${months === 1 ? 'month' : 'months'} left`;
      }
      const years = Math.floor(days / 365);
      const months = Math.floor((days % 365) / 30);
      if (months === 0) return `${years} ${years === 1 ? 'year' : 'years'} left`;
      return `${years}y ${months}m left`;
    },
  },
};
</script>
