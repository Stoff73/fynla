<template>
  <div class="bg-white rounded-lg border border-gray-200 p-6 mb-6">
    <!-- Header -->
    <div class="flex justify-between items-start mb-4">
      <div>
        <h4 class="text-lg font-semibold text-gray-900">Tax Breakdown</h4>
        <p class="text-sm text-gray-500">Annual retirement income tax calculation</p>
      </div>
      <span class="text-sm font-medium text-gray-500">
        {{ formatPercent(breakdown.effective_rate || 0) }} effective rate
      </span>
    </div>

    <!-- Income Sources Section -->
    <div class="space-y-2 mb-4">
      <div class="text-xs font-medium text-gray-500 uppercase">Income Sources</div>

      <div
        v-for="(source, index) in breakdown.sources || []"
        :key="index"
        class="flex justify-between items-center text-sm py-1"
      >
        <span class="text-gray-600">
          {{ getSourceTypeLabel(source.source_type) }}: {{ source.name }}
        </span>
        <span class="text-gray-900 font-medium">{{ formatCurrency(source.amount) }}</span>
      </div>
    </div>

    <!-- Tax Calculation Section -->
    <div class="space-y-2 mb-4 pt-3 border-t border-gray-100">
      <div class="text-xs font-medium text-gray-500 uppercase">Tax Calculation</div>

      <!-- Tax-free income -->
      <div
        v-if="taxFreeIncome > 0"
        class="flex justify-between items-center text-sm"
      >
        <span class="text-gray-600">Tax-free/deferred income (Bond, ISA, PCLS)</span>
        <span class="text-green-600 font-medium">{{ formatCurrency(taxFreeIncome) }}</span>
      </div>

      <!-- Personal Allowance -->
      <div
        v-if="breakdown.band_usage?.personal_allowance?.used > 0"
        class="flex justify-between items-center text-sm"
      >
        <span class="text-gray-600">Personal Allowance: {{ formatCurrency(breakdown.band_usage.personal_allowance.used) }} @ 0%</span>
        <span class="text-gray-400 font-medium">£0</span>
      </div>

      <!-- Basic Rate -->
      <div
        v-if="breakdown.band_usage?.basic_rate?.used > 0"
        class="flex justify-between items-center text-sm"
      >
        <span class="text-gray-600">Basic: {{ formatCurrency(breakdown.band_usage.basic_rate.used) }} @ 20%</span>
        <span class="text-red-600 font-medium">-{{ formatCurrency(breakdown.band_usage.basic_rate.used * 0.2) }}</span>
      </div>

      <!-- Higher Rate -->
      <div
        v-if="breakdown.band_usage?.higher_rate?.used > 0"
        class="flex justify-between items-center text-sm"
      >
        <span class="text-gray-600">Higher: {{ formatCurrency(breakdown.band_usage.higher_rate.used) }} @ 40%</span>
        <span class="text-red-600 font-medium">-{{ formatCurrency(breakdown.band_usage.higher_rate.used * 0.4) }}</span>
      </div>

      <!-- Additional Rate -->
      <div
        v-if="breakdown.band_usage?.additional_rate?.used > 0"
        class="flex justify-between items-center text-sm"
      >
        <span class="text-gray-600">Additional: {{ formatCurrency(breakdown.band_usage.additional_rate.used) }} @ 45%</span>
        <span class="text-red-600 font-medium">-{{ formatCurrency(breakdown.band_usage.additional_rate.used * 0.45) }}</span>
      </div>
    </div>

    <!-- Summary Section -->
    <div class="space-y-2 pt-3 border-t border-gray-200">
      <div class="flex justify-between items-center text-sm">
        <span class="text-gray-600">Gross Income</span>
        <span class="text-gray-900 font-medium">{{ formatCurrency(breakdown.gross_income || 0) }}</span>
      </div>
      <div class="flex justify-between items-center text-sm">
        <span class="text-gray-600">Total Tax</span>
        <span class="text-red-600 font-medium">-{{ formatCurrency(breakdown.total_tax || 0) }}</span>
      </div>
      <div class="flex justify-between items-center pt-2 border-t border-gray-200">
        <span class="text-sm font-semibold text-gray-900">Net Income</span>
        <span class="text-lg font-bold text-green-700">{{ formatCurrency(breakdown.net_income || 0) }}</span>
      </div>
    </div>

    <!-- Optimisation Tips -->
    <div v-if="tips.length > 0" class="mt-4 pt-3 border-t border-gray-100">
      <div class="text-xs font-medium text-gray-500 uppercase mb-2">Optimisation Tips</div>
      <ul class="space-y-1">
        <li v-for="(tip, index) in tips" :key="index" class="text-sm text-gray-600">
          • {{ tip }}
        </li>
      </ul>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'TaxBreakdownCard',

  mixins: [currencyMixin],

  props: {
    breakdown: {
      type: Object,
      required: true,
    },
  },

  computed: {
    taxFreeIncome() {
      return this.breakdown.tax_free_income || 0;
    },

    tips() {
      const tips = [];
      const bandUsage = this.breakdown.band_usage;

      if (!bandUsage) return tips;

      // Check if personal allowance is not fully used
      if (bandUsage.personal_allowance?.remaining > 0) {
        tips.push(`You have £${this.formatNumber(bandUsage.personal_allowance.remaining)} of unused Personal Allowance. Consider drawing more pension income tax-free.`);
      }

      // Check if in higher rate but basic rate has room
      if (bandUsage.higher_rate?.used > 0 && bandUsage.basic_rate?.remaining > 0) {
        tips.push('Consider spreading income across tax years to stay within the basic rate band.');
      }

      return tips;
    },
  },

  methods: {
    getSourceTypeLabel(sourceType) {
      const labels = {
        pension_pot_pcls: 'PCLS',
        pension_pot_drawdown: 'Pension Pot',
        dc_pension_pcls: 'PCLS',
        dc_pension_drawdown: 'Pension',
        db_pension: 'DB Pension',
        state_pension: 'State Pension',
        isa: 'ISA',
        gia: 'GIA',
        onshore_bond: 'Onshore Bond',
        offshore_bond: 'Offshore Bond',
        bond: 'Bond',
        savings: 'Savings',
      };
      return labels[sourceType] || 'Income';
    },

    formatNumber(value) {
      return new Intl.NumberFormat('en-GB').format(value || 0);
    },

    formatPercent(value) {
      return (value * 100).toFixed(1) + '%';
    },
  },
};
</script>
