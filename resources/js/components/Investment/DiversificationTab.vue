<template>
  <div class="diversification-tab">
    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
      <span class="ml-3 text-gray-600">Analysing diversification...</span>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-gray-50 rounded-lg p-6 text-center">
      <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
      </svg>
      <p class="text-gray-600 font-medium">{{ error }}</p>
      <button @click="loadData" class="mt-4 px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition">
        Try Again
      </button>
    </div>

    <!-- Empty State (No Holdings) -->
    <div v-else-if="data && !data.has_holdings" class="bg-blue-50 rounded-lg p-8 text-center border border-blue-200">
      <svg class="w-16 h-16 text-blue-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
      </svg>
      <h3 class="text-lg font-medium text-blue-800 mb-2">No Holdings Recorded</h3>
      <p class="text-blue-600 mb-4">Add holdings to this {{ accountType === 'pension' ? 'pension' : 'account' }} to see diversification analysis.</p>
      <button v-if="showAddHoldings" v-preview-disabled="'add'" @click="$emit('add-holdings')" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
        Add Holdings
      </button>
    </div>

    <!-- Diversification Analysis -->
    <div v-else-if="data && data.has_holdings" class="space-y-6">
      <!-- Risk Profile Banner -->
      <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg p-4 border border-blue-100">
        <div class="flex items-center justify-between flex-wrap gap-4">
          <div class="flex items-center space-x-6">
            <div>
              <p class="text-xs text-blue-600 uppercase tracking-wide font-medium">User Risk Profile</p>
              <p class="text-lg font-semibold text-blue-800">{{ getRiskLabel(data.risk_profile.user_level) }}</p>
            </div>
            <div v-if="data.risk_profile.using_custom" class="border-l border-blue-200 pl-6">
              <p class="text-xs text-indigo-600 uppercase tracking-wide font-medium">{{ accountType === 'pension' ? 'Pension' : 'Account' }} Override</p>
              <p class="text-lg font-semibold text-indigo-800">{{ getRiskLabel(data.risk_profile.account_level) }}</p>
            </div>
          </div>
          <div v-if="data.risk_profile.using_custom" class="bg-indigo-100 text-indigo-700 px-3 py-1 rounded-full text-sm font-medium">
            Using Custom Risk Level
          </div>
        </div>
      </div>

      <!-- Score and HHI Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Diversification Score Card -->
        <div class="bg-white rounded-lg shadow-md p-6">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900">Diversification Score</h3>
            <div class="relative group">
              <svg class="w-5 h-5 text-gray-400 cursor-help" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <div class="hidden group-hover:block absolute right-0 top-6 w-64 bg-gray-800 text-white text-sm p-3 rounded-lg shadow-lg z-10">
                Score based on concentration, number of holdings, and asset class diversity. Higher is better.
              </div>
            </div>
          </div>
          <div class="flex items-end space-x-4">
            <div class="text-5xl font-bold" :class="getScoreColor(data.diversification_score)">
              {{ data.diversification_score }}
            </div>
            <div class="text-gray-500 text-lg mb-1">/100</div>
          </div>
          <div class="mt-3">
            <span class="px-3 py-1 rounded-full text-sm font-medium" :class="getScoreBadge(data.diversification_label)">
              {{ data.diversification_label }}
            </span>
          </div>
          <!-- Score Bar -->
          <div class="mt-4 bg-gray-200 rounded-full h-3 overflow-hidden">
            <div
              class="h-full rounded-full transition-all duration-500"
              :class="getScoreBarColor(data.diversification_score)"
              :style="{ width: `${data.diversification_score}%` }"
            ></div>
          </div>
        </div>

        <!-- HHI Indicator Card -->
        <div class="bg-white rounded-lg shadow-md p-6">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900">Concentration (HHI)</h3>
            <div class="relative group">
              <svg class="w-5 h-5 text-gray-400 cursor-help" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <div class="hidden group-hover:block absolute right-0 top-6 w-64 bg-gray-800 text-white text-sm p-3 rounded-lg shadow-lg z-10">
                Herfindahl-Hirschman Index: 0 = highly diversified, 1 = single holding. Lower is better.
              </div>
            </div>
          </div>
          <div class="flex items-end space-x-4">
            <div class="text-5xl font-bold" :class="getHHIColor(data.hhi)">
              {{ data.hhi.toFixed(2) }}
            </div>
          </div>
          <div class="mt-3">
            <span class="px-3 py-1 rounded-full text-sm font-medium" :class="getHHIBadge(data.hhi_label)">
              {{ data.hhi_label }}
            </span>
          </div>
          <!-- HHI Bar (inverted - lower is better) -->
          <div class="mt-4 bg-gray-200 rounded-full h-3 overflow-hidden">
            <div
              class="h-full rounded-full transition-all duration-500"
              :class="getHHIBarColor(data.hhi)"
              :style="{ width: `${Math.min(data.hhi * 100, 100)}%` }"
            ></div>
          </div>
          <p class="text-xs text-gray-500 mt-2">{{ data.holdings_count }} holdings</p>
        </div>
      </div>

      <!-- Asset Allocation Comparison -->
      <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Asset Allocation vs Target</h3>
        <div class="space-y-4">
          <div v-for="(classData, className) in data.asset_class_breakdown" :key="className" class="relative">
            <div class="flex items-center justify-between mb-1">
              <span class="text-sm font-medium text-gray-700 capitalize">{{ className }}</span>
              <div class="flex items-center space-x-4 text-sm">
                <span class="text-gray-600">Current: {{ classData.current.toFixed(1) }}%</span>
                <span class="text-gray-400">|</span>
                <span class="text-gray-600">Target: {{ classData.target.toFixed(1) }}%</span>
                <span
                  class="font-medium"
                  :class="getDeviationColor(classData.deviation)"
                >
                  {{ classData.deviation > 0 ? '+' : '' }}{{ classData.deviation.toFixed(1) }}%
                </span>
              </div>
            </div>
            <!-- Dual Progress Bar -->
            <div class="relative h-6 bg-gray-100 rounded-lg overflow-hidden">
              <!-- Current Allocation Bar -->
              <div
                class="absolute top-0 left-0 h-full transition-all duration-500"
                :class="getAssetClassBarColor(className)"
                :style="{ width: `${Math.min(classData.current, 100)}%` }"
              ></div>
              <!-- Target Marker -->
              <div
                v-if="classData.target > 0"
                class="absolute top-0 w-1 h-full bg-gray-700 opacity-60"
                :style="{ left: `${Math.min(classData.target, 100)}%` }"
              ></div>
            </div>
            <!-- Severity Badge -->
            <div class="mt-1 flex justify-end">
              <span
                v-if="classData.severity !== 'aligned'"
                class="text-xs px-2 py-0.5 rounded-full"
                :class="getSeverityBadge(classData.severity)"
              >
                {{ classData.severity === 'minor' ? 'Minor Deviation' : 'Significant Deviation' }}
              </span>
              <span v-else class="text-xs text-green-600">Aligned</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Concentration Metrics -->
      <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Concentration Metrics</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div class="bg-gray-50 rounded-lg p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Top Holding</p>
            <p class="text-xl font-bold text-gray-900 mt-1">{{ data.concentration.top_holding_percent.toFixed(1) }}%</p>
          </div>
          <div class="bg-gray-50 rounded-lg p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Top 3 Holdings</p>
            <p class="text-xl font-bold text-gray-900 mt-1">{{ data.concentration.top_3_holdings_percent.toFixed(1) }}%</p>
          </div>
          <div class="bg-gray-50 rounded-lg p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Holdings &gt;10%</p>
            <p class="text-xl font-bold text-gray-900 mt-1">{{ data.concentration.holdings_over_10_percent }}</p>
          </div>
          <div class="bg-gray-50 rounded-lg p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Holdings &gt;5%</p>
            <p class="text-xl font-bold text-gray-900 mt-1">{{ data.concentration.holdings_over_5_percent }}</p>
          </div>
        </div>

        <!-- Concentration Warnings -->
        <div v-if="data.concentration_warnings && data.concentration_warnings.length > 0" class="mt-4 space-y-2">
          <div
            v-for="(warning, index) in data.concentration_warnings"
            :key="index"
            class="flex items-start space-x-2 p-3 rounded-lg"
            :class="warning.type === 'warning' ? 'bg-gray-50' : 'bg-gray-50'"
          >
            <svg v-if="warning.type === 'warning'" class="w-5 h-5 text-violet-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <svg v-else class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="text-sm" :class="warning.type === 'warning' ? 'text-violet-700' : 'text-blue-700'">{{ warning.message }}</p>
          </div>
        </div>
      </div>

      <!-- Recommendations -->
      <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Recommendations</h3>
        <div class="space-y-3">
          <div
            v-for="(rec, index) in data.recommendations"
            :key="index"
            class="flex items-start space-x-3 p-4 rounded-lg"
            :class="getRecBg(rec.type)"
          >
            <svg v-if="rec.type === 'success'" class="w-6 h-6 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <svg v-else-if="rec.type === 'warning'" class="w-6 h-6 text-violet-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <svg v-else class="w-6 h-6 text-blue-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="text-sm" :class="getRecText(rec.type)">{{ rec.message }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import diversificationService from '@/services/diversificationService';

