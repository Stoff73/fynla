<template>
  <div v-if="scenario" class="space-y-0">
    <PlanWhatIfMetricRow label="Total Wealth" :value="scenario.total_wealth" format="currency" />
    <PlanWhatIfMetricRow label="Annual Fees" :value="scenario.annual_fees" format="currency" />
    <PlanWhatIfMetricRow label="Emergency Fund" :value="scenario.emergency_fund_months" format="number" suffix="months" />
    <PlanWhatIfMetricRow
      v-if="scenario.additional_monthly_savings != null"
      label="Additional Monthly Savings"
      :value="scenario.additional_monthly_savings"
      format="currency"
    />
    <PlanWhatIfMetricRow label="At Retirement" :value="scenario.projected_value" format="currency" />
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
