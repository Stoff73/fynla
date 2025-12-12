<template>
  <component :is="isEmbedded ? 'div' : 'AppLayout'">
    <div class="investment-dashboard py-2 sm:py-6 px-0 sm:px-6">
      <!-- Header (only show when not embedded) -->
      <div v-if="!isEmbedded" class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Investment Portfolio</h1>
        <p class="text-gray-600 mt-2">Monitor your portfolio performance, analyse holdings, and optimise your investment strategy</p>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="flex items-center justify-center py-12">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600"></div>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
        <p class="text-red-800">{{ error }}</p>
      </div>

      <!-- Main Content -->
      <div v-else>
        <!-- Tab Navigation -->
        <div class="mb-6 border-b border-gray-200">
          <nav class="flex overflow-x-auto scrollbar-hide -webkit-overflow-scrolling-touch">
            <button
              v-for="tab in currentTabs"
              :key="tab.id"
              @click="handleTabClick(tab.id)"
              :class="[
                'px-3 sm:px-4 py-3 text-xs sm:text-sm font-medium whitespace-nowrap transition-colors duration-200 bg-transparent flex-shrink-0',
                activeTab === tab.id
                  ? 'text-indigo-600 border-b-2 border-indigo-600'
                  : 'text-gray-600 hover:text-gray-900 border-b-2 border-transparent'
              ]"
            >
              {{ tab.label }}
            </button>
          </nav>
        </div>

        <!-- Tab Content -->
        <transition name="fade" mode="out-in">
          <!-- DETAIL MODE: Account Detail View -->
          <AccountDetailView
            v-if="isDetailMode"
            :account="selectedAccount"
            :active-tab="activeTab"
            @open-holding-modal="openHoldingModal"
          />

          <!-- OVERVIEW MODE: Portfolio Overview Tab -->
          <PortfolioOverview
            v-else-if="activeTab === 'overview'"
            @open-add-account-modal="openAddAccountModal"
            @select-account="selectAccount"
          />

          <!-- OVERVIEW MODE: Holdings Tab (all holdings across all accounts) -->
          <Holdings
            v-else-if="activeTab === 'holdings'"
            :selected-account-id="selectedAccountId"
            @clear-filter="clearAccountFilter"
          />

          <!-- OVERVIEW MODE: Performance Tab (Coming Soon) -->
          <div v-else-if="activeTab === 'performance'" class="relative">
            <!-- Coming Soon Watermark -->
            <div class="absolute inset-0 flex items-center justify-center z-10 pointer-events-none">
              <div class="bg-amber-100 border-2 border-amber-400 rounded-lg px-8 py-4 transform -rotate-12 shadow-lg">
                <p class="text-2xl font-bold text-amber-700">Coming Soon</p>
              </div>
            </div>
            <div class="opacity-50">
              <Performance @navigate-to-tab="navigateToTab" />
              <div class="mt-8">
                <PerformanceAttribution />
              </div>
              <div class="mt-8">
                <BenchmarkComparison />
              </div>
            </div>
          </div>

          <!-- OVERVIEW MODE: Portfolio Optimisation Tab (Coming Soon) -->
          <div v-else-if="activeTab === 'optimization'" class="relative">
            <div class="absolute inset-0 flex items-center justify-center z-10 pointer-events-none">
              <div class="bg-amber-100 border-2 border-amber-400 rounded-lg px-8 py-4 transform -rotate-12 shadow-lg">
                <p class="text-2xl font-bold text-amber-700">Coming Soon</p>
              </div>
            </div>
            <div class="opacity-50">
              <PortfolioOptimization />
            </div>
          </div>

          <!-- OVERVIEW MODE: Rebalancing Tab (Coming Soon) -->
          <div v-else-if="activeTab === 'rebalancing'" class="relative">
            <div class="absolute inset-0 flex items-center justify-center z-10 pointer-events-none">
              <div class="bg-amber-100 border-2 border-amber-400 rounded-lg px-8 py-4 transform -rotate-12 shadow-lg">
                <p class="text-2xl font-bold text-amber-700">Coming Soon</p>
              </div>
            </div>
            <div class="opacity-50">
              <RebalancingCalculator />
            </div>
          </div>

          <!-- OVERVIEW MODE: Goals Tab (Coming Soon) -->
          <div v-else-if="activeTab === 'goals'" class="relative">
            <div class="absolute inset-0 flex items-center justify-center z-10 pointer-events-none">
              <div class="bg-amber-100 border-2 border-amber-400 rounded-lg px-8 py-4 transform -rotate-12 shadow-lg">
                <p class="text-2xl font-bold text-amber-700">Coming Soon</p>
              </div>
            </div>
            <div class="opacity-50">
              <Goals @view-projection="handleViewProjection" />
              <div class="mt-8">
                <GoalProjection />
              </div>
            </div>
          </div>

          <!-- OVERVIEW MODE: Tax Efficiency Tab (Coming Soon) -->
          <div v-else-if="activeTab === 'taxefficiency'" class="relative">
            <div class="absolute inset-0 flex items-center justify-center z-10 pointer-events-none">
              <div class="bg-amber-100 border-2 border-amber-400 rounded-lg px-8 py-4 transform -rotate-12 shadow-lg">
                <p class="text-2xl font-bold text-amber-700">Coming Soon</p>
              </div>
            </div>
            <div class="opacity-50">
              <AssetLocationOptimizer />
              <div class="mt-8">
                <WrapperOptimizer />
              </div>
            </div>
          </div>

          <!-- OVERVIEW MODE: Fees Tab (Coming Soon) -->
          <div v-else-if="activeTab === 'fees'" class="relative">
            <div class="absolute inset-0 flex items-center justify-center z-10 pointer-events-none">
              <div class="bg-amber-100 border-2 border-amber-400 rounded-lg px-8 py-4 transform -rotate-12 shadow-lg">
                <p class="text-2xl font-bold text-amber-700">Coming Soon</p>
              </div>
            </div>
            <div class="opacity-50">
              <FeeBreakdown />
              <div class="mt-8">
                <FeeSavingsCalculator />
              </div>
            </div>
          </div>

          <!-- OVERVIEW MODE: Strategy Tab (Coming Soon) -->
          <div v-else-if="activeTab === 'recommendations'" class="relative">
            <div class="absolute inset-0 flex items-center justify-center z-10 pointer-events-none">
              <div class="bg-amber-100 border-2 border-amber-400 rounded-lg px-8 py-4 transform -rotate-12 shadow-lg">
                <p class="text-2xl font-bold text-amber-700">Coming Soon</p>
              </div>
            </div>
            <div class="opacity-50">
              <Recommendations />
            </div>
          </div>
        </transition>
      </div>
    </div>
  </component>
