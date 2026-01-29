<template>
  <div class="strategies-tab">
    <!-- Back Button -->
    <button
      @click="$emit('back')"
      class="back-button"
    >
      <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
      </svg>
      Back to Pensions
    </button>

    <!-- Loading State -->
    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <p>Analysing your retirement position...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="error-state">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="error-icon">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
      </svg>
      <p>{{ error }}</p>
      <button class="retry-button" @click="fetchStrategies">Try Again</button>
    </div>

    <!-- Requires DOB -->
    <div v-else-if="requiresDob" class="dob-required">
      <div class="dob-icon">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
        </svg>
      </div>
      <h3>Date of Birth Required</h3>
      <p class="dob-message">Please enter your date of birth in your profile to calculate pension strategies.</p>
      <p class="dob-subtitle">Your date of birth is needed to calculate years to retirement and project investment growth.</p>
    </div>

    <!-- On Track Banner -->
    <div v-else-if="isOnTrack" class="on-track-banner">
      <div class="on-track-icon">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      </div>
      <h3>You're On Track!</h3>
      <p class="probability">{{ currentProbability }}% probability of achieving your retirement goals</p>
      <p class="subtitle">Based on your current pension contributions and retirement timeline, you are well-positioned for a comfortable retirement.</p>
    </div>

    <!-- Strategies Content -->
    <template v-else-if="strategies">
      <!-- Retirement Age Context -->
      <div class="retirement-context">
        <div class="context-icon">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div class="context-text">
          <span class="context-label">Retirement age {{ retirementAge }}</span>
          <span class="context-separator">&middot;</span>
          <span class="context-detail">{{ yearsToRetirement }} years of growth</span>
        </div>
      </div>

      <!-- Summary Cards -->
      <div class="summary-grid-2">
        <!-- Affordability Card -->
        <div class="summary-card blue">
          <p class="summary-label">Monthly Disposable Income</p>
          <p class="summary-value">{{ formatCurrency(strategies.affordability?.monthly_disposable) }}</p>
          <p class="summary-subtitle">Available for additional contributions</p>
        </div>

        <!-- Annual Allowance Card -->
        <div class="summary-card orange">
          <p class="summary-label">Annual Allowance Remaining</p>
          <p class="summary-value">{{ formatCurrency(strategies.annual_allowance?.remaining_allowance) }}</p>
          <p class="summary-subtitle">
            <template v-if="strategies.annual_allowance?.carry_forward?.available">
              + {{ formatCurrency(strategies.annual_allowance.carry_forward.amount) }} carry forward
            </template>
            <template v-else>
              {{ strategies.annual_allowance?.carry_forward?.message }}
            </template>
          </p>
        </div>
      </div>

      <!-- No Strategies Available -->
      <div v-if="applicableStrategies.length === 0" class="no-strategies">
        <p>No additional strategies are needed at this time.</p>
      </div>

      <!-- Strategy Cards -->
      <div v-else class="strategies-list">
        <h3 class="section-title">Recommended Strategies</h3>
        <p class="section-subtitle">Follow these strategies in order to improve your retirement readiness</p>

        <StrategyCard
          v-for="strategy in strategiesWithContext"
          :key="strategy.type + (strategy.pension_id || '')"
          :strategy="strategy"
          :is-at-target="strategy.impact?.new_probability >= 95"
          @slider-change="handleSliderChange"
        />

        <!-- Combined Impact Summary -->
        <div v-if="applicableStrategies.length > 0" class="combined-impact">
          <div class="impact-header">
            <h4>Combined Strategy Impact</h4>
            <p v-if="strategies.on_track_at_strategy">
              Following strategies 1-{{ strategies.on_track_at_strategy }} will get you on track
            </p>
          </div>
          <div class="probability-comparison">
            <div class="prob-item">
              <span class="prob-label">Current</span>
              <span :class="['prob-value', getProbabilityClass(currentProbability)]">{{ currentProbability }}%</span>
            </div>
            <div class="prob-arrow">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 8.25L21 12m0 0l-3.75 3.75M21 12H3" />
              </svg>
            </div>
            <div class="prob-item">
              <span class="prob-label">Projected</span>
              <span :class="['prob-value', getProbabilityClass(projectedProbability)]">{{ projectedProbability }}%</span>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script>
