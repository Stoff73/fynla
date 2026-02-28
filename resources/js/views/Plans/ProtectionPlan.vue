<template>
  <PlanPageLayout
    title="Protection Plan"
    subtitle="Coverage analysis, gap identification, and risk scenarios"
    :loading="loading"
    :error="error"
    loading-message="Analysing your protection coverage..."
    @retry="loadPlan"
    @print="handlePrint"
  >
    <ProtectionPlanContent
      v-if="plan"
      :plan="plan"
      @toggle-action="handleToggle"
    />
  </PlanPageLayout>
</template>

<script>
import { mapGetters, mapActions } from 'vuex';
import PlanPageLayout from '@/components/Plans/Shared/PlanPageLayout.vue';
import ProtectionPlanContent from '@/components/Plans/Protection/ProtectionPlanContent.vue';
import { planPrintMixin } from '@/components/Plans/Shared/planPrintMixin';

export default {
  name: 'ProtectionPlan',
  components: { PlanPageLayout, ProtectionPlanContent },
  mixins: [planPrintMixin],

  computed: {
    ...mapGetters('plans', ['getPlan', 'isLoading']),
    plan() { return this.getPlan('protection'); },
    loading() { return this.isLoading; },
    error() { return this.$store.state.plans.error; },
  },

  mounted() { this.loadPlan(); },

  methods: {
    ...mapActions('plans', ['fetchPlan', 'toggleAction']),
    async loadPlan() {
      try { await this.fetchPlan('protection'); } catch { /* handled */ }
    },
    handleToggle(actionId) { this.toggleAction({ planKey: 'protection', actionId }); },
    handlePrint() { this.printPlan(this.plan, 'Protection Plan'); },
  },
};
</script>
