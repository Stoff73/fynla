<template>
  <div :class="['strategy-card', priorityClass, { 'at-target': isAtTarget }]">
    <div class="card-header">
      <div class="priority-badge">
        <span class="priority-number">{{ strategy.priority }}</span>
      </div>
      <div class="header-content">
        <h4 class="strategy-title">{{ strategy.title }}</h4>
        <p v-if="strategy.pension_name" class="pension-name">{{ strategy.pension_name }}</p>
      </div>
      <div v-if="isAtTarget" class="target-badge">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        On Track
      </div>
    </div>

    <p class="description">{{ strategy.description }}</p>

    <!-- Retirement Age Context -->
    <div v-if="strategy.retirement_age && strategy.type !== 'retirement_age'" class="strategy-context">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="context-icon-sm">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <span>{{ strategy.years_to_retirement }} years of growth to age {{ strategy.retirement_age }}</span>
    </div>

    <!-- Slider Control -->
    <div class="slider-section">
      <div class="slider-header">
        <span class="current-label">Current: {{ formatValue(strategy.current_value) }}</span>
        <span class="new-label">{{ formatValue(localValue) }}</span>
      </div>
      <div class="slider-wrapper">
        <input
          v-model.number="localValue"
          type="range"
          class="slider"
          :min="strategy.slider_config.min"
          :max="strategy.slider_config.max"
          :step="strategy.slider_config.step"
          @input="onSliderInput"
        />
        <div class="slider-track-bg"></div>
        <div class="slider-track-fill" :style="{ width: sliderFillWidth }"></div>
      </div>
      <div class="slider-labels">
        <span>{{ formatValue(strategy.slider_config.min) }}</span>
        <span>{{ formatValue(strategy.slider_config.max) }}</span>
      </div>
    </div>

    <!-- Impact Preview -->
    <div :class="['impact-section', { 'calculating': isCalculating }]">
      <div class="impact-item">
        <span class="impact-label">Probability Improvement</span>
        <span class="impact-value positive">+{{ displayImpact.probability_improvement }}%</span>
      </div>
      <div class="impact-item">
        <span class="impact-label">New Probability</span>
        <span :class="['impact-value', getProbabilityClass(displayImpact.new_probability)]">
          {{ displayImpact.new_probability }}%
        </span>
      </div>
      <div v-if="strategy.type === 'employer_match' || strategy.type === 'increase_contribution'" class="impact-item">
        <span class="impact-label">Additional Monthly</span>
        <span class="impact-value">{{ formatCurrency(displayImpact.additional_monthly || 0) }}</span>
      </div>
    </div>

    <!-- Constraints Info -->
    <div v-if="strategy.constraints" class="constraints-info">
      <p v-if="strategy.constraints.affordability_limit">
        Affordability limit: {{ formatCurrency(strategy.constraints.affordability_limit) }}/month
      </p>
      <p v-if="strategy.constraints.annual_allowance_limit">
        Annual allowance limit: {{ formatCurrency(strategy.constraints.annual_allowance_limit) }}/month
      </p>
      <p v-if="strategy.constraints.guaranteed_income">
        Guaranteed income floor: {{ formatCurrency(strategy.constraints.guaranteed_income) }}/year
      </p>
    </div>

    <!-- Projection Chart -->
    <div v-if="displayProjection" class="projection-section">
      <h5 class="projection-title">Projected Outcome</h5>

      <!-- Chart -->
      <div class="chart-container">
        <apexchart
          :key="chartKey"
          type="area"
          height="280"
          :options="chartOptions"
          :series="chartSeries"
        />
      </div>

      <!-- Income Comparison -->
      <div class="income-comparison">
        <div class="comparison-item without">
          <span class="comparison-label">Without Strategy</span>
          <div class="comparison-values">
            <span class="pot-value">Pot: {{ formatCurrency(displayProjection.without_strategy.pot_at_retirement) }}</span>
            <span class="income-value">Income: {{ formatCurrency(displayProjection.without_strategy.sustainable_income) }}/yr</span>
            <span :class="['coverage-badge', getCoverageBadgeClass(displayProjection.without_strategy.income_coverage_percent)]">
              {{ displayProjection.without_strategy.income_coverage_percent }}% of target
            </span>
          </div>
        </div>
        <div class="comparison-arrow">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 8.25L21 12m0 0l-3.75 3.75M21 12H3" />
          </svg>
        </div>
        <div class="comparison-item with">
          <span class="comparison-label">With Strategy</span>
          <div class="comparison-values">
            <span class="pot-value">Pot: {{ formatCurrency(displayProjection.with_strategy.pot_at_retirement) }}</span>
            <span class="income-value">Income: {{ formatCurrency(displayProjection.with_strategy.sustainable_income) }}/yr</span>
            <span :class="['coverage-badge', getCoverageBadgeClass(displayProjection.with_strategy.income_coverage_percent)]">
              {{ displayProjection.with_strategy.income_coverage_percent }}% of target
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import retirementService from '../../services/retirementService';
import VueApexCharts from 'vue3-apexcharts';
import { currencyMixin } from '@/mixins/currencyMixin';
import { SUCCESS_COLORS, TEXT_COLORS, BORDER_COLORS, SECONDARY_COLORS, CHART_DEFAULTS } from '@/constants/designSystem';