</template>

<script>
import { mapState, mapActions } from 'vuex';
import AppLayout from '@/layouts/AppLayout.vue';
import PortfolioOverview from '@/components/Investment/PortfolioOverview.vue';
import Holdings from '@/components/Investment/Holdings.vue';
import Performance from '@/components/Investment/Performance.vue';
import Goals from '@/components/Investment/Goals.vue';
import Recommendations from '@/components/Investment/Recommendations.vue';
import PortfolioOptimization from '@/components/Investment/PortfolioOptimization.vue';
import RebalancingCalculator from '@/components/Investment/RebalancingCalculator.vue';
import AssetLocationOptimizer from '@/components/Investment/AssetLocationOptimizer.vue';
import WrapperOptimizer from '@/components/Investment/WrapperOptimizer.vue';
import PerformanceAttribution from '@/components/Investment/PerformanceAttribution.vue';
import BenchmarkComparison from '@/components/Investment/BenchmarkComparison.vue';
import GoalProjection from '@/components/Investment/GoalProjection.vue';
import FeeBreakdown from '@/components/Investment/FeeBreakdown.vue';
import FeeSavingsCalculator from '@/components/Investment/FeeSavingsCalculator.vue';
import AccountDetailView from '@/views/Investment/AccountDetailView.vue';

