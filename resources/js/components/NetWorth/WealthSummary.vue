<template>
  <div class="wealth-summary">
    <h3 class="chart-title">Wealth Summary</h3>

    <div v-if="hasData" class="summary-content" :class="{ 'has-spouse': hasSpouse }">
      <!-- Column Headers -->
      <div class="summary-row header-row">
        <div class="row-label"></div>
        <div class="column-header">{{ userName }}</div>
        <div v-if="hasSpouse" class="column-header">{{ spouseName }}</div>
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
      </div>

      <!-- Asset Breakdown Rows -->
      <div v-if="showAssetRow('property')" class="summary-row breakdown-row">
        <div class="row-label">Property</div>
        <div class="column-value">{{ formatCurrency(userBreakdown.property) }}</div>
        <div v-if="hasSpouse" class="column-value">{{ formatCurrency(spouseBreakdown.property) }}</div>
      </div>
      <div v-if="showAssetRow('investments')" class="summary-row breakdown-row">
        <div class="row-label">Investments</div>
        <div class="column-value">{{ formatCurrency(userBreakdown.investments) }}</div>
        <div v-if="hasSpouse" class="column-value">{{ formatCurrency(spouseBreakdown.investments) }}</div>
      </div>
      <div v-if="showAssetRow('cash')" class="summary-row breakdown-row">
        <div class="row-label">Cash & Savings</div>
        <div class="column-value">{{ formatCurrency(userBreakdown.cash) }}</div>
        <div v-if="hasSpouse" class="column-value">{{ formatCurrency(spouseBreakdown.cash) }}</div>
      </div>
      <div v-if="showAssetRow('pensions')" class="summary-row breakdown-row">
        <div class="row-label">Pensions</div>
        <div class="column-value">{{ formatCurrency(userBreakdown.pensions) }}</div>
        <div v-if="hasSpouse" class="column-value">{{ formatCurrency(spouseBreakdown.pensions) }}</div>
      </div>
      <div v-if="showAssetRow('business')" class="summary-row breakdown-row">
        <div class="row-label">Business</div>
        <div class="column-value">{{ formatCurrency(userBreakdown.business) }}</div>
        <div v-if="hasSpouse" class="column-value">{{ formatCurrency(spouseBreakdown.business) }}</div>
      </div>
      <div v-if="showAssetRow('chattels')" class="summary-row breakdown-row">
        <div class="row-label">Chattels</div>
        <div class="column-value">{{ formatCurrency(userBreakdown.chattels) }}</div>
        <div v-if="hasSpouse" class="column-value">{{ formatCurrency(spouseBreakdown.chattels) }}</div>
      </div>

      <!-- Total Assets Row -->
      <div class="summary-row total-row assets-total-row">
        <div class="row-label total-label">Total Assets</div>
        <div class="column-value total-value">{{ formatCurrency(userTotalAssets) }}</div>
        <div v-if="hasSpouse" class="column-value total-value">{{ formatCurrency(spouseTotalAssets) }}</div>
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
      </div>

      <!-- Liability Breakdown Rows -->
      <div v-if="showLiabilityRow('mortgages')" class="summary-row breakdown-row">
        <div class="row-label">Mortgages</div>
        <div class="column-value">{{ formatCurrency(userLiabilitiesBreakdown.mortgages) }}</div>
        <div v-if="hasSpouse" class="column-value">{{ formatCurrency(spouseLiabilitiesBreakdown.mortgages) }}</div>
      </div>
      <div v-if="showLiabilityRow('loans')" class="summary-row breakdown-row">
        <div class="row-label">Loans</div>
        <div class="column-value">{{ formatCurrency(userLiabilitiesBreakdown.loans) }}</div>
        <div v-if="hasSpouse" class="column-value">{{ formatCurrency(spouseLiabilitiesBreakdown.loans) }}</div>
      </div>
      <div v-if="showLiabilityRow('credit_cards')" class="summary-row breakdown-row">
        <div class="row-label">Credit Cards</div>
        <div class="column-value">{{ formatCurrency(userLiabilitiesBreakdown.credit_cards) }}</div>
        <div v-if="hasSpouse" class="column-value">{{ formatCurrency(spouseLiabilitiesBreakdown.credit_cards) }}</div>
      </div>
      <div v-if="showLiabilityRow('other')" class="summary-row breakdown-row">
        <div class="row-label">Other</div>
        <div class="column-value">{{ formatCurrency(userLiabilitiesBreakdown.other) }}</div>
        <div v-if="hasSpouse" class="column-value">{{ formatCurrency(spouseLiabilitiesBreakdown.other) }}</div>
      </div>

      <!-- Total Liabilities Row -->
      <div class="summary-row total-row liabilities-total-row">
        <div class="row-label total-label">Total Liabilities</div>
        <div class="column-value total-value">{{ formatCurrency(userTotalLiabilities) }}</div>
        <div v-if="hasSpouse" class="column-value total-value">{{ formatCurrency(spouseTotalLiabilities) }}</div>
      </div>

      <!-- Net Worth Row -->
      <div class="summary-row total-row net-worth-row">
        <div class="row-label total-label net-worth-label">Net Worth</div>
        <div class="column-value total-value net-worth-value" :class="userNetWorthClass">{{ formatCurrency(userNetWorth) }}</div>
        <div v-if="hasSpouse" class="column-value total-value net-worth-value" :class="spouseNetWorthClass">{{ formatCurrency(spouseNetWorth) }}</div>
      </div>
    </div>

    <div v-else class="no-data">
      <p>No wealth data available</p>
    </div>
  </div>
