<template>
  <section class="rounded-lg bg-white border border-light-gray p-6">
    <h2 class="text-body font-bold text-horizon-500 mb-4">What if you...</h2>

    <div class="mb-6">
      <label class="flex items-center justify-between mb-2">
        <span class="text-body-sm font-semibold text-horizon-500">Pension contribution</span>
        <span class="text-body-sm font-semibold text-raspberry-500">{{ pensionPct }}%</span>
      </label>
      <input
        v-model.number="pensionPct"
        type="range"
        min="0"
        max="40"
        step="1"
        class="w-full h-2 bg-savannah-200 rounded-lg appearance-none cursor-pointer"
        @input="onChange"
      />
    </div>

    <div class="mb-6 flex items-center justify-between">
      <label class="text-body-sm font-semibold text-horizon-500">Salary sacrifice</label>
      <input
        v-model="salarySacrifice"
        type="checkbox"
        class="h-5 w-5 rounded border-light-gray text-raspberry-500 focus:ring-violet-500"
        @change="onChange"
      />
    </div>

    <div class="mb-6">
      <label class="flex items-center justify-between mb-2">
        <span class="text-body-sm font-semibold text-horizon-500">Top up Individual Savings Account this year</span>
        <span class="text-body-sm font-semibold text-raspberry-500">{{ formatCurrency(isaTopup) }}</span>
      </label>
      <input
        v-model.number="isaTopup"
        type="range"
        min="0"
        max="20000"
        step="500"
        class="w-full h-2 bg-savannah-200 rounded-lg appearance-none cursor-pointer"
        @input="onChange"
      />
    </div>

    <div v-if="marriageAllowanceEligible" class="flex items-center justify-between">
      <label class="text-body-sm font-semibold text-horizon-500">Claim Marriage Allowance</label>
      <input
        v-model="marriageAllowanceClaimed"
        type="checkbox"
        class="h-5 w-5 rounded border-light-gray text-raspberry-500 focus:ring-violet-500"
        @change="onChange"
      />
    </div>
  </section>
</template>

<script>
import { mapState } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'StrategySliderPanel',
  mixins: [currencyMixin],
  data() {
    return {
      pensionPct: 5,
      salarySacrifice: false,
      isaTopup: 0,
      marriageAllowanceClaimed: false,
      _debounceTimer: null,
    };
  },
  computed: {
    ...mapState('taxStrategy', ['dashboard']),
    marriageAllowanceEligible() {
      return this.dashboard?.calculation_mode === 'single_earner_couple';
    },
  },
  beforeUnmount() {
    if (this._debounceTimer) {
      clearTimeout(this._debounceTimer);
    }
  },
  methods: {
    onChange() {
      if (this._debounceTimer) {
        clearTimeout(this._debounceTimer);
      }
      this._debounceTimer = setTimeout(() => {
        this.$store.dispatch('taxStrategy/recalculate', {
          pension_contribution_percent: this.pensionPct,
          salary_sacrifice: this.salarySacrifice,
          isa_additional_deposit: this.isaTopup,
          marriage_allowance_claimed: this.marriageAllowanceClaimed,
        });
      }, 200);
    },
  },
};
</script>
