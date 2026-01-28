<template>
  <div class="wealth-summary">
    <h3 class="chart-title">Wealth Summary</h3>

    <div v-if="hasData" class="summary-content" :class="{ 'has-spouse': hasSpouse }">
      <!-- Column Headers -->
      <div class="summary-row header-row">
        <div class="row-label"></div>
        <div class="column-header">{{ userName }}</div>
        <div v-if="hasSpouse" class="column-header">{{ spouseName }}</div>
        <div v-if="hasSpouse" class="column-header total-header">Total</div>
      </div>

      <!-- Assets Section Header -->
      <div class="summary-row section-header-row">
        <div class="row-label section-label">
          <svg class="section-icon text-green-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941" />
          </svg>
          <span>Assets</span>
        </div>
        <div class="column-value"></div>
        <div v-if="hasSpouse" class="column-value"></div>
        <div v-if="hasSpouse" class="column-value"></div>
      </div>

      <!-- Asset Breakdown Rows -->
      <router-link v-if="showAssetRow('property')" :to="getAssetLink('property')" class="summary-row breakdown-row clickable-row">
        <div class="row-label">Property</div>
        <div class="column-value">{{ formatCurrency(userBreakdown.property) }}</div>
        <div v-if="hasSpouse" class="column-value">{{ formatCurrency(spouseBreakdown.property) }}</div>
        <div v-if="hasSpouse" class="column-value total-column">{{ formatCurrency(userBreakdown.property + (spouseBreakdown.property || 0)) }}</div>
      </router-link>
      <router-link v-if="showAssetRow('investments')" :to="getAssetLink('investments')" class="summary-row breakdown-row clickable-row">
        <div class="row-label">Investments</div>
        <div class="column-value">{{ formatCurrency(userBreakdown.investments) }}</div>
        <div v-if="hasSpouse" class="column-value">{{ formatCurrency(spouseBreakdown.investments) }}</div>
        <div v-if="hasSpouse" class="column-value total-column">{{ formatCurrency(userBreakdown.investments + (spouseBreakdown.investments || 0)) }}</div>
      </router-link>
      <router-link v-if="showAssetRow('cash')" :to="getAssetLink('cash')" class="summary-row breakdown-row clickable-row">
        <div class="row-label">Cash & Savings</div>
        <div class="column-value">{{ formatCurrency(userBreakdown.cash) }}</div>
        <div v-if="hasSpouse" class="column-value">{{ formatCurrency(spouseBreakdown.cash) }}</div>
        <div v-if="hasSpouse" class="column-value total-column">{{ formatCurrency(userBreakdown.cash + (spouseBreakdown.cash || 0)) }}</div>
      </router-link>
      <router-link v-if="showAssetRow('pensions')" :to="getAssetLink('pensions')" class="summary-row breakdown-row clickable-row">
        <div class="row-label">Pensions</div>
        <div class="column-value">{{ formatCurrency(userBreakdown.pensions) }}</div>
        <div v-if="hasSpouse" class="column-value">{{ formatCurrency(spouseBreakdown.pensions) }}</div>
        <div v-if="hasSpouse" class="column-value total-column">{{ formatCurrency(userBreakdown.pensions + (spouseBreakdown.pensions || 0)) }}</div>
      </router-link>
      <router-link v-if="showAssetRow('business')" :to="getAssetLink('business')" class="summary-row breakdown-row clickable-row">
        <div class="row-label">Business</div>
        <div class="column-value">{{ formatCurrency(userBreakdown.business) }}</div>
        <div v-if="hasSpouse" class="column-value">{{ formatCurrency(spouseBreakdown.business) }}</div>
        <div v-if="hasSpouse" class="column-value total-column">{{ formatCurrency(userBreakdown.business + (spouseBreakdown.business || 0)) }}</div>
      </router-link>
      <router-link v-if="showAssetRow('chattels')" :to="getAssetLink('chattels')" class="summary-row breakdown-row clickable-row">
        <div class="row-label">Personal Valuables</div>
        <div class="column-value">{{ formatCurrency(userBreakdown.chattels) }}</div>
        <div v-if="hasSpouse" class="column-value">{{ formatCurrency(spouseBreakdown.chattels) }}</div>
        <div v-if="hasSpouse" class="column-value total-column">{{ formatCurrency(userBreakdown.chattels + (spouseBreakdown.chattels || 0)) }}</div>
      </router-link>

      <!-- Total Assets Row -->
      <div class="summary-row total-row assets-total-row">
        <div class="row-label total-label">Total Assets</div>
        <div class="column-value total-value">{{ formatCurrency(userTotalAssets) }}</div>
        <div v-if="hasSpouse" class="column-value total-value">{{ formatCurrency(spouseTotalAssets) }}</div>
        <div v-if="hasSpouse" class="column-value total-value total-column">{{ formatCurrency(combinedTotalAssets) }}</div>
      </div>

      <!-- Liabilities Section Header -->
      <div class="summary-row section-header-row liabilities-header">
        <div class="row-label section-label">
          <svg class="section-icon text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6L9 12.75l4.286-4.286a11.948 11.948 0 014.306 6.43l.776 2.898m0 0l3.182-5.511m-3.182 5.51l-5.511-3.181" />
          </svg>
          <span>Liabilities</span>
        </div>
        <div class="column-value"></div>
        <div v-if="hasSpouse" class="column-value"></div>
        <div v-if="hasSpouse" class="column-value"></div>
      </div>

      <!-- Liability Breakdown Rows -->
      <router-link v-if="showLiabilityRow('mortgages')" :to="getLiabilityLink('mortgages')" class="summary-row breakdown-row clickable-row">
        <div class="row-label">Mortgages</div>
        <div class="column-value">{{ formatCurrency(userLiabilitiesBreakdown.mortgages) }}</div>
        <div v-if="hasSpouse" class="column-value">{{ formatCurrency(spouseLiabilitiesBreakdown.mortgages) }}</div>
        <div v-if="hasSpouse" class="column-value total-column">{{ formatCurrency(userLiabilitiesBreakdown.mortgages + (spouseLiabilitiesBreakdown.mortgages || 0)) }}</div>
      </router-link>
      <router-link v-if="showLiabilityRow('loans')" :to="getLiabilityLink('loans')" class="summary-row breakdown-row clickable-row">
        <div class="row-label">Loans</div>
        <div class="column-value">{{ formatCurrency(userLiabilitiesBreakdown.loans) }}</div>
        <div v-if="hasSpouse" class="column-value">{{ formatCurrency(spouseLiabilitiesBreakdown.loans) }}</div>
        <div v-if="hasSpouse" class="column-value total-column">{{ formatCurrency(userLiabilitiesBreakdown.loans + (spouseLiabilitiesBreakdown.loans || 0)) }}</div>
      </router-link>
      <router-link v-if="showLiabilityRow('credit_cards')" :to="getLiabilityLink('credit_cards')" class="summary-row breakdown-row clickable-row">
        <div class="row-label">Credit Cards</div>
        <div class="column-value">{{ formatCurrency(userLiabilitiesBreakdown.credit_cards) }}</div>
        <div v-if="hasSpouse" class="column-value">{{ formatCurrency(spouseLiabilitiesBreakdown.credit_cards) }}</div>
        <div v-if="hasSpouse" class="column-value total-column">{{ formatCurrency(userLiabilitiesBreakdown.credit_cards + (spouseLiabilitiesBreakdown.credit_cards || 0)) }}</div>
      </router-link>
      <router-link v-if="showLiabilityRow('other')" :to="getLiabilityLink('other')" class="summary-row breakdown-row clickable-row">
        <div class="row-label">Other</div>
        <div class="column-value">{{ formatCurrency(userLiabilitiesBreakdown.other) }}</div>
        <div v-if="hasSpouse" class="column-value">{{ formatCurrency(spouseLiabilitiesBreakdown.other) }}</div>
        <div v-if="hasSpouse" class="column-value total-column">{{ formatCurrency(userLiabilitiesBreakdown.other + (spouseLiabilitiesBreakdown.other || 0)) }}</div>
      </router-link>

      <!-- Total Liabilities Row -->
      <div class="summary-row total-row liabilities-total-row">
        <div class="row-label total-label">Total Liabilities</div>
        <div class="column-value total-value">{{ formatCurrency(userTotalLiabilities) }}</div>
        <div v-if="hasSpouse" class="column-value total-value">{{ formatCurrency(spouseTotalLiabilities) }}</div>
        <div v-if="hasSpouse" class="column-value total-value total-column">{{ formatCurrency(combinedTotalLiabilities) }}</div>
      </div>

      <!-- Net Worth Row -->
      <div class="summary-row total-row net-worth-row">
        <div class="row-label total-label net-worth-label">Net Worth</div>
        <div class="column-value total-value net-worth-value" :class="userNetWorthClass">{{ formatCurrency(userNetWorth) }}</div>
        <div v-if="hasSpouse" class="column-value total-value net-worth-value" :class="spouseNetWorthClass">{{ formatCurrency(spouseNetWorth) }}</div>
        <div v-if="hasSpouse" class="column-value total-value net-worth-value total-column" :class="combinedNetWorthClass">{{ formatCurrency(combinedNetWorth) }}</div>
      </div>
    </div>

    <div v-else class="no-data">
      <p>No wealth data available</p>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'WealthSummary',
  mixins: [currencyMixin],

  props: {
    breakdown: {
      type: Object,
      required: true,
      default: () => ({}),
    },
    liabilitiesBreakdown: {
      type: Object,
      default: () => ({}),
    },
    totalAssets: {
      type: Number,
      default: 0,
    },
    totalLiabilities: {
      type: Number,
      default: 0,
    },
    spouseData: {
      type: Object,
      default: null,
    },
    userName: {
      type: String,
      default: 'Your Wealth',
    },
    spouseName: {
      type: String,
      default: 'Spouse Wealth',
    },
  },

  computed: {
    hasData() {
      return this.totalAssets > 0 || this.totalLiabilities > 0 || (this.spouseData && (this.spouseData.totalAssets > 0 || this.spouseData.totalLiabilities > 0));
    },

    hasSpouse() {
      return this.spouseData !== null && this.spouseData !== undefined;
    },

    userBreakdown() {
      return {
        property: this.breakdown.property || 0,
        investments: this.breakdown.investments || 0,
        cash: this.breakdown.cash || 0,
        pensions: this.breakdown.pensions || 0,
        business: this.breakdown.business || 0,
        chattels: this.breakdown.chattels || 0,
      };
    },

    userLiabilitiesBreakdown() {
      return {
        mortgages: this.liabilitiesBreakdown.mortgages || 0,
        loans: this.liabilitiesBreakdown.loans || 0,
        credit_cards: this.liabilitiesBreakdown.credit_cards || 0,
        other: this.liabilitiesBreakdown.other || 0,
      };
    },

    userTotalAssets() {
      return this.totalAssets;
    },

    userTotalLiabilities() {
      return this.totalLiabilities;
    },

    userNetWorth() {
      return this.userTotalAssets - this.userTotalLiabilities;
    },

    userNetWorthClass() {
      if (this.userNetWorth < 0) {
        return 'negative';
      } else if (this.userNetWorth > 0) {
        return 'positive';
      }
      return '';
    },

    spouseBreakdown() {
      if (!this.spouseData) return {};
      return {
        property: this.spouseData.breakdown?.property || 0,
        investments: this.spouseData.breakdown?.investments || 0,
        cash: this.spouseData.breakdown?.cash || 0,
        pensions: this.spouseData.breakdown?.pensions || 0,
        business: this.spouseData.breakdown?.business || 0,
        chattels: this.spouseData.breakdown?.chattels || 0,
      };
    },

    spouseLiabilitiesBreakdown() {
      if (!this.spouseData) return {};
      return {
        mortgages: this.spouseData.liabilitiesBreakdown?.mortgages || 0,
        loans: this.spouseData.liabilitiesBreakdown?.loans || 0,
        credit_cards: this.spouseData.liabilitiesBreakdown?.credit_cards || 0,
        other: this.spouseData.liabilitiesBreakdown?.other || 0,
      };
    },

    spouseTotalAssets() {
      return this.spouseData?.totalAssets || 0;
    },

    spouseTotalLiabilities() {
      return this.spouseData?.totalLiabilities || 0;
    },

    spouseNetWorth() {
      return this.spouseTotalAssets - this.spouseTotalLiabilities;
    },

    spouseNetWorthClass() {
      if (this.spouseNetWorth < 0) {
        return 'negative';
      } else if (this.spouseNetWorth > 0) {
        return 'positive';
      }
      return '';
    },

    combinedTotalAssets() {
      return this.userTotalAssets + this.spouseTotalAssets;
    },

    combinedTotalLiabilities() {
      return this.userTotalLiabilities + this.spouseTotalLiabilities;
    },

    combinedNetWorth() {
      return this.combinedTotalAssets - this.combinedTotalLiabilities;
    },

    combinedNetWorthClass() {
      if (this.combinedNetWorth < 0) {
        return 'negative';
      } else if (this.combinedNetWorth > 0) {
        return 'positive';
      }
      return '';
    },
  },

  methods: {
    showAssetRow(key) {
      // Show row if either user or spouse has a value > 0
      const userValue = this.userBreakdown[key] || 0;
      const spouseValue = this.hasSpouse ? (this.spouseBreakdown[key] || 0) : 0;
      return userValue > 0 || spouseValue > 0;
    },

    showLiabilityRow(key) {
      // Show row if either user or spouse has a value > 0
      const userValue = this.userLiabilitiesBreakdown[key] || 0;
      const spouseValue = this.hasSpouse ? (this.spouseLiabilitiesBreakdown[key] || 0) : 0;
      return userValue > 0 || spouseValue > 0;
    },

    getAssetLink(type) {
      const isPreview = this.$route.path.startsWith('/preview');
      const basePath = isPreview ? '/preview/net-worth' : '/net-worth';

      const routes = {
        property: `${basePath}/property`,
        investments: `${basePath}/investments`,
        cash: `${basePath}/cash`,
        pensions: `${basePath}/retirement`,
        business: `${basePath}/business`,
        chattels: `${basePath}/chattels`,
      };

      return routes[type] || basePath;
    },

    getLiabilityLink(type) {
      // Mortgages link to property tab, other liabilities go to profile
      const isPreview = this.$route.path.startsWith('/preview');

      if (type === 'mortgages') {
        const basePath = isPreview ? '/preview/net-worth' : '/net-worth';
        return `${basePath}/property`;
      }

      // Other liabilities go to profile liabilities section
      return '/profile?section=liabilities';
    },
  },
};
</script>

