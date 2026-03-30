<template>
  <div class="strategies-tab">
    <!-- Back Button -->
    <button
      @click="$emit('back')"
      class="detail-inline-back"
    >
      <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
      </svg>
      Back to Pensions
    </button>

    <!-- Loading State -->
    <div v-if="loading" class="loading-state">
      <div class="w-12 h-12 border-4 border-light-gray border-t-raspberry-500 rounded-full animate-spin mb-4"></div>
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
    <div v-else-if="isOnTrack" class="on-track-section">
      <div class="on-track-banner">
        <div class="on-track-icon">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <h3>You're On Track!</h3>
        <p class="probability">{{ currentProbability }}% probability of achieving your retirement goals</p>
        <p class="subtitle">Based on your current pension contributions and retirement timeline, you are well-positioned for a comfortable retirement.</p>
      </div>

      <!-- Capital Position Summary (also shown when on track) -->
      <div v-if="capitalPosition" class="on-track-capital-summary">
        <div class="capital-summary-grid">
          <div class="capital-summary-item">
            <span class="capital-summary-label">Total Projected Capital</span>
            <span class="capital-summary-value">{{ formatCurrency(capitalPosition.total_projected_capital) }}</span>
          </div>
          <div class="capital-summary-item">
            <span class="capital-summary-label">Required Capital</span>
            <span class="capital-summary-value">{{ formatCurrency(capitalPosition.required_capital) }}</span>
          </div>
          <div class="capital-summary-item surplus">
            <span class="capital-summary-label">{{ capitalPosition.is_surplus ? 'Surplus' : 'Gap' }}</span>
            <span class="capital-summary-value">{{ formatCurrency(Math.abs(capitalPosition.gap_to_target)) }}</span>
          </div>
          <div class="capital-summary-item income">
            <span class="capital-summary-label">Achievable Net Income</span>
            <span class="capital-summary-value">{{ formatCurrency(capitalPosition.achievable_net_income) }}/year</span>
          </div>
        </div>
      </div>
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

      <!-- Capital Position Section -->
      <div v-if="capitalPosition" class="capital-position-section">
        <div class="capital-position-header">
          <h3>Capital Position at Retirement</h3>
          <p class="capital-position-subtitle">How your projected assets compare to your required capital</p>
        </div>

        <!-- Progress Bar -->
        <div class="capital-progress-container">
          <div class="capital-progress-labels">
            <span class="progress-current">{{ formatCurrency(capitalPosition.total_projected_capital) }}</span>
            <span :class="['progress-status', capitalPosition.is_surplus ? 'surplus' : (capitalPosition.progress_percentage >= 80 ? 'on-track' : 'gap')]">
              {{ capitalPosition.is_surplus ? 'Surplus' : (capitalPosition.progress_percentage >= 80 ? 'On Track' : 'Gap') }}:
              {{ formatCurrency(Math.abs(capitalPosition.gap_to_target)) }}
            </span>
            <span class="progress-target">{{ formatCurrency(capitalPosition.required_capital) }}</span>
          </div>
          <div class="capital-progress-bar">
            <div
              class="capital-progress-fill"
              :class="capitalProgressClass"
              :style="{ width: Math.min(capitalPosition.progress_percentage, 100) + '%' }"
            ></div>
          </div>
          <div class="capital-progress-percent">
            <span :class="['percent-value', capitalProgressClass]">{{ capitalPosition.progress_percentage }}%</span>
            <span class="percent-label">of required capital</span>
          </div>
        </div>

        <!-- Capital Breakdown Grid -->
        <div class="capital-grid">
          <!-- Projected Pension Pot -->
          <div class="capital-card pension">
            <div class="capital-card-icon">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <div class="capital-card-content">
              <p class="capital-card-label">Projected Pension Pot</p>
              <p class="capital-card-value">{{ formatCurrency(capitalPosition.projected_pension_pot) }}</p>
              <p class="capital-card-note">80% Monte Carlo confidence</p>
            </div>
          </div>

          <!-- Other Assets -->
          <div class="capital-card assets">
            <div class="capital-card-icon">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
              </svg>
            </div>
            <div class="capital-card-content">
              <p class="capital-card-label">Other Assets</p>
              <p class="capital-card-value">{{ formatCurrency(capitalPosition.other_assets_total) }}</p>
              <p class="capital-card-note">ISAs, bonds, savings included</p>
            </div>
          </div>

          <!-- Achievable Net Income -->
          <div class="capital-card income" :class="{ 'meets-target': capitalPosition.income_meets_target }">
            <div class="capital-card-icon">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
              </svg>
            </div>
            <div class="capital-card-content">
              <p class="capital-card-label">Achievable Net Income</p>
              <p class="capital-card-value">{{ formatCurrency(capitalPosition.achievable_net_income) }}<span class="per-year">/year</span></p>
              <p class="capital-card-note">
                <template v-if="capitalPosition.income_meets_target">
                  <span class="meets-target-badge">Meets target of {{ formatCurrency(capitalPosition.target_income) }}</span>
                </template>
                <template v-else>
                  Target: {{ formatCurrency(capitalPosition.target_income) }}/year
                </template>
              </p>
            </div>
          </div>
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
        <div class="summary-card blue">
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

