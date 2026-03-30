<template>
  <div class="portfolio-strategy-panel">
    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-raspberry-500"></div>
      <span class="ml-3 text-neutral-500">Analysing portfolio strategy...</span>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="text-center py-12">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12 mx-auto mb-4 text-raspberry-400">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
      </svg>
      <p class="text-lg font-medium text-horizon-500">Unable to load strategy</p>
      <p class="text-sm text-neutral-500 mb-4">{{ error }}</p>
      <button @click="fetchStrategy" class="px-4 py-2 bg-violet-600 text-white rounded-lg hover:bg-violet-700">
        Try Again
      </button>
    </div>

    <!-- No Data State -->
    <div v-else-if="!strategyData || !strategyData.success" class="text-center py-12 text-neutral-500">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12 mx-auto mb-4 text-horizon-400">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5m.75-9l3-3 2.148 2.148A12.061 12.061 0 0116.5 7.605" />
      </svg>
      <p class="text-lg font-medium">No Strategy Data</p>
      <p class="text-sm">Add investment accounts to see strategies.</p>
    </div>

    <!-- Main Content -->
    <div v-else class="space-y-6">
      <!-- Header with Refresh Button -->
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-xl font-semibold text-horizon-500">Investment Strategy</h2>
          <p class="text-sm text-neutral-500">Prioritised strategies to optimise your portfolio</p>
        </div>
        <button
          @click="fetchStrategy"
          :disabled="loading"
          class="px-4 py-2 text-violet-600 hover:bg-violet-50 rounded-lg flex items-center text-sm"
        >
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
          </svg>
          Refresh
        </button>
      </div>

      <!-- Summary Stats -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <!-- Total Potential Savings -->
        <div class="bg-spring-50 rounded-lg p-4 border border-spring-200">
          <p class="text-sm text-neutral-500 mb-1">Potential Annual Savings</p>
          <p class="text-2xl font-bold text-spring-600">{{ formatCurrency(summary.total_potential_savings) }}</p>
          <p class="text-xs text-neutral-500 mt-1">from {{ summary.strategy_count || summary.recommendation_count }} strategies</p>
        </div>

        <!-- High Priority Count -->
        <div class="bg-raspberry-50 rounded-lg p-4 border border-raspberry-200">
          <p class="text-sm text-neutral-500 mb-1">High Priority</p>
          <p class="text-2xl font-bold text-raspberry-600">{{ summary.high_priority_count }}</p>
          <p class="text-xs text-neutral-500 mt-1">require attention</p>
        </div>

        <!-- Tax Efficiency -->
        <div class="bg-violet-50 rounded-lg p-4 border border-violet-200">
          <p class="text-sm text-neutral-500 mb-1">Tax Efficiency</p>
          <p class="text-lg font-bold" :class="getTaxEfficiencyClass(summary.tax_efficiency_score)">
            {{ getTaxEfficiencyLabel(summary.tax_efficiency_score) }}
          </p>
        </div>

        <!-- Days Remaining -->
        <div class="bg-violet-50 rounded-lg p-4 border border-violet-200">
          <p class="text-sm text-neutral-500 mb-1">Tax Year Ends</p>
          <p class="text-2xl font-bold text-violet-600">{{ daysRemaining }}</p>
          <p class="text-xs text-neutral-500 mt-1">days remaining</p>
        </div>
      </div>

      <!-- View Toggle -->
      <div class="flex items-center space-x-2 bg-savannah-100 rounded-lg p-1 w-fit">
        <button
          @click="activeView = 'portfolio'"
          :class="[
            'px-4 py-2 rounded-md text-sm font-medium transition-colors',
            activeView === 'portfolio' ? 'bg-white text-horizon-500 shadow' : 'text-neutral-500 hover:text-horizon-500'
          ]"
        >
          Portfolio View
        </button>
        <button
          @click="activeView = 'account'"
          :class="[
            'px-4 py-2 rounded-md text-sm font-medium transition-colors',
            activeView === 'account' ? 'bg-white text-horizon-500 shadow' : 'text-neutral-500 hover:text-horizon-500'
          ]"
        >
          Per Account
        </button>
      </div>

      <!-- Portfolio View -->
      <div v-if="activeView === 'portfolio'" class="space-y-6">
        <!-- Tax Actions (Priority 1-2) -->
        <div v-if="taxRecommendations.length > 0" class="bg-white rounded-lg border border-light-gray overflow-hidden">
          <div class="bg-violet-50 px-4 py-3 border-b border-violet-200">
            <div class="flex items-center justify-between">
              <div class="flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-violet-600 mr-2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
                </svg>
                <h3 class="font-semibold text-violet-800">Tax Actions</h3>
              </div>
              <span class="bg-violet-200 text-violet-800 text-xs font-medium px-2 py-1 rounded-full">
                {{ taxRecommendations.length }}
              </span>
            </div>
          </div>
          <div class="divide-y divide-savannah-100">
            <StrategyRecommendationCard
              v-for="rec in taxRecommendations"
              :key="rec.id"
              :recommendation="rec"
              @action="handleAction"
            />
          </div>
        </div>

        <!-- Wrapper Optimisation (Priority 3) -->
        <div v-if="wrapperRecommendations.length > 0" class="bg-white rounded-lg border border-light-gray overflow-hidden">
          <div class="bg-violet-50 px-4 py-3 border-b border-violet-200">
            <div class="flex items-center justify-between">
              <div class="flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-violet-600 mr-2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
                </svg>
                <h3 class="font-semibold text-violet-800">Wrapper Optimisation</h3>
              </div>
              <span class="bg-violet-200 text-violet-800 text-xs font-medium px-2 py-1 rounded-full">
                {{ wrapperRecommendations.length }}
              </span>
            </div>
          </div>
          <div class="divide-y divide-savannah-100">
            <StrategyRecommendationCard
              v-for="rec in wrapperRecommendations"
              :key="rec.id"
              :recommendation="rec"
              @action="handleAction"
            />
          </div>
        </div>

        <!-- Fee Reduction (Priority 4) -->
        <div v-if="feeRecommendations.length > 0" class="bg-white rounded-lg border border-light-gray overflow-hidden">
          <div class="bg-violet-50 px-4 py-3 border-b border-violet-200">
            <div class="flex items-center justify-between">
              <div class="flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-violet-600 mr-2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <h3 class="font-semibold text-violet-800">Fee Reduction</h3>
              </div>
              <span class="bg-violet-200 text-violet-800 text-xs font-medium px-2 py-1 rounded-full">
                {{ feeRecommendations.length }}
              </span>
            </div>
          </div>
          <div class="divide-y divide-savannah-100">
            <StrategyRecommendationCard
              v-for="rec in feeRecommendations"
              :key="rec.id"
              :recommendation="rec"
              @action="handleAction"
            />
          </div>
        </div>

        <!-- Rebalancing (Priority 5) -->
        <div v-if="rebalancingRecommendations.length > 0" class="bg-white rounded-lg border border-light-gray overflow-hidden">
          <div class="bg-spring-50 px-4 py-3 border-b border-spring-200">
            <div class="flex items-center justify-between">
              <div class="flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-spring-600 mr-2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                </svg>
                <h3 class="font-semibold text-spring-800">Portfolio Rebalancing</h3>
              </div>
              <span class="bg-spring-200 text-spring-800 text-xs font-medium px-2 py-1 rounded-full">
                {{ rebalancingRecommendations.length }}
              </span>
            </div>
          </div>
          <div class="divide-y divide-savannah-100">
            <StrategyRecommendationCard
              v-for="rec in rebalancingRecommendations"
              :key="rec.id"
              :recommendation="rec"
              @action="handleAction"
            />
          </div>
        </div>

        <!-- Empty State -->
        <div v-if="recommendations.length === 0" class="text-center py-12 bg-eggshell-500 rounded-lg">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12 mx-auto mb-4 text-spring-500">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <p class="text-lg font-medium text-horizon-500">Your portfolio is well-optimised</p>
          <p class="text-sm text-neutral-500">No immediate actions required.</p>
        </div>
      </div>

      <!-- Per Account View -->
      <div v-else class="space-y-6">
        <div v-for="account in byAccount" :key="account.account_id || 'portfolio'" class="bg-white rounded-lg border border-light-gray overflow-hidden">
          <div class="bg-eggshell-500 px-4 py-3 border-b border-light-gray">
            <div class="flex items-center justify-between">
              <div>
                <h3 class="font-semibold text-horizon-500">{{ account.account_name }}</h3>
                <p v-if="account.provider" class="text-sm text-neutral-500">{{ account.provider }} - {{ formatAccountType(account.account_type) }}</p>
              </div>
              <span class="bg-savannah-200 text-horizon-500 text-xs font-medium px-2 py-1 rounded-full">
                {{ account.recommendations.length }} strategies
              </span>
            </div>
          </div>
          <div v-if="account.recommendations.length > 0" class="divide-y divide-savannah-100">
            <StrategyRecommendationCard
              v-for="rec in account.recommendations"
              :key="rec.id"
              :recommendation="rec"
              @action="handleAction"
            />
          </div>
          <div v-else class="p-6 text-center text-neutral-500">
            <p class="text-sm">No strategies for this account</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Modals -->
    <ISATransferModal
      v-if="showISAModal"
      :data="activeModalData"
      @close="showISAModal = false"
    />

    <BedAndISAWizardModal
      v-if="showBedAndISAModal"
      :data="activeModalData"
      @close="showBedAndISAModal = false"
    />

    <HarvestLossModal
      v-if="showHarvestLossModal"
      :data="activeModalData"
      @close="showHarvestLossModal = false"
    />

    <BondWrapperInfoModal
      v-if="showBondModal"
      :data="activeModalData"
      @close="showBondModal = false"
    />
  </div>
