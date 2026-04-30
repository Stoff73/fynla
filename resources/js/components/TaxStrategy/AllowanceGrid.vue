<template>
  <div>
    <div class="flex items-baseline justify-between mb-4">
      <h2 class="text-h3 font-bold text-horizon-500">Allowances for {{ taxYearShort }}</h2>
      <span v-if="totalHeadroom > 0" class="text-body-sm font-semibold text-raspberry-500">
        {{ formatCurrency(Math.round(totalHeadroom)) }} of headroom
      </span>
    </div>

    <div v-if="headroom.length" class="mb-6">
      <h3 class="text-caption uppercase tracking-wide text-neutral-500 mb-3">
        Headroom available
      </h3>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <AllowanceCard v-for="a in headroom" :key="a.key" :allowance="a" />
      </div>
    </div>

    <div v-if="utilised.length">
      <h3 class="text-caption uppercase tracking-wide text-neutral-500 mb-3">
        Well-utilised
      </h3>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <AllowanceCard v-for="a in utilised" :key="a.key" :allowance="a" compact />
      </div>
    </div>
  </div>
</template>

<script>
import { mapGetters } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';
import AllowanceCard from './AllowanceCard.vue';

export default {
  name: 'AllowanceGrid',
  mixins: [currencyMixin],
  components: { AllowanceCard },
  props: {
    allowances: { type: Array, required: true },
  },
  computed: {
    ...mapGetters('taxStrategy', ['taxYear']),
    taxYearShort() {
      return this.taxYear || '';
    },
    headroom() {
      return [...this.allowances]
        .filter((a) => a.utilisation_pct < 90)
        .sort((b, a) => (a.remaining || 0) - (b.remaining || 0));
    },
    utilised() {
      return this.allowances.filter((a) => a.utilisation_pct >= 90);
    },
    totalHeadroom() {
      return this.headroom.reduce((sum, a) => sum + (Number(a.remaining) || 0), 0);
    },
  },
};
</script>