import logger from '@/utils/logger';
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

    capitalPosition() {
      return this.strategies?.capital_position || null;
    },

    capitalProgressClass() {
      const pct = this.capitalPosition?.progress_percentage || 0;
      if (pct >= 100) return 'green';
      if (pct >= 80) return 'blue';
      return 'red';
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
        logger.error('Failed to calculate strategy impact:', error);
      }
    },

    getProbabilityClass(probability) {
      if (probability >= 95) return 'green';
      if (probability >= 80) return 'blue';
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
  animation: fadeInSlideUp 0.3s ease-out;
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

.loading-state p {
  @apply text-neutral-500;
  font-size: 16px;
  margin: 0;
}

/* Error State */
.error-state {
  text-align: center;
  padding: 60px 40px;
  background: white;
  border-radius: 12px;
  @apply border border-raspberry-200;
}

.error-icon {
  width: 48px;
  height: 48px;
  @apply text-raspberry-500;
  margin: 0 auto 16px;
}

.error-state p {
  @apply text-neutral-500;
  font-size: 16px;
  margin: 0 0 16px 0;
}

.retry-button {
  @apply bg-raspberry-500;
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
  @apply bg-raspberry-500;
}

/* On Track Banner */
.on-track-banner {
  text-align: center;
  padding: 60px 40px;
  background: linear-gradient(135deg, theme('colors.green.50') 0%, theme('colors.green.100') 100%);
  border-radius: 16px;
  @apply border-2 border-spring-200;
}

.on-track-icon {
  width: 72px;
  height: 72px;
  @apply bg-spring-500;
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
  @apply text-spring-800;
  margin: 0 0 12px 0;
}

.on-track-banner .probability {
  font-size: 20px;
  font-weight: 600;
  @apply text-spring-500;
  margin: 0 0 16px 0;
}

.on-track-banner .subtitle {
  font-size: 16px;
  @apply text-spring-700;
  margin: 0;
  max-width: 500px;
  margin-left: auto;
  margin-right: auto;
}

/* On Track Capital Summary */
.on-track-section {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.on-track-capital-summary {
  background: white;
  border-radius: 12px;
  padding: 24px;
  @apply border border-light-gray;
}

.capital-summary-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 20px;
}

.capital-summary-item {
  text-align: center;
  padding: 16px;
  border-radius: 8px;
  @apply bg-savannah-100;
}

.capital-summary-item.surplus {
  @apply bg-spring-50;
}

.capital-summary-item.income {
  @apply bg-violet-50;
}

.capital-summary-label {
  display: block;
  font-size: 13px;
  @apply text-neutral-500;
  margin-bottom: 8px;
  font-weight: 500;
}

.capital-summary-value {
  display: block;
  font-size: 20px;
  font-weight: 700;
  @apply text-horizon-500;
}

.capital-summary-item.surplus .capital-summary-value {
  @apply text-spring-600;
}

.capital-summary-item.income .capital-summary-value {
  @apply text-violet-600;
}

/* DOB Required */
.dob-required {
  text-align: center;
  padding: 60px 40px;
  background: linear-gradient(135deg, theme('colors.blue.50') 0%, theme('colors.blue.100') 100%);
  border-radius: 16px;
  @apply border-2 border-violet-200;
}

.dob-icon {
  width: 72px;
  height: 72px;
  @apply bg-violet-500;
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
  @apply text-violet-800;
  margin: 0 0 12px 0;
}

.dob-message {
  font-size: 16px;
  @apply text-violet-700;
  margin: 0 0 8px 0;
  font-weight: 500;
}

.dob-subtitle {
  font-size: 14px;
  @apply text-violet-500;
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
  @apply bg-spring-50;
  @apply border border-spring-200;
  border-radius: 8px;
  margin-bottom: 20px;
}

.context-icon {
  flex-shrink: 0;
}

.context-icon svg {
  width: 20px;
  height: 20px;
  @apply text-spring-500;
}

.context-text {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
}

.context-label {
  font-weight: 600;
  @apply text-spring-800;
}

.context-separator {
  @apply text-spring-200;
}

.context-detail {
  @apply text-spring-700;
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
  @apply border border-light-gray;
}

.summary-card.blue {
  background: linear-gradient(135deg, theme('colors.blue.50') 0%, theme('colors.blue.100') 100%);
  @apply border-violet-200;
}

.summary-card.green {
  background: linear-gradient(135deg, theme('colors.green.50') 0%, theme('colors.green.100') 100%);
  @apply border-spring-200;
}

.summary-card.blue {
  background: linear-gradient(135deg, theme('colors.blue.50') 0%, theme('colors.blue.100') 100%);
  @apply border-violet-200;
}

.summary-card.red {
  background: linear-gradient(135deg, theme('colors.red.50') 0%, theme('colors.red.100') 100%);
  @apply border-raspberry-200;
}

.summary-label {
  font-size: 14px;
  @apply text-neutral-500;
  margin: 0 0 8px 0;
  font-weight: 500;
}

.summary-value {
  font-size: 28px;
  font-weight: 700;
  @apply text-horizon-500;
  margin: 0;
}

.summary-subtitle {
  font-size: 13px;
  @apply text-neutral-500;
  margin: 8px 0 0 0;
}

/* No Strategies */
.no-strategies {
  text-align: center;
  padding: 40px;
  @apply bg-savannah-100;
  border-radius: 12px;
  @apply border border-light-gray;
}

.no-strategies p {
  @apply text-neutral-500;
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
  @apply text-horizon-500;
  margin: 0 0 8px 0;
}

.section-subtitle {
  font-size: 14px;
  @apply text-neutral-500;
  margin: 0 0 24px 0;
}

/* Combined Impact */
.combined-impact {
  background: linear-gradient(135deg, theme('colors.blue.50') 0%, theme('colors.blue.100') 100%);
  @apply border border-violet-200;
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
  @apply text-violet-800;
  margin: 0 0 4px 0;
}

.impact-header p {
  font-size: 14px;
  @apply text-raspberry-500;
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
  @apply text-neutral-500;
  margin-bottom: 4px;
}

.prob-value {
  font-size: 32px;
  font-weight: 700;
}

.prob-value.green {
  @apply text-spring-500;
}

.prob-value.blue {
  @apply text-violet-500;
}

.prob-value.red {
  @apply text-raspberry-500;
}

.prob-arrow svg {
  width: 32px;
  height: 32px;
  @apply text-raspberry-500;
}

/* Capital Position Section */
.capital-position-section {
  background: white;
  border-radius: 12px;
  padding: 24px;
  @apply border border-light-gray;
  margin-bottom: 24px;
}

.capital-position-header {
  margin-bottom: 20px;
}

.capital-position-header h3 {
  font-size: 18px;
  font-weight: 600;
  @apply text-horizon-500;
  margin: 0 0 4px 0;
}

.capital-position-subtitle {
  font-size: 14px;
  @apply text-neutral-500;
  margin: 0;
}

/* Capital Progress Bar */
.capital-progress-container {
  margin-bottom: 24px;
}

.capital-progress-labels {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
  font-size: 14px;
}

.progress-current {
  font-weight: 600;
  @apply text-neutral-500;
}

.progress-status {
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 4px;
}

.progress-status.gap {
  @apply bg-raspberry-100 text-raspberry-700;
}

.progress-status.on-track {
  @apply bg-violet-100 text-violet-700;
}

.progress-status.surplus {
  @apply bg-spring-100 text-spring-700;
}

.progress-target {
  @apply text-neutral-500;
  font-weight: 500;
}

.capital-progress-bar {
  height: 16px;
  @apply bg-savannah-200;
  border-radius: 8px;
  overflow: hidden;
  margin-bottom: 8px;
}

.capital-progress-fill {
  height: 100%;
  border-radius: 8px;
  transition: width 0.5s ease-out;
}

.capital-progress-fill.green {
  @apply bg-spring-500;
}

.capital-progress-fill.blue {
  @apply bg-violet-500;
}

.capital-progress-fill.red {
  @apply bg-raspberry-500;
}

.capital-progress-percent {
  display: flex;
  align-items: center;
  gap: 8px;
}

.percent-value {
  font-size: 24px;
  font-weight: 700;
}

.percent-value.green {
  @apply text-spring-600;
}

.percent-value.blue {
  @apply text-violet-600;
}

.percent-value.red {
  @apply text-raspberry-600;
}

.percent-label {
  font-size: 14px;
  @apply text-neutral-500;
}

/* Capital Grid */
.capital-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
}

