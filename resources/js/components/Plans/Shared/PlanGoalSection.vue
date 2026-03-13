<template>
  <div v-if="hasContent" class="plan-goal-section mb-6">
    <!-- Linked Goals -->
    <div v-if="linkedGoals.length > 0">
      <PlanSectionHeader
        title="Linked Goals"
        subtitle="Goals linked to your accounts with progress tracking"
        color="horizon"
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
              <h4 class="text-sm font-semibold text-horizon-500 leading-snug">{{ goal.name }}</h4>
              <span class="text-xs text-neutral-500 mt-0.5 block">{{ goal.display_type }}</span>
            </div>
            <span class="goal-status-badge" :class="goalStatus(goal).badge">
              <span class="status-dot" :class="goalStatus(goal).dot"></span>
              {{ goalStatus(goal).label }}
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

          <!-- Description -->
          <p v-if="goal.description" class="text-xs text-neutral-500 italic mt-3 leading-relaxed">
            {{ goal.description }}
          </p>

          <!-- Detail grid -->
          <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mt-3">
            <div class="bg-eggshell-500 rounded-lg p-2.5">
              <p class="text-xs text-neutral-500">Priority</p>
              <p class="text-xs font-semibold text-horizon-500 capitalize">
                {{ goal.priority || '—' }}
                <span v-if="goal.is_essential" class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-violet-100 text-violet-700">Essential</span>
              </p>
            </div>
            <div class="bg-eggshell-500 rounded-lg p-2.5">
              <p class="text-xs text-neutral-500">Target Date</p>
              <p class="text-xs font-semibold text-horizon-500">{{ formatGoalDate(goal.target_date) }}</p>
            </div>
            <div class="bg-eggshell-500 rounded-lg p-2.5">
              <p class="text-xs text-neutral-500">Amount Remaining</p>
              <p class="text-xs font-semibold text-horizon-500">{{ formatCurrency(Math.max(0, goal.target_amount - goal.current_amount)) }}</p>
            </div>
            <div class="bg-eggshell-500 rounded-lg p-2.5">
              <p class="text-xs text-neutral-500">Required Monthly</p>
              <p class="text-xs font-semibold" :class="requiredMonthlyColor(goal)">
                {{ goal.required_monthly_contribution > 0 ? formatCurrency(goal.required_monthly_contribution) : '—' }}
              </p>
            </div>
          </div>

          <!-- Meta info -->
          <div v-if="goal.months_remaining > 0 || goal.monthly_contribution > 0" class="goal-meta">
            <span v-if="goal.months_remaining > 0" class="goal-meta-item">
              <svg class="w-3.5 h-3.5 mr-1 text-horizon-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              {{ goal.months_remaining }} {{ goal.months_remaining === 1 ? 'month' : 'months' }} remaining
            </span>
            <span v-if="goal.months_remaining > 0 && goal.monthly_contribution > 0" class="goal-meta-divider"></span>
            <span v-if="goal.monthly_contribution > 0" class="goal-meta-item">
              Current contribution: {{ formatCurrency(goal.monthly_contribution) }}/month
            </span>
          </div>

          <!-- Action block for incomplete goals -->
          <div v-if="goal.progress_percentage < 100" class="goal-action-block">
            <p class="text-xs font-semibold text-horizon-500 mb-2">Action to Complete Goal</p>
            <p class="text-xs text-horizon-500 leading-relaxed">
              {{ formatCurrency(goal.target_amount) }} &minus; {{ formatCurrency(goal.current_amount) }} = <span class="font-semibold text-horizon-500">{{ formatCurrency(Math.max(0, goal.target_amount - goal.current_amount)) }}</span> lump sum needed
            </p>
            <p class="text-xs text-neutral-500 mt-1.5">
              Before tax year end &mdash; 5 April {{ taxYearEndYear }}
            </p>
            <p v-if="goal.funding_source && goal.funding_source.name" class="text-xs text-neutral-500 mt-1.5">
              Recommended source: <span class="font-medium text-horizon-500">{{ goal.funding_source.name }}</span>
            </p>
            <p v-else class="text-xs text-violet-600 mt-1.5">
              Link an account to identify a funding source
            </p>
            <p v-if="goal.funding_source && goal.funding_source.warning" class="text-xs text-raspberry-600 mt-1.5 leading-relaxed">
              {{ goal.funding_source.warning }}
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Unlinked Goals Prompt -->
    <div v-if="unlinkedGoals.length > 0" class="mt-4">
      <div class="unlinked-goals-prompt">
        <div class="flex items-start gap-3">
          <div class="unlinked-goals-icon">
            <svg class="w-4 h-4 text-violet-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
            </svg>
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-horizon-500">
              {{ unlinkedGoals.length }} {{ unlinkedGoals.length === 1 ? 'goal needs' : 'goals need' }} a linked account
            </p>
            <p class="text-sm text-neutral-500 mt-1 leading-relaxed">
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

    taxYearEndYear() {
      const now = new Date();
      const month = now.getMonth() + 1;
      const day = now.getDate();
      // If before 6 April, tax year ends 5 April this year; otherwise next year
      return (month < 4 || (month === 4 && day < 6)) ? now.getFullYear() : now.getFullYear() + 1;
    },
  },

  methods: {
    formatGoalDate(date) {
      if (!date) return '—';
      try {
        const dateObj = new Date(date + 'T00:00:00');
        if (isNaN(dateObj.getTime())) return '—';
        return dateObj.toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });
      } catch { return '—'; }
    },

    requiredMonthlyColor(goal) {
      if (!goal.required_monthly_contribution || goal.required_monthly_contribution <= 0) return 'text-horizon-500';
      return (goal.monthly_contribution || 0) >= goal.required_monthly_contribution ? 'text-spring-700' : 'text-raspberry-700';
    },

    goalStatus(goal) {
      if (goal.progress_percentage >= 100) {
        return { badge: 'badge-complete', dot: 'dot-complete', label: 'Complete' };
      }
      if (goal.is_on_track) {
        return { badge: 'badge-on-track', dot: 'dot-on-track', label: 'On track' };
      }
      return { badge: 'badge-behind', dot: 'dot-behind', label: 'Behind' };
    },
  },
};
</script>

