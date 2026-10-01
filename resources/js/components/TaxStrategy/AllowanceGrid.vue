<template>
  <div>
    <div class="flex items-baseline justify-between mb-4">
      <h2 class="text-h3 font-bold text-horizon-500">Allowances for {{ taxYearShort }}</h2>
      <!-- A count, not a total: allowances of different kinds cannot be added
           (audit item 40, CSJ 2026-10-01), as /m and iOS show it. -->
      <span v-if="headroomCount > 0" class="text-body-sm font-semibold text-raspberry-500">
        {{ headroomCount }} {{ headroomCount === 1 ? 'allowance' : 'allowances' }} with headroom
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

    <div v-if="utilised.length" class="mb-6">
      <h3 class="text-caption uppercase tracking-wide text-neutral-500 mb-3">
        Well-utilised
      </h3>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <AllowanceCard v-for="a in utilised" :key="a.key" :allowance="a" compact />
      </div>
    </div>

    <div v-if="unconfirmed.length" class="mb-6">
      <h3 class="text-caption uppercase tracking-wide text-neutral-500 mb-3">
        Current-year use not confirmed
      </h3>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <AllowanceCard v-for="a in unconfirmed" :key="a.key" :allowance="a" compact />
      </div>
    </div>

    <div v-if="unavailable.length">
      <h3 class="text-caption uppercase tracking-wide text-neutral-500 mb-3">
        Not available
      </h3>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <AllowanceCard v-for="a in unavailable" :key="a.key" :allowance="a" compact />
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
    ...mapGetters('taxStrategy', ['taxYear', 'summary']),
    taxYearShort() {
      return this.taxYear || '';
    },
    // Grouped by the server's tile state (TaxStrategyService `tile_state`).
    headroom() {
      return [...this.allowances]
        .filter((a) => a.tile_state === 'open')
        .sort((b, a) => (a.remaining || 0) - (b.remaining || 0));
    },
    utilised() {
      return this.allowances.filter((a) => a.tile_state === 'full' || a.tile_state === 'budget_capped');
    },
    unconfirmed() {
      return this.allowances.filter((a) => a.tile_state === 'unconfirmed');
    },
    unavailable() {
      return this.allowances.filter((a) => a.tile_state === 'unavailable');
    },
    headroomCount() {
      return Number(this.summary?.headroom_count) || 0;
    },
  },
};
</script>
