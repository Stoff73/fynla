<template>
  <div
    class="card cursor-pointer hover:shadow-lg hover:-translate-y-0.5 hover:border-primary-500 transition-all duration-200"
    @click="navigateToRetirement"
  >
    <!-- Primary Value Section -->
    <div class="border-b border-gray-200 pb-4 mb-4">
      <span class="text-sm text-gray-500">Projected Annual Income</span>
      <div class="flex items-baseline gap-2 mt-1">
        <span class="text-3xl font-bold text-primary-600">
          {{ formatCurrency(projectedIncome) }}
        </span>
        <span class="text-sm text-gray-500">/year</span>
      </div>
    </div>

    <!-- Breakdown -->
    <div class="space-y-3">
      <div class="flex justify-between items-center">
        <span class="text-sm text-gray-600">Potential Income</span>
        <span class="text-sm font-semibold text-gray-900">{{ formatCurrency(potentialRetirementIncome) }}/yr</span>
      </div>
      <div v-if="dbPensionIncome > 0" class="flex justify-between items-center">
        <span class="text-sm text-gray-600">Guaranteed Income</span>
        <span class="text-sm font-semibold text-gray-900">{{ formatCurrency(dbPensionIncome) }}/yr</span>
      </div>
      <div class="flex justify-between items-center">
        <span class="text-sm text-gray-600">State Pension</span>
        <span class="text-sm font-semibold text-gray-900">{{ formatCurrency(statePensionIncome) }}/yr</span>
      </div>
    </div>

    <!-- Target Income -->
    <div class="mt-3 pt-3 border-t border-gray-200">
      <div class="flex justify-between items-center">
        <div class="flex items-center gap-1">
          <span class="text-sm text-gray-600">Target Income</span>
          <span class="text-xs text-gray-400">({{ isUserEnteredTarget ? 'user set' : '75% default' }})</span>
        </div>
        <span class="text-sm font-semibold text-gray-900">{{ formatCurrency(retirementTargetIncome) }}/yr</span>
      </div>
    </div>

    <!-- Income Gap Status -->
    <div
      v-if="hasIncomeGap"
      class="mt-4 p-3 bg-white border-2 border-amber-500 rounded-lg"
    >
      <div class="flex items-center gap-2">
        <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
        <span class="text-sm font-medium text-amber-700">
          {{ formatCurrency(incomeGap) }} income gap to target
        </span>
      </div>
    </div>

    <div
      v-else-if="projectedIncome > 0"
      class="mt-4 p-3 bg-white border-2 border-green-600 rounded-lg"
    >
      <div class="flex items-center gap-2">
        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span class="text-sm font-medium text-green-700">On track for retirement</span>
      </div>
    </div>
  </div>
</template>

<script>
import { mapState, mapGetters } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';

// UK full new State Pension 2025/26 (approximate)
const DEFAULT_STATE_PENSION = 11500;

export default {
  name: 'RetirementOverviewCard',
  mixins: [currencyMixin],

  computed: {
    ...mapState('retirement', ['dcPensions', 'dbPensions', 'statePension', 'profile', 'analysis']),
    ...mapGetters('retirement', ['totalPensionWealth']),
    ...mapGetters('userProfile', ['totalAnnualIncome']),

    retirementAge() {
      return this.profile?.target_retirement_age || 67;
    },

    dcPensionWealth() {
      return this.totalPensionWealth || 0;
    },

    // Convert DC pension wealth to estimated annual income using 4% safe withdrawal rate
    potentialRetirementIncome() {
      return this.dcPensionWealth * 0.04;
    },

    dbPensionIncome() {
      if (!this.dbPensions || this.dbPensions.length === 0) return 0;
      return this.dbPensions.reduce((sum, pension) => {
        return sum + parseFloat(pension.accrued_annual_pension || 0);
      }, 0);
    },

    // State pension - use configured amount or default to full UK state pension
    statePensionIncome() {
      const configured = parseFloat(this.statePension?.annual_amount || 0);
      return configured > 0 ? configured : DEFAULT_STATE_PENSION;
    },

    // Total projected annual income from all sources
    projectedIncome() {
      return this.potentialRetirementIncome + this.dbPensionIncome + this.statePensionIncome;
    },

    // Check if user has entered a target income
    isUserEnteredTarget() {
      const userTarget = this.profile?.target_retirement_income;
      return userTarget && userTarget > 0;
    },

    // Target income - user configured or default to 75% of current income
    retirementTargetIncome() {
      if (this.isUserEnteredTarget) {
        return this.profile.target_retirement_income;
      }
      // Default to 75% of current annual income
      const currentIncome = this.totalAnnualIncome || 0;
      return currentIncome * 0.75;
    },

    // Check if projected income meets target
    hasIncomeGap() {
      return this.projectedIncome < this.retirementTargetIncome;
    },

    incomeGap() {
      return Math.max(0, this.retirementTargetIncome - this.projectedIncome);
    },
  },

  methods: {
    navigateToRetirement() {
      this.$router.push('/net-worth/retirement');
    },
  },
};
</script>
