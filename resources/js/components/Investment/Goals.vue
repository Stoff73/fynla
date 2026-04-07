<template>
  <div class="goals">
    <!-- Loading State -->
    <div v-if="loading" class="flex justify-center items-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-raspberry-500"></div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-eggshell-500 rounded-lg p-4 mb-6">
      <div class="flex items-center">
        <svg class="h-5 w-5 text-raspberry-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
        </svg>
        <span class="text-sm font-medium text-raspberry-800">{{ error }}</span>
      </div>
    </div>

    <!-- Empty State - No Goals -->
    <div v-else-if="!hasGoals" class="flex flex-col items-center justify-center py-16 px-4">
      <div class="bg-white border-2 border-light-gray rounded-lg p-8 max-w-md w-full text-center shadow-sm">
        <svg class="mx-auto h-16 w-16 text-horizon-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
        </svg>
        <h2 class="text-2xl font-bold text-horizon-500 mb-2">No Investment Goals Yet</h2>
        <p class="text-neutral-500 mb-6">
          Set specific financial goals to track your progress and plan for the future
        </p>
        <button
          @click="openGoalModal"
          class="px-6 py-3 bg-raspberry-500 text-white rounded-button hover:bg-raspberry-600 transition-colors font-medium"
        >
          Create Your First Goal
        </button>
      </div>
    </div>

    <!-- Main Content - Goals Exist -->
    <div v-else class="space-y-6">
      <!-- Header with Add Button -->
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-2xl font-bold text-horizon-500">Investment Goals</h2>
          <p class="text-sm text-neutral-500 mt-1">Track progress towards your financial objectives</p>
        </div>
        <button
          @click="openGoalModal"
          class="px-4 py-2 bg-raspberry-500 text-white rounded-button hover:bg-raspberry-600 transition-colors font-medium text-sm"
        >
          + Add Goal
        </button>
      </div>

      <!-- Goals Summary Cards -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Total Goals -->
        <div class="bg-white rounded-lg shadow-md p-6">
          <p class="text-sm text-neutral-500 mb-2">Total Goals</p>
          <p class="text-3xl font-bold text-horizon-500">
            {{ goals.length }}
          </p>
          <p class="text-xs text-neutral-500 mt-1">Active objectives</p>
        </div>

        <!-- Goals On Track -->
        <div class="bg-white rounded-lg shadow-md p-6">
          <p class="text-sm text-neutral-500 mb-2">On Track</p>
          <p class="text-3xl font-bold text-spring-600">
            {{ goalsOnTrackCount }}
          </p>
          <p class="text-xs text-neutral-500 mt-1">Meeting targets</p>
        </div>

        <!-- Total Target Value -->
        <div class="bg-white rounded-lg shadow-md p-6">
          <p class="text-sm text-neutral-500 mb-2">Total Target</p>
          <p class="text-3xl font-bold text-horizon-500">
            £{{ formatNumber(totalTargetAmount) }}
          </p>
          <p class="text-xs text-neutral-500 mt-1">Combined goal value</p>
        </div>
      </div>

      <!-- Goals List -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div
          v-for="goal in goals"
          :key="goal.id"
          class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow cursor-pointer"
          @click="viewGoalDetails(goal)"
        >
          <!-- Goal Header -->
          <div class="flex items-start justify-between mb-4">
            <div>
              <h3 class="text-lg font-semibold text-horizon-500">{{ goal.goal_name }}</h3>
              <p class="text-sm text-neutral-500 capitalize">{{ formatGoalType(goal.goal_type) }}</p>
            </div>
            <span
              :class="[
                'px-3 py-1 text-xs font-semibold rounded-full',
                getPriorityClass(goal.priority)
              ]"
            >
              {{ goal.priority || 'Medium' }}
            </span>
          </div>

          <!-- Progress Bar -->
          <div class="mb-4">
            <div class="flex justify-between text-sm mb-2">
              <span class="text-neutral-500">Progress</span>
              <span class="font-semibold text-horizon-500">{{ getGoalProgress(goal) }}%</span>
            </div>
            <div class="w-full bg-savannah-200 rounded-full h-2.5">
              <div
                :class="[
                  'h-2.5 rounded-full transition-all',
                  getProgressBarColour(getGoalProgress(goal))
                ]"
                :style="{ width: `${Math.min(100, getGoalProgress(goal))}%` }"
              ></div>
            </div>
          </div>

          <!-- Goal Details -->
          <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
              <p class="text-neutral-500">Current Value</p>
              <p class="font-semibold text-horizon-500">£{{ formatNumber(goal.current_value || 0) }}</p>
            </div>
            <div>
              <p class="text-neutral-500">Target Amount</p>
              <p class="font-semibold text-horizon-500">£{{ formatNumber(goal.target_amount) }}</p>
            </div>
            <div>
              <p class="text-neutral-500">Target Date</p>
              <p class="font-semibold text-horizon-500">{{ formatDate(goal.target_date) }}</p>
            </div>
            <div>
              <p class="text-neutral-500">Time Remaining</p>
              <p class="font-semibold text-horizon-500">{{ getTimeRemaining(goal.target_date) }}</p>
            </div>
          </div>

          <!-- Goal Actions -->
          <div class="mt-4 pt-4 border-t border-light-gray flex justify-between items-center">
            <button
              @click.stop="editGoal(goal)"
              class="text-violet-600 hover:text-violet-700 text-sm font-medium"
            >
              Edit
            </button>
            <button
              @click.stop="viewProjections(goal)"
              class="text-spring-600 hover:text-spring-700 text-sm font-medium"
            >
              View Projections
            </button>
            <button
              @click.stop="deleteGoal(goal)"
              class="text-raspberry-600 hover:text-raspberry-700 text-sm font-medium"
            >
              Delete
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Goal Form Modal -->
    <GoalForm
      v-if="showGoalModal"
      :goal="selectedGoal"
      @close="closeGoalModal"
      @save="handleGoalSave"
    />
  </div>
