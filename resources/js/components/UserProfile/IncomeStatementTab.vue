<template>
  <div>
    <div class="mb-6">
      <h2 class="text-h4 font-semibold text-gray-900">Income Statement</h2>
      <p class="mt-1 text-body-sm text-gray-600">
        Forecast Income Statement for the month of {{ currentMonthName }} {{ currentYear }}
      </p>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="flex justify-center items-center py-12">
      <div class="text-center">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600 mx-auto"></div>
        <p class="mt-4 text-body-base text-gray-600">Loading income statement...</p>
      </div>
    </div>

    <div v-else-if="hasData" class="space-y-6">
      <!-- Income Section -->
      <div class="card p-6 overflow-x-auto">
        <h3 class="text-h5 font-semibold text-success-700 mb-4">Income</h3>
        <table class="min-w-full divide-y divide-gray-200">
          <thead>
            <tr>
              <th class="px-3 py-2 text-left text-body-sm font-semibold text-gray-900">Line Item</th>
              <th class="px-3 py-2 text-right text-body-sm font-semibold text-gray-900">{{ currentMonthName }} {{ currentYear }}</th>
              <th class="px-3 py-2 text-right text-body-sm font-semibold text-gray-900">Forecast Annual</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr v-for="(item, index) in incomeItems" :key="'income-' + index">
              <td class="px-3 py-2 text-body-base text-gray-700">{{ item.line_item }}</td>
              <td class="px-3 py-2 text-right text-body-base font-medium text-gray-900">
                {{ formatCurrency(item.monthlyAmount) }}
              </td>
              <td class="px-3 py-2 text-right text-body-base font-medium text-gray-900">
                {{ formatCurrency(item.annualAmount) }}
              </td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="bg-success-50 border-t-2 border-success-300">
              <td class="px-3 py-3 text-body-base font-bold text-success-800">Total Income</td>
              <td class="px-3 py-3 text-right text-h5 font-bold text-success-700">
                {{ formatCurrency(totalIncome.monthly) }}
              </td>
              <td class="px-3 py-3 text-right text-h5 font-bold text-success-700">
                {{ formatCurrency(totalIncome.annual) }}
              </td>
            </tr>
          </tfoot>
        </table>
      </div>

      <!-- Expenses Section -->
      <div class="card p-6 overflow-x-auto">
        <h3 class="text-h5 font-semibold text-error-700 mb-4">Expenses</h3>
        <table class="min-w-full divide-y divide-gray-200">
          <thead>
            <tr>
              <th class="px-3 py-2 text-left text-body-sm font-semibold text-gray-900">Line Item</th>
              <th class="px-3 py-2 text-right text-body-sm font-semibold text-gray-900">{{ currentMonthName }} {{ currentYear }}</th>
              <th class="px-3 py-2 text-right text-body-sm font-semibold text-gray-900">Forecast Annual</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr v-for="(item, index) in expenseItems" :key="'expense-' + index">
              <td class="px-3 py-2 text-body-base text-gray-700">{{ item.line_item }}</td>
              <td class="px-3 py-2 text-right text-body-base font-medium text-gray-900">
                {{ formatCurrency(item.monthlyAmount) }}
              </td>
              <td class="px-3 py-2 text-right text-body-base font-medium text-gray-900">
                {{ formatCurrency(item.annualAmount) }}
              </td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="bg-error-50 border-t-2 border-error-300">
              <td class="px-3 py-3 text-body-base font-bold text-error-800">Total Expenses</td>
              <td class="px-3 py-3 text-right text-h5 font-bold text-error-700">
                {{ formatCurrency(totalExpenses.monthly) }}
              </td>
              <td class="px-3 py-3 text-right text-h5 font-bold text-error-700">
                {{ formatCurrency(totalExpenses.annual) }}
              </td>
            </tr>
          </tfoot>
        </table>
      </div>

      <!-- Net Income Section -->
      <div class="card p-6 bg-gradient-to-r from-primary-50 to-primary-100 overflow-x-auto">
        <table class="min-w-full">
          <thead>
            <tr>
              <th class="px-3 py-2 text-left text-body-sm font-semibold text-gray-900">Net Income</th>
              <th class="px-3 py-2 text-right text-body-sm font-semibold text-gray-900">{{ currentMonthName }} {{ currentYear }}</th>
              <th class="px-3 py-2 text-right text-body-sm font-semibold text-gray-900">Forecast Annual</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td class="px-3 py-3 text-h5 font-semibold text-gray-900">Net Income (Surplus/Deficit)</td>
              <td class="px-3 py-3 text-right">
                <p
                  class="text-h3 font-display font-bold"
                  :class="netIncome.monthly >= 0 ? 'text-success-700' : 'text-error-700'"
                >
                  {{ formatCurrency(netIncome.monthly) }}
                </p>
              </td>
              <td class="px-3 py-3 text-right">
                <p
                  class="text-h3 font-display font-bold"
                  :class="netIncome.annual >= 0 ? 'text-success-700' : 'text-error-700'"
                >
                  {{ formatCurrency(netIncome.annual) }}
                </p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="card p-8 text-center">
      <p class="text-body-base text-gray-500">
        No data available. Please add income and expense information to your profile.
      </p>
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import userProfileService from '@/services/userProfileService';

