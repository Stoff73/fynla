<template>
  <div>
    <div class="mb-6">
      <h2 class="text-h4 font-semibold text-gray-900">Income and Cash Flow Summary</h2>
      <p class="mt-1 text-body-sm text-gray-600">
        Cash based Income Statement using all cash movements including capital, mortgage and loan repayments
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
              <th class="px-3 py-2 text-left text-body-sm font-semibold text-gray-900 w-1/2"></th>
              <th class="px-3 py-2 text-right text-body-sm font-semibold text-gray-900 w-1/4">{{ currentMonthName }} {{ currentYear }}</th>
              <th class="px-3 py-2 text-right text-body-sm font-semibold text-gray-900 w-1/4">Forecast Annual</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr v-for="(item, index) in incomeItems" :key="'income-' + index">
              <td class="px-3 py-2 text-body-base text-gray-700 w-1/2">{{ item.line_item }}</td>
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
              <td class="px-3 py-3 text-body-base font-bold text-success-800 w-1/2">Total Income</td>
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

      <!-- Outflows Section -->
      <div class="card p-6 overflow-x-auto">
        <h3 class="text-h5 font-semibold text-error-700 mb-4">Outflows</h3>
        <table class="min-w-full divide-y divide-gray-200">
          <thead>
            <tr>
              <th class="px-3 py-2 text-left text-body-sm font-semibold text-gray-900 w-1/2"></th>
              <th class="px-3 py-2 text-right text-body-sm font-semibold text-gray-900 w-1/4">{{ currentMonthName }} {{ currentYear }}</th>
              <th class="px-3 py-2 text-right text-body-sm font-semibold text-gray-900 w-1/4">Forecast Annual</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr v-for="(item, index) in outflowItems" :key="'outflow-' + index">
              <td class="px-3 py-2 text-body-base text-gray-700 w-1/2">{{ item.line_item }}</td>
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
              <td class="px-3 py-3 text-body-base font-bold text-error-800 w-1/2">Total Outflows</td>
              <td class="px-3 py-3 text-right text-h5 font-bold text-error-700">
                {{ formatCurrency(totalOutflows.monthly) }}
              </td>
              <td class="px-3 py-3 text-right text-h5 font-bold text-error-700">
                {{ formatCurrency(totalOutflows.annual) }}
              </td>
            </tr>
          </tfoot>
        </table>
      </div>

      <!-- Cash Flow Section -->
      <div class="card p-6 bg-gradient-to-r from-primary-50 to-primary-100 overflow-x-auto">
        <table class="min-w-full">
          <thead>
            <tr>
              <th class="px-3 py-2 text-left text-body-sm font-semibold text-gray-900 w-1/2"></th>
              <th class="px-3 py-2 text-right text-body-sm font-semibold text-gray-900 w-1/4">{{ currentMonthName }} {{ currentYear }}</th>
              <th class="px-3 py-2 text-right text-body-sm font-semibold text-gray-900 w-1/4">Forecast Annual</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <!-- Cash Flow before Tax -->
            <tr>
              <td class="px-3 py-3 text-body-base font-semibold text-gray-900 w-1/2">Cash Flow before Tax for the period</td>
              <td class="px-3 py-3 text-right">
                <p
                  class="text-h5 font-bold"
                  :class="cashFlowBeforeTax.monthly >= 0 ? 'text-success-700' : 'text-error-700'"
                >
                  {{ formatCurrency(cashFlowBeforeTax.monthly) }}
                </p>
              </td>
              <td class="px-3 py-3 text-right">
                <p
                  class="text-h5 font-bold"
                  :class="cashFlowBeforeTax.annual >= 0 ? 'text-success-700' : 'text-error-700'"
                >
                  {{ formatCurrency(cashFlowBeforeTax.annual) }}
                </p>
              </td>
            </tr>
            <!-- Estimated Income Tax -->
            <tr>
              <td class="px-3 py-2 text-body-base text-gray-700 pl-4 w-1/2">Estimated Income Tax</td>
              <td class="px-3 py-2 text-right text-body-base font-medium text-gray-400">-</td>
              <td class="px-3 py-2 text-right text-body-base font-medium text-error-700">
                {{ formatCurrencyNegative(estimatedIncomeTax) }}
              </td>
            </tr>
            <!-- Estimated Capital Gains Tax -->
            <tr>
              <td class="px-3 py-2 text-body-base text-gray-700 pl-4 w-1/2">Estimated Capital Gains Tax</td>
              <td class="px-3 py-2 text-right text-body-base font-medium text-gray-400">-</td>
              <td class="px-3 py-2 text-right text-body-base font-medium text-error-700">
                {{ formatCurrencyNegative(estimatedCapitalGainsTax) }}
              </td>
            </tr>
            <!-- Cash Flow after Tax -->
            <tr class="border-t-2 border-primary-300">
              <td class="px-3 py-3 text-body-base font-bold text-gray-900 w-1/2">Cash Flow after Tax for the period</td>
              <td class="px-3 py-3 text-right">
                <p
                  class="text-h5 font-bold"
                  :class="cashFlowAfterTax.monthly >= 0 ? 'text-success-700' : 'text-error-700'"
                >
                  {{ formatCurrency(cashFlowAfterTax.monthly) }}
                </p>
              </td>
              <td class="px-3 py-3 text-right">
                <p
                  class="text-h5 font-bold"
                  :class="cashFlowAfterTax.annual >= 0 ? 'text-success-700' : 'text-error-700'"
                >
                  {{ formatCurrency(cashFlowAfterTax.annual) }}
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
import { useStore } from 'vuex';
import userProfileService from '@/services/userProfileService';

