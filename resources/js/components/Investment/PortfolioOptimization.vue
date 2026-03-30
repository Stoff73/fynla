<template>
  <div class="portfolio-optimization space-y-6">
    <!-- Header -->
    <div class="mb-6">
      <h2 class="text-2xl font-bold text-horizon-500 mb-2">Portfolio Optimisation</h2>
      <p class="text-neutral-500">
        Optimise your portfolio using Modern Portfolio Theory. Analyse efficient frontier, find optimal allocations, and understand asset correlations.
      </p>
    </div>

    <!-- Sub-navigation -->
    <div class="border-b border-light-gray mb-6">
      <nav class="-mb-px flex space-x-8" aria-label="Optimization sections">
        <button
          v-for="section in sections"
          :key="section.id"
          @click="activeSection = section.id"
          :class="[
            activeSection === section.id
              ? 'border-violet-500 text-violet-600'
              : 'border-transparent text-neutral-500 hover:text-neutral-500 hover:border-horizon-300',
            'whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm transition-colors duration-200',
          ]"
        >
          {{ section.label }}
        </button>
      </nav>
    </div>

    <!-- Section Content -->
    <div class="section-content">
      <!-- Efficient Frontier Section -->
      <div v-if="activeSection === 'frontier'">
        <EfficientFrontier
          @view-optimiser="activeSection = 'optimiser'"
          @view-correlation="activeSection = 'correlation'"
        />
      </div>

      <!-- Portfolio Optimiser Section -->
      <div v-else-if="activeSection === 'optimiser'">
        <PortfolioOptimizer
          @view-frontier="activeSection = 'frontier'"
          @apply-allocation="handleApplyAllocation"
        />
      </div>

      <!-- Correlation Matrix Section -->
      <div v-else-if="activeSection === 'correlation'">
        <CorrelationMatrix />
      </div>
    </div>

    <!-- Apply Allocation Confirmation Modal -->
    <div
      v-if="showApplyModal"
      class="fixed inset-0 bg-horizon-500 bg-opacity-75 flex items-center justify-center p-4 z-50"
      @click.self="closeApplyModal"
    >
      <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full p-6">
        <div class="mb-4">
          <h3 class="text-lg font-semibold text-horizon-500">Apply Optimised Allocation</h3>
          <p class="text-sm text-neutral-500 mt-2">
            This will create a rebalancing plan based on the optimised portfolio allocation.
          </p>
        </div>

        <!-- Allocation Summary -->
        <div v-if="pendingAllocation" class="bg-eggshell-500 rounded-lg p-4 mb-4">
          <h4 class="text-sm font-semibold text-violet-900 mb-3">Optimised Portfolio</h4>
          <div class="grid grid-cols-2 gap-3 text-sm">
            <div>
              <span class="text-violet-700">Expected Return:</span>
              <span class="ml-2 font-medium text-violet-900">
                {{ formatPercentage(pendingAllocation.expected_return) }}
              </span>
            </div>
            <div>
              <span class="text-violet-700">Expected Risk:</span>
              <span class="ml-2 font-medium text-violet-900">
                {{ formatPercentage(pendingAllocation.expected_risk) }}
              </span>
            </div>
            <div>
              <span class="text-violet-700">Sharpe Ratio:</span>
              <span class="ml-2 font-medium text-violet-900">
                {{ pendingAllocation.sharpe_ratio?.toFixed(2) || 'N/A' }}
              </span>
            </div>
            <div>
              <span class="text-violet-700">Strategy:</span>
              <span class="ml-2 font-medium text-violet-900">
                {{ getStrategyName(pendingAllocation.optimization_type) }}
              </span>
            </div>
          </div>
        </div>

        <!-- Warning -->
        <div class="bg-eggshell-500 rounded-lg p-4 mb-6">
          <div class="flex">
            <svg class="h-5 w-5 text-violet-600 mr-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <div class="text-sm">
              <p class="font-medium text-violet-900 mb-1">Important Notice</p>
              <ul class="list-disc list-inside text-violet-800 space-y-1">
                <li>This is a theoretical optimisation based on historical data</li>
                <li>Past performance does not guarantee future results</li>
                <li>Consider transaction costs, taxes, and your risk tolerance</li>
                <li>Consult with a financial adviser before making significant changes</li>
              </ul>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex justify-end space-x-3">
          <button
            @click="closeApplyModal"
            class="px-4 py-2 text-sm font-medium text-neutral-500 bg-white border border-horizon-300 rounded-lg hover:bg-eggshell-500"
          >
            Cancel
          </button>
          <button
            @click="confirmApplyAllocation"
            class="px-6 py-2 text-sm font-medium text-white bg-raspberry-500 rounded-button hover:bg-raspberry-600"
          >
            Create Rebalancing Plan
          </button>
        </div>
      </div>
    </div>

    <!-- Info Notification -->
    <div
      v-if="showSuccessNotification"
      class="fixed bottom-4 right-4 bg-violet-500 text-white px-6 py-3 rounded-lg shadow-lg flex items-center space-x-3 z-50 animate-slide-in-right"
    >
      <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <span class="text-sm font-medium">Rebalancing plans coming soon</span>
    </div>
  </div>
