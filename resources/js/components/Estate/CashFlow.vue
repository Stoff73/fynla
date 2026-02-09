<template>
  <div class="cash-flow-tab">
    <!-- Tax Year Selector -->
    <div class="mb-6">
      <label for="tax-year" class="block text-sm font-medium text-gray-700 mb-2">
        Tax Year
      </label>
      <select
        id="tax-year"
        v-model="selectedTaxYear"
        @change="loadCashFlow"
        class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary-500 focus:border-blue-500 sm:text-sm rounded-md"
      >
        <option value="2025/26">2025/26</option>
        <option value="2024/25">2024/25</option>
        <option value="2023/24">2023/24</option>
      </select>
    </div>

    <!-- Cash Flow Summary -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
      <div class="bg-green-50 rounded-lg p-6">
        <p class="text-sm text-green-600 font-medium mb-2">Total Income</p>
        <p class="text-3xl font-bold text-gray-900">{{ formattedIncome }}</p>
      </div>
      <div class="bg-red-50 rounded-lg p-6">
        <p class="text-sm text-red-600 font-medium mb-2">Total Expenses</p>
        <p class="text-3xl font-bold text-gray-900">{{ formattedExpenses }}</p>
      </div>
      <div :class="[
        'rounded-lg p-6',
        netCashFlow >= 0 ? 'bg-blue-50' : 'bg-blue-50',
      ]">
        <p :class="[
          'text-sm font-medium mb-2',
          netCashFlow >= 0 ? 'text-blue-600' : 'text-blue-600',
        ]">
          Net Cash Flow
        </p>
        <p class="text-3xl font-bold text-gray-900">{{ formattedNetCashFlow }}</p>
      </div>
    </div>

    <!-- Personal P&L Statement -->
    <div class="bg-white rounded-lg border border-gray-200 mb-8">
      <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Personal P&L Statement</h3>
      </div>
      <div class="px-6 py-6">
        <!-- Income Section -->
        <div class="mb-6">
          <h4 class="text-sm font-semibold text-gray-900 mb-3 uppercase">Income</h4>
          <div class="space-y-2">
            <div class="flex justify-between items-center">
              <span class="text-sm text-gray-600">Employment Income</span>
              <span class="text-sm font-medium text-gray-900">{{ formatCurrency(cashFlowData.employment_income || 0) }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-sm text-gray-600">Dividend Income</span>
              <span class="text-sm font-medium text-gray-900">{{ formatCurrency(cashFlowData.dividend_income || 0) }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-sm text-gray-600">Rental Income</span>
              <span class="text-sm font-medium text-gray-900">{{ formatCurrency(cashFlowData.rental_income || 0) }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-sm text-gray-600">Interest Income</span>
              <span class="text-sm font-medium text-gray-900">{{ formatCurrency(cashFlowData.interest_income || 0) }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-sm text-gray-600">Other Income</span>
              <span class="text-sm font-medium text-gray-900">{{ formatCurrency(cashFlowData.other_income || 0) }}</span>
            </div>
            <div class="flex justify-between items-center pt-2 border-t border-gray-200">
              <span class="text-sm font-semibold text-gray-900">Total Income</span>
              <span class="text-sm font-bold text-gray-900">{{ formattedIncome }}</span>
            </div>
          </div>
        </div>

        <!-- Expenses Section -->
        <div class="mb-6">
          <h4 class="text-sm font-semibold text-gray-900 mb-3 uppercase">Expenses</h4>
          <div class="space-y-2">
            <div class="flex justify-between items-center">
              <span class="text-sm text-gray-600">Essential Expenses</span>
              <span class="text-sm font-medium text-gray-900">{{ formatCurrency(cashFlowData.essential_expenses || 0) }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-sm text-gray-600">Lifestyle Expenses</span>
              <span class="text-sm font-medium text-gray-900">{{ formatCurrency(cashFlowData.lifestyle_expenses || 0) }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-sm text-gray-600">Debt Servicing</span>
              <span class="text-sm font-medium text-gray-900">{{ formatCurrency(cashFlowData.debt_servicing || 0) }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-sm text-gray-600">Taxes</span>
              <span class="text-sm font-medium text-gray-900">{{ formatCurrency(cashFlowData.taxes || 0) }}</span>
            </div>
            <div class="flex justify-between items-center pt-2 border-t border-gray-200">
              <span class="text-sm font-semibold text-gray-900">Total Expenses</span>
              <span class="text-sm font-bold text-gray-900">{{ formattedExpenses }}</span>
            </div>
          </div>
        </div>

        <!-- Net Cash Flow -->
        <div :class="[
          'p-4 rounded-lg',
          netCashFlow >= 0 ? 'bg-green-50' : 'bg-red-50',
        ]">
          <div class="flex justify-between items-center">
            <span :class="[
              'text-base font-bold',
              netCashFlow >= 0 ? 'text-green-800' : 'text-red-800',
            ]">
              Net Cash Flow
            </span>
            <span :class="[
              'text-base font-bold',
              netCashFlow >= 0 ? 'text-green-800' : 'text-red-800',
            ]">
              {{ formattedNetCashFlow }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Recommendations -->
    <div v-if="netCashFlow < 0" class="bg-red-50 border-l-4 border-red-500 p-4">
      <div class="flex">
        <div class="flex-shrink-0">
          <svg
            class="h-5 w-5 text-red-400"
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
          <h3 class="text-sm font-medium text-red-800">Cash Flow Deficit</h3>
          <div class="mt-2 text-sm text-red-700">
            <p>You are spending more than you earn. Consider reviewing your expenses or increasing income.</p>
          </div>
        </div>
      </div>
    </div>
    <div v-else class="bg-green-50 border-l-4 border-green-500 p-4">
      <div class="flex">
        <div class="flex-shrink-0">
          <svg
            class="h-5 w-5 text-green-400"
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
          <h3 class="text-sm font-medium text-green-800">Positive Cash Flow</h3>
          <div class="mt-2 text-sm text-green-700">
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

export default {
  name: 'CashFlow',
  mixins: [currencyMixin],

  data() {
    return {
      selectedTaxYear: '2025/26',
      isLoading: false,
      hasLoaded: false,
    };
  },

  computed: {
    ...mapState('estate', ['cashFlow']),

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
        console.error('Failed to load cash flow:', error);
      } finally {
        this.isLoading = false;
      }
    },
  },
};
</script>
