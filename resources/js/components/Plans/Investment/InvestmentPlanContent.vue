<template>
  <div>
    <!-- Missing Data Warning -->
    <PlanMissingDataPrompt :warning="plan.completeness_warning" />

    <!-- Executive Summary -->
    <PlanExecutiveSummary :summary="plan.executive_summary" />

    <!-- Current Situation -->
    <InvestmentCurrentSituation :situation="plan.current_situation" />

    <!-- Recommended Actions -->
    <PlanActionsList
      :actions="plan.actions"
      @toggle="$emit('toggle-action', $event)"
    />

    <!-- What-If Comparison -->
    <PlanWhatIfComparison
      :is-approximate="plan.what_if?.is_approximate"
      :recalculating="recalculating"
      :current-scenario="plan.what_if?.current_scenario"
      :projected-scenario="plan.what_if?.projected_scenario"
      :chart-metrics="chartMetrics"
      @recalculate="$emit('recalculate')"
    >
      <template #current>
        <InvestmentWhatIfControls :scenario="plan.what_if?.current_scenario" label="current" />
      </template>
      <template #projected>
        <InvestmentWhatIfControls :scenario="plan.what_if?.projected_scenario" label="projected" />
      </template>
    </PlanWhatIfComparison>

    <!-- Conclusion -->
    <PlanConclusion :conclusion="plan.conclusion" />
  </div>
</template>

<script>
import PlanMissingDataPrompt from '@/components/Plans/Shared/PlanMissingDataPrompt.vue';
import PlanExecutiveSummary from '@/components/Plans/Shared/PlanExecutiveSummary.vue';
import PlanActionsList from '@/components/Plans/Shared/PlanActionsList.vue';
import PlanWhatIfComparison from '@/components/Plans/Shared/PlanWhatIfComparison.vue';
import PlanConclusion from '@/components/Plans/Shared/PlanConclusion.vue';
import InvestmentCurrentSituation from './InvestmentCurrentSituation.vue';
import InvestmentWhatIfControls from './InvestmentWhatIfControls.vue';

export default {
  name: 'InvestmentPlanContent',

  components: {
    PlanMissingDataPrompt,
    PlanExecutiveSummary,
    PlanActionsList,
    PlanWhatIfComparison,
    PlanConclusion,
    InvestmentCurrentSituation,
    InvestmentWhatIfControls,
  },

  props: {
    plan: { type: Object, required: true },
    recalculating: { type: Boolean, default: false },
  },

  data() {
    return {
      chartMetrics: [
        { key: 'total_wealth', label: 'Total Wealth' },
        { key: 'annual_fees', label: 'Annual Fees' },
        { key: 'projected_5yr_value', label: '5-Year Projection' },
      ],
    };
  },

  emits: ['toggle-action', 'recalculate'],
};
</script>
