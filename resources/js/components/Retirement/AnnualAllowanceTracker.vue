<template>
  <div class="annual-allowance-tracker bg-white rounded-lg shadow p-6">
    <div class="flex items-center justify-between mb-6">
      <h3 class="text-lg font-semibold text-gray-900">Annual Allowance Tracker</h3>
      <select
        v-model="selectedTaxYear"
        class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
      >
        <option value="2025/26">2025/26</option>
        <option value="2024/25">2024/25</option>
        <option value="2023/24">2023/24</option>
        <option value="2022/23">2022/23</option>
      </select>
    </div>

    <!-- Current Year Progress -->
    <div v-if="selectedTaxYear === '2025/26'" class="mb-8">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 sm:gap-0 mb-3">
        <span class="text-sm font-medium text-gray-700">Contributions Used</span>
        <div class="text-left sm:text-right">
          <span class="text-xl sm:text-2xl font-bold text-gray-900">
            {{ formatCurrency(contributionsUsed) }}
          </span>
          <span class="text-sm text-gray-500"> / {{ formatCurrency(currentAllowance) }}</span>
        </div>
      </div>

      <!-- Progress Bar -->
      <div class="relative w-full bg-gray-200 rounded-full h-4 mb-2">
        <div
          class="h-4 rounded-full transition-all duration-500"
          :class="progressBarColour"
          :style="{ width: progressPercent + '%' }"
        ></div>
      </div>

      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 sm:gap-0 text-sm">
        <span :class="statusTextColour" class="font-medium">
          {{ statusText }}
        </span>
        <span class="text-gray-600">
          {{ progressPercent }}% used
        </span>
      </div>

      <!-- Remaining Allowance -->
      <div class="mt-4 p-4 bg-gray-50 rounded-lg">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 sm:gap-0">
          <span class="text-sm text-gray-600">Remaining Allowance</span>
          <span class="text-lg font-bold" :class="remainingAllowance > 0 ? 'text-green-600' : 'text-red-600'">
            {{ formatCurrency(Math.max(0, remainingAllowance)) }}
          </span>
        </div>
      </div>
    </div>

    <!-- Historical View for Past Years -->
    <div v-else class="mb-8">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 sm:gap-0 mb-3">
        <span class="text-sm font-medium text-gray-700">Contributions Used ({{ selectedTaxYear }})</span>
        <span class="text-lg font-bold text-gray-900">
          {{ formatCurrency(getHistoricalContributions(selectedTaxYear)) }}
        </span>
      </div>

      <div class="relative w-full bg-gray-200 rounded-full h-4 mb-2">
        <div
          class="bg-blue-500 h-4 rounded-full"
          :style="{ width: getHistoricalPercent(selectedTaxYear) + '%' }"
        ></div>
      </div>

      <p class="text-sm text-gray-600 mt-2">
        Unused allowance: {{ formatCurrency(getHistoricalUnused(selectedTaxYear)) }}
      </p>
    </div>

    <!-- Carry Forward Available -->
    <div class="border-t border-gray-200 pt-6 mb-6">
      <div class="flex items-center mb-4">
        <svg class="w-5 h-5 text-indigo-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
        </svg>
        <h4 class="font-semibold text-gray-900">Carry Forward Available</h4>
      </div>

      <div class="space-y-3">
        <div
          v-for="year in carryForwardYears"
          :key="year.taxYear"
          class="flex items-center justify-between p-3 bg-gray-50 rounded-lg"
        >
          <div>
            <p class="text-sm font-medium text-gray-900">{{ year.taxYear }}</p>
            <p class="text-xs text-gray-500">Available to carry forward</p>
          </div>
          <div class="text-right">
            <p class="text-lg font-bold text-indigo-600">{{ formatCurrency(year.available) }}</p>
          </div>
        </div>
      </div>

      <div class="mt-4 p-4 bg-indigo-50 border border-indigo-200 rounded-lg">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 sm:gap-0">
          <span class="text-sm font-medium text-indigo-900">Total Available (with carry forward)</span>
          <span class="text-xl font-bold text-indigo-600">
            {{ formatCurrency(totalAvailableWithCarryForward) }}
          </span>
        </div>
      </div>
    </div>

    <!-- MPAA Warning (if applicable) -->
    <div v-if="mpaaTriggered" class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6 flex items-start">
      <svg class="w-5 h-5 text-red-600 mr-3 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
      </svg>
      <div>
        <p class="text-sm font-bold text-red-900">MPAA Triggered</p>
        <p class="text-sm text-red-800 mt-1">
          Your annual allowance is reduced to £10,000 because you've accessed pension benefits flexibly.
          Carry forward is not available under MPAA.
        </p>
      </div>
    </div>

    <!-- Tapered Allowance Info (if applicable) -->
    <div v-if="isTapered" class="bg-amber-50 border border-amber-200 rounded-lg p-4 flex items-start">
      <svg class="w-5 h-5 text-amber-600 mr-3 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
      </svg>
      <div>
        <p class="text-sm font-bold text-amber-900">Tapered Annual Allowance</p>
        <p class="text-sm text-amber-800 mt-1">
          Your annual allowance has been tapered to {{ formatCurrency(currentAllowance) }} based on your income level.
        </p>
      </div>
    </div>
  </div>
