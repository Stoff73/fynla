<template>
  <div
    class="card cursor-pointer hover:shadow-lg hover:-translate-y-0.5 hover:border-primary-500 transition-all duration-200"
    @click="navigateToProfile"
  >
    <!-- Card Header -->
    <div class="flex items-start justify-between mb-4">
      <div>
        <h3 class="text-h3 text-gray-900">Affordability</h3>
        <p class="text-sm text-gray-500 mt-1">Income vs Expenditure</p>
      </div>
      <span class="text-gray-400">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" />
        </svg>
      </span>
    </div>

    <!-- Primary Value Section -->
    <div class="border-b border-gray-200 pb-4 mb-4">
      <span class="text-sm text-gray-500">Monthly Surplus/Deficit</span>
      <div class="flex items-baseline gap-2 mt-1">
        <span
          class="text-3xl font-bold"
          :class="monthlySurplus >= 0 ? 'text-green-600' : 'text-red-600'"
        >
          {{ formatCurrency(Math.abs(monthlySurplus)) }}
        </span>
        <span
          class="text-sm font-medium"
          :class="monthlySurplus >= 0 ? 'text-green-600' : 'text-red-600'"
        >
          {{ monthlySurplus >= 0 ? 'surplus' : 'deficit' }}
        </span>
      </div>
    </div>

    <!-- Breakdown -->
    <div class="space-y-3">
      <div class="flex justify-between items-center">
        <span class="text-sm text-gray-600">Monthly Income</span>
        <span class="text-sm font-semibold text-gray-900">{{ formatCurrency(monthlyIncome) }}</span>
      </div>
      <div class="flex justify-between items-center">
        <span class="text-sm text-gray-600">Monthly Expenditure</span>
        <span class="text-sm font-semibold text-gray-900">{{ formatCurrency(monthlyExpenditure) }}</span>
      </div>
    </div>

    <!-- Spouse Section (if linked) -->
    <div v-if="hasSpouse" class="mt-4 pt-4 border-t border-gray-200">
      <div class="flex justify-between items-center">
        <span class="text-sm text-gray-600">Combined Household</span>
        <span
          class="text-sm font-semibold"
          :class="combinedSurplus >= 0 ? 'text-green-600' : 'text-red-600'"
        >
          {{ formatCurrency(Math.abs(combinedSurplus)) }}/mo
          {{ combinedSurplus >= 0 ? 'surplus' : 'deficit' }}
        </span>
      </div>
    </div>

    <!-- Status Banner -->
    <div
      v-if="monthlySurplus < 0"
      class="mt-4 p-3 bg-white border-2 border-red-600 rounded-lg"
    >
      <div class="flex items-center gap-2">
        <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
        <span class="text-sm font-medium text-red-700">Spending exceeds income</span>
      </div>
    </div>
  </div>
</template>

<script>
import { mapState, mapGetters } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'AffordabilityOverviewCard',
  mixins: [currencyMixin],

  computed: {
    ...mapState('userProfile', ['incomeOccupation', 'spouseAccounts']),
    ...mapState('savings', ['expenditureProfile']),
    ...mapGetters('userProfile', ['spouse', 'totalAnnualIncome']),

    hasSpouse() {
      return !!this.spouse;
    },

    monthlyIncome() {
      const annual = this.totalAnnualIncome || 0;
      return annual / 12;
    },

    monthlyExpenditure() {
      // Get expenditure from savings module's expenditureProfile
      return this.expenditureProfile?.total_monthly_expenditure || 0;
    },

    monthlySurplus() {
      return this.monthlyIncome - this.monthlyExpenditure;
    },

    spouseMonthlyIncome() {
      // Get spouse income from their profile if available
      const spouse = this.spouse;
      if (!spouse) return 0;
      const annualSalary = parseFloat(spouse.annual_gross_salary || 0);
      const annualBonus = parseFloat(spouse.annual_bonus || 0);
      const annualDividends = parseFloat(spouse.annual_dividend_income || 0);
      const annualRental = parseFloat(spouse.annual_rental_income || 0);
      const annualOther = parseFloat(spouse.annual_other_income || 0);
      return (annualSalary + annualBonus + annualDividends + annualRental + annualOther) / 12;
    },

    spouseMonthlyExpenditure() {
      // Spouse expenditure would need separate tracking - for now assume shared household
      return 0;
    },

    combinedSurplus() {
      const userSurplus = this.monthlySurplus;
      const spouseSurplus = this.spouseMonthlyIncome - this.spouseMonthlyExpenditure;
      return userSurplus + spouseSurplus;
    },
  },

  methods: {
    navigateToProfile() {
      this.$router.push('/profile');
    },
  },
};
</script>
