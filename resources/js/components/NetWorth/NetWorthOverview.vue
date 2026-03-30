<template>
  <div class="net-worth-overview">
    <div v-if="loading" class="loading-state">
      <div class="w-10 h-10 border-[3px] border-light-gray border-t-raspberry-500 rounded-full animate-spin mb-4"></div>
      <p>Loading your assets...</p>
    </div>

    <div v-else class="overview-cards">
      <!-- Retirement Card -->
      <div class="asset-card retirement-card" @click="navigateTo('retirement')">
        <div class="card-header">
          <div class="card-icon retirement">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div class="card-title-section">
            <h3 class="card-title">Retirement</h3>
            <p class="card-total">{{ formatCurrency(assetsSummaryDetailed.pensions?.total_value || 0) }}</p>
          </div>
          <div class="card-arrow">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
            </svg>
          </div>
        </div>
        <div class="card-items">
          <div v-if="pensionItems.length === 0" class="empty-state">
            <p>No pensions recorded</p>
          </div>
          <div v-else>
            <div
              v-for="item in displayedPensions"
              :key="`pension-${item.type}-${item.id}`"
              class="item-row"
            >
              <span class="item-name">{{ item.name }}</span>
              <span class="item-value">{{ formatCurrency(item.value) }}</span>
            </div>
            <div v-if="pensionItems.length > maxDisplayItems" class="view-all">
              +{{ pensionItems.length - maxDisplayItems }} more
            </div>
          </div>
        </div>
      </div>

      <!-- Property Card -->
      <div class="asset-card property-card" @click="navigateTo('property')">
        <div class="card-header">
          <div class="card-icon property">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
            </svg>
          </div>
          <div class="card-title-section">
            <h3 class="card-title">Property</h3>
            <p class="card-total">{{ formatCurrency(assetsSummaryDetailed.property?.total_value || 0) }}</p>
          </div>
          <div class="card-arrow">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
            </svg>
          </div>
        </div>
        <div class="card-items">
          <div v-if="propertyItems.length === 0" class="empty-state">
            <p>No properties recorded</p>
          </div>
          <div v-else>
            <div
              v-for="item in displayedProperties"
              :key="`property-${item.id}`"
              class="item-row"
            >
              <span class="item-name">{{ item.name }}</span>
              <span class="item-value">{{ formatCurrency(item.value) }}</span>
            </div>
            <div v-if="propertyItems.length > maxDisplayItems" class="view-all">
              +{{ propertyItems.length - maxDisplayItems }} more
            </div>
          </div>
        </div>
      </div>

      <!-- Investments Card -->
      <div class="asset-card investments-card" @click="navigateTo('investments')">
        <div class="card-header">
          <div class="card-icon investments">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
            </svg>
          </div>
          <div class="card-title-section">
            <h3 class="card-title">Investments</h3>
            <p class="card-total">{{ formatCurrency(assetsSummaryDetailed.investments?.total_value || 0) }}</p>
          </div>
          <div class="card-arrow">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
            </svg>
          </div>
        </div>
        <div class="card-items">
          <div v-if="investmentItems.length === 0" class="empty-state">
            <p>No investments recorded</p>
          </div>
          <div v-else>
            <div
              v-for="item in displayedInvestments"
              :key="`investment-${item.id}`"
              class="item-row"
            >
              <span class="item-name">{{ item.name }}</span>
              <span class="item-value">{{ formatCurrency(item.value) }}</span>
            </div>
            <div v-if="investmentItems.length > maxDisplayItems" class="view-all">
              +{{ investmentItems.length - maxDisplayItems }} more
            </div>
          </div>
        </div>
      </div>

      <!-- Cash Card -->
      <div class="asset-card cash-card" @click="navigateTo('cash')">
        <div class="card-header">
          <div class="card-icon cash">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
            </svg>
          </div>
          <div class="card-title-section">
            <h3 class="card-title">Cash</h3>
            <p class="card-total">{{ formatCurrency(assetsSummaryDetailed.cash?.total_value || 0) }}</p>
          </div>
          <div class="card-arrow">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
            </svg>
          </div>
        </div>
        <div class="card-items">
          <div v-if="cashItems.length === 0" class="empty-state">
            <p>No cash accounts recorded</p>
          </div>
          <div v-else>
            <div
              v-for="item in displayedCash"
              :key="`cash-${item.id}`"
              class="item-row"
            >
              <span class="item-name">
                {{ item.name }}
                <span v-if="item.is_isa" class="badge isa">ISA</span>
                <span v-if="item.is_emergency_fund" class="badge emergency">Emergency</span>
              </span>
              <span class="item-value">{{ formatCurrency(item.value) }}</span>
            </div>
            <div v-if="cashItems.length > maxDisplayItems" class="view-all">
              +{{ cashItems.length - maxDisplayItems }} more
            </div>
          </div>
        </div>
      </div>

      <!-- Business Interest Card -->
      <div class="asset-card business-card" @click="navigateTo('business')">
        <div class="card-header">
          <div class="card-icon business">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z" />
            </svg>
          </div>
          <div class="card-title-section">
            <h3 class="card-title">Business Interest</h3>
            <p class="card-total">{{ formatCurrency(assetsSummaryDetailed.business?.total_value || 0) }}</p>
          </div>
          <div class="card-arrow">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
            </svg>
          </div>
        </div>
        <div class="card-items">
          <div v-if="businessItems.length === 0" class="empty-state">
            <p>No business interests recorded</p>
          </div>
          <div v-else>
            <div
              v-for="item in displayedBusiness"
              :key="`business-${item.id}`"
              class="item-row"
            >
              <span class="item-name">
                {{ item.name }}
                <span class="badge business-type">{{ formatBusinessType(item.business_type) }}</span>
              </span>
              <span class="item-value">{{ formatCurrency(item.value) }}</span>
            </div>
            <div v-if="businessItems.length > maxDisplayItems" class="view-all">
              +{{ businessItems.length - maxDisplayItems }} more
            </div>
          </div>
        </div>
      </div>

      <!-- Chattels Card -->
      <div class="asset-card chattels-card" @click="navigateTo('chattels')">
        <div class="card-header">
          <div class="card-icon chattels">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
            </svg>
          </div>
          <div class="card-title-section">
            <h3 class="card-title">Personal Valuables</h3>
            <p class="card-total">{{ formatCurrency(assetsSummaryDetailed.chattels?.total_value || 0) }}</p>
          </div>
          <div class="card-arrow">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
            </svg>
          </div>
        </div>
        <div class="card-items">
          <div v-if="chattelItems.length === 0" class="empty-state">
            <p>No personal valuables recorded</p>
          </div>
          <div v-else>
            <div
              v-for="item in displayedChattels"
              :key="`chattel-${item.id}`"
              class="item-row"
            >
              <span class="item-name">
                {{ item.name }}
                <span class="badge chattel-type">{{ formatChattelType(item.chattel_type) }}</span>
              </span>
              <span class="item-value">{{ formatCurrency(item.value) }}</span>
            </div>
            <div v-if="chattelItems.length > maxDisplayItems" class="view-all">
              +{{ chattelItems.length - maxDisplayItems }} more
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script>
import { mapState, mapGetters, mapActions } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';