import logger from '@/utils/logger';
export default {
  name: 'StrategyCard',

  mixins: [currencyMixin],

  components: {
    apexchart: VueApexCharts,
  },

  props: {
    strategy: {
      type: Object,
      required: true,
    },
    isAtTarget: {
      type: Boolean,
      default: false,
    },
  },

  emits: ['slider-change'],

  data() {
    return {
      localValue: this.strategy.recommended_value,
      calculatedImpact: null,
      localProjection: null,  // Updated projection from slider interaction
      sliderTimeout: null,
      isCalculating: false,
    };
  },

  computed: {
    priorityClass() {
      const priority = this.strategy.priority;
      if (priority === 1) return 'priority-1';
      if (priority === 2) return 'priority-2';
      if (priority === 3) return 'priority-3';
      return 'priority-4';
    },

    sliderFillWidth() {
      const { min, max } = this.strategy.slider_config;
      const percentage = ((this.localValue - min) / (max - min)) * 100;
      return `${percentage}%`;
    },

    displayImpact() {
      return this.calculatedImpact || this.strategy.impact;
    },

    // Use localProjection if slider has been moved, otherwise use original
    displayProjection() {
      return this.localProjection || this.strategy.projection;
    },

    chartSeries() {
      if (!this.displayProjection?.pot_growth) return [];

      const growth = this.displayProjection.pot_growth;
      return [
        {
          name: 'With Strategy',
          data: growth.map(item => item.pot_with_strategy),
        },
        {
          name: 'Current Path',
          data: growth.map(item => item.pot_without_strategy),
        },
      ];
    },

    // Force chart re-render when projection data changes
    chartKey() {
      const pot = this.displayProjection?.with_strategy?.pot_at_retirement || 0;
      const withStrategyFinal = this.displayProjection?.pot_growth?.slice(-1)[0]?.pot_with_strategy || 0;
      return `chart-${pot}-${withStrategyFinal}`;
    },

    chartOptions() {
      if (!this.displayProjection?.pot_growth) return {};

      const growth = this.displayProjection.pot_growth;
      const years = growth.map(item => item.year);

      return {
        chart: {
          ...CHART_DEFAULTS.chart,
          type: 'area',
          animations: { enabled: true, speed: 500 },
        },
        colors: [SUCCESS_COLORS[500], SECONDARY_COLORS[500]],
        fill: {
          type: 'gradient',
          gradient: {
            shadeIntensity: 1,
            opacityFrom: 0.4,
            opacityTo: 0.1,
            stops: [0, 90, 100],
          },
        },
        stroke: {
          curve: 'smooth',
          width: [3, 2],
          dashArray: [0, 5],
        },
        xaxis: {
          categories: years,
          labels: {
            style: { colors: TEXT_COLORS.muted, fontSize: '11px' },
            rotate: 0,
            formatter: (val) => val % 5 === 0 ? val : '',
          },
          axisBorder: { show: false },
          axisTicks: { show: false },
        },
        yaxis: {
          labels: {
            style: { colors: TEXT_COLORS.muted, fontSize: '11px' },
            formatter: (val) => '£' + (val / 1000).toFixed(0) + 'k',
          },
        },
        grid: {
          borderColor: BORDER_COLORS.default,
          strokeDashArray: 4,
        },
        legend: {
          position: 'top',
          horizontalAlign: 'right',
          fontSize: '12px',
          markers: { width: 8, height: 8, radius: 2 },
        },
        tooltip: {
          y: {
            formatter: (val) => '£' + val.toLocaleString(),
          },
        },
        dataLabels: { enabled: false },
      };
    },
  },

  watch: {
    strategy: {
      handler(newStrategy) {
        this.localValue = newStrategy.recommended_value;
        this.calculatedImpact = null;
      },
      deep: true,
    },
  },

  beforeUnmount() {
    if (this.sliderTimeout) {
      clearTimeout(this.sliderTimeout);
    }
  },

  methods: {
    onSliderInput() {
      clearTimeout(this.sliderTimeout);
      this.sliderTimeout = setTimeout(() => {
        this.calculateImpact();
      }, 300);
    },

    async calculateImpact() {
      if (this.isCalculating) return;

      this.isCalculating = true;
      try {
        // Pass cumulative context from prior strategies
        const response = await retirementService.calculateStrategyImpact(
          this.strategy.type,
          this.localValue,
          {
            priorAdditionalMonthly: this.strategy.prior_cumulative_monthly || 0,
            priorAdditionalIncome: this.strategy.prior_cumulative_income || 0,
            priorProbability: this.strategy.prior_probability || null,
          }
        );

        if (response.data) {
          // Use additional_monthly from backend
          const additionalMonthly = response.data.additional_monthly || 0;

          this.calculatedImpact = {
            probability_improvement: Math.round(response.data.probability_improvement || 0),
            new_probability: Math.round(response.data.new_probability || 0),
            additional_monthly: additionalMonthly,
          };

          // Update local projection for chart/cards
          if (response.data.projection) {
            this.localProjection = response.data.projection;
          }
        }

        // Emit event for parent component (include cumulative context)
        this.$emit('slider-change', {
          strategyType: this.strategy.type,
          newValue: this.localValue,
          pensionId: this.strategy.pension_id,
          impact: this.calculatedImpact,
          priorCumulativeMonthly: this.strategy.prior_cumulative_monthly || 0,
          priorCumulativeIncome: this.strategy.prior_cumulative_income || 0,
          priorProbability: this.strategy.prior_probability || null,
        });
      } catch (error) {
        logger.error('Failed to calculate strategy impact:', error);
      } finally {
        this.isCalculating = false;
      }
    },

    calculateAdditionalMonthly() {
      // For employer_match: calculate based on percentage of salary
      if (this.strategy.type === 'employer_match') {
        const currentPercent = this.strategy.current_value || 0;
        const additionalPercent = this.localValue - currentPercent;
        // Estimate monthly based on original impact ratio
        const originalImpact = this.strategy.impact?.additional_monthly || 0;
        const originalPercentDiff = (this.strategy.recommended_value - currentPercent) || 1;
        return Math.round((originalImpact / originalPercentDiff) * additionalPercent);
      }

      // For increase_contribution: the slider value IS the monthly amount
      if (this.strategy.type === 'increase_contribution') {
        const currentMonthly = this.strategy.current_value || 0;
        return Math.round(this.localValue - currentMonthly);
      }

      return 0;
    },

    formatValue(value) {
      const format = this.strategy.slider_config?.format;
      const unit = this.strategy.slider_config?.unit || '';

      if (format === 'currency') {
        return this.formatCurrency(value) + unit;
      }
      if (format === 'percentage') {
        return `${value}${unit}`;
      }
      if (format === 'age') {
        return `${value}${unit}`;
      }
      return `${value}${unit}`;
    },

    getProbabilityClass(probability) {
      if (probability >= 95) return 'green';
      if (probability >= 80) return 'blue';
      return 'red';
    },

    getCoverageBadgeClass(coverage) {
      if (coverage >= 100) return 'coverage-excellent';
      if (coverage >= 75) return 'coverage-good';
      if (coverage >= 50) return 'coverage-fair';
      return 'coverage-poor';
    },
  },
};
</script>

