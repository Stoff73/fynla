<template>
  <div
    class="card cursor-pointer hover:shadow-lg hover:-translate-y-0.5 hover:bg-light-gray transition-all duration-200"
    @click="navigateToCash"
  >
    <!-- Primary Value Section -->
    <div class="border-b border-light-gray pb-4 mb-4">
      <span class="text-sm text-neutral-500">{{ currentMonth }} Cash Flow</span>
      <div class="flex items-baseline gap-2 mt-1">
        <span
          class="text-3xl font-bold"
          :class="monthlySurplus >= 0 ? 'text-spring-600' : 'text-raspberry-600'"
        >
          {{ formatCurrency(Math.abs(monthlySurplus)) }}
        </span>
        <span
          class="text-sm font-medium"
          :class="monthlySurplus >= 0 ? 'text-spring-600' : 'text-raspberry-600'"
        >
          {{ monthlySurplus >= 0 ? 'surplus' : 'deficit' }}
        </span>
      </div>
    </div>

    <!-- Breakdown -->
    <div class="space-y-3">
      <div class="flex justify-between items-center">
        <span class="text-sm text-neutral-500">Money In</span>
        <span class="text-sm font-semibold text-spring-600">{{ formatCurrency(monthlyIncome) }}</span>
      </div>
      <div class="flex justify-between items-center">
        <span class="text-sm text-neutral-500">Money Out</span>
        <span class="text-sm font-semibold text-raspberry-600">{{ formatCurrency(monthlyExpenditure) }}</span>
      </div>
    </div>

    <!-- Spouse Section (if linked) -->
    <div v-if="hasSpouse" class="mt-4 pt-4 border-t border-light-gray">
      <div class="flex justify-between items-center">
        <span class="text-sm text-neutral-500">Combined Household</span>
        <span
          class="text-sm font-semibold"
          :class="combinedSurplus >= 0 ? 'text-spring-600' : 'text-raspberry-600'"
        >
          {{ formatCurrency(Math.abs(combinedSurplus)) }}/mo
          {{ combinedSurplus >= 0 ? 'surplus' : 'deficit' }}
        </span>
      </div>
    </div>

    <!-- Status Banner -->
    <div
      v-if="monthlySurplus < 0"
      class="mt-4 p-3 bg-white border-2 border-raspberry-600 rounded-lg"
    >
      <div class="flex items-center gap-2">
        <svg class="w-5 h-5 text-raspberry-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
        <span class="text-sm font-medium text-raspberry-700">Spending exceeds income</span>
      </div>
    </div>
  </div>
</template>

<script>
import { mapState, mapGetters, mapActions } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';
import userProfileService from '@/services/userProfileService';

import logger from '@/utils/logger';
export default {
  name: 'AffordabilityOverviewCard',
  mixins: [currencyMixin],

  data() {
    return {
      financialCommitmentsData: null,
    };
  },

  computed: {
    ...mapState('userProfile', ['incomeOccupation', 'spouseAccounts']),
    ...mapState('savings', ['expenditureProfile']),
    ...mapGetters('userProfile', ['spouse', 'totalAnnualIncome']),

    hasSpouse() {
      return !!this.spouse;
    },

    currentMonth() {
      return new Date().toLocaleString('en-GB', { month: 'long' });
    },

    monthlyIncome() {
      const annual = this.totalAnnualIncome || 0;
      return annual / 12;
    },

    // Total financial commitments from user profile API
    financialCommitmentsTotal() {
      return this.financialCommitmentsData?.totals?.total || 0;
    },

    monthlyExpenditure() {
      // Get discretionary expenditure from savings module's expenditureProfile
      const discretionary = this.expenditureProfile?.total_monthly_expenditure || 0;
      // Add financial commitments
      return discretionary + this.financialCommitmentsTotal;
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

  async mounted() {
    // Load all data in parallel
    await Promise.all([
      this.loadProfileData(),
      this.loadFinancialCommitments(),
    ]);
  },

  methods: {
    ...mapActions('userProfile', ['fetchProfile']),

    async loadProfileData() {
      if (!this.incomeOccupation) {
        await this.fetchProfile();
      }
    },

    async loadFinancialCommitments() {
      try {
        const response = await userProfileService.getFinancialCommitments();
        if (response.success) {
          this.financialCommitmentsData = response.data;
        }
      } catch (error) {
        logger.error('Failed to load financial commitments:', error);
        this.financialCommitmentsData = null;
      }
    },

    navigateToCash() {
      this.$router.push('/net-worth/cash');
    },
  },
};
</script>
