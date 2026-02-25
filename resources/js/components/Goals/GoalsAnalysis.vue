<template>
  <div class="goals-analysis">
    <!-- Loading State -->
    <div v-if="loading" class="flex justify-center items-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
      <span class="ml-3 text-gray-600">Analysing your goals...</span>
    </div>

    <!-- Empty State -->
    <div v-else-if="!analysis || !analysis.summary" class="text-center py-12">
      <div class="mx-auto w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center mb-4">
        <svg class="w-6 h-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
        </svg>
      </div>
      <h3 class="text-lg font-medium text-gray-900 mb-1">No analysis available</h3>
      <p class="text-gray-500 mb-4">Add goals to see detailed analysis and recommendations</p>
      <button
        @click="$emit('refresh')"
        class="px-4 py-2 text-sm font-medium text-primary-600 bg-primary-50 rounded-lg hover:bg-primary-100 transition-colors"
      >
        Refresh Analysis
      </button>
    </div>

    <!-- Analysis Content -->
    <div v-else>
      <!-- Refresh Button -->
      <div class="flex justify-end mb-6">
        <button
          @click="$emit('refresh')"
          class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors flex items-center gap-2"
        >
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
          Refresh Analysis
        </button>
      </div>

      <!-- Summary Section -->
      <div class="mb-8">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Summary</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <div class="bg-gray-50 rounded-lg p-4">
            <p class="text-sm text-gray-500 mb-1">Total Goals</p>
            <p class="text-2xl font-bold text-gray-900">{{ analysis.summary.total_goals }}</p>
          </div>
          <div class="bg-green-50 rounded-lg p-4">
            <p class="text-sm text-green-600 mb-1">On Track</p>
            <p class="text-2xl font-bold text-green-700">{{ analysis.summary.on_track_count }}</p>
          </div>
          <div class="bg-blue-50 rounded-lg p-4">
            <p class="text-sm text-blue-600 mb-1">Total Target</p>
            <p class="text-2xl font-bold text-blue-700">{{ formatCurrency(analysis.summary.total_target) }}</p>
          </div>
          <div class="bg-primary-50 rounded-lg p-4">
            <p class="text-sm text-primary-600 mb-1">Monthly Required</p>
            <p class="text-2xl font-bold text-primary-700">{{ formatCurrency(analysis.summary.total_monthly_required) }}</p>
          </div>
        </div>
      </div>

      <!-- Affordability Analysis -->
      <div v-if="analysis.affordability" class="mb-8">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Affordability</h3>
        <div class="bg-white border border-gray-200 rounded-lg p-6">
          <div class="flex items-center gap-4 mb-4">
            <div
              class="w-16 h-16 rounded-full flex items-center justify-center text-2xl"
              :class="getAffordabilityClass(analysis.affordability.category)"
            >
              {{ getAffordabilityIcon(analysis.affordability.category) }}
            </div>
            <div>
              <p class="text-lg font-semibold text-gray-900 capitalize">{{ analysis.affordability.category }}</p>
              <p class="text-sm text-gray-600">{{ analysis.affordability.message }}</p>
            </div>
          </div>

          <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-4 border-t border-gray-100">
            <div>
              <p class="text-xs text-gray-500">Monthly Income</p>
              <p class="text-sm font-semibold text-gray-900">{{ formatCurrency(analysis.affordability.monthly_income) }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500">Monthly Expenses</p>
              <p class="text-sm font-semibold text-gray-900">{{ formatCurrency(analysis.affordability.monthly_expenditure) }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500">Available Surplus</p>
              <p class="text-sm font-semibold" :class="analysis.affordability.monthly_surplus >= 0 ? 'text-green-600' : 'text-red-600'">
                {{ formatCurrency(analysis.affordability.monthly_surplus) }}
              </p>
            </div>
            <div>
              <p class="text-xs text-gray-500">Required for Goals</p>
              <p class="text-sm font-semibold text-gray-900">{{ formatCurrency(analysis.affordability.total_required) }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Goals by Module -->
      <div v-if="analysis.by_module" class="mb-8">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Goals by Module</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <div
            v-for="(moduleData, moduleName) in analysis.by_module"
            :key="moduleName"
            class="bg-white border border-gray-200 rounded-lg p-4"
          >
            <div class="flex items-center gap-2 mb-3">
              <span class="text-xl">{{ getModuleIcon(moduleName) }}</span>
              <h4 class="font-medium text-gray-900 capitalize">{{ moduleName }}</h4>
            </div>
            <div class="space-y-2">
              <div class="flex justify-between text-sm">
                <span class="text-gray-500">Goals</span>
                <span class="font-medium text-gray-900">{{ moduleData.count }}</span>
              </div>
              <div class="flex justify-between text-sm">
                <span class="text-gray-500">Target</span>
                <span class="font-medium text-gray-900">{{ formatCurrency(moduleData.total_target) }}</span>
              </div>
              <div class="flex justify-between text-sm">
                <span class="text-gray-500">Current</span>
                <span class="font-medium text-gray-900">{{ formatCurrency(moduleData.total_current) }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Recommendations -->
      <div v-if="analysis.recommendations && analysis.recommendations.length > 0" class="mb-8">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Recommendations</h3>
        <div class="space-y-3">
          <div
            v-for="(rec, index) in analysis.recommendations"
            :key="index"
            class="bg-white border rounded-lg p-4"
            :class="getRecommendationBorderClass(rec.priority)"
          >
            <div class="flex items-start gap-3">
              <span class="text-lg">{{ getRecommendationIcon(rec.type) }}</span>
              <div class="flex-1">
                <div class="flex items-center gap-2 mb-1">
                  <h4 class="font-medium text-gray-900">{{ rec.title }}</h4>
                  <span
                    class="px-2 py-0.5 text-xs font-medium rounded-full"
                    :class="getPriorityClass(rec.priority)"
                  >
                    {{ rec.priority }}
                  </span>
                </div>
                <p class="text-sm text-gray-600">{{ rec.description }}</p>
                <p v-if="rec.action" class="text-sm text-primary-600 mt-2">
                  {{ rec.action }}
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Risk Analysis for Investment Goals -->
      <div v-if="analysis.investment_goals_risk" class="mb-8">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Investment Goal Risk Analysis</h3>
        <div class="bg-white border border-gray-200 rounded-lg p-6">
          <p class="text-sm text-gray-600 mb-4">
            Based on your risk profile and time horizons, here are the recommended asset allocations for your investment goals.
          </p>
          <div class="space-y-4">
            <div
              v-for="(riskData, goalId) in analysis.investment_goals_risk"
              :key="goalId"
              class="border-t border-gray-100 pt-4"
            >
              <p class="font-medium text-gray-900 mb-2">{{ riskData.goal_name }}</p>
              <div class="flex items-center gap-4">
                <div class="flex-1 h-4 bg-gray-100 rounded-full overflow-hidden flex">
                  <div
                    class="h-full bg-blue-500"
                    :style="{ width: riskData.allocation.equities + '%' }"
                    :title="`Equities: ${riskData.allocation.equities}%`"
                  ></div>
                  <div
                    class="h-full bg-green-500"
                    :style="{ width: riskData.allocation.bonds + '%' }"
                    :title="`Bonds: ${riskData.allocation.bonds}%`"
                  ></div>
                  <div
                    class="h-full bg-blue-500"
                    :style="{ width: riskData.allocation.cash + '%' }"
                    :title="`Cash: ${riskData.allocation.cash}%`"
                  ></div>
                </div>
                <span class="text-sm text-gray-500 whitespace-nowrap">
                  {{ riskData.years_to_goal }}y remaining
                </span>
              </div>
              <div class="flex gap-4 mt-2 text-xs text-gray-500">
                <span class="flex items-center gap-1">
                  <span class="w-2 h-2 bg-blue-500 rounded"></span>
                  Equities {{ riskData.allocation.equities }}%
                </span>
                <span class="flex items-center gap-1">
                  <span class="w-2 h-2 bg-green-500 rounded"></span>
                  Bonds {{ riskData.allocation.bonds }}%
                </span>
                <span class="flex items-center gap-1">
                  <span class="w-2 h-2 bg-blue-500 rounded"></span>
                  Cash {{ riskData.allocation.cash }}%
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'GoalsAnalysis',
  mixins: [currencyMixin],

  props: {
    analysis: {
      type: Object,
      default: null,
    },
    loading: {
      type: Boolean,
      default: false,
    },
  },

  emits: ['refresh'],

  methods: {
    getModuleIcon(module) {
      const icons = {
        savings: '💰',
        investment: '📈',
        property: '🏠',
        retirement: '☀️',
      };
      return icons[module] || '🎯';
    },

    getAffordabilityClass(category) {
      const classes = {
        comfortable: 'bg-green-100 text-green-700',
        moderate: 'bg-blue-100 text-blue-700',
        challenging: 'bg-blue-100 text-blue-700',
        stretch: 'bg-red-100 text-red-700',
      };
      return classes[category] || 'bg-gray-100 text-gray-700';
    },

    getAffordabilityIcon(category) {
      const icons = {
        comfortable: '✅',
        moderate: '👍',
        challenging: '⚠️',
        stretch: '🔴',
      };
      return icons[category] || '❓';
    },

    getRecommendationIcon(type) {
      const icons = {
        increase_contribution: '💸',
        adjust_timeline: '📅',
        reprioritize: '🎯',
        risk_adjustment: '⚖️',
        celebrate: '🎉',
        default: '💡',
      };
      return icons[type] || icons.default;
    },

    getRecommendationBorderClass(priority) {
      const classes = {
        high: 'border-red-200',
        medium: 'border-blue-200',
        low: 'border-gray-200',
      };
      return classes[priority] || 'border-gray-200';
    },

    getPriorityClass(priority) {
      const classes = {
        high: 'bg-red-100 text-red-700',
        medium: 'bg-blue-100 text-blue-700',
        low: 'bg-gray-100 text-gray-700',
      };
      return classes[priority] || 'bg-gray-100 text-gray-700';
    },
  },
};
</script>
