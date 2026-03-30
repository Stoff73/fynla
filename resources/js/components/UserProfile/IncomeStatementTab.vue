<template>
  <div>
    <div class="mb-6">
      <h2 class="text-h4 font-semibold text-horizon-500">Income and Cash Flow Summary</h2>
      <p class="mt-1 text-body-sm text-neutral-500">
        Cash based Income Statement using all cash movements including capital, mortgage and loan repayments
      </p>
    </div>

    <!-- View Toggle -->
    <div class="flex gap-2 mb-6">
      <button
        @click="activeView = 'current'"
        :class="[
          'px-4 py-2 text-sm font-medium rounded-lg transition-colors',
          activeView === 'current'
            ? 'bg-raspberry-500 text-white'
            : 'bg-savannah-100 text-neutral-500 hover:bg-horizon-200'
        ]"
      >
        Current
      </button>
      <button
        @click="switchToForecast"
        :class="[
          'px-4 py-2 text-sm font-medium rounded-lg transition-colors',
          activeView === 'forecast'
            ? 'bg-raspberry-500 text-white'
            : 'bg-savannah-100 text-neutral-500 hover:bg-horizon-200'
        ]"
      >
        Forecast
      </button>
    </div>

    <!-- ==================== CURRENT VIEW ==================== -->
    <template v-if="activeView === 'current'">
      <!-- Loading State -->
      <div v-if="loading" class="flex justify-center items-center py-12">
        <div class="text-center">
          <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-raspberry-500 mx-auto"></div>
          <p class="mt-4 text-body-base text-neutral-500">Loading income statement...</p>
        </div>
      </div>

      <div v-else-if="hasData" class="space-y-6">
        <!-- Income Section -->
        <div class="card p-6 overflow-x-auto">
          <h3 class="text-h5 font-semibold text-success-700 mb-4">Income</h3>
          <table class="min-w-full divide-y divide-light-gray">
            <thead>
              <tr>
                <th class="px-3 py-2 text-left text-body-sm font-semibold text-horizon-500 w-1/2"></th>
                <th class="px-3 py-2 text-right text-body-sm font-semibold text-horizon-500 w-1/4">{{ currentMonthName }} {{ currentYear }}</th>
                <th class="px-3 py-2 text-right text-body-sm font-semibold text-horizon-500 w-1/4">Forecast Annual</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-light-gray">
              <tr v-for="(item, index) in incomeItems" :key="'income-' + index">
                <td class="px-3 py-2 text-body-base text-neutral-500 w-1/2">{{ item.line_item }}</td>
                <td class="px-3 py-2 text-right text-body-base font-medium text-horizon-500">
                  {{ formatCurrency(item.monthlyAmount) }}
                </td>
                <td class="px-3 py-2 text-right text-body-base font-medium text-horizon-500">
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
          <h3 class="text-h5 font-semibold text-raspberry-700 mb-4">Outflows</h3>
          <table class="min-w-full divide-y divide-light-gray">
            <thead>
              <tr>
                <th class="px-3 py-2 text-left text-body-sm font-semibold text-horizon-500 w-1/2"></th>
                <th class="px-3 py-2 text-right text-body-sm font-semibold text-horizon-500 w-1/4">{{ currentMonthName }} {{ currentYear }}</th>
                <th class="px-3 py-2 text-right text-body-sm font-semibold text-horizon-500 w-1/4">Forecast Annual</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-light-gray">
              <tr v-for="(item, index) in outflowItems" :key="'outflow-' + index">
                <td class="px-3 py-2 text-body-base text-neutral-500 w-1/2">{{ item.line_item }}</td>
                <td class="px-3 py-2 text-right text-body-base font-medium text-horizon-500">
                  {{ formatCurrency(item.monthlyAmount) }}
                </td>
                <td class="px-3 py-2 text-right text-body-base font-medium text-horizon-500">
                  {{ formatCurrency(item.annualAmount) }}
                </td>
              </tr>
            </tbody>
            <tfoot>
              <tr class="bg-raspberry-50 border-t-2 border-raspberry-300">
                <td class="px-3 py-3 text-body-base font-bold text-raspberry-800 w-1/2">Total Outflows</td>
                <td class="px-3 py-3 text-right text-h5 font-bold text-raspberry-700">
                  {{ formatCurrency(totalOutflows.monthly) }}
                </td>
                <td class="px-3 py-3 text-right text-h5 font-bold text-raspberry-700">
                  {{ formatCurrency(totalOutflows.annual) }}
                </td>
              </tr>
            </tfoot>
          </table>
        </div>

        <!-- Cash Flow Section -->
        <div class="card p-6 bg-gradient-to-r from-raspberry-50 to-raspberry-100 overflow-x-auto">
          <table class="min-w-full">
            <thead>
              <tr>
                <th class="px-3 py-2 text-left text-body-sm font-semibold text-horizon-500 w-1/2"></th>
                <th class="px-3 py-2 text-right text-body-sm font-semibold text-horizon-500 w-1/4">{{ currentMonthName }} {{ currentYear }}</th>
                <th class="px-3 py-2 text-right text-body-sm font-semibold text-horizon-500 w-1/4">Forecast Annual</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-light-gray">
              <!-- Cash Flow before Tax -->
              <tr>
                <td class="px-3 py-3 text-body-base font-semibold text-horizon-500 w-1/2">Cash Flow before Tax for the period</td>
                <td class="px-3 py-3 text-right">
                  <p
                    class="text-h5 font-bold"
                    :class="cashFlowBeforeTax.monthly >= 0 ? 'text-success-700' : 'text-raspberry-700'"
                  >
                    {{ formatCurrency(cashFlowBeforeTax.monthly) }}
                  </p>
                </td>
                <td class="px-3 py-3 text-right">
                  <p
                    class="text-h5 font-bold"
                    :class="cashFlowBeforeTax.annual >= 0 ? 'text-success-700' : 'text-raspberry-700'"
                  >
                    {{ formatCurrency(cashFlowBeforeTax.annual) }}
                  </p>
                </td>
              </tr>
              <!-- Estimated Income Tax -->
              <tr>
                <td class="px-3 py-2 text-body-base text-neutral-500 pl-4 w-1/2">Estimated Income Tax</td>
                <td class="px-3 py-2 text-right text-body-base font-medium text-horizon-400">-</td>
                <td class="px-3 py-2 text-right text-body-base font-medium text-raspberry-700">
                  {{ formatCurrencyNegative(estimatedIncomeTax) }}
                </td>
              </tr>
              <!-- Estimated Capital Gains Tax -->
              <tr>
                <td class="px-3 py-2 text-body-base text-neutral-500 pl-4 w-1/2">Estimated Capital Gains Tax</td>
                <td class="px-3 py-2 text-right text-body-base font-medium text-horizon-400">-</td>
                <td class="px-3 py-2 text-right text-body-base font-medium text-raspberry-700">
                  {{ formatCurrencyNegative(estimatedCapitalGainsTax) }}
                </td>
              </tr>
              <!-- Cash Flow after Tax -->
              <tr class="border-t-2 border-raspberry-300">
                <td class="px-3 py-3 text-body-base font-bold text-horizon-500 w-1/2">Cash Flow after Tax for the period</td>
                <td class="px-3 py-3 text-right">
                  <p
                    class="text-h5 font-bold"
                    :class="cashFlowAfterTax.monthly >= 0 ? 'text-success-700' : 'text-raspberry-700'"
                  >
                    {{ formatCurrency(cashFlowAfterTax.monthly) }}
                  </p>
                </td>
                <td class="px-3 py-3 text-right">
                  <p
                    class="text-h5 font-bold"
                    :class="cashFlowAfterTax.annual >= 0 ? 'text-success-700' : 'text-raspberry-700'"
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
        <p class="text-body-base text-neutral-500">
          No data available. Please add income and expense information to your profile.
        </p>
      </div>
    </template>

    <!-- ==================== FORECAST VIEW ==================== -->
    <template v-if="activeView === 'forecast'">
      <!-- Granularity Toggle -->
      <div class="flex items-center justify-between mb-4">
        <div class="flex gap-2">
          <button
            @click="forecastGranularity = 'monthly'"
            :class="[
              'px-3 py-1.5 text-xs font-medium rounded transition-colors',
              forecastGranularity === 'monthly'
                ? 'bg-raspberry-100 text-raspberry-700'
                : 'bg-eggshell-500 text-neutral-500 hover:bg-savannah-100'
            ]"
          >
            Monthly (12 months)
          </button>
          <button
            @click="forecastGranularity = 'annual'"
            :class="[
              'px-3 py-1.5 text-xs font-medium rounded transition-colors',
              forecastGranularity === 'annual'
                ? 'bg-raspberry-100 text-raspberry-700'
                : 'bg-eggshell-500 text-neutral-500 hover:bg-savannah-100'
            ]"
          >
            Annual (5 years)
          </button>
        </div>
        <p v-if="forecastSummary" class="text-xs text-neutral-500">
          <span v-if="forecastSummary.deficit_months > 0 || forecastSummary.deficit_years > 0" class="text-raspberry-600 font-medium">
            {{ forecastSummary.deficit_months || forecastSummary.deficit_years }} deficit {{ forecastGranularity === 'monthly' ? 'months' : 'years' }}
          </span>
          <span v-else class="text-success-600 font-medium">No deficit periods</span>
        </p>
      </div>

      <!-- Forecast Loading -->
      <div v-if="forecastLoading" class="flex justify-center items-center py-12">
        <div class="text-center">
          <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-raspberry-500 mx-auto"></div>
          <p class="mt-4 text-body-base text-neutral-500">Loading forecast...</p>
        </div>
      </div>

      <!-- Forecast Data -->
      <div v-else-if="forecastPeriods.length > 0" class="space-y-6">
        <!-- Summary Card -->
        <div v-if="forecastSummary" class="card p-6">
          <h3 class="text-h5 font-semibold text-horizon-500 mb-4">Forecast Summary</h3>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div>
              <p class="text-xs text-neutral-500 mb-1">Regular Income</p>
              <p class="text-sm font-semibold text-success-700">
                {{ formatCurrency(forecastSummary.monthly_regular_income || forecastSummary.annual_regular_income) }}
                <span class="text-xs text-neutral-500 font-normal">/ {{ forecastGranularity === 'monthly' ? 'month' : 'year' }}</span>
              </p>
            </div>
            <div>
              <p class="text-xs text-neutral-500 mb-1">Regular Expenditure</p>
              <p class="text-sm font-semibold text-raspberry-700">
                {{ formatCurrency(forecastSummary.monthly_regular_expenditure || forecastSummary.annual_regular_expenditure) }}
                <span class="text-xs text-neutral-500 font-normal">/ {{ forecastGranularity === 'monthly' ? 'month' : 'year' }}</span>
              </p>
            </div>
            <div>
              <p class="text-xs text-neutral-500 mb-1">Life Event Income</p>
              <p class="text-sm font-semibold text-success-700">
                {{ formatCurrency(forecastSummary.total_event_income || 0) }}
              </p>
            </div>
            <div>
              <p class="text-xs text-neutral-500 mb-1">Life Event Expenses</p>
              <p class="text-sm font-semibold text-raspberry-700">
                {{ formatCurrency(forecastSummary.total_event_expense || 0) }}
              </p>
            </div>
          </div>
        </div>

        <!-- Forecast Table -->
        <div class="card p-6 overflow-x-auto">
          <h3 class="text-h5 font-semibold text-horizon-500 mb-4">
            {{ forecastGranularity === 'monthly' ? 'Monthly' : 'Annual' }} Cash Flow Forecast
          </h3>
          <table class="min-w-full divide-y divide-light-gray">
            <thead>
              <tr>
                <th class="px-3 py-2 text-left text-body-sm font-semibold text-horizon-500">Period</th>
                <th class="px-3 py-2 text-right text-body-sm font-semibold text-horizon-500">Income</th>
                <th class="px-3 py-2 text-right text-body-sm font-semibold text-horizon-500">Expenditure</th>
                <th class="px-3 py-2 text-right text-body-sm font-semibold text-horizon-500">Net Cash Flow</th>
                <th class="px-3 py-2 text-right text-body-sm font-semibold text-horizon-500">Cumulative</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-light-gray">
              <template v-for="(period, index) in forecastPeriods" :key="'period-' + index">
                <!-- Period row -->
                <tr :class="period.is_deficit ? 'bg-raspberry-50' : ''">
                  <td class="px-3 py-2 text-body-base text-horizon-500 font-medium">
                    <div class="flex items-center gap-1.5">
                      {{ period.month_label || period.year }}
                      <span v-if="period.has_events" class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-raspberry-100">
                        <svg class="w-3 h-3 text-raspberry-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                          <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" />
                        </svg>
                      </span>
                    </div>
                  </td>
                  <td class="px-3 py-2 text-right text-body-base font-medium text-success-700">
                    {{ formatCurrency(period.total_income) }}
                  </td>
                  <td class="px-3 py-2 text-right text-body-base font-medium text-raspberry-700">
                    {{ formatCurrency(period.total_expenditure) }}
                  </td>
                  <td class="px-3 py-2 text-right text-body-base font-semibold" :class="period.net_cash_flow >= 0 ? 'text-success-700' : 'text-raspberry-700'">
                    {{ formatCurrency(period.net_cash_flow) }}
                  </td>
                  <td class="px-3 py-2 text-right text-body-base font-medium" :class="period.cumulative_surplus >= 0 ? 'text-horizon-500' : 'text-raspberry-700'">
                    {{ formatCurrency(period.cumulative_surplus) }}
                  </td>
                </tr>

                <!-- Event detail rows (if period has events) -->
                <tr v-for="(event, eIdx) in period.events" :key="'event-' + index + '-' + eIdx" class="bg-raspberry-50">
                  <td class="px-3 py-1.5 pl-8 text-xs text-raspberry-700" colspan="2">
                    <div class="flex items-center gap-1.5">
                      <svg class="w-3 h-3 text-raspberry-500 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" />
                      </svg>
                      <span class="font-medium">{{ event.event_name }}</span>
                      <span class="text-neutral-500">({{ event.certainty }})</span>
                    </div>
                  </td>
                  <td class="px-3 py-1.5 text-right text-xs" :class="event.impact_type === 'income' ? 'text-success-600' : 'text-raspberry-600'">
                    {{ event.impact_type === 'income' ? '+' : '-' }}{{ formatCurrency(event.amount) }}
                  </td>
                  <td class="px-3 py-1.5 text-right text-xs text-neutral-500" colspan="2">
                    <span v-if="event.weighted_amount !== event.amount">
                      Weighted: {{ formatCurrency(event.weighted_amount) }}
                    </span>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Forecast Empty State -->
      <div v-else class="card p-8 text-center">
        <p class="text-body-base text-neutral-500">
          No forecast data available. Please add income and expense information to your profile.
        </p>
      </div>
    </template>
  </div>
