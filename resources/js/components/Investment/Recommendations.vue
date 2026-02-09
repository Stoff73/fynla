<template>
  <div class="recommendations bg-white rounded-lg shadow-sm p-6">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h2 class="text-2xl font-bold text-gray-900">Investment Strategies</h2>
        <p class="mt-1 text-sm text-gray-600">Personalised strategies to optimise your portfolio</p>
      </div>
      <button
        v-if="!showTracker"
        @click="loadRecommendations"
        class="px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-button transition-colors duration-200"
      >
        Load Strategies
      </button>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <svg class="animate-spin h-12 w-12 mx-auto text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
      <p class="mt-4 text-gray-600">Loading strategies...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-gray-50 rounded-lg p-6 text-center">
      <svg class="mx-auto h-12 w-12 text-red-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <h3 class="text-lg font-medium text-red-800 mb-2">Failed to load strategies</h3>
      <p class="text-sm text-red-600 mb-4">{{ error }}</p>
      <button
        @click="loadRecommendations"
        class="px-4 py-2 text-sm font-medium text-white bg-error-600 hover:bg-error-700 rounded-button transition-colors duration-200"
      >
        Try Again
      </button>
    </div>

    <!-- Tracker Component -->
    <div v-else-if="showTracker">
      <InvestmentRecommendationsTracker />
    </div>

    <!-- Initial Empty State -->
    <div v-else class="text-center py-12">
      <svg class="mx-auto h-16 w-16 text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
      </svg>
      <h3 class="text-lg font-medium text-gray-900 mb-2">Investment Strategies</h3>
      <p class="text-gray-600 mb-6">
        Get personalised strategies based on your portfolio analysis
      </p>
      <button
        @click="loadRecommendations"
        class="px-6 py-3 bg-primary-600 text-white rounded-button hover:bg-primary-700 transition-colors font-medium"
      >
        Generate Strategies
      </button>

      <!-- Information Cards -->
      <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-4 max-w-3xl mx-auto">
        <div class="bg-gray-50 rounded-lg p-4 text-left">
          <h4 class="font-semibold text-blue-900 mb-2">Portfolio Optimisation</h4>
          <p class="text-sm text-blue-700">Rebalancing suggestions to align with your target allocation</p>
        </div>
        <div class="bg-gray-50 rounded-lg p-4 text-left">
          <h4 class="font-semibold text-green-900 mb-2">Tax Efficiency</h4>
          <p class="text-sm text-green-700">Strategies to minimize tax impact on your investments</p>
        </div>
        <div class="bg-gray-50 rounded-lg p-4 text-left">
          <h4 class="font-semibold text-purple-900 mb-2">Fee Reduction</h4>
          <p class="text-sm text-purple-700">Identify opportunities to lower investment costs</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { mapGetters } from 'vuex';
import InvestmentRecommendationsTracker from './InvestmentRecommendationsTracker.vue';

export default {
  name: 'Recommendations',

  components: {
    InvestmentRecommendationsTracker,
  },

  data() {
    return {
      loading: false,
      error: null,
      showTracker: false,
      delayTimeout: null,
    };
  },

  computed: {
    ...mapGetters('investment', ['recommendations', 'accounts']),

    // Check if recommendations already exist in the store
    hasExistingRecommendations() {
      return this.recommendations?.recommendations?.length > 0;
    },
  },

  async mounted() {
    // Load investment data if not already loaded
    const hasAccounts = this.accounts?.length > 0;
    if (!hasAccounts) {
      try {
        await this.$store.dispatch('investment/fetchInvestmentData');
      } catch (err) {
        console.error('Failed to load investment data:', err);
      }
    }

    // Auto-show tracker if recommendations already exist in store
    if (this.hasExistingRecommendations) {
      this.showTracker = true;
    }
  },

  beforeUnmount() {
    if (this.delayTimeout) clearTimeout(this.delayTimeout);
  },

  methods: {
    async loadRecommendations() {
      this.loading = true;
      this.error = null;

      // Simulate a delay to show that it's working
      await new Promise(resolve => {
        if (this.delayTimeout) clearTimeout(this.delayTimeout);
        this.delayTimeout = setTimeout(resolve, 500);
      });

      try {
        // Check if we have the necessary data
        const hasAccounts = this.accounts?.length > 0;

        if (!hasAccounts) {
          this.error = 'Please add investment accounts first to generate strategies';
          this.loading = false;
          return;
        }

        // Show the tracker component
        this.showTracker = true;
        this.loading = false;
      } catch (err) {
        this.error = err.message || 'An error occurred while loading recommendations';
        this.loading = false;
      }
    },
  },
};
</script>
