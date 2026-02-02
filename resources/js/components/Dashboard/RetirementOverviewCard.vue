<template>
  <div class="card w-full !h-auto">
    <!-- Clickable content area -->
    <div
      class="cursor-pointer hover:bg-gray-50 -m-6 p-6 rounded-lg transition-colors"
      @click="navigateToRetirement"
    >
      <!-- RETIRED VIEW: Retirement Income -->
      <template v-if="isRetired">
        <!-- Header -->
        <h3 class="text-lg font-bold text-gray-900 mb-3">Retirement Income</h3>

        <!-- Loading State -->
        <div v-if="retirementIncomeLoading" class="h-[180px] flex items-center justify-center">
          <div class="flex items-center gap-2 text-gray-500">
            <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span class="text-sm">Loading...</span>
          </div>
        </div>

        <template v-else>
          <!-- Income Stats Grid -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
            <div class="stat-item">
              <span class="stat-label">Target Income</span>
              <span class="stat-value text-blue-600">{{ formatCurrencyCompact(retiredTargetIncome) }}</span>
            </div>
            <div class="stat-item">
              <span class="stat-label">Net Income</span>
              <span class="stat-value text-green-600">{{ formatCurrencyCompact(retiredNetIncome) }}</span>
            </div>
            <div class="stat-item">
              <span class="stat-label">Tax Rate</span>
              <span class="stat-value text-purple-600">{{ formatPercent(retiredEffectiveTaxRate) }}</span>
            </div>
            <div class="stat-item">
              <span class="stat-label">Total Capital</span>
              <span class="stat-value text-teal-600">{{ formatCurrencyCompact(retiredTotalCapital) }}</span>
            </div>
          </div>

          <!-- Income Sources -->
          <div class="flex gap-4">
            <!-- Sources List (left side) -->
            <div class="flex-1">
              <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Income Sources</div>
              <div v-if="retiredIncomeSources.length > 0" class="space-y-2">
                <div v-for="source in retiredIncomeSources" :key="source.key" class="income-source-item">
                  <div class="source-info">
                    <span class="source-name">{{ source.name }}</span>
                    <span class="source-type">{{ source.type }}</span>
                  </div>
                  <span class="source-value">{{ formatCurrencyCompact(source.amount) }}/yr</span>
                </div>
              </div>
              <div v-else class="text-xs text-gray-400 italic">No income sources configured</div>
            </div>

            <!-- Summary (right side) -->
            <div class="w-48 flex-shrink-0">
              <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Annual Breakdown</div>
              <div class="breakdown-box">
                <div class="breakdown-row">
                  <span class="breakdown-label">Gross Income</span>
                  <span class="breakdown-value">{{ formatCurrencyCompact(retiredGrossIncome) }}</span>
                </div>
                <div class="breakdown-row">
                  <span class="breakdown-label">Tax</span>
                  <span class="breakdown-value text-red-600">-{{ formatCurrencyCompact(retiredTaxPaid) }}</span>
                </div>
                <div class="breakdown-row total">
                  <span class="breakdown-label">Net Income</span>
                  <span class="breakdown-value text-green-600">{{ formatCurrencyCompact(retiredNetIncome) }}</span>
                </div>
              </div>
            </div>
          </div>
        </template>
      </template>

      <!-- NON-RETIRED VIEW: Projection Card -->
      <template v-else>
        <!-- Header -->
        <h3 class="text-lg font-bold text-gray-900 mb-3">Retirement</h3>

        <!-- Stats Grid -->
        <div class="grid grid-cols-3 sm:grid-cols-6 gap-3 mb-4">
          <div class="stat-item">
            <span class="stat-label">Target Income</span>
            <span class="stat-value text-blue-600">{{ formatCurrencyCompact(targetIncome) }}</span>
          </div>
          <div class="stat-item">
            <span class="stat-label">Projected Income</span>
            <span class="stat-value text-green-600">{{ formatCurrencyCompact(projectedIncome) }}</span>
          </div>
          <div class="stat-item">
            <span class="stat-label">Required Capital</span>
            <span class="stat-value text-purple-600">{{ formatCurrencyCompact(requiredCapitalValue) }}</span>
          </div>
          <div class="stat-item">
            <span class="stat-label">Projected Capital</span>
            <span class="stat-value text-teal-600">{{ formatCurrencyCompact(projectedCapital) }}</span>
          </div>
          <div class="stat-item">
            <span class="stat-label">Retirement Age</span>
            <span class="stat-value text-primary-600">{{ retirementAge }}</span>
          </div>
          <div class="stat-item">
            <span class="stat-label">Allowance Used</span>
            <span class="stat-value text-rose-600">{{ formatCurrencyCompact(allowanceUsed) }}</span>
          </div>
        </div>

        <!-- Pensions List + Chart -->
        <div class="flex gap-4">
          <!-- Pensions List (left side) -->
          <div class="w-48 flex-shrink-0">
            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Your Pensions</div>
            <div v-if="dcPensions && dcPensions.length > 0" class="space-y-2">
              <div v-for="pension in dcPensions" :key="pension.id" class="pension-item">
                <span class="pension-name">{{ pension.scheme_name || 'Pension' }}</span>
                <span class="pension-value">{{ formatCurrencyCompact(pension.current_fund_value) }}</span>
              </div>
            </div>
            <div v-else class="text-xs text-gray-400 italic">No pensions added</div>
          </div>

          <!-- Chart (right side) -->
          <div class="flex-1 min-w-0">
            <div v-if="projectionsLoading" class="h-[120px] flex items-center justify-center">
              <div class="flex items-center gap-2 text-gray-500">
                <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span class="text-sm">Loading...</span>
              </div>
            </div>
            <div v-else-if="hasProjectionData" class="pension-chart-container">
              <PensionPotProjectionChart
                :data="projections.pension_pot_projection"
                :height="120"
                :show-legend="false"
                :show-axes="false"
                :show-toolbar="false"
                :single-line="true"
                :key="chartKey"
              />
            </div>
            <div v-else class="h-[120px] flex items-center justify-center bg-gray-50 rounded-lg border border-dashed border-gray-300">
              <p class="text-sm text-gray-500">Add DC pensions to see projections</p>
            </div>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>

