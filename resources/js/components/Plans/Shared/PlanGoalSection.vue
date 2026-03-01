<template>
  <div v-if="hasContent" class="plan-goal-section mb-6">
    <!-- Linked Goals -->
    <div v-if="linkedGoals.length > 0">
      <PlanSectionHeader
        title="Linked Goals"
        subtitle="Goals linked to your accounts with progress tracking"
        color="teal"
      />

      <div class="space-y-3">
        <div
          v-for="(goal, index) in linkedGoals"
          :key="goal.id"
          class="goal-card"
          :style="{ animationDelay: (index * 60) + 'ms' }"
        >
          <!-- Header: name, type, and status badge -->
          <div class="flex items-start justify-between mb-3">
            <div class="min-w-0 flex-1 mr-3">
              <h4 class="text-sm font-semibold text-gray-900 leading-snug">{{ goal.name }}</h4>
              <span class="text-xs text-gray-500 mt-0.5 block">{{ goal.display_type }}</span>
            </div>
            <span class="goal-status-badge" :class="statusBadgeClass(goal)">
              <span class="status-dot" :class="statusDotClass(goal)"></span>
              {{ statusLabel(goal) }}
            </span>
          </div>

          <!-- Progress bar -->
          <GoalProgressBar
            :percentage="goal.progress_percentage"
            :current-amount="goal.current_amount"
            :target-amount="goal.target_amount"
            :is-on-track="goal.is_on_track"
            size="sm"
            :show-amounts="true"
          />

          <!-- Meta info -->
          <div v-if="goal.months_remaining > 0 || goal.monthly_contribution > 0" class="goal-meta">
            <span v-if="goal.months_remaining > 0" class="goal-meta-item">
              <svg class="w-3.5 h-3.5 mr-1 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              {{ goal.months_remaining }} {{ goal.months_remaining === 1 ? 'month' : 'months' }} remaining
            </span>
            <span v-if="goal.months_remaining > 0 && goal.monthly_contribution > 0" class="goal-meta-divider"></span>
            <span v-if="goal.monthly_contribution > 0" class="goal-meta-item">
              <svg class="w-3.5 h-3.5 mr-1 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              {{ formatCurrency(goal.monthly_contribution) }}/month
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Unlinked Goals Prompt -->
    <div v-if="unlinkedGoals.length > 0" class="mt-4">
      <div class="unlinked-goals-prompt">
        <div class="flex items-start gap-3">
          <div class="unlinked-goals-icon">
            <svg class="w-4 h-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
            </svg>
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-gray-900">
              {{ unlinkedGoals.length }} {{ unlinkedGoals.length === 1 ? 'goal needs' : 'goals need' }} a linked account
            </p>
            <p class="text-sm text-gray-600 mt-1 leading-relaxed">
              {{ unlinkedGoalNames }} — link {{ unlinkedGoals.length === 1 ? 'this goal' : 'these goals' }} to an account to track progress automatically.
            </p>
            <router-link
              to="/goals"
              class="unlinked-goals-link"
            >
              Manage goals
              <svg class="w-3.5 h-3.5 ml-1 transition-transform duration-150 group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
              </svg>
            </router-link>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';
import PlanSectionHeader from './PlanSectionHeader.vue';
import GoalProgressBar from '@/components/Goals/GoalProgressBar.vue';

export default {
  name: 'PlanGoalSection',

  components: {
    PlanSectionHeader,
    GoalProgressBar,
  },

  mixins: [currencyMixin],

  props: {
    linkedGoals: {
      type: Array,
      default: () => [],
    },
    unlinkedGoals: {
      type: Array,
      default: () => [],
    },
  },

  computed: {
    hasContent() {
      return this.linkedGoals.length > 0 || this.unlinkedGoals.length > 0;
    },

    unlinkedGoalNames() {
      return this.unlinkedGoals.map((g) => g.name).join(', ');
    },
  },

  methods: {
    statusBadgeClass(goal) {
      if (goal.progress_percentage >= 100) {
        return 'badge-complete';
      }
      if (goal.is_on_track) {
        return 'badge-on-track';
      }
      return 'badge-behind';
    },

    statusDotClass(goal) {
      if (goal.progress_percentage >= 100) {
        return 'dot-complete';
      }
      if (goal.is_on_track) {
        return 'dot-on-track';
      }
      return 'dot-behind';
    },

    statusLabel(goal) {
      if (goal.progress_percentage >= 100) {
        return 'Complete';
      }
      if (goal.is_on_track) {
        return 'On track';
      }
      return 'Behind';
    },
  },
};
</script>

<style scoped>
/* -- Entrance animation for goal cards -- */
@keyframes goalCardEnter {
  from {
    opacity: 0;
    transform: translateY(8px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.goal-card {
  @apply bg-white border border-gray-200 rounded-lg p-5 shadow-sm;
  animation: goalCardEnter 0.3s cubic-bezier(0.4, 0, 0.2, 1) both;
  transition: border-color 0.2s cubic-bezier(0.4, 0, 0.2, 1),
              box-shadow 0.2s cubic-bezier(0.4, 0, 0.2, 1),
              transform 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.goal-card:hover {
  @apply border-gray-300 shadow-md;
  transform: translateY(-1px);
}

/* -- Status badges with pill shape and indicator dot -- */
.goal-status-badge {
  @apply inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium whitespace-nowrap flex-shrink-0;
  transition: opacity 0.15s ease;
}

.status-dot {
  @apply w-1.5 h-1.5 rounded-full mr-1.5 flex-shrink-0;
}

.badge-complete {
  @apply bg-green-100 text-green-800;
}
.dot-complete {
  @apply bg-green-500;
}

.badge-on-track {
  @apply bg-blue-100 text-blue-800;
}
.dot-on-track {
  @apply bg-blue-500;
}

.badge-behind {
  @apply bg-red-100 text-red-800;
}
.dot-behind {
  @apply bg-red-500;
}

/* -- Meta info row -- */
.goal-meta {
  @apply flex items-center mt-3 pt-3 border-t border-gray-100 text-xs text-gray-500;
}

.goal-meta-item {
  @apply inline-flex items-center;
}

.goal-meta-divider {
  @apply w-1 h-1 rounded-full bg-gray-300 mx-3 flex-shrink-0;
}

/* -- Unlinked goals prompt -- */
.unlinked-goals-prompt {
  @apply bg-gray-50 border border-gray-200 rounded-lg p-5;
  transition: border-color 0.2s ease;
}

.unlinked-goals-prompt:hover {
  @apply border-gray-300;
}

.unlinked-goals-icon {
  @apply w-8 h-8 rounded-full bg-blue-50 flex items-center justify-center flex-shrink-0 mt-0.5;
}

.unlinked-goals-link {
  @apply inline-flex items-center text-sm font-medium mt-2.5;
  @apply text-primary-600;
  transition: color 0.15s ease;
}

.unlinked-goals-link:hover {
  @apply text-primary-700;
}

.unlinked-goals-link:hover svg {
  transform: translateX(2px);
}

/* -- Reduced motion support -- */
@media (prefers-reduced-motion: reduce) {
  .goal-card {
    animation: none;
  }
  .goal-card:hover {
    transform: none;
  }
  .unlinked-goals-link:hover svg {
    transform: none;
  }
}
</style>