import { mapState, mapActions } from 'vuex';
import StrategyCard from './StrategyCard.vue';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'StrategiesTab',

  mixins: [currencyMixin],

  emits: ['back'],

  components: {
    StrategyCard,
  },

  computed: {
    ...mapState('retirement', ['strategies', 'strategiesLoading', 'strategyImpact', 'error']),

    loading() {
      return this.strategiesLoading;
    },

    requiresDob() {
      return this.strategies?.requires_dob === true;
    },

    retirementAge() {
      return this.strategies?.current_status?.retirement_age || 68;
    },

    yearsToRetirement() {
      return this.strategies?.current_status?.years_to_retirement || 0;
    },

    currentProbability() {
      return this.strategies?.current_status?.probability || 0;
    },

    isOnTrack() {
      return this.currentProbability >= 95;
    },

    applicableStrategies() {
      return this.strategies?.strategies?.filter(s => s.applicable) || [];
    },

    /**
     * Augment strategies with cumulative context from prior strategies.
     * This enables each strategy card to calculate its impact relative to
     * all prior strategies, showing the true cumulative improvement.
     */
    strategiesWithContext() {
      let cumulativeMonthly = 0;
      let cumulativeIncome = 0;
      let cumulativeProbability = this.currentProbability;

      return this.applicableStrategies.map((strategy, index) => {
        // Context for this strategy = cumulative values from ALL prior strategies
        const augmented = {
          ...strategy,
          prior_cumulative_monthly: cumulativeMonthly,
          prior_cumulative_income: cumulativeIncome,
          prior_probability: cumulativeProbability,
          strategy_index: index,
        };

        // Update cumulative values for next strategy
        cumulativeMonthly += strategy.impact?.additional_monthly || 0;
        cumulativeIncome += strategy.impact?.additional_annual_income || 0;
        cumulativeProbability = strategy.impact?.new_probability || cumulativeProbability;

        return augmented;
      });
    },

    projectedProbability() {
      if (this.applicableStrategies.length === 0) return this.currentProbability;
      const lastStrategy = this.applicableStrategies[this.applicableStrategies.length - 1];
      return lastStrategy?.impact?.new_probability || this.currentProbability;
    },
  },

  methods: {
    ...mapActions('retirement', ['fetchStrategies', 'calculateStrategyImpact']),

    async handleSliderChange({ strategyType, newValue, priorCumulativeMonthly, priorCumulativeIncome, priorProbability }) {
      try {
        await this.calculateStrategyImpact({
          strategyType,
          newValue,
          priorAdditionalMonthly: priorCumulativeMonthly || 0,
          priorAdditionalIncome: priorCumulativeIncome || 0,
          priorProbability: priorProbability || null,
        });
      } catch (error) {
        console.error('Failed to calculate strategy impact:', error);
      }
    },

    getProbabilityClass(probability) {
      if (probability >= 95) return 'green';
      if (probability >= 80) return 'orange';
      return 'red';
    },
  },

  mounted() {
    this.fetchStrategies();
  },
};
</script>

<style scoped>
.strategies-tab {
  animation: fadeIn 0.3s ease-out;
}

/* Back Button */
.back-button {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  margin-bottom: 16px;
  font-size: 14px;
  font-weight: 500;
  @apply text-gray-700;
  background: white;
  @apply border border-gray-300;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.15s;
}

.back-button:hover {
  @apply bg-gray-50;
  @apply border-gray-400;
}

.back-button svg {
  width: 20px;
  height: 20px;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Loading State */
.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 80px 20px;
  text-align: center;
}

.spinner {
  @apply w-12 h-12 border-4 border-gray-200 border-t-primary-500 rounded-full mb-4;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.loading-state p {
  @apply text-gray-500;
  font-size: 16px;
  margin: 0;
}

/* Error State */
.error-state {
  text-align: center;
  padding: 60px 40px;
  background: white;
  border-radius: 12px;
  @apply border border-red-200;
}

.error-icon {
  width: 48px;
  height: 48px;
  @apply text-red-500;
  margin: 0 auto 16px;
}

.error-state p {
  @apply text-gray-500;
  font-size: 16px;
  margin: 0 0 16px 0;
}

.retry-button {
  @apply bg-primary-500;
  color: white;
  border: none;
  padding: 10px 24px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}

.retry-button:hover {
  @apply bg-blue-600;
}

/* On Track Banner */
.on-track-banner {
  text-align: center;
  padding: 60px 40px;
  background: linear-gradient(135deg, theme('colors.green.50') 0%, theme('colors.green.100') 100%);
  border-radius: 16px;
  @apply border-2 border-green-200;
}

.on-track-icon {
  width: 72px;
  height: 72px;
  @apply bg-green-500;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 24px;
}

.on-track-icon svg {
  width: 40px;
  height: 40px;
  color: white;
}

.on-track-banner h3 {
  font-size: 28px;
  font-weight: 700;
  @apply text-green-800;
  margin: 0 0 12px 0;
}

.on-track-banner .probability {
  font-size: 20px;
  font-weight: 600;
  @apply text-green-500;
  margin: 0 0 16px 0;
}

.on-track-banner .subtitle {
  font-size: 16px;
  @apply text-green-700;
  margin: 0;
  max-width: 500px;
  margin-left: auto;
  margin-right: auto;
}

/* DOB Required */
.dob-required {
  text-align: center;
  padding: 60px 40px;
  background: linear-gradient(135deg, theme('colors.orange.50') 0%, theme('colors.orange.100') 100%);
  border-radius: 16px;
  @apply border-2 border-orange-200;
}

.dob-icon {
  width: 72px;
  height: 72px;
  @apply bg-orange-500;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 24px;
}

.dob-icon svg {
  width: 40px;
  height: 40px;
  color: white;
}

.dob-required h3 {
  font-size: 22px;
  font-weight: 700;
  @apply text-orange-800;
  margin: 0 0 12px 0;
}

.dob-message {
  font-size: 16px;
  @apply text-orange-700;
  margin: 0 0 8px 0;
  font-weight: 500;
}

.dob-subtitle {
  font-size: 14px;
  @apply text-orange-500;
  margin: 0;
  max-width: 400px;
  margin-left: auto;
  margin-right: auto;
}

/* Retirement Context */
.retirement-context {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  @apply bg-green-50;
  @apply border border-green-200;
  border-radius: 8px;
  margin-bottom: 20px;
}

.context-icon {
  flex-shrink: 0;
}

.context-icon svg {
  width: 20px;
  height: 20px;
  @apply text-green-500;
}

.context-text {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
}

.context-label {
  font-weight: 600;
  @apply text-green-800;
}

.context-separator {
  @apply text-green-200;
}

.context-detail {
  @apply text-green-700;
}

/* Summary Cards */
.summary-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 20px;
  margin-bottom: 32px;
}

