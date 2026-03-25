<template>
  <div>
    <!-- 1. Executive Summary -->
    <HolisticExecutiveSummary
      :available-plans="availablePlanTypes"
      :personal-info="personalInfo"
    />

    <!-- 2. Personal Information (shown once) -->
    <InvestmentPersonalInformation
      v-if="personalInfo"
      :info="personalInfo"
    />

    <!-- 3. Current Situation — per module -->
    <template v-if="hasAnySituation">
      <!-- Protection -->
      <HolisticModuleSection
        v-if="protectionPlan && protectionPlan.current_situation"
        title="Protection — Current Situation"
        subtitle="Your insurance coverage overview"
        color="violet"
      >
        <ProtectionCurrentSituation :situation="protectionPlan.current_situation" />
      </HolisticModuleSection>

      <!-- Investment -->
      <HolisticModuleSection
        v-if="investmentPlan && hasInvestmentData"
        title="Investment — Current Situation"
        subtitle="Your investment portfolio overview"
        color="violet"
      >
        <HolisticInvestmentSituation :situation="investmentPlan.current_situation" />
      </HolisticModuleSection>

      <!-- Savings -->
      <HolisticModuleSection
        v-if="investmentPlan && hasSavingsData"
        title="Savings — Current Situation"
        subtitle="Your savings and emergency fund overview"
        color="spring"
      >
        <HolisticSavingsSituation :situation="investmentPlan.current_situation" />
      </HolisticModuleSection>

      <!-- Retirement -->
      <HolisticModuleSection
        v-if="retirementPlan && retirementPlan.current_situation"
        title="Retirement — Current Situation"
        subtitle="Your pension and retirement overview"
        color="spring"
      >
        <RetirementCurrentSituation :situation="retirementPlan.current_situation" />
      </HolisticModuleSection>

      <!-- Estate -->
      <HolisticModuleSection
        v-if="estatePlan && estatePlan.current_situation"
        title="Estate Planning — Current Situation"
        subtitle="Your Inheritance Tax position"
        color="gray"
      >
        <EstateCurrentSituation :situation="estatePlan.current_situation" />
      </HolisticModuleSection>
    </template>

    <!-- 4. Recommended Actions — per module -->
    <template v-if="hasAnyActions">
      <!-- Protection Actions -->
      <HolisticModuleSection
        v-if="protectionPlan && protectionPlan.actions && protectionPlan.actions.length"
        title="Protection — Recommended Actions"
        color="violet"
      >
        <PlanActionsList
          :actions="protectionPlan.actions"
          @toggle="handleProtectionToggle"
        />
        <PlanWhatIfComparison
          v-if="protectionPlan.what_if"
          :current-scenario="protectionPlan.what_if.current_scenario"
          :projected-scenario="computedProjectedScenario"
          :chart-metrics="protectionChartMetrics"
        >
          <template #current>
            <ProtectionWhatIfControls :scenario="protectionPlan.what_if.current_scenario" />
          </template>
          <template #projected>
            <ProtectionWhatIfControls :scenario="computedProjectedScenario" />
          </template>
        </PlanWhatIfComparison>
      </HolisticModuleSection>

      <!-- Investment & Savings Actions -->
      <HolisticModuleSection
        v-if="investmentPlan && investmentPlan.actions && investmentPlan.actions.length"
        title="Investment & Savings — Recommended Actions"
        color="violet"
      >
        <InvestmentGroupedActions
          :actions="investmentPlan.actions"
          :what-if="investmentPlan.what_if"
          @toggle="handleInvestmentToggle"
          @update-funding-source="handleInvestmentFundingSource"
        />
      </HolisticModuleSection>

      <!-- Retirement Actions -->
      <HolisticModuleSection
        v-if="retirementPlan && retirementPlan.actions && retirementPlan.actions.length"
        title="Retirement — Recommended Actions"
        color="spring"
      >
        <RetirementGroupedActions
          :actions="retirementPlan.actions"
          :pension-projections="retirementPlan.pension_projections || []"
          :what-if="retirementPlan.what_if"
          @toggle="handleRetirementToggle"
          @update-funding-source="handleRetirementFundingSource"
        />
      </HolisticModuleSection>

      <!-- Estate Actions -->
      <HolisticModuleSection
        v-if="estatePlan && estatePlan.actions && estatePlan.actions.length"
        title="Estate Planning — Recommended Actions"
        color="gray"
      >
        <EstateGroupedActions
          :actions="estatePlan.actions"
          :what-if="estatePlan.what_if"
          @toggle="handleEstateToggle"
        />
      </HolisticModuleSection>
    </template>

    <!-- 5. Priority Area -->
    <HolisticPriorityArea
      v-if="mergedActions.length"
      :all-actions="mergedActions"
      :monthly-disposable-income="monthlyDisposableIncome"
    />

    <!-- 6. Conclusion -->
    <HolisticConclusion
      :conclusions="conclusions"
      :all-actions="mergedActions"
    />
  </div>
