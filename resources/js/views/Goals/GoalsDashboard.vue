<template>
  <AppLayout>
    <div class="goals-dashboard module-gradient py-2 sm:py-6">
      <ModuleStatusBar />
      <div class="">
        <!-- Info Banner -->
        <div class="bg-violet-50 border border-violet-200 rounded-lg p-4 mb-6">
          <div class="flex items-start">
            <svg class="w-5 h-5 text-violet-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
            </svg>
            <p class="ml-3 text-sm text-violet-800">
              This feature is still being developed. The aim is to have this integrated with the whole site, allowing the AI to use your goals and life events to adjust strategies and recommendations accordingly. Your feedback on how this looks and feels is appreciated.
            </p>
          </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="flex justify-center items-center py-12">
          <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-raspberry-600"></div>
        </div>

        <!-- Error State -->
        <div
          v-else-if="error"
          class="bg-raspberry-50 border-l-4 border-raspberry-500 p-4 mb-6"
        >
          <div class="flex">
            <div class="flex-shrink-0">
              <svg class="h-5 w-5 text-raspberry-400" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
              </svg>
            </div>
            <div class="ml-3">
              <p class="text-sm text-raspberry-700">{{ error }}</p>
            </div>
          </div>
        </div>

        <!-- Goal Detail View (inline, replaces list) -->
        <GoalDetailInline
          v-if="selectedGoal && !loading && !error"
          :goal-id="selectedGoal.id"
          @back="closeGoalDetail"
          @edit="openEditModal"
          @delete="confirmDeleteGoal"
          @add-contribution="openContributionModal"
          @updated="handleGoalUpdated"
        />

        <!-- Main Content -->
        <div v-else-if="!loading && !error" class="bg-white rounded-lg border border-light-gray">
          <!-- Tab Navigation -->
          <div class="border-b border-light-gray">
            <nav class="-mb-px flex overflow-x-auto scrollbar-hide" aria-label="Tabs">
              <button
                v-for="tab in tabs"
                :key="tab.id"
                @click="activeTab = tab.id"
                :class="[
                  activeTab === tab.id
                    ? 'border-raspberry-500 text-raspberry-600'
                    : 'border-transparent text-neutral-500 hover:text-neutral-500 hover:border-horizon-300',
                  'whitespace-nowrap py-3 sm:py-4 px-3 sm:px-6 border-b-2 font-medium text-xs sm:text-sm transition-colors duration-200 flex-shrink-0',
                ]"
              >
                {{ tab.label }}
              </button>
            </nav>
          </div>

          <!-- Tab Content -->
          <div class="p-6">
            <!-- Overview Tab -->
            <div v-if="activeTab === 'overview'">
              <GoalsOverview
                :summary="summary"
                :top-goals="topGoals"
                :best-streak="bestStreak"
                @create-goal="openCreateModal"
                @create-event="switchToEventsTab"
                @view-goal="viewGoal"
              />
            </div>

            <!-- Life Events Tab -->
            <div v-else-if="activeTab === 'events'">
              <EventsTab />
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Create/Edit Goal Modal -->
    <GoalFormModal
      :is-open="showGoalModal"
      :goal="editingGoal"
      @close="closeGoalModal"
      @save="handleSaveGoal"
    />

    <!-- Contribution Modal -->
    <ContributionModal
      :is-open="showContributionModal"
      :goal="contributionGoal"
      @close="closeContributionModal"
      @save="handleSaveContribution"
    />

    <!-- Delete Confirmation Modal -->
    <div v-if="showDeleteModal" class="fixed inset-0 z-50 overflow-y-auto">
      <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-savannah-1000 bg-opacity-75 transition-opacity" @click="closeDeleteModal"></div>
        <div class="relative z-10 inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
          <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
            <div class="sm:flex sm:items-start">
              <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-raspberry-100 sm:mx-0 sm:h-10 sm:w-10">
                <svg class="h-6 w-6 text-raspberry-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
              </div>
              <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                <h3 class="text-lg leading-6 font-medium text-horizon-500">Delete Goal</h3>
                <div class="mt-2">
                  <p class="text-sm text-neutral-500">
                    Are you sure you want to delete "{{ deletingGoal?.goal_name }}"? This action cannot be undone.
                  </p>
                </div>
              </div>
            </div>
          </div>
          <div class="bg-savannah-100 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
            <button
              type="button"
              @click="handleDeleteGoal"
              :disabled="deleteLoading"
              class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-raspberry-600 text-base font-medium text-white hover:bg-raspberry-700 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50"
            >
              {{ deleteLoading ? 'Deleting...' : 'Delete' }}
            </button>
            <button
              type="button"
              @click="closeDeleteModal"
              class="mt-3 w-full inline-flex justify-center rounded-md border border-horizon-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-neutral-500 hover:bg-savannah-100 sm:mt-0 sm:w-auto sm:text-sm"
            >
              Cancel
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import { mapState, mapGetters, mapActions } from 'vuex';
import AppLayout from '@/layouts/AppLayout.vue';
import GoalFormModal from '@/components/Goals/GoalFormModal.vue';
import GoalsOverview from '@/components/Goals/GoalsOverview.vue';
import ContributionModal from '@/components/Goals/ContributionModal.vue';
import EventsTab from '@/components/Goals/EventsTab.vue';
import GoalDetailInline from '@/components/Goals/GoalDetailInline.vue';
import ModuleStatusBar from '@/components/Shared/ModuleStatusBar.vue';

