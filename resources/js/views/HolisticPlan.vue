<template>
  <div class="holistic-plan-container">
    <!-- Page Header -->
    <div class="bg-white shadow-sm border-b border-gray-200 mb-4 sm:mb-6">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Holistic Financial Plan</h1>
            <p class="mt-1 text-sm text-gray-600">
              Your comprehensive financial strategy across all modules
            </p>
          </div>
          <div class="flex space-x-3 flex-shrink-0">
            <button
              @click="refreshPlan"
              :disabled="loading"
              class="inline-flex items-center justify-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 w-full sm:w-auto"
            >
              <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
              </svg>
              Refresh Plan
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 text-center">
      <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
      <p class="mt-4 text-gray-600">Generating your holistic plan...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
      <div class="bg-red-50 border border-red-200 rounded-lg p-6 text-center">
        <svg class="mx-auto h-12 w-12 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
        </svg>
        <h3 class="mt-4 text-lg font-medium text-red-900">Error Loading Plan</h3>
        <p class="mt-2 text-sm text-red-700">{{ error }}</p>
        <button
          @click="refreshPlan"
          class="mt-4 inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-error-600 hover:bg-error-700"
        >
          Try Again
        </button>
      </div>
    </div>

    <!-- Main Content -->
    <div v-else-if="plan" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-12">
      <!-- Executive Summary -->
      <ExecutiveSummary
        v-if="executiveSummary"
        :summary="executiveSummary"
        class="mb-6"
      />

      <!-- Tab Navigation -->
      <div class="bg-white shadow rounded-lg mb-6">
        <div class="border-b border-gray-200">
          <nav class="flex -mb-px overflow-x-auto scrollbar-hide" aria-label="Tabs">
            <button
              v-for="tab in tabs"
              :key="tab.id"
              @click="activeTab = tab.id"
              :class="[
                activeTab === tab.id
                  ? 'border-blue-500 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                'whitespace-nowrap py-3 sm:py-4 px-3 sm:px-4 border-b-2 font-medium text-xs sm:text-sm text-center flex-shrink-0'
              ]"
            >
              <span class="hidden sm:inline">{{ tab.label }}</span>
              <span class="sm:hidden">{{ getShortLabel(tab.id) }}</span>
              <span
                v-if="tab.badge"
                :class="[
                  activeTab === tab.id ? 'bg-blue-100 text-blue-600' : 'bg-gray-100 text-gray-600',
                  'ml-1 sm:ml-2 py-0.5 px-1.5 sm:px-2.5 rounded-full text-xs font-medium'
                ]"
              >
                {{ tab.badge }}
              </span>
            </button>
          </nav>
        </div>

        <!-- Tab Content -->
        <div class="p-6">
          <!-- Action Plan Tab -->
          <div v-show="activeTab === 'action-plan'">
            <PrioritizedRecommendations
              v-if="recommendations.length > 0"
              :recommendations="recommendations"
              :action-plan="actionPlan"
              @mark-done="handleMarkDone"
              @mark-in-progress="handleMarkInProgress"
              @dismiss="handleDismiss"
              @update-notes="handleUpdateNotes"
            />
            <div v-else class="text-center py-12">
              <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
              <h3 class="mt-4 text-lg font-medium text-gray-900">No Recommendations</h3>
              <p class="mt-2 text-sm text-gray-500">Your financial plan looks great! No actions needed at this time.</p>
            </div>
          </div>

          <!-- Cashflow Tab -->
          <div v-show="activeTab === 'cashflow'">
            <CashFlowAllocationChart
              v-if="cashFlowAnalysis"
              :cashflow-data="cashFlowAnalysis"
            />
          </div>

          <!-- Net Worth Projection Tab -->
          <div v-show="activeTab === 'projection'">
            <NetWorthProjectionChart
              v-if="netWorthProjection"
              :projection-data="netWorthProjection"
            />
          </div>

          <!-- Risk Assessment Tab -->
          <div v-show="activeTab === 'risk'">
            <RiskAssessment
              v-if="riskAssessment"
              :risk-data="riskAssessment"
            />
          </div>

          <!-- Module Summary Tab -->
          <div v-show="activeTab === 'modules'">
            <ModuleSummaries
              v-if="plan.module_summaries"
              :summaries="plan.module_summaries"
            />
          </div>
        </div>
      </div>

      <!-- Conflicts Section (if any) -->
      <div v-if="hasConflicts" class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 mb-6">
        <div class="flex">
          <div class="flex-shrink-0">
            <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
          </div>
          <div class="ml-3 flex-1">
            <h3 class="text-sm font-medium text-yellow-800">Conflicts Detected</h3>
            <div class="mt-2 text-sm text-yellow-700">
              <p>We've identified {{ plan.conflicts?.length || 0 }} conflicts in your recommendations. These have been automatically resolved using priority-based allocation.</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
      <div class="text-center">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        <h3 class="mt-4 text-lg font-medium text-gray-900">Generate Your Holistic Plan</h3>
        <p class="mt-2 text-sm text-gray-500">Click the button below to create your comprehensive financial plan.</p>
        <button
          @click="refreshPlan"
          class="mt-4 inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700"
        >
          Generate Plan
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import { mapState, mapGetters, mapActions } from 'vuex';
import ExecutiveSummary from '../components/Holistic/ExecutiveSummary.vue';
import PrioritizedRecommendations from '../components/Holistic/PrioritizedRecommendations.vue';
import CashFlowAllocationChart from '../components/Holistic/CashFlowAllocationChart.vue';
import NetWorthProjectionChart from '../components/Holistic/NetWorthProjectionChart.vue';
import RiskAssessment from '../components/Holistic/RiskAssessment.vue';
import ModuleSummaries from '../components/Holistic/ModuleSummaries.vue';

