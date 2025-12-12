<template>
  <AppLayout>
    <div class="plans-dashboard py-6">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
          <h1 class="text-3xl font-bold text-gray-900 mb-2">Financial Plans</h1>
          <p class="text-gray-600">
            Comprehensive financial plans for investments, savings, retirement, and estate planning
          </p>
        </div>

        <!-- Plans Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <!-- Investment & Savings Plan Card -->
          <div class="bg-white rounded-lg shadow-md border border-gray-200 p-6 cursor-pointer hover:shadow-lg hover:-translate-y-0.5 hover:border-blue-500 transition-all duration-200" @click="navigateToInvestmentPlan">
            <div class="flex items-center mb-4">
              <div class="p-3 bg-blue-100 rounded-lg">
                <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
              </div>
              <div class="ml-4">
                <h3 class="text-lg font-semibold text-gray-900">Investment & Savings Plan</h3>
              </div>
            </div>
            <p class="text-gray-600 mb-4">
              Comprehensive portfolio analysis, tax optimisation, fee reduction, and goal alignment for your investments and savings
            </p>
            <div class="flex items-center text-blue-600 font-medium">
              <span>View Plan</span>
              <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
            </div>
          </div>

          <!-- Retirement Plan Card (Coming Soon) -->
          <div class="bg-white rounded-lg shadow-md p-6 opacity-60">
            <div class="flex items-center mb-4">
              <div class="p-3 bg-green-100 rounded-lg">
                <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <div class="ml-4">
                <h3 class="text-lg font-semibold text-gray-900">Retirement Plan</h3>
                <span class="text-xs text-gray-500 font-medium">Coming Soon</span>
              </div>
            </div>
            <p class="text-gray-600 mb-4">
              Comprehensive retirement readiness analysis, pension optimisation, and income projection planning
            </p>
          </div>

          <!-- Estate Plan Card (Coming Soon) -->
          <div class="bg-white rounded-lg shadow-md p-6 opacity-60">
            <div class="flex items-center mb-4">
              <div class="p-3 bg-purple-100 rounded-lg">
                <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
              </div>
              <div class="ml-4">
                <h3 class="text-lg font-semibold text-gray-900">Estate Plan</h3>
                <span class="text-xs text-gray-500 font-medium">Coming Soon</span>
              </div>
            </div>
            <p class="text-gray-600 mb-4">
              IHT planning, gifting strategies, life policy optimisation, and asset protection for your estate
            </p>
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
                Each plan provides comprehensive analysis, recommendations, and actionable steps to help you achieve your financial goals. Plans are generated based on your current financial data and can be regenerated at any time to reflect your latest situation.
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';

export default {
  name: 'PlansDashboard',

  components: {
    AppLayout,
  },

  methods: {
    navigateToInvestmentPlan() {
      this.$router.push('/plans/investment-savings');
    },
  },
};
</script>

<style scoped>
/* Additional styles if needed */
</style>
