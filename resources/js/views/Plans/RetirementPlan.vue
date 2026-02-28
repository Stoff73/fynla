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
      :recalculating="recalculating"
      @toggle-action="handleToggle"
      @recalculate="handleRecalculate"
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
    ...mapGetters('plans', ['getPlan', 'isLoading', 'isRecalculating']),
    plan() { return this.getPlan('retirement'); },
    loading() { return this.isLoading; },
    recalculating() { return this.isRecalculating; },
    error() { return this.$store.state.plans.error; },
  },

  mounted() { this.loadPlan(); },

  methods: {
    ...mapActions('plans', ['fetchPlan', 'toggleAction', 'recalculateScenario']),
    async loadPlan() {
      try { await this.fetchPlan('retirement'); } catch { /* handled */ }
    },
    handleToggle(actionId) { this.toggleAction({ planKey: 'retirement', actionId }); },
    async handleRecalculate() {
      try { await this.recalculateScenario({ type: 'retirement' }); } catch { /* handled */ }
    },
    handlePrint() { this.printPlan(this.plan, 'Retirement Plan'); },
  },
};
</script>
