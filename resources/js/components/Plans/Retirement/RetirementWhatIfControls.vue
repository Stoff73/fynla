<template>
  <div v-if="scenario" class="space-y-0">
    <PlanWhatIfMetricRow label="Projected Annual Income" :value="scenario.projected_annual_income" format="currency" />
    <PlanWhatIfMetricRow label="Income Gap" :value="scenario.income_gap" format="currency" />
    <PlanWhatIfMetricRow label="Total Pension Value" :value="scenario.total_dc_value" format="currency" />
    <PlanWhatIfMetricRow label="At Retirement" :value="scenario.dc_value_at_retirement" format="currency" />
    <PlanWhatIfMetricRow
      v-if="scenario.additional_monthly_contribution"
      label="Additional Monthly Contribution"
      :value="scenario.additional_monthly_contribution"
      format="currency"
    />
  </div>
</template>

<script>
import PlanWhatIfMetricRow from '@/components/Plans/Shared/PlanWhatIfMetricRow.vue';

export default {
  name: 'RetirementWhatIfControls',
  components: { PlanWhatIfMetricRow },
  props: {
    scenario: { type: Object, default: null },
  },
};
</script>
