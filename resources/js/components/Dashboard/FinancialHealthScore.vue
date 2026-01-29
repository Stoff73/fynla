<template>
  <div class="financial-health-score bg-white rounded-lg shadow-md p-6">
    <div class="flex justify-between items-start mb-4">
      <h3 class="text-xl font-semibold text-gray-800">Financial Health Score</h3>
      <button
        @click="showDetails = !showDetails"
        class="text-sm text-blue-600 hover:text-blue-700"
      >
        {{ showDetails ? 'Hide' : 'View' }} Details
      </button>
    </div>

    <!-- Radial Gauge -->
    <div class="flex justify-center mb-6">
      <div class="relative w-48 h-48">
        <!-- SVG Radial Gauge -->
        <svg class="w-full h-full transform -rotate-90" viewBox="0 0 200 200">
          <!-- Background circle -->
          <circle
            cx="100"
            cy="100"
            r="85"
            fill="none"
            class="stroke-gray-200"
            stroke-width="20"
          />
          <!-- Progress circle -->
          <circle
            cx="100"
            cy="100"
            r="85"
            fill="none"
            :stroke="gaugeColour"
            stroke-width="20"
            stroke-linecap="round"
            :stroke-dasharray="circumference"
            :stroke-dashoffset="dashOffset"
            class="transition-all duration-1000 ease-out"
          />
        </svg>
        <!-- Score text -->
        <div class="absolute inset-0 flex flex-col items-center justify-center">
          <span class="text-4xl font-bold" :class="scoreTextClass">
            {{ Math.round(compositeScore) }}
          </span>
          <span class="text-sm text-gray-600">out of 100</span>
        </div>
      </div>
    </div>

    <!-- Score Label -->
    <div class="text-center mb-6">
      <span
        class="inline-block px-4 py-2 rounded-full text-sm font-semibold"
        :class="scoreBadgeClass"
      >
        {{ scoreLabel }}
      </span>
    </div>

    <!-- Breakdown Details (Expandable) -->
    <div v-if="showDetails" class="mt-6 space-y-3 border-t pt-4">
      <h4 class="text-sm font-semibold text-gray-700 mb-3">Score Breakdown</h4>

      <!-- Protection -->
      <div class="space-y-1">
        <div class="flex justify-between items-center text-sm">
          <span class="text-gray-700">Protection Coverage</span>
          <span class="font-semibold" :class="getScoreClass(protectionScore)">
            {{ Math.round(protectionScore) }}/100
          </span>
        </div>
        <div class="flex items-center text-xs text-gray-500">
          <span>Weight: 20%</span>
          <span class="mx-2">•</span>
          <span>Contribution: {{ Math.round(protectionContribution) }} pts</span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-2">
          <div
            class="h-2 rounded-full"
            :class="getBarClass(protectionScore)"
            :style="{ width: protectionScore + '%' }"
          ></div>
        </div>
      </div>

      <!-- Emergency Fund -->
      <div class="space-y-1">
        <div class="flex justify-between items-center text-sm">
          <span class="text-gray-700">Emergency Fund</span>
          <span class="font-semibold" :class="getScoreClass(emergencyFundScore)">
            {{ Math.round(emergencyFundScore) }}/100
          </span>
        </div>
        <div class="flex items-center text-xs text-gray-500">
          <span>Weight: 15%</span>
          <span class="mx-2">•</span>
          <span>Contribution: {{ Math.round(emergencyFundContribution) }} pts</span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-2">
          <div
            class="h-2 rounded-full"
            :class="getBarClass(emergencyFundScore)"
            :style="{ width: emergencyFundScore + '%' }"
          ></div>
        </div>
      </div>

      <!-- Retirement Readiness -->
      <div class="space-y-1">
        <div class="flex justify-between items-center text-sm">
          <span class="text-gray-700">Retirement Readiness</span>
          <span class="font-semibold" :class="getScoreClass(retirementScore)">
            {{ Math.round(retirementScore) }}/100
          </span>
        </div>
        <div class="flex items-center text-xs text-gray-500">
          <span>Weight: 25%</span>
          <span class="mx-2">•</span>
          <span>Contribution: {{ Math.round(retirementContribution) }} pts</span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-2">
          <div
            class="h-2 rounded-full"
            :class="getBarClass(retirementScore)"
            :style="{ width: retirementScore + '%' }"
          ></div>
        </div>
      </div>

      <!-- Investment Diversification -->
      <div class="space-y-1">
        <div class="flex justify-between items-center text-sm">
          <span class="text-gray-700">Investment Diversification</span>
          <span class="font-semibold" :class="getScoreClass(investmentScore)">
            {{ Math.round(investmentScore) }}/100
          </span>
        </div>
        <div class="flex items-center text-xs text-gray-500">
          <span>Weight: 20%</span>
          <span class="mx-2">•</span>
          <span>Contribution: {{ Math.round(investmentContribution) }} pts</span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-2">
          <div
            class="h-2 rounded-full"
            :class="getBarClass(investmentScore)"
            :style="{ width: investmentScore + '%' }"
          ></div>
        </div>
      </div>

      <!-- Estate Planning -->
      <div class="space-y-1">
        <div class="flex justify-between items-center text-sm">
          <span class="text-gray-700">Estate Planning</span>
          <span class="font-semibold" :class="getScoreClass(estateScore)">
            {{ Math.round(estateScore) }}/100
          </span>
        </div>
        <div class="flex items-center text-xs text-gray-500">
          <span>Weight: 20%</span>
          <span class="mx-2">•</span>
          <span>Contribution: {{ Math.round(estateContribution) }} pts</span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-2">
          <div
            class="h-2 rounded-full"
            :class="getBarClass(estateScore)"
            :style="{ width: estateScore + '%' }"
          ></div>
        </div>
      </div>
    </div>

    <!-- Recommendation Summary -->
    <div v-if="!showDetails" class="mt-4 text-sm text-gray-600 text-center">
      {{ recommendation }}
    </div>
  </div>
