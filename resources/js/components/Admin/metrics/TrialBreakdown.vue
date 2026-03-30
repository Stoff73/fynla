<template>
  <div>
    <h2 class="text-lg font-bold text-horizon-500 mb-4">Trial Breakdown</h2>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
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
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'TrialBreakdown',

  props: {
    data: {
      type: Object,
      required: true,
    },
  },

  computed: {
    cards() {
      return [
        { label: '4+ Days', value: this.data.four_plus_days ?? 0 },
        { label: '3 Days', value: this.data.three_days ?? 0 },
        { label: '2 Days', value: this.data.two_days ?? 0 },
        { label: '1 Day', value: this.data.one_day ?? 0 },
        { label: 'Expiring Today', value: this.data.expiring_today ?? 0 },
        { label: 'Expired', value: this.data.expired ?? 0 },
      ];
    },
  },
};
</script>