export default {
  name: 'IncomeStatementTab',

  setup() {
    const store = useStore();
    const loading = ref(true);
    const profitAndLossData = ref(null);
    const cashflowData = ref(null);
    const balanceSheetData = ref(null);

    const now = new Date();
    const currentMonthName = computed(() => {
      return now.toLocaleDateString('en-GB', { month: 'long' });
    });
    const currentYear = computed(() => now.getFullYear());

    const hasData = computed(() => profitAndLossData.value !== null || cashflowData.value !== null);

    const user = computed(() => store.getters['userProfile/user']);

    const formatCurrency = (amount) => {
      if (amount === null || amount === undefined) return '£0';
      return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: 'GBP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
      }).format(amount);
    };

    const formatCurrencyNegative = (amount) => {
      if (amount === null || amount === undefined || amount === 0) return '£0';
      return '-' + new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: 'GBP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
      }).format(amount);
    };

    const incomeItems = computed(() => {
      if (!profitAndLossData.value?.income) return [];
      return profitAndLossData.value.income
        .filter(item => Number(item.amount) > 0)
        .map(item => ({
          line_item: item.line_item,
          monthlyAmount: Number(item.amount) / 12,
          annualAmount: Number(item.amount),
        }));
    });

    // Combine expenses from P&L with cashflow outflows (liability repayments, pension contributions)
    const outflowItems = computed(() => {
      const items = [];
      const seenItems = new Set();

      // Add expenses from P&L
      if (profitAndLossData.value?.expenses) {
        profitAndLossData.value.expenses
          .filter(item => Number(item.amount) > 0)
          .forEach(item => {
            items.push({
              line_item: item.line_item,
              monthlyAmount: Number(item.amount) / 12,
              annualAmount: Number(item.amount),
            });
            seenItems.add(item.line_item);
          });
      }

      // Add additional outflows from cashflow data (pension contributions, etc.)
      if (cashflowData.value?.outflows) {
        cashflowData.value.outflows
          .filter(item => Number(item.amount) > 0 && !seenItems.has(item.line_item))
          .forEach(item => {
            items.push({
              line_item: item.line_item,
              monthlyAmount: Number(item.amount) / 12,
              annualAmount: Number(item.amount),
            });
            seenItems.add(item.line_item);
          });
      }

      // Sort items: Living Expenses first, then Mortgage Payments, then others
      const order = ['Living Expenses', 'Mortgage Payments'];
      return items.sort((a, b) => {
        const aIndex = order.indexOf(a.line_item);
        const bIndex = order.indexOf(b.line_item);
        if (aIndex !== -1 && bIndex !== -1) return aIndex - bIndex;
        if (aIndex !== -1) return -1;
        if (bIndex !== -1) return 1;
        return 0;
      });
    });

    // Estimated taxes (annual only, shown in annual column)
    const estimatedTaxes = computed(() => {
      const taxes = [];
      const totalAnnualIncome = profitAndLossData.value?.total_income || 0;

      // Simple income tax estimate based on UK tax bands 2025/26
      if (totalAnnualIncome > 0) {
        let incomeTax = 0;
        const personalAllowance = 12570;
        const basicRateLimit = 50270;
        const higherRateLimit = 125140;

        const taxableIncome = Math.max(0, totalAnnualIncome - personalAllowance);
        if (taxableIncome > 0) {
          // Basic rate (20%)
          const basicRateTaxable = Math.min(taxableIncome, basicRateLimit - personalAllowance);
          incomeTax += basicRateTaxable * 0.20;

          // Higher rate (40%)
          if (taxableIncome > basicRateLimit - personalAllowance) {
            const higherRateTaxable = Math.min(taxableIncome - (basicRateLimit - personalAllowance), higherRateLimit - basicRateLimit);
            incomeTax += higherRateTaxable * 0.40;
          }

          // Additional rate (45%)
          if (taxableIncome > higherRateLimit - personalAllowance) {
            const additionalRateTaxable = taxableIncome - (higherRateLimit - personalAllowance);
            incomeTax += additionalRateTaxable * 0.45;
          }
        }

        if (incomeTax > 0) {
          taxes.push({
            line_item: 'Estimated Income Tax',
            monthlyAmount: null,
            annualAmount: incomeTax,
            annualOnly: true,
          });
        }
      }

      // Capital gains tax estimate (placeholder - would need actual gains data)
      // For now, we'll leave this out unless we have CGT data

      return taxes;
    });

    const totalIncome = computed(() => {
      const annual = Number(profitAndLossData.value?.total_income) || 0;
      return {
        monthly: annual / 12,
        annual: annual,
      };
    });

    const totalOutflows = computed(() => {
      const expensesAnnual = outflowItems.value.reduce((sum, item) => sum + (item.annualAmount || 0), 0);
      // Taxes are now shown separately in Cash Flow section, not included in Total Outflows
      return {
        monthly: expensesAnnual / 12,
        annual: expensesAnnual,
      };
    });

    // Cash Flow before Tax = Total Income - Total Outflows (excluding taxes)
    const cashFlowBeforeTax = computed(() => ({
      monthly: totalIncome.value.monthly - totalOutflows.value.monthly,
      annual: totalIncome.value.annual - totalOutflows.value.annual,
    }));

    // Estimated Income Tax (annual only - using the calculation from estimatedTaxes)
    const estimatedIncomeTax = computed(() => {
      const taxItem = estimatedTaxes.value.find(t => t.line_item === 'Estimated Income Tax');
      return taxItem?.annualAmount || 0;
    });

    // Estimated Capital Gains Tax (placeholder - would need actual gains data)
    const estimatedCapitalGainsTax = computed(() => 0);

    // Cash Flow after Tax = Cash Flow before Tax - Taxes
    const cashFlowAfterTax = computed(() => ({
      monthly: cashFlowBeforeTax.value.monthly, // No monthly tax estimate
      annual: cashFlowBeforeTax.value.annual - estimatedIncomeTax.value - estimatedCapitalGainsTax.value,
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
          cashflowData.value = response.data.cashflow;
          balanceSheetData.value = response.data.balance_sheet;
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
      formatCurrencyNegative,
      incomeItems,
      outflowItems,
      totalIncome,
      totalOutflows,
      cashFlowBeforeTax,
      estimatedIncomeTax,
      estimatedCapitalGainsTax,
      cashFlowAfterTax,
    };
  },
};
</script>
