<template>
  <div class="account-holdings-panel">
    <!-- Header with Add Button -->
    <div class="panel-header">
      <h3 class="panel-title">Holdings in {{ account.account_name }}</h3>
      <button v-preview-disabled="'add'" @click="$emit('open-holding-modal')" class="add-holding-btn">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="btn-icon">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
        </svg>
        Add Holding
      </button>
    </div>

    <!-- Info Banner for Default Holding Period -->
    <div v-if="hasHoldings && holdingsWithoutDates > 0" class="default-period-banner">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="banner-icon">
        <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
      </svg>
      <div class="banner-content">
        <p class="banner-title">{{ holdingsWithoutDates }} holding{{ holdingsWithoutDates > 1 ? 's' : '' }} without purchase date</p>
        <p class="banner-text">Annualised returns use a <strong>3-year default</strong> holding period. Add purchase dates for more accurate return calculations.</p>
      </div>
    </div>

    <!-- Holdings Table -->
    <div v-if="hasHoldings" class="holdings-table-container">
      <table class="holdings-table">
        <thead>
          <tr>
            <th class="th-name">Name</th>
            <th class="th-type">Type</th>
            <th class="th-units">Units</th>
            <th class="th-date">Purchase Date</th>
            <th class="th-cost">Initial Unit Cost</th>
            <th class="th-price">Current Unit Price</th>
            <th class="th-initial-value">Initial Value</th>
            <th class="th-value">Current Value</th>
            <th class="th-initial-allocation">Initial Alloc</th>
            <th class="th-allocation">Current Alloc</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="holding in sortedHoldings" :key="holding.id" class="holding-row">
            <td class="td-name">
              <div class="holding-info">
                <span class="holding-name">{{ holding.security_name }}</span>
                <span v-if="holding.ticker" class="holding-ticker">{{ holding.ticker }}</span>
                <span v-if="holding.isin" class="holding-isin">{{ holding.isin }}</span>
              </div>
            </td>
            <td class="td-type">
              <span
                class="type-badge"
                :class="getAssetTypeBadgeClass(holding.asset_type)"
              >
                {{ formatAssetType(holding.asset_type) }}
              </span>
            </td>
            <td class="td-units">{{ formatNumber(holding.quantity) }}</td>
            <td class="td-date">
              <span v-if="holding.purchase_date" class="date-text">{{ formatDate(holding.purchase_date) }}</span>
              <span v-else class="date-default" title="Default: 3 years assumed">3yr default</span>
            </td>
            <td class="td-cost">{{ formatCurrencyWithPence(holding.purchase_price) }}</td>
            <td class="td-price">{{ formatCurrencyWithPence(holding.current_price) }}</td>
            <td class="td-initial-value">{{ formatCurrency(getInitialValue(holding)) }}</td>
            <td class="td-value">{{ formatCurrency(holding.current_value) }}</td>
            <td class="td-initial-allocation">
              <span class="allocation-text">{{ initialAllocations.get(holding.id) }}%</span>
            </td>
            <td class="td-allocation">
              <span class="allocation-text">{{ currentAllocations.get(holding.id) }}%</span>
            </td>
          </tr>
        </tbody>
        <tfoot>
          <tr class="totals-row">
            <td colspan="6" class="totals-label">Total</td>
            <td class="totals-initial-value">{{ formatCurrency(totalInitialValue) }}</td>
            <td class="totals-value">{{ formatCurrency(totalValue) }}</td>
            <td class="totals-initial-allocation">100%</td>
            <td class="totals-allocation">100%</td>
          </tr>
        </tfoot>
      </table>
    </div>

    <!-- Mobile Cards View -->
    <div v-if="hasHoldings" class="holdings-cards-mobile">
      <div v-for="holding in sortedHoldings" :key="holding.id" class="holding-card">
        <div class="card-header">
          <div class="holding-info">
            <span class="holding-name">{{ holding.security_name }}</span>
            <span v-if="holding.ticker" class="holding-ticker">{{ holding.ticker }}</span>
          </div>
          <span
            class="type-badge"
            :class="getAssetTypeBadgeClass(holding.asset_type)"
          >
            {{ formatAssetType(holding.asset_type) }}
          </span>
        </div>
        <div class="card-details">
          <div class="detail-row">
            <span class="detail-label">Units</span>
            <span class="detail-value">{{ formatNumber(holding.quantity) }}</span>
          </div>
          <div class="detail-row">
            <span class="detail-label">Initial Unit Cost</span>
            <span class="detail-value">{{ formatCurrencyWithPence(holding.purchase_price) }}</span>
          </div>
          <div class="detail-row">
            <span class="detail-label">Current Unit Price</span>
            <span class="detail-value">{{ formatCurrencyWithPence(holding.current_price) }}</span>
          </div>
          <div class="detail-row">
            <span class="detail-label">Initial Value</span>
            <span class="detail-value">{{ formatCurrency(getInitialValue(holding)) }}</span>
          </div>
          <div class="detail-row">
            <span class="detail-label">Current Value</span>
            <span class="detail-value font-bold">{{ formatCurrency(holding.current_value) }}</span>
          </div>
          <div class="detail-row">
            <span class="detail-label">Initial Allocation</span>
            <span class="detail-value">{{ initialAllocations.get(holding.id) }}%</span>
          </div>
          <div class="detail-row">
            <span class="detail-label">Current Allocation</span>
            <span class="detail-value">{{ currentAllocations.get(holding.id) }}%</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="empty-state">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="empty-icon">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z" />
      </svg>
      <p class="empty-title">No holdings yet</p>
      <p class="empty-subtitle">Add your first holding to track your investments</p>
      <button v-preview-disabled="'add'" @click="$emit('open-holding-modal')" class="add-first-btn">
        Add First Holding
      </button>
    </div>

    <!-- Asset Allocation Summary -->
    <div v-if="hasHoldings" class="allocation-summary">
      <h4 class="summary-title">Asset Allocation Summary</h4>
      <div class="allocation-grid">
        <div
          v-for="(allocation, index) in assetAllocationSummary"
          :key="index"
          class="allocation-item"
        >
          <div class="allocation-header">
            <span
              class="allocation-dot"
              :style="{ backgroundColor: getAssetColor(allocation.type) }"
            ></span>
            <span class="allocation-type">{{ formatAssetType(allocation.type) }}</span>
          </div>
          <div class="allocation-values">
            <span class="allocation-amount">{{ formatCurrency(allocation.value) }}</span>
            <span class="allocation-percent">{{ allocation.percentage.toFixed(1) }}%</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';
