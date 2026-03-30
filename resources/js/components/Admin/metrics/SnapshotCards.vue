<template>
  <div>
    <h2 class="text-lg font-bold text-horizon-500 mb-4">Overview</h2>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <div
        v-for="card in cards"
        :key="card.label"
        class="bg-white shadow-card rounded-card p-5"
      >
        <p class="text-xs font-semibold text-neutral-500 uppercase tracking-wider mb-2">
          {{ card.label }}
        </p>
        <p class="text-3xl font-black text-horizon-500">
          {{ card.value }}
        </p>
        <p v-if="card.sub" class="text-xs text-neutral-500 mt-1">
          {{ card.sub }}
        </p>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'SnapshotCards',

  props: {
    data: {
      type: Object,
      required: true,
    },
  },

  computed: {
    conversionRate() {
      if (!this.data.total_registered || this.data.total_registered === 0) return 0;
      return ((this.data.active_subscribers / this.data.total_registered) * 100).toFixed(1);
    },

    neverPaidPercent() {
      if (!this.data.total_registered || this.data.total_registered === 0) return 0;
      return ((this.data.never_paid / this.data.total_registered) * 100).toFixed(1);
    },

    cards() {
      return [
        {
          label: 'Total Registered',
          value: this.data.total_registered ?? 0,
          sub: null,
        },
        {
          label: 'Active Subscribers',
          value: this.data.active_subscribers ?? 0,
          sub: `${this.conversionRate}% conversion`,
        },
        {
          label: 'On Trial',
          value: this.data.on_trial ?? 0,
          sub: null,
        },
        {
          label: 'Never Paid',
          value: this.data.never_paid ?? 0,
          sub: `${this.neverPaidPercent}% of total`,
        },
      ];
    },
  },
};
</script>
