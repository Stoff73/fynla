<template>
  <div>
    <PlanMissingDataPrompt :warning="plan.completeness_warning" />

    <!-- Structured executive summary (new) or legacy fallback -->
    <EstateExecutiveSummary v-if="hasStructuredSummary" :summary="plan.executive_summary" />
    <PlanExecutiveSummary v-else :summary="plan.executive_summary" />

    <EstatePersonalInformation :info="plan.personal_information" />

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
import PlanConclusion from '@/components/Plans/Shared/PlanConclusion.vue';
import EstateExecutiveSummary from './EstateExecutiveSummary.vue';
import EstatePersonalInformation from './EstatePersonalInformation.vue';
import EstateCurrentSituation from './EstateCurrentSituation.vue';
import EstateGroupedActions from './EstateGroupedActions.vue';

export default {
  name: 'EstatePlanContent',
  components: {
    PlanMissingDataPrompt,
    PlanExecutiveSummary,
    PlanConclusion,
    EstateExecutiveSummary,
    EstatePersonalInformation,
    EstateCurrentSituation,
    EstateGroupedActions,
  },
  props: {
    plan: { type: Object, required: true },
  },
  computed: {
    hasStructuredSummary() {
      return !!this.plan.executive_summary?.greeting;
    },
  },
  emits: ['toggle-action'],
};
</script>
