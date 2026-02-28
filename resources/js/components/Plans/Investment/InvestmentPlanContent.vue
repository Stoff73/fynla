<template>
  <div>
    <!-- Missing Data Warning -->
    <PlanMissingDataPrompt :warning="plan.completeness_warning" />

    <!-- Executive Summary -->
    <PlanExecutiveSummary :summary="plan.executive_summary" />

    <!-- Current Situation -->
    <InvestmentCurrentSituation :situation="plan.current_situation" />

    <!-- Recommended Actions (accounts first, portfolio second, portfolio projection at bottom) -->
    <InvestmentGroupedActions
      :actions="plan.actions"
      :account-projections="plan.account_projections || []"
      :what-if="plan.what_if"
      @toggle="$emit('toggle-action', $event)"
    />

    <!-- Conclusion -->
    <PlanConclusion :conclusion="plan.conclusion" />
  </div>
</template>

<script>
import PlanMissingDataPrompt from '@/components/Plans/Shared/PlanMissingDataPrompt.vue';
import PlanExecutiveSummary from '@/components/Plans/Shared/PlanExecutiveSummary.vue';
import PlanConclusion from '@/components/Plans/Shared/PlanConclusion.vue';
import InvestmentCurrentSituation from './InvestmentCurrentSituation.vue';
import InvestmentGroupedActions from './InvestmentGroupedActions.vue';

export default {
  name: 'InvestmentPlanContent',

  components: {
    PlanMissingDataPrompt,
    PlanExecutiveSummary,
    PlanConclusion,
    InvestmentCurrentSituation,
    InvestmentGroupedActions,
  },

  props: {
    plan: { type: Object, required: true },
  },

  emits: ['toggle-action'],
};
</script>
