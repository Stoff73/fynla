<template>
  <div>
    <PlanMissingDataPrompt :warning="plan.completeness_warning" />
    <RetirementExecutiveSummary :summary="plan.executive_summary" />
    <RetirementPersonalInformation :info="plan.personal_information" />
    <RetirementCurrentSituation :situation="plan.current_situation" />
    <PlanGoalSection
      :linked-goals="plan.linked_goals || []"
      :unlinked-goals="plan.unlinked_goals || []"
    />
    <RetirementGroupedActions
      :actions="plan.actions"
      :pension-projections="plan.pension_projections || []"
      :what-if="plan.what_if"
      @toggle="$emit('toggle-action', $event)"
      @update-funding-source="$emit('update-funding-source', $event)"
    />
    <PlanConclusion :conclusion="plan.conclusion" />
  </div>
</template>

<script>
import PlanMissingDataPrompt from '@/components/Plans/Shared/PlanMissingDataPrompt.vue';
import PlanGoalSection from '@/components/Plans/Shared/PlanGoalSection.vue';
import PlanConclusion from '@/components/Plans/Shared/PlanConclusion.vue';
import RetirementExecutiveSummary from './RetirementExecutiveSummary.vue';
import RetirementPersonalInformation from './RetirementPersonalInformation.vue';
import RetirementCurrentSituation from './RetirementCurrentSituation.vue';
import RetirementGroupedActions from './RetirementGroupedActions.vue';

export default {
  name: 'RetirementPlanContent',
  components: { PlanMissingDataPrompt, RetirementExecutiveSummary, RetirementPersonalInformation, PlanGoalSection, PlanConclusion, RetirementCurrentSituation, RetirementGroupedActions },
  props: {
    plan: { type: Object, required: true },
  },
  emits: ['toggle-action', 'update-funding-source'],
};
</script>