export default {
  name: 'InvestmentDashboard',

  components: {
    AppLayout,
    PortfolioOverview,
    Holdings,
    Performance,
    Goals,
    Recommendations,
    PortfolioOptimization,
    RebalancingCalculator,
    AssetLocationOptimizer,
    WrapperOptimizer,
    PerformanceAttribution,
    BenchmarkComparison,
    GoalProjection,
    FeeBreakdown,
    FeeSavingsCalculator,
    AccountDetailView,
  },

  data() {
    return {
      activeTab: 'overview',
      selectedAccountId: null,
      selectedAccount: null,
      // Overview Mode Tabs (9 tabs - removed Accounts and Contributions)
      overviewTabs: [
        { id: 'overview', label: 'Portfolio Overview' },
        { id: 'holdings', label: 'Holdings' },
        { id: 'performance', label: 'Performance' },
        { id: 'optimization', label: 'Portfolio Optimisation' },
        { id: 'rebalancing', label: 'Rebalancing' },
        { id: 'goals', label: 'Goals' },
        { id: 'taxefficiency', label: 'Tax Efficiency' },
        { id: 'fees', label: 'Fees' },
        { id: 'recommendations', label: 'Strategy' },
      ],
      // Detail Mode Tabs (5 tabs)
      detailTabs: [
        { id: 'portfolio-overview', label: 'Portfolio Overview' },
        { id: 'account-overview', label: 'Overview' },
        { id: 'account-holdings', label: 'Holdings' },
        { id: 'account-performance', label: 'Performance' },
        { id: 'account-fees', label: 'Fees' },
      ],
    };
  },

  computed: {
    ...mapState('investment', ['loading', 'error']),

    // Check if this component is embedded in another page (like Net Worth)
    isEmbedded() {
      return this.$route.path.startsWith('/net-worth/');
    },

    // Returns true when an account is selected (detail mode)
    isDetailMode() {
      return !!this.selectedAccount;
    },

    // Returns appropriate tabs based on mode
    currentTabs() {
      return this.isDetailMode ? this.detailTabs : this.overviewTabs;
    },
  },

  mounted() {
    this.loadInvestmentData();
  },

  methods: {
    ...mapActions('investment', ['fetchInvestmentData', 'analyseInvestment']),

    async loadInvestmentData() {
      try {
        await this.fetchInvestmentData();
        await this.analyseInvestment();
      } catch (error) {
        console.error('Failed to load investment data:', error);
      }
    },

    // Select an account and enter detail mode
    selectAccount(account) {
      this.selectedAccount = account;
      this.activeTab = 'account-overview';
    },

    // Clear selection and return to overview mode
    clearSelection() {
      this.selectedAccount = null;
      this.activeTab = 'overview';
    },

    // Handle tab click - special handling for portfolio-overview in detail mode
    handleTabClick(tabId) {
      if (tabId === 'portfolio-overview') {
        // Return to overview mode
        this.clearSelection();
      } else {
        this.activeTab = tabId;
      }
    },

    openAddAccountModal() {
      // Open account add modal via store action or emit
      // For now, just show the accounts component would handle this
      // This will be handled by PortfolioOverview's own add button
    },

    openHoldingModal(account) {
      // Will be implemented when Holdings form is connected
      console.log('Open holding modal for account:', account);
    },

    clearAccountFilter() {
      // Clear the selected account filter
      this.selectedAccountId = null;
    },

    navigateToTab(tabId) {
      // Navigate to a specific tab
      this.activeTab = tabId;
    },

    handleViewProjection(goal) {
      // Handle viewing goal projection
      console.log('Viewing projection for goal:', goal);
      this.$nextTick(() => {
        const projectionElement = this.$el.querySelector('.goal-projection');
        if (projectionElement) {
          projectionElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      });
    },
  },
};
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

/* Hide scrollbar for horizontal tab navigation */
.scrollbar-hide {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
.scrollbar-hide::-webkit-scrollbar {
  display: none;
}

/* Smooth scrolling on iOS */
.-webkit-overflow-scrolling-touch {
  -webkit-overflow-scrolling: touch;
}
</style>
