<template>
  <div class="net-worth-wealth-summary">
    <div class="chart-full-width">
      <WealthSummary
        :breakdown="overview.breakdown"
        :liabilities-breakdown="overview.liabilitiesBreakdown"
        :total-assets="overview.totalAssets"
        :total-liabilities="overview.totalLiabilities"
        :spouse-data="filteredSpouseOverview"
        :user-name="currentUserName"
        :spouse-name="filteredSpouseName"
      />
    </div>

    <!-- Asset Allocation Cards - 3 inline -->
    <div class="allocation-cards-grid">
      <div class="chart-item">
        <AssetAllocationDonut
          :breakdown="overview.breakdown"
          :title="`${currentUserName}'s Asset Allocation`"
        />
      </div>
      <div v-if="filteredSpouseOverview" class="chart-item chart-item-center">
        <AssetAllocationDonut
          :breakdown="filteredSpouseOverview.breakdown || {}"
          :title="`${filteredSpouseName}'s Asset Allocation`"
        />
      </div>
      <div v-if="filteredSpouseOverview" class="chart-item">
        <AssetAllocationDonut
          :breakdown="combinedBreakdown"
          title="Combined Asset Allocation"
        />
      </div>
    </div>

    <div v-if="asOfDate" class="last-updated">
      <p>Last updated: {{ formatDate(asOfDate) }}</p>
    </div>
  </div>
</template>

<script>
import { mapState, mapGetters, mapActions } from 'vuex';
import AssetAllocationDonut from './AssetAllocationDonut.vue';
import WealthSummary from './WealthSummary.vue';

export default {
  name: 'NetWorthWealthSummary',

  components: {
    AssetAllocationDonut,
    WealthSummary,
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
      const user = this.$store.getters['auth/currentUser'];
      const spouseName = user?.spouse?.name;
      return spouseName || 'Partner';
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
  },

  methods: {
    ...mapActions('netWorth', ['loadAllData']),

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
      console.error('Failed to load net worth data:', error);
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

.chart-full-width {
  width: 100%;
}

.allocation-cards-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
  padding: 0 8px;
}

.chart-item {
  min-width: 0;
  overflow: visible;
}

.last-updated {
  text-align: center;
  padding: 12px;
  @apply bg-gray-50;
  border-radius: 8px;
}

.last-updated p {
  margin: 0;
  font-size: 14px;
  @apply text-gray-500;
}

/* Mobile responsive */
@media (max-width: 1200px) {
  .allocation-cards-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 768px) {
  .allocation-cards-grid {
    grid-template-columns: 1fr;
  }
}
</style>