<style scoped>
.goal-card {
  @apply bg-white border border-light-gray rounded-lg p-5 shadow-sm animate-fade-in-slide;
  transition: border-color 0.2s cubic-bezier(0.4, 0, 0.2, 1),
              box-shadow 0.2s cubic-bezier(0.4, 0, 0.2, 1),
              transform 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.goal-card:hover {
  @apply border-horizon-300 shadow-md;
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
  @apply bg-spring-100 text-spring-800;
}
.dot-complete {
  @apply bg-spring-500;
}

.badge-on-track {
  @apply bg-violet-100 text-violet-800;
}
.dot-on-track {
  @apply bg-violet-500;
}

.badge-behind {
  @apply bg-raspberry-100 text-raspberry-800;
}
.dot-behind {
  @apply bg-raspberry-500;
}

/* -- Meta info row -- */
.goal-meta {
  @apply flex items-center mt-3 pt-3 border-t border-savannah-100 text-xs text-neutral-500;
}

.goal-meta-item {
  @apply inline-flex items-center;
}

.goal-meta-divider {
  @apply w-1 h-1 rounded-full bg-horizon-300 mx-3 flex-shrink-0;
}

/* -- Goal action block -- */
.goal-action-block {
  @apply mt-3 pt-3 bg-violet-50 rounded-lg p-3 -mx-1 border border-violet-200;
}

/* -- Unlinked goals prompt -- */
.unlinked-goals-prompt {
  @apply bg-eggshell-500 border border-light-gray rounded-lg p-5;
  transition: border-color 0.2s ease;
}

.unlinked-goals-prompt:hover {
  @apply border-horizon-300;
}

.unlinked-goals-icon {
  @apply w-8 h-8 rounded-full bg-violet-50 flex items-center justify-center flex-shrink-0 mt-0.5;
}

.unlinked-goals-link {
  @apply inline-flex items-center text-sm font-medium mt-2.5;
  @apply text-raspberry-500;
  transition: color 0.15s ease;
}

.unlinked-goals-link:hover {
  @apply text-raspberry-600;
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
