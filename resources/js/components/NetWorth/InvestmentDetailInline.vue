<template>
  <div class="investment-detail-inline w-full max-w-full overflow-hidden">
    <!-- Back Button -->
    <button @click="$emit('back')" class="back-button mb-4">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
      </svg>
      Back to Investments
    </button>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
      <p class="mt-4 text-gray-600">Loading account details...</p>
    </div>

    <!-- Account Content -->
    <div v-else class="space-y-6">
      <!-- Header -->
      <div class="bg-white rounded-lg shadow-md p-4 sm:p-6 overflow-hidden">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4 max-w-full">
          <div>
            <div class="flex flex-wrap items-center gap-2 sm:gap-3 mb-2">
              <span :class="['badge', getOwnershipBadgeClass(account.ownership_type)]">
                {{ formatOwnershipType(account.ownership_type) }}
              </span>
              <span :class="['badge', accountTypeBadgeClass(account.account_type)]">
                {{ formatAccountType(account.account_type) }}
              </span>
            </div>
            <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold text-gray-900">{{ account.provider }}</h1>
            <p class="text-base sm:text-lg text-gray-600 mt-1">{{ account.account_name }}</p>
          </div>
          <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto shrink-0">
            <button
              @click="showEditModal = true"
              class="btn-primary whitespace-nowrap"
            >
              Edit
            </button>
            <button
              @click="confirmDelete"
              class="btn-danger whitespace-nowrap"
            >
              Delete
            </button>
          </div>
        </div>

        <!-- Key Metrics -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4 mt-6">
          <div class="bg-gray-50 rounded-lg p-4">
            <p class="text-sm text-gray-600">Current Value</p>
            <p class="text-2xl font-bold text-blue-600">{{ formatCurrency(account.current_value) }}</p>
            <p v-if="account.ownership_type === 'joint'" class="text-sm text-purple-600 mt-1">
              Your {{ account.ownership_percentage ?? 50 }}% share: {{ formatCurrency(userShareValue) }}
            </p>
          </div>
          <div class="bg-gray-50 rounded-lg p-4">
            <p class="text-sm text-gray-600">Annualised Return</p>
            <div class="flex items-baseline gap-2">
              <p class="text-2xl font-bold" :class="getReturnColorClass(grossReturnPercent)">
                {{ formatReturnPercent(grossReturnPercent) }}
              </p>
              <span class="text-xs text-gray-500">p.a. gross</span>
            </div>
            <div v-if="grossReturnPercent !== null" class="mt-1 flex items-baseline gap-2">
              <p class="text-lg font-semibold" :class="getReturnColorClass(netReturnPercent)">
                {{ formatReturnPercent(netReturnPercent) }}
              </p>
              <span class="text-xs text-gray-500">p.a. net of {{ formatPercentage(totalFeePercent) }} fees</span>
            </div>
            <p v-if="usingDefaultHoldingPeriod" class="text-xs text-amber-600 mt-2">
              *Based on 3-year default holding period
            </p>
          </div>
          <!-- ISA Contributions Card (for ISA accounts) -->
          <div v-if="account.account_type === 'isa'" class="bg-gray-50 rounded-lg p-4">
            <div class="flex justify-between items-start">
              <div>
                <p class="text-sm text-gray-600">ISA Contributions (This Year)</p>
                <p class="text-2xl font-bold text-green-600">{{ formatCurrency(account.isa_subscription_current_year || 0) }}</p>
              </div>
              <div class="text-right">
                <p class="text-sm text-gray-600">Allowance Remaining</p>
                <p class="text-xl font-bold" :class="getIsaRemainingClass()">{{ formatCurrency(isaRemaining) }}</p>
              </div>
            </div>
          </div>
          <!-- Holdings Card (for non-ISA accounts) -->
          <div v-else class="bg-gray-50 rounded-lg p-4">
            <p class="text-sm text-gray-600">Holdings</p>
            <p class="text-2xl font-bold text-gray-900">{{ holdingsCount }}</p>
          </div>
        </div>
      </div>

      <!-- Tabs -->
      <div class="bg-white rounded-lg shadow-md">
        <div class="border-b border-gray-200">
          <nav class="flex -mb-px overflow-x-auto">
            <button
              v-for="tab in tabs"
              :key="tab.id"
              @click="activeTab = tab.id"
              class="px-6 py-3 border-b-2 font-medium text-sm transition-colors whitespace-nowrap"
              :class="
                activeTab === tab.id
                  ? 'border-blue-600 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              "
            >
              {{ tab.label }}
            </button>
          </nav>
        </div>

        <div class="p-6">
          <!-- Overview Tab -->
          <AccountSummaryPanel
            v-if="activeTab === 'overview'"
            :account="account"
          />

          <!-- Holdings Tab -->
          <AccountHoldingsPanel
            v-else-if="activeTab === 'holdings'"
            :account="account"
            @open-holding-modal="openHoldingModal"
          />

          <!-- Diversification Tab -->
          <DiversificationTab
            v-else-if="activeTab === 'diversification'"
            :account-id="account.id"
            account-type="investment"
            @add-holdings="openHoldingModal(null)"
          />

          <!-- Performance Tab -->
          <AccountPerformancePanel
            v-else-if="activeTab === 'performance'"
            :account="account"
          />

          <!-- Rebalancing Tab -->
          <AccountRebalancingPanel
            v-else-if="activeTab === 'rebalancing'"
            :account="account"
          />

          <!-- Fees Tab -->
          <AccountFeesPanel
            v-else-if="activeTab === 'fees'"
            :account="account"
          />

          <!-- Tax Status Tab -->
          <TaxStatusPanel
            v-else-if="activeTab === 'tax-status'"
            product-category="investment"
            :product-type="account.account_type"
          />

          <!-- Documents Tab -->
          <div v-else-if="activeTab === 'documents'" class="text-center py-12 text-gray-500">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12 mx-auto mb-4 text-gray-400">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
            </svg>
            <p class="text-lg font-medium">Documents Coming Soon</p>
            <p class="text-sm">Upload and manage investment documents in a future update.</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Edit Modal -->
    <AccountForm
      :show="showEditModal"
      :account="account"
      :is-edit="true"
      @close="showEditModal = false"
      @save="handleUpdate"
    />

    <!-- Delete Confirmation -->
    <ConfirmationModal
      v-if="showDeleteConfirm"
      title="Delete Investment Account"
      message="Are you sure you want to delete this investment account? This will also delete all associated holdings. This action cannot be undone."
      @confirm="handleDelete"
      @cancel="showDeleteConfirm = false"
    />

    <!-- Holding Form Modal -->
    <HoldingForm
      v-if="showHoldingModal"
      :account-id="account.id"
      :holding="editingHolding"
      :is-edit="!!editingHolding"
      @close="closeHoldingModal"
      @save="handleHoldingSave"
    />
  </div>