<style scoped>
.wealth-summary {
  background: white;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  @apply border border-gray-200;
}

.chart-title {
  font-size: 18px;
  font-weight: 600;
  @apply text-gray-900;
  margin: 0 0 24px 0;
}

/* Table-like layout */
.summary-content {
  display: flex;
  flex-direction: column;
  gap: 0;
}

.summary-row {
  display: grid;
  grid-template-columns: 200px 1fr;
  gap: 16px;
  align-items: center;
}

.summary-content.has-spouse .summary-row {
  grid-template-columns: 200px 1fr 1fr 1fr;
}

/* Header row */
.header-row {
  margin-bottom: 16px;
  padding-bottom: 12px;
  @apply border-b-2 border-gray-200;
}

.column-header {
  font-size: 16px;
  font-weight: 600;
  @apply text-gray-900;
  text-align: right;
  padding-right: 16px;
}

/* Row labels */
.row-label {
  font-size: 14px;
  @apply text-gray-500;
  font-weight: 500;
}

/* Clickable row styling */
.clickable-row {
  text-decoration: none;
  cursor: pointer;
  border-radius: 8px;
  margin: 0 -8px;
  padding: 8px;
  transition: background-color 0.15s ease;
}

.clickable-row:hover {
  @apply bg-gray-100;
}

