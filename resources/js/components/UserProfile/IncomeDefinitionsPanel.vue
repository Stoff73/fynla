<template>
  <div v-if="definitions" class="bg-white rounded-lg border border-light-gray shadow-sm p-6">
    <h3 class="text-lg font-bold text-horizon-500 mb-6">Your Income Definitions</h3>

    <!-- Total Income -->
    <div class="flex justify-between items-baseline mb-1">
      <span class="text-body-sm font-semibold text-horizon-500">Total Income</span>
      <span class="text-body font-bold text-horizon-500">{{ formatCurrency(definitions.total_income) }}</span>
    </div>
    <p class="text-xs text-neutral-500 mb-4">
      <span v-for="(value, key) in activeComponents" :key="key">
        {{ componentLabel(key) }} {{ formatCurrency(value) }}
        <span v-if="!isLastActive(key)"> &middot; </span>
      </span>
    </p>

    <!-- Deductions to Net Income -->
    <div v-if="definitions.deductions.pension_relief > 0" class="flex justify-between text-body-sm text-neutral-500 mb-1">
      <span>Less pension relief</span>
      <span>-{{ formatCurrency(definitions.deductions.pension_relief) }}</span>
    </div>
    <div v-if="definitions.deductions.gift_aid_gross > 0" class="flex justify-between text-body-sm text-neutral-500 mb-1">
      <span>Less Gift Aid (grossed up)</span>
      <span>-{{ formatCurrency(definitions.deductions.gift_aid_gross) }}</span>
    </div>

    <!-- Net Income -->
    <div class="border-t border-light-gray pt-2 mt-2 mb-4">
      <div class="flex justify-between items-baseline">
        <span class="text-body-sm font-semibold text-horizon-500">Net Income</span>
        <span class="text-body font-bold text-horizon-500">{{ formatCurrency(definitions.net_income) }}</span>
      </div>
    </div>

    <!-- Deduction to ANI -->
    <div v-if="definitions.deductions.blind_persons_allowance > 0" class="flex justify-between text-body-sm text-neutral-500 mb-1">
      <span>Less Blind Person's Allowance</span>
      <span>-{{ formatCurrency(definitions.deductions.blind_persons_allowance) }}</span>
    </div>

    <!-- Adjusted Net Income -->
    <div class="border-t border-light-gray pt-2 mt-2 mb-4">
      <div class="flex justify-between items-baseline">
        <span class="text-body-sm font-semibold text-horizon-500">Adjusted Net Income</span>
        <span class="text-body font-bold text-horizon-500">{{ formatCurrency(definitions.adjusted_net_income) }}</span>
      </div>
    </div>

    <!-- Deduction to Threshold -->
    <div v-if="definitions.deductions.employee_pension_contributions > 0" class="flex justify-between text-body-sm text-neutral-500 mb-1">
      <span>Less employee pension contributions</span>
      <span>-{{ formatCurrency(definitions.deductions.employee_pension_contributions) }}</span>
    </div>

    <!-- Threshold Income -->
    <div class="border-t border-light-gray pt-2 mt-2 mb-1">
      <div class="flex justify-between items-baseline">
        <span class="text-body-sm font-semibold text-horizon-500">Threshold Income</span>
        <span class="text-body font-bold text-horizon-500">{{ formatCurrency(definitions.threshold_income) }}</span>
      </div>
    </div>
    <p class="text-xs mb-4" :class="definitions.threshold_income > PENSION_TAPER_THRESHOLD_INCOME ? 'text-raspberry-500' : 'text-spring-500'">
      {{ definitions.threshold_income > PENSION_TAPER_THRESHOLD_INCOME ? `Above ${formatCurrency(PENSION_TAPER_THRESHOLD_INCOME)} \u2014 pension taper may apply` : `Below ${formatCurrency(PENSION_TAPER_THRESHOLD_INCOME)} \u2014 no pension taper triggered` }}
    </p>

    <!-- Addition to Adjusted Income -->
    <div v-if="definitions.deductions.employer_pension_contributions > 0" class="flex justify-between text-body-sm text-neutral-500 mb-1">
      <span>Plus employer pension contributions</span>
      <span>+{{ formatCurrency(definitions.deductions.employer_pension_contributions) }}</span>
    </div>

    <!-- Adjusted Income -->
    <div class="border-t border-light-gray pt-2 mt-2 mb-1">
      <div class="flex justify-between items-baseline">
        <span class="text-body-sm font-semibold text-horizon-500">Adjusted Income</span>
        <span class="text-body font-bold text-horizon-500">{{ formatCurrency(definitions.adjusted_income) }}</span>
      </div>
    </div>
    <p class="text-xs mb-6" :class="definitions.adjusted_income > PENSION_TAPER_ADJUSTED_INCOME ? 'text-raspberry-500' : 'text-spring-500'">
      {{ definitions.adjusted_income > PENSION_TAPER_ADJUSTED_INCOME ? `Above ${formatCurrency(PENSION_TAPER_ADJUSTED_INCOME)} \u2014 Annual Allowance reduced` : `Below ${formatCurrency(PENSION_TAPER_ADJUSTED_INCOME)} \u2014 full Annual Allowance available` }}
    </p>

    <!-- Adjusted Allowances -->
    <div class="bg-eggshell-500 rounded-lg p-4">
      <h4 class="text-sm font-bold text-horizon-500 mb-3">Your Allowances</h4>
      <div class="space-y-2">
        <div class="flex justify-between items-center">
          <span class="text-body-sm text-horizon-500">Personal Allowance</span>
          <div class="text-right">
            <span class="text-body-sm font-bold text-horizon-500">{{ formatCurrency(definitions.adjusted_allowances.personal_allowance) }}</span>
            <span v-if="definitions.adjusted_allowances.personal_allowance_tapered" class="text-xs text-raspberry-500 ml-2">
              (reduced from {{ formatCurrency(definitions.adjusted_allowances.personal_allowance_full) }})
            </span>
            <span v-else class="text-xs text-spring-500 ml-2">(full)</span>
          </div>
        </div>
        <div class="flex justify-between items-center">
          <span class="text-body-sm text-horizon-500">Pension Annual Allowance</span>
          <div class="text-right">
            <span class="text-body-sm font-bold text-horizon-500">{{ formatCurrency(definitions.adjusted_allowances.pension_annual_allowance) }}</span>
            <span v-if="definitions.adjusted_allowances.pension_aa_tapered" class="text-xs text-raspberry-500 ml-2">
              (reduced from {{ formatCurrency(definitions.adjusted_allowances.pension_annual_allowance_full) }})
            </span>
            <span v-else class="text-xs text-spring-500 ml-2">(full)</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { formatCurrency } from '@/utils/currency';
import { PENSION_TAPER_THRESHOLD_INCOME, PENSION_TAPER_ADJUSTED_INCOME } from '@/constants/taxConfig';

export default {
  name: 'IncomeDefinitionsPanel',
  props: {
    definitions: {
      type: Object,
      default: null,
    },
  },
  setup() {
    return { formatCurrency, PENSION_TAPER_THRESHOLD_INCOME, PENSION_TAPER_ADJUSTED_INCOME };
  },
  computed: {
    activeComponents() {
      if (!this.definitions?.components) return {};
      return Object.fromEntries(
        Object.entries(this.definitions.components).filter(([, v]) => v > 0)
      );
    },
  },
  methods: {
    componentLabel(key) {
      const labels = {
        employment: 'Employment',
        self_employment: 'Self-Employment',
        rental: 'Rental',
        dividend: 'Dividends',
        interest: 'Interest',
        other: 'Other',
        trust: 'Trust',
        pension_income: 'Pension',
      };
      return labels[key] || key;
    },
    isLastActive(key) {
      const keys = Object.keys(this.activeComponents);
      return keys.indexOf(key) === keys.length - 1;
    },
  },
};
</script>
