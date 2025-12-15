<template>
  <div class="net-worth-wealth-summary">
    <div class="summary-cards">
      <div class="summary-card assets-card clickable" @click="navigateToAssets">
        <div class="card-icon">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941" />
          </svg>
        </div>
        <div class="card-content">
          <p class="card-label">Total Assets</p>
          <p class="card-value">{{ formattedAssets }}</p>
        </div>
      </div>

      <div class="summary-card liabilities-card clickable" @click="navigateToLiabilities">
        <div class="card-icon">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6L9 12.75l4.286-4.286a11.948 11.948 0 014.306 6.43l.776 2.898m0 0l3.182-5.511m-3.182 5.51l-5.511-3.181" />
          </svg>
        </div>
        <div class="card-content">
          <p class="card-label">Total Liabilities</p>
          <p class="card-value">{{ formattedLiabilities }}</p>
        </div>
      </div>

      <div class="summary-card net-worth-card highlighted clickable" @click="navigateToBalanceSheet">
        <div class="card-icon">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div class="card-content">
          <p class="card-label">Net Worth</p>
          <p class="card-value" :class="netWorthClass">{{ formattedNetWorth }}</p>
        </div>
      </div>
    </div>

    <div class="chart-full-width">
      <WealthSummary
        :breakdown="overview.breakdown"
        :liabilities-breakdown="overview.liabilitiesBreakdown"
        :total-assets="overview.totalAssets"
        :total-liabilities="overview.totalLiabilities"
        :spouse-data="spouseOverview"
        :user-name="currentUserName"
        :spouse-name="spouseUserName"
      />
    </div>

    <div class="charts-grid">
      <div class="chart-item">
        <AssetAllocationDonut :breakdown="overview.breakdown" />
      </div>
      <div class="chart-item">
        <NetWorthTrendChart :trend="trend" />
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
import NetWorthTrendChart from './NetWorthTrendChart.vue';
import WealthSummary from './WealthSummary.vue';

export default {
  name: 'NetWorthWealthSummary',

  components: {
    AssetAllocationDonut,
    NetWorthTrendChart,
    WealthSummary,
  },

  computed: {
    ...mapState('netWorth', ['overview', 'trend', 'loading', 'spouseOverview']),
    ...mapGetters('netWorth', [
      'formattedNetWorth',
      'formattedAssets',
      'formattedLiabilities',
      'netWorth',
    ]),

    asOfDate() {
      return this.overview.asOfDate;
    },

    netWorthClass() {
      if (this.netWorth < 0) {
        return 'negative';
      } else if (this.netWorth > 0) {
        return 'positive';
      }
      return '';
    },

    currentUserName() {
      const user = this.$store.getters['auth/currentUser'];
      return user?.name || 'Your Wealth';
    },

    spouseUserName() {
      const user = this.$store.getters['auth/currentUser'];
      const spouseName = user?.spouse?.name;
      return spouseName || 'Spouse';
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

    navigateToAssets() {
      this.$router.push('/profile?section=assets');
    },

    navigateToLiabilities() {
      this.$router.push('/profile?section=liabilities');
    },

    navigateToBalanceSheet() {
      this.$router.push('/profile?section=accounts');
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
}

.summary-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 20px;
}

.summary-card {
  background: white;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  border: 1px solid #e5e7eb;
  display: flex;
  align-items: center;
  gap: 16px;
  transition: all 0.2s;
}

.summary-card.clickable {
  cursor: pointer;
}

.summary-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.summary-card.highlighted {
  border: 2px solid #3b82f6;
  background: linear-gradient(135deg, #f0f9ff 0%, #ffffff 100%);
}

.card-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.assets-card .card-icon {
  background: #d1fae5;
  color: #10b981;
}

.liabilities-card .card-icon {
  background: #fee2e2;
  color: #ef4444;
}

.net-worth-card .card-icon {
  background: #dbeafe;
  color: #3b82f6;
}

.card-icon svg {
  width: 24px;
  height: 24px;
}

.card-content {
  flex: 1;
}

.card-label {
  font-size: 14px;
  color: #6b7280;
  font-weight: 500;
  margin: 0 0 8px 0;
}

.card-value {
  font-size: 28px;
  font-weight: 700;
  color: #111827;
  margin: 0;
}

.card-value.positive {
  color: #10b981;
}

.card-value.negative {
  color: #ef4444;
}

.charts-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
  gap: 24px;
}

.chart-item {
  min-width: 0;
}

.chart-full-width {
  width: 100%;
}

.last-updated {
  text-align: center;
  padding: 12px;
  background: #f9fafb;
  border-radius: 8px;
}

.last-updated p {
  margin: 0;
  font-size: 14px;
  color: #6b7280;
}

/* Mobile responsive */
@media (max-width: 768px) {
  .summary-cards {
    grid-template-columns: 1fr;
  }

  .charts-grid {
    grid-template-columns: 1fr;
  }

  .summary-card {
    padding: 16px;
  }

  .card-value {
    font-size: 24px;
  }

  .card-icon {
    width: 40px;
    height: 40px;
  }

  .card-icon svg {
    width: 20px;
    height: 20px;
  }
}
</style>