</template>

<script>
export default {
  name: 'WealthSummary',

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
  },

  methods: {
    formatCurrency(value) {
      return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: 'GBP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
      }).format(value);
    },

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
  },
};
</script>

<style scoped>
.wealth-summary {
  background: white;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  border: 1px solid #e5e7eb;
}

.chart-title {
  font-size: 18px;
  font-weight: 600;
  color: #111827;
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
  grid-template-columns: 200px 1fr 1fr;
}

/* Header row */
.header-row {
  margin-bottom: 16px;
  padding-bottom: 12px;
  border-bottom: 2px solid #e5e7eb;
}

.column-header {
  font-size: 16px;
  font-weight: 600;
  color: #111827;
  text-align: right;
  padding-right: 16px;
}

/* Row labels */
.row-label {
  font-size: 14px;
  color: #6b7280;
  font-weight: 500;
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
  color: #6b7280;
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
  padding: 8px 0;
}

.breakdown-row .column-value {
  text-align: right;
  padding: 8px 16px;
  background: #f9fafb;
  border-radius: 6px;
  font-size: 14px;
  color: #111827;
  font-weight: 600;
}

/* Total rows - consistent sizing */
.total-row {
  margin-top: 8px;
}

.total-row .row-label.total-label {
  font-size: 14px;
  font-weight: 600;
  color: #111827;
}

.total-row .column-value.total-value {
  text-align: right;
  padding: 12px 16px;
  border-radius: 8px;
  font-size: 16px;
  font-weight: 700;
  color: #111827;
}

/* Assets total row styling */
.assets-total-row .column-value.total-value {
  background: #d1fae5;
  border: 1px solid #10b981;
}

/* Liabilities total row styling */
.liabilities-total-row .column-value.total-value {
  background: #fee2e2;
  border: 1px solid #ef4444;
}

/* Net worth row styling */
.net-worth-row {
  margin-top: 16px;
  padding-top: 16px;
  border-top: 2px solid #e5e7eb;
}

.net-worth-row .row-label.net-worth-label {
  font-size: 16px;
  font-weight: 700;
}

.net-worth-row .column-value.net-worth-value {
  background: linear-gradient(135deg, #f0f9ff 0%, #ffffff 100%);
  border: 2px solid #3b82f6;
  font-size: 20px;
}

.net-worth-value.positive {
  color: #10b981;
}

.net-worth-value.negative {
  color: #ef4444;
}

.no-data {
  text-align: center;
  padding: 60px 20px;
  color: #9ca3af;
}

.no-data p {
  margin: 0;
  font-size: 14px;
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
    grid-template-columns: 120px 1fr 1fr;
  }

  .column-header {
    font-size: 14px;
    padding-right: 8px;
  }

  .row-label {
    font-size: 13px;
  }

  .section-header-row .section-label {
    font-size: 12px;
  }

  .section-icon {
    width: 16px;
    height: 16px;
  }

  .breakdown-row .column-value {
    padding: 6px 10px;
    font-size: 13px;
  }

  .total-row .column-value.total-value {
    padding: 10px 12px;
    font-size: 14px;
  }

  .net-worth-row .column-value.net-worth-value {
    font-size: 16px;
  }

  .net-worth-row .row-label.net-worth-label {
    font-size: 14px;
  }
}

@media (max-width: 480px) {
  .summary-row {
    grid-template-columns: 100px 1fr;
  }

  .summary-content.has-spouse .summary-row {
    grid-template-columns: 100px 1fr 1fr;
  }
}
</style>
