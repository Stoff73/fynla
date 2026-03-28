<template>
  <div class="portfolio-overview">
    <!-- Investment Accounts -->
    <div class="account-overview mb-8">
      <div class="section-header-row">
        <h3 class="section-title">Investment Accounts</h3>
      </div>

      <!-- Empty State -->
      <div v-if="!loading && accounts.length === 0" class="empty-state">
        <p class="empty-message">No investment accounts added yet.</p>
        <button v-preview-disabled="'add'" @click="addAccount" class="add-account-button">
          Add Your First Account
        </button>
      </div>

      <!-- Accounts Grid -->
      <div v-else-if="!loading && accounts.length > 0" class="accounts-grid">
        <div
          v-for="account in accounts"
          :key="account.id"
          @click="viewAccount(account.id)"
          class="account-card"
        >
          <div class="card-header">
            <span
              :class="getOwnershipBadgeClass(account.ownership_type)"
              class="ownership-badge"
            >
              {{ formatOwnershipType(account.ownership_type) }}
            </span>
            <span
              class="badge"
              :class="accountTypeBadgeClass(account.account_type)"
            >
              {{ formatAccountType(account.account_type) }}
            </span>
          </div>

          <div class="card-content">
            <h4 class="account-institution">{{ account.provider }}</h4>
            <p class="account-type">{{ account.account_name }}</p>

            <div class="account-details">
              <!-- Joint account: DB stores FULL value, calculate user's share -->
              <div v-if="account.ownership_type === 'joint'">
                <div class="detail-row">
                  <span class="detail-label">Full Value</span>
                  <span class="detail-value">{{ formatCurrency(account.current_value) }}</span>
                </div>
                <div class="detail-row">
                  <span class="detail-label">Your Share ({{ account.ownership_percentage || 50 }}%)</span>
                  <span class="detail-value text-violet-600">{{ formatCurrency(account.current_value * ((account.ownership_percentage || 50) / 100)) }}</span>
                </div>
              </div>

              <!-- Individual account shows just current value -->
              <div v-else class="detail-row">
                <span class="detail-label">Current Value</span>
                <span class="detail-value">{{ formatCurrency(account.current_value) }}</span>
              </div>

              <!-- ISA allowance info -->
              <div v-if="account.account_type === 'isa'" class="isa-allowance-info">
                <div class="detail-row">
                  <span class="detail-label">ISA Contributions (YTD)</span>
                  <span class="detail-value text-spring-600">{{ formatCurrency(getIsaContributions(account)) }}</span>
                </div>
                <div class="detail-row">
                  <span class="detail-label">Allowance Remaining</span>
                  <span class="detail-value" :class="getIsaRemainingClass(account)">{{ formatCurrency(getIsaRemaining(account)) }}</span>
                </div>
              </div>

              <div v-if="account.ytd_return" class="detail-row">
                <span class="detail-label">YTD Return</span>
                <span class="detail-value" :class="getReturnColorClass(account.ytd_return)">
                  {{ formatReturn(account.ytd_return) }}
                </span>
              </div>

              <div class="detail-row">
                <span class="detail-label">{{ getPrimaryAssetClass(account).label }}</span>
                <span class="detail-value">{{ getPrimaryAssetClass(account).percentage }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Loading State -->
      <div v-else class="flex justify-center items-center py-12">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-raspberry-500"></div>
        <span class="ml-3 text-neutral-500">Loading accounts...</span>
      </div>
    </div>

    <!-- Risk Metrics Placeholder -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
      <div class="bg-white border border-light-gray rounded-lg p-6">
        <h2 class="text-xl font-semibold text-horizon-500 mb-4">Risk Profile</h2>
        <div v-if="riskMetrics" class="space-y-3">
          <div class="flex justify-between">
            <span class="text-sm text-neutral-500">Risk Level:</span>
            <span class="text-sm font-medium text-horizon-500 capitalize">{{ riskMetrics.risk_level }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-sm text-neutral-500">Equity %:</span>
            <span class="text-sm font-medium text-horizon-500">{{ riskMetrics.equity_percentage }}%</span>
          </div>
          <div class="flex justify-between">
            <span class="text-sm text-neutral-500">Est. Volatility:</span>
            <span class="text-sm font-medium text-horizon-500">{{ riskMetrics.estimated_volatility }}%</span>
          </div>
        </div>
        <p v-else class="text-neutral-500 text-center py-4">No risk metrics available</p>
      </div>

      <div class="bg-white border border-light-gray rounded-lg p-6">
        <h2 class="text-xl font-semibold text-horizon-500 mb-4">Tax Efficiency</h2>
        <div v-if="taxEfficiency" class="space-y-3">
          <div class="flex justify-between">
            <span class="text-sm text-neutral-500">Tax Efficiency:</span>
            <span class="text-sm font-medium text-horizon-500">{{ taxEfficiencyLabel }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-sm text-neutral-500">Unrealised Gains:</span>
            <span class="text-sm font-medium text-horizon-500">{{ formatCurrency(unrealisedGains) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-sm text-neutral-500">Tax-Free Allowance Used:</span>
            <span class="text-sm font-medium text-horizon-500">{{ isaAllowancePercentage.toFixed(1) }}%</span>
          </div>
        </div>
        <p v-else class="text-neutral-500 text-center py-4">No tax efficiency data available</p>
      </div>
    </div>

    <!-- Portfolio Summary -->
    <div class="bg-white rounded-lg shadow p-6">
      <h3 class="text-lg font-semibold text-horizon-500 mb-6">Portfolio Summary</h3>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Total Portfolio Value -->
        <div class="border-l-4 border-violet-500 pl-4">
          <p class="text-sm text-neutral-500 mb-1">Total Portfolio Value</p>
          <p class="text-2xl font-bold text-horizon-500">{{ formattedTotalValue }}</p>
          <p class="text-sm text-neutral-500 mt-1">{{ accountsCount }} account{{ accountsCount !== 1 ? 's' : '' }}</p>
        </div>

        <!-- YTD Return -->
        <div class="border-l-4 pl-4" :class="ytdReturn >= 0 ? 'border-spring-500' : 'border-raspberry-500'">
          <p class="text-sm text-neutral-500 mb-1">Return (This Year)</p>
          <p class="text-2xl font-bold" :class="ytdReturn >= 0 ? 'text-spring-600' : 'text-raspberry-600'">{{ formattedYtdReturn }}</p>
          <p class="text-sm text-neutral-500 mt-1">{{ holdingsCount }} holding{{ holdingsCount !== 1 ? 's' : '' }}</p>
        </div>

        <!-- Diversification -->
        <div class="border-l-4 border-violet-500 pl-4">
          <p class="text-sm text-neutral-500 mb-1">Diversification</p>
          <p class="text-2xl font-bold text-horizon-500">{{ diversificationLabel }}</p>
          <p class="text-sm text-neutral-500 mt-1">Across {{ holdingsCount }} holding{{ holdingsCount !== 1 ? 's' : '' }}</p>
        </div>
      </div>
    </div>

    <!-- Document Upload Modal -->
    <DocumentUploadModal
      v-if="showUploadModal"
      document-type="investment_statement"
      @close="closeUploadModal"
      @saved="handleDocumentSaved"
      @manual-entry="closeUploadModal(); addAccount();"
    />
  </div>
</template>

<script>
import { mapGetters } from 'vuex';
import AssetAllocationChart from './AssetAllocationChart.vue';
import GeographicAllocationMap from './GeographicAllocationMap.vue';
import DocumentUploadModal from '@/components/Shared/DocumentUploadModal.vue';
import { TAX_CONFIG } from '@/constants/taxConfig';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'PortfolioOverview',

  mixins: [currencyMixin],

  emits: ['open-add-account-modal', 'select-account'],

  components: {
    AssetAllocationChart,
    GeographicAllocationMap,
    DocumentUploadModal,
  },

  data() {
    return {
      showUploadModal: false,
    };
  },

  computed: {
    ...mapGetters('investment', [
      'totalPortfolioValue',
      'ytdReturn',
      'assetAllocation',
      'diversificationScore',
      'holdingsCount',
      'accountsCount',
      'unrealisedGains',
      'taxEfficiencyScore',
      'isaAllowancePercentage',
      'accounts',
      'analysis',
      'loading',
    ]),

    formattedTotalValue() {
      return this.formatCurrency(this.totalPortfolioValue);
    },

    formattedYtdReturn() {
      const sign = this.ytdReturn >= 0 ? '+' : '';
      return `${sign}${this.ytdReturn.toFixed(2)}%`;
    },

    taxEfficiencyLabel() {
      if (this.taxEfficiencyScore >= 80) return 'Highly Efficient';
      if (this.taxEfficiencyScore >= 60) return 'Moderately Efficient';
      if (this.taxEfficiencyScore >= 40) return 'Could Be Improved';
      return 'Needs Attention';
    },

    diversificationLabel() {
      if (this.diversificationScore >= 80) return 'Well Diversified';
      if (this.diversificationScore >= 60) return 'Moderately Diversified';
      if (this.diversificationScore >= 40) return 'Concentrated';
      return 'Highly Concentrated';
    },

    riskMetrics() {
      return this.analysis?.risk_metrics;
    },

    taxEfficiency() {
      return this.analysis?.tax_efficiency;
    },

    allocationForChart() {
      // Convert array of allocation objects to key-value object for chart
      if (!this.assetAllocation || this.assetAllocation.length === 0) {
        return {};
      }

      return this.assetAllocation.reduce((acc, asset) => {
        acc[asset.asset_type] = asset.percentage;
        return acc;
      }, {});
    },

    geographicAllocationForChart() {
      // Get geographic allocation from analysis
      const geographicAllocation = this.analysis?.geographic_allocation;

      if (!geographicAllocation || Object.keys(geographicAllocation).length === 0) {
        return {};
      }

      return geographicAllocation;
    },

  },

  methods: {
    formatReturn(value) {
      if (!value && value !== 0) return 'N/A';
      const sign = value >= 0 ? '+' : '';
      return `${sign}${value.toFixed(2)}%`;
    },

    formatAccountType(type) {
      const types = {
        'isa': 'Stocks & Shares ISA',
        'sipp': 'Self-Invested Personal Pension',
        'gia': 'General Investment Account',
        'pension': 'Pension',
        'nsi': 'National Savings & Investments',
        'onshore_bond': 'Onshore Bond',
        'offshore_bond': 'Offshore Bond',
        'vct': 'Venture Capital Trust',
        'eis': 'Enterprise Investment Scheme',
        'other': 'Other',
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

    getOwnershipBadgeClass(type) {
      const classes = {
        individual: 'bg-savannah-100 text-horizon-500',
        joint: 'bg-violet-500 text-white',
        trust: 'bg-violet-500 text-white',
      };
      return classes[type] || 'bg-savannah-100 text-horizon-500';
    },

    accountTypeBadgeClass(type) {
      const classes = {
        isa: 'bg-spring-500 text-white',
        gia: 'bg-violet-500 text-white',
        sipp: 'bg-violet-500 text-white',
        pension: 'bg-violet-500 text-white',
        nsi: 'bg-violet-500 text-white',
        onshore_bond: 'bg-violet-500 text-white',
        offshore_bond: 'bg-violet-500 text-white',
        vct: 'bg-pink-500 text-white',
        eis: 'bg-pink-500 text-white',
        other: 'bg-savannah-100 text-horizon-500',
      };
      return classes[type] || 'bg-savannah-100 text-horizon-500';
    },

    getReturnColorClass(value) {
      if (!value && value !== 0) return 'text-neutral-500';
      return value >= 0 ? 'text-spring-600' : 'text-raspberry-600';
    },

    getPrimaryAssetClass(account) {
      // If no holdings, default to 100% Cash
      if (!account.holdings || account.holdings.length === 0) {
        return {
          label: 'Cash',
          percentage: '(100%)',
        };
      }

      // Calculate asset allocation from holdings
      const assetAllocation = {};
      let totalValue = 0;

      account.holdings.forEach(holding => {
        const value = parseFloat(holding.current_value || 0);
        const assetType = holding.asset_type || 'other';

        if (!assetAllocation[assetType]) {
          assetAllocation[assetType] = 0;
        }
        assetAllocation[assetType] += value;
        totalValue += value;
      });

      // Find the primary asset class (highest value)
      let primaryAsset = 'Cash';
      let primaryValue = 0;

      Object.entries(assetAllocation).forEach(([assetType, value]) => {
        if (value > primaryValue) {
          primaryValue = value;
          primaryAsset = assetType;
        }
      });

      // Calculate percentage
      const percentage = totalValue > 0
        ? ((primaryValue / totalValue) * 100).toFixed(0)
        : 100;

      // Format asset class name
      const assetClassNames = {
        equity: 'Equity',
        fixed_income: 'Fixed Income',
        property: 'Property',
        commodities: 'Commodities',
        cash: 'Cash',
        alternatives: 'Alternatives',
        other: 'Other',
      };

      const label = assetClassNames[primaryAsset] || primaryAsset.charAt(0).toUpperCase() + primaryAsset.slice(1);

      return {
        label: label,
        percentage: `(${percentage}%)`,
      };
    },

    addAccount() {
      // Emit event to parent to switch tab and open modal
      this.$emit('open-add-account-modal');
    },

    viewAccount(accountId) {
      // Find the full account object from the accounts array
      const account = this.accounts.find(a => a.id === accountId);
      if (account) {
        // Emit the full account object to parent
        this.$emit('select-account', account);
      }
    },

    getSpouseName() {
      const user = this.$store.getters['auth/currentUser'];
      return user?.spouse?.name || 'Spouse';
    },

    getIsaContributions(account) {
      // Use isa_subscription_current_year for ISA accounts
      return account.isa_subscription_current_year || 0;
    },

    getIsaRemaining(account) {
      const contributions = this.getIsaContributions(account);
      return Math.max(0, TAX_CONFIG.ISA_ANNUAL_ALLOWANCE - contributions);
    },

    getIsaRemainingClass(account) {
      const remaining = this.getIsaRemaining(account);
      if (remaining <= 0) return 'text-raspberry-600';
      if (remaining < 5000) return 'text-violet-600';
      return 'text-spring-600';
    },

    closeUploadModal() {
      this.showUploadModal = false;
    },

    async handleDocumentSaved(savedData) {
      this.showUploadModal = false;
      // Refresh investment data
      await this.$store.dispatch('investment/fetchInvestmentData');
    },
  },
};
</script>

<style scoped>
.account-overview {
  margin-bottom: 24px;
}

.section-header-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  flex-wrap: wrap;
  gap: 16px;
}

.section-title {
  font-size: 20px;
  font-weight: 600;
  @apply text-horizon-500;
  margin: 0;
}

.add-account-btn {
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

.add-account-btn:hover {
  @apply bg-raspberry-500;
}

.upload-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  background: white;
  @apply text-raspberry-500;
  @apply border-2 border-raspberry-500;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.upload-btn:hover {
  @apply bg-light-pink-50;
}

.btn-icon {
  width: 20px;
  height: 20px;
}

.accounts-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 20px;
}

.account-card {
  background: white;
  border-radius: 12px;
  @apply border border-light-gray;
  padding: 20px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.account-card:hover {
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  transform: translateY(-2px);
  @apply border-raspberry-500;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 16px;
  flex-wrap: wrap;
  gap: 8px;
}

.ownership-badge {
  display: inline-block;
  padding: 4px 12px;
  font-size: 12px;
  font-weight: 600;
  border-radius: 6px;
}

.badge {
  display: inline-block;
  padding: 4px 10px;
  font-size: 11px;
  font-weight: 600;
  border-radius: 6px;
}

.card-content {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.account-institution {
  font-size: 18px;
  font-weight: 700;
  @apply text-horizon-500;
  margin: 0;
}

.account-type {
  font-size: 14px;
  @apply text-neutral-500;
  margin: 0;
}

.account-details {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-top: 4px;
  padding-top: 12px;
  @apply border-t border-light-gray;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.detail-label {
  font-size: 14px;
  @apply text-neutral-500;
  font-weight: 500;
}

.detail-value {
  font-size: 16px;
  @apply text-horizon-500;
  font-weight: 700;
}

.isa-allowance-info {
  @apply bg-spring-50;
  border-radius: 6px;
  padding: 8px;
  margin: 4px 0;
}

.empty-state {
  text-align: center;
  padding: 60px 20px;
  border-radius: 12px;
  @apply bg-light-blue-100 border border-light-gray;
}

.empty-message {
  @apply text-neutral-500;
  font-size: 16px;
  margin-bottom: 20px;
}

.add-account-button {
  padding: 12px 24px;
  @apply bg-horizon-500 text-white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}

.add-account-button:hover {
  @apply bg-horizon-600;
}

@media (max-width: 768px) {
  .section-header-row {
    flex-direction: column;
    align-items: flex-start;
  }

  .add-account-btn {
    width: 100%;
    justify-content: center;
  }

  .accounts-grid {
    grid-template-columns: 1fr;
  }
}
</style>
