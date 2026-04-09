<template>
  <div class="projections">
    <div class="mb-6">
      <h2 class="text-2xl font-bold text-horizon-500">Retirement Projections</h2>
      <p class="text-neutral-500 mt-1">Visualize your projected retirement income and pension growth</p>
    </div>

    <!-- Income Projection Chart -->
    <div class="bg-white rounded-lg shadow p-6 mb-8">
      <div class="flex items-center justify-between mb-6">
        <h3 class="text-lg font-semibold text-horizon-500">Projected Retirement Income</h3>
        <div class="flex items-center space-x-2">
          <span class="text-sm text-neutral-500">View:</span>
          <select
            v-model="incomeViewMode"
            class="px-3 py-1 border border-horizon-300 rounded text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent"
          >
            <option value="stacked">Stacked</option>
            <option value="individual">Individual Sources</option>
          </select>
        </div>
      </div>

      <IncomeProjectionChart
        :view-mode="incomeViewMode"
        :projected-income="projectedIncome"
        :target-income="targetIncome"
      />

      <!-- Legend -->
      <div class="mt-6 flex flex-wrap items-center justify-center gap-4">
        <div class="flex items-center">
          <div class="w-4 h-4 bg-violet-500 rounded mr-2"></div>
          <span class="text-sm text-neutral-500">Defined Contribution Pension</span>
        </div>
        <div class="flex items-center">
          <div class="w-4 h-4 bg-purple-500 rounded mr-2"></div>
          <span class="text-sm text-neutral-500">Defined Benefit Pension</span>
        </div>
        <div class="flex items-center">
          <div class="w-4 h-4 bg-spring-500 rounded mr-2"></div>
          <span class="text-sm text-neutral-500">State Pension</span>
        </div>
        <div class="flex items-center">
          <div class="w-3 h-0.5 bg-violet-500 mr-2"></div>
          <span class="text-sm text-neutral-500">Target Income</span>
        </div>
      </div>
    </div>

    <!-- Accumulation Chart -->
    <div class="bg-white rounded-lg shadow p-6 mb-8">
      <h3 class="text-lg font-semibold text-horizon-500 mb-6">Pension Pot Accumulation</h3>
      <AccumulationChart />
      <p class="text-sm text-neutral-500 mt-4 text-center">
        Shows projected Defined Contribution pension fund value growth to retirement
      </p>
    </div>

    <!-- Key Metrics Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
      <!-- Projected Annual Income at Retirement -->
      <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between mb-2">
          <h4 class="text-sm font-medium text-neutral-500">Projected Annual Income</h4>
          <svg class="w-5 h-5 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
          </svg>
        </div>
        <p class="text-3xl font-bold text-horizon-500">{{ formatCurrency(projectedIncome) }}</p>
        <p class="text-sm text-neutral-500 mt-1">At age {{ targetRetirementAge }}</p>
      </div>

      <!-- Replacement Ratio -->
      <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between mb-2">
          <h4 class="text-sm font-medium text-neutral-500">Income Replacement</h4>
          <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
          </svg>
        </div>
        <p class="text-3xl font-bold text-horizon-500">{{ replacementRatio }}%</p>
        <p class="text-sm text-neutral-500 mt-1">Of current income</p>
      </div>

      <!-- Projected Pension Pot at Retirement -->
      <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between mb-2">
          <h4 class="text-sm font-medium text-neutral-500">Projected Pension Pot</h4>
          <svg class="w-5 h-5 text-spring-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
          </svg>
        </div>
        <p class="text-3xl font-bold text-horizon-500">{{ formatCurrency(projectedPot) }}</p>
        <p class="text-sm text-neutral-500 mt-1">Defined Contribution pensions only</p>
      </div>
    </div>

    <!-- Assumptions -->
    <div class="bg-violet-50 border border-violet-200 rounded-lg p-6">
      <div class="flex items-start">
        <svg class="w-5 h-5 text-violet-600 mr-3 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <div class="flex-1">
          <p class="text-sm font-semibold text-violet-900 mb-2">Projection Assumptions</p>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm text-violet-800">
            <div>• Defined Contribution pension growth: 5% p.a. (before retirement)</div>
            <div>• Drawdown rate: 4% p.a. (after retirement)</div>
            <div>• Defined Benefit pensions: Fixed income (indexed with inflation)</div>
            <div>• State Pension: Based on your forecast</div>
            <div>• Inflation: 2.5% p.a.</div>
            <div>• Life expectancy: Age {{ lifeExpectancy }}</div>
          </div>
          <p class="text-xs text-violet-700 mt-3">
            These are estimates only. Actual returns may vary. Pension values can go down as well as up.
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { mapState, mapGetters } from 'vuex';
import IncomeProjectionChart from '../../components/Retirement/IncomeProjectionChart.vue';
import AccumulationChart from '../../components/Retirement/AccumulationChart.vue';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'RetirementProjections',
  mixins: [currencyMixin],

  components: {
    IncomeProjectionChart,
    AccumulationChart,
  },

  data() {
    return {
      incomeViewMode: 'stacked',
    };
  },

  computed: {
    ...mapState('retirement', ['profile', 'analysis']),
    ...mapGetters('retirement', [
      'totalPensionWealth',
      'projectedIncome',
      'targetIncome',
      'yearsToRetirement',
    ]),

    targetRetirementAge() {
      return this.profile?.target_retirement_age || 67;
    },

    lifeExpectancy() {
      return this.profile?.life_expectancy || 90;
    },

    replacementRatio() {
      const currentIncome = this.profile?.current_income || 0;
      if (currentIncome === 0) return 0;
      return Math.round((this.projectedIncome / currentIncome) * 100);
    },

    projectedPot() {
      // In a real app, this would come from the analysis
      // For now, project based on current values
      return this.analysis?.projected_fund_value || this.totalPensionWealth * 1.5;
    },
  },

  // formatCurrency provided by currencyMixin
};
</script>

<style scoped>
.projections > div {
  animation: fadeInSlideUp 0.5s ease-out;
}
</style>
