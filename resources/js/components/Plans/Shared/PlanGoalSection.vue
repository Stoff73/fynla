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
          v-for="goal in linkedGoals"
          :key="goal.id"
          class="bg-white border border-gray-200 rounded-lg p-4"
        >
          <div class="flex items-start justify-between mb-2">
            <div>
              <h4 class="text-sm font-semibold text-gray-900">{{ goal.name }}</h4>
              <span class="text-xs text-gray-500">{{ goal.display_type }}</span>
            </div>
            <span
              class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
              :class="statusBadgeClass(goal)"
            >
              {{ statusLabel(goal) }}
            </span>
          </div>

          <GoalProgressBar
            :percentage="goal.progress_percentage"
            :current-amount="goal.current_amount"
            :target-amount="goal.target_amount"
            :is-on-track="goal.is_on_track"
            size="sm"
            :show-amounts="true"
          />

          <div class="flex items-center gap-4 mt-2 text-xs text-gray-500">
            <span v-if="goal.months_remaining > 0">
              {{ goal.months_remaining }} {{ goal.months_remaining === 1 ? 'month' : 'months' }} remaining
            </span>
            <span v-if="goal.monthly_contribution > 0">
              {{ formatCurrency(goal.monthly_contribution) }}/month
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Unlinked Goals Prompt -->
    <div v-if="unlinkedGoals.length > 0" class="mt-4">
      <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div class="flex items-start gap-3">
          <svg class="w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <div>
            <p class="text-sm font-medium text-blue-900">
              {{ unlinkedGoals.length }} {{ unlinkedGoals.length === 1 ? 'goal needs' : 'goals need' }} a linked account
            </p>
            <p class="text-sm text-blue-700 mt-1">
              {{ unlinkedGoalNames }} — link {{ unlinkedGoals.length === 1 ? 'this goal' : 'these goals' }} to an account to track progress automatically.
            </p>
            <router-link
              to="/goals"
              class="inline-flex items-center text-sm font-medium text-blue-700 hover:text-blue-800 mt-2"
            >
              Manage goals
              <svg class="w-4 h-4 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
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
        return 'bg-green-100 text-green-800';
      }
      if (goal.is_on_track) {
        return 'bg-blue-100 text-blue-800';
      }
      return 'bg-red-100 text-red-800';
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
