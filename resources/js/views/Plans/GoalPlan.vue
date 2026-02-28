<template>
  <PlanPageLayout
    :title="goalName ? `${goalName} Plan` : 'Goal Plan'"
    subtitle="Progress tracking, contribution strategy, and goal optimisation"
    :loading="loading"
    :error="error"
    loading-message="Analysing your goal progress..."
    @retry="loadPlan"
    @print="handlePrint"
  >
    <GoalPlanContent
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
import GoalPlanContent from '@/components/Plans/Goals/GoalPlanContent.vue';
import { planPrintMixin } from '@/components/Plans/Shared/planPrintMixin';

export default {
  name: 'GoalPlan',
  components: { PlanPageLayout, GoalPlanContent },
  mixins: [planPrintMixin],

  computed: {
    ...mapGetters('plans', ['getGoalPlan', 'isLoading', 'isRecalculating']),

    goalId() { return this.$route.params.goalId; },
    plan() { return this.getGoalPlan(this.goalId); },
    goalName() { return this.plan?.goal?.name || ''; },
    loading() { return this.isLoading; },
    recalculating() { return this.isRecalculating; },
    error() { return this.$store.state.plans.error; },
  },

  mounted() { this.loadPlan(); },

  methods: {
    ...mapActions('plans', ['fetchGoalPlan', 'toggleAction', 'recalculateGoalScenario']),

    async loadPlan() {
      try { await this.fetchGoalPlan(this.goalId); } catch { /* handled */ }
    },
    handleToggle(actionId) {
      this.toggleAction({ planKey: `goal_${this.goalId}`, actionId });
    },
    async handleRecalculate() {
      try { await this.recalculateGoalScenario({ goalId: this.goalId }); } catch { /* handled */ }
    },
    handlePrint() {
      this.printPlan(this.plan, this.goalName ? `${this.goalName} Plan` : 'Goal Plan');
    },
  },
};
</script>
