<template>
  <div class="future-value-tab">
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
        <div class="w-12 h-12 border-[3px] border-light-gray border-t-raspberry-500 rounded-full animate-spin mb-4"></div>
        <p>Running projections...</p>
      </div>

      <!-- No DC Pensions State -->
      <div v-else-if="!projections || !projections.pension_pot_projection?.dc_pension_count" class="empty-state">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="empty-icon">
          <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
        </svg>
        <p>No Defined Contribution pensions found</p>
        <p class="empty-subtitle">Add money purchase pensions to see future value projections</p>
      </div>

      <!-- Projections Content -->
      <template v-else>
        <!-- Summary Cards -->
        <div class="summary-grid">
          <div class="summary-card blue">
            <p class="summary-label">Projected Pot at Retirement</p>
            <p class="summary-value">{{ formatCurrency(projections.pension_pot_projection?.percentile_20_at_retirement) }}</p>
            <p class="summary-subtitle">Age {{ projections.pension_pot_projection?.retirement_age }} (80% probability)</p>
          </div>
          <div class="summary-card purple clickable" @click="$emit('show-income')">
            <p class="summary-label">Required Capital</p>
            <p class="summary-value">{{ formatCurrency(requiredCapital) }}</p>
            <p class="summary-subtitle">View in Income Planner <span class="arrow-icon">→</span></p>
          </div>
          <div class="summary-card teal">
            <p class="summary-label">Target Retirement Income</p>
            <p class="summary-value">{{ formatCurrency(projections.income_drawdown?.target_income) }}<span class="per-year">/year</span></p>
            <p class="summary-subtitle">From retirement profile</p>
          </div>
        </div>

      <!-- Pension Pot Projection Chart -->
      <div class="chart-card">
        <div class="chart-header">
          <h3 class="chart-title">Pension Pot Projection <span class="text-sm font-normal">(using high probability of 80% of achieving {{ projections.pension_pot_projection?.expected_return }}% returns)</span></h3>
          <!-- Retirement Age Info -->
          <div class="retirement-age-info">
            <div class="retirement-age-item">
              <span class="retirement-age-label">Retirement Age</span>
              <span class="retirement-age-value">{{ projections.pension_pot_projection?.retirement_age }}</span>
            </div>
            <div class="retirement-age-divider"></div>
            <div class="retirement-age-item">
              <span class="retirement-age-label">Years to Retirement</span>
              <span class="retirement-age-value">{{ projections.pension_pot_projection?.years_to_retirement }}</span>
            </div>
          </div>
          <p class="chart-subtitle">
            Monte Carlo simulation for {{ projections.pension_pot_projection?.dc_pension_count }} Defined Contribution pension(s)
            <span class="risk-badge">{{ formatRiskLevel(projections.pension_pot_projection?.risk_level) }} Risk</span>
          </p>
        </div>
        <PensionPotProjectionChart
          :data="projections.pension_pot_projection"
          :life-events="projections.life_events_applied || []"
        />
      </div>

      <!-- Income Drawdown Chart -->
      <div class="chart-card">
        <div class="chart-header">
          <h3 class="chart-title">Sustainable Withdrawal Income</h3>
          <p class="chart-subtitle">
            {{ projections.income_drawdown?.withdrawal_rate }}% sustainable withdrawal rate with {{ projections.income_drawdown?.growth_rate }}% fund growth and {{ projections.income_drawdown?.inflation_rate }}% inflation adjustment
          </p>
        </div>
        <IncomeDrawdownChart :data="projections.income_drawdown" />
      </div>

      <!-- Target Income Drawdown Chart -->
      <div class="chart-card">
        <div class="chart-header">
          <h3 class="chart-title">Target Income Until Fund Depletes</h3>
          <p class="chart-subtitle">
            Drawing full target income with {{ projections.target_income_drawdown?.growth_rate }}% fund growth
            <span v-if="projections.target_income_drawdown?.fund_depletion_age" class="depletion-badge">
              Fund lasts until age {{ projections.target_income_drawdown.fund_depletion_age }}
            </span>
          </p>
        </div>
        <TargetIncomeDrawdownChart :data="projections.target_income_drawdown" />
      </div>

        <!-- Guaranteed Income Info -->
        <div class="info-panel">
          <div class="info-icon-wrapper">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="info-icon">
              <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
            </svg>
          </div>
          <div class="info-content">
            <p class="info-title">Guaranteed Income Sources</p>
            <ul class="info-list">
              <li>Defined Benefit Pension Income: <strong>{{ formatCurrency(projections.income_drawdown?.guaranteed_income?.db_pensions) }}/year</strong></li>
              <li>State Pension: <strong>{{ formatCurrency(projections.income_drawdown?.guaranteed_income?.state_pension) }}/year</strong></li>
              <li>Total Guaranteed: <strong>{{ formatCurrency(projections.income_drawdown?.guaranteed_income?.total) }}/year</strong></li>
            </ul>
            <p v-if="projections.income_drawdown?.fund_depletion_age" class="info-warning">
              Defined Contribution fund projected to deplete at age {{ projections.income_drawdown.fund_depletion_age }}
            </p>
          </div>
        </div>
      </template>
  </div>
</template>