import logger from '@/utils/logger';
export default {
  name: 'NetWorthOverview',
  mixins: [currencyMixin],

  data() {
    return {
      maxDisplayItems: 3,
    };
  },

  computed: {
    ...mapState('netWorth', ['assetsSummaryDetailed', 'loading']),

    pensionItems() {
      return this.assetsSummaryDetailed.pensions?.items || [];
    },

    propertyItems() {
      return this.assetsSummaryDetailed.property?.items || [];
    },

    investmentItems() {
      return this.assetsSummaryDetailed.investments?.items || [];
    },

    cashItems() {
      return this.assetsSummaryDetailed.cash?.items || [];
    },

    businessItems() {
      return this.assetsSummaryDetailed.business?.items || [];
    },

    chattelItems() {
      return this.assetsSummaryDetailed.chattels?.items || [];
    },

    displayedPensions() {
      return this.pensionItems.slice(0, this.maxDisplayItems);
    },

    displayedProperties() {
      return this.propertyItems.slice(0, this.maxDisplayItems);
    },

    displayedInvestments() {
      return this.investmentItems.slice(0, this.maxDisplayItems);
    },

    displayedCash() {
      return this.cashItems.slice(0, this.maxDisplayItems);
    },

    displayedBusiness() {
      return this.businessItems.slice(0, this.maxDisplayItems);
    },

    displayedChattels() {
      return this.chattelItems.slice(0, this.maxDisplayItems);
    },
  },

  methods: {
    ...mapActions('netWorth', ['fetchAssetsSummaryDetailed']),

    navigateTo(section) {
      const isPreview = this.$route.path.startsWith('/preview');
      const basePath = isPreview ? '/preview/net-worth' : '/net-worth';
      this.$router.push(`${basePath}/${section}`);
    },

    formatBusinessType(type) {
      const types = {
        sole_trader: 'Sole Trader',
        partnership: 'Partnership',
        limited_company: 'Ltd',
        llp: 'LLP',
        other: 'Other',
      };
      return types[type] || type;
    },

    formatChattelType(type) {
      const types = {
        vehicle: 'Vehicle',
        art: 'Art',
        antique: 'Antique',
        jewelry: 'Jewellery',
        collectible: 'Collectible',
        other: 'Other',
      };
      return types[type] || type;
    },
  },

  async mounted() {
    try {
      await this.fetchAssetsSummaryDetailed();
    } catch (error) {
      logger.error('Failed to load assets summary:', error);
    }
  },
};
</script>