</template>

<script>
import EfficientFrontier from '@/components/Investment/EfficientFrontier.vue';
import PortfolioOptimizer from '@/components/Investment/PortfolioOptimizer.vue';
import CorrelationMatrix from '@/components/Investment/CorrelationMatrix.vue';

import logger from '@/utils/logger';
export default {
  name: 'PortfolioOptimization',

  emits: ['allocation-applied'],

  components: {
    EfficientFrontier,
    PortfolioOptimizer,
    CorrelationMatrix,
  },

  data() {
    return {
      activeSection: 'frontier',
      showApplyModal: false,
      showSuccessNotification: false,
      pendingAllocation: null,
      notificationTimeout: null,

      sections: [
        { id: 'frontier', label: 'Efficient Frontier' },
        { id: 'optimiser', label: 'Portfolio Optimiser' },
        { id: 'correlation', label: 'Correlation Analysis' },
      ],
    };
  },

  beforeUnmount() {
    if (this.notificationTimeout) clearTimeout(this.notificationTimeout);
  },

  methods: {
    handleApplyAllocation(allocation) {
      this.pendingAllocation = allocation;
      this.showApplyModal = true;
    },

    closeApplyModal() {
      this.showApplyModal = false;
      this.pendingAllocation = null;
    },

    async confirmApplyAllocation() {
      try {
        // TODO: Implement rebalancing plan creation
        // This would create a new rebalancing goal or action plan
        // For now, just show success notification

        this.showApplyModal = false;
        this.showSuccessNotification = true;

        // Hide notification after 3 seconds
        if (this.notificationTimeout) clearTimeout(this.notificationTimeout);
        this.notificationTimeout = setTimeout(() => {
          this.showSuccessNotification = false;
        }, 3000);

        // Emit event to parent if needed
        this.$emit('allocation-applied', this.pendingAllocation);

        this.pendingAllocation = null;
      } catch (error) {
        logger.error('Failed to apply allocation:', error);
        alert('Failed to create rebalancing plan. Please try again.');
      }
    },

    formatPercentage(value) {
      if (value === null || value === undefined) return 'N/A';
      return `${(value * 100).toFixed(2)}%`;
    },

    getStrategyName(type) {
      const strategies = {
        max_sharpe: 'Maximum Sharpe Ratio',
        min_variance: 'Minimum Variance',
        target_return: 'Target Return',
        risk_parity: 'Risk Parity',
      };
      return strategies[type] || type;
    },
  },
};
</script>

<style scoped>
/* Smooth transitions for section changes */
.section-content {
  min-height: 400px;
}

</style>