<script>
import PensionPotProjectionChart from './PensionPotProjectionChart.vue';
import IncomeDrawdownChart from './IncomeDrawdownChart.vue';
import TargetIncomeDrawdownChart from './TargetIncomeDrawdownChart.vue';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'FutureValueTab',

  mixins: [currencyMixin],

  emits: ['back', 'show-income'],

  components: {
    PensionPotProjectionChart,
    IncomeDrawdownChart,
    TargetIncomeDrawdownChart,
  },

  props: {
    projections: {
      type: Object,
      default: null,
    },
    loading: {
      type: Boolean,
      default: false,
    },
  },

  computed: {
    targetIncome() {
      return this.projections?.income_drawdown?.target_income || 0;
    },

    requiredCapital() {
      // Calculate required capital based on 4.7% withdrawal rate
      const withdrawalRate = 0.047;
      return this.targetIncome / withdrawalRate;
    },
  },

  methods: {
    formatRiskLevel(level) {
      const levels = {
        low: 'Low',
        lower_medium: 'Lower-Medium',
        medium: 'Medium',
        upper_medium: 'Upper-Medium',
        high: 'High',
      };
      return levels[level] || 'Medium';
    },
  },
};
</script>

<style scoped>
.future-value-tab {
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

/* Empty State */
.empty-state {
  text-align: center;
  padding: 80px 40px;
  border-radius: 12px;
  @apply bg-light-blue-100 border border-light-gray;
}

.empty-icon {
  width: 64px;
  height: 64px;
  @apply text-horizon-400;
  margin: 0 auto 16px;
}

.empty-state p {
  @apply text-neutral-500;
  font-size: 18px;
  font-weight: 600;
  margin: 0 0 8px 0;
}

.empty-subtitle {
  @apply text-horizon-400;
  font-size: 14px;
  font-weight: 400;
}

/* Summary Cards */
.summary-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 20px;
  margin-bottom: 24px;
}

.summary-card {
  background: white;
  border-radius: 12px;
  padding: 20px;
  @apply border border-light-gray;
}

.summary-card.blue {
  @apply bg-gradient-to-br from-blue-50 to-blue-100;
  @apply border-violet-200;
}

.summary-card.purple {
  @apply bg-gradient-to-br from-purple-50 to-purple-100;
  @apply border-purple-300;
}

.summary-card.clickable {
  cursor: pointer;
  transition: all 0.15s ease;
}

.summary-card.clickable:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.summary-card.clickable:hover .arrow-icon {
  transform: translateX(4px);
}

.arrow-icon {
  display: inline-block;
  transition: transform 0.15s ease;
}

.summary-card.green {
  @apply bg-gradient-to-br from-green-50 to-green-100;
  @apply border-spring-200;
}

.summary-card.teal {
  @apply bg-gradient-to-br from-teal-50 to-teal-100;
  @apply border-teal-200;
}

.summary-card.red {
  @apply bg-gradient-to-br from-red-50 to-red-100;
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

.per-year {
  font-size: 14px;
  font-weight: 500;
  @apply text-neutral-500;
}

.summary-subtitle {
  font-size: 13px;
  @apply text-neutral-500;
  margin: 8px 0 0 0;
}

.chart-header {
  margin-bottom: 20px;
}

.chart-title {
  font-size: 18px;
  font-weight: 600;
  @apply text-horizon-500;
  margin: 0 0 12px 0;
}

.retirement-age-info {
  display: flex;
  align-items: center;
  gap: 20px;
  margin-bottom: 12px;
  padding: 12px 16px;
  @apply bg-teal-50;
  @apply border border-teal-200;
  border-radius: 8px;
}

.retirement-age-item {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.retirement-age-label {
  font-size: 12px;
  font-weight: 500;
  @apply text-teal-600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.retirement-age-value {
  font-size: 24px;
  font-weight: 700;
  @apply text-teal-800;
}

.retirement-age-divider {
  width: 1px;
  height: 40px;
  @apply bg-teal-300;
}

.chart-subtitle {
  font-size: 14px;
  @apply text-neutral-500;
  margin: 0;
}

.risk-badge {
  display: inline-block;
  padding: 2px 8px;
  @apply bg-violet-50;
  @apply text-violet-600;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 600;
  margin-left: 8px;
}

.depletion-badge {
  display: inline-block;
  padding: 2px 8px;
  @apply bg-raspberry-100;
  @apply text-raspberry-700;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 600;
  margin-left: 8px;
}

.success-badge {
  display: inline-block;
  padding: 2px 8px;
  @apply bg-spring-100;
  @apply text-spring-800;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 600;
  margin-left: 8px;
}

/* Info Panel */
.info-panel {
  display: flex;
  gap: 16px;
  @apply bg-violet-50;
  @apply border border-violet-200;
  border-radius: 12px;
  padding: 20px;
}

.info-icon-wrapper {
  flex-shrink: 0;
}

.info-icon {
  width: 24px;
  height: 24px;
  @apply text-violet-600;
}

.info-content {
  flex: 1;
}

.info-title {
  font-size: 14px;
  font-weight: 600;
  @apply text-violet-800;
  margin: 0 0 12px 0;
}

.info-list {
  list-style: none;
  padding: 0;
  margin: 0;
}

.info-list li {
  font-size: 14px;
  @apply text-violet-900;
  margin-bottom: 6px;
}

.info-list li strong {
  @apply text-violet-800;
}

.info-warning {
  font-size: 13px;
  @apply text-raspberry-800;
  @apply bg-raspberry-100;
  padding: 8px 12px;
  border-radius: 6px;
  margin: 12px 0 0 0;
}

@media (max-width: 768px) {
  .summary-grid {
    grid-template-columns: 1fr;
  }

  .info-panel {
    flex-direction: column;
  }
}
</style>
