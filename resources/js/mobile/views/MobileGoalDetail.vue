<template>
  <div class="px-4 pt-4 pb-24">
    <!-- Loading -->
    <div v-if="loading" class="space-y-4">
      <div class="bg-savannah-100 animate-pulse rounded-xl h-48"></div>
      <div class="bg-savannah-100 animate-pulse rounded-xl h-24"></div>
    </div>

    <template v-else-if="goal">
      <!-- Hero card -->
      <div class="bg-white rounded-xl border border-light-gray p-6 text-center mb-4">
        <ProgressRing
          :percentage="progressPercentage"
          :size="120"
          :stroke-width="6"
          :status="status"
          class="mx-auto mb-4"
        />

        <h2 class="text-lg font-bold text-horizon-500">{{ goal.name }}</h2>

        <div class="flex justify-center gap-6 mt-4">
          <div>
            <p class="text-xs text-neutral-500">Current</p>
            <p class="text-base font-bold text-horizon-500">{{ formatCurrency(goal.current_amount) }}</p>
          </div>
          <div>
            <p class="text-xs text-neutral-500">Target</p>
            <p class="text-base font-bold text-horizon-500">{{ formatCurrency(goal.target_amount) }}</p>
          </div>
        </div>

        <!-- Status + target date -->
        <div class="flex items-center justify-center gap-2 mt-3">
          <span
            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium"
            :class="statusBadgeClass"
          >
            <span class="w-1.5 h-1.5 rounded-full" :class="statusDotClass"></span>
            {{ statusLabel }}
          </span>
          <span v-if="goal.target_date" class="text-xs text-neutral-500">
            Target: {{ formattedTargetDate }}
          </span>
        </div>
      </div>

      <!-- Remaining amount -->
      <div class="bg-white rounded-xl border border-light-gray p-4 mb-4">
        <div class="flex justify-between items-center">
          <span class="text-sm text-neutral-500">Remaining</span>
          <span class="text-sm font-bold text-horizon-500">
            {{ formatCurrency(remainingAmount) }}
          </span>
        </div>
        <div class="w-full bg-neutral-200 rounded-full h-1.5 mt-2">
          <div
            class="h-1.5 rounded-full transition-all duration-500"
            :class="progressBarClass"
            :style="{ width: `${progressPercentage}%` }"
          ></div>
        </div>
      </div>

      <!-- Recent contributions -->
      <div v-if="goal.recent_contributions && goal.recent_contributions.length" class="mb-4">
        <h3 class="text-sm font-bold text-horizon-500 mb-2">Recent contributions</h3>
        <div class="bg-white rounded-xl border border-light-gray divide-y divide-light-gray">
          <div
            v-for="contribution in goal.recent_contributions"
            :key="contribution.id"
            class="px-4 py-3 flex justify-between items-center"
          >
            <div>
              <p class="text-sm text-horizon-500">{{ formatCurrency(contribution.amount) }}</p>
              <p v-if="contribution.note" class="text-xs text-neutral-500 mt-0.5">{{ contribution.note }}</p>
            </div>
            <span class="text-xs text-neutral-400">
              {{ formatContributionDate(contribution.date || contribution.created_at) }}
            </span>
          </div>
        </div>
      </div>
    </template>

    <!-- Not found -->
    <div v-else class="text-center py-16">
      <p class="text-neutral-500">Goal not found</p>
    </div>

    <!-- Contribution FAB -->
    <ContributionFAB
      v-if="goal"
      :goal-id="goal.id"
      @saved="handleContributionSaved"
    />

    <!-- Milestone overlay -->
    <MilestoneOverlay
      v-if="showMilestone"
      :milestone="milestoneData"
      @dismiss="showMilestone = false"
    />
  </div>
</template>

<script>
import { mapActions } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';
import ProgressRing from '@/mobile/charts/ProgressRing.vue';
import ContributionFAB from '@/mobile/goals/ContributionFAB.vue';
import MilestoneOverlay from '@/mobile/goals/MilestoneOverlay.vue';

export default {
  name: 'MobileGoalDetail',

  components: {
    ProgressRing,
    ContributionFAB,
    MilestoneOverlay,
  },

  mixins: [currencyMixin],

  data() {
    return {
      loading: false,
      showMilestone: false,
      milestoneData: null,
    };
  },

  computed: {
    goal() {
      return this.$store.state.goals.selectedGoal;
    },

    progressPercentage() {
      const target = parseFloat(this.goal?.target_amount) || 0;
      if (target === 0) return 0;
      const current = parseFloat(this.goal?.current_amount) || 0;
      return Math.min(Math.round((current / target) * 100), 100);
    },

    remainingAmount() {
      const target = parseFloat(this.goal?.target_amount) || 0;
      const current = parseFloat(this.goal?.current_amount) || 0;
      return Math.max(target - current, 0);
    },

    status() {
      if (!this.goal) return 'on-track';
      if (this.goal.status === 'completed') return 'on-track';
      if (this.goal.is_on_track) return 'on-track';
      if (this.goal.is_at_risk) return 'at-risk';
      return 'behind';
    },

    statusLabel() {
      if (this.goal?.status === 'completed') return 'Completed';
      if (this.goal?.is_on_track) return 'On track';
      if (this.goal?.is_at_risk) return 'At risk';
      return 'Behind';
    },

    statusBadgeClass() {
      const map = {
        'on-track': 'bg-spring-50 text-spring-500',
        'behind': 'bg-violet-50 text-violet-500',
        'at-risk': 'bg-raspberry-50 text-raspberry-500',
      };
      return map[this.status];
    },

    statusDotClass() {
      const map = {
        'on-track': 'bg-spring-500',
        'behind': 'bg-violet-500',
        'at-risk': 'bg-raspberry-500',
      };
      return map[this.status];
    },

    progressBarClass() {
      const map = {
        'on-track': 'bg-spring-500',
        'behind': 'bg-violet-500',
        'at-risk': 'bg-raspberry-500',
      };
      return map[this.status];
    },

    formattedTargetDate() {
      if (!this.goal?.target_date) return '';
      const date = new Date(this.goal.target_date);
      return date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
    },
  },

  async mounted() {
    const goalId = this.$route.params.id;
    if (goalId) {
      this.loading = true;
      try {
        await this.fetchGoal(goalId);
      } finally {
        this.loading = false;
      }
    }
  },

  methods: {
    ...mapActions('goals', ['fetchGoal', 'recordContribution']),

    async handleContributionSaved(contribution) {
      // Check for milestones
      const prevPercentage = this.progressPercentage;
      await this.fetchGoal(this.goal.id);
      const newPercentage = this.progressPercentage;

      // Check if we crossed a milestone threshold
      const milestones = [25, 50, 75, 100];
      for (const threshold of milestones) {
        if (prevPercentage < threshold && newPercentage >= threshold) {
          this.milestoneData = {
            percentage: threshold,
            goalName: this.goal.name,
            amount: contribution.amount,
          };
          this.showMilestone = true;
          break;
        }
      }
    },

    formatContributionDate(dateStr) {
      if (!dateStr) return '';
      const date = new Date(dateStr);
      return date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
    },
  },
};
</script>
