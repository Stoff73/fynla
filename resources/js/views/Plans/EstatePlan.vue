<template>
  <PlanPageLayout
    title="Estate Plan"
    subtitle="Inheritance Tax analysis, mitigation strategies, and estate optimisation"
    :loading="loading"
    :error="error"
    loading-message="Analysing your estate position..."
    @retry="loadPlan"
    @print="handlePrint"
  >
    <template v-if="plan && plan.not_applicable">
      <div class="bg-violet-50 border border-violet-200 rounded-lg p-6">
        <div class="flex">
          <div class="flex-shrink-0">
            <svg class="h-5 w-5 text-violet-600" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
            </svg>
          </div>
          <div class="ml-3">
            <h3 class="text-sm font-medium text-violet-800">Estate Plan Not Applicable</h3>
            <p class="mt-2 text-sm text-violet-700">{{ plan.not_applicable_reason }}</p>
          </div>
        </div>
      </div>
    </template>

    <EstatePlanContent
      v-else-if="plan"
      :plan="plan"
      @toggle-action="handleToggle"
    />
  </PlanPageLayout>
</template>

<script>
import { mapGetters, mapActions } from 'vuex';
import PlanPageLayout from '@/components/Plans/Shared/PlanPageLayout.vue';
import EstatePlanContent from '@/components/Plans/Estate/EstatePlanContent.vue';
import { planPrintMixin } from '@/components/Plans/Shared/planPrintMixin';

export default {
  name: 'EstatePlan',
  components: { PlanPageLayout, EstatePlanContent },
  mixins: [planPrintMixin],

  computed: {
    ...mapGetters('plans', ['getPlan', 'isLoading']),
    plan() { return this.getPlan('estate'); },
    loading() { return this.isLoading; },
    error() { return this.$store.state.plans.error; },
  },

  mounted() { this.loadPlan(); },

  methods: {
    ...mapActions('plans', ['fetchPlan', 'toggleAction']),
    async loadPlan() {
      try { await this.fetchPlan('estate'); } catch { /* handled */ }
    },
    handleToggle(actionId) { this.toggleAction({ planKey: 'estate', actionId }); },
    handlePrint() { this.printPlan(this.plan, 'Estate Plan'); },
  },
};
</script>
