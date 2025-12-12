<template>
  <component :is="isEmbedded ? 'div' : 'AppLayout'">
    <div class="retirement-dashboard py-2 sm:py-6 px-0 sm:px-6">
      <!-- Header (only show when not embedded) -->
    <div v-if="!isEmbedded" class="mb-8">
      <h1 class="text-3xl font-bold text-gray-900">Retirement Planning</h1>
      <p class="text-gray-600 mt-2">Plan your retirement with confidence</p>
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
              'px-3 sm:px-6 py-3 sm:py-4 text-xs sm:text-sm font-medium whitespace-nowrap transition-colors duration-200 bg-transparent flex-shrink-0',
              activeTab === tab.id
                ? 'text-indigo-600 border-b-2 border-indigo-600'
                : 'text-gray-600 hover:text-gray-900 border-b-2 border-transparent'
            ]"
          >
            {{ tab.name }}
          </button>
        </nav>
      </div>

      <!-- Tab Content -->
      <transition name="fade" mode="out-in">
        <!-- Overview Mode: Show standard tabs -->
        <component
          v-if="!isDetailMode"
          :is="currentTabComponent"
          :key="activeTab"
          @change-tab="handleTabChange"
          @select-pension="selectPension"
        />
        <!-- Detail Mode: Show pension detail view -->
        <PensionDetailView
          v-else
          :key="'detail-' + selectedPensionType + '-' + (selectedPension?.id || 'state')"
          :pension="selectedPension"
          :pension-type="selectedPensionType"
          :active-tab="activeTab"
        />
      </transition>
    </div>
    </div>
  </component>
</template>

<script>
import { mapState } from 'vuex';
import AppLayout from '@/layouts/AppLayout.vue';
import RetirementReadiness from './RetirementReadiness.vue';
import ContributionsAllowances from './ContributionsAllowances.vue';
import Projections from './Projections.vue';
import PortfolioAnalysis from './PortfolioAnalysis.vue';
import Recommendations from './Recommendations.vue';
import DecumulationPlanning from './DecumulationPlanning.vue';
import PensionDetailView from './PensionDetailView.vue';

export default {
  name: 'RetirementDashboard',

  components: {
    AppLayout,
    RetirementReadiness,
    ContributionsAllowances,
    Projections,
    PortfolioAnalysis,
    Recommendations,
    DecumulationPlanning,
    PensionDetailView,
  },

  data() {
    return {
      activeTab: 'readiness',
      // State for pension detail navigation
      selectedPension: null,
      selectedPensionType: null, // 'dc', 'db', or 'state'
      // Overview mode tabs
      overviewTabs: [
        { id: 'readiness', name: 'Overview' },
        { id: 'contributions', name: 'Contributions' },
        { id: 'projections', name: 'Projections' },
        { id: 'portfolio', name: 'Portfolio Analysis' },
        { id: 'recommendations', name: 'Strategies' },
        { id: 'decumulation', name: 'Decumulation' },
      ],
      // Detail mode tabs (shown when viewing a specific pension)
      detailTabs: [
        { id: 'all-pensions', name: 'All Pensions' },
        { id: 'summary', name: 'Summary' },
        { id: 'details', name: 'Details' },
        { id: 'contributions', name: 'Contributions' },
        { id: 'projections', name: 'Projections' },
        { id: 'analysis', name: 'Analysis' },
      ],
    };
  },

  computed: {
    ...mapState('retirement', ['loading', 'error']),

    // Check if this component is embedded in Net Worth module
    isEmbedded() {
      return this.$route.path.startsWith('/net-worth/');
    },

    // Check if we're in detail mode (viewing a specific pension)
    isDetailMode() {
      return this.selectedPension !== null;
    },

    // Return appropriate tabs based on mode
    currentTabs() {
      return this.isDetailMode ? this.detailTabs : this.overviewTabs;
    },

    currentTabComponent() {
      // Only used in overview mode
      const componentMap = {
        readiness: 'RetirementReadiness',
        contributions: 'ContributionsAllowances',
        projections: 'Projections',
        portfolio: 'PortfolioAnalysis',
        recommendations: 'Recommendations',
        decumulation: 'DecumulationPlanning',
      };
      return componentMap[this.activeTab];
    },
  },

  methods: {
    handleTabChange(tabId) {
      this.activeTab = tabId;
    },

    handleTabClick(tabId) {
      // Handle "All Pensions" tab click to return to overview
      if (tabId === 'all-pensions') {
        this.clearSelection();
        return;
      }
      this.activeTab = tabId;
    },

    // Called when a pension card is clicked in RetirementReadiness
    selectPension(pension, type) {
      this.selectedPension = pension;
      this.selectedPensionType = type;
      this.activeTab = 'summary'; // Start on summary tab
    },

    // Return to overview mode
    clearSelection() {
      this.selectedPension = null;
      this.selectedPensionType = null;
      this.activeTab = 'readiness';
    },
  },

  async mounted() {
    try {
      await this.$store.dispatch('retirement/fetchRetirementData');
      await this.$store.dispatch('retirement/analyseRetirement');
    } catch (error) {
      console.error('Failed to load retirement data:', error);
    }
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
