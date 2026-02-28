<template>
  <div v-if="scenario" class="space-y-0">
    <PlanWhatIfMetricRow label="Inheritance Tax Liability" :value="scenario.iht_liability" format="currency" />
    <PlanWhatIfMetricRow label="Effective Tax Rate" :value="scenario.effective_tax_rate" format="percentage" />
    <PlanWhatIfMetricRow label="Estate to Beneficiaries" :value="scenario.estate_to_beneficiaries" format="currency" />
    <PlanWhatIfMetricRow
      v-if="showSavings && scenario.total_mitigation_savings"
      label="Total Mitigation Savings"
      :value="scenario.total_mitigation_savings"
      format="currency"
    />
  </div>
</template>

<script>
import PlanWhatIfMetricRow from '@/components/Plans/Shared/PlanWhatIfMetricRow.vue';

export default {
  name: 'EstateWhatIfControls',
  components: { PlanWhatIfMetricRow },
  props: {
    scenario: { type: Object, default: null },
    showSavings: { type: Boolean, default: false },
  },
};
</script>