<script>
import { mapState, mapGetters, mapActions } from 'vuex';
import PensionPotProjectionChart from '@/components/Retirement/PensionPotProjectionChart.vue';

export default {
  name: 'RetirementOverviewCard',

  components: {
    PensionPotProjectionChart,
  },

  data() {
    return {
      chartKey: 0,
    };
  },

  computed: {
    ...mapState('retirement', [
      'dcPensions',
      'projections',
      'projectionsLoading',
      'annualAllowance',
      'requiredCapital',
      'retirementIncome',
      'retirementIncomeLoading',
      'incomeAllocations',
    ]),

    // Check if user is already retired (current age >= retirement age)
    isRetired() {
      const projection = this.projections?.pension_pot_projection;
      if (!projection) return false;
      const currentAge = projection.current_age;
      const retirementAge = projection.retirement_age;
      // If current_age >= retirement_age, user is retired
      return currentAge !== undefined && retirementAge !== undefined && currentAge >= retirementAge;
    },

    hasProjectionData() {
      return this.projections?.pension_pot_projection?.year_by_year?.length > 0;
    },

    // ============ NON-RETIRED (Projection) computed properties ============

    // Target income from projections API
    targetIncome() {
      return this.projections?.income_drawdown?.target_income || 0;
    },

    // Projected income - first year's total income from drawdown projection
    projectedIncome() {
      return this.projections?.income_drawdown?.yearly_income?.[0]?.total_income || 0;
    },

    // Required capital from requiredCapital API
    requiredCapitalValue() {
      return this.requiredCapital?.required_capital_today || 0;
    },

    // Projected capital at retirement (80% probability) from projections API
    projectedCapital() {
      return this.projections?.pension_pot_projection?.percentile_20_at_retirement || 0;
    },

    // Retirement age from projections API
    retirementAge() {
      return this.projections?.pension_pot_projection?.retirement_age || 67;
    },

    // Allowance used value from annualAllowance API
    allowanceUsed() {
      return this.annualAllowance?.total_contributions || 0;
    },

    // ============ RETIRED (Income) computed properties ============

    // Target income for retired users
    retiredTargetIncome() {
      return this.retirementIncome?.optimised_income ||
             this.retirementIncome?.target_income ||
             0;
    },

    // Fund projections for retired users
    retiredFundProjections() {
      return this.retirementIncome?.fund_projections || [];
    },

    // Gross income for first year
    retiredGrossIncome() {
      if (this.retiredFundProjections.length === 0) return 0;
      const firstYear = this.retiredFundProjections[0];
      return (firstYear.total_income || 0) +
             (firstYear.state_pension || 0) +
             (firstYear.db_pension || 0);
    },

    // Tax paid for first year
    retiredTaxPaid() {
      if (this.retiredFundProjections.length === 0) return 0;
      return this.retiredFundProjections[0].tax_paid || 0;
    },

    // Net income after tax
    retiredNetIncome() {
      return this.retiredGrossIncome - this.retiredTaxPaid;
    },

    // Effective tax rate
    retiredEffectiveTaxRate() {
      if (this.retiredGrossIncome <= 0) return 0;
      return this.retiredTaxPaid / this.retiredGrossIncome;
    },

    // Total capital at start of retirement
    retiredTotalCapital() {
      if (this.retiredFundProjections.length === 0) return 0;
      const firstYear = this.retiredFundProjections[0];
      // Sum all fund balances at start
      return (firstYear.pension_pot_start || 0) +
             (firstYear.isa_start || 0) +
             (firstYear.bond_start || 0) +
             (firstYear.gia_start || 0);
    },

    // Income sources for retired users
    retiredIncomeSources() {
      const allocations = this.incomeAllocations?.length > 0
        ? this.incomeAllocations
        : (this.retirementIncome?.allocations || []);

      // Filter to only include sources with amounts > 0
      return allocations
        .filter(a => (a.annual_amount || 0) > 0)
        .map(a => ({
          key: `${a.source_type}-${a.source_id}`,
          name: a.name || this.formatSourceName(a.source_type),
          type: this.formatSourceType(a.source_type),
          amount: a.annual_amount || 0,
        }))
        .slice(0, 5); // Limit to 5 sources for compact view
    },
  },

  methods: {
    ...mapActions('retirement', [
      'fetchProjections',
      'fetchRequiredCapital',
      'fetchAnnualAllowance',
      'fetchRetirementIncome',
    ]),

    navigateToRetirement() {
      this.$router.push('/net-worth/retirement');
    },

    formatCurrencyCompact(value) {
      if (value === null || value === undefined || value === 0) return '£0';
      if (value >= 1000000) {
        return '£' + (value / 1000000).toFixed(1) + 'M';
      }
      if (value >= 1000) {
        return '£' + (value / 1000).toFixed(0) + 'K';
      }
      return '£' + Math.round(value).toLocaleString();
    },

    formatPercent(value) {
      return (value * 100).toFixed(1) + '%';
    },

    formatSourceType(type) {
      const typeMap = {
        pension_pot: 'DC Pension',
        pension_pot_pcls: 'Tax-Free Cash',
        pension_pot_drawdown: 'Drawdown',
        db_pension: 'DB Pension',
        state_pension: 'State Pension',
        isa: 'ISA',
        isa_investment: 'S&S ISA',
        isa_cash: 'Cash ISA',
        onshore_bond: 'Bond',
        offshore_bond: 'Bond',
        gia: 'GIA',
        savings: 'Savings',
      };
      return typeMap[type] || 'Other';
    },

    formatSourceName(type) {
      const nameMap = {
        pension_pot: 'Pension Drawdown',
        pension_pot_pcls: 'Tax-Free Cash',
        pension_pot_drawdown: 'Pension Drawdown',
        db_pension: 'Defined Benefit',
        state_pension: 'State Pension',
      };
      return nameMap[type] || type?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) || 'Unknown';
    },
  },

  async mounted() {
    const promises = [];

    // Always fetch projections to get age data (needed to determine if retired)
    if (!this.projections) {
      promises.push(
        this.fetchProjections().then(() => {
          this.chartKey++;
        }).catch(() => {})
      );
    }

    // Fetch required capital if not loaded
    if (!this.requiredCapital) {
      promises.push(this.fetchRequiredCapital().catch(() => {}));
    }

    // Fetch annual allowance if not loaded (only relevant for non-retired)
    if (!this.annualAllowance) {
      promises.push(this.fetchAnnualAllowance().catch(() => {}));
    }

    await Promise.all(promises);

    // If retired, also fetch retirement income data
    if (this.isRetired && !this.retirementIncome) {
      this.fetchRetirementIncome().catch(() => {});
    }
  },

  watch: {
    // Re-fetch projections when DC pensions change
    'dcPensions.length': {
      handler(newLen, oldLen) {
        if (newLen !== oldLen) {
          this.fetchProjections().then(() => {
            this.chartKey++;
          }).catch(() => {});
        }
      },
    },

    // When isRetired changes (after projections load), fetch retirement income
    isRetired: {
      handler(retired) {
        if (retired && !this.retirementIncome) {
          this.fetchRetirementIncome().catch(() => {});
        }
      },
    },
  },
};
</script>

