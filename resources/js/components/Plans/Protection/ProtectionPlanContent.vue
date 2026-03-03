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
      :projected-scenario="computedProjectedScenario"
      :chart-metrics="chartMetrics"
    >
      <template #current>
        <ProtectionWhatIfControls :scenario="plan.what_if?.current_scenario" />
      </template>
      <template #projected>
        <ProtectionWhatIfControls :scenario="computedProjectedScenario" />
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
        { key: 'income_protection_coverage', label: 'Income Protection Cover' },
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
    computedProjectedScenario() {
      const current = this.plan.what_if?.current_scenario;
      if (!current) return this.plan.what_if?.projected_scenario || null;

      const lifeGap = current.life_insurance_gap || 0;
      const ciGap = current.critical_illness_gap || 0;
      const ipGap = current.income_protection_gap || 0;
      const lifeCoverage = current.life_insurance_coverage || 0;
      const ciCoverage = current.critical_illness_coverage || 0;
      const ipCoverage = current.income_protection_coverage || 0;

      let lifeReduction = 0;
      let ciReduction = 0;
      let ipReduction = 0;
      let additionalPremium = 0;

      const actions = this.plan.actions || [];
      actions.forEach((action) => {
        if (!action.enabled) return;

        const category = (action.category || '').toLowerCase();
        const coverageAmount = action.impact_parameters?.coverage_amount || 0;
        const premium = action.impact_parameters?.premium || 0;

        if (category.includes('life')) {
          lifeReduction += coverageAmount || lifeGap;
          additionalPremium += premium;
        } else if (category.includes('critical')) {
          ciReduction += coverageAmount || ciGap;
          additionalPremium += premium;
        } else if (category.includes('income')) {
          ipReduction += coverageAmount || ipGap;
          additionalPremium += premium;
        }
      });

      const projectedLifeGap = Math.max(0, lifeGap - lifeReduction);
      const projectedCiGap = Math.max(0, ciGap - ciReduction);
      const projectedIpGap = Math.max(0, ipGap - ipReduction);
      const projectedTotalGap = projectedLifeGap + projectedCiGap + (projectedIpGap * 12);

      return {
        total_coverage_gap: projectedTotalGap,
        life_insurance_gap: projectedLifeGap,
        critical_illness_gap: projectedCiGap,
        income_protection_gap: projectedIpGap,
        life_insurance_coverage: lifeCoverage + lifeReduction,
        critical_illness_coverage: ciCoverage + ciReduction,
        income_protection_coverage: ipCoverage + ipReduction,
        life_insurance_need: current.life_insurance_need || 0,
        critical_illness_need: current.critical_illness_need || 0,
        income_protection_need: current.income_protection_need || 0,
        estimated_additional_premium: additionalPremium || null,
      };
    },
  },
  emits: ['toggle-action'],
};
</script>
