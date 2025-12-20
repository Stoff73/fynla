<template>
  <div class="future-value-tab">
    <!-- Loading State -->
    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <p>Running projections...</p>
    </div>

    <!-- No DC Pensions State -->
    <div v-else-if="!projections || !projections.pension_pot_projection?.dc_pension_count" class="empty-state">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="empty-icon">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
      </svg>
      <p>No DC pensions found</p>
      <p class="empty-subtitle">Add money purchase pensions to see future value projections</p>
    </div>

    <!-- Projections Content -->
    <template v-else>
      <!-- Summary Cards -->
      <div class="summary-grid">
        <div class="summary-card blue">
          <p class="summary-label">Projected Pot at Retirement</p>
          <p class="summary-value">{{ formatCurrency(projections.pension_pot_projection?.percentile_5_at_retirement) }}</p>
          <p class="summary-subtitle">Age {{ projections.pension_pot_projection?.retirement_age }} (95% probability)</p>
        </div>
        <div :class="['summary-card', onTrackClass]">
          <p class="summary-label">Retirement Readiness</p>
          <p class="summary-value">{{ projections.income_drawdown?.on_track_status }}</p>
          <p class="summary-subtitle">Based on {{ formatCurrency(projections.income_drawdown?.starting_pot) }} starting pot</p>
        </div>
        <div class="summary-card amber">
          <p class="summary-label">Target Retirement Income</p>
          <p class="summary-value">{{ formatCurrency(projections.income_drawdown?.target_income) }}<span class="per-year">/year</span></p>
          <p class="summary-subtitle">75% of current after-tax income</p>
        </div>
      </div>

      <!-- Pension Pot Projection Chart -->
      <div class="chart-card">
        <div class="chart-header">
          <h3 class="chart-title">Pension Pot Projection</h3>
          <p class="chart-subtitle">
            Monte Carlo simulation for {{ projections.pension_pot_projection?.dc_pension_count }} DC pension(s) to age {{ projections.pension_pot_projection?.retirement_age }}
            <span class="risk-badge">{{ formatRiskLevel(projections.pension_pot_projection?.risk_level) }} Risk</span>
          </p>
        </div>
        <PensionPotProjectionChart :data="projections.pension_pot_projection" />
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
            <li>DB Pension Income: <strong>{{ formatCurrency(projections.income_drawdown?.guaranteed_income?.db_pensions) }}/year</strong></li>
            <li>State Pension: <strong>{{ formatCurrency(projections.income_drawdown?.guaranteed_income?.state_pension) }}/year</strong></li>
            <li>Total Guaranteed: <strong>{{ formatCurrency(projections.income_drawdown?.guaranteed_income?.total) }}/year</strong></li>
          </ul>
          <p v-if="projections.income_drawdown?.fund_depletion_age" class="info-warning">
            DC fund projected to deplete at age {{ projections.income_drawdown.fund_depletion_age }}
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

export default {
  name: 'FutureValueTab',

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
    onTrackClass() {
      const status = this.projections?.income_drawdown?.on_track_status;
      if (status === 'Excellent' || status === 'On Track') {
        return 'green';
      }
      if (status === 'Needs Attention') {
        return 'amber';
      }
      return 'red';
    },
  },

  methods: {
    formatCurrency(value) {
      if (value === null || value === undefined) return '£0';
      return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: 'GBP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
      }).format(value);
    },

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
  animation: fadeIn 0.3s ease-out;
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
  width: 48px;
  height: 48px;
  border: 3px solid #e5e7eb;
  border-top-color: #3b82f6;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-bottom: 16px;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.loading-state p {
  color: #6b7280;
  font-size: 16px;
  margin: 0;
}

/* Empty State */
.empty-state {
  text-align: center;
  padding: 80px 40px;
  background: white;
  border-radius: 12px;
  border: 2px dashed #d1d5db;
}

.empty-icon {
  width: 64px;
  height: 64px;
  color: #9ca3af;
  margin: 0 auto 16px;
}

.empty-state p {
  color: #6b7280;
  font-size: 18px;
  font-weight: 600;
  margin: 0 0 8px 0;
}

.empty-subtitle {
  color: #9ca3af;
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
  border: 1px solid #e5e7eb;
}

.summary-card.blue {
  background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
  border-color: #bfdbfe;
}

.summary-card.green {
  background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
  border-color: #a7f3d0;
}

.summary-card.amber {
  background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
  border-color: #fde68a;
}

.summary-card.red {
  background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
  border-color: #fecaca;
}

.summary-label {
  font-size: 14px;
  color: #6b7280;
  margin: 0 0 8px 0;
  font-weight: 500;
}

.summary-value {
  font-size: 28px;
  font-weight: 700;
  color: #111827;
  margin: 0;
}

.per-year {
  font-size: 14px;
  font-weight: 500;
  color: #6b7280;
}

.summary-subtitle {
  font-size: 13px;
  color: #6b7280;
  margin: 8px 0 0 0;
}

/* Chart Cards */
.chart-card {
  background: white;
  border-radius: 12px;
  padding: 24px;
  border: 1px solid #e5e7eb;
  margin-bottom: 24px;
}

.chart-header {
  margin-bottom: 20px;
}

.chart-title {
  font-size: 18px;
  font-weight: 600;
  color: #111827;
  margin: 0 0 8px 0;
}

.chart-subtitle {
  font-size: 14px;
  color: #6b7280;
  margin: 0;
}

.risk-badge {
  display: inline-block;
  padding: 2px 8px;
  background: #eff6ff;
  color: #2563eb;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 600;
  margin-left: 8px;
}

.depletion-badge {
  display: inline-block;
  padding: 2px 8px;
  background: #fef3c7;
  color: #b45309;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 600;
  margin-left: 8px;
}

.success-badge {
  display: inline-block;
  padding: 2px 8px;
  background: #d1fae5;
  color: #065f46;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 600;
  margin-left: 8px;
}

/* Info Panel */
.info-panel {
  display: flex;
  gap: 16px;
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  border-radius: 12px;
  padding: 20px;
}

.info-icon-wrapper {
  flex-shrink: 0;
}

.info-icon {
  width: 24px;
  height: 24px;
  color: #2563eb;
}

.info-content {
  flex: 1;
}

.info-title {
  font-size: 14px;
  font-weight: 600;
  color: #1e40af;
  margin: 0 0 12px 0;
}

.info-list {
  list-style: none;
  padding: 0;
  margin: 0;
}

.info-list li {
  font-size: 14px;
  color: #1e3a8a;
  margin-bottom: 6px;
}

.info-list li strong {
  color: #1e40af;
}

.info-warning {
  font-size: 13px;
  color: #92400e;
  background: #fef3c7;
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
