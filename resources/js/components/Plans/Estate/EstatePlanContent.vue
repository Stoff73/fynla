<template>
  <div>
    <PlanMissingDataPrompt :warning="plan.completeness_warning" />
    <PlanExecutiveSummary :summary="plan.executive_summary" />
    <PlanGoalSection
      :linked-goals="plan.linked_goals || []"
      :unlinked-goals="plan.unlinked_goals || []"
    />
    <EstateCurrentSituation :situation="plan.current_situation" />
    <EstateGroupedActions
      :actions="plan.actions"
      :what-if="plan.what_if"
      @toggle="$emit('toggle-action', $event)"
    />
    <PlanConclusion :conclusion="plan.conclusion" />
  </div>
</template>

<script>
import PlanMissingDataPrompt from '@/components/Plans/Shared/PlanMissingDataPrompt.vue';
import PlanExecutiveSummary from '@/components/Plans/Shared/PlanExecutiveSummary.vue';
import PlanGoalSection from '@/components/Plans/Shared/PlanGoalSection.vue';
import PlanConclusion from '@/components/Plans/Shared/PlanConclusion.vue';
import EstateCurrentSituation from './EstateCurrentSituation.vue';
import EstateGroupedActions from './EstateGroupedActions.vue';

export default {
  name: 'EstatePlanContent',
  components: { PlanMissingDataPrompt, PlanExecutiveSummary, PlanGoalSection, PlanConclusion, EstateCurrentSituation, EstateGroupedActions },
  props: {
    plan: { type: Object, required: true },
  },
  emits: ['toggle-action'],
};
</script>
