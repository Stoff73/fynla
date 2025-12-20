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
  </div>
</template>

<script>
import retirementService from '../../services/retirementService';

export default {
  name: 'StrategyCard',

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
        const response = await retirementService.calculateStrategyImpact(
          this.strategy.type,
          this.localValue
        );

        if (response.data) {
          // Calculate additional monthly for contribution strategies
          let additionalMonthly = this.strategy.impact?.additional_monthly || 0;
          if (this.strategy.type === 'employer_match' || this.strategy.type === 'increase_contribution') {
            additionalMonthly = this.calculateAdditionalMonthly();
          }

          this.calculatedImpact = {
            probability_improvement: Math.round(response.data.probability_improvement || 0),
            new_probability: Math.round(response.data.new_probability || 0),
            additional_monthly: additionalMonthly,
          };
        }

        // Emit event for parent component
        this.$emit('slider-change', {
          strategyType: this.strategy.type,
          newValue: this.localValue,
          pensionId: this.strategy.pension_id,
          impact: this.calculatedImpact,
        });
      } catch (error) {
        console.error('Failed to calculate strategy impact:', error);
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

    formatCurrency(value) {
      if (value === null || value === undefined) return '£0';
      return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: 'GBP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
      }).format(value);
    },

    getProbabilityClass(probability) {
      if (probability >= 95) return 'green';
      if (probability >= 80) return 'amber';
      return 'red';
    },
  },
};
</script>

<style scoped>
.strategy-card {
  background: white;
  border-radius: 12px;
  padding: 24px;
  border: 1px solid #e5e7eb;
  margin-bottom: 16px;
  transition: box-shadow 0.2s, border-color 0.2s;
}

.strategy-card:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.strategy-card.at-target {
  border-color: #a7f3d0;
  background: linear-gradient(135deg, white 0%, #f0fdf4 100%);
}

.strategy-card.priority-1 .priority-badge {
  background: #10b981;
}

.strategy-card.priority-2 .priority-badge {
  background: #3b82f6;
}

.strategy-card.priority-3 .priority-badge {
  background: #f59e0b;
}

.strategy-card.priority-4 .priority-badge {
  background: #6b7280;
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
  color: #111827;
  margin: 0 0 4px 0;
}

.pension-name {
  font-size: 14px;
  color: #6b7280;
  margin: 0;
}

.target-badge {
  display: flex;
  align-items: center;
  gap: 4px;
  background: #d1fae5;
  color: #059669;
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
  color: #6b7280;
  margin: 0 0 20px 0;
  line-height: 1.5;
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
  color: #9ca3af;
}

.new-label {
  font-size: 14px;
  font-weight: 600;
  color: #3b82f6;
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
  background: #3b82f6;
  border-radius: 50%;
  cursor: pointer;
  border: 2px solid white;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
}

.slider::-moz-range-thumb {
  width: 20px;
  height: 20px;
  background: #3b82f6;
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
  background: #e5e7eb;
  border-radius: 4px;
  z-index: 0;
}

.slider-track-fill {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  left: 0;
  height: 8px;
  background: linear-gradient(90deg, #3b82f6, #60a5fa);
  border-radius: 4px;
  z-index: 1;
  pointer-events: none;
}

.slider-labels {
  display: flex;
  justify-content: space-between;
  font-size: 11px;
  color: #9ca3af;
}

/* Impact Section */
.impact-section {
  display: flex;
  gap: 24px;
  padding: 16px;
  background: #f9fafb;
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
  color: #6b7280;
  margin-bottom: 4px;
}

.impact-value {
  font-size: 18px;
  font-weight: 700;
  color: #111827;
}

.impact-value.positive {
  color: #059669;
}

.impact-value.green {
  color: #059669;
}

.impact-value.amber {
  color: #d97706;
}

.impact-value.red {
  color: #dc2626;
}

/* Constraints Info */
.constraints-info {
  padding: 12px 16px;
  background: #fef3c7;
  border-radius: 8px;
  border: 1px solid #fde68a;
}

.constraints-info p {
  font-size: 12px;
  color: #92400e;
  margin: 0;
}

.constraints-info p + p {
  margin-top: 4px;
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
}
</style>
