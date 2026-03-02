<template>
  <AppLayout>
    <div class="holistic-plan py-2 sm:py-6">
      <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
          <div>
            <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold text-gray-900 mb-2">Holistic Financial Plan</h1>
            <p class="text-gray-600">
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

        <!-- Loading State -->
        <div v-if="loading" class="py-12 text-center">
          <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600"></div>
          <p class="mt-4 text-gray-600">Generating your holistic plan...</p>
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="py-12">
          <div class="bg-red-50 border border-red-200 rounded-lg p-6 text-center">
            <svg class="mx-auto h-12 w-12 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <h3 class="mt-4 text-lg font-medium text-red-900">Error Loading Plan</h3>
            <p class="mt-2 text-sm text-red-700">{{ error }}</p>
            <button
              @click="refreshPlan"
              class="mt-4 inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700"
            >
              Try Again
            </button>
          </div>
        </div>

        <!-- Main Content: Flowing Vertical Layout -->
        <div v-else-if="plan" class="space-y-6 pb-12">
          <!-- 1. Executive Summary -->
          <ExecutiveSummary
            v-if="executiveSummary"
            :summary="executiveSummary"
          />

          <!-- 2. Financial Snapshot -->
          <FinancialSnapshot
            v-if="financialSnapshot"
            :snapshot="financialSnapshot"
          />

          <!-- 3. Module Summaries -->
          <ModuleSummaries
            v-if="plan.module_summaries"
            :summaries="plan.module_summaries"
          />

          <!-- 4. Prioritised Recommendations -->
          <div>
            <PrioritizedRecommendations
              v-if="recommendations.length > 0"
              :recommendations="recommendations"
              :action-plan="actionPlan"
              @mark-done="handleMarkDone"
              @mark-in-progress="handleMarkInProgress"
              @dismiss="handleDismiss"
              @update-notes="handleUpdateNotes"
            />
            <div v-else class="bg-white border border-gray-200 rounded-lg text-center py-12">
              <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
              <h3 class="mt-4 text-lg font-medium text-gray-900">No Recommendations</h3>
              <p class="mt-2 text-sm text-gray-500">Your financial plan looks great! No actions needed at this time.</p>
            </div>
          </div>

          <!-- 5. Cashflow Allocation -->
          <CashFlowAllocationChart
            v-if="cashFlowAnalysis"
            :cashflow-data="cashFlowAnalysis"
          />

          <!-- 6. Net Worth Projection -->
          <NetWorthProjectionChart
            v-if="netWorthProjection"
            :projection-data="netWorthProjection"
          />

          <!-- 7. Risk Assessment -->
          <RiskAssessment
            v-if="riskAssessment"
            :risk-data="riskAssessment"
          />

          <!-- 8. Conflicts (only if detected) -->
          <div v-if="hasConflicts" class="bg-blue-50 border border-blue-200 rounded-lg p-6">
            <div class="flex">
              <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
              </div>
              <div class="ml-3 flex-1">
                <h3 class="text-sm font-medium text-blue-800">Conflicts Detected</h3>
                <div class="mt-2 text-sm text-blue-700">
                  <p>We've identified {{ plan.conflicts?.length || 0 }} conflicts in your recommendations. These have been automatically resolved using priority-based allocation.</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div v-else class="py-12">
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
    </div>
  </AppLayout>
</template>

<script>
import { mapState, mapGetters, mapActions } from 'vuex';
import AppLayout from '@/layouts/AppLayout.vue';
import ExecutiveSummary from '../components/Holistic/ExecutiveSummary.vue';
import FinancialSnapshot from '../components/Holistic/FinancialSnapshot.vue';
import PrioritizedRecommendations from '../components/Holistic/PrioritizedRecommendations.vue';
import CashFlowAllocationChart from '../components/Holistic/CashFlowAllocationChart.vue';
import NetWorthProjectionChart from '../components/Holistic/NetWorthProjectionChart.vue';
import RiskAssessment from '../components/Holistic/RiskAssessment.vue';
import ModuleSummaries from '../components/Holistic/ModuleSummaries.vue';

export default {
  name: 'HolisticPlan',

  components: {
    AppLayout,
    ExecutiveSummary,
    FinancialSnapshot,
    PrioritizedRecommendations,
    CashFlowAllocationChart,
    NetWorthProjectionChart,
    RiskAssessment,
    ModuleSummaries,
  },

  computed: {
    ...mapState('holistic', ['plan', 'recommendations', 'cashFlowAnalysis', 'loading', 'error']),
    ...mapGetters('holistic', [
      'executiveSummary',
      'financialSnapshot',
      'netWorthProjection',
      'riskAssessment',
      'actionPlan',
    ]),

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

    async handleRecommendationAction(action, payload, successMsg, errorMsg) {
      try {
        await action(payload);
        this.$toast?.success(successMsg);
      } catch (error) {
        this.$toast?.error(errorMsg);
      }
    },

    handleMarkDone(id) {
      this.handleRecommendationAction(this.markRecommendationDone, id, 'Recommendation marked as completed', 'Failed to update recommendation');
    },

    handleMarkInProgress(id) {
      this.handleRecommendationAction(this.markRecommendationInProgress, id, 'Recommendation marked as in progress', 'Failed to update recommendation');
    },

    handleDismiss(id) {
      this.handleRecommendationAction(this.dismissRecommendation, id, 'Recommendation dismissed', 'Failed to dismiss recommendation');
    },

    handleUpdateNotes(payload) {
      this.handleRecommendationAction(this.updateRecommendationNotes, payload, 'Notes updated successfully', 'Failed to update notes');
    },
  },
};
</script>
