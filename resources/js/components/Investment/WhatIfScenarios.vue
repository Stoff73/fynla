<template>
  <div class="what-if-scenarios bg-white rounded-lg shadow-sm p-6">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h2 class="text-2xl font-bold text-gray-900">What-If Scenarios</h2>
        <p class="mt-1 text-sm text-gray-600">Model portfolio changes and compare different outcomes</p>
      </div>
      <button
        v-if="!showBuilder"
        @click="loadScenarios"
        class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors duration-200"
      >
        Load Scenarios
      </button>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <svg class="animate-spin h-12 w-12 mx-auto text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
      <p class="mt-4 text-gray-600">Loading scenarios...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-gray-50 rounded-lg p-6 text-center">
      <svg class="mx-auto h-12 w-12 text-red-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <h3 class="text-lg font-medium text-red-800 mb-2">Failed to load scenarios</h3>
      <p class="text-sm text-red-600 mb-4">{{ error }}</p>
      <button
        @click="loadScenarios"
        class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors duration-200"
      >
        Try Again
      </button>
    </div>

    <!-- Builder Component -->
    <div v-else-if="showBuilder">
      <WhatIfScenariosBuilder />
    </div>

    <!-- Initial Empty State -->
    <div v-else class="text-center py-12">
      <svg class="mx-auto h-16 w-16 text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
      </svg>
      <h3 class="text-lg font-medium text-gray-900 mb-2">What-If Scenario Modeling</h3>
      <p class="text-gray-600 mb-6">
        Explore different investment strategies and see how they might perform
      </p>
      <button
        @click="loadScenarios"
        class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium"
      >
        Start Scenario Planning
      </button>

      <!-- Information Cards -->
      <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-4 max-w-3xl mx-auto">
        <div class="bg-gray-50 rounded-lg p-4 text-left">
          <h4 class="font-semibold text-blue-900 mb-2">Contribution Changes</h4>
          <p class="text-sm text-blue-700">Model the impact of increasing or decreasing monthly contributions</p>
        </div>
        <div class="bg-gray-50 rounded-lg p-4 text-left">
          <h4 class="font-semibold text-green-900 mb-2">Allocation Shifts</h4>
          <p class="text-sm text-green-700">See how different asset allocations affect long-term growth</p>
        </div>
        <div class="bg-gray-50 rounded-lg p-4 text-left">
          <h4 class="font-semibold text-purple-900 mb-2">Time Horizons</h4>
          <p class="text-sm text-purple-700">Compare outcomes over different investment periods</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import WhatIfScenariosBuilder from './WhatIfScenariosBuilder.vue';

export default {
  name: 'WhatIfScenarios',

  components: {
    WhatIfScenariosBuilder,
  },

  data() {
    return {
      loading: false,
      error: null,
      showBuilder: false,
    };
  },

  methods: {
    async loadScenarios() {
      this.loading = true;
      this.error = null;

      // Simulate a delay to show that it's working
      await new Promise(resolve => setTimeout(resolve, 500));

      try {
        // Check if we have the necessary data
        const hasAccounts = this.$store.state.investment.accounts?.length > 0;

        if (!hasAccounts) {
          this.error = 'Please add investment accounts first to create scenarios';
          this.loading = false;
          return;
        }

        // Show the builder component
        this.showBuilder = true;
        this.loading = false;
      } catch (err) {
        this.error = err.message || 'An error occurred while loading scenarios';
        this.loading = false;
      }
    },
  },
};
</script>