import { PRIMARY_COLORS, SUCCESS_COLORS, WARNING_COLORS, SECONDARY_COLORS, CHART_COLORS, ASSET_COLORS } from '@/constants/designSystem';

export default {
  name: 'AccountHoldingsPanel',

  mixins: [currencyMixin],

  props: {
    account: {
      type: Object,
      required: true,
    },
  },

  emits: ['open-holding-modal'],

  computed: {
    holdings() {
      return this.account.holdings || [];
    },

    hasHoldings() {
      return this.holdings.length > 0;
    },

    holdingsWithoutDates() {
      return this.holdings.filter(h => !h.purchase_date).length;
    },

    sortedHoldings() {
      return [...this.holdings].sort((a, b) => {
        return (b.current_value || 0) - (a.current_value || 0);
      });
    },

    totalValue() {
      return this.holdings.reduce((sum, holding) => {
        return sum + (parseFloat(holding.current_value) || 0);
      }, 0);
    },

    totalInitialValue() {
      return this.holdings.reduce((sum, holding) => {
        return sum + this.getInitialValue(holding);
      }, 0);
    },

    assetAllocationSummary() {
      const allocation = {};

      this.holdings.forEach(holding => {
        const value = parseFloat(holding.current_value || 0);
        const assetType = holding.asset_type || 'other';

        if (!allocation[assetType]) {
          allocation[assetType] = 0;
        }
        allocation[assetType] += value;
      });

      return Object.entries(allocation)
        .map(([type, value]) => ({
          type,
          value,
          percentage: this.totalValue > 0 ? (value / this.totalValue) * 100 : 0,
        }))
        .sort((a, b) => b.percentage - a.percentage);
    },

    // Adjusted allocations to ensure they sum to exactly 100%
    currentAllocations() {
      return this.calculateAdjustedAllocations(
        this.sortedHoldings,
        (h) => parseFloat(h.current_value) || 0,
        this.totalValue
      );
    },

    initialAllocations() {
      return this.calculateAdjustedAllocations(
        this.sortedHoldings,
        (h) => this.getInitialValue(h),
        this.totalInitialValue
      );
    },
  },

  methods: {
    calculateAdjustedAllocations(holdings, getValueFn, total) {
      if (total === 0 || holdings.length === 0) {
        return new Map(holdings.map(h => [h.id, 0]));
      }

      // Calculate raw percentages and round to 1 decimal
      const allocations = holdings.map(h => ({
        id: h.id,
        raw: (getValueFn(h) / total) * 100,
        rounded: Math.round((getValueFn(h) / total) * 1000) / 10
      }));

      // Calculate sum of rounded values
      const roundedSum = allocations.reduce((sum, a) => sum + a.rounded, 0);
      const difference = Math.round((100 - roundedSum) * 10) / 10;

      // Adjust the first (largest) item to make total exactly 100%
      if (allocations.length > 0 && difference !== 0) {
        allocations[0].rounded = Math.round((allocations[0].rounded + difference) * 10) / 10;
      }

      return new Map(allocations.map(a => [a.id, a.rounded]));
    },

    formatNumber(value) {
      if (!value) return '0';
      return new Intl.NumberFormat('en-GB', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
      }).format(Math.round(value));
    },

    formatAssetType(type) {
      const types = {
        equity: 'Equity',
        fixed_income: 'Fixed Income',
        property: 'Property',
        commodities: 'Commodities',
        cash: 'Cash',
        alternatives: 'Alternatives',
        other: 'Other',
      };
      return types[type] || type?.charAt(0).toUpperCase() + type?.slice(1) || 'Other';
    },

    getAssetTypeBadgeClass(type) {
      const classes = {
        equity: 'bg-violet-100 text-violet-800',
        fixed_income: 'bg-spring-100 text-spring-800',
        property: 'bg-violet-100 text-violet-800',
        commodities: 'bg-violet-100 text-violet-800',
        cash: 'bg-savannah-100 text-horizon-500',
        alternatives: 'bg-pink-100 text-pink-800',
        other: 'bg-slate-100 text-slate-800',
      };
      return classes[type] || 'bg-slate-100 text-slate-800';
    },

    getAssetColor(type) {
      return ASSET_COLORS[type] || ASSET_COLORS.other;
    },

    getInitialValue(holding) {
      const quantity = parseFloat(holding.quantity) || 0;
      const purchasePrice = parseFloat(holding.purchase_price) || 0;
      return quantity * purchasePrice;
    },

    formatDate(dateString) {
      if (!dateString) return '';
      const date = new Date(dateString);
      return date.toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
      });
    },
  },
};
</script>