.summary-grid-2 {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 20px;
  margin-bottom: 32px;
}

.summary-card {
  background: white;
  border-radius: 12px;
  padding: 20px;
  @apply border border-gray-200;
}

.summary-card.blue {
  background: linear-gradient(135deg, theme('colors.blue.50') 0%, theme('colors.blue.100') 100%);
  @apply border-blue-200;
}

.summary-card.green {
  background: linear-gradient(135deg, theme('colors.green.50') 0%, theme('colors.green.100') 100%);
  @apply border-green-200;
}

.summary-card.orange {
  background: linear-gradient(135deg, theme('colors.orange.50') 0%, theme('colors.orange.100') 100%);
  @apply border-orange-200;
}

.summary-card.red {
  background: linear-gradient(135deg, theme('colors.red.50') 0%, theme('colors.red.100') 100%);
  @apply border-red-200;
}

.summary-label {
  font-size: 14px;
  @apply text-gray-500;
  margin: 0 0 8px 0;
  font-weight: 500;
}

.summary-value {
  font-size: 28px;
  font-weight: 700;
  @apply text-gray-900;
  margin: 0;
}

.summary-subtitle {
  font-size: 13px;
  @apply text-gray-500;
  margin: 8px 0 0 0;
}

/* No Strategies */
.no-strategies {
  text-align: center;
  padding: 40px;
  @apply bg-gray-50;
  border-radius: 12px;
  @apply border border-gray-200;
}

.no-strategies p {
  @apply text-gray-500;
  font-size: 16px;
  margin: 0;
}

/* Strategies List */
.strategies-list {
  margin-top: 8px;
}

.section-title {
  font-size: 20px;
  font-weight: 700;
  @apply text-gray-900;
  margin: 0 0 8px 0;
}

.section-subtitle {
  font-size: 14px;
  @apply text-gray-500;
  margin: 0 0 24px 0;
}

/* Combined Impact */
.combined-impact {
  background: linear-gradient(135deg, theme('colors.blue.50') 0%, theme('colors.blue.100') 100%);
  @apply border border-blue-200;
  border-radius: 12px;
  padding: 24px;
  margin-top: 24px;
}

.impact-header {
  text-align: center;
  margin-bottom: 20px;
}

.impact-header h4 {
  font-size: 16px;
  font-weight: 600;
  @apply text-blue-800;
  margin: 0 0 4px 0;
}

.impact-header p {
  font-size: 14px;
  @apply text-primary-500;
  margin: 0;
}

.probability-comparison {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 24px;
}

.prob-item {
  text-align: center;
}

.prob-label {
  display: block;
  font-size: 12px;
  @apply text-gray-500;
  margin-bottom: 4px;
}

.prob-value {
  font-size: 32px;
  font-weight: 700;
}

.prob-value.green {
  @apply text-green-500;
}

.prob-value.orange {
  @apply text-orange-500;
}

.prob-value.red {
  @apply text-red-500;
}

.prob-arrow svg {
  width: 32px;
  height: 32px;
  @apply text-primary-500;
}

@media (max-width: 768px) {
  .summary-grid,
  .summary-grid-2 {
    grid-template-columns: 1fr;
  }

  .probability-comparison {
    flex-direction: column;
    gap: 16px;
  }

  .prob-arrow svg {
    transform: rotate(90deg);
  }
}
</style>
