<template>
  <div>
    <!-- Missing Data Warning -->
    <PlanMissingDataPrompt :warning="plan.completeness_warning" />

    <!-- Executive Summary -->
    <InvestmentExecutiveSummary
      v-if="hasStructuredSummary"
      :summary="plan.executive_summary"
    />
    <PlanExecutiveSummary
      v-else-if="plan.executive_summary"
      :summary="plan.executive_summary"
    />

    <!-- Personal Information -->
    <InvestmentPersonalInformation :info="plan.personal_information" />

    <!-- Linked Goals -->
    <PlanGoalSection
      :linked-goals="plan.linked_goals || []"
      :unlinked-goals="plan.unlinked_goals || []"
    />

    <!-- Current Situation -->
    <InvestmentCurrentSituation :situation="plan.current_situation" />

    <!-- Recommended Actions (accounts first, portfolio second, cascading charts per action) -->
    <InvestmentGroupedActions
      :actions="plan.actions"
      :what-if="plan.what_if"
      @toggle="$emit('toggle-action', $event)"
      @update-funding-source="$emit('update-funding-source', $event)"
    />

    <!-- Conclusion -->
    <PlanConclusion :conclusion="plan.conclusion" />
  </div>
</template>

<script>
import PlanMissingDataPrompt from '@/components/Plans/Shared/PlanMissingDataPrompt.vue';
import PlanExecutiveSummary from '@/components/Plans/Shared/PlanExecutiveSummary.vue';
import PlanGoalSection from '@/components/Plans/Shared/PlanGoalSection.vue';
import PlanConclusion from '@/components/Plans/Shared/PlanConclusion.vue';
import InvestmentExecutiveSummary from './InvestmentExecutiveSummary.vue';
import InvestmentPersonalInformation from './InvestmentPersonalInformation.vue';
import InvestmentCurrentSituation from './InvestmentCurrentSituation.vue';
import InvestmentGroupedActions from './InvestmentGroupedActions.vue';

export default {
  name: 'InvestmentPlanContent',

  components: {
    PlanMissingDataPrompt,
    PlanExecutiveSummary,
    InvestmentExecutiveSummary,
    InvestmentPersonalInformation,
    PlanGoalSection,
    PlanConclusion,
    InvestmentCurrentSituation,
    InvestmentGroupedActions,
  },

  props: {
    plan: { type: Object, required: true },
  },

  emits: ['toggle-action', 'update-funding-source'],

  computed: {
    hasStructuredSummary() {
      const s = this.plan.executive_summary;
      return s && s.greeting && !s.narrative;
    },
  },
};
</script>
