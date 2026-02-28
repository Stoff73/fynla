<template>
  <div>
    <PlanMissingDataPrompt :warning="plan.completeness_warning" />
    <PlanExecutiveSummary :summary="plan.executive_summary" />
    <RetirementCurrentSituation :situation="plan.current_situation" />
    <RetirementGroupedActions
      :actions="plan.actions"
      :pension-projections="plan.pension_projections || []"
      :what-if="plan.what_if"
      @toggle="$emit('toggle-action', $event)"
    />
    <PlanConclusion :conclusion="plan.conclusion" />
  </div>
</template>

<script>
import PlanMissingDataPrompt from '@/components/Plans/Shared/PlanMissingDataPrompt.vue';
import PlanExecutiveSummary from '@/components/Plans/Shared/PlanExecutiveSummary.vue';
import PlanConclusion from '@/components/Plans/Shared/PlanConclusion.vue';
import RetirementCurrentSituation from './RetirementCurrentSituation.vue';
import RetirementGroupedActions from './RetirementGroupedActions.vue';

export default {
  name: 'RetirementPlanContent',
  components: { PlanMissingDataPrompt, PlanExecutiveSummary, PlanConclusion, RetirementCurrentSituation, RetirementGroupedActions },
  props: {
    plan: { type: Object, required: true },
  },
  emits: ['toggle-action'],
};
</script>
