<template>
  <div class="cash-flow-tab">
    <!-- Tax Year Selector -->
    <div class="mb-6">
      <label for="tax-year" class="block text-sm font-medium text-neutral-500 mb-2">
        Tax Year
      </label>
      <select
        id="tax-year"
        v-model="selectedTaxYear"
        @change="loadCashFlow"
        class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-horizon-300 focus:outline-none focus:ring-violet-500 focus:border-violet-500 sm:text-sm rounded-md"
      >
        <option v-for="year in taxYearOptions" :key="year" :value="year">{{ year }}</option>
      </select>
    </div>

    <!-- Cash Flow Summary -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
      <div class="bg-spring-50 rounded-lg p-6">
        <p class="text-sm text-spring-600 font-medium mb-2">Total Income</p>
        <p class="text-3xl font-bold text-horizon-500">{{ formattedIncome }}</p>
      </div>
      <div class="bg-raspberry-50 rounded-lg p-6">
        <p class="text-sm text-raspberry-600 font-medium mb-2">Total Expenses</p>
        <p class="text-3xl font-bold text-horizon-500">{{ formattedExpenses }}</p>
      </div>
      <div :class="[
        'rounded-lg p-6',
        netCashFlow >= 0 ? 'bg-violet-50' : 'bg-violet-50',
      ]">
        <p :class="[
          'text-sm font-medium mb-2',
          netCashFlow >= 0 ? 'text-violet-600' : 'text-violet-600',
        ]">
          Net Cash Flow
        </p>
        <p class="text-3xl font-bold text-horizon-500">{{ formattedNetCashFlow }}</p>
      </div>
    </div>

    <!-- Personal P&L Statement -->
    <div class="bg-white rounded-lg border border-light-gray mb-8">
      <div class="px-6 py-4 border-b border-light-gray">
        <h3 class="text-lg font-semibold text-horizon-500">Personal P&L Statement</h3>
      </div>
      <div class="px-6 py-6">
        <!-- Income Section -->
        <div class="mb-6">
          <h4 class="text-sm font-semibold text-horizon-500 mb-3 uppercase">Income</h4>
          <div class="space-y-2">
            <div class="flex justify-between items-center">
              <span class="text-sm text-neutral-500">Employment Income</span>
              <span class="text-sm font-medium text-horizon-500">{{ formatCurrency(cashFlowData.employment_income || 0) }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-sm text-neutral-500">Dividend Income</span>
              <span class="text-sm font-medium text-horizon-500">{{ formatCurrency(cashFlowData.dividend_income || 0) }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-sm text-neutral-500">Rental Income</span>
              <span class="text-sm font-medium text-horizon-500">{{ formatCurrency(cashFlowData.rental_income || 0) }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-sm text-neutral-500">Interest Income</span>
              <span class="text-sm font-medium text-horizon-500">{{ formatCurrency(cashFlowData.interest_income || 0) }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-sm text-neutral-500">Other Income</span>
              <span class="text-sm font-medium text-horizon-500">{{ formatCurrency(cashFlowData.other_income || 0) }}</span>
            </div>
            <div class="flex justify-between items-center pt-2 border-t border-light-gray">
              <span class="text-sm font-semibold text-horizon-500">Total Income</span>
              <span class="text-sm font-bold text-horizon-500">{{ formattedIncome }}</span>
            </div>
          </div>
        </div>

        <!-- Expenses Section -->
        <div class="mb-6">
          <h4 class="text-sm font-semibold text-horizon-500 mb-3 uppercase">Expenses</h4>
          <div class="space-y-2">
            <div class="flex justify-between items-center">
              <span class="text-sm text-neutral-500">Essential Expenses</span>
              <span class="text-sm font-medium text-horizon-500">{{ formatCurrency(cashFlowData.essential_expenses || 0) }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-sm text-neutral-500">Lifestyle Expenses</span>
              <span class="text-sm font-medium text-horizon-500">{{ formatCurrency(cashFlowData.lifestyle_expenses || 0) }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-sm text-neutral-500">Debt Servicing</span>
              <span class="text-sm font-medium text-horizon-500">{{ formatCurrency(cashFlowData.debt_servicing || 0) }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-sm text-neutral-500">Taxes</span>
              <span class="text-sm font-medium text-horizon-500">{{ formatCurrency(cashFlowData.taxes || 0) }}</span>
            </div>
            <div class="flex justify-between items-center pt-2 border-t border-light-gray">
              <span class="text-sm font-semibold text-horizon-500">Total Expenses</span>
              <span class="text-sm font-bold text-horizon-500">{{ formattedExpenses }}</span>
            </div>
          </div>
        </div>

        <!-- Net Cash Flow -->
        <div :class="[
          'p-4 rounded-lg',
          netCashFlow >= 0 ? 'bg-spring-50' : 'bg-raspberry-50',
        ]">
          <div class="flex justify-between items-center">
            <span :class="[
              'text-base font-bold',
              netCashFlow >= 0 ? 'text-spring-800' : 'text-raspberry-800',
            ]">
              Net Cash Flow
            </span>
            <span :class="[
              'text-base font-bold',
              netCashFlow >= 0 ? 'text-spring-800' : 'text-raspberry-800',
            ]">
              {{ formattedNetCashFlow }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Recommendations -->
    <div v-if="netCashFlow < 0" class="bg-raspberry-50 border border-raspberry-200 p-4">
      <div class="flex">
        <div class="flex-shrink-0">
          <svg
            class="h-5 w-5 text-raspberry-400"
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 20 20"
            fill="currentColor"
          >
            <path
              fill-rule="evenodd"
              d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
              clip-rule="evenodd"
            />
          </svg>
        </div>
        <div class="ml-3">
          <h3 class="text-sm font-medium text-raspberry-800">Cash Flow Deficit</h3>
          <div class="mt-2 text-sm text-raspberry-700">
            <p>You are spending more than you earn. Consider reviewing your expenses or increasing income.</p>
          </div>
        </div>
      </div>
    </div>
    <div v-else class="bg-spring-50 border border-spring-200 p-4">
      <div class="flex">
        <div class="flex-shrink-0">
          <svg
            class="h-5 w-5 text-spring-400"
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 20 20"
            fill="currentColor"
          >
            <path
              fill-rule="evenodd"
              d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
              clip-rule="evenodd"
            />
          </svg>
        </div>
        <div class="ml-3">
          <h3 class="text-sm font-medium text-spring-800">Positive Cash Flow</h3>
          <div class="mt-2 text-sm text-spring-700">
            <p>Great! You have a surplus. Consider investing or building your emergency fund.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { mapState, mapActions } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';
import { getCurrentTaxYear } from '@/utils/dateFormatter';

import logger from '@/utils/logger';
export default {
  name: 'CashFlow',
  mixins: [currencyMixin],

  data() {
    return {
      selectedTaxYear: getCurrentTaxYear(),
      isLoading: false,
      hasLoaded: false,
    };
  },

  computed: {
    ...mapState('estate', ['cashFlow']),

    taxYearOptions() {
      const current = getCurrentTaxYear();
      const startYear = parseInt(current.split('/')[0]);
      return [
        `${startYear}/${String(startYear + 1).slice(-2)}`,
        `${startYear - 1}/${String(startYear).slice(-2)}`,
        `${startYear - 2}/${String(startYear - 1).slice(-2)}`,
      ];
    },

    cashFlowData() {
      return this.cashFlow || {};
    },

    totalIncome() {
      return (
        (this.cashFlowData.employment_income || 0) +
        (this.cashFlowData.dividend_income || 0) +
        (this.cashFlowData.rental_income || 0) +
        (this.cashFlowData.interest_income || 0) +
        (this.cashFlowData.other_income || 0)
      );
    },

    totalExpenses() {
      return (
        (this.cashFlowData.essential_expenses || 0) +
        (this.cashFlowData.lifestyle_expenses || 0) +
        (this.cashFlowData.debt_servicing || 0) +
        (this.cashFlowData.taxes || 0)
      );
    },

    netCashFlow() {
      return this.totalIncome - this.totalExpenses;
    },

    formattedIncome() {
      return this.formatCurrency(this.totalIncome);
    },

    formattedExpenses() {
      return this.formatCurrency(this.totalExpenses);
    },

    formattedNetCashFlow() {
      return this.formatCurrency(this.netCashFlow);
    },
  },

  mounted() {
    // Only load once when component first mounts
    if (!this.hasLoaded && !this.isLoading) {
      this.loadCashFlow();
    }
  },

  methods: {
    ...mapActions('estate', ['fetchCashFlow']),

    async loadCashFlow() {
      // Prevent multiple simultaneous loads
      if (this.isLoading) {
        return;
      }

      this.isLoading = true;

      try {
        await this.fetchCashFlow(this.selectedTaxYear);
        this.hasLoaded = true;
      } catch (error) {
        logger.error('Failed to load cash flow:', error);
      } finally {
        this.isLoading = false;
      }
    },
  },
};
</script>
