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
      @toggle-action="handleToggle"
      @update-funding-source="handleUpdateFundingSource"
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
    ...mapGetters('plans', ['getPlan', 'isLoading']),

    plan() { return this.getPlan('investment'); },
    loading() { return this.isLoading; },
    error() { return this.$store.state.plans.error; },
  },

  mounted() {
    this.loadPlan();
  },

  methods: {
    ...mapActions('plans', ['fetchPlan', 'toggleAction', 'updateActionFundingSource']),

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

    handleUpdateFundingSource(payload) {
      this.updateActionFundingSource({ planKey: 'investment', ...payload });
    },

    handlePrint() {
      this.printPlan(this.plan, 'Investment & Savings Plan');
    },
  },
};
</script>