<style scoped>
.stat-item {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.stat-label {
  font-size: 11px;
  @apply text-gray-500;
  font-weight: 500;
}

.stat-value {
  font-size: 14px;
  font-weight: 600;
}

.pension-item {
  display: flex;
  flex-direction: column;
  padding: 6px 8px;
  background: #f9fafb;
  border-radius: 6px;
}

.pension-name {
  font-size: 12px;
  font-weight: 500;
  @apply text-gray-700;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.pension-value {
  font-size: 13px;
  font-weight: 600;
  @apply text-primary-600;
}

.pension-chart-container {
  height: 120px;
}

.pension-chart-container :deep(.pension-pot-chart) {
  height: 100%;
}

.pension-chart-container :deep(.apexcharts-canvas) {
  height: 100% !important;
}

.pension-chart-container :deep(.chart-placeholder) {
  height: 100%;
}

/* Hide chart legend and footer in compact mode */
.pension-chart-container :deep(.apexcharts-legend) {
  display: none !important;
}

.pension-chart-container :deep(.chart-footer) {
  display: none;
}

/* Retired View Styles */
.income-source-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 10px;
  background: #f9fafb;
  border-radius: 6px;
}

.source-info {
  display: flex;
  flex-direction: column;
  gap: 1px;
  min-width: 0;
  flex: 1;
}

.source-name {
  font-size: 12px;
  font-weight: 600;
  @apply text-gray-800;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.source-type {
  font-size: 10px;
  @apply text-gray-500;
}

.source-value {
  font-size: 13px;
  font-weight: 600;
  @apply text-green-600;
  flex-shrink: 0;
  margin-left: 8px;
}

.breakdown-box {
  background: #f9fafb;
  border-radius: 8px;
  padding: 12px;
}

.breakdown-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 4px 0;
}

.breakdown-row.total {
  border-top: 1px solid #e5e7eb;
  margin-top: 8px;
  padding-top: 8px;
}

.breakdown-label {
  font-size: 12px;
  @apply text-gray-600;
}

.breakdown-value {
  font-size: 13px;
  font-weight: 600;
  @apply text-gray-800;
}

.breakdown-row.total .breakdown-label {
  font-weight: 600;
  @apply text-gray-700;
}

.breakdown-row.total .breakdown-value {
  font-weight: 700;
}

</style>