</template>

<script>
import { ref, computed, onMounted, watch } from 'vue';
import { useStore } from 'vuex';
import userProfileService from '@/services/userProfileService';
import goalsService from '@/services/goalsService';
import { formatCurrency } from '@/utils/currency';

import logger from '@/utils/logger';
export default {
  name: 'IncomeStatementTab',

  setup() {
    const store = useStore();
    const loading = ref(true);
    const profitAndLossData = ref(null);
    const cashflowData = ref(null);
    const balanceSheetData = ref(null);

    // View state
    const activeView = ref('current');
    const forecastGranularity = ref('monthly');
    const forecastLoading = ref(false);
    const forecastData = ref(null);

    const now = new Date();
    const currentMonthName = computed(() => {
      return now.toLocaleDateString('en-GB', { month: 'long' });
    });
    const currentYear = computed(() => now.getFullYear());

    const hasData = computed(() => profitAndLossData.value !== null || cashflowData.value !== null);

    const formatCurrencyNegative = (amount) => {
      if (amount === null || amount === undefined || amount === 0) return '£0';
      return '-' + formatCurrency(amount);
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

    // Tax data from backend (uses TaxConfigService rates)
    const taxData = computed(() => profitAndLossData.value?.tax || null);

    const totalIncome = computed(() => {
      const annual = Number(profitAndLossData.value?.total_income) || 0;
      return {
        monthly: annual / 12,
        annual: annual,
      };
    });

    const totalOutflows = computed(() => {
      const expensesAnnual = outflowItems.value.reduce((sum, item) => sum + (item.annualAmount || 0), 0);
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

    // Income tax from backend TaxConfigService calculation
    const estimatedIncomeTax = computed(() => taxData.value?.income_tax || 0);

    // Estimated Capital Gains Tax (placeholder - would need actual gains data)
    const estimatedCapitalGainsTax = computed(() => 0);

    // Cash Flow after Tax = Cash Flow before Tax - Taxes
    const cashFlowAfterTax = computed(() => ({
      monthly: cashFlowBeforeTax.value.monthly, // No monthly tax estimate
      annual: cashFlowBeforeTax.value.annual - estimatedIncomeTax.value - estimatedCapitalGainsTax.value,
    }));

    // Forecast computed properties
    const forecastPeriods = computed(() => {
      if (!forecastData.value) return [];
      return forecastData.value.months || forecastData.value.years || [];
    });

    const forecastSummary = computed(() => {
      if (!forecastData.value) return null;
      return forecastData.value.summary || null;
    });

    // Data loading
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
        logger.error('Failed to load income statement:', error);
      } finally {
        loading.value = false;
      }
    };

    const loadForecast = async () => {
      forecastLoading.value = true;
      try {
        const options = { view: forecastGranularity.value };
        if (forecastGranularity.value === 'monthly') {
          options.months = 12;
        } else {
          options.years = 5;
        }

        const response = await goalsService.getFinancialForecast(options);
        if (response.success) {
          forecastData.value = response.data;
        }
      } catch (error) {
        logger.error('Failed to load forecast:', error);
      } finally {
        forecastLoading.value = false;
      }
    };

    const switchToForecast = () => {
      activeView.value = 'forecast';
      if (!forecastData.value) {
        loadForecast();
      }
    };

    // Watch granularity changes to reload forecast
    watch(forecastGranularity, () => {
      if (activeView.value === 'forecast') {
        loadForecast();
      }
    });

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
      activeView,
      forecastGranularity,
      forecastLoading,
      forecastPeriods,
      forecastSummary,
      switchToForecast,
    };
  },
};
</script>
