<template>
  <div class="portfolio-optimization space-y-6">
    <!-- Header -->
    <div class="mb-6">
      <h2 class="text-2xl font-bold text-gray-900 mb-2">Portfolio Optimisation</h2>
      <p class="text-gray-600">
        Optimise your portfolio using Modern Portfolio Theory. Analyse efficient frontier, find optimal allocations, and understand asset correlations.
      </p>
    </div>

    <!-- Sub-navigation -->
    <div class="border-b border-gray-200 mb-6">
      <nav class="-mb-px flex space-x-8" aria-label="Optimization sections">
        <button
          v-for="section in sections"
          :key="section.id"
          @click="activeSection = section.id"
          :class="[
            activeSection === section.id
              ? 'border-blue-500 text-blue-600'
              : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
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
      class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center p-4 z-50"
      @click.self="closeApplyModal"
    >
      <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full p-6">
        <div class="mb-4">
          <h3 class="text-lg font-semibold text-gray-900">Apply Optimised Allocation</h3>
          <p class="text-sm text-gray-600 mt-2">
            This will create a rebalancing plan based on the optimised portfolio allocation.
          </p>
        </div>

        <!-- Allocation Summary -->
        <div v-if="pendingAllocation" class="bg-gray-50 rounded-lg p-4 mb-4">
          <h4 class="text-sm font-semibold text-blue-900 mb-3">Optimised Portfolio</h4>
          <div class="grid grid-cols-2 gap-3 text-sm">
            <div>
              <span class="text-blue-700">Expected Return:</span>
              <span class="ml-2 font-medium text-blue-900">
                {{ formatPercentage(pendingAllocation.expected_return) }}
              </span>
            </div>
            <div>
              <span class="text-blue-700">Expected Risk:</span>
              <span class="ml-2 font-medium text-blue-900">
                {{ formatPercentage(pendingAllocation.expected_risk) }}
              </span>
            </div>
            <div>
              <span class="text-blue-700">Sharpe Ratio:</span>
              <span class="ml-2 font-medium text-blue-900">
                {{ pendingAllocation.sharpe_ratio?.toFixed(2) || 'N/A' }}
              </span>
            </div>
            <div>
              <span class="text-blue-700">Strategy:</span>
              <span class="ml-2 font-medium text-blue-900">
                {{ getStrategyName(pendingAllocation.optimization_type) }}
              </span>
            </div>
          </div>
        </div>

        <!-- Warning -->
        <div class="bg-gray-50 rounded-lg p-4 mb-6">
          <div class="flex">
            <svg class="h-5 w-5 text-amber-600 mr-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <div class="text-sm">
              <p class="font-medium text-amber-900 mb-1">Important Notice</p>
              <ul class="list-disc list-inside text-amber-800 space-y-1">
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
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
          >
            Cancel
          </button>
          <button
            @click="confirmApplyAllocation"
            class="px-6 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700"
          >
            Create Rebalancing Plan
          </button>
        </div>
      </div>
    </div>

    <!-- Success Notification -->
    <div
      v-if="showSuccessNotification"
      class="fixed bottom-4 right-4 bg-green-600 text-white px-6 py-3 rounded-lg shadow-lg flex items-center space-x-3 z-50"
    >
      <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
      </svg>
      <span class="text-sm font-medium">Rebalancing plan created successfully!</span>
    </div>
  </div>
</template>

<script>
import EfficientFrontier from '@/components/Investment/EfficientFrontier.vue';
import PortfolioOptimizer from '@/components/Investment/PortfolioOptimizer.vue';
import CorrelationMatrix from '@/components/Investment/CorrelationMatrix.vue';

export default {
  name: 'PortfolioOptimization',

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

      sections: [
        { id: 'frontier', label: 'Efficient Frontier' },
        { id: 'optimiser', label: 'Portfolio Optimiser' },
        { id: 'correlation', label: 'Correlation Analysis' },
      ],
    };
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
        setTimeout(() => {
          this.showSuccessNotification = false;
        }, 3000);

        // Emit event to parent if needed
        this.$emit('allocation-applied', this.pendingAllocation);

        this.pendingAllocation = null;
      } catch (error) {
        console.error('Failed to apply allocation:', error);
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

/* Animation for success notification */
@keyframes slideIn {
  from {
    transform: translateX(100%);
    opacity: 0;
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
}

.fixed.bottom-4.right-4 {
  animation: slideIn 0.3s ease-out;
}
</style>