</template>

<script>
import { mapState, mapGetters, mapActions } from 'vuex';
import GoalForm from './GoalForm.vue';
import { currencyMixin } from '@/mixins/currencyMixin';

import logger from '@/utils/logger';
export default {
  name: 'InvestmentGoals',
  mixins: [currencyMixin],

  emits: ['view-projection'],

  components: {
    GoalForm,
  },

  data() {
    return {
      showGoalModal: false,
      selectedGoal: null,
    };
  },

  computed: {
    ...mapState('investment', ['goals', 'loading', 'error']),
    ...mapGetters('investment', ['goalsOnTrack']),

    hasGoals() {
      return this.goals && this.goals.length > 0;
    },

    goalsOnTrackCount() {
      return this.goalsOnTrack ? this.goalsOnTrack.length : 0;
    },

    totalTargetAmount() {
      if (!this.goals) return 0;
      return this.goals.reduce((sum, goal) => sum + parseFloat(goal.target_amount || 0), 0);
    },
  },

  methods: {
    ...mapActions('investment', ['createGoal', 'updateGoal', 'deleteGoal']),

    formatGoalType(type) {
      return type ? type.replace(/_/g, ' ') : 'General';
    },

    formatDate(dateString) {
      if (!dateString) return 'Not set';
      const date = new Date(dateString);
      return date.toLocaleDateString('en-GB', { year: 'numeric', month: 'short', day: 'numeric' });
    },

    getTimeRemaining(targetDate) {
      if (!targetDate) return 'Not set';
      const target = new Date(targetDate);
      const now = new Date();
      const diffTime = target - now;
      const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

      if (diffDays < 0) return 'Overdue';
      if (diffDays < 30) return `${diffDays} days`;
      if (diffDays < 365) return `${Math.floor(diffDays / 30)} months`;
      return `${Math.floor(diffDays / 365)} years`;
    },

    getGoalProgress(goal) {
      if (!goal.target_amount || goal.target_amount === 0) return 0;
      const currentValue = parseFloat(goal.current_value || 0);
      const targetAmount = parseFloat(goal.target_amount);
      return Math.round((currentValue / targetAmount) * 100);
    },

    getProgressBarColour(progress) {
      if (progress >= 80) return 'bg-spring-500';
      if (progress >= 50) return 'bg-violet-500';
      return 'bg-violet-500';
    },

    getPriorityClass(priority) {
      const priorityLower = (priority || 'medium').toLowerCase();
      if (priorityLower === 'high') return 'bg-raspberry-500 text-white';
      if (priorityLower === 'low') return 'bg-savannah-100 text-horizon-500';
      return 'bg-violet-500 text-white';
    },

    openGoalModal() {
      this.selectedGoal = null;
      this.showGoalModal = true;
    },

    closeGoalModal() {
      this.showGoalModal = false;
      this.selectedGoal = null;
    },

    async handleGoalSave(goalData) {
      try {
        if (this.selectedGoal) {
          await this.updateGoal({ id: this.selectedGoal.id, data: goalData });
        } else {
          await this.createGoal(goalData);
        }
        this.closeGoalModal();
      } catch (error) {
        logger.error('Failed to save goal:', error);
      }
    },

    editGoal(goal) {
      this.selectedGoal = goal;
      this.showGoalModal = true;
    },

    viewGoalDetails(goal) {
      // Navigate to detailed goal view or expand in place
      this.selectedGoal = goal;
    },

    viewProjections(goal) {
      // This would navigate to or display the GoalProjection component
      this.$emit('view-projection', goal);
    },

    async deleteGoal(goal) {
      if (confirm(`Are you sure you want to delete the goal "${goal.goal_name}"?`)) {
        try {
          await this.$store.dispatch('investment/deleteGoal', goal.id);
        } catch (error) {
          logger.error('Failed to delete goal:', error);
        }
      }
    },
  },
};
</script>

<style scoped>
/* Add any specific styles here */
</style>