</template>

<script>
import { mapGetters } from 'vuex';
import { SUCCESS_COLORS, WARNING_COLORS, ERROR_COLORS, BORDER_COLORS, getColorByThreshold } from '@/constants/designSystem';

export default {
  name: 'FinancialHealthScore',

  data() {
    return {
      showDetails: false,
      circumference: 2 * Math.PI * 85, // 2πr where r=85
    };
  },

  computed: {
    ...mapGetters('protection', {
      protectionAdequacyScore: 'adequacyScore',
    }),
    ...mapGetters('savings', {
      emergencyFundRunway: 'emergencyFundRunway',
    }),
    ...mapGetters('retirement', {
      retirementReadinessScore: 'retirementReadinessScore',
    }),
    ...mapGetters('investment', {
      investmentDiversificationScore: 'diversificationScore',
    }),
    ...mapGetters('estate', {
      estateProbateReadiness: 'probateReadiness',
    }),

    // Individual module scores (0-100)
    protectionScore() {
      return this.protectionAdequacyScore || 0;
    },

    emergencyFundScore() {
      // Convert runway (months) to score (0-100)
      // Target: 6 months = 100%
      const runway = this.emergencyFundRunway || 0;
      return Math.min(100, (runway / 6) * 100);
    },

    retirementScore() {
      return this.retirementReadinessScore || 0;
    },

    investmentScore() {
      return this.investmentDiversificationScore || 0;
    },

    estateScore() {
      return this.estateProbateReadiness || 0;
    },

    // Weighted contributions
    protectionContribution() {
      return this.protectionScore * 0.20;
    },

    emergencyFundContribution() {
      return this.emergencyFundScore * 0.15;
    },

    retirementContribution() {
      return this.retirementScore * 0.25;
    },

    investmentContribution() {
      return this.investmentScore * 0.20;
    },

    estateContribution() {
      return this.estateScore * 0.20;
    },

    // Composite score
    compositeScore() {
      return (
        this.protectionContribution +
        this.emergencyFundContribution +
        this.retirementContribution +
        this.investmentContribution +
        this.estateContribution
      );
    },

    // Gauge visualization
    dashOffset() {
      const progress = this.compositeScore / 100;
      return this.circumference * (1 - progress);
    },

    gaugeColour() {
      // Use design system color helper for threshold-based coloring
      return getColorByThreshold(this.compositeScore, { success: 80, warning: 60 });
    },

    scoreTextClass() {
      if (this.compositeScore >= 80) return 'text-green-600';
      if (this.compositeScore >= 60) return 'text-orange-600';
      return 'text-red-600';
    },

    scoreBadgeClass() {
      if (this.compositeScore >= 80) return 'bg-green-100 text-green-800';
      if (this.compositeScore >= 60) return 'bg-orange-100 text-orange-800';
      return 'bg-red-100 text-red-800';
    },

    scoreLabel() {
      if (this.compositeScore >= 80) return 'Excellent Financial Health';
      if (this.compositeScore >= 60) return 'Good Financial Health';
      if (this.compositeScore >= 40) return 'Fair Financial Health';
      return 'Needs Improvement';
    },

    recommendation() {
      if (this.compositeScore >= 80) {
        return 'Your finances are in great shape. Keep up the good work!';
      } else if (this.compositeScore >= 60) {
        return 'Your finances are on track with some room for improvement.';
      } else {
        return 'Consider addressing key areas to improve your financial health.';
      }
    },
  },

  methods: {
    getScoreClass(score) {
      if (score >= 80) return 'text-green-600';
      if (score >= 60) return 'text-orange-600';
      return 'text-red-600';
    },

    getBarClass(score) {
      if (score >= 80) return 'bg-green-600';
      if (score >= 60) return 'bg-orange-500';
      return 'bg-red-600';
    },
  },
};
</script>

<style scoped>
.financial-health-score {
  min-width: 280px;
  max-width: 100%;
}

@media (min-width: 640px) {
  .financial-health-score {
    min-width: 320px;
  }
}

@media (min-width: 1024px) {
  .financial-health-score {
    min-width: 400px;
  }
}
</style>
