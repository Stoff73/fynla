<template>
  <div class="comprehensive-investment-plan">
    <!-- Loading State -->
    <div v-if="loading" class="flex justify-center items-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-gray-50 rounded-lg p-4 mb-6">
      <div class="flex items-center">
        <svg class="h-5 w-5 text-red-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
        </svg>
        <span class="text-sm font-medium text-red-800">{{ error }}</span>
      </div>
    </div>

    <!-- Plan Content -->
    <div v-else-if="plan">
      <!-- Header Section with Portfolio Health Score -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <!-- Portfolio Health Score Card -->
        <div class="bg-gray-50 rounded-lg shadow-md p-6 lg:col-span-1">
          <h3 class="text-lg font-semibold text-gray-800 mb-4">Portfolio Health Score</h3>
          <div class="flex items-center justify-center mb-4">
            <div class="relative">
              <apexchart
                v-if="plan.portfolio_health_score !== undefined"
                type="radialBar"
                :options="healthScoreChartOptions"
                :series="[plan.portfolio_health_score]"
                height="200"
              />
            </div>
          </div>
          <div class="text-center">
            <p class="text-3xl font-bold mb-1" :class="getHealthScoreColour(plan.portfolio_health_score)">
              {{ plan.portfolio_health_score || 0 }}/100
            </p>
            <p class="text-sm text-gray-600">{{ getHealthScoreInterpretation(plan.portfolio_health_score) }}</p>
          </div>
        </div>

        <!-- Executive Summary -->
        <div class="bg-white rounded-lg shadow-md p-6 lg:col-span-2">
          <h3 class="text-lg font-semibold text-gray-800 mb-4">Executive Summary</h3>
          <div v-if="plan.executive_summary" class="space-y-4">
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
              <div>
                <p class="text-sm text-gray-600 mb-1">Total Portfolio Value</p>
                <p class="text-xl font-semibold text-gray-800">
                  £{{ formatNumber(plan.executive_summary.total_portfolio_value || 0) }}
                </p>
              </div>
              <div>
                <p class="text-sm text-gray-600 mb-1">Total Return (1Y)</p>
                <p class="text-xl font-semibold" :class="plan.executive_summary.total_return >= 0 ? 'text-green-600' : 'text-red-600'">
                  {{ formatPercentage(plan.executive_summary.total_return || 0) }}%
                </p>
              </div>
              <div>
                <p class="text-sm text-gray-600 mb-1">Risk Score</p>
                <p class="text-xl font-semibold text-gray-800">
                  {{ plan.executive_summary.risk_score || 'N/A' }}/10
                </p>
              </div>
            </div>

            <!-- Key Metrics -->
            <div class="mt-4 p-4 bg-gray-50 rounded-md">
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <p class="text-xs text-gray-600 mb-1">Diversification</p>
                  <p class="text-sm font-semibold text-gray-800">
                    {{ formatPercentage(plan.executive_summary.diversification_score || 0) }}%
                  </p>
                </div>
                <div>
                  <p class="text-xs text-gray-600 mb-1">Tax Efficiency</p>
                  <p class="text-sm font-semibold text-gray-800">
                    {{ formatPercentage(plan.executive_summary.tax_efficiency || 0) }}%
                  </p>
                </div>
                <div>
                  <p class="text-xs text-gray-600 mb-1">Fee Efficiency</p>
                  <p class="text-sm font-semibold text-gray-800">
                    {{ formatPercentage(plan.executive_summary.fee_efficiency || 0) }}%
                  </p>
                </div>
                <div>
                  <p class="text-xs text-gray-600 mb-1">Goal Progress</p>
                  <p class="text-sm font-semibold text-gray-800">
                    {{ formatPercentage(plan.executive_summary.goal_progress || 0) }}%
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Tab Navigation -->
      <div class="bg-white rounded-lg shadow-md mb-6">
        <div class="border-b border-gray-200">
          <nav class="flex -mb-px overflow-x-auto">
            <button
              v-for="tab in tabs"
              :key="tab.id"
              @click="activeTab = tab.id"
              :class="[
                'py-4 px-6 text-sm font-medium border-b-2 transition-colors duration-200 whitespace-nowrap',
                activeTab === tab.id
                  ? 'border-blue-500 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              ]"
            >
              {{ tab.name }}
            </button>
          </nav>
        </div>

        <!-- Tab Content -->
        <div class="p-6">
          <!-- Current Situation Tab -->
          <div v-if="activeTab === 'situation'" class="space-y-6">
            <CurrentSituationSection :data="plan.current_situation" />
          </div>

          <!-- Goals Tab -->
          <div v-if="activeTab === 'goals'" class="space-y-6">
            <GoalProgressSection :data="plan.goal_progress" />
          </div>

          <!-- Risk Tab -->
          <div v-if="activeTab === 'risk'" class="space-y-6">
            <RiskAnalysisSection :data="plan.risk_analysis" />
          </div>

          <!-- Tax Tab -->
          <div v-if="activeTab === 'tax'" class="space-y-6">
            <TaxStrategySection :data="plan.tax_strategy" />
          </div>

          <!-- Fees Tab -->
          <div v-if="activeTab === 'fees'" class="space-y-6">
            <FeeAnalysisSection :data="plan.fee_analysis" />
          </div>

          <!-- Recommendations Tab -->
          <div v-if="activeTab === 'recommendations'" class="space-y-6">
            <RecommendationsSection :data="plan.recommendations" />
          </div>

          <!-- Action Plan Tab -->
          <div v-if="activeTab === 'action-plan'" class="space-y-6">
            <ActionPlanSection :data="plan.action_plan" />
          </div>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex justify-end space-x-3">
        <button
          @click="refreshPlan"
          class="px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
        >
          Refresh Plan
        </button>
        <button
          @click="exportPlan"
          class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
        >
          Export to PDF
        </button>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="bg-white rounded-lg shadow p-12 text-center">
      <svg
        class="mx-auto h-16 w-16 text-gray-400 mb-4"
        fill="none"
        stroke="currentColor"
        viewBox="0 0 24 24"
      >
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          stroke-width="2"
          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
        />
      </svg>
      <h3 class="text-lg font-medium text-gray-900 mb-2">
        No Investment Plan Available
      </h3>
      <p class="text-sm text-gray-500 mb-6">
        Generate your first comprehensive investment plan to get personalized recommendations and insights.
      </p>
      <button
        @click="generatePlan"
        class="px-6 py-3 bg-blue-600 text-white font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
      >
        Generate Investment Plan
      </button>
    </div>
  </div>
