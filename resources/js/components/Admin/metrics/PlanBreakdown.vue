<template>
  <div>
    <h2 class="text-lg font-bold text-horizon-500 mb-4">Plan Breakdown</h2>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <div
        v-for="plan in plans"
        :key="plan.plan"
        class="bg-white shadow-card rounded-card p-5"
      >
        <p class="text-xs font-semibold text-neutral-500 uppercase tracking-wider mb-2">
          {{ plan.plan }}
        </p>
        <p class="text-3xl font-black text-horizon-500">
          {{ plan.total }}
        </p>
        <div class="mt-2 space-y-1">
          <p class="text-xs text-neutral-500">
            {{ plan.monthly }} monthly / {{ plan.yearly }} yearly
          </p>
          <p class="text-sm font-semibold text-horizon-500">
            {{ formatRevenue(plan) }}/mo
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'PlanBreakdown',

  props: {
    data: {
      type: Array,
      required: true,
    },
  },

  computed: {
    plans() {
      return this.data || [];
    },
  },

  methods: {
    formatRevenue(plan) {
      const monthlyPounds = (plan.monthly_revenue || 0) / 100;
      const yearlyMonthlyPounds = ((plan.yearly_revenue || 0) / 100) / 12;
      const total = monthlyPounds + yearlyMonthlyPounds;
      return `£${total.toFixed(2)}`;
    },
  },
};
</script>
