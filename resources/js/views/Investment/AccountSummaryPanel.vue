<template>
  <div class="account-details-panel">
    <!-- Basic Information -->
    <div class="details-section">
      <h3 class="section-title">Basic Information</h3>
      <div class="details-grid">
        <div class="detail-item">
          <span class="detail-label">Account Name</span>
          <span class="detail-value">{{ account.account_name || 'Not specified' }}</span>
        </div>
        <div class="detail-item">
          <span class="detail-label">Provider</span>
          <span class="detail-value">{{ account.provider || 'Not specified' }}</span>
        </div>
        <div class="detail-item">
          <span class="detail-label">Account Type</span>
          <span class="detail-value">{{ formatAccountType(account.account_type) }}</span>
        </div>
        <div class="detail-item">
          <span class="detail-label">Ownership</span>
          <span class="detail-value">{{ formatOwnershipType(account.ownership_type) }}</span>
        </div>
        <div class="detail-item">
          <span class="detail-label">Platform Fee</span>
          <span class="detail-value">{{ formatPercentage(account.platform_fee) }}</span>
        </div>
        <div class="detail-item">
          <span class="detail-label">Holdings</span>
          <span class="detail-value">{{ holdingsCount }}</span>
        </div>
      </div>
    </div>

    <!-- Value Information -->
    <div class="details-section">
      <h3 class="section-title">Value Information</h3>
      <div class="details-grid">
        <div class="detail-item">
          <span class="detail-label">Current Value</span>
          <span class="detail-value highlight">{{ formatCurrency(displayValue) }}</span>
        </div>
        <div v-if="account.ownership_type === 'joint'" class="detail-item">
          <span class="detail-label">Your Share ({{ account.ownership_percentage || 50 }}%)</span>
          <span class="detail-value">{{ formatCurrency(account.current_value * ((account.ownership_percentage || 50) / 100)) }}</span>
        </div>
        <div class="detail-item">
          <span class="detail-label">YTD Return</span>
          <span v-if="hasHoldings && account.ytd_return !== null && account.ytd_return !== undefined" class="detail-value" :class="returnColorClass">{{ formatReturn(account.ytd_return) }}</span>
          <span v-else class="detail-value text-blue-600 cursor-pointer hover:underline" @click="$emit('add-holding')">Enter Holdings</span>
        </div>
        <div class="detail-item">
          <span class="detail-label">Valuation Date</span>
          <span class="detail-value">{{ formatDate(account.updated_at) }}</span>
        </div>
      </div>
    </div>

    <!-- ISA Information (if ISA account) -->
    <div v-if="account.account_type === 'isa'" class="details-section">
      <h3 class="section-title">ISA Allowance</h3>
      <div class="details-grid">
        <div class="detail-item">
          <span class="detail-label">Contributions (Current Tax Year)</span>
          <span class="detail-value highlight">{{ formatCurrency(isaContributions) }}</span>
        </div>
        <div class="detail-item">
          <span class="detail-label">Allowance Remaining</span>
          <span class="detail-value" :class="isaRemainingClass">{{ formatCurrency(isaRemaining) }}</span>
        </div>
        <div class="detail-item">
          <span class="detail-label">Annual Allowance</span>
          <span class="detail-value">{{ formatCurrency(20000) }}</span>
        </div>
        <div class="detail-item">
          <span class="detail-label">Tax Year</span>
          <span class="detail-value">{{ currentTaxYear }}</span>
        </div>
      </div>
    </div>

    <!-- Joint Ownership (if joint account) -->
    <div v-if="account.ownership_type === 'joint'" class="details-section">
      <h3 class="section-title">Joint Ownership</h3>
      <div class="details-grid">
        <div class="detail-item">
          <span class="detail-label">Full Account Value</span>
          <span class="detail-value highlight">{{ formatCurrency(account.current_value) }}</span>
        </div>
        <div class="detail-item">
          <span class="detail-label">Your Share</span>
          <span class="detail-value">{{ account.ownership_percentage || 50 }}%</span>
        </div>
        <div v-if="account.joint_owner_name" class="detail-item">
          <span class="detail-label">Joint Owner</span>
          <span class="detail-value">{{ account.joint_owner_name }}</span>
        </div>
      </div>
    </div>

    <!-- Asset Allocation -->
    <div class="details-section">
      <h3 class="section-title">Asset Allocation</h3>
      <div v-if="hasHoldings" class="details-grid">
        <div class="detail-item">
          <span class="detail-label">Primary Asset Class</span>
          <span class="detail-value">{{ primaryAssetClass.label }} {{ primaryAssetClass.percentage }}</span>
        </div>
        <div v-for="(allocation, index) in assetAllocation" :key="index" class="detail-item">
          <span class="detail-label">{{ formatAssetType(allocation.type) }}</span>
          <span class="detail-value">{{ allocation.percentage.toFixed(1) }}%</span>
        </div>
      </div>
      <div v-else class="no-holdings">
        <p>No holdings in this account yet.</p>
      </div>
    </div>

    <!-- Notes -->
    <div v-if="account.notes" class="details-section">
      <h3 class="section-title">Notes</h3>
      <p class="notes-text">{{ account.notes }}</p>
    </div>
  </div>
</template>