</template>

<script>
import { mapState, mapActions } from 'vuex';
import CurrentSituationSection from './PlanSections/CurrentSituationSection.vue';
import GoalProgressSection from './PlanSections/GoalProgressSection.vue';
import RiskAnalysisSection from './PlanSections/RiskAnalysisSection.vue';
import TaxStrategySection from './PlanSections/TaxStrategySection.vue';
import FeeAnalysisSection from './PlanSections/FeeAnalysisSection.vue';
import RecommendationsSection from './PlanSections/RecommendationsSection.vue';
import ActionPlanSection from './PlanSections/ActionPlanSection.vue';

export default {
  name: 'ComprehensiveInvestmentPlan',

  components: {
    CurrentSituationSection,
    GoalProgressSection,
    RiskAnalysisSection,
    TaxStrategySection,
    FeeAnalysisSection,
    RecommendationsSection,
    ActionPlanSection,
  },

  props: {
    planId: {
      type: Number,
      default: null,
    },
  },

  data() {
    return {
      loading: false,
      error: null,
      activeTab: 'situation',
      plan: null,
      tabs: [
        { id: 'situation', name: 'Current Situation' },
        { id: 'goals', name: 'Goal Progress' },
        { id: 'risk', name: 'Risk Analysis' },
        { id: 'tax', name: 'Tax Strategy' },
        { id: 'fees', name: 'Fee Analysis' },
        { id: 'recommendations', name: 'Recommendations' },
        { id: 'action-plan', name: 'Action Plan' },
      ],
    };
  },

  computed: {
    ...mapState('investment', ['investmentPlan']),

    healthScoreChartOptions() {
      const score = this.plan?.portfolio_health_score || 0;

      return {
        chart: {
          type: 'radialBar',
          sparkline: {
            enabled: true,
          },
        },
        plotOptions: {
          radialBar: {
            startAngle: -135,
            endAngle: 135,
            hollow: {
              size: '65%',
            },
            track: {
              background: '#e5e7eb',
              strokeWidth: '100%',
            },
            dataLabels: {
              name: {
                show: false,
              },
              value: {
                show: true,
                fontSize: '24px',
                fontWeight: 'bold',
                offsetY: 8,
                color: this.getHealthScoreColourHex(score),
                formatter: (val) => `${Math.round(val)}`,
              },
            },
          },
        },
        fill: {
          type: 'gradient',
          gradient: {
            shade: 'dark',
            type: 'horizontal',
            shadeIntensity: 0.5,
            gradientToColours: [this.getHealthScoreColourHex(score)],
            stops: [0, 100],
          },
        },
        stroke: {
          lineCap: 'round',
        },
        colours: [this.getHealthScoreColourHex(score)],
      };
    },
  },

  mounted() {
    this.loadPlan();
  },

  methods: {
    ...mapActions('investment', ['getLatestInvestmentPlan', 'generateInvestmentPlan', 'getInvestmentPlanById']),

    async loadPlan() {
      this.loading = true;
      this.error = null;

      try {
        if (this.planId) {
          // Load specific plan by ID
          const response = await this.getInvestmentPlanById(this.planId);
          this.plan = response.data?.plan_data || null;
        } else {
          // Load latest plan
          await this.getLatestInvestmentPlan();
          this.plan = this.investmentPlan?.plan_data || null;
        }
      } catch (err) {
        // 404 means no plan exists yet - show empty state instead of error
        if (err.response?.status === 404) {
          this.plan = null;
          this.error = null;
        } else {
          // Only show error for actual failures (500, network errors, etc.)
          console.error('Error loading investment plan:', err);
          this.error = err.response?.data?.message || 'Failed to load investment plan. Please try again.';
        }
      } finally {
        this.loading = false;
      }
    },

    async generatePlan() {
      this.loading = true;
      this.error = null;

      try {
        const response = await this.generateInvestmentPlan();
        this.plan = response.data?.plan || null;
      } catch (err) {
        console.error('Error generating investment plan:', err);
        this.error = err.response?.data?.message || 'Failed to generate investment plan';
      } finally {
        this.loading = false;
      }
    },

    async refreshPlan() {
      await this.generatePlan();
    },

    exportPlan() {
      // TODO: Implement PDF export functionality
      alert('PDF export functionality coming soon!');
    },

    formatNumber(value) {
      if (value === null || value === undefined) return '0';
      return Math.round(value).toLocaleString('en-GB');
    },

    formatPercentage(value) {
      if (value === null || value === undefined) return '0.0';
      return value.toFixed(1);
    },

    getHealthScoreColour(score) {
      if (score >= 80) return 'text-green-600';
      if (score >= 60) return 'text-blue-600';
      if (score >= 40) return 'text-yellow-600';
      if (score >= 20) return 'text-orange-600';
      return 'text-red-600';
    },

    getHealthScoreColourHex(score) {
      if (score >= 80) return '#10B981'; // green-600
      if (score >= 60) return '#3B82F6'; // blue-600
      if (score >= 40) return '#FBBF24'; // yellow-600
      if (score >= 20) return '#F97316'; // orange-600
      return '#EF4444'; // red-600
    },

    getHealthScoreInterpretation(score) {
      if (score >= 80) return 'Excellent portfolio health';
      if (score >= 60) return 'Good portfolio health';
      if (score >= 40) return 'Fair portfolio health';
      if (score >= 20) return 'Needs improvement';
      return 'Requires urgent attention';
    },
  },
};
</script>

<style scoped>
/* Animations */
@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

.animate-spin {
  animation: spin 1s linear infinite;
}
</style>
