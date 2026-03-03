<template>
  <PlanPageLayout
    title="Retirement Plan"
    subtitle="Pension analysis, income projections, and contribution optimisation"
    :loading="loading"
    :error="error"
    loading-message="Analysing your retirement position..."
    @retry="loadPlan"
    @print="handlePrint"
  >
    <RetirementPlanContent
      v-if="plan"
      :plan="plan"
      @toggle-action="handleToggle"
      @update-funding-source="handleUpdateFundingSource"
    />
  </PlanPageLayout>
</template>

<script>
import { mapGetters, mapActions } from 'vuex';
import PlanPageLayout from '@/components/Plans/Shared/PlanPageLayout.vue';
import RetirementPlanContent from '@/components/Plans/Retirement/RetirementPlanContent.vue';
import { planPrintMixin } from '@/components/Plans/Shared/planPrintMixin';

export default {
  name: 'RetirementPlan',
  components: { PlanPageLayout, RetirementPlanContent },
  mixins: [planPrintMixin],

  computed: {
    ...mapGetters('plans', ['getPlan', 'isLoading']),
    plan() { return this.getPlan('retirement'); },
    loading() { return this.isLoading; },
    error() { return this.$store.state.plans.error; },
  },

  mounted() { this.loadPlan(); },

  methods: {
    ...mapActions('plans', ['fetchPlan', 'toggleAction', 'updateActionFundingSource']),
    async loadPlan() {
      try { await this.fetchPlan('retirement'); } catch { /* handled */ }
    },
    handleToggle(actionId) { this.toggleAction({ planKey: 'retirement', actionId }); },
    handleUpdateFundingSource(payload) {
      this.updateActionFundingSource({ planKey: 'retirement', ...payload });
    },
    handlePrint() { this.printPlan(this.plan, 'Retirement Plan'); },
  },
};
</script>
