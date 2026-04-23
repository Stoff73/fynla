<template>
  <div class="net-worth-wealth-summary module-gradient">
    <ModuleStatusBar />
    <!-- Joint users: two per-person donuts inline, then a combined-household bar chart below with a per-user tooltip breakdown. Single users keep the original side-by-side donut + bar layout. -->
    <template v-if="filteredSpouseOverview">
      <div class="donut-row">
        <AssetAllocationDonut
          :breakdown="overview.breakdown"
          :title="`${currentUserName}'s Asset Allocation`"
          @highlight="onChartHighlight('user', $event)"
          @clear-highlight="onChartClearHighlight"
        />
        <AssetAllocationDonut
          :breakdown="filteredSpouseOverview.breakdown || {}"
          :title="`${filteredSpouseName}'s Asset Allocation`"
          @highlight="onChartHighlight('spouse', $event)"
          @clear-highlight="onChartClearHighlight"
        />
      </div>

      <AssetBreakdownBar
        :breakdown="combinedBreakdown"
        :liabilities-breakdown="combinedLiabilitiesBreakdown"
        :total-assets="combinedTotalAssets"
        :total-liabilities="combinedTotalLiabilities"
        :user-breakdown="overview.breakdown"
        :spouse-breakdown="filteredSpouseOverview.breakdown || {}"
        :user-liabilities="overview.liabilitiesBreakdown"
        :spouse-liabilities="filteredSpouseOverview.liabilitiesBreakdown || {}"
        :user-name="currentUserName"
        :spouse-name="filteredSpouseName"
      />
    </template>

    <div v-else class="charts-row">
      <div class="chart-column">
        <AssetAllocationDonut
          :breakdown="overview.breakdown"
          :title="`${currentUserName}'s Asset Allocation`"
          @highlight="onChartHighlight('user', $event)"
          @clear-highlight="onChartClearHighlight"
        />
      </div>
      <div class="bar-chart-column">
        <AssetBreakdownBar
          :breakdown="overview.breakdown"
          :liabilities-breakdown="overview.liabilitiesBreakdown"
          :total-assets="overview.totalAssets"
          :total-liabilities="overview.totalLiabilities"
        />
      </div>
    </div>

    <!-- Wealth Summary Table (full width below) -->
    <WealthSummary
      :breakdown="overview.breakdown"
      :liabilities-breakdown="overview.liabilitiesBreakdown"
      :total-assets="overview.totalAssets"
      :total-liabilities="overview.totalLiabilities"
      :spouse-data="filteredSpouseOverview"
      :user-name="currentUserName"
      :spouse-name="filteredSpouseName"
      :has-db-pensions="overview.hasDbPensions"
      :spouse-has-db-pensions="filteredSpouseOverview?.hasDbPensions || false"
      :highlighted-cell="highlightedCell"
    />

    <div v-if="asOfDate" class="last-updated">
      <p>Last updated: {{ formatDate(asOfDate) }}</p>
    </div>
  </div>
</template>

<script>
import { mapState, mapGetters, mapActions } from 'vuex';
import AssetAllocationDonut from './AssetAllocationDonut.vue';
import AssetBreakdownBar from './AssetBreakdownBar.vue';
import WealthSummary from './WealthSummary.vue';
import ModuleStatusBar from '@/components/Shared/ModuleStatusBar.vue';

