<template>
  <component :is="isEmbedded ? 'div' : 'AppLayout'">
    <div class="savings-dashboard module-gradient py-2 sm:py-6">
      <ModuleStatusBar />
      <div class="">
      <!-- Account Detail View (when embedded and account selected) -->
      <SavingsAccountDetailInline
        v-if="isEmbedded && selectedAccount"
        :account-id="selectedAccount.id"
        @back="clearSelection"
        @deleted="handleAccountDeleted"
      />

      <!-- Normal Dashboard View -->
      <template v-else>
        <!-- Header (only show when not embedded) -->
        <div v-if="!isEmbedded" class="mb-8">
          <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold text-horizon-500 mb-2">Savings & Emergency Fund</h1>
          <p class="text-neutral-500">
            Manage your savings accounts, track emergency fund, and monitor progress towards your goals
          </p>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="flex justify-center items-center py-12">
          <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-violet-600"></div>
        </div>

        <!-- Error State -->
        <div
          v-else-if="error"
          class="bg-raspberry-50 border-l-4 border-raspberry-500 p-4 mb-6"
        >
          <div class="flex">
            <div class="flex-shrink-0">
              <svg
                class="h-5 w-5 text-raspberry-400"
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 20 20"
                fill="currentColor"
              >
                <path
                  fill-rule="evenodd"
                  d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                  clip-rule="evenodd"
                />
              </svg>
            </div>
            <div class="ml-3">
              <p class="text-sm text-raspberry-700">{{ error }}</p>
            </div>
          </div>
        </div>

        <!-- Main Content -->
        <div v-else>
          <!-- Life Events & Goal Strategies (above main card, only when not embedded) -->
          <div v-if="!isEmbedded" class="space-y-4 mb-6">
            <ModuleLifeEvents
              module="savings"
              :events="lifeEvents"
              :impact-summary="lifeEventImpact"
            />
            <ModuleGoalStrategies
              module="savings"
              :strategies="goalStrategies"
              :summary="goalsSummary"
            />
          </div>

        <div :class="isEmbedded ? 'savings-embedded' : 'bg-white rounded-lg shadow'">
          <!-- Tab Navigation -->
          <div v-if="!isEmbedded" class="border-b border-light-gray">
            <nav class="-mb-px flex overflow-x-auto scrollbar-hide" aria-label="Tabs">
              <button
                v-for="tab in tabs"
                :key="tab.id"
                @click="activeTab = tab.id"
                :class="[
                  activeTab === tab.id
                    ? 'border-violet-500 text-violet-600'
                    : 'border-transparent text-neutral-500 hover:text-neutral-500 hover:border-horizon-300',
                  'whitespace-nowrap py-3 sm:py-4 px-3 sm:px-6 border-b-2 font-medium text-xs sm:text-sm transition-colors duration-200 flex-shrink-0',
                ]"
              >
                {{ tab.label }}
              </button>
            </nav>
          </div>

          <!-- Tab Content -->
          <div :class="isEmbedded ? '' : 'p-6'">
            <!-- Current Situation Tab -->
            <CurrentSituation
              v-if="activeTab === 'current'"
              @select-account="selectAccount"
            />

            <!-- Emergency Fund Tab -->
            <EmergencyFund v-else-if="activeTab === 'emergency'" />

            <!-- Savings Goals Tab (unified) -->
            <div v-else-if="activeTab === 'goals'" class="text-center py-12">
              <svg class="mx-auto h-12 w-12 text-raspberry-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
              </svg>
              <h3 class="text-lg font-semibold text-horizon-500 mb-2">Goals Have Moved</h3>
              <p class="text-sm text-neutral-500 mb-4 max-w-md mx-auto">
                Savings goals are now managed in the unified Goals &amp; Life Events module, where you can track all your financial goals in one place.
              </p>
              <router-link
                to="/goals?module=savings"
                class="inline-flex items-center px-4 py-2 bg-raspberry-500 text-white font-medium rounded-lg hover:bg-raspberry-600 transition-colors"
              >
                Go to Goals &amp; Life Events
                <svg class="ml-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                </svg>
              </router-link>
            </div>

            <!-- Recommendations Tab -->
            <Recommendations v-else-if="activeTab === 'recommendations'" />
          </div>
        </div>
        </div>
      </template>
      </div>
    </div>
  </component>
</template>

<script>
import { mapState, mapActions } from 'vuex';
import AppLayout from '@/layouts/AppLayout.vue';
import CurrentSituation from '@/components/Savings/CurrentSituation.vue';
import EmergencyFund from '@/components/Savings/EmergencyFund.vue';
import Recommendations from '@/components/Savings/Recommendations.vue';
import AccountDetails from '@/components/Savings/AccountDetails.vue';
import SavingsAccountDetailInline from '@/views/Savings/SavingsAccountDetailInline.vue';
import ModuleLifeEvents from '@/components/Shared/ModuleLifeEvents.vue';
import ModuleGoalStrategies from '@/components/Shared/ModuleGoalStrategies.vue';
import ModuleStatusBar from '@/components/Shared/ModuleStatusBar.vue';

import logger from '@/utils/logger';
export default {
  name: 'SavingsDashboard',

  components: {
    AppLayout,
    CurrentSituation,
    EmergencyFund,
    Recommendations,
    AccountDetails,
    SavingsAccountDetailInline,
    ModuleLifeEvents,
    ModuleGoalStrategies,
    ModuleStatusBar,
  },

  data() {
    return {
      activeTab: 'current',
      selectedAccount: null,
      tabs: [
        { id: 'current', label: 'Cash Overview' },
        { id: 'emergency', label: 'Emergency Fund' },
        { id: 'goals', label: 'Savings Goals' },
        { id: 'recommendations', label: 'Strategy' },
      ],
    };
  },

  computed: {
    ...mapState('savings', ['loading', 'error', 'lifeEvents', 'lifeEventImpact', 'goalStrategies', 'goalsSummary']),

    // Check if this component is embedded in another page (like Net Worth)
    isEmbedded() {
      return this.$route.path.startsWith('/net-worth/');
    },
  },

  mounted() {
    // Reset detail view state when mounted (for embedded mode)
    if (this.isEmbedded) {
      this.setDetailView(false);
    }

    this.loadSavingsData();

    // Check for tab query parameter and set active tab
    const tabParam = this.$route.query.tab;
    if (tabParam && this.tabs.some(tab => tab.id === tabParam)) {
      this.activeTab = tabParam;
    }
  },

  methods: {
    ...mapActions('savings', ['fetchSavingsData']),
    ...mapActions('netWorth', ['setDetailView']),

    async loadSavingsData() {
      try {
        await this.fetchSavingsData();
      } catch (error) {
        logger.error('Failed to load savings data:', error);
      }
    },

    // Account selection for detail view (only when embedded)
    selectAccount(account) {
      if (this.isEmbedded) {
        this.selectedAccount = account;
        this.setDetailView(true);
      } else {
        // When not embedded, use router navigation
        this.$router.push({ name: 'SavingsAccountDetail', params: { id: account.id } });
      }
    },

    clearSelection() {
      this.selectedAccount = null;
      this.setDetailView(false);
      // Refresh data after returning
      this.loadSavingsData();
    },

    handleAccountDeleted() {
      this.selectedAccount = null;
      this.setDetailView(false);
      this.loadSavingsData();
    },
  },
};
</script>

<style scoped>
/* Mobile optimization for tab navigation */
@media (max-width: 640px) {
  .savings-dashboard nav[aria-label="Tabs"] button {
    font-size: 0.875rem;
    padding-left: 1rem;
    padding-right: 1rem;
  }
}
</style>
