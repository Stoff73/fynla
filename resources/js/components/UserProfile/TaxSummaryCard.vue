<template>
  <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg border border-blue-200 p-4">
    <!-- Header -->
    <div class="flex justify-between items-center mb-4">
      <h3 class="text-lg font-semibold text-horizon-500">UK Tax & NI Summary</h3>
      <span class="px-3 py-1 text-sm font-medium rounded-full bg-blue-100 text-blue-800">
        {{ summary.tax_year }}
      </span>
    </div>

    <!-- Main Summary Grid -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
      <!-- Gross Income -->
      <div class="text-center">
        <p class="text-xs text-neutral-500 uppercase tracking-wide mb-1">Gross Income</p>
        <p class="text-xl font-bold text-horizon-500">{{ formatCurrency(summary.total_gross_income) }}</p>
      </div>

      <!-- Income Tax -->
      <div class="text-center">
        <p class="text-xs text-neutral-500 uppercase tracking-wide mb-1">Income Tax</p>
        <p class="text-xl font-bold text-red-600">-{{ formatCurrency(summary.total_income_tax) }}</p>
        <p v-if="section24?.applied_credit > 0" class="text-xs text-green-600 mt-0.5">
          Inc. S24 credit {{ formatCurrency(section24.applied_credit) }}
        </p>
      </div>

      <!-- National Insurance -->
      <div class="text-center">
        <p class="text-xs text-neutral-500 uppercase tracking-wide mb-1">National Insurance</p>
        <p class="text-xl font-bold" :class="summary.total_national_insurance > 0 ? 'text-red-600' : 'text-horizon-400'">
          {{ summary.total_national_insurance > 0 ? '-' : '' }}{{ formatCurrency(summary.total_national_insurance) }}
        </p>
      </div>

      <!-- Net Income -->
      <div class="text-center">
        <p class="text-xs text-neutral-500 uppercase tracking-wide mb-1">Net Income</p>
        <p class="text-xl font-bold text-green-700">{{ formatCurrency(summary.net_income) }}</p>
      </div>
    </div>

    <!-- Bottom Row -->
    <div class="flex justify-between items-center pt-3 border-t border-blue-200">
      <div class="flex items-center gap-4">
        <div>
          <span class="text-xs text-neutral-500">Monthly Net</span>
          <p class="text-sm font-semibold text-horizon-500">{{ formatCurrency(summary.monthly_net_income) }}</p>
        </div>
        <div>
          <span class="text-xs text-neutral-500">Total Deductions</span>
          <p class="text-sm font-semibold text-red-600">{{ formatCurrency(summary.total_deductions) }}</p>
        </div>
      </div>
      <div class="text-right">
        <span class="text-xs text-neutral-500">Effective Tax Rate</span>
        <p class="text-lg font-bold text-horizon-500">{{ summary.effective_tax_rate }}%</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { formatCurrency } from '@/utils/currency';

const props = defineProps({
  summary: {
    type: Object,
    required: true,
  },
  section24: {
    type: Object,
    default: null,
  },
});


</script>