import logger from '@/utils/logger';
export default {
  name: 'NetWorthWealthSummary',

  components: {
    AssetAllocationDonut,
    AssetBreakdownBar,
    WealthSummary,
    ModuleStatusBar,
  },

  data() {
    return {
      highlightedCell: null, // { category: 'pensions', column: 'user' | 'spouse' | 'total' }
    };
  },

  computed: {
    ...mapState('netWorth', ['overview', 'loading', 'spouseOverview']),
    ...mapGetters('netWorth', [
      'formattedNetWorth',
      'formattedAssets',
      'formattedLiabilities',
      'netWorth',
    ]),

    asOfDate() {
      return this.overview.asOfDate;
    },

    /**
     * Check if user should see spouse data.
     * Widowed and divorced users should not see spouse columns.
     */
    shouldShowSpouseData() {
      const user = this.$store.getters['auth/currentUser'];
      const maritalStatus = user?.marital_status;
      const excludedStatuses = ['widowed', 'divorced'];
      return !excludedStatuses.includes(maritalStatus);
    },

    /**
     * Returns spouse overview data only for married users.
     */
    filteredSpouseOverview() {
      return this.shouldShowSpouseData ? this.spouseOverview : null;
    },

    /**
     * Returns spouse name only for married users.
     */
    filteredSpouseName() {
      return this.shouldShowSpouseData ? this.spouseUserName : null;
    },

    currentUserName() {
      const user = this.$store.getters['auth/currentUser'];
      return user?.name || 'You';
    },

    spouseUserName() {
      return this.$store.getters['userProfile/spouse']?.name || 'Partner';
    },

    /**
     * Combined breakdown of user and spouse assets for total allocation chart.
     */
    combinedBreakdown() {
      const userBreakdown = this.overview.breakdown || {};
      const spouseBreakdown = this.filteredSpouseOverview?.breakdown || {};

      return {
        property: (userBreakdown.property || 0) + (spouseBreakdown.property || 0),
        investments: (userBreakdown.investments || 0) + (spouseBreakdown.investments || 0),
        cash: (userBreakdown.cash || 0) + (spouseBreakdown.cash || 0),
        pensions: (userBreakdown.pensions || 0) + (spouseBreakdown.pensions || 0),
        business: (userBreakdown.business || 0) + (spouseBreakdown.business || 0),
        chattels: (userBreakdown.chattels || 0) + (spouseBreakdown.chattels || 0),
      };
    },

    /**
     * Combined liabilities breakdown across both household members. The bar
     * chart draws one bar per liability category from this, and uses the
     * per-user liabilities props for the tooltip split.
     */
    combinedLiabilitiesBreakdown() {
      const u = this.overview.liabilitiesBreakdown || {};
      const s = this.filteredSpouseOverview?.liabilitiesBreakdown || {};
      return {
        mortgages: (u.mortgages || 0) + (s.mortgages || 0),
        loans: (u.loans || 0) + (s.loans || 0),
        credit_cards: (u.credit_cards || 0) + (s.credit_cards || 0),
        other: (u.other || 0) + (s.other || 0),
      };
    },

    combinedTotalAssets() {
      return (this.overview.totalAssets || 0)
        + (this.filteredSpouseOverview?.totalAssets || 0);
    },

    combinedTotalLiabilities() {
      return (this.overview.totalLiabilities || 0)
        + (this.filteredSpouseOverview?.totalLiabilities || 0);
    },
  },

  methods: {
    ...mapActions('netWorth', ['loadAllData']),

    onChartHighlight(column, { category, color }) {
      this.highlightedCell = { category, column, color };
    },

    onChartClearHighlight() {
      this.highlightedCell = null;
    },

    formatDate(dateString) {
      const date = new Date(dateString);
      return date.toLocaleDateString('en-GB', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
      });
    },
  },

  async mounted() {
    try {
      await this.loadAllData();
    } catch (error) {
      logger.error('Failed to load net worth data:', error);
    }
  },
};
</script>

<style scoped>
.net-worth-wealth-summary {
  display: flex;
  flex-direction: column;
  gap: 24px;
  overflow: visible;
}

.charts-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 24px;
  align-items: stretch;
}

.donut-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 24px;
  align-items: stretch;
}

@media (max-width: 768px) {
  .donut-row {
    grid-template-columns: 1fr;
  }
}

.chart-column {
  min-width: 0;
  overflow: visible;
  display: flex;
  flex-direction: column;
}

.bar-chart-column {
  min-width: 0;
}

.last-updated {
  text-align: center;
  padding: 12px;
  @apply bg-savannah-100;
  border-radius: 8px;
}

.last-updated p {
  margin: 0;
  font-size: 14px;
  @apply text-neutral-500;
}

@media (max-width: 640px) {
  .charts-row {
    grid-template-columns: 1fr;
  }
}
</style>