</template>

<script>
import { mapActions } from 'vuex';
import AccountForm from '@/components/Investment/AccountForm.vue';
import ConfirmationModal from '@/components/Common/ConfirmationModal.vue';
import AccountSummaryPanel from '@/views/Investment/AccountSummaryPanel.vue';
import AccountHoldingsPanel from '@/views/Investment/AccountHoldingsPanel.vue';
import AccountPerformancePanel from '@/views/Investment/AccountPerformancePanel.vue';
import AccountFeesPanel from '@/views/Investment/AccountFeesPanel.vue';
import AccountRebalancingPanel from '@/views/Investment/AccountRebalancingPanel.vue';
import HoldingForm from '@/components/Investment/HoldingForm.vue';
import TaxStatusPanel from '@/components/Common/TaxStatusPanel.vue';
import DiversificationTab from '@/components/Investment/DiversificationTab.vue';
import { TAX_CONFIG } from '@/constants/taxConfig';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'InvestmentDetailInline',
  mixins: [currencyMixin],

  components: {
    AccountForm,
    ConfirmationModal,
    AccountSummaryPanel,
    AccountHoldingsPanel,
    AccountPerformancePanel,
    AccountFeesPanel,
    AccountRebalancingPanel,
    HoldingForm,
    TaxStatusPanel,
    DiversificationTab,
  },

  props: {
    account: {
      type: Object,
      required: true,
    },
  },

  emits: ['back', 'deleted', 'updated', 'account-updated'],

  data() {
    return {
      activeTab: 'overview',
      loading: false,
      showEditModal: false,
      showDeleteConfirm: false,
      showHoldingModal: false,
      editingHolding: null,
    };
  },

  computed: {
    tabs() {
      return [
        { id: 'overview', label: 'Overview' },
        { id: 'holdings', label: 'Holdings' },
        { id: 'diversification', label: 'Diversification' },
        { id: 'performance', label: 'Performance' },
        { id: 'rebalancing', label: 'Rebalancing' },
        { id: 'fees', label: 'Fees' },
        { id: 'tax-status', label: 'Tax Status' },
        { id: 'documents', label: 'Documents' },
      ];
    },

    userShareValue() {
      // For joint accounts, calculate the user's share
      if (this.account.ownership_type === 'joint') {
        const percentage = this.account.ownership_percentage ?? 50;
        return this.account.current_value * (percentage / 100);
      }
      return this.account.current_value;
    },

    holdingsCount() {
      return this.account.holdings?.length || 0;
    },

    isaRemaining() {
      const contributions = this.account.isa_subscription_current_year || 0;
      return Math.max(0, TAX_CONFIG.ISA_ANNUAL_ALLOWANCE - contributions);
    },

    // Calculate total cost basis from holdings
    totalCostBasis() {
      if (!this.account.holdings?.length) return 0;
      return this.account.holdings.reduce((sum, h) => {
        const costBasis = h.cost_basis || ((h.quantity || 0) * (h.purchase_price || 0)) || 0;
        return sum + costBasis;
      }, 0);
    },

    // Calculate total current value from holdings
    totalHoldingsValue() {
      if (!this.account.holdings?.length) return 0;
      return this.account.holdings.reduce((sum, h) => sum + (h.current_value || 0), 0);
    },

    // Calculate weighted average holding period in years (defaults to 3 years if no dates)
    weightedHoldingPeriodYears() {
      if (!this.account.holdings?.length || this.totalHoldingsValue === 0) return 3;

      const now = new Date();
      let weightedDays = 0;
      let valueWithDates = 0;

      this.account.holdings.forEach(h => {
        if (h.purchase_date && h.current_value) {
          const purchaseDate = new Date(h.purchase_date);
          const daysDiff = (now - purchaseDate) / (1000 * 60 * 60 * 24);
          if (daysDiff > 0) {
            weightedDays += daysDiff * h.current_value;
            valueWithDates += h.current_value;
          }
        }
      });

      // If less than 50% have dates, default to 3 years
      if (valueWithDates < this.totalHoldingsValue * 0.5) return 3;

      const avgDays = weightedDays / valueWithDates;
      const years = avgDays / 365.25;

      // Minimum 30 days for sensible annualization
      return Math.max(years, 30 / 365.25);
    },

    // Check if using default holding period (no actual dates)
    usingDefaultHoldingPeriod() {
      if (!this.account.holdings?.length) return true;

      let valueWithDates = 0;
      this.account.holdings.forEach(h => {
        if (h.purchase_date && h.current_value) {
          valueWithDates += h.current_value;
        }
      });

      return valueWithDates < this.totalHoldingsValue * 0.5;
    },

    // Total return percentage (not annualized)
    totalReturnPercent() {
      if (!this.totalCostBasis || this.totalCostBasis === 0) return null;
      return ((this.totalHoldingsValue - this.totalCostBasis) / this.totalCostBasis) * 100;
    },

    // Annualized gross return percentage
    grossReturnPercent() {
      if (this.totalReturnPercent === null) return null;

      const years = this.weightedHoldingPeriodYears;
      const totalReturn = this.totalReturnPercent / 100;

      // For very short periods (< 3 months), use simple linear extrapolation
      // For longer periods, use compound annualization
      if (years < 0.25) {
        return (totalReturn / years) * 100;
      } else {
        return (Math.pow(1 + totalReturn, 1 / years) - 1) * 100;
      }
    },

    // Total fee percentage (matching Fees tab calculation)
    totalFeePercent() {
      const platformFee = parseFloat(this.account.platform_fee_percent) || 0;
      const advisorFee = parseFloat(this.account.advisor_fee_percent) || 0;

      // Weighted average OCF
      let weightedOCF = 0;
      if (this.totalHoldingsValue > 0 && this.account.holdings?.length) {
        const totalWeightedOCF = this.account.holdings.reduce((sum, h) => {
          return sum + ((h.current_value || 0) * (parseFloat(h.ocf_percent) || 0));
        }, 0);
        weightedOCF = totalWeightedOCF / this.totalHoldingsValue;
      }

      return platformFee + advisorFee + weightedOCF;
    },

    // Net return (gross minus fees)
    netReturnPercent() {
      if (this.grossReturnPercent === null) return null;
      return this.grossReturnPercent - this.totalFeePercent;
    },
  },

  methods: {
    ...mapActions('investment', ['updateAccount', 'deleteAccount', 'fetchInvestmentData']),

    formatReturnPercent(value) {
      if (value === null || value === undefined) return 'N/A';
      const sign = value >= 0 ? '+' : '';
      return `${sign}${this.formatPercentage(value)}`;
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
        individual: 'badge-individual',
        joint: 'badge-joint',
        trust: 'badge-trust',
      };
      return classes[type] || 'badge-individual';
    },

    accountTypeBadgeClass(type) {
      const classes = {
        isa: 'badge-isa',
        gia: 'badge-gia',
        sipp: 'badge-sipp',
        pension: 'badge-sipp',
        nsi: 'badge-nsi',
        onshore_bond: 'badge-bond',
        offshore_bond: 'badge-bond',
        vct: 'badge-vct',
        eis: 'badge-vct',
        other: 'badge-other',
      };
      return classes[type] || 'badge-other';
    },

    getReturnColorClass(value) {
      if (!value && value !== 0) return 'text-gray-600';
      return value >= 0 ? 'text-green-600' : 'text-red-600';
    },

    getIsaRemainingClass() {
      if (this.isaRemaining <= 0) return 'text-red-600';
      if (this.isaRemaining < 5000) return 'text-amber-600';
      return 'text-green-600';
    },

    confirmDelete() {
      this.showDeleteConfirm = true;
    },

    async handleUpdate(data) {
      try {
        await this.updateAccount({ id: this.account.id, accountData: data });
        this.showEditModal = false;

        // In preview mode, emit updated data to parent (can't mutate prop directly)
        const isPreview = this.$store.getters['preview/isPreviewMode'];
        if (isPreview) {
          // Emit updated account data to parent so it can update local state
          this.$emit('account-updated', { ...this.account, ...data });
        } else {
          // Normal mode: reload from API
          await this.fetchInvestmentData();
        }

        this.$emit('updated');
        this.$emit('back');
      } catch (error) {
        console.error('Failed to update account:', error);
      }
    },

    async handleDelete() {
      try {
        await this.deleteAccount(this.account.id);
        this.showDeleteConfirm = false;
        this.$emit('deleted');
      } catch (error) {
        console.error('Failed to delete account:', error);
      }
    },

    openHoldingModal(holding = null) {
      this.editingHolding = holding;
      this.showHoldingModal = true;
    },

    closeHoldingModal() {
      this.showHoldingModal = false;
      this.editingHolding = null;
    },

    async handleHoldingSave() {
      this.closeHoldingModal();
      await this.fetchInvestmentData();
      this.$emit('updated');
    },
  },
};
</script>

<style scoped>
.investment-detail-inline {
  animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

.back-button {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  color: #374151;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.back-button:hover {
  background: #f3f4f6;
  border-color: #d1d5db;
}

.badge {
  display: inline-block;
  padding: 4px 10px;
  font-size: 12px;
  font-weight: 600;
  border-radius: 6px;
}

.badge-individual {
  background: #f3f4f6;
  color: #374151;
}

.badge-joint {
  background: #f3e8ff;
  color: #7c3aed;
}

.badge-trust {
  background: #fef3c7;
  color: #92400e;
}

.badge-isa {
  background: #d1fae5;
  color: #065f46;
}

.badge-gia {
  background: #dbeafe;
  color: #1e40af;
}

.badge-sipp {
  background: #e9d5ff;
  color: #6b21a8;
}

.badge-nsi {
  background: #e0e7ff;
  color: #3730a3;
}

.badge-bond {
  background: #ffedd5;
  color: #9a3412;
}

.badge-vct {
  background: #fce7f3;
  color: #9d174d;
}

.badge-other {
  background: #f3f4f6;
  color: #374151;
}
</style>
