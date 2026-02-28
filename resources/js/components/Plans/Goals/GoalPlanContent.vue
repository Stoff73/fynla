<template>
  <div>
    <PlanMissingDataPrompt :warning="plan.completeness_warning" />
    <PlanExecutiveSummary :summary="plan.executive_summary" />
    <GoalCurrentSituation :situation="plan.current_situation" :goal="plan.goal" />
    <PlanActionsList :actions="plan.actions" @toggle="$emit('toggle-action', $event)" />
    <PlanWhatIfComparison
      :current-scenario="plan.what_if?.current_scenario"
      :projected-scenario="plan.what_if?.projected_scenario"
      :chart-metrics="chartMetrics"
    >
      <template #current>
        <GoalWhatIfControls :scenario="plan.what_if?.current_scenario" />
      </template>
      <template #projected>
        <GoalWhatIfControls :scenario="plan.what_if?.projected_scenario" />
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
import GoalCurrentSituation from './GoalCurrentSituation.vue';
import GoalWhatIfControls from './GoalWhatIfControls.vue';

export default {
  name: 'GoalPlanContent',
  components: { PlanMissingDataPrompt, PlanExecutiveSummary, PlanActionsList, PlanWhatIfComparison, PlanConclusion, GoalCurrentSituation, GoalWhatIfControls },
  props: {
    plan: { type: Object, required: true },
  },
  data() {
    return {
      chartMetrics: [
        { key: 'monthly_contribution', label: 'Monthly Contribution' },
        { key: 'total_contributions', label: 'Total Contributions' },
      ],
    };
  },
  emits: ['toggle-action'],
};
</script>
