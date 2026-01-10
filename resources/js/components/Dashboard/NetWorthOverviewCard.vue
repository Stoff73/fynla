<template>
  <div
    class="net-worth-overview-card"
    @click="navigateToDetail"
    role="button"
    tabindex="0"
    @keypress.enter="navigateToDetail"
  >
    <div v-if="showSkeleton" class="loading-skeleton">
      <div class="skeleton-text skeleton-large"></div>
      <div class="skeleton-text skeleton-small"></div>
      <div class="skeleton-text skeleton-small"></div>
      <div class="skeleton-text skeleton-small"></div>
      <div class="skeleton-text skeleton-small"></div>
    </div>

    <div v-else-if="error" class="error-state">
      <p class="error-message">{{ error }}</p>
      <button @click.stop="retry" class="retry-button">Retry</button>
    </div>

    <div v-else class="card-content">
      <div class="net-worth-value">
        <span class="value-label">Total Net Worth</span>
        <span class="value-amount" :class="netWorthClass">{{ formattedNetWorth }}</span>
      </div>

      <!-- Assets Section -->
      <div v-if="totalAssets > 0" class="section-breakdown">
        <div class="section-header">Assets</div>
        <div class="asset-breakdown">
          <div class="breakdown-item" v-if="breakdown.pensions > 0">
            <span class="breakdown-label">Pensions</span>
            <span class="breakdown-value breakdown-value-asset">{{ formatCurrency(breakdown.pensions) }}</span>
          </div>
          <div class="breakdown-item" v-if="breakdown.property > 0">
            <span class="breakdown-label">Property</span>
            <span class="breakdown-value breakdown-value-asset">{{ formatCurrency(breakdown.property) }}</span>
          </div>
          <div class="breakdown-item" v-if="breakdown.investments > 0">
            <span class="breakdown-label">Investments</span>
            <span class="breakdown-value breakdown-value-asset">{{ formatCurrency(breakdown.investments) }}</span>
          </div>
          <div class="breakdown-item" v-if="breakdown.cash > 0">
            <span class="breakdown-label">Cash</span>
            <span class="breakdown-value breakdown-value-asset">{{ formatCurrency(breakdown.cash) }}</span>
          </div>
          <div class="breakdown-item" v-if="breakdown.business > 0">
            <span class="breakdown-label">Business</span>
            <span class="breakdown-value breakdown-value-asset">{{ formatCurrency(breakdown.business) }}</span>
          </div>
          <div class="breakdown-item" v-if="breakdown.chattels > 0">
            <span class="breakdown-label">Chattels</span>
            <span class="breakdown-value breakdown-value-asset">{{ formatCurrency(breakdown.chattels) }}</span>
          </div>
        </div>
      </div>

      <!-- Liabilities Section -->
      <div v-if="totalLiabilities > 0" class="section-breakdown">
        <div class="section-header">Liabilities</div>
        <div class="asset-breakdown">
          <div class="breakdown-item" v-if="liabilitiesBreakdown.mortgages > 0">
            <span class="breakdown-label">Mortgages</span>
            <span class="breakdown-value breakdown-value-liability">{{ formatCurrency(liabilitiesBreakdown.mortgages) }}</span>
          </div>
          <div class="breakdown-item" v-if="liabilitiesBreakdown.loans > 0">
            <span class="breakdown-label">Loans</span>
            <span class="breakdown-value breakdown-value-liability">{{ formatCurrency(liabilitiesBreakdown.loans) }}</span>
          </div>
          <div class="breakdown-item" v-if="liabilitiesBreakdown.credit_cards > 0">
            <span class="breakdown-label">Credit Cards</span>
            <span class="breakdown-value breakdown-value-liability">{{ formatCurrency(liabilitiesBreakdown.credit_cards) }}</span>
          </div>
          <div class="breakdown-item" v-if="liabilitiesBreakdown.other > 0">
            <span class="breakdown-label">Other</span>
            <span class="breakdown-value breakdown-value-liability">{{ formatCurrency(liabilitiesBreakdown.other) }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { mapState, mapGetters, mapActions } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'NetWorthOverviewCard',
  mixins: [currencyMixin],

  computed: {
    ...mapState('netWorth', ['loading', 'error', 'overview']),
    ...mapGetters('netWorth', ['formattedNetWorth', 'netWorth', 'totalAssets', 'totalLiabilities']),

    isPreviewMode() {
      return this.$store.getters['preview/isPreviewMode'];
    },

    // Show skeleton when loading OR when data hasn't been fetched yet
    // (asOfDate is null until first successful API call)
    showSkeleton() {
      return this.loading || (!this.error && !this.overview.asOfDate);
    },

    breakdown() {
      return this.overview.breakdown || {};
    },

    liabilitiesBreakdown() {
      return this.overview.liabilitiesBreakdown || {};
    },

    netWorthClass() {
      if (this.netWorth < 0) {
        return 'negative';
      } else if (this.netWorth > 0) {
        return 'positive';
      }
      return '';
    },
  },

  methods: {
    ...mapActions('netWorth', ['fetchOverview']),

    navigateToDetail() {
      this.$router.push('/net-worth/overview');
    },

    async retry() {
      // Preview users are real DB users - use normal API to fetch their data
      await this.fetchOverview();
    },
  },

  // Note: No mounted() hook - Dashboard.vue coordinates data loading
  // via its user watcher to prevent race conditions
};
</script>