</template>

<script>
import HolisticExecutiveSummary from './HolisticExecutiveSummary.vue';
import HolisticModuleSection from './HolisticModuleSection.vue';
import HolisticInvestmentSituation from './HolisticInvestmentSituation.vue';
import HolisticSavingsSituation from './HolisticSavingsSituation.vue';
import HolisticPriorityArea from './HolisticPriorityArea.vue';
import HolisticConclusion from './HolisticConclusion.vue';
import InvestmentPersonalInformation from '@/components/Plans/Investment/InvestmentPersonalInformation.vue';
import ProtectionCurrentSituation from '@/components/Plans/Protection/ProtectionCurrentSituation.vue';
import RetirementCurrentSituation from '@/components/Plans/Retirement/RetirementCurrentSituation.vue';
import EstateCurrentSituation from '@/components/Plans/Estate/EstateCurrentSituation.vue';
import PlanActionsList from '@/components/Plans/Shared/PlanActionsList.vue';
import PlanWhatIfComparison from '@/components/Plans/Shared/PlanWhatIfComparison.vue';
import ProtectionWhatIfControls from '@/components/Plans/Protection/ProtectionWhatIfControls.vue';
import InvestmentGroupedActions from '@/components/Plans/Investment/InvestmentGroupedActions.vue';
import RetirementGroupedActions from '@/components/Plans/Retirement/RetirementGroupedActions.vue';
import EstateGroupedActions from '@/components/Plans/Estate/EstateGroupedActions.vue';

