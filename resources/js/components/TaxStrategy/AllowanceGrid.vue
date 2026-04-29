<template>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <section>
      <h2 class="text-body font-bold text-horizon-500 mb-3">Income</h2>
      <div class="space-y-3">
        <AllowanceCard
          v-for="allowance in incomeAllowances"
          :key="allowance.key"
          :allowance="allowance"
        />
      </div>
    </section>
    <section>
      <h2 class="text-body font-bold text-horizon-500 mb-3">Investment &amp; Cash</h2>
      <div class="space-y-3">
        <AllowanceCard
          v-for="allowance in investmentAllowances"
          :key="allowance.key"
          :allowance="allowance"
        />
      </div>
    </section>
  </div>
</template>

<script>
import AllowanceCard from './AllowanceCard.vue';

const INCOME_KEYS = [
  'personal_allowance',
  'savings_allowance',
  'starting_rate_for_savings',
  'marriage_allowance',
];
const INVESTMENT_KEYS = [
  'isa_allowance',
  'cgt_allowance',
  'dividend_allowance',
  'pension_annual_allowance',
];

export default {
  name: 'AllowanceGrid',
  components: { AllowanceCard },
  props: {
    allowances: { type: Array, required: true },
  },
  computed: {
    incomeAllowances() {
      return INCOME_KEYS
        .map((k) => this.allowances.find((a) => a.key === k))
        .filter(Boolean);
    },
    investmentAllowances() {
      return INVESTMENT_KEYS
        .map((k) => this.allowances.find((a) => a.key === k))
        .filter(Boolean);
    },
  },
};
</script>
