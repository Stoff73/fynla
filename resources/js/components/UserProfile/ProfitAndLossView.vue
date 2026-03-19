<template>
  <div class="relative">
    <!-- Coming Soon Watermark -->
    <div class="absolute inset-0 flex items-center justify-center z-10 pointer-events-none">
      <div class="bg-blue-100 border-2 border-blue-400 rounded-lg px-8 py-4 transform -rotate-12 shadow-lg">
        <p class="text-2xl font-bold text-blue-700">Coming Soon</p>
      </div>
    </div>

    <div v-if="data" class="space-y-6 opacity-50">
      <!-- Period Info -->
      <div class="card p-4 bg-eggshell-500">
        <p class="text-body-sm text-neutral-500">
          Period: {{ formatDate(data.period?.start_date) }} to {{ formatDate(data.period?.end_date) }}
        </p>
      </div>

      <!-- Income Section -->
      <div class="card p-6">
        <h3 class="text-h5 font-semibold text-success-700 mb-4">Income</h3>
        <div class="space-y-2">
          <div
            v-for="(item, index) in data.income"
            :key="index"
            class="flex justify-between items-center py-2 border-b border-light-gray last:border-0"
          >
            <span class="text-body-base text-neutral-500">{{ item.line_item }}</span>
            <span class="text-body-base font-medium text-horizon-500">
              {{ formatCurrency(item.amount) }}
            </span>
          </div>
        </div>
        <div class="mt-4 pt-4 border-t-2 border-horizon-300 flex justify-between items-center">
          <span class="text-body-base font-semibold text-horizon-500">Total Income</span>
          <span class="text-h5 font-bold text-success-700">
            {{ formatCurrency(data.total_income) }}
          </span>
        </div>
      </div>

      <!-- Expenses Section -->
      <div class="card p-6">
        <h3 class="text-h5 font-semibold text-raspberry-700 mb-4">Expenses</h3>
        <div class="space-y-2">
          <div
            v-for="(item, index) in data.expenses"
            :key="index"
            class="flex justify-between items-center py-2 border-b border-light-gray last:border-0"
          >
            <span class="text-body-base text-neutral-500">{{ item.line_item }}</span>
            <span class="text-body-base font-medium text-horizon-500">
              {{ formatCurrency(item.amount) }}
            </span>
          </div>
        </div>
        <div class="mt-4 pt-4 border-t-2 border-horizon-300 flex justify-between items-center">
          <span class="text-body-base font-semibold text-horizon-500">Total Expenses</span>
          <span class="text-h5 font-bold text-raspberry-700">
            {{ formatCurrency(data.total_expenses) }}
          </span>
        </div>
      </div>

      <!-- Net Profit/Loss -->
      <div class="card p-6 bg-gradient-to-r from-raspberry-50 to-raspberry-100">
        <div class="flex justify-between items-center">
          <div>
            <h3 class="text-h5 font-semibold text-horizon-500">Net Profit / Loss</h3>
            <p class="text-body-sm text-neutral-500 mt-1">Total Income minus Total Expenses</p>
          </div>
          <div>
            <p
              class="text-h2 font-display font-bold"
              :class="data.net_profit_loss >= 0 ? 'text-success-700' : 'text-raspberry-700'"
            >
              {{ formatCurrency(data.net_profit_loss) }}
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="card p-8 text-center opacity-50">
      <p class="text-body-base text-neutral-500">
        No data available. Click "Calculate" to generate your Profit & Loss statement.
      </p>
    </div>
  </div>
</template>

<script>
import { formatCurrency } from '@/utils/currency';

export default {
  name: 'ProfitAndLossView',

  props: {
    data: {
      type: Object,
      default: null,
    },
  },

  setup() {
    const formatDate = (dateString) => {
      if (!dateString) return 'N/A';
      const date = new Date(dateString);
      const day = String(date.getDate()).padStart(2, '0');
      const month = String(date.getMonth() + 1).padStart(2, '0');
      const year = date.getFullYear();
      return `${day}/${month}/${year}`;
    };

    return {
      formatCurrency,
      formatDate,
    };
  },
};
</script>
