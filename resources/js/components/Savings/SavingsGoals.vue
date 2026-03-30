<template>
  <div class="savings-goals">
    <!-- Goals Module Migration Banner -->
    <div class="mb-4 p-4 bg-violet-50 border border-violet-200 rounded-lg flex items-start gap-3">
      <svg class="h-5 w-5 text-violet-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <div>
        <p class="text-sm font-medium text-violet-900">Goals are now managed in the Goals module</p>
        <p class="text-xs text-violet-700 mt-1">
          For a more comprehensive view of your financial goals, including investment and retirement targets, visit the
          <router-link to="/goals" class="text-violet-600 underline hover:text-violet-800 font-medium">Goals &amp; Life Events</router-link>
          section. These legacy savings goals remain available for reference.
        </p>
      </div>
    </div>

    <!-- Header with Add Button -->
    <div class="mb-6 flex justify-between items-center">
      <div>
        <h3 class="text-lg font-semibold text-horizon-500">Your Savings Goals</h3>
        <p class="text-sm text-neutral-500 mt-1">
          {{ goalsOnTrack.length }} of {{ goals.length }} goals on track
        </p>
      </div>
      <button
        @click="showAddGoalModal = true"
        class="px-4 py-2 bg-raspberry-500 text-white font-medium rounded-button hover:bg-raspberry-600 transition-colors flex items-center gap-2"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          class="h-5 w-5"
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M12 4v16m8-8H4"
          />
        </svg>
        Add Goal
      </button>
    </div>

    <!-- Goals List -->
    <div v-if="goals.length > 0" class="space-y-4">
      <div
        v-for="goal in goals"
        :key="goal.id"
        class="bg-white rounded-lg border border-light-gray p-6"
      >
        <div class="flex justify-between items-start mb-4">
          <div>
            <h4 class="text-lg font-semibold text-horizon-500">{{ goal.goal_name }}</h4>
            <p class="text-sm text-neutral-500">Target: {{ formatDate(goal.target_date) }}</p>
          </div>
          <span
            class="px-3 py-1 text-xs font-semibold rounded-full"
            :class="getStatusBadge(goal)"
          >
            {{ getStatusLabel(goal) }}
          </span>
        </div>

        <!-- Progress Bar -->
        <div class="mb-4">
          <div class="flex justify-between text-sm mb-1">
            <span class="text-neutral-500">Progress</span>
            <span class="font-semibold">{{ getProgressPercent(goal) }}%</span>
          </div>
          <div class="w-full bg-savannah-200 rounded-full h-3">
            <div
              class="h-3 rounded-full transition-all"
              :class="getProgressBarColour(goal)"
              :style="{ width: getProgressPercent(goal) + '%' }"
            ></div>
          </div>
        </div>

        <!-- Amount Info -->
        <div class="grid grid-cols-2 gap-4 mb-4">
          <div>
            <p class="text-sm text-neutral-500">Saved</p>
            <p class="text-lg font-bold text-horizon-500">
              {{ formatCurrency(goal.current_saved) }}
            </p>
          </div>
          <div>
            <p class="text-sm text-neutral-500">Target</p>
            <p class="text-lg font-bold text-horizon-500">
              {{ formatCurrency(goal.target_amount) }}
            </p>
          </div>
        </div>

        <!-- Required Monthly Savings -->
        <div class="p-3 bg-eggshell-500 rounded-lg mb-4">
          <p class="text-sm text-neutral-500">
            <span class="font-medium">Required monthly savings:</span>
            {{ formatCurrency(getRequiredMonthlySavings(goal)) }}
          </p>
        </div>

        <!-- Actions -->
        <div class="flex gap-3">
          <button
            @click="handleUpdateProgress(goal.id)"
            class="flex-1 px-4 py-2 bg-raspberry-500 text-white text-sm font-medium rounded-button hover:bg-raspberry-600"
          >
            Update Progress
          </button>
          <button
            @click="handleEditGoal(goal)"
            class="px-4 py-2 bg-savannah-100 text-neutral-500 text-sm font-medium rounded-lg hover:bg-savannah-200"
          >
            Edit
          </button>
          <button
            @click="handleDeleteGoal(goal.id)"
            class="px-4 py-2 bg-raspberry-50 text-raspberry-600 text-sm font-medium rounded-lg hover:bg-raspberry-100"
          >
            Delete
          </button>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="text-center py-12 bg-white rounded-lg border border-light-gray">
      <svg
        class="mx-auto h-12 w-12 text-horizon-400"
        fill="none"
        viewBox="0 0 24 24"
        stroke="currentColor"
      >
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          stroke-width="2"
          d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
        />
      </svg>
      <h3 class="mt-2 text-sm font-medium text-horizon-500">No savings goals yet</h3>
      <p class="mt-1 text-sm text-neutral-500">
        Get started by creating your first savings goal.
      </p>
      <button
        @click="showAddGoalModal = true"
        class="mt-4 px-4 py-2 bg-raspberry-500 text-white font-medium rounded-button hover:bg-raspberry-600"
      >
        Create Goal
      </button>
    </div>

    <!-- Save Goal Modal -->
    <SaveGoalModal
      v-if="showAddGoalModal"
      :goal="selectedGoal"
      :is-editing="isEditingGoal"
      @save="handleSaveGoal"
      @close="handleCloseModal"
    />
  </div>
