<template>
  <div class="portfolio-overview">
    <!-- Investment Accounts -->
    <div class="account-overview mb-8">
      <div class="section-header-row">
        <h3 class="section-title">Investment Accounts</h3>
        <div class="flex gap-3">
          <button v-preview-disabled="'add'" @click="addAccount" class="add-account-btn">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="btn-icon">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            Add Account
          </button>
          <button v-preview-disabled="'upload'" @click="showUploadModal = true" class="upload-btn">
            <svg class="btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
            </svg>
            Upload Statement
          </button>
        </div>
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
                  <span class="detail-value text-purple-600">{{ formatCurrency(account.current_value * ((account.ownership_percentage || 50) / 100)) }}</span>
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
                  <span class="detail-value text-green-600">{{ formatCurrency(getIsaContributions(account)) }}</span>
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
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
        <span class="ml-3 text-gray-600">Loading accounts...</span>
      </div>
    </div>

    <!-- Risk Metrics Placeholder -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
      <div class="bg-white border border-gray-200 rounded-lg p-6">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Risk Profile</h2>
        <div v-if="riskMetrics" class="space-y-3">
          <div class="flex justify-between">
            <span class="text-sm text-gray-600">Risk Level:</span>
            <span class="text-sm font-medium text-gray-900 capitalize">{{ riskMetrics.risk_level }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-sm text-gray-600">Equity %:</span>
            <span class="text-sm font-medium text-gray-900">{{ riskMetrics.equity_percentage }}%</span>
          </div>
          <div class="flex justify-between">
            <span class="text-sm text-gray-600">Est. Volatility:</span>
            <span class="text-sm font-medium text-gray-900">{{ riskMetrics.estimated_volatility }}%</span>
          </div>
        </div>
        <p v-else class="text-gray-500 text-center py-4">No risk metrics available</p>
      </div>

      <div class="bg-white border border-gray-200 rounded-lg p-6">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Tax Efficiency</h2>
        <div v-if="taxEfficiency" class="space-y-3">
          <div class="flex justify-between">
            <span class="text-sm text-gray-600">Efficiency Score:</span>
            <span class="text-sm font-medium text-gray-900">{{ taxEfficiencyScore }}/100</span>
          </div>
          <div class="flex justify-between">
            <span class="text-sm text-gray-600">Unrealised Gains:</span>
            <span class="text-sm font-medium text-gray-900">{{ formatCurrency(unrealisedGains) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-sm text-gray-600">Tax-Free Allowance Used:</span>
            <span class="text-sm font-medium text-gray-900">{{ isaAllowancePercentage.toFixed(1) }}%</span>
          </div>
        </div>
        <p v-else class="text-gray-500 text-center py-4">No tax efficiency data available</p>
      </div>
    </div>

    <!-- Portfolio Summary -->
    <div class="bg-white rounded-lg shadow p-6">
      <h3 class="text-lg font-semibold text-gray-900 mb-6">Portfolio Summary</h3>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Total Portfolio Value -->
        <div class="border-l-4 border-blue-500 pl-4">
          <p class="text-sm text-gray-600 mb-1">Total Portfolio Value</p>
          <p class="text-2xl font-bold text-gray-900">{{ formattedTotalValue }}</p>
          <p class="text-sm text-gray-500 mt-1">{{ accountsCount }} account{{ accountsCount !== 1 ? 's' : '' }}</p>
        </div>

        <!-- YTD Return -->
        <div class="border-l-4 pl-4" :class="ytdReturn >= 0 ? 'border-green-500' : 'border-red-500'">
          <p class="text-sm text-gray-600 mb-1">Return (This Year)</p>
          <p class="text-2xl font-bold" :class="ytdReturn >= 0 ? 'text-green-600' : 'text-red-600'">{{ formattedYtdReturn }}</p>
          <p class="text-sm text-gray-500 mt-1">{{ holdingsCount }} holding{{ holdingsCount !== 1 ? 's' : '' }}</p>
        </div>

        <!-- Diversification Score -->
        <div class="border-l-4 border-purple-500 pl-4">
          <p class="text-sm text-gray-600 mb-1">Diversification Score</p>
          <p class="text-2xl font-bold text-gray-900">{{ diversificationScore }}/100</p>
          <p class="text-sm text-gray-500 mt-1">{{ diversificationLabel }}</p>
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
    ]),

    formattedTotalValue() {
      return this.formatCurrency(this.totalPortfolioValue);
    },

    formattedYtdReturn() {
      const sign = this.ytdReturn >= 0 ? '+' : '';
      return `${sign}${this.ytdReturn.toFixed(2)}%`;
    },

    diversificationLabel() {
      if (this.diversificationScore >= 80) return 'Excellent';
      if (this.diversificationScore >= 60) return 'Good';
      if (this.diversificationScore >= 40) return 'Fair';
      return 'Poor';
    },

    riskMetrics() {
      return this.$store.state.investment.analysis?.risk_metrics;
    },

    taxEfficiency() {
      return this.$store.state.investment.analysis?.tax_efficiency;
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
      const analysis = this.$store.state.investment.analysis;
      const geographicAllocation = analysis?.geographic_allocation;

      if (!geographicAllocation || Object.keys(geographicAllocation).length === 0) {
        return {};
      }

      return geographicAllocation;
    },

    loading() {
      return this.$store.state.investment.loading;
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
        'sipp': 'Self-Invested Pension',
        'gia': 'General Investment',
        'pension': 'Pension',
        'nsi': 'NS&I',
        'onshore_bond': 'Onshore Bond',
        'offshore_bond': 'Offshore Bond',
        'vct': 'Venture Capital Trust',
        'eis': 'Enterprise Scheme',
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
        individual: 'bg-gray-100 text-gray-800',
        joint: 'bg-purple-500 text-white',
        trust: 'bg-amber-500 text-white',
      };
      return classes[type] || 'bg-gray-100 text-gray-800';
    },

    accountTypeBadgeClass(type) {
      const classes = {
        isa: 'bg-green-500 text-white',
        gia: 'bg-blue-500 text-white',
        sipp: 'bg-purple-500 text-white',
        pension: 'bg-purple-500 text-white',
        nsi: 'bg-indigo-500 text-white',
        onshore_bond: 'bg-orange-500 text-white',
        offshore_bond: 'bg-orange-500 text-white',
        vct: 'bg-pink-500 text-white',
        eis: 'bg-pink-500 text-white',
        other: 'bg-gray-100 text-gray-800',
      };
      return classes[type] || 'bg-gray-100 text-gray-800';
    },

    getReturnColorClass(value) {
      if (!value && value !== 0) return 'text-gray-600';
      return value >= 0 ? 'text-green-600' : 'text-red-600';
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
      if (remaining <= 0) return 'text-red-600';
      if (remaining < 5000) return 'text-amber-600';
      return 'text-green-600';
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
  @apply text-gray-900;
  margin: 0;
}

