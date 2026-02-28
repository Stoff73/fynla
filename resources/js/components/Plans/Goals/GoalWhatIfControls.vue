<template>
  <div v-if="scenario" class="space-y-0">
    <PlanWhatIfMetricRow label="Monthly Contribution" :value="scenario.monthly_contribution" format="currency" />
    <PlanWhatIfMetricRow label="Months to Goal" :value="scenario.months_to_goal" format="number" suffix="months" />
    <PlanWhatIfMetricRow label="Estimated Completion" :value="formattedDate" format="text" />
    <PlanWhatIfMetricRow label="Total Contributions" :value="scenario.total_contributions" format="currency" />
    <PlanWhatIfMetricRow
      v-if="scenario.lump_sum"
      label="Lump Sum"
      :value="scenario.lump_sum"
      format="currency"
    />
  </div>
</template>

<script>
import PlanWhatIfMetricRow from '@/components/Plans/Shared/PlanWhatIfMetricRow.vue';

export default {
  name: 'GoalWhatIfControls',
  components: { PlanWhatIfMetricRow },
  props: {
    scenario: { type: Object, default: null },
  },
  computed: {
    formattedDate() {
      if (!this.scenario?.completion_date) return 'Unknown';
      const date = new Date(this.scenario.completion_date);
      return date.toLocaleDateString('en-GB', { month: 'short', year: 'numeric' });
    },
  },
};
</script>