</template>

<script>
import { mapState, mapGetters, mapActions } from 'vuex';
import SaveGoalModal from './SaveGoalModal.vue';
import { currencyMixin } from '@/mixins/currencyMixin';

import logger from '@/utils/logger';
export default {
  name: 'SavingsGoals',
  mixins: [currencyMixin],

  components: {
    SaveGoalModal,
  },

  data() {
    return {
      showAddGoalModal: false,
      selectedGoal: null,
      isEditingGoal: false,
    };
  },

  computed: {
    ...mapState('savings', ['goals']),
    ...mapGetters('savings', ['goalsOnTrack']),
  },

  methods: {
    ...mapActions('savings', ['createGoal', 'updateGoal', 'deleteGoal', 'updateGoalProgress', 'fetchSavingsData']),

    getProgressPercent(goal) {
      return Math.min(Math.round((goal.current_saved / goal.target_amount) * 100), 100);
    },

    getStatusLabel(goal) {
      const progress = this.getProgressPercent(goal);
      const now = new Date();
      const targetDate = new Date(goal.target_date);
      const monthsRemaining = (targetDate - now) / (1000 * 60 * 60 * 24 * 30);

      if (progress >= 100) return 'Completed';
      if (monthsRemaining < 0) return 'Overdue';

      const required = this.getRequiredMonthlySavings(goal);
      if (required <= 0) return 'On Track';
      if (required > 1000) return 'Off Track';
      return 'On Track';
    },

    getStatusBadge(goal) {
      const status = this.getStatusLabel(goal);
      if (status === 'Completed') return 'bg-spring-500 text-white';
      if (status === 'On Track') return 'bg-violet-500 text-white';
      if (status === 'Off Track') return 'bg-raspberry-500 text-white';
      if (status === 'Overdue') return 'bg-raspberry-500 text-white';
      return 'bg-eggshell-5000 text-white';
    },

    getProgressBarColour(goal) {
      const status = this.getStatusLabel(goal);
      if (status === 'Completed') return 'bg-spring-600';
      if (status === 'On Track') return 'bg-raspberry-500';
      return 'bg-raspberry-600';
    },

    getRequiredMonthlySavings(goal) {
      const remaining = goal.target_amount - goal.current_saved;
      const now = new Date();
      const targetDate = new Date(goal.target_date);
      const monthsRemaining = Math.max((targetDate - now) / (1000 * 60 * 60 * 24 * 30), 1);

      return Math.max(0, remaining / monthsRemaining);
    },

    formatDate(dateString) {
      return new Date(dateString).toLocaleDateString('en-GB', {
        year: 'numeric',
        month: 'long',
      });
    },

    // Modal handlers
    handleCloseModal() {
      this.showAddGoalModal = false;
      this.selectedGoal = null;
      this.isEditingGoal = false;
    },

    handleEditGoal(goal) {
      this.selectedGoal = goal;
      this.isEditingGoal = true;
      this.showAddGoalModal = true;
    },

    async handleSaveGoal(goalData) {
      try {
        if (this.isEditingGoal && this.selectedGoal) {
          // Update existing goal
          await this.updateGoal({
            id: this.selectedGoal.id,
            goalData,
          });
        } else {
          // Create new goal
          await this.createGoal(goalData);
        }

        // Refresh data
        await this.fetchSavingsData();

        // Close modal
        this.handleCloseModal();
      } catch (error) {
        logger.error('Failed to save goal:', error);
        alert('Failed to save goal. Please try again.');
      }
    },

    async handleDeleteGoal(goalId) {
      if (!confirm('Are you sure you want to delete this goal?')) {
        return;
      }

      try {
        await this.deleteGoal(goalId);
        await this.fetchSavingsData();
      } catch (error) {
        logger.error('Failed to delete goal:', error);
        alert('Failed to delete goal. Please try again.');
      }
    },

    async handleUpdateProgress(goalId) {
      const amount = prompt('Enter the current amount saved for this goal:');
      if (amount === null) return;

      const numAmount = parseFloat(amount);
      if (isNaN(numAmount) || numAmount < 0) {
        alert('Please enter a valid amount.');
        return;
      }

      try {
        await this.updateGoalProgress({
          id: goalId,
          amount: numAmount,
        });
        await this.fetchSavingsData();
      } catch (error) {
        logger.error('Failed to update goal progress:', error);
        alert('Failed to update goal progress. Please try again.');
      }
    },
  },
};
</script>