export default {
  name: 'HolisticPlanContent',

  components: {
    HolisticExecutiveSummary,
    HolisticModuleSection,
    HolisticInvestmentSituation,
    HolisticSavingsSituation,
    HolisticPriorityArea,
    HolisticConclusion,
    InvestmentPersonalInformation,
    ProtectionCurrentSituation,
    RetirementCurrentSituation,
    EstateCurrentSituation,
    PlanActionsList,
    PlanWhatIfComparison,
    ProtectionWhatIfControls,
    InvestmentGroupedActions,
    RetirementGroupedActions,
    EstateGroupedActions,
  },

  props: {
    protectionPlan: { type: Object, default: null },
    investmentPlan: { type: Object, default: null },
    retirementPlan: { type: Object, default: null },
    estatePlan: { type: Object, default: null },
  },

  emits: ['toggle-action', 'update-funding-source'],

  data() {
    return {
      protectionChartMetrics: [
        { key: 'life_insurance_coverage', label: 'Life Insurance Cover' },
        { key: 'critical_illness_coverage', label: 'Critical Illness Cover' },
        { key: 'income_protection_coverage', label: 'Income Protection Cover' },
      ],
    };
  },

  computed: {
    availablePlanTypes() {
      const types = [];
      if (this.protectionPlan) types.push('protection');
      if (this.investmentPlan) types.push('investment');
      if (this.retirementPlan) types.push('retirement');
      if (this.estatePlan) types.push('estate');
      return types;
    },

    personalInfo() {
      return this.investmentPlan?.personal_information
        || this.retirementPlan?.personal_information
        || this.protectionPlan?.personal_information
        || this.estatePlan?.personal_information
        || null;
    },

    hasAnySituation() {
      return this.protectionPlan?.current_situation
        || this.investmentPlan?.current_situation
        || this.retirementPlan?.current_situation
        || this.estatePlan?.current_situation;
    },

    hasInvestmentData() {
      const sit = this.investmentPlan?.current_situation;
      return sit && (sit.investment_accounts?.length > 0 || sit.total_investment_value);
    },

    hasSavingsData() {
      const sit = this.investmentPlan?.current_situation;
      return sit && (sit.savings_accounts?.length > 0 || sit.total_savings_value);
    },

    hasAnyActions() {
      return (this.protectionPlan?.actions?.length > 0)
        || (this.investmentPlan?.actions?.length > 0)
        || (this.retirementPlan?.actions?.length > 0)
        || (this.estatePlan?.actions?.length > 0);
    },

    // Protection reactive what-if (same logic as ProtectionPlanContent)
    computedProjectedScenario() {
      const current = this.protectionPlan?.what_if?.current_scenario;
      if (!current) return this.protectionPlan?.what_if?.projected_scenario || null;

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

      const actions = this.protectionPlan?.actions || [];
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

    monthlyDisposableIncome() {
      return this.personalInfo?.monthly_disposable || 0;
    },

    mergedActions() {
      const tagged = [];
      const tagActions = (actions, sourceModule) => {
        if (!actions) return;
        actions.forEach(action => {
          tagged.push({
            ...action,
            sourceModule,
            isGoalAction: !!(action.goal_id || action.source === 'goal'),
            isTaxOptimisation: this.isTaxOptimisationAction(action),
            monthlyCost: this.extractMonthlyCost(action),
          });
        });
      };

      tagActions(this.protectionPlan?.actions, 'protection');
      tagActions(this.investmentPlan?.actions, 'investment');
      tagActions(this.retirementPlan?.actions, 'retirement');
      tagActions(this.estatePlan?.actions, 'estate');

      return tagged;
    },

    conclusions() {
      const c = {};
      if (this.protectionPlan?.conclusion) c.protection = this.protectionPlan.conclusion;
      if (this.investmentPlan?.conclusion) c.investment = this.investmentPlan.conclusion;
      if (this.retirementPlan?.conclusion) c.retirement = this.retirementPlan.conclusion;
      if (this.estatePlan?.conclusion) c.estate = this.estatePlan.conclusion;
      return c;
    },
  },

  methods: {
    handleProtectionToggle(actionId) {
      this.$emit('toggle-action', { planKey: 'protection', actionId });
    },

    handleInvestmentToggle(actionId) {
      this.$emit('toggle-action', { planKey: 'investment', actionId });
    },

    handleRetirementToggle(actionId) {
      this.$emit('toggle-action', { planKey: 'retirement', actionId });
    },

    handleEstateToggle(actionId) {
      this.$emit('toggle-action', { planKey: 'estate', actionId });
    },

    handleInvestmentFundingSource(payload) {
      this.$emit('update-funding-source', { planKey: 'investment', ...payload });
    },

    handleRetirementFundingSource(payload) {
      this.$emit('update-funding-source', { planKey: 'retirement', ...payload });
    },

    isTaxOptimisationAction(action) {
      const category = (action.category || '').toLowerCase();
      const title = (action.title || '').toLowerCase();
      return category.includes('isa') || category.includes('tax')
        || title.includes('isa') || title.includes('allowance')
        || title.includes('tax');
    },

    extractMonthlyCost(action) {
      // Try cascade_params first (retirement/investment contributions)
      if (action.cascade_params?.additional_monthly) {
        return action.cascade_params.additional_monthly;
      }
      // Protection premiums
      if (action.impact_parameters?.monthly_premium_estimate) {
        return action.impact_parameters.monthly_premium_estimate;
      }
      if (action.impact_parameters?.premium) {
        return action.impact_parameters.premium;
      }
      // Estate affordability
      if (action.affordability?.monthly_premium_estimate) {
        return action.affordability.monthly_premium_estimate;
      }
      // Fallback: annual / 12
      if (action.estimated_impact) {
        return Math.abs(action.estimated_impact) / 12;
      }
      return 0;
    },
  },
};
</script>
