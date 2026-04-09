<template>
  <div class="annual-allowance-tracker bg-white rounded-lg shadow p-6">
    <div class="flex items-center justify-between mb-6">
      <h3 class="text-lg font-semibold text-horizon-500">Annual Allowance Tracker</h3>
      <select
        v-model="selectedTaxYear"
        class="px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
      >
        <option v-for="year in taxYearOptions" :key="year" :value="year">{{ year }}</option>
      </select>
    </div>

    <!-- Current Year Progress -->
    <div v-if="selectedTaxYear === currentTaxYear" class="mb-8">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 sm:gap-0 mb-3">
        <span class="text-sm font-medium text-neutral-500">Contributions Used</span>
        <div class="text-left sm:text-right">
          <span class="text-xl sm:text-2xl font-bold text-horizon-500">
            {{ formatCurrency(contributionsUsed) }}
          </span>
          <span class="text-sm text-neutral-500"> / {{ formatCurrency(currentAllowance) }}</span>
        </div>
      </div>

      <!-- Progress Bar -->
      <div class="relative w-full bg-savannah-200 rounded-full h-4 mb-2">
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
        <span class="text-neutral-500">
          {{ progressPercent }}% used
        </span>
      </div>

      <!-- Remaining Allowance -->
      <div class="mt-4 p-4 bg-savannah-100 rounded-lg">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 sm:gap-0">
          <span class="text-sm text-neutral-500">Remaining Allowance</span>
          <span class="text-lg font-bold" :class="remainingAllowance > 0 ? 'text-spring-600' : 'text-raspberry-600'">
            {{ formatCurrency(Math.max(0, remainingAllowance)) }}
          </span>
        </div>
      </div>
    </div>

    <!-- Historical View for Past Years -->
    <div v-else class="mb-8">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 sm:gap-0 mb-3">
        <span class="text-sm font-medium text-neutral-500">Contributions Used ({{ selectedTaxYear }})</span>
        <span class="text-lg font-bold text-horizon-500">
          <template v-if="getHistoricalContributions(selectedTaxYear) !== null">
            {{ formatCurrency(getHistoricalContributions(selectedTaxYear)) }}
          </template>
          <template v-else>
            <span class="text-neutral-400 text-sm font-normal italic">Not yet tracked</span>
          </template>
        </span>
      </div>

      <div v-if="getHistoricalContributions(selectedTaxYear) !== null" class="relative w-full bg-savannah-200 rounded-full h-4 mb-2">
        <div
          class="bg-violet-500 h-4 rounded-full"
          :style="{ width: getHistoricalPercent(selectedTaxYear) + '%' }"
        ></div>
      </div>
      <div v-else class="bg-savannah-100 rounded-lg p-3 mb-2">
        <p class="text-sm text-neutral-500 italic">
          Historical contribution data will be available in a future update.
        </p>
      </div>

      <p class="text-sm text-neutral-500 mt-2">
        <template v-if="getHistoricalUnused(selectedTaxYear) !== null">
          Unused allowance: {{ formatCurrency(getHistoricalUnused(selectedTaxYear)) }}
        </template>
      </p>
    </div>

    <!-- Carry Forward Available -->
    <div class="border-t border-light-gray pt-6 mb-6">
      <div class="flex items-center mb-4">
        <svg class="w-5 h-5 text-violet-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
        </svg>
        <h4 class="font-semibold text-horizon-500">Carry Forward Available</h4>
      </div>

      <div class="space-y-3">
        <div
          v-for="year in carryForwardYears"
          :key="year.taxYear"
          class="flex items-center justify-between p-3 bg-savannah-100 rounded-lg"
        >
          <div>
            <p class="text-sm font-medium text-horizon-500">{{ year.taxYear }}</p>
            <p class="text-xs text-neutral-500">Available to carry forward</p>
          </div>
          <div class="text-right">
            <p v-if="year.available !== null" class="text-lg font-bold text-violet-600">{{ formatCurrency(year.available) }}</p>
            <p v-else class="text-sm text-neutral-400 italic">Not yet tracked</p>
          </div>
        </div>
      </div>

      <div class="mt-4 p-4 bg-savannah-100 rounded-lg">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 sm:gap-0">
          <span class="text-sm font-medium text-violet-900">Total Available (with carry forward)</span>
          <span class="text-xl font-bold text-violet-600">
            {{ formatCurrency(totalAvailableWithCarryForward) }}
          </span>
        </div>
      </div>
    </div>

    <!-- MPAA Warning (if applicable) -->
    <div v-if="mpaaTriggered" class="bg-savannah-100 rounded-lg p-4 mb-6 flex items-start">
      <svg class="w-5 h-5 text-raspberry-600 mr-3 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
      </svg>
      <div>
        <p class="text-sm font-bold text-raspberry-900">Money Purchase Annual Allowance Triggered</p>
        <p class="text-sm text-raspberry-800 mt-1">
          Your annual allowance is reduced to £{{ mpaaLimit.toLocaleString() }} because you've accessed pension benefits flexibly.
          Carry forward is not available under the Money Purchase Annual Allowance.
        </p>
      </div>
    </div>

    <!-- Tapered Allowance Info (if applicable) -->
    <div v-if="isTapered" class="bg-savannah-100 rounded-lg p-4 flex items-start">
      <svg class="w-5 h-5 text-violet-600 mr-3 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
      </svg>
      <div>
        <p class="text-sm font-bold text-violet-900">Tapered Annual Allowance</p>
        <p class="text-sm text-violet-800 mt-1">
          Your annual allowance has been tapered to {{ formatCurrency(currentAllowance) }} based on your income level.
        </p>
      </div>
    </div>
  </div>