<style scoped>
.net-worth-overview {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 48px;
  @apply text-neutral-500;
}

.overview-cards {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  grid-template-rows: repeat(2, auto);
  gap: 20px;
}

.asset-card {
  background: white;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  @apply border border-light-gray;
  cursor: pointer;
  transition: all 0.2s;
}

.asset-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  @apply border-raspberry-500;
}

.asset-card.business-card:hover {
  @apply border-purple-500;
}

.asset-card.chattels-card:hover {
  @apply border-pink-500;
}

.card-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
  padding-bottom: 16px;
  @apply border-b border-light-gray;
}

.card-icon {
  width: 44px;
  height: 44px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.card-icon svg {
  width: 22px;
  height: 22px;
}

.card-icon.retirement {
  @apply bg-violet-100;
  @apply text-violet-600;
}

.card-icon.property {
  @apply bg-violet-100;
  @apply text-violet-600;
}

.card-icon.investments {
  @apply bg-spring-100;
  @apply text-spring-600;
}

.card-icon.cash {
  @apply bg-violet-100;
  @apply text-purple-600;
}

.card-icon.business {
  @apply bg-fuchsia-100;
  @apply text-fuchsia-700;
}

.card-icon.chattels {
  @apply bg-pink-100;
  @apply text-pink-600;
}

.card-title-section {
  flex: 1;
}

.card-title {
  font-size: 16px;
  font-weight: 600;
  @apply text-neutral-500;
  margin: 0 0 4px 0;
}

.card-total {
  font-size: 24px;
  font-weight: 700;
  @apply text-horizon-500;
  margin: 0;
}

.card-arrow {
  width: 24px;
  height: 24px;
  @apply text-horizon-400;
  flex-shrink: 0;
}

.card-arrow svg {
  width: 100%;
  height: 100%;
}

.card-items {
  min-height: 80px;
}

.empty-state {
  @apply text-horizon-400;
  font-size: 14px;
  text-align: center;
  padding: 16px 0;
}

.empty-state p {
  margin: 0;
}

.item-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 0;
  @apply border-b border-light-gray;
}

.item-row:last-child {
  border-bottom: none;
}

.item-name {
  font-size: 14px;
  @apply text-neutral-500;
  display: flex;
  align-items: center;
  gap: 8px;
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.badge {
  font-size: 10px;
  padding: 2px 6px;
  border-radius: 4px;
  font-weight: 500;
  flex-shrink: 0;
}

.badge.isa {
  @apply bg-violet-100;
  @apply text-violet-700;
}

.badge.emergency {
  @apply bg-violet-100;
  @apply text-violet-700;
}

.badge.business-type {
  @apply bg-fuchsia-100;
  @apply text-fuchsia-800;
}

.badge.chattel-type {
  @apply bg-pink-100;
  @apply text-pink-800;
}

.item-value {
  font-size: 14px;
  font-weight: 600;
  @apply text-horizon-500;
  flex-shrink: 0;
  margin-left: 12px;
}

.view-all {
  font-size: 13px;
  @apply text-raspberry-500;
  text-align: center;
  padding: 8px 0 0 0;
  font-weight: 500;
}

/* Mobile responsive */
@media (max-width: 1200px) {
  .overview-cards {
    grid-template-columns: repeat(2, 1fr);
    grid-template-rows: repeat(3, auto);
  }
}

@media (max-width: 768px) {
  .overview-cards {
    grid-template-columns: 1fr;
    grid-template-rows: auto;
  }
}

@media (max-width: 768px) {
  .card-total {
    font-size: 20px;
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