export default {
  name: 'HolisticPlan',

  components: {
    ExecutiveSummary,
    PrioritizedRecommendations,
    CashFlowAllocationChart,
    NetWorthProjectionChart,
    RiskAssessment,
    ModuleSummaries,
  },

  data() {
    return {
      activeTab: 'action-plan',
    };
  },

  computed: {
    ...mapState('holistic', ['plan', 'recommendations', 'cashFlowAnalysis', 'loading', 'error']),
    ...mapGetters('holistic', [
      'executiveSummary',
      'netWorthProjection',
      'riskAssessment',
      'actionPlan',
      'activeRecommendations',
    ]),

    tabs() {
      return [
        {
          id: 'action-plan',
          label: 'Action Plan',
          badge: this.activeRecommendations?.length || null,
        },
        {
          id: 'cashflow',
          label: 'Cashflow',
        },
        {
          id: 'projection',
          label: 'Net Worth Projection',
        },
        {
          id: 'risk',
          label: 'Risk Assessment',
        },
        {
          id: 'modules',
          label: 'Module Summary',
        },
      ];
    },

    hasConflicts() {
      return this.plan?.conflicts && this.plan.conflicts.length > 0;
    },
  },

  async mounted() {
    await this.loadPlan();
  },

  methods: {
    ...mapActions('holistic', [
      'fetchPlan',
      'fetchCashFlowAnalysis',
      'markRecommendationDone',
      'markRecommendationInProgress',
      'dismissRecommendation',
      'updateRecommendationNotes',
      'clearError',
    ]),

    async loadPlan() {
      try {
        await this.fetchPlan();
        await this.fetchCashFlowAnalysis();
      } catch (error) {
        console.error('Error loading holistic plan:', error);
      }
    },

    async refreshPlan() {
      this.clearError();
      await this.loadPlan();
    },

    async handleMarkDone(recommendationId) {
      try {
        await this.markRecommendationDone(recommendationId);
        this.$toast?.success('Recommendation marked as completed');
      } catch (error) {
        this.$toast?.error('Failed to update recommendation');
      }
    },

    async handleMarkInProgress(recommendationId) {
      try {
        await this.markRecommendationInProgress(recommendationId);
        this.$toast?.success('Recommendation marked as in progress');
      } catch (error) {
        this.$toast?.error('Failed to update recommendation');
      }
    },

    async handleDismiss(recommendationId) {
      try {
        await this.dismissRecommendation(recommendationId);
        this.$toast?.success('Recommendation dismissed');
      } catch (error) {
        this.$toast?.error('Failed to dismiss recommendation');
      }
    },

    async handleUpdateNotes({ id, notes }) {
      try {
        await this.updateRecommendationNotes({ id, notes });
        this.$toast?.success('Notes updated successfully');
      } catch (error) {
        this.$toast?.error('Failed to update notes');
      }
    },

    getShortLabel(tabId) {
      const labels = {
        'action-plan': 'Actions',
        cashflow: 'Cashflow',
        projection: 'Projection',
        risk: 'Risk',
        modules: 'Modules',
      };
      return labels[tabId] || tabId;
    },
  },
};
</script>

<style scoped>
.holistic-plan-container {
  min-height: calc(100vh - 64px);
  background-color: #f9fafb;
}

/* Hide scrollbar for horizontal tab navigation */
.scrollbar-hide {
  -ms-overflow-style: none;
  scrollbar-width: none;
  -webkit-overflow-scrolling: touch;
}
.scrollbar-hide::-webkit-scrollbar {
  display: none;
}
</style>