<style scoped>
.net-worth-overview-card {
  padding: 24px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.net-worth-overview-card:hover {
  background: rgba(59, 130, 246, 0.04);
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.card-title {
  font-size: 18px;
  font-weight: 600;
  color: #111827;
  margin: 0;
}

.card-icon {
  color: #3b82f6;
}

.card-icon svg {
  width: 24px;
  height: 24px;
}

.card-content {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.net-worth-value {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding-bottom: 16px;
  border-bottom: 1px solid #e5e7eb;
}

.value-label {
  font-size: 14px;
  color: #6b7280;
  font-weight: 500;
}

.value-amount {
  font-size: 32px;
  font-weight: 700;
  color: #111827;
}

.value-amount.positive {
  color: #10b981;
}

.value-amount.negative {
  color: #ef4444;
}

.section-breakdown {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.section-breakdown + .section-breakdown {
  margin-top: 16px;
  padding-top: 16px;
  border-top: 1px solid #e5e7eb;
}

.section-header {
  font-size: 14px;
  font-weight: 600;
  color: #374151;
  margin-bottom: 4px;
}

.asset-breakdown {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.breakdown-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 14px;
}

.breakdown-label {
  color: #6b7280;
  font-weight: 500;
}

.breakdown-value {
  color: #111827;
  font-weight: 600;
}

.breakdown-value-asset {
  color: #2563eb;
}

.breakdown-value-liability {
  color: #dc2626;
}

.loading-skeleton {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.skeleton-text {
  background: linear-gradient(90deg, #f3f4f6 25%, #e5e7eb 50%, #f3f4f6 75%);
  background-size: 200% 100%;
  animation: loading 1.5s ease-in-out infinite;
  border-radius: 4px;
  height: 20px;
}

.skeleton-large {
  height: 40px;
  width: 60%;
}

.skeleton-small {
  height: 16px;
  width: 80%;
}

@keyframes loading {
  0% {
    background-position: 200% 0;
  }
  100% {
    background-position: -200% 0;
  }
}

.error-state {
  padding: 20px;
  text-align: center;
}

.error-message {
  color: #ef4444;
  font-size: 14px;
  margin-bottom: 12px;
}

.retry-button {
  background: #3b82f6;
  color: white;
  padding: 8px 16px;
  border-radius: 6px;
  border: none;
  cursor: pointer;
  font-size: 14px;
  font-weight: 600;
  transition: background 0.2s;
}

.retry-button:hover {
  background: #2563eb;
}

/* Mobile responsive */
@media (max-width: 768px) {
  .net-worth-overview-card {
    padding: 16px;
  }

  .card-title {
    font-size: 16px;
  }

  .value-amount {
    font-size: 24px;
  }

  .breakdown-item {
    font-size: 13px;
  }
}
</style>
