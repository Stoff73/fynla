<template>
  <div class="risk-analysis-section">
    <h4 class="text-md font-semibold text-horizon-500 mb-4">Risk Analysis</h4>

    <div v-if="!data" class="text-center py-8 text-neutral-500">
      <p>No risk analysis data available</p>
    </div>

    <div v-else class="space-y-6">
      <!-- Risk Score Overview -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white border border-light-gray rounded-lg p-5">
          <h5 class="text-sm font-semibold text-neutral-500 mb-3">Current Risk Score</h5>
          <div class="flex items-center justify-center mb-2">
            <div class="text-4xl font-bold" :class="getRiskScoreColour(data.current_risk_score)">
              {{ data.current_risk_score || 0 }}<span class="text-2xl text-neutral-500">/10</span>
            </div>
          </div>
          <p class="text-center text-sm text-neutral-500">{{ getRiskScoreLabel(data.current_risk_score) }}</p>
        </div>

        <div class="bg-white border border-light-gray rounded-lg p-5">
          <h5 class="text-sm font-semibold text-neutral-500 mb-3">Target Risk Score</h5>
          <div class="flex items-center justify-center mb-2">
            <div class="text-4xl font-bold text-violet-600">
              {{ data.target_risk_score || 0 }}<span class="text-2xl text-neutral-500">/10</span>
            </div>
          </div>
          <p class="text-center text-sm text-neutral-500">{{ getRiskScoreLabel(data.target_risk_score) }}</p>
        </div>

        <div class="bg-white border border-light-gray rounded-lg p-5">
          <h5 class="text-sm font-semibold text-neutral-500 mb-3">Risk Alignment</h5>
          <div class="flex items-center justify-center mb-2">
            <div class="text-4xl font-bold" :class="getAlignmentColour(data.risk_alignment)">
              {{ formatPercentage(data.risk_alignment || 0) }}<span class="text-2xl">%</span>
            </div>
          </div>
          <p class="text-center text-sm text-neutral-500">{{ getAlignmentLabel(data.risk_alignment) }}</p>
        </div>
      </div>

      <!-- Risk Metrics -->
      <div class="bg-white border border-light-gray rounded-lg p-5">
        <h5 class="text-sm font-semibold text-neutral-500 mb-4">Portfolio Risk Metrics</h5>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
          <div>
            <p class="text-xs text-neutral-500 mb-1">Volatility (Annual)</p>
            <p class="text-lg font-semibold text-horizon-500">{{ formatPercentage(data.volatility || 0) }}%</p>
            <p class="text-xs text-neutral-500 mt-1">Standard deviation</p>
          </div>
          <div>
            <p class="text-xs text-neutral-500 mb-1">Sharpe Ratio</p>
            <p class="text-lg font-semibold text-horizon-500">{{ formatDecimal(data.sharpe_ratio || 0) }}</p>
            <p class="text-xs text-neutral-500 mt-1">Risk-adjusted return</p>
          </div>
          <div>
            <p class="text-xs text-neutral-500 mb-1">Max Drawdown</p>
            <p class="text-lg font-semibold text-raspberry-600">{{ formatPercentage(Math.abs(data.max_drawdown || 0)) }}%</p>
            <p class="text-xs text-neutral-500 mt-1">Largest decline</p>
          </div>
          <div>
            <p class="text-xs text-neutral-500 mb-1">Value at Risk (95%)</p>
            <p class="text-lg font-semibold text-horizon-500">{{ formatPercentage(Math.abs(data.value_at_risk || 0)) }}%</p>
            <p class="text-xs text-neutral-500 mt-1">1-year horizon</p>
          </div>
        </div>
      </div>

      <!-- Risk Recommendations -->
      <div v-if="data.recommendations && data.recommendations.length > 0" class="bg-white border border-light-gray rounded-lg p-5">
        <h5 class="text-sm font-semibold text-neutral-500 mb-4">Risk Management Recommendations</h5>
        <div class="space-y-3">
          <div
            v-for="(rec, index) in data.recommendations"
            :key="index"
            class="p-4 rounded-md border"
            :class="getRecommendationClass(rec.priority)"
          >
            <div class="flex items-start">
              <svg class="w-5 h-5 mr-3 flex-shrink-0 mt-0.5" :class="getRecommendationIconColour(rec.priority)" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
              </svg>
              <div class="flex-1">
                <p class="text-sm font-medium text-horizon-500 mb-1">{{ rec.title }}</p>
                <p class="text-sm text-neutral-500">{{ rec.description }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Risk Tolerance Assessment -->
      <div v-if="data.risk_tolerance" class="bg-eggshell-500 rounded-lg p-5">
        <h5 class="text-sm font-semibold text-neutral-500 mb-3">Risk Tolerance Profile</h5>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <p class="text-sm text-neutral-500 mb-2"><strong>Time Horizon:</strong> {{ data.risk_tolerance.time_horizon || 'Not specified' }}</p>
            <p class="text-sm text-neutral-500 mb-2"><strong>Risk Capacity:</strong> {{ data.risk_tolerance.capacity || 'Not assessed' }}</p>
            <p class="text-sm text-neutral-500"><strong>Loss Tolerance:</strong> {{ data.risk_tolerance.loss_tolerance || 'Not specified' }}</p>
          </div>
          <div>
            <p class="text-sm text-neutral-500 mb-2"><strong>Investment Experience:</strong> {{ data.risk_tolerance.experience || 'Not specified' }}</p>
            <p class="text-sm text-neutral-500 mb-2"><strong>Risk Attitude:</strong> {{ data.risk_tolerance.attitude || 'Not assessed' }}</p>
            <p class="text-sm text-neutral-500"><strong>Financial Cushion:</strong> {{ data.risk_tolerance.cushion || 'Not specified' }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'RiskAnalysisSection',

  props: {
    data: {
      type: Object,
      default: null,
    },
  },

  methods: {
    formatPercentage(value) {
      if (value === null || value === undefined) return '0.0';
      return value.toFixed(1);
    },

    formatDecimal(value) {
      if (value === null || value === undefined) return '0.00';
      return value.toFixed(2);
    },

    getRiskScoreColour(score) {
      if (score <= 3) return 'text-spring-600';
      if (score <= 5) return 'text-violet-600';
      if (score <= 7) return 'text-violet-600';
      return 'text-raspberry-600';
    },

    getRiskScoreLabel(score) {
      if (score <= 2) return 'Very Low Risk';
      if (score <= 4) return 'Low Risk';
      if (score <= 6) return 'Medium Risk';
      if (score <= 8) return 'High Risk';
      return 'Very High Risk';
    },

    getAlignmentColour(alignment) {
      if (alignment >= 90) return 'text-spring-600';
      if (alignment >= 75) return 'text-violet-600';
      if (alignment >= 60) return 'text-violet-600';
      return 'text-raspberry-600';
    },

    getAlignmentLabel(alignment) {
      if (alignment >= 90) return 'Excellent alignment';
      if (alignment >= 75) return 'Good alignment';
      if (alignment >= 60) return 'Fair alignment';
      return 'Needs adjustment';
    },

    getRecommendationClass(priority) {
      const classes = {
        high: 'bg-eggshell-500',
        medium: 'bg-eggshell-500',
        low: 'bg-eggshell-500',
      };
      return classes[priority] || 'bg-eggshell-500 border-light-gray';
    },

    getRecommendationIconColour(priority) {
      const colours = {
        high: 'text-raspberry-600',
        medium: 'text-violet-600',
        low: 'text-violet-600',
      };
      return colours[priority] || 'text-neutral-500';
    },
  },
};
</script>

<style scoped>
/* Component-specific styles */
</style>
