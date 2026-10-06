<template>
  <div v-if="scenario" class="space-y-0">
    <PlanWhatIfMetricRow label="Total Coverage Gap" :value="scenario.total_coverage_gap" format="currency" />
    <PlanWhatIfMetricRow label="Life Insurance Gap" :value="scenario.life_insurance_gap" format="currency" />
    <PlanWhatIfMetricRow label="Critical Illness Gap" :value="scenario.critical_illness_gap" format="currency" />
    <PlanWhatIfMetricRow label="Income Protection Gap" :value="scenario.income_protection_gap" format="currency" suffix="/month" />
  </div>
</template>

<script>
import PlanWhatIfMetricRow from '@/components/Plans/Shared/PlanWhatIfMetricRow.vue';

export default {
  name: 'ProtectionWhatIfControls',
  components: { PlanWhatIfMetricRow },
  props: {
    scenario: { type: Object, default: null },
  },
};
</script>
