<template>
  <AppLayout>
    <div class="py-6">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
          <h1 class="text-3xl font-bold text-gray-900 mb-2">Financial Plans</h1>
          <p class="text-gray-600">
            Personalised plans built from your financial data, with actionable recommendations
          </p>
        </div>

        <!-- Module Plans -->
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
          <h2 class="text-xl font-semibold text-gray-900 mb-4">Goal Plans</h2>
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
import AppLayout from '@/layouts/AppLayout.vue';
import PlanDashboardCard from '@/components/Plans/Shared/PlanDashboardCard.vue';

export default {
  name: 'PlansDashboard',
  components: { AppLayout, PlanDashboardCard },

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
        parts.push(`Target: ${this.formatSimpleCurrency(goal.target_amount)}`);
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

    formatSimpleCurrency(value) {
      if (!value && value !== 0) return '£0';
      return new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP', maximumFractionDigits: 0 }).format(value);
    },
  },
};
</script>