.clickable-row:hover .row-label {
  @apply text-primary-500;
}

.clickable-row:hover .column-value {
  @apply bg-gray-200;
}

/* Section header rows */
.section-header-row {
  margin-top: 20px;
  margin-bottom: 12px;
}

.section-header-row .section-label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  font-weight: 600;
  @apply text-gray-500;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.liabilities-header {
  margin-top: 24px;
}

.section-icon {
  width: 20px;
  height: 20px;
  flex-shrink: 0;
}

/* Breakdown rows */
.breakdown-row {
  padding: 0;
}

.breakdown-row.clickable-row {
  padding: 8px;
}

.breakdown-row .column-value {
  text-align: right;
  padding: 8px 16px;
  @apply bg-gray-50;
  border-radius: 6px;
  font-size: 14px;
  @apply text-gray-900;
  font-weight: 600;
  transition: background-color 0.15s ease;
}

/* Total rows - consistent sizing */
.total-row {
  margin-top: 8px;
}

.total-row .row-label.total-label {
  font-size: 14px;
  font-weight: 600;
  @apply text-gray-900;
}

.total-row .column-value.total-value {
  text-align: right;
  padding: 12px 16px;
  border-radius: 8px;
  font-size: 16px;
  font-weight: 700;
  @apply text-gray-900;
}

