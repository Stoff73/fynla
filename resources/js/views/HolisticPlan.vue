<template>
  <PlanPageLayout
    title="Holistic Financial Plan"
    subtitle="Your unified view across Protection, Investment, Retirement and Estate"
    :loading="holisticLoading"
    :error="error"
    loading-message="Loading your holistic plan..."
    @retry="loadAllPlans"
    @print="handlePrint"
  >
    <HolisticPlanContent
      v-if="hasAnyPlan"
      :protection-plan="protectionPlan"
      :investment-plan="investmentPlan"
      :retirement-plan="retirementPlan"
      :estate-plan="estatePlan"
      @toggle-action="handleToggleAction"
      @update-funding-source="handleUpdateFundingSource"
    />

    <div v-else class="py-12 text-center">
      <svg class="mx-auto h-12 w-12 text-horizon-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
      </svg>
      <h3 class="mt-4 text-lg font-medium text-horizon-500">No Plans Available</h3>
      <p class="mt-2 text-sm text-neutral-500">Generate individual plans first to see your holistic view.</p>
      <button
        @click="$router.push('/plans')"
        class="mt-4 inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-raspberry-500 hover:bg-raspberry-600"
      >
        Go to Plans
      </button>
    </div>
  </PlanPageLayout>
</template>

<script>
import { mapState, mapActions } from 'vuex';
import PlanPageLayout from '@/components/Plans/Shared/PlanPageLayout.vue';
import HolisticPlanContent from '@/components/Plans/Holistic/HolisticPlanContent.vue';
import { planPrintMixin } from '@/components/Plans/Shared/planPrintMixin';

export default {
  name: 'HolisticPlan',

  components: {
    PlanPageLayout,
    HolisticPlanContent,
  },

  mixins: [planPrintMixin],

  data() {
    return {
      holisticLoading: false,
      error: null,
      loadErrors: {},
    };
  },

  computed: {
    ...mapState('plans', ['plans']),

    protectionPlan() { return this.plans.protection || null; },
    investmentPlan() { return this.plans.investment || null; },
    retirementPlan() { return this.plans.retirement || null; },
    estatePlan() { return this.plans.estate || null; },

    hasAnyPlan() {
      return this.protectionPlan || this.investmentPlan || this.retirementPlan || this.estatePlan;
    },

    allPlans() {
      const p = {};
      if (this.protectionPlan) p.protection = this.protectionPlan;
      if (this.investmentPlan) p.investment = this.investmentPlan;
      if (this.retirementPlan) p.retirement = this.retirementPlan;
      if (this.estatePlan) p.estate = this.estatePlan;
      return p;
    },
  },

  async mounted() {
    await this.loadAllPlans();
  },

  methods: {
    ...mapActions('plans', ['fetchPlan', 'toggleAction', 'updateActionFundingSource']),

    async loadAllPlans() {
      this.holisticLoading = true;
      this.error = null;
      this.loadErrors = {};

      const types = ['protection', 'investment', 'retirement', 'estate'];
      const results = await Promise.allSettled(
        types.map(type => this.fetchPlan(type))
      );

      results.forEach((result, index) => {
        if (result.status === 'rejected') {
          this.loadErrors[types[index]] = result.reason?.message || 'Failed to load';
        }
      });

      // Only show error if ALL plans failed
      const successCount = results.filter(r => r.status === 'fulfilled').length;
      if (successCount === 0) {
        this.error = 'Unable to load any plans. Please ensure you have data entered in at least one module.';
      }

      this.holisticLoading = false;
    },

    handleToggleAction({ planKey, actionId }) {
      this.toggleAction({ planKey, actionId });
    },

    handleUpdateFundingSource({ planKey, ...payload }) {
      this.updateActionFundingSource({ planKey, ...payload });
    },

    handlePrint() {
      this.printHolisticPlan(this.allPlans);
    },
  },
};
</script>