.capital-card {
  display: flex;
  gap: 12px;
  padding: 16px;
  border-radius: 10px;
  @apply border;
}

.capital-card.pension {
  @apply bg-purple-50 border-purple-200;
}

.capital-card.pension .capital-card-icon {
  @apply bg-purple-100 text-purple-600;
}

.capital-card.assets {
  @apply bg-violet-50 border-violet-200;
}

.capital-card.assets .capital-card-icon {
  @apply bg-violet-100 text-violet-600;
}

.capital-card.income {
  @apply bg-violet-50 border-violet-200;
}

.capital-card.income .capital-card-icon {
  @apply bg-violet-100 text-violet-600;
}

.capital-card.income.meets-target {
  @apply bg-spring-50 border-spring-200;
}

.capital-card.income.meets-target .capital-card-icon {
  @apply bg-spring-100 text-spring-600;
}

.capital-card-icon {
  flex-shrink: 0;
  width: 40px;
  height: 40px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.capital-card-icon svg {
  width: 22px;
  height: 22px;
}

.capital-card-content {
  flex: 1;
  min-width: 0;
}

.capital-card-label {
  font-size: 13px;
  @apply text-neutral-500;
  margin: 0 0 4px 0;
  font-weight: 500;
}

.capital-card-value {
  font-size: 20px;
  font-weight: 700;
  @apply text-horizon-500;
  margin: 0 0 4px 0;
}

.capital-card-value .per-year {
  font-size: 14px;
  font-weight: 500;
  @apply text-neutral-500;
}

.capital-card-note {
  font-size: 12px;
  @apply text-horizon-400;
  margin: 0;
}

.meets-target-badge {
  display: inline-block;
  padding: 2px 6px;
  border-radius: 4px;
  @apply bg-spring-100 text-spring-700;
  font-weight: 500;
}

@media (max-width: 1024px) {
  .capital-grid {
    grid-template-columns: 1fr;
  }

  .capital-summary-grid {
    grid-template-columns: repeat(2, 1fr);
  }
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

  .capital-progress-labels {
    flex-direction: column;
    align-items: flex-start;
    gap: 4px;
  }

  .capital-summary-grid {
    grid-template-columns: 1fr;
  }
}
</style>
