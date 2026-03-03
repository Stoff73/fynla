<template>
  <div>
    <PlanMissingDataPrompt :warning="plan.completeness_warning" />

    <!-- Structured executive summary (new) or legacy fallback -->
    <ProtectionExecutiveSummary v-if="hasStructuredSummary" :summary="plan.executive_summary" />
    <PlanExecutiveSummary v-else :summary="plan.executive_summary" />

    <ProtectionPersonalInformation :info="plan.personal_information" />

    <PlanGoalSection
      v-if="hasGoals"
      :linked-goals="plan.linked_goals"
      :unlinked-goals="plan.unlinked_goals"
    />

    <ProtectionCurrentSituation :situation="plan.current_situation" />

    <PlanActionsList :actions="plan.actions" @toggle="$emit('toggle-action', $event)" />

    <PlanWhatIfComparison
      :current-scenario="plan.what_if?.current_scenario"
      :projected-scenario="plan.what_if?.projected_scenario"
      :chart-metrics="chartMetrics"
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
import PlanGoalSection from '@/components/Plans/Shared/PlanGoalSection.vue';
import ProtectionExecutiveSummary from './ProtectionExecutiveSummary.vue';
import ProtectionPersonalInformation from './ProtectionPersonalInformation.vue';
import ProtectionCurrentSituation from './ProtectionCurrentSituation.vue';
import ProtectionWhatIfControls from './ProtectionWhatIfControls.vue';

export default {
  name: 'ProtectionPlanContent',
  components: {
    PlanMissingDataPrompt,
    PlanExecutiveSummary,
    PlanActionsList,
    PlanWhatIfComparison,
    PlanConclusion,
    PlanGoalSection,
    ProtectionExecutiveSummary,
    ProtectionPersonalInformation,
    ProtectionCurrentSituation,
    ProtectionWhatIfControls,
  },
  props: {
    plan: { type: Object, required: true },
  },
  data() {
    return {
      chartMetrics: [
        { key: 'life_insurance_coverage', label: 'Life Insurance Cover' },
        { key: 'critical_illness_coverage', label: 'Critical Illness Cover' },
      ],
    };
  },
  computed: {
    hasStructuredSummary() {
      return !!this.plan.executive_summary?.greeting;
    },
    hasGoals() {
      return (this.plan.linked_goals?.length > 0) || (this.plan.unlinked_goals?.length > 0);
    },
  },
  emits: ['toggle-action'],
};
</script>
