<template>
  <div class="bg-white rounded-lg border border-gray-200 p-4">
    <!-- Income Type Header -->
    <div class="flex justify-between items-start mb-3">
      <div>
        <h4 class="text-sm font-semibold text-gray-900">{{ breakdown.income_type_label }}</h4>
        <p class="text-lg font-bold text-gray-900">{{ formatCurrency(breakdown.gross_amount) }}</p>
      </div>
      <span
        v-if="breakdown.ni_breakdown"
        class="px-2 py-1 text-xs font-medium rounded bg-blue-100 text-blue-800"
      >
        {{ breakdown.ni_breakdown.class }}
      </span>
      <span
        v-else
        class="px-2 py-1 text-xs font-medium rounded bg-gray-100 text-gray-600"
      >
        No NI
      </span>
    </div>

    <!-- Trust Income Tax Breakdown (special handling) -->
    <div v-if="isTrustIncome" class="space-y-2 mb-3">
      <!-- Trust Type Badge -->
      <div class="flex items-center gap-2 mb-2">
        <span class="px-2 py-1 text-xs font-medium rounded bg-purple-100 text-purple-800">
          {{ breakdown.tax_breakdown.trust_type_label || 'Trust' }}
        </span>
      </div>

      <!-- Tax Paid by Trust -->
      <div
        v-if="breakdown.tax_breakdown.tax_paid_by_trust > 0"
        class="flex justify-between items-center text-sm"
      >
        <span class="text-gray-600">
          {{ breakdown.tax_breakdown.tax_description || 'Tax paid by trust' }}
        </span>
        <span class="text-red-600 font-medium">-{{ formatCurrency(breakdown.tax_breakdown.tax_paid_by_trust) }}</span>
      </div>

      <!-- Net to Beneficiary -->
      <div
        v-if="breakdown.tax_breakdown.net_to_beneficiary"
        class="flex justify-between items-center text-sm"
      >
        <span class="text-gray-600">Net received from trust</span>
        <span class="text-green-600 font-medium">{{ formatCurrency(breakdown.tax_breakdown.net_to_beneficiary) }}</span>
      </div>

      <!-- Personalized Tax Reclaim Info -->
      <div
        v-if="breakdown.tax_breakdown.reclaim_info"
        class="mt-3 p-3 rounded-lg"
        :class="{
          'bg-green-50 border border-green-200': breakdown.tax_breakdown.reclaim_info.type === 'reclaim',
          'bg-amber-50 border border-amber-200': breakdown.tax_breakdown.reclaim_info.type === 'owe',
          'bg-gray-50 border border-gray-200': breakdown.tax_breakdown.reclaim_info.type === 'none'
        }"
      >
        <p
          class="text-sm font-medium"
          :class="{
            'text-green-800': breakdown.tax_breakdown.reclaim_info.type === 'reclaim',
            'text-amber-800': breakdown.tax_breakdown.reclaim_info.type === 'owe',
            'text-gray-700': breakdown.tax_breakdown.reclaim_info.type === 'none'
          }"
        >
          {{ breakdown.tax_breakdown.reclaim_info.message }}
        </p>
        <p
          v-if="breakdown.tax_breakdown.reclaim_info.type === 'reclaim'"
          class="text-xs text-green-700 mt-1"
        >
          Claim via your Self Assessment tax return (form R40 if you don't file one).
        </p>
      </div>
    </div>

    <!-- Standard Tax Band Breakdown (non-trust income) -->
    <div v-else class="space-y-2 mb-3">
      <!-- Personal Allowance -->
      <div
        v-if="breakdown.tax_breakdown.personal_allowance_used > 0"
        class="flex justify-between items-center text-sm"
      >
        <span class="text-gray-600">Personal Allowance: {{ formatCurrency(breakdown.tax_breakdown.personal_allowance_used) }} @ 0%</span>
        <span class="text-gray-400 font-medium">£0</span>
      </div>

      <!-- Basic Rate -->
      <div
        v-if="breakdown.tax_breakdown.basic_rate?.taxable > 0"
        class="flex justify-between items-center text-sm"
      >
        <span class="text-gray-600">
          Basic: {{ formatCurrency(breakdown.tax_breakdown.basic_rate.taxable) }} @ {{ formatPercent(breakdown.tax_breakdown.basic_rate.rate) }}
        </span>
        <span class="text-red-600 font-medium">-{{ formatCurrency(breakdown.tax_breakdown.basic_rate.tax) }}</span>
      </div>

      <!-- Higher Rate -->
      <div
        v-if="breakdown.tax_breakdown.higher_rate?.taxable > 0"
        class="flex justify-between items-center text-sm"
      >
        <span class="text-gray-600">
          Higher: {{ formatCurrency(breakdown.tax_breakdown.higher_rate.taxable) }} @ {{ formatPercent(breakdown.tax_breakdown.higher_rate.rate) }}
        </span>
        <span class="text-red-600 font-medium">-{{ formatCurrency(breakdown.tax_breakdown.higher_rate.tax) }}</span>
      </div>

      <!-- Additional Rate -->
      <div
        v-if="breakdown.tax_breakdown.additional_rate?.taxable > 0"
        class="flex justify-between items-center text-sm"
      >
        <span class="text-gray-600">
          Additional: {{ formatCurrency(breakdown.tax_breakdown.additional_rate.taxable) }} @ {{ formatPercent(breakdown.tax_breakdown.additional_rate.rate) }}
        </span>
        <span class="text-red-600 font-medium">-{{ formatCurrency(breakdown.tax_breakdown.additional_rate.tax) }}</span>
      </div>

      <!-- Personal Savings Allowance (for interest income) -->
      <div
        v-if="breakdown.tax_breakdown.personal_savings_allowance > 0"
        class="flex justify-between items-center text-sm"
      >
        <span class="text-gray-600">Personal Savings Allowance</span>
        <span class="text-green-600 font-medium">{{ formatCurrency(breakdown.tax_breakdown.personal_savings_allowance) }}</span>
      </div>

      <!-- Dividend Allowance (for dividend income) -->
      <div
        v-if="breakdown.tax_breakdown.dividend_allowance > 0"
        class="flex justify-between items-center text-sm"
      >
        <span class="text-gray-600">Dividend Allowance</span>
        <span class="text-green-600 font-medium">{{ formatCurrency(breakdown.tax_breakdown.dividend_allowance) }}</span>
      </div>
    </div>

    <!-- NI Breakdown (if applicable) -->
    <div v-if="breakdown.ni_breakdown" class="space-y-2 mb-3 pt-2 border-t border-gray-100">
      <div class="text-xs font-medium text-gray-500 uppercase">National Insurance</div>

      <!-- Main Rate NI -->
      <div
        v-if="breakdown.ni_breakdown.main_rate?.contribution > 0"
        class="flex justify-between items-center text-sm"
      >
        <span class="text-gray-600">
          {{ formatPercent(breakdown.ni_breakdown.main_rate.rate) }} on {{ formatCurrency(breakdown.ni_breakdown.main_rate.earnings) }}
        </span>
        <span class="text-red-600">-{{ formatCurrency(breakdown.ni_breakdown.main_rate.contribution) }}</span>
      </div>

      <!-- Additional Rate NI -->
      <div
        v-if="breakdown.ni_breakdown.additional_rate?.contribution > 0"
        class="flex justify-between items-center text-sm"
      >
        <span class="text-gray-600">
          {{ formatPercent(breakdown.ni_breakdown.additional_rate.rate) }} on {{ formatCurrency(breakdown.ni_breakdown.additional_rate.earnings) }}
        </span>
        <span class="text-red-600">-{{ formatCurrency(breakdown.ni_breakdown.additional_rate.contribution) }}</span>
      </div>
    </div>

    <!-- Net Income -->
    <div class="pt-2 border-t border-gray-200">
      <div class="flex justify-between items-center">
        <span class="text-sm font-medium text-gray-700">Net Income</span>
        <span class="text-lg font-bold text-green-700">{{ formatCurrency(breakdown.net_income) }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  breakdown: {
    type: Object,
    required: true,
  },
});

// Check if this is trust income (has special tax treatment)
const isTrustIncome = computed(() => {
  return props.breakdown.income_type === 'trust';
});

const formatCurrency = (value) => {
  return new Intl.NumberFormat('en-GB', {
    style: 'currency',
    currency: 'GBP',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(value ?? 0);
};

const formatPercent = (value) => {
  return `${Math.round((value ?? 0) * 100)}%`;
};
</script>
