<template>
  <div>
    <PlanMissingDataPrompt :warning="plan.completeness_warning" />
    <PlanExecutiveSummary :summary="plan.executive_summary" />
    <RetirementCurrentSituation :situation="plan.current_situation" />
    <PlanActionsList :actions="plan.actions" @toggle="$emit('toggle-action', $event)" />
    <PlanWhatIfComparison
      :current-scenario="plan.what_if?.current_scenario"
      :projected-scenario="plan.what_if?.projected_scenario"
      :chart-metrics="chartMetrics"
    >
      <template #current>
        <RetirementWhatIfControls :scenario="plan.what_if?.current_scenario" />
      </template>
      <template #projected>
        <RetirementWhatIfControls :scenario="plan.what_if?.projected_scenario" />
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
import RetirementCurrentSituation from './RetirementCurrentSituation.vue';
import RetirementWhatIfControls from './RetirementWhatIfControls.vue';

export default {
  name: 'RetirementPlanContent',
  components: { PlanMissingDataPrompt, PlanExecutiveSummary, PlanActionsList, PlanWhatIfComparison, PlanConclusion, RetirementCurrentSituation, RetirementWhatIfControls },
  props: {
    plan: { type: Object, required: true },
  },
  data() {
    return {
      chartMetrics: [
        { key: 'projected_annual_income', label: 'Projected Annual Income' },
        { key: 'income_gap', label: 'Income Gap' },
        { key: 'dc_value_at_retirement', label: 'Defined Contribution Value at Retirement' },
      ],
    };
  },
  emits: ['toggle-action'],
};
</script>
