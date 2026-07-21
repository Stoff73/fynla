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
import { mapGetters } from 'vuex';
import { getCurrentTaxYear } from '@/utils/dateFormatter';
import { currencyMixin } from '@/mixins/currencyMixin';

const STAT_MAP = {
  isa_annual_allowance: { getter: 'isaAnnualAllowance', format: 'currency' },
  personal_allowance: { getter: 'personalAllowance', format: 'currency' },
  pension_annual_allowance: { getter: 'pensionAnnualAllowance', format: 'currency' },
  iht_nil_rate_band: { getter: 'ihtNilRateBand', format: 'currency' },
  cgt_annual_allowance: { getter: 'cgtAnnualAllowance', format: 'currency' },
};

export default {
  name: 'TaxYearStatBlock',
  mixins: [currencyMixin],
  props: { block: { type: Object, required: true } },
  computed: {
    ...mapGetters('taxConfig', [
      'isaAnnualAllowance',
      'personalAllowance',
      'pensionAnnualAllowance',
      'ihtNilRateBand',
      'cgtAnnualAllowance',
    ]),
    currentTaxYear() { return getCurrentTaxYear(); },
    formattedValue() {
      const mapping = STAT_MAP[this.block.stat_key];
      if (!mapping) return '—';
      const value = this[mapping.getter];
      if (value == null) return '—';
      return mapping.format === 'currency' ? this.formatCurrency(value) : value;
    },
  },
};
</script>
