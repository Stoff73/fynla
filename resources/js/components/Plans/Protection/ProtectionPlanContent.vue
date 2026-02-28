<template>
  <div>
    <PlanMissingDataPrompt :warning="plan.completeness_warning" />
    <PlanExecutiveSummary :summary="plan.executive_summary" />
    <ProtectionCurrentSituation :situation="plan.current_situation" />
    <PlanActionsList :actions="plan.actions" @toggle="$emit('toggle-action', $event)" />
    <PlanWhatIfComparison
      :is-approximate="plan.what_if?.is_approximate"
      :recalculating="recalculating"
      :current-scenario="plan.what_if?.current_scenario"
      :projected-scenario="plan.what_if?.projected_scenario"
      :chart-metrics="chartMetrics"
      @recalculate="$emit('recalculate')"
    >
      <template #current>
        <ProtectionWhatIfControls :scenario="plan.what_if?.current_scenario" />
      </template>
      <template #projected>
        <ProtectionWhatIfControls :scenario="plan.what_if?.projected_scenario" />
      </template>
    </PlanWhatIfComparison>
    <PlanConclusion :conclusion="plan.conclusion" />
  </div>
</template>

<script>
import PlanMissingDataPrompt from '@/components/Plans/Shared/PlanMissingDataPrompt.vue';
import PlanExecutiveSummary from '@/components/Plans/Shared/PlanExecutiveSummary.vue';
import PlanActionsList from '@/components/Plans/Shared/PlanActionsList.vue';
import PlanWhatIfComparison from '@/components/Plans/Shared/PlanWhatIfComparison.vue';
import PlanConclusion from '@/components/Plans/Shared/PlanConclusion.vue';
import ProtectionCurrentSituation from './ProtectionCurrentSituation.vue';
import ProtectionWhatIfControls from './ProtectionWhatIfControls.vue';

export default {
  name: 'ProtectionPlanContent',
  components: { PlanMissingDataPrompt, PlanExecutiveSummary, PlanActionsList, PlanWhatIfComparison, PlanConclusion, ProtectionCurrentSituation, ProtectionWhatIfControls },
  props: {
    plan: { type: Object, required: true },
    recalculating: { type: Boolean, default: false },
  },
  data() {
    return {
      chartMetrics: [
        { key: 'total_coverage_gap', label: 'Total Coverage Gap' },
        { key: 'life_insurance_gap', label: 'Life Insurance Gap' },
        { key: 'critical_illness_gap', label: 'Critical Illness Gap' },
        { key: 'income_protection_gap', label: 'Income Protection Gap' },
      ],
    };
  },
  emits: ['toggle-action', 'recalculate'],
};
</script>