.add-account-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  @apply bg-primary-500;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}

.add-account-btn:hover {
  @apply bg-blue-600;
}

.upload-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  background: white;
  @apply text-primary-500;
  @apply border-2 border-primary-500;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.upload-btn:hover {
  @apply bg-blue-50;
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
  @apply border border-gray-200;
  padding: 20px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.account-card:hover {
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  transform: translateY(-2px);
  border-@apply text-primary-500;
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
  @apply text-gray-900;
  margin: 0;
}

.account-type {
  font-size: 14px;
  @apply text-gray-500;
  margin: 0;
}

.account-details {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-top: 4px;
  padding-top: 12px;
  @apply border-t border-gray-200;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.detail-label {
  font-size: 14px;
  @apply text-gray-500;
  font-weight: 500;
}

.detail-value {
  font-size: 16px;
  @apply text-gray-900;
  font-weight: 700;
}

.isa-allowance-info {
  @apply bg-green-50;
  border-radius: 6px;
  padding: 8px;
  margin: 4px 0;
}

.empty-state {
  text-align: center;
  padding: 60px 20px;
  background: white;
  border-radius: 12px;
  @apply border-2 border-dashed border-gray-300;
}

.empty-message {
  @apply text-gray-500;
  font-size: 16px;
  margin-bottom: 20px;
}

.add-account-button {
  padding: 12px 24px;
  @apply bg-primary-500;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}

.add-account-button:hover {
  @apply bg-blue-600;
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