<style scoped>
.account-holdings-panel {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.panel-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 16px;
}

.panel-title {
  font-size: 18px;
  font-weight: 600;
  @apply text-horizon-500;
  margin: 0;
}

.add-holding-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  @apply bg-raspberry-500;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}

.add-holding-btn:hover {
  @apply bg-raspberry-500;
}

.btn-icon {
  width: 20px;
  height: 20px;
}

/* Default Period Banner */
.default-period-banner {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 12px 16px;
  @apply bg-violet-50 border border-violet-500;
  border-radius: 8px;
}

.banner-icon {
  width: 20px;
  height: 20px;
  @apply text-violet-600;
  flex-shrink: 0;
  margin-top: 2px;
}

.banner-content {
  flex: 1;
}

.banner-title {
  font-size: 14px;
  font-weight: 600;
  @apply text-violet-800;
  margin: 0 0 4px 0;
}

.banner-text {
  font-size: 13px;
  @apply text-violet-700;
  margin: 0;
}

/* Holdings Table */
.holdings-table-container {
  overflow-x: auto;
  @apply border border-light-gray;
  border-radius: 12px;
}

.holdings-table {
  width: 100%;
  border-collapse: collapse;
}

.holdings-table th,
.holdings-table td {
  padding: 12px 16px;
  text-align: left;
}