</template>

<script>
import { mapState } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'AnnualAllowanceTracker',
  mixins: [currencyMixin],

  data() {
    return {
      selectedTaxYear: '2025/26',
    };
  },

  computed: {
    ...mapState('retirement', ['annualAllowance', 'dcPensions', 'profile']),

    currentAllowance() {
      // Check if MPAA triggered
      if (this.mpaaTriggered) {
        return 10000;
      }
      // Check if tapered
      if (this.isTapered) {
        return this.annualAllowance?.tapered_allowance || 60000;
      }
      // Standard allowance
      return 60000;
    },

    calculatedContributions() {
      // Calculate total annual contributions from all DC pensions
      return this.dcPensions.reduce((total, pension) => {
        // For personal/SIPP pensions, use the monthly contribution amount
        if (pension.scheme_type === 'personal' || pension.scheme_type === 'sipp') {
          const monthlyAmount = parseFloat(pension.monthly_contribution_amount || 0);
          return total + (monthlyAmount * 12);
        }

        // For workplace pensions, calculate based on percentage of salary
        const employeePercent = parseFloat(pension.employee_contribution_percent || 0);
        const employerPercent = parseFloat(pension.employer_contribution_percent || 0);
        const totalPercent = employeePercent + employerPercent;

        // Use annual_salary if available, otherwise use profile income, otherwise estimate
        const salary = parseFloat(pension.annual_salary || this.profile?.current_income || 50000);
        return total + ((salary * totalPercent) / 100);
      }, 0);
    },

    contributionsUsed() {
      // Use backend data if available, otherwise calculate from dcPensions
      return this.annualAllowance?.contributions_used || this.calculatedContributions;
    },

    remainingAllowance() {
      return this.currentAllowance - this.contributionsUsed;
    },

    progressPercent() {
      return Math.min(100, Math.round((this.contributionsUsed / this.currentAllowance) * 100));
    },

    progressBarColour() {
      if (this.progressPercent >= 100) return 'bg-red-500';
      if (this.progressPercent >= 80) return 'bg-amber-500';
      if (this.progressPercent >= 60) return 'bg-yellow-500';
      return 'bg-green-500';
    },

    statusTextColour() {
      if (this.progressPercent >= 100) return 'text-red-600';
      if (this.progressPercent >= 80) return 'text-amber-600';
      return 'text-green-600';
    },

    statusText() {
      if (this.progressPercent >= 100) return 'Allowance Exceeded';
      if (this.progressPercent >= 80) return 'Approaching Limit';
      return 'On Track';
    },

    mpaaTriggered() {
      return this.annualAllowance?.mpaa_triggered || false;
    },

    isTapered() {
      return this.annualAllowance?.is_tapered || false;
    },

    carryForwardYears() {
      // Calculate carry forward from previous 3 years
      const years = [];
      const currentYear = '2025/26';

      if (!this.mpaaTriggered) {
        years.push(
          { taxYear: '2024/25', available: this.getHistoricalUnused('2024/25') },
          { taxYear: '2023/24', available: this.getHistoricalUnused('2023/24') },
          { taxYear: '2022/23', available: this.getHistoricalUnused('2022/23') }
        );
      }

      return years;
    },

    totalAvailableWithCarryForward() {
      const carryForwardTotal = this.carryForwardYears.reduce((sum, year) => sum + year.available, 0);
      return this.remainingAllowance + carryForwardTotal;
    },
  },

  methods: {
    getHistoricalContributions(taxYear) {
      // TODO: This should come from the backend API
      // For now, return 0 to avoid showing fake data
      // Users should only see their actual contributions
      return 0;
    },

    getHistoricalUnused(taxYear) {
      const standardAllowance = 60000;
      const used = this.getHistoricalContributions(taxYear);
      return Math.max(0, standardAllowance - used);
    },

    getHistoricalPercent(taxYear) {
      const used = this.getHistoricalContributions(taxYear);
      return Math.min(100, Math.round((used / 60000) * 100));
    },
  },

  async mounted() {
    // Fetch annual allowance data
    try {
      await this.$store.dispatch('retirement/fetchAnnualAllowance', '2025/26');
    } catch (error) {
      console.error('Failed to fetch annual allowance:', error);
    }
  },
};
</script>

<style scoped>
/* Progress bar animation */
.bg-green-500,
.bg-yellow-500,
.bg-amber-500,
.bg-red-500 {
  transition: width 0.5s ease-out, background-colour 0.3s ease;
}
</style>