<script>
import { TAX_CONFIG } from '@/constants/taxConfig';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'AccountSummaryPanel',
  mixins: [currencyMixin],

  props: {
    account: {
      type: Object,
      required: true,
    },
  },

  computed: {
    displayValue() {
      // current_value IS the full value (single-record pattern)
      return this.account.current_value;
    },

    holdingsCount() {
      return this.account.holdings?.length || 0;
    },

    hasHoldings() {
      return this.holdingsCount > 0;
    },

    isaContributions() {
      return this.account.isa_subscription_current_year || 0;
    },

    isaRemaining() {
      return Math.max(0, TAX_CONFIG.ISA_ANNUAL_ALLOWANCE - this.isaContributions);
    },

    isaRemainingClass() {
      if (this.isaRemaining <= 0) return 'text-red-600';
      if (this.isaRemaining < 5000) return 'text-blue-600';
      return 'text-green-600';
    },

    currentTaxYear() {
      const now = new Date();
      const year = now.getFullYear();
      const month = now.getMonth();
      // Tax year starts April 6
      if (month < 3 || (month === 3 && now.getDate() < 6)) {
        return `${year - 1}/${year.toString().slice(-2)}`;
      }
      return `${year}/${(year + 1).toString().slice(-2)}`;
    },

    primaryAssetClass() {
      if (!this.hasHoldings) {
        return { label: 'Cash', percentage: '(100%)' };
      }

      const allocation = {};
      let totalValue = 0;

      this.account.holdings.forEach(holding => {
        const value = parseFloat(holding.current_value || 0);
        const assetType = holding.asset_type || 'other';

        if (!allocation[assetType]) {
          allocation[assetType] = 0;
        }
        allocation[assetType] += value;
        totalValue += value;
      });

      let primaryAsset = 'cash';
      let primaryValue = 0;

      Object.entries(allocation).forEach(([assetType, value]) => {
        if (value > primaryValue) {
          primaryValue = value;
          primaryAsset = assetType;
        }
      });

      const percentage = totalValue > 0
        ? ((primaryValue / totalValue) * 100).toFixed(0)
        : 100;

      return {
        label: this.formatAssetType(primaryAsset),
        percentage: `(${percentage}%)`,
      };
    },

    assetAllocation() {
      if (!this.hasHoldings) return [];

      const allocation = {};
      let totalValue = 0;

      this.account.holdings.forEach(holding => {
        const value = parseFloat(holding.current_value || 0);
        const assetType = holding.asset_type || 'other';

        if (!allocation[assetType]) {
          allocation[assetType] = 0;
        }
        allocation[assetType] += value;
        totalValue += value;
      });

      return Object.entries(allocation)
        .map(([type, value]) => ({
          type,
          value,
          percentage: totalValue > 0 ? (value / totalValue) * 100 : 0,
        }))
        .sort((a, b) => b.percentage - a.percentage);
    },

    returnColorClass() {
      if (this.account.ytd_return >= 0) return 'text-green-600';
      return 'text-red-600';
    },
  },

  methods: {
    formatPercentage(value) {
      if (value === null || value === undefined) return 'N/A';
      return `${parseFloat(value).toFixed(2)}%`;
    },

    formatReturn(value) {
      if (value === null || value === undefined) return 'N/A';
      const sign = value >= 0 ? '+' : '';
      return `${sign}${parseFloat(value).toFixed(2)}%`;
    },

    formatDate(date) {
      if (!date) return 'Not specified';
      return new Date(date).toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
      });
    },

    formatAccountType(type) {
      const types = {
        isa: 'ISA',
        sipp: 'SIPP',
        gia: 'GIA',
        pension: 'Pension',
        nsi: 'NS&I',
        onshore_bond: 'Onshore Bond',
        offshore_bond: 'Offshore Bond',
        vct: 'VCT',
        eis: 'EIS',
        other: 'Other',
      };
      return types[type] || type;
    },

    formatOwnershipType(type) {
      const types = {
        individual: 'Individual',
        joint: 'Joint',
        trust: 'Trust',
      };
      return types[type] || 'Individual';
    },

    formatAssetType(type) {
      const types = {
        equity: 'Equity',
        uk_equity: 'UK Equity',
        us_equity: 'US Equity',
        international_equity: 'International Equity',
        fixed_income: 'Fixed Income',
        bond: 'Bond',
        property: 'Property',
        commodities: 'Commodities',
        cash: 'Cash',
        alternatives: 'Alternatives',
        alternative: 'Alternative',
        fund: 'Fund',
        etf: 'ETF',
        other: 'Other',
      };
      return types[type] || type.charAt(0).toUpperCase() + type.slice(1).replace(/_/g, ' ');
    },
  },
};
</script>

<style scoped>
.account-details-panel {
  animation: fadeIn 0.3s ease-out;
  display: flex;
  flex-direction: column;
  gap: 24px;
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

.details-section {
  background: white;
  border-radius: 12px;
  border: 1px solid #e5e7eb;
  padding: 24px;
}

.section-title {
  font-size: 18px;
  font-weight: 600;
  color: #111827;
  margin-bottom: 20px;
  padding-bottom: 12px;
  border-bottom: 1px solid #e5e7eb;
}

.details-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
  gap: 20px;
}

.detail-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.detail-label {
  font-size: 14px;
  font-weight: 500;
  color: #6b7280;
}

.detail-value {
  font-size: 16px;
  font-weight: 600;
  color: #111827;
}

.detail-value.highlight {
  font-size: 20px;
  color: #059669;
}

.no-holdings {
  text-align: center;
  padding: 24px;
  color: #6b7280;
}

.notes-text {
  font-size: 14px;
  color: #4b5563;
  line-height: 1.6;
  white-space: pre-wrap;
}

@media (max-width: 768px) {
  .details-grid {
    grid-template-columns: 1fr;
  }
}
</style>