</template>

<script>
import { mapState } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';
import { ANNUAL_ALLOWANCE, MONEY_PURCHASE_ANNUAL_ALLOWANCE } from '@/constants/taxConfig';
import { getCurrentTaxYear } from '@/utils/dateFormatter';
import logger from '@/utils/logger';
export default {
  name: 'AnnualAllowanceTracker',
  mixins: [currencyMixin],

  data() {
    return {
      selectedTaxYear: getCurrentTaxYear(),
      mpaaLimit: MONEY_PURCHASE_ANNUAL_ALLOWANCE,
    };
  },

  computed: {
    ...mapState('retirement', ['annualAllowance', 'dcPensions', 'profile']),

    currentTaxYear() {
      return getCurrentTaxYear();
    },

    taxYearOptions() {
      // Build options: current year plus 3 previous years
      const current = this.currentTaxYear;
      const match = current.match(/^(\d{4})/);
      if (!match) return [current];
      const startYear = parseInt(match[1], 10);
      return Array.from({ length: 4 }, (_, i) => {
        const y = startYear - i;
        return `${y}/${String(y + 1).slice(-2)}`;
      });
    },

    currentAllowance() {
      // Check if MPAA triggered
      if (this.mpaaTriggered) {
        return MONEY_PURCHASE_ANNUAL_ALLOWANCE;
      }
      // Check if tapered
      if (this.isTapered) {
        return this.annualAllowance?.tapered_allowance || ANNUAL_ALLOWANCE;
      }
      // Standard allowance
      return ANNUAL_ALLOWANCE;
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
      if (this.progressPercent >= 100) return 'bg-raspberry-500';
      if (this.progressPercent >= 80) return 'bg-violet-500';
      if (this.progressPercent >= 60) return 'bg-violet-500';
      return 'bg-spring-500';
    },

    statusTextColour() {
      if (this.progressPercent >= 100) return 'text-raspberry-600';
      if (this.progressPercent >= 80) return 'text-violet-600';
      return 'text-spring-600';
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
      // Calculate carry forward from previous 3 years (dynamically)
      const years = [];

      if (!this.mpaaTriggered) {
        const currentYear = parseInt(getCurrentTaxYear().split('/')[0]);
        for (let i = 1; i <= 3; i++) {
          const y = currentYear - i;
          const taxYear = `${y}/${String(y + 1).slice(-2)}`;
          years.push({ taxYear, available: this.getHistoricalUnused(taxYear) });
        }
      }

      return years;
    },

    hasCarryForwardData() {
      return this.carryForwardYears.some(year => year.available !== null);
    },

    totalAvailableWithCarryForward() {
      if (!this.hasCarryForwardData) return this.remainingAllowance;
      const carryForwardTotal = this.carryForwardYears.reduce((sum, year) => sum + (year.available || 0), 0);
      return this.remainingAllowance + carryForwardTotal;
    },
  },

  methods: {
    getHistoricalContributions(taxYear) {
      // Historical contribution data is not yet available from the backend API.
      // Returns null to distinguish "no data" from "zero contributions".
      return null;
    },

    getHistoricalUnused(taxYear) {
      const used = this.getHistoricalContributions(taxYear);
      if (used === null) return null;
      const standardAllowance = ANNUAL_ALLOWANCE;
      return Math.max(0, standardAllowance - used);
    },

    getHistoricalPercent(taxYear) {
      const used = this.getHistoricalContributions(taxYear);
      if (used === null) return 0;
      return Math.min(100, Math.round((used / ANNUAL_ALLOWANCE) * 100));
    },
  },

  async mounted() {
    // Fetch annual allowance data
    try {
      await this.$store.dispatch('retirement/fetchAnnualAllowance', this.currentTaxYear);
    } catch (error) {
      logger.error('Failed to fetch annual allowance:', error);
    }
  },
};
</script>

<style scoped>
/* Progress bar animation */
.bg-spring-500,
.bg-violet-500,
.bg-violet-500,
.bg-raspberry-500 {
  transition: width 0.5s ease-out, background-colour 0.3s ease;
}
</style>