</template>

<script>
import investmentService from '@/services/investmentService';
import StrategyRecommendationCard from '@/components/Investment/StrategyRecommendationCard.vue';
import ISATransferModal from '@/components/Investment/ISATransferModal.vue';
import BedAndISAWizardModal from '@/components/Investment/BedAndISAWizardModal.vue';
import HarvestLossModal from '@/components/Investment/HarvestLossModal.vue';
import BondWrapperInfoModal from '@/components/Investment/BondWrapperInfoModal.vue';
import { currencyMixin } from '@/mixins/currencyMixin';

import logger from '@/utils/logger';
export default {
  name: 'PortfolioStrategyPanel',

  mixins: [currencyMixin],

  components: {
    StrategyRecommendationCard,
    ISATransferModal,
    BedAndISAWizardModal,
    HarvestLossModal,
    BondWrapperInfoModal,
  },

  data() {
    return {
      loading: false,
      error: null,
      strategyData: null,
      activeView: 'portfolio',
      showISAModal: false,
      showBedAndISAModal: false,
      showHarvestLossModal: false,
      showBondModal: false,
      activeModalData: null,
    };
  },

  computed: {
    summary() {
      return this.strategyData?.summary || {};
    },

    recommendations() {
      return this.strategyData?.recommendations || [];
    },

    byAccount() {
      return this.strategyData?.by_account || [];
    },

    taxRecommendations() {
      return this.recommendations.filter(r => r.category === 'tax');
    },

    wrapperRecommendations() {
      return this.recommendations.filter(r => r.category === 'wrapper');
    },

    feeRecommendations() {
      return this.recommendations.filter(r => r.category === 'fees');
    },

    rebalancingRecommendations() {
      return this.recommendations.filter(r => r.category === 'rebalancing');
    },

    daysRemaining() {
      // Find from first tax recommendation or calculate
      const taxRec = this.recommendations.find(r => r.days_remaining);
      if (taxRec) return taxRec.days_remaining;

      // Calculate manually
      const now = new Date();
      const year = now.getMonth() < 3 || (now.getMonth() === 3 && now.getDate() <= 5)
        ? now.getFullYear()
        : now.getFullYear() + 1;
      const taxYearEnd = new Date(year, 3, 5); // April 5
      return Math.ceil((taxYearEnd - now) / (1000 * 60 * 60 * 24));
    },
  },

  mounted() {
    this.fetchStrategy();
  },

  methods: {
    async fetchStrategy() {
      this.loading = true;
      this.error = null;

      try {
        this.strategyData = await investmentService.getPortfolioStrategy();
      } catch (err) {
        logger.error('Failed to fetch portfolio strategy:', err);
        this.error = err.response?.data?.message || 'Failed to load strategies';
      } finally {
        this.loading = false;
      }
    },

    handleAction(recommendation) {
      this.activeModalData = recommendation.action_data || {};

      switch (recommendation.action_type) {
        case 'isa_transfer':
          this.showISAModal = true;
          break;
        case 'bed_and_isa':
          this.showBedAndISAModal = true;
          break;
        case 'harvest_loss':
          this.showHarvestLossModal = true;
          break;
        case 'info':
          if (recommendation.category === 'wrapper') {
            this.showBondModal = true;
          }
          break;
        case 'navigate':
          // Emit event to parent to switch tabs
          this.$emit('navigate', recommendation.action_data?.navigate_to);
          break;
      }
    },

    getTaxEfficiencyLabel(score) {
      if (!score && score !== 0) return 'N/A';
      if (score >= 80) return 'Well optimised';
      if (score >= 50) return 'Room for improvement';
      return 'Review recommended';
    },

    getTaxEfficiencyClass(score) {
      if (!score && score !== 0) return 'text-neutral-500';
      if (score >= 80) return 'text-spring-600';
      if (score >= 50) return 'text-violet-600';
      return 'text-raspberry-600';
    },

    formatAccountType(type) {
      const types = {
        isa: 'ISA',
        stocks_shares_isa: 'Stocks & Shares ISA',
        gia: 'General Investment Account',
        general: 'General Investment Account',
        sipp: 'Self-Invested Personal Pension',
        pension: 'Pension',
        onshore_bond: 'Onshore Bond',
        offshore_bond: 'Offshore Bond',
      };
      return types[type] || type;
    },
  },
};
</script>

<style scoped>
.portfolio-strategy-panel {
  @apply min-h-[400px];
}
</style>