/* Assets total row styling */
.assets-total-row .column-value.total-value {
  @apply bg-green-100;
  @apply border border-green-500;
}

/* Liabilities total row styling */
.liabilities-total-row .column-value.total-value {
  @apply bg-red-100;
  @apply border border-red-500;
}

/* Net worth row styling */
.net-worth-row {
  margin-top: 16px;
  padding-top: 16px;
  @apply border-t-2 border-gray-200;
}

.net-worth-row .row-label.net-worth-label {
  font-size: 16px;
  font-weight: 700;
}

.net-worth-row .column-value.net-worth-value {
  background: linear-gradient(135deg, theme('colors.sky.50') 0%, white 100%);
  @apply border-2 border-primary-500;
  font-size: 20px;
}

.net-worth-value.positive {
  @apply text-green-500;
}

.net-worth-value.negative {
  @apply text-red-500;
}

.no-data {
  text-align: center;
  padding: 60px 20px;
  @apply text-gray-400;
}

.no-data p {
  margin: 0;
  font-size: 14px;
}

/* Total column styling */
.column-header.total-header {
  @apply font-bold;
}

.column-value.total-column {
  @apply bg-blue-50;
}

/* Mobile responsive */
@media (max-width: 768px) {
  .wealth-summary {
    padding: 16px;
  }

  .chart-title {
    font-size: 16px;
    margin-bottom: 16px;
  }

  .summary-row {
    grid-template-columns: 120px 1fr;
    gap: 8px;
  }

  .summary-content.has-spouse .summary-row {
    grid-template-columns: 100px 1fr 1fr 1fr;
  }

  .column-header {
    font-size: 12px;
    padding-right: 4px;
  }

  .row-label {
    font-size: 12px;
  }

  .section-header-row .section-label {
    font-size: 11px;
  }

  .section-icon {
    width: 14px;
    height: 14px;
  }

  .breakdown-row .column-value {
    padding: 4px 6px;
    font-size: 11px;
  }

  .total-row .column-value.total-value {
    padding: 8px 6px;
    font-size: 12px;
  }

  .net-worth-row .column-value.net-worth-value {
    font-size: 14px;
  }

  .net-worth-row .row-label.net-worth-label {
    font-size: 12px;
  }
}

@media (max-width: 480px) {
  .summary-row {
    grid-template-columns: 80px 1fr;
  }

  .summary-content.has-spouse .summary-row {
    grid-template-columns: 80px 1fr 1fr 1fr;
  }

  .column-header {
    font-size: 10px;
  }

  .breakdown-row .column-value {
    padding: 3px 4px;
    font-size: 10px;
  }
}
</style>
