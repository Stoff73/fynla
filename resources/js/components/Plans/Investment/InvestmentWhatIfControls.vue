<template>
  <div v-if="scenario" class="space-y-0">
    <PlanWhatIfMetricRow label="Total Wealth" :value="scenario.total_wealth" format="currency" />
    <PlanWhatIfMetricRow label="Annual Fees" :value="scenario.annual_fees" format="currency" />
    <PlanWhatIfMetricRow label="Emergency Fund" :value="scenario.emergency_fund_months" format="number" suffix="months" />
    <PlanWhatIfMetricRow label="5-Year Projection" :value="scenario.projected_5yr_value" format="currency" />
  </div>
</template>

<script>
import PlanWhatIfMetricRow from '@/components/Plans/Shared/PlanWhatIfMetricRow.vue';

export default {
  name: 'InvestmentWhatIfControls',
  components: { PlanWhatIfMetricRow },
  props: {
    scenario: { type: Object, default: null },
    label: { type: String, default: '' },
  },
};
</script>