import logger from '@/utils/logger';
export default {
  name: 'GoalsDashboard',

  components: {
    AppLayout,
    GoalFormModal,
    GoalsOverview,
    ContributionModal,
    EventsTab,
    GoalDetailInline,
    ModuleStatusBar,
  },

  data() {
    return {
      activeTab: 'overview',
      tabs: [
        { id: 'overview', label: 'Overview' },
        { id: 'events', label: 'Life Events' },
      ],
      selectedGoal: null,
      showGoalModal: false,
      editingGoal: null,
      showContributionModal: false,
      contributionGoal: null,
      showDeleteModal: false,
      deletingGoal: null,
      deleteLoading: false,
    };
  },

  watch: {
    '$route.query.tab'(tab) {
      this.activeTab = tab === 'events' ? 'events' : 'overview';
    },

    actionCounter() {
      if (this.pendingAction === 'addGoal') {
        this.openCreateModal();
        this.$store.dispatch('subNav/consumeCta');
      } else if (this.pendingAction === 'addLifeEvent') {
        this.activeTab = 'events';
        this.$nextTick(() => {
          // EventsTab will handle opening the modal via its own create flow
          // Dispatch event to trigger the EventsTab's create modal
          this.$store.dispatch('subNav/consumeCta');
        });
      }
    },

    '$store.state.aiFormFill.pendingFill'(fill) {
      if (!fill) return;
      if (fill.entityType === 'goal') {
        if (fill.mode === 'edit' && fill.entityId) {
          const goal = this.goals?.find(g => g.id === fill.entityId);
          if (goal) this.openEditModal(goal);
        } else {
          this.openCreateModal();
        }
      } else if (fill.entityType === 'life_event') {
        this.activeTab = 'events';
      }
    },
  },

  computed: {
    ...mapState('goals', ['loading', 'error', 'goals']),
    ...mapGetters('goals', ['dashboardData']),
    ...mapGetters('subNav', ['pendingAction', 'actionCounter']),

    summary() {
      return {
        total_goals: this.dashboardData?.total_goals || 0,
        on_track_count: this.dashboardData?.on_track_count || 0,
        total_target: this.dashboardData?.total_target || 0,
        total_current: this.dashboardData?.total_current || 0,
        overall_progress: this.dashboardData?.overall_progress || 0,
      };
    },

    topGoals() {
      return this.dashboardData?.top_goals || [];
    },

    bestStreak() {
      return this.dashboardData?.best_streak || 0;
    },
  },

  mounted() {
    this.loadGoalsData();

    // Check for tab query parameter (e.g., from sidebar Life Events link)
    if (this.$route.query.tab === 'events') {
      this.activeTab = 'events';
    }

    // Check for action query parameter (e.g., from dashboard empty state CTA)
    if (this.$route.query.action === 'create') {
      this.openCreateModal();
    }
  },

  methods: {
    ...mapActions('goals', [
      'fetchGoals',
      'fetchDashboardOverview',
      'createGoal',
      'updateGoal',
      'deleteGoal',
      'recordContribution',
    ]),

    async loadGoalsData() {
      try {
        await Promise.all([
          this.fetchGoals(),
          this.fetchDashboardOverview(),
        ]);
      } catch (error) {
        logger.error('Failed to load goals data:', error);
      }
    },

    openCreateModal() {
      this.editingGoal = null;
      this.showGoalModal = true;
    },

    openEditModal(goal) {
      this.editingGoal = goal;
      this.showGoalModal = true;
    },

    closeGoalModal() {
      this.showGoalModal = false;
      this.editingGoal = null;
    },

    async handleSaveGoal(formData) {
      try {
        if (this.editingGoal) {
          await this.updateGoal({ goalId: this.editingGoal.id, goalData: formData });
        } else {
          await this.createGoal(formData);
        }
        if (this.$store.state.aiFormFill.pendingFill) {
          this.$store.dispatch('aiFormFill/completeFill');
        }
        this.closeGoalModal();
        // If editing from detail view, close detail and return to list
        if (this.selectedGoal) {
          this.selectedGoal = null;
        }
        await this.loadGoalsData();
      } catch (error) {
        logger.error('Failed to save goal:', error);
      }
    },

    openContributionModal(goal) {
      this.contributionGoal = goal;
      this.showContributionModal = true;
    },

    closeContributionModal() {
      this.showContributionModal = false;
      this.contributionGoal = null;
    },

    async handleSaveContribution(contributionData) {
      try {
        await this.recordContribution({
          goalId: this.contributionGoal.id,
          contributionData: contributionData,
        });
        this.closeContributionModal();
        await this.loadGoalsData();
      } catch (error) {
        logger.error('Failed to record contribution:', error);
      }
    },

    confirmDeleteGoal(goal) {
      this.deletingGoal = goal;
      this.showDeleteModal = true;
    },

    closeDeleteModal() {
      this.showDeleteModal = false;
      this.deletingGoal = null;
    },

    async handleDeleteGoal() {
      if (!this.deletingGoal) return;

      this.deleteLoading = true;
      try {
        await this.deleteGoal(this.deletingGoal.id);
        this.closeDeleteModal();
        // Close detail view if deleting from it
        if (this.selectedGoal && this.selectedGoal.id === this.deletingGoal.id) {
          this.selectedGoal = null;
        }
        await this.loadGoalsData();
      } catch (error) {
        logger.error('Failed to delete goal:', error);
      } finally {
        this.deleteLoading = false;
      }
    },

    switchToEventsTab() {
      this.activeTab = 'events';
    },

    viewGoal(goal) {
      this.selectedGoal = goal;
    },

    closeGoalDetail() {
      this.selectedGoal = null;
    },

    async handleGoalUpdated() {
      await this.loadGoalsData();
    },
  },
};
</script>

<style scoped>
/* Mobile optimization for tab navigation */
@media (max-width: 640px) {
  .goals-dashboard nav[aria-label="Tabs"] button {
    font-size: 0.875rem;
    padding-left: 1rem;
    padding-right: 1rem;
  }
}
</style>
