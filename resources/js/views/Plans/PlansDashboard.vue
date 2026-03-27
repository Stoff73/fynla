<template>
  <AppLayout>
    <div class="module-gradient py-6">
      <ModuleStatusBar />
      <div class="">
        <!-- Holistic Plan - Premium CTA -->
        <div
          class="holistic-cta relative overflow-hidden rounded-2xl p-8 mb-8 cursor-pointer transition-all duration-200 hover:-translate-y-0.5 hover:shadow-xl"
          @click="$router.push('/holistic-plan')"
        >
          <div class="absolute -right-5 -top-5 w-44 h-44 rounded-full bg-white/5"></div>
          <div class="absolute right-10 -bottom-8 w-28 h-28 rounded-full bg-white/[0.03]"></div>
          <div class="relative z-10">
            <h2 class="text-xl sm:text-2xl font-black text-white mb-2">Your Holistic Financial Plan</h2>
            <p class="text-sm text-white/80 mb-5 max-w-2xl">
              Your complete financial strategy across all modules — net worth projection, cross-module recommendations, cashflow allocation, and risk assessment
            </p>
            <span class="inline-flex items-center gap-2 bg-raspberry-500 hover:bg-raspberry-600 text-white px-6 py-2.5 rounded-button text-sm font-semibold transition-colors">
              View Your Plan
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
            </span>
          </div>
        </div>

        <!-- Module Plans -->
        <h2 class="text-lg font-semibold text-horizon-500 mb-4">Module Plans</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <PlanDashboardCard
            title="Investment & Savings Plan"
            description="Portfolio analysis, fee reduction, tax optimisation, and goal alignment for your investments and savings"
            color="blue"
            :completeness="statusFor('investment')"
            icon-path="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
            @click="$router.push('/plans/investment')"
          />

          <PlanDashboardCard
            title="Protection Plan"
            description="Life insurance, critical illness, and income protection gap analysis with coverage recommendations"
            color="green"
            :completeness="statusFor('protection')"
            icon-path="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
            @click="$router.push('/plans/protection')"
          />

          <PlanDashboardCard
            title="Retirement Plan"
            description="Pension analysis, income projections, contribution optimisation, and retirement readiness assessment"
            color="teal"
            :completeness="statusFor('retirement')"
            icon-path="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
            @click="$router.push('/plans/retirement')"
          />

          <PlanDashboardCard
            title="Estate Plan"
            description="Inheritance Tax analysis, gifting strategies, charitable relief, and life cover recommendations"
            color="purple"
            :completeness="statusFor('estate')"
            icon-path="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"
            @click="$router.push('/plans/estate')"
          />
        </div>

        <!-- Goal Plans -->
        <div v-if="goals && goals.length" class="mt-10">
          <h2 class="text-xl font-semibold text-horizon-500 mb-4">Goal Plans</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <PlanDashboardCard
              v-for="goal in goals"
              :key="goal.id"
              :title="goal.goal_name || 'Unnamed Goal'"
              :description="goalDescription(goal)"
              color="purple"
              :completeness="goalCompleteness(goal)"
              icon-path="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"
              @click="$router.push(`/plans/goal/${goal.id}`)"
            />
          </div>
        </div>

        <!-- Info Card -->
        <div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-6">
          <div class="flex">
            <div class="flex-shrink-0">
              <svg class="h-5 w-5 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
              </svg>
            </div>
            <div class="ml-3">
              <h3 class="text-sm font-medium text-blue-800">About Financial Plans</h3>
              <p class="mt-2 text-sm text-blue-700">
                Each plan provides comprehensive analysis, recommendations, and actionable steps based on your current financial data. Toggle actions on or off to see how different decisions affect your outcomes, then save a copy as a PDF.
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import { mapGetters, mapActions } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';
import AppLayout from '@/layouts/AppLayout.vue';
import PlanDashboardCard from '@/components/Plans/Shared/PlanDashboardCard.vue';
import ModuleStatusBar from '@/components/Shared/ModuleStatusBar.vue';

export default {
  name: 'PlansDashboard',
  components: { AppLayout, PlanDashboardCard, ModuleStatusBar },
  mixins: [currencyMixin],

  computed: {
    ...mapGetters('plans', ['planStatuses']),
    ...mapGetters('goals', { goals: 'activeGoals' }),
  },

  mounted() {
    this.fetchDashboardStatuses();
    this.fetchGoals();
  },

  methods: {
    ...mapActions('plans', ['fetchDashboardStatuses']),
    ...mapActions('goals', ['fetchGoals']),

    statusFor(type) {
      if (!this.planStatuses) return null;
      return this.planStatuses[type]?.completeness ?? null;
    },

    goalDescription(goal) {
      const parts = [];
      if (goal.goal_type) {
        parts.push(goal.goal_type.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()));
      }
      if (goal.target_amount) {
        parts.push(`Target: ${this.formatCurrency(goal.target_amount)}`);
      }
      return parts.length ? parts.join(' · ') : 'Track progress and optimise your strategy for this goal';
    },

    goalCompleteness(goal) {
      let complete = 0;
      const total = 3;
      if (goal.target_amount && goal.target_amount > 0) complete++;
      if (goal.target_date) complete++;
      if (goal.linked_savings_account_id || goal.linked_investment_account_id) complete++;
      return Math.round((complete / total) * 100);
    },
  },
};
</script>

<style scoped>
.holistic-cta {
  background: linear-gradient(135deg, #1F2A44 0%, #2D3A5C 100%);
}
</style>
