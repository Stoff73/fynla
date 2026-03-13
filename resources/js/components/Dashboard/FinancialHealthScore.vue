<template>
  <div class="financial-health-score bg-white rounded-lg shadow-md p-6">
    <div class="flex justify-between items-start mb-4">
      <h3 class="text-xl font-semibold text-horizon-500">Financial Health Overview</h3>
      <button
        @click="showDetails = !showDetails"
        class="text-sm text-violet-600 hover:text-violet-700"
      >
        {{ showDetails ? 'Hide' : 'View' }} Details
      </button>
    </div>

    <!-- Overall Status Badge -->
    <div class="text-center mb-6">
      <div class="inline-flex items-center justify-center w-20 h-20 rounded-full mb-3" :class="overallBgClass">
        <svg v-if="compositeScore >= 80" class="w-10 h-10 text-spring-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <svg v-else-if="compositeScore >= 60" class="w-10 h-10 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <svg v-else class="w-10 h-10 text-raspberry-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
      </div>
      <span
        class="inline-block px-4 py-2 rounded-full text-sm font-semibold"
        :class="scoreBadgeClass"
      >
        {{ scoreLabel }}
      </span>
    </div>

    <!-- Breakdown Details (Expandable) -->
    <div v-if="showDetails" class="mt-6 space-y-3 border-t pt-4">
      <h4 class="text-sm font-semibold text-horizon-500 mb-3">Area Breakdown</h4>

      <!-- Protection -->
      <div class="flex items-center justify-between p-3 bg-eggshell-500 rounded-lg">
        <div class="flex items-center space-x-3">
          <div class="w-2 h-2 rounded-full" :class="getDotClass(protectionScore)"></div>
          <span class="text-sm text-horizon-500">Protection Coverage</span>
        </div>
        <span class="text-sm font-semibold" :class="getScoreClass(protectionScore)">
          {{ getStatusLabel(protectionScore) }}
        </span>
      </div>

      <!-- Emergency Fund -->
      <div class="flex items-center justify-between p-3 bg-eggshell-500 rounded-lg">
        <div class="flex items-center space-x-3">
          <div class="w-2 h-2 rounded-full" :class="getDotClass(emergencyFundScore)"></div>
          <span class="text-sm text-horizon-500">Emergency Fund</span>
        </div>
        <span class="text-sm font-semibold" :class="getScoreClass(emergencyFundScore)">
          {{ emergencyFundMonths }}
        </span>
      </div>

      <!-- Retirement Readiness -->
      <div class="flex items-center justify-between p-3 bg-eggshell-500 rounded-lg">
        <div class="flex items-center space-x-3">
          <div class="w-2 h-2 rounded-full" :class="getDotClass(retirementScore)"></div>
          <span class="text-sm text-horizon-500">Retirement Readiness</span>
        </div>
        <span class="text-sm font-semibold" :class="getScoreClass(retirementScore)">
          {{ getStatusLabel(retirementScore) }}
        </span>
      </div>

      <!-- Investment Diversification -->
      <div class="flex items-center justify-between p-3 bg-eggshell-500 rounded-lg">
        <div class="flex items-center space-x-3">
          <div class="w-2 h-2 rounded-full" :class="getDotClass(investmentScore)"></div>
          <span class="text-sm text-horizon-500">Investment Diversification</span>
        </div>
        <span class="text-sm font-semibold" :class="getScoreClass(investmentScore)">
          {{ getStatusLabel(investmentScore) }}
        </span>
      </div>

      <!-- Estate Planning -->
      <div class="flex items-center justify-between p-3 bg-eggshell-500 rounded-lg">
        <div class="flex items-center space-x-3">
          <div class="w-2 h-2 rounded-full" :class="getDotClass(estateScore)"></div>
          <span class="text-sm text-horizon-500">Estate Planning</span>
        </div>
        <span class="text-sm font-semibold" :class="getScoreClass(estateScore)">
          {{ getStatusLabel(estateScore) }}
        </span>
      </div>
    </div>

    <!-- Recommendation Summary -->
    <div v-if="!showDetails" class="mt-4 text-sm text-neutral-500 text-center">
      {{ recommendation }}
    </div>
  </div>
</template>

<script>
import { mapGetters } from 'vuex';

export default {
  name: 'FinancialHealthScore',

  data() {
    return {
      showDetails: false,
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

    protectionScore() {
      return this.protectionAdequacyScore || 0;
    },

    emergencyFundScore() {
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

    emergencyFundMonths() {
      const runway = this.emergencyFundRunway || 0;
      if (runway >= 6) return `${Math.round(runway)} months runway`;
      if (runway >= 3) return `${Math.round(runway)} months runway`;
      if (runway > 0) return `${Math.round(runway)} month${runway >= 1.5 ? 's' : ''} runway`;
      return 'No emergency fund';
    },

    compositeScore() {
      return (
        this.protectionScore * 0.20 +
        this.emergencyFundScore * 0.15 +
        this.retirementScore * 0.25 +
        this.investmentScore * 0.20 +
        this.estateScore * 0.20
      );
    },

    overallBgClass() {
      if (this.compositeScore >= 80) return 'bg-spring-100';
      if (this.compositeScore >= 60) return 'bg-violet-100';
      return 'bg-raspberry-100';
    },

    scoreBadgeClass() {
      if (this.compositeScore >= 80) return 'bg-spring-100 text-spring-800';
      if (this.compositeScore >= 60) return 'bg-violet-100 text-violet-800';
      return 'bg-raspberry-100 text-raspberry-800';
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
    getStatusLabel(score) {
      if (score >= 80) return 'Good';
      if (score >= 60) return 'Fair';
      if (score >= 30) return 'Needs attention';
      return 'Needs attention';
    },

    getScoreClass(score) {
      if (score >= 80) return 'text-spring-600';
      if (score >= 60) return 'text-violet-600';
      return 'text-raspberry-600';
    },

    getDotClass(score) {
      if (score >= 80) return 'bg-spring-500';
      if (score >= 60) return 'bg-violet-500';
      return 'bg-raspberry-500';
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
