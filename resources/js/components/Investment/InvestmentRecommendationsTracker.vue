<template>
  <div class="recommendations-tracker bg-white rounded-lg shadow-sm">
    <!-- Header with Statistics -->
    <div class="p-6 border-b border-light-gray">
      <div class="flex items-center justify-between mb-6">
        <h2 class="text-2xl font-bold text-horizon-500">Investment Strategies</h2>
        <button
          @click="refreshRecommendations"
          class="px-4 py-2 text-sm font-medium text-white bg-raspberry-500 hover:bg-raspberry-600 rounded-button transition-colors duration-200"
          :disabled="loading"
        >
          {{ loading ? 'Loading...' : 'Refresh' }}
        </button>
      </div>

      <!-- Statistics Cards -->
      <div v-if="effectiveStats" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        <!-- Total Recommendations -->
        <div class="bg-eggshell-500 rounded-lg p-4">
          <div class="text-sm font-medium text-violet-600 mb-1">Total</div>
          <div class="text-2xl font-bold text-violet-900">{{ effectiveStats.total }}</div>
        </div>

        <!-- Pending -->
        <div class="bg-eggshell-500 rounded-lg p-4">
          <div class="text-sm font-medium text-violet-600 mb-1">Pending</div>
          <div class="text-2xl font-bold text-violet-900">{{ effectiveStats.pending }}</div>
        </div>

        <!-- In Progress -->
        <div class="bg-eggshell-500 rounded-lg p-4">
          <div class="text-sm font-medium text-violet-600 mb-1">In Progress</div>
          <div class="text-2xl font-bold text-violet-900">{{ effectiveStats.in_progress }}</div>
        </div>

        <!-- Completed -->
        <div class="bg-eggshell-500 rounded-lg p-4">
          <div class="text-sm font-medium text-spring-600 mb-1">Completed</div>
          <div class="text-2xl font-bold text-spring-900">{{ effectiveStats.completed }}</div>
        </div>

        <!-- Potential Savings -->
        <div class="bg-eggshell-500 rounded-lg p-4">
          <div class="text-sm font-medium text-violet-600 mb-1">Potential Savings</div>
          <div class="text-2xl font-bold text-violet-900">£{{ formatNumber(effectiveStats.total_potential_saving) }}</div>
        </div>
      </div>
    </div>

    <!-- Recommendations List -->
    <div class="p-6">
      <!-- Loading State -->
      <div v-if="loading" class="text-center py-12">
        <svg class="animate-spin h-12 w-12 mx-auto text-violet-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <p class="mt-4 text-neutral-500">Loading strategies...</p>
      </div>

      <!-- Empty State -->
      <div v-else-if="!recommendations || recommendations.length === 0" class="text-center py-12">
        <svg class="mx-auto h-12 w-12 text-horizon-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
        </svg>
        <h3 class="mt-4 text-lg font-medium text-horizon-500">No strategies found</h3>
        <p class="mt-2 text-neutral-500">Try adjusting your filters or generate new strategies.</p>
      </div>

      <!-- Recommendations Cards -->
      <div v-else class="space-y-4">
        <div
          v-for="recommendation in recommendations"
          :key="recommendation.id"
          class="border rounded-lg p-5 hover:shadow-md transition-shadow duration-200"
          :class="getRecommendationBorderClass(recommendation)"
        >
          <!-- Header Row -->
          <div class="flex items-start justify-between mb-3">
            <div class="flex-1">
              <div class="flex items-center gap-3 mb-2">
                <!-- Priority Badge -->
                <span
                  class="px-2 py-1 text-xs font-semibold rounded"
                  :class="getPriorityClass(recommendation.priority)"
                >
                  Priority {{ recommendation.priority }}
                </span>

                <!-- Category Badge -->
                <span
                  class="px-2 py-1 text-xs font-semibold rounded"
                  :class="getCategoryClass(recommendation.category)"
                >
                  {{ formatCategory(recommendation.category) }}
                </span>

                <!-- Status Badge -->
                <span
                  class="px-2 py-1 text-xs font-semibold rounded"
                  :class="getStatusClass(recommendation.status)"
                >
                  {{ formatStatus(recommendation.status) }}
                </span>
              </div>

              <h3 class="text-lg font-semibold text-horizon-500">{{ recommendation.title }}</h3>
            </div>

            <!-- Action Menu -->
            <div class="relative">
              <button
                @click="toggleActionMenu(recommendation.id)"
                class="p-2 text-horizon-400 hover:text-neutral-500 rounded-lg hover:bg-savannah-100"
              >
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                  <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z" />
                </svg>
              </button>

              <!-- Dropdown Menu -->
              <div
                v-if="activeMenuId === recommendation.id"
                class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-light-gray z-10"
              >
                <button
                  v-if="recommendation.status === 'pending'"
                  @click="updateStatus(recommendation.id, 'in_progress')"
                  class="block w-full text-left px-4 py-2 text-sm text-neutral-500 hover:bg-savannah-100"
                >
                  Start Working
                </button>
                <button
                  v-if="recommendation.status === 'in_progress'"
                  @click="updateStatus(recommendation.id, 'completed')"
                  class="block w-full text-left px-4 py-2 text-sm text-spring-700 hover:bg-spring-50"
                >
                  Mark Complete
                </button>
                <button
                  v-if="recommendation.status !== 'dismissed'"
                  @click="openDismissModal(recommendation)"
                  class="block w-full text-left px-4 py-2 text-sm text-violet-700 hover:bg-violet-50"
                >
                  Dismiss
                </button>
                <button
                  @click="deleteRecommendation(recommendation.id)"
                  class="block w-full text-left px-4 py-2 text-sm text-raspberry-700 hover:bg-raspberry-50"
                >
                  Delete
                </button>
              </div>
            </div>
          </div>

          <!-- Description -->
          <p class="text-neutral-500 mb-3">{{ recommendation.description }}</p>

          <!-- Action Required -->
          <div class="bg-eggshell-500 rounded-lg p-3 mb-3">
            <div class="text-sm font-medium text-violet-900 mb-1">Action Required:</div>
            <div class="text-sm text-violet-800">{{ recommendation.action_required }}</div>
          </div>

          <!-- Metrics Row -->
          <div class="flex flex-wrap gap-4 text-sm">
            <div v-if="recommendation.potential_saving" class="flex items-center gap-1">
              <span class="font-medium text-neutral-500">Potential Saving:</span>
              <span class="text-spring-600 font-semibold">£{{ formatNumber(recommendation.potential_saving) }}</span>
            </div>
            <div v-if="recommendation.impact_level" class="flex items-center gap-1">
              <span class="font-medium text-neutral-500">Impact:</span>
              <span :class="getImpactClass(recommendation.impact_level)">{{ recommendation.impact_level }}</span>
            </div>
            <div v-if="recommendation.estimated_effort" class="flex items-center gap-1">
              <span class="font-medium text-neutral-500">Effort:</span>
              <span class="text-neutral-500">{{ recommendation.estimated_effort }}</span>
            </div>
          </div>

          <!-- Completion/Dismissal Info -->
          <div v-if="recommendation.completed_at" class="mt-3 text-sm text-spring-600">
            ✓ Completed {{ formatDate(recommendation.completed_at) }}
          </div>
          <div v-if="recommendation.dismissed_at" class="mt-3 text-sm text-violet-600">
            Dismissed {{ formatDate(recommendation.dismissed_at) }}
            <span v-if="recommendation.dismissal_reason"> - {{ recommendation.dismissal_reason }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Dismiss Modal -->
    <div
      v-if="showDismissModal"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
      @click.self="closeDismissModal"
    >
      <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
        <div class="p-6">
          <h3 class="text-lg font-semibold text-horizon-500 mb-4">Dismiss Strategy</h3>
          <p class="text-neutral-500 mb-4">Please provide a reason for dismissing this strategy:</p>
          <textarea
            v-model="dismissalReason"
            class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
            rows="4"
            placeholder="Enter dismissal reason..."
          ></textarea>
          <div class="flex justify-end gap-3 mt-4">
            <button
              @click="closeDismissModal"
              class="px-4 py-2 text-sm font-medium text-neutral-500 bg-savannah-100 hover:bg-savannah-200 rounded-button transition-colors duration-200"
            >
              Cancel
            </button>
            <button
              @click="confirmDismiss"
              class="px-4 py-2 text-sm font-medium text-white bg-raspberry-500 hover:bg-raspberry-600 rounded-button transition-colors duration-200"
              :disabled="!dismissalReason"
            >
              Dismiss
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { mapState, mapGetters, mapActions } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';

import logger from '@/utils/logger';
export default {
  name: 'InvestmentRecommendationsTracker',

  mixins: [currencyMixin],

  data() {
    return {
      filters: {
        status: '',
        category: '',
        priority_level: '',
      },
      activeMenuId: null,
      showDismissModal: false,
      dismissalReason: '',
      recommendationToDissmiss: null,
    };
  },

  computed: {
    ...mapState('investment', ['loading', 'error']),
    ...mapGetters('investment', [
      'investmentRecommendations',
      'recommendationStats',
    ]),
    ...mapGetters('investment', { storeRecommendations: 'recommendations' }),

    // Get dynamic recommendations from analyseInvestment (shown on Strategies card)
    dynamicRecommendations() {
      return this.storeRecommendations?.recommendations || [];
    },

    recommendations() {
      // Use tracked recommendations if available, otherwise fallback to dynamic ones
      if (this.investmentRecommendations && this.investmentRecommendations.length > 0) {
        return this.investmentRecommendations;
      }
      // Convert dynamic recommendations to display format
      return this.dynamicRecommendations.map((rec, index) => ({
        id: `dynamic-${index}`,
        title: rec.title,
        description: rec.description,
        category: rec.category || 'general',
        priority: rec.priority || 5,
        status: 'pending',
        potential_saving: rec.potential_saving || 0,
        isDynamic: true, // Flag to indicate this is a dynamic recommendation
      }));
    },

    // Override stats to include dynamic recommendations count
    effectiveStats() {
      if (this.recommendationStats && this.recommendationStats.total > 0) {
        return this.recommendationStats;
      }
      const recs = this.recommendations;
      return {
        total: recs.length,
        pending: recs.filter(r => r.status === 'pending').length,
        in_progress: recs.filter(r => r.status === 'in_progress').length,
        completed: recs.filter(r => r.status === 'completed').length,
        total_potential_saving: recs.reduce((sum, r) => sum + (r.potential_saving || 0), 0),
      };
    },
  },

  mounted() {
    this.loadRecommendations();
    // Also load dynamic recommendations if not already loaded
    if (!this.dynamicRecommendations.length) {
      this.$store.dispatch('investment/fetchInvestmentData').then(() => {
        this.$store.dispatch('investment/analyseInvestment');
      });
    }
  },

  methods: {
    ...mapActions('investment', [
      'fetchInvestmentRecommendations',
      'updateRecommendationStatus',
      'deleteInvestmentRecommendation',
    ]),

    async loadRecommendations() {
      try {
        await this.fetchInvestmentRecommendations(this.filters);
      } catch (error) {
        logger.error('Failed to load recommendations:', error);
      }
    },

    async refreshRecommendations() {
      await this.loadRecommendations();
    },

    applyFilters() {
      this.loadRecommendations();
    },

    clearFilters() {
      this.filters = {
        status: '',
        category: '',
        priority_level: '',
      };
      this.loadRecommendations();
    },

    toggleActionMenu(id) {
      this.activeMenuId = this.activeMenuId === id ? null : id;
    },

    async updateStatus(id, status) {
      try {
        await this.updateRecommendationStatus({ id, status });
        this.activeMenuId = null;
      } catch (error) {
        logger.error('Failed to update status:', error);
      }
    },

    openDismissModal(recommendation) {
      this.recommendationToDissmiss = recommendation;
      this.showDismissModal = true;
      this.dismissalReason = '';
      this.activeMenuId = null;
    },

    closeDismissModal() {
      this.showDismissModal = false;
      this.recommendationToDissmiss = null;
      this.dismissalReason = '';
    },

    async confirmDismiss() {
      if (!this.dismissalReason || !this.recommendationToDissmiss) return;

      try {
        await this.updateRecommendationStatus({
          id: this.recommendationToDissmiss.id,
          status: 'dismissed',
          dismissalReason: this.dismissalReason,
        });
        this.closeDismissModal();
      } catch (error) {
        logger.error('Failed to dismiss recommendation:', error);
      }
    },

    async deleteRecommendation(id) {
      if (!confirm('Are you sure you want to delete this strategy? This action cannot be undone.')) {
        return;
      }

      try {
        await this.deleteInvestmentRecommendation(id);
        this.activeMenuId = null;
      } catch (error) {
        logger.error('Failed to delete recommendation:', error);
      }
    },

    getRecommendationBorderClass(recommendation) {
      if (recommendation.status === 'completed') return 'border-l-4 border-spring-500 bg-white';
      if (recommendation.status === 'dismissed') return 'border-light-gray bg-eggshell-500';
      if (recommendation.priority <= 3) return 'border-l-4 border-raspberry-500 bg-white';
      if (recommendation.priority <= 7) return 'border-l-4 border-violet-500 bg-white';
      return 'border-l-4 border-violet-500 bg-white';
    },

    getPriorityClass(priority) {
      if (priority <= 3) return 'bg-raspberry-500 text-white';
      if (priority <= 7) return 'bg-violet-500 text-white';
      return 'bg-violet-500 text-white';
    },

    getCategoryClass(category) {
      const classes = {
        rebalancing: 'bg-violet-500 text-white',
        tax: 'bg-spring-500 text-white',
        fees: 'bg-violet-500 text-white',
        risk: 'bg-raspberry-500 text-white',
        goal: 'bg-violet-500 text-white',
        contribution: 'bg-teal-500 text-white',
      };
      return classes[category] || 'bg-eggshell-500 text-white';
    },

    getStatusClass(status) {
      const classes = {
        pending: 'bg-violet-500 text-white',
        in_progress: 'bg-violet-500 text-white',
        completed: 'bg-spring-500 text-white',
        dismissed: 'bg-eggshell-500 text-white',
      };
      return classes[status] || 'bg-eggshell-500 text-white';
    },

    getImpactClass(impact) {
      const classes = {
        low: 'text-violet-600',
        medium: 'text-violet-600',
        high: 'text-raspberry-600',
      };
      return classes[impact] || 'text-neutral-500';
    },

    formatCategory(category) {
      const labels = {
        rebalancing: 'Rebalancing',
        tax: 'Tax Optimisation',
        fees: 'Fee Reduction',
        risk: 'Risk Management',
        goal: 'Goal Alignment',
        contribution: 'Contribution Strategy',
      };
      return labels[category] || category;
    },

    formatStatus(status) {
      const labels = {
        pending: 'Pending',
        in_progress: 'In Progress',
        completed: 'Completed',
        dismissed: 'Dismissed',
      };
      return labels[status] || status;
    },

    formatDate(dateString) {
      if (!dateString) return '';
      const date = new Date(dateString);
      return date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
    },
  },
};
</script>

<style scoped>
/* Additional custom styles if needed */
</style>
