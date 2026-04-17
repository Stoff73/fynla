<template>
  <div
    class="my-8 p-8 bg-gradient-to-br from-horizon-500 to-raspberry-500 rounded-lg text-white text-center"
  >
    <p class="text-sm font-semibold uppercase tracking-wider opacity-80 mb-2">{{ block.label }}</p>
    <p class="text-5xl md:text-6xl font-black mb-1">{{ formattedValue }}</p>
    <p class="text-xs opacity-70">Tax year {{ currentTaxYear }}</p>
  </div>
</template>

<script>
import { getCurrentTaxYear } from '@/utils/dateFormatter';
import * as taxConfig from '@/constants/taxConfig';
import { currencyMixin } from '@/mixins/currencyMixin';

const STAT_MAP = {
  isa_annual_allowance: { source: 'ISA_ANNUAL_ALLOWANCE', format: 'currency' },
  personal_allowance: { source: 'PERSONAL_ALLOWANCE', format: 'currency' },
  pension_annual_allowance: { source: 'PENSION_ANNUAL_ALLOWANCE', format: 'currency' },
  iht_nil_rate_band: { source: 'IHT_NIL_RATE_BAND', format: 'currency' },
  cgt_annual_allowance: { source: 'CGT_ANNUAL_ALLOWANCE', format: 'currency' },
};

export default {
  name: 'TaxYearStatBlock',
  mixins: [currencyMixin],
  props: { block: { type: Object, required: true } },
  computed: {
    currentTaxYear() { return getCurrentTaxYear(); },
    formattedValue() {
      const mapping = STAT_MAP[this.block.stat_key];
      if (!mapping) return '—';
      const value = taxConfig[mapping.source];
      if (value == null) return '—';
      return mapping.format === 'currency' ? this.formatCurrency(value) : value;
    },
  },
};
</script>