<style scoped>
.strategy-card {
  background: white;
  border-radius: 12px;
  padding: 24px;
  @apply border border-light-gray;
  margin-bottom: 16px;
  transition: box-shadow 0.2s, border-color 0.2s;
}

.strategy-card:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.strategy-card.at-target {
  @apply border-spring-200;
  background: linear-gradient(135deg, white 0%, theme('colors.green.50') 100%);
}

.strategy-card.priority-1 .priority-badge {
  @apply bg-spring-600;
}

.strategy-card.priority-2 .priority-badge {
  @apply bg-raspberry-500;
}

.strategy-card.priority-3 .priority-badge {
  @apply bg-violet-500;
}

.strategy-card.priority-4 .priority-badge {
  @apply bg-savannah-500;
}

.card-header {
  display: flex;
  align-items: flex-start;
  gap: 16px;
  margin-bottom: 16px;
}

.priority-badge {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.priority-number {
  color: white;
  font-size: 14px;
  font-weight: 700;
}

.header-content {
  flex: 1;
}

.strategy-title {
  font-size: 18px;
  font-weight: 600;
  @apply text-horizon-500;
  margin: 0 0 4px 0;
}

.pension-name {
  font-size: 14px;
  @apply text-neutral-500;
  margin: 0;
}

.target-badge {
  display: flex;
  align-items: center;
  gap: 4px;
  @apply bg-spring-100;
  @apply text-spring-600;
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
}

.target-badge svg {
  width: 16px;
  height: 16px;
}

.description {
  font-size: 14px;
  @apply text-neutral-500;
  margin: 0 0 12px 0;
  line-height: 1.5;
}

.strategy-context {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  @apply text-spring-700;
  margin: 0 0 20px 0;
}

.context-icon-sm {
  width: 14px;
  height: 14px;
  flex-shrink: 0;
}

/* Slider Section */
.slider-section {
  margin-bottom: 20px;
}

.slider-header {
  display: flex;
  justify-content: space-between;
  margin-bottom: 8px;
}

.current-label {
  font-size: 12px;
  @apply text-horizon-400;
}

.new-label {
  font-size: 14px;
  font-weight: 600;
  @apply text-raspberry-500;
}

.slider-wrapper {
  position: relative;
  height: 20px;
  margin-bottom: 8px;
}

.slider {
  width: 100%;
  height: 20px;
  -webkit-appearance: none;
  appearance: none;
  background: transparent;
  position: relative;
  z-index: 2;
  cursor: pointer;
}

.slider::-webkit-slider-thumb {
  -webkit-appearance: none;
  appearance: none;
  width: 20px;
  height: 20px;
  @apply bg-raspberry-500;
  border-radius: 50%;
  cursor: pointer;
  border: 2px solid white;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
}

.slider::-moz-range-thumb {
  width: 20px;
  height: 20px;
  @apply bg-raspberry-500;
  border-radius: 50%;
  cursor: pointer;
  border: 2px solid white;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
}

.slider-track-bg {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  left: 0;
  right: 0;
  height: 8px;
  @apply bg-savannah-200;
  border-radius: 4px;
  z-index: 0;
}

.slider-track-fill {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  left: 0;
  height: 8px;
  @apply bg-gradient-to-r from-raspberry-500 to-raspberry-400;
  border-radius: 4px;
  z-index: 1;
  pointer-events: none;
}

.slider-labels {
  display: flex;
  justify-content: space-between;
  font-size: 11px;
  @apply text-horizon-400;
}

/* Impact Section */
.impact-section {
  display: flex;
  gap: 24px;
  padding: 16px;
  @apply bg-savannah-100;
  border-radius: 8px;
  margin-bottom: 16px;
  transition: opacity 0.2s;
}

.impact-section.calculating {
  opacity: 0.6;
}

.impact-item {
  flex: 1;
}

.impact-label {
  display: block;
  font-size: 12px;
  @apply text-neutral-500;
  margin-bottom: 4px;
}

.impact-value {
  font-size: 18px;
  font-weight: 700;
  @apply text-horizon-500;
}

.impact-value.positive {
  @apply text-spring-600;
}

.impact-value.green {
  @apply text-spring-600;
}

.impact-value.blue {
  @apply text-violet-600;
}

.impact-value.red {
  @apply text-raspberry-600;
}

/* Constraints Info */
.constraints-info {
  padding: 12px 16px;
  @apply bg-violet-100;
  border-radius: 8px;
  @apply border border-violet-200;
}

.constraints-info p {
  font-size: 12px;
  @apply text-violet-800;
  margin: 0;
}

.constraints-info p + p {
  margin-top: 4px;
}

/* Projection Section */
.projection-section {
  margin-top: 24px;
  padding-top: 24px;
  @apply border-t border-light-gray;
}

.projection-title {
  font-size: 16px;
  font-weight: 600;
  @apply text-horizon-500;
  margin: 0 0 16px 0;
}

.chart-container {
  margin-bottom: 20px;
  border-radius: 8px;
  overflow: hidden;
}

.income-comparison {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px;
  @apply bg-savannah-100;
  border-radius: 12px;
}

.comparison-item {
  flex: 1;
  padding: 12px 16px;
  border-radius: 8px;
}

.comparison-item.without {
  @apply bg-raspberry-50;
  @apply border border-raspberry-200;
}

.comparison-item.with {
  @apply bg-spring-50;
  @apply border border-spring-200;
}

.comparison-label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  @apply text-neutral-500;
  margin-bottom: 8px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.comparison-values {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.pot-value {
  font-size: 14px;
  font-weight: 600;
  @apply text-horizon-500;
}

.income-value {
  font-size: 13px;
  @apply text-neutral-500;
}

.coverage-badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 600;
  margin-top: 4px;
}

.coverage-excellent {
  @apply bg-spring-100;
  @apply text-spring-800;
}

.coverage-good {
  @apply bg-violet-100;
  @apply text-violet-800;
}

.coverage-fair {
  @apply bg-violet-200;
  @apply text-violet-800;
}

.coverage-poor {
  @apply bg-raspberry-200;
  @apply text-raspberry-800;
}

.comparison-arrow {
  flex-shrink: 0;
}

.comparison-arrow svg {
  @apply w-6 h-6 text-spring-600;
}

@media (max-width: 640px) {
  .card-header {
    flex-wrap: wrap;
  }

  .target-badge {
    width: 100%;
    justify-content: center;
    margin-top: 8px;
  }

  .impact-section {
    flex-direction: column;
    gap: 12px;
  }

  .income-comparison {
    flex-direction: column;
  }

  .comparison-arrow {
    transform: rotate(90deg);
  }

  .comparison-item {
    width: 100%;
  }
}
</style>