export default {
  name: 'DiversificationTab',

  props: {
    accountId: {
      type: [Number, String],
      required: true,
    },
    accountType: {
      type: String,
      default: 'investment',
      validator: (value) => ['investment', 'pension'].includes(value),
    },
    showAddHoldings: {
      type: Boolean,
      default: true,
    },
  },

  emits: ['add-holdings'],

  data() {
    return {
      loading: false,
      error: null,
      data: null,
    };
  },

  mounted() {
    this.loadData();
  },

  watch: {
    accountId() {
      this.loadData();
    },
  },

  methods: {
    async loadData() {
      this.loading = true;
      this.error = null;

      try {
        const response = this.accountType === 'pension'
          ? await diversificationService.getPensionDiversification(this.accountId)
          : await diversificationService.getAccountDiversification(this.accountId);

        if (response.success) {
          this.data = response.data;
        } else {
          this.error = response.message || 'Failed to load diversification data';
        }
      } catch (err) {
        this.error = err.response?.data?.message || 'Failed to load diversification data';
      } finally {
        this.loading = false;
      }
    },

    getRiskLabel(level) {
      const labels = {
        1: 'Very Conservative',
        2: 'Conservative',
        3: 'Moderate',
        4: 'Growth',
        5: 'Aggressive',
      };
      return labels[level] || 'Unknown';
    },

    getScoreColor(score) {
      if (score >= 80) return 'text-green-600';
      if (score >= 60) return 'text-blue-600';
      if (score >= 40) return 'text-violet-600';
      return 'text-gray-600';
    },

    getScoreBadge(label) {
      const classes = {
        'Excellent': 'bg-green-500 text-white',
        'Good': 'bg-blue-500 text-white',
        'Fair': 'bg-violet-500 text-white',
        'Poor': 'bg-gray-500 text-white',
      };
      return classes[label] || 'bg-gray-500 text-white';
    },

    getScoreBarColor(score) {
      if (score >= 80) return 'bg-green-500';
      if (score >= 60) return 'bg-blue-500';
      if (score >= 40) return 'bg-violet-500';
      return 'bg-gray-500';
    },

    getHHIColor(hhi) {
      if (hhi < 0.15) return 'text-green-600';
      if (hhi <= 0.25) return 'text-blue-600';
      return 'text-violet-600';
    },

    getHHIBadge(label) {
      const classes = {
        'Well Diversified': 'bg-green-500 text-white',
        'Moderate Concentration': 'bg-blue-500 text-white',
        'High Concentration': 'bg-violet-500 text-white',
      };
      return classes[label] || 'bg-gray-500 text-white';
    },

    getHHIBarColor(hhi) {
      if (hhi < 0.15) return 'bg-green-500';
      if (hhi <= 0.25) return 'bg-blue-500';
      return 'bg-violet-500';
    },

    getDeviationColor(deviation) {
      const absDeviation = Math.abs(deviation);
      if (absDeviation < 5) return 'text-green-600';
      if (absDeviation <= 10) return 'text-blue-600';
      return 'text-violet-600';
    },

    getAssetClassBarColor(className) {
      const colors = {
        equities: 'bg-blue-500',
        bonds: 'bg-green-500',
        cash: 'bg-gray-400',
        alternatives: 'bg-purple-500',
      };
      return colors[className] || 'bg-gray-400';
    },

    getSeverityBadge(severity) {
      if (severity === 'minor') return 'bg-blue-500 text-white';
      if (severity === 'significant') return 'bg-violet-500 text-white';
      return 'bg-green-500 text-white';
    },

    getRecBg(type) {
      const bgs = {
        success: 'bg-gray-50',
        warning: 'bg-gray-50',
        info: 'bg-gray-50',
      };
      return bgs[type] || 'bg-gray-50';
    },

    getRecText(type) {
      const texts = {
        success: 'text-green-700',
        warning: 'text-violet-700',
        info: 'text-blue-700',
      };
      return texts[type] || 'text-gray-700';
    },
  },
};
</script>

<style scoped>
.diversification-tab {
  width: 100%;
}
</style>
