<template>
  <PlanPageLayout
    title="Investment & Savings Plan"
    subtitle="Portfolio analysis, fee reduction, and goal alignment"
    :loading="loading"
    :error="error"
    loading-message="Analysing your investment portfolio..."
    @retry="loadPlan"
    @print="handlePrint"
  >
    <InvestmentPlanContent
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
import InvestmentPlanContent from '@/components/Plans/Investment/InvestmentPlanContent.vue';
import { planPrintMixin } from '@/components/Plans/Shared/planPrintMixin';

export default {
  name: 'InvestmentPlan',

  components: {
    PlanPageLayout,
    InvestmentPlanContent,
  },

  mixins: [planPrintMixin],

  computed: {
    ...mapGetters('plans', ['getPlan', 'isLoading', 'isRecalculating']),

    plan() { return this.getPlan('investment'); },
    loading() { return this.isLoading; },
    recalculating() { return this.isRecalculating; },
    error() { return this.$store.state.plans.error; },
  },

  mounted() {
    this.loadPlan();
  },

  methods: {
    ...mapActions('plans', ['fetchPlan', 'toggleAction', 'recalculateScenario']),

    async loadPlan() {
      try {
        await this.fetchPlan('investment');
      } catch {
        // Error is handled via store
      }
    },

    handleToggle(actionId) {
      this.toggleAction({ planKey: 'investment', actionId });
    },

    async handleRecalculate() {
      try {
        await this.recalculateScenario({ type: 'investment' });
      } catch {
        // Error handled via store
      }
    },

    handlePrint() {
      this.printPlan(this.plan, 'Investment & Savings Plan');
    },
  },
};
</script>
