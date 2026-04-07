<template>
  <div class="bg-white rounded-lg border border-light-gray p-4">
    <!-- Income Type Header -->
    <div class="flex justify-between items-start mb-3">
      <div>
        <h4 class="text-sm font-semibold text-horizon-500">{{ breakdown.income_type_label }}</h4>
        <p class="text-lg font-bold text-horizon-500">{{ formatCurrency(breakdown.gross_amount) }}</p>
      </div>
      <div class="flex flex-col items-end gap-1">
        <span
          v-if="hasNI"
          class="px-2 py-1 text-xs font-medium rounded bg-light-blue-100 text-horizon-600"
        >
          NI Applies
        </span>
        <span
          v-else
          class="px-2 py-1 text-xs font-medium rounded bg-savannah-100 text-neutral-500"
        >
          No NI
        </span>
      </div>
    </div>

    <!-- Earned Income: Show income components breakdown -->
    <div v-if="isEarnedIncome && breakdown.income_components?.length > 0" class="space-y-2 mb-3">
      <!-- Income Components (employment, rental, pension, etc.) -->
      <template v-for="(component, index) in breakdown.income_components" :key="index">
        <div class="flex justify-between items-center text-sm">
          <span class="text-neutral-500">{{ component.label }}</span>
          <span
            :class="component.is_deduction ? 'text-spring-600 font-medium' : 'text-horizon-500'"
          >
            {{ formatCurrency(component.amount) }}
          </span>
        </div>
        <!-- Per-property rental breakdown -->
        <template v-if="component.label === 'Rental Income' && rentalBreakdown?.properties?.length > 0">
          <div
            v-for="(property, pIndex) in rentalBreakdown.properties"
            :key="'prop-' + pIndex"
            class="flex justify-between items-center text-xs pl-4"
          >
            <span class="text-horizon-400">{{ property.name }}</span>
            <span class="text-neutral-500">{{ formatCurrency(property.annual_taxable) }}</span>
          </div>
        </template>
      </template>

      <!-- Taxable Income Total (after deductions) -->
      <div class="flex justify-between items-center text-sm border-t border-light-gray pt-2 mt-2">
        <span class="text-neutral-500 font-medium">Taxable Income</span>
        <span class="text-horizon-500 font-medium">{{ formatCurrency(breakdown.taxable_income) }}</span>
      </div>
    </div>

    <!-- Trust Income Tax Breakdown (special handling) -->
    <div v-else-if="isTrustIncome" class="space-y-2 mb-3">
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
        <span class="text-neutral-500">
          {{ breakdown.tax_breakdown.tax_description || 'Tax paid by trust' }}
        </span>
        <span class="text-raspberry-600 font-medium">-{{ formatCurrency(breakdown.tax_breakdown.tax_paid_by_trust) }}</span>
      </div>

      <!-- Net to Beneficiary -->
      <div
        v-if="breakdown.tax_breakdown.net_to_beneficiary"
        class="flex justify-between items-center text-sm"
      >
        <span class="text-neutral-500">Net received from trust</span>
        <span class="text-spring-600 font-medium">{{ formatCurrency(breakdown.tax_breakdown.net_to_beneficiary) }}</span>
      </div>

      <!-- Personalized Tax Reclaim Info -->
      <div
        v-if="breakdown.tax_breakdown.reclaim_info"
        class="mt-3 p-3 rounded-lg"
        :class="{
          'bg-spring-50 border border-spring-200': breakdown.tax_breakdown.reclaim_info.type === 'reclaim',
          'bg-light-blue-100 border border-horizon-200': breakdown.tax_breakdown.reclaim_info.type === 'owe',
          'bg-eggshell-500 border border-light-gray': breakdown.tax_breakdown.reclaim_info.type === 'none'
        }"
      >
        <p
          class="text-sm font-medium"
          :class="{
            'text-spring-800': breakdown.tax_breakdown.reclaim_info.type === 'reclaim',
            'text-horizon-600': breakdown.tax_breakdown.reclaim_info.type === 'owe',
            'text-neutral-500': breakdown.tax_breakdown.reclaim_info.type === 'none'
          }"
        >
          {{ breakdown.tax_breakdown.reclaim_info.message }}
        </p>
        <p
          v-if="breakdown.tax_breakdown.reclaim_info.type === 'reclaim'"
          class="text-xs text-spring-700 mt-1"
        >
          Claim via your Self Assessment tax return (form R40 if you don't file one).
        </p>
      </div>
    </div>

    <!-- Standard Tax Band Breakdown (for earned, interest, dividend income) -->
    <div v-else class="space-y-2 mb-3">
      <!-- Personal Savings Allowance (for interest income) -->
      <div
        v-if="breakdown.tax_breakdown.personal_savings_allowance > 0"
        class="flex justify-between items-center text-sm"
      >
        <span class="text-neutral-500">Personal Savings Allowance</span>
        <span class="text-spring-600 font-medium">{{ formatCurrency(breakdown.tax_breakdown.personal_savings_allowance) }}</span>
      </div>

      <!-- Dividend Allowance (for dividend income) -->
      <div
        v-if="breakdown.tax_breakdown.dividend_allowance > 0"
        class="flex justify-between items-center text-sm"
      >
        <span class="text-neutral-500">Dividend Allowance</span>
        <span class="text-spring-600 font-medium">{{ formatCurrency(breakdown.tax_breakdown.dividend_allowance) }}</span>
      </div>
    </div>

    <!-- Tax Bands Section -->
    <div class="space-y-2 mb-3 pt-2 border-t border-savannah-100">
      <div class="text-xs font-medium text-neutral-500 uppercase">Income Tax</div>

      <!-- Personal Allowance -->
      <div
        v-if="breakdown.tax_breakdown.personal_allowance_used > 0"
        class="flex justify-between items-center text-sm"
      >
        <span class="text-neutral-500">Personal Allowance: {{ formatCurrency(breakdown.tax_breakdown.personal_allowance_used) }} @ 0%</span>
        <span class="text-horizon-400 font-medium">£0</span>
      </div>

      <!-- Basic Rate -->
      <div
        v-if="breakdown.tax_breakdown.basic_rate?.taxable > 0"
        class="flex justify-between items-center text-sm"
      >
        <span class="text-neutral-500">
          Basic: {{ formatCurrency(breakdown.tax_breakdown.basic_rate.taxable) }} @ {{ formatPercent(breakdown.tax_breakdown.basic_rate.rate) }}
        </span>
        <span class="text-raspberry-600 font-medium">-{{ formatCurrency(breakdown.tax_breakdown.basic_rate.tax) }}</span>
      </div>

      <!-- Higher Rate -->
      <div
        v-if="breakdown.tax_breakdown.higher_rate?.taxable > 0"
        class="flex justify-between items-center text-sm"
      >
        <span class="text-neutral-500">
          Higher: {{ formatCurrency(breakdown.tax_breakdown.higher_rate.taxable) }} @ {{ formatPercent(breakdown.tax_breakdown.higher_rate.rate) }}
        </span>
        <span class="text-raspberry-600 font-medium">-{{ formatCurrency(breakdown.tax_breakdown.higher_rate.tax) }}</span>
      </div>

      <!-- Additional Rate -->
      <div
        v-if="breakdown.tax_breakdown.additional_rate?.taxable > 0"
        class="flex justify-between items-center text-sm"
      >
        <span class="text-neutral-500">
          Additional: {{ formatCurrency(breakdown.tax_breakdown.additional_rate.taxable) }} @ {{ formatPercent(breakdown.tax_breakdown.additional_rate.rate) }}
        </span>
        <span class="text-raspberry-600 font-medium">-{{ formatCurrency(breakdown.tax_breakdown.additional_rate.tax) }}</span>
      </div>

      <!-- Tax Payable Subtotal -->
      <div v-if="section24?.applied_credit > 0" class="flex justify-between items-center text-sm border-t border-light-gray pt-2 mt-2">
        <span class="text-neutral-500 font-medium">Tax Payable</span>
        <span class="text-raspberry-600 font-medium">-{{ formatCurrency(totalIncomeTax) }}</span>
      </div>

      <!-- Section 24 Tax Credit -->
      <div v-if="section24?.applied_credit > 0" class="flex justify-between items-center text-sm">
        <span class="text-neutral-500">Section 24 Tax Credit</span>
        <span class="text-spring-600 font-medium">+{{ formatCurrency(section24.applied_credit) }}</span>
      </div>

      <!-- Tax Payable After Credit -->
      <div v-if="section24?.applied_credit > 0" class="flex justify-between items-center text-sm border-t border-light-gray pt-2 mt-2">
        <span class="text-neutral-500 font-medium">Tax Payable After Credit</span>
        <span class="text-raspberry-600 font-bold">-{{ formatCurrency(totalIncomeTax - section24.applied_credit) }}</span>
      </div>
    </div>

    <!-- NI Breakdown (if applicable) -->
    <div v-if="hasNI" class="space-y-2 mb-3 pt-2 border-t border-savannah-100">
      <div class="text-xs font-medium text-neutral-500 uppercase">National Insurance</div>

      <!-- Class 1 NI (Employment) -->
      <template v-if="breakdown.ni_breakdown?.class_1">
        <div class="text-xs text-neutral-500 mb-1">Class 1 (Employment)</div>
        <div
          v-if="breakdown.ni_breakdown.class_1.main_rate?.contribution > 0"
          class="flex justify-between items-center text-sm"
        >
          <span class="text-neutral-500">
            {{ formatPercent(breakdown.ni_breakdown.class_1.main_rate.rate) }} on {{ formatCurrency(breakdown.ni_breakdown.class_1.main_rate.earnings) }}
          </span>
          <span class="text-raspberry-600">-{{ formatCurrency(breakdown.ni_breakdown.class_1.main_rate.contribution) }}</span>
        </div>
        <div
          v-if="breakdown.ni_breakdown.class_1.additional_rate?.contribution > 0"
          class="flex justify-between items-center text-sm"
        >
          <span class="text-neutral-500">
            {{ formatPercent(breakdown.ni_breakdown.class_1.additional_rate.rate) }} on {{ formatCurrency(breakdown.ni_breakdown.class_1.additional_rate.earnings) }}
          </span>
          <span class="text-raspberry-600">-{{ formatCurrency(breakdown.ni_breakdown.class_1.additional_rate.contribution) }}</span>
        </div>
      </template>

      <!-- Class 4 NI (Self-Employment) -->
      <template v-if="breakdown.ni_breakdown?.class_4">
        <div class="text-xs text-neutral-500 mb-1 mt-2">Class 4 (Self-Employment)</div>
        <div
          v-if="breakdown.ni_breakdown.class_4.main_rate?.contribution > 0"
          class="flex justify-between items-center text-sm"
        >
          <span class="text-neutral-500">
            {{ formatPercent(breakdown.ni_breakdown.class_4.main_rate.rate) }} on {{ formatCurrency(breakdown.ni_breakdown.class_4.main_rate.earnings) }}
          </span>
          <span class="text-raspberry-600">-{{ formatCurrency(breakdown.ni_breakdown.class_4.main_rate.contribution) }}</span>
        </div>
        <div
          v-if="breakdown.ni_breakdown.class_4.additional_rate?.contribution > 0"
          class="flex justify-between items-center text-sm"
        >
          <span class="text-neutral-500">
            {{ formatPercent(breakdown.ni_breakdown.class_4.additional_rate.rate) }} on {{ formatCurrency(breakdown.ni_breakdown.class_4.additional_rate.earnings) }}
          </span>
          <span class="text-raspberry-600">-{{ formatCurrency(breakdown.ni_breakdown.class_4.additional_rate.contribution) }}</span>
        </div>
      </template>

      <!-- Legacy single-class NI display (for backwards compatibility) -->
      <template v-if="breakdown.ni_breakdown?.class && !breakdown.ni_breakdown?.class_1 && !breakdown.ni_breakdown?.class_4">
        <div
          v-if="breakdown.ni_breakdown.main_rate?.contribution > 0"
          class="flex justify-between items-center text-sm"
        >
          <span class="text-neutral-500">
            {{ formatPercent(breakdown.ni_breakdown.main_rate.rate) }} on {{ formatCurrency(breakdown.ni_breakdown.main_rate.earnings) }}
          </span>
          <span class="text-raspberry-600">-{{ formatCurrency(breakdown.ni_breakdown.main_rate.contribution) }}</span>
        </div>
        <div
          v-if="breakdown.ni_breakdown.additional_rate?.contribution > 0"
          class="flex justify-between items-center text-sm"
        >
          <span class="text-neutral-500">
            {{ formatPercent(breakdown.ni_breakdown.additional_rate.rate) }} on {{ formatCurrency(breakdown.ni_breakdown.additional_rate.earnings) }}
          </span>
          <span class="text-raspberry-600">-{{ formatCurrency(breakdown.ni_breakdown.additional_rate.contribution) }}</span>
        </div>
      </template>
    </div>

    <!-- Net Income -->
    <div class="pt-2 border-t border-light-gray">
      <div class="flex justify-between items-center">
        <span class="text-sm font-medium text-neutral-500">Net Income</span>
        <span class="text-lg font-bold text-spring-700">{{ formatCurrency(adjustedNetIncome) }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { formatCurrency } from '@/utils/currency';

const props = defineProps({
  breakdown: {
    type: Object,
    required: true,
  },
  rentalBreakdown: {
    type: Object,
    default: null,
  },
  section24: {
    type: Object,
    default: null,
  },
});

// Check if this is earned income (combined employment, self-employment, rental, pension)
const isEarnedIncome = computed(() => {
  return props.breakdown.income_type === 'earned';
});

// Check if this is trust income (has special tax treatment)
const isTrustIncome = computed(() => {
  return props.breakdown.income_type === 'trust';
});

// Total income tax from all bands
const totalIncomeTax = computed(() => {
  const tb = props.breakdown.tax_breakdown;
  if (!tb) return 0;
  return (tb.basic_rate?.tax ?? 0) + (tb.higher_rate?.tax ?? 0) + (tb.additional_rate?.tax ?? 0);
});

// Net income adjusted for Section 24 credit
const adjustedNetIncome = computed(() => {
  const credit = props.section24?.applied_credit ?? 0;
  return (props.breakdown.net_income ?? 0) + credit;
});

// Check if NI applies
const hasNI = computed(() => {
  if (!props.breakdown.ni_breakdown) return false;
  // New format with class_1/class_4
  if (props.breakdown.ni_breakdown.class_1 || props.breakdown.ni_breakdown.class_4) return true;
  // Legacy format with total_ni
  return props.breakdown.ni_breakdown.total_ni > 0;
});

const formatPercent = (value) => {
  const pct = (value ?? 0) * 100;
  return Number.isInteger(pct) ? `${pct}%` : `${parseFloat(pct.toFixed(2))}%`;
};
</script>