.holdings-table th {
  @apply bg-eggshell-500 text-neutral-500 border-b border-light-gray;
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.holding-row {
  @apply border-b border-light-gray;
}

.holding-row:last-child {
  border-bottom: none;
}

.holding-row:hover {
  @apply bg-eggshell-500;
}

.holding-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.holding-name {
  font-weight: 600;
  @apply text-horizon-500;
}

.holding-ticker {
  font-size: 12px;
  @apply text-raspberry-500;
  font-weight: 500;
}

.holding-isin {
  font-size: 11px;
  @apply text-horizon-400;
}

.type-badge {
  display: inline-block;
  padding: 4px 8px;
  font-size: 11px;
  font-weight: 600;
  border-radius: 4px;
}

.td-units,
.td-cost,
.td-price,
.td-initial-value,
.td-value {
  font-variant-numeric: tabular-nums;
}

.td-date {
  white-space: nowrap;
}

.date-text {
  font-size: 13px;
  @apply text-neutral-500;
}

.date-default {
  font-size: 12px;
  @apply text-violet-700 bg-violet-50;
  padding: 2px 6px;
  border-radius: 4px;
  cursor: help;
}

.td-initial-value {
  @apply text-neutral-500;
}

.td-value {
  font-weight: 600;
  @apply text-horizon-500;
}

.td-initial-allocation {
  @apply text-neutral-500;
}

.totals-initial-value {
  @apply text-neutral-500;
}

.totals-initial-allocation {
  @apply text-neutral-500;
}

.allocation-text {
  font-size: 13px;
  @apply text-neutral-500;
}

.totals-row {
  @apply bg-eggshell-500;
  font-weight: 600;
}

.totals-label {
  text-align: right;
  @apply text-neutral-500;
}

.totals-value {
  @apply text-horizon-500;
  font-size: 16px;
}

.totals-allocation {
  @apply text-neutral-500;
}

/* Mobile Cards (hidden on desktop) */
.holdings-cards-mobile {
  display: none;
}

/* Empty State */
.empty-state {
  text-align: center;
  padding: 60px 20px;
  @apply bg-white border-2 border-dashed border-horizon-300;
  border-radius: 12px;
}

.empty-icon {
  width: 48px;
  height: 48px;
  @apply text-horizon-400;
  margin: 0 auto 16px;
}

.empty-title {
  font-size: 18px;
  font-weight: 600;
  @apply text-neutral-500;
  margin: 0 0 8px 0;
}

.empty-subtitle {
  font-size: 14px;
  @apply text-neutral-500;
  margin: 0 0 20px 0;
}

.add-first-btn {
  padding: 12px 24px;
  @apply bg-raspberry-500;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}

.add-first-btn:hover {
  @apply bg-raspberry-500;
}

/* Allocation Summary */
.allocation-summary {
  @apply bg-white border border-light-gray;
  border-radius: 12px;
  padding: 20px;
}

.summary-title {
  font-size: 16px;
  font-weight: 600;
  @apply text-horizon-500;
  margin: 0 0 16px 0;
}

.allocation-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 12px;
}

.allocation-item {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 12px;
  @apply bg-eggshell-500;
  border-radius: 8px;
}

.allocation-header {
  display: flex;
  align-items: center;
  gap: 8px;
}

.allocation-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
}

.allocation-type {
  font-size: 14px;
  @apply text-neutral-500;
}

.allocation-values {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.allocation-amount {
  font-size: 16px;
  font-weight: 600;
  @apply text-horizon-500;
}

.allocation-percent {
  font-size: 14px;
  @apply text-neutral-500;
}

@media (max-width: 768px) {
  .holdings-table-container {
    display: none;
  }

  .holdings-cards-mobile {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }

  .holding-card {
    background: white;
    @apply border border-light-gray;
    border-radius: 12px;
    padding: 16px;
  }

  .card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
  }

  .card-details {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 8px;
  }

  .detail-row {
    display: flex;
    flex-direction: column;
    gap: 2px;
  }

  .detail-label {
    font-size: 12px;
    @apply text-neutral-500;
  }

  .detail-value {
    font-size: 14px;
    @apply text-horizon-500;
  }

  .panel-header {
    flex-direction: column;
    align-items: flex-start;
  }

  .add-holding-btn {
    width: 100%;
    justify-content: center;
  }
}
</style>