export default {
  name: 'IncomeStatementTab',

  setup() {
    const loading = ref(true);
    const profitAndLossData = ref(null);

    const now = new Date();
    const currentMonthName = computed(() => {
      return now.toLocaleDateString('en-GB', { month: 'long' });
    });
    const currentYear = computed(() => now.getFullYear());

    const hasData = computed(() => profitAndLossData.value !== null);

    const formatCurrency = (amount) => {
      if (amount === null || amount === undefined) return '£0';
      return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: 'GBP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
      }).format(amount);
    };

    const incomeItems = computed(() => {
      if (!profitAndLossData.value?.income) return [];
      return profitAndLossData.value.income
        .filter(item => item.amount > 0)
        .map(item => ({
          line_item: item.line_item,
          monthlyAmount: item.amount / 12,
          annualAmount: item.amount,
        }));
    });

    const expenseItems = computed(() => {
      if (!profitAndLossData.value?.expenses) return [];
      return profitAndLossData.value.expenses
        .filter(item => item.amount > 0)
        .map(item => ({
          line_item: item.line_item,
          monthlyAmount: item.amount / 12,
          annualAmount: item.amount,
        }));
    });

    const totalIncome = computed(() => {
      const annual = profitAndLossData.value?.total_income || 0;
      return {
        monthly: annual / 12,
        annual: annual,
      };
    });

    const totalExpenses = computed(() => {
      const annual = profitAndLossData.value?.total_expenses || 0;
      return {
        monthly: annual / 12,
        annual: annual,
      };
    });

    const netIncome = computed(() => ({
      monthly: totalIncome.value.monthly - totalExpenses.value.monthly,
      annual: totalIncome.value.annual - totalExpenses.value.annual,
    }));

    const loadData = async () => {
      loading.value = true;
      try {
        const taxYearStart = now.getMonth() >= 3 && now.getDate() >= 6
          ? `${now.getFullYear()}-04-06`
          : `${now.getFullYear() - 1}-04-06`;
        const taxYearEnd = now.getMonth() >= 3 && now.getDate() >= 6
          ? `${now.getFullYear() + 1}-04-05`
          : `${now.getFullYear()}-04-05`;

        const response = await userProfileService.calculatePersonalAccounts({
          start_date: taxYearStart,
          end_date: taxYearEnd,
          as_of_date: now.toISOString().split('T')[0],
        });

        if (response.success && response.data) {
          profitAndLossData.value = response.data.profit_and_loss;
        }
      } catch (error) {
        console.error('Failed to load income statement:', error);
      } finally {
        loading.value = false;
      }
    };

    onMounted(() => {
      loadData();
    });

    return {
      loading,
      hasData,
      currentMonthName,
      currentYear,
      formatCurrency,
      incomeItems,
      expenseItems,
      totalIncome,
      totalExpenses,
      netIncome,
    };
  },
};
</script>
