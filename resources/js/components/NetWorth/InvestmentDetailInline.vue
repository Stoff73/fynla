<template>
  <div class="investment-detail-inline w-full max-w-full overflow-hidden">
    <!-- Back Button - contextual based on current tab -->
    <button @click="handleBackClick" class="back-button mb-4">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
      </svg>
      {{ backButtonText }}
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
              v-preview-disabled="'edit'"
              @click="showEditModal = true"
              class="btn-primary whitespace-nowrap"
            >
              Edit
            </button>
            <button
              v-preview-disabled="'delete'"
              @click="confirmDelete"
              class="btn-danger whitespace-nowrap"
            >
              Delete
            </button>
          </div>
        </div>

        <!-- Key Metrics - Only show for standard accounts -->
        <template v-if="detailComponentType === 'standard'">
          <div v-if="activeTab === 'fees'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mt-6">
            <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
              <p class="text-sm text-gray-600">Platform Fee</p>
              <p class="text-2xl font-bold text-blue-600">{{ platformFeeDisplay }}</p>
            </div>
            <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
              <p class="text-sm text-gray-600">Average Fund Fee (OCF)</p>
              <p class="text-2xl font-bold text-blue-600">{{ weightedAverageOCF.toFixed(2) }}%</p>
            </div>
            <div class="bg-purple-50 rounded-lg p-4 border border-purple-200">
              <p class="text-sm text-gray-600">Advisor Fee</p>
              <p class="text-2xl font-bold text-purple-600">{{ (advisorFeePercent || 0).toFixed(2) }}%</p>
            </div>
            <div class="bg-red-50 rounded-lg p-4 border border-red-200">
              <p class="text-sm text-gray-600">Total Annual Cost</p>
              <p class="text-2xl font-bold text-red-600">{{ totalFeePercent.toFixed(2) }}%</p>
              <p class="text-xs text-gray-500 mt-1">{{ formatCurrency(totalAnnualFeeCost) }}/year</p>
            </div>
          </div>
          <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mt-6">
            <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
              <p class="text-sm text-gray-600">Current Value</p>
              <p class="text-2xl font-bold text-blue-600">{{ formatCurrency(account.current_value) }}</p>
              <p v-if="account.ownership_type === 'joint'" class="text-xs text-violet-600 mt-1">
                Your {{ account.ownership_percentage ?? 50 }}%: {{ formatCurrency(userShareValue) }}
              </p>
            </div>
            <div class="bg-gray-50 rounded-lg p-4">
              <p class="text-sm text-gray-600">Annualised Return</p>
              <p class="text-2xl font-bold" :class="getReturnColorClass(grossReturnPercent)">
                {{ formatReturnPercent(grossReturnPercent) }}
              </p>
              <p v-if="grossReturnPercent !== null" class="text-xs text-gray-500 mt-1">
                {{ formatReturnPercent(netReturnPercent) }} net of fees
              </p>
            </div>
            <div class="bg-gray-50 rounded-lg p-4">
              <p class="text-sm text-gray-600">Monthly Contribution</p>
              <p class="text-2xl font-bold" :class="estimatedMonthlyContribution > 0 ? 'text-green-600' : 'text-gray-900'">
                {{ estimatedMonthlyContribution > 0 ? formatCurrency(estimatedMonthlyContribution) : '—' }}
              </p>
            </div>
            <!-- ISA Allowance Card (for ISA accounts) -->
            <div v-if="account.account_type === 'isa'" class="bg-green-50 rounded-lg p-4 border border-green-200">
              <p class="text-sm text-gray-600">ISA Allowance Used</p>
              <p class="text-2xl font-bold text-green-600">{{ formatCurrency(account.isa_subscription_current_year || 0) }}</p>
              <p class="text-xs text-gray-500 mt-1">{{ formatCurrency(isaRemaining) }} remaining</p>
            </div>
            <!-- Joint Owner Card (for joint non-ISA accounts) -->
            <div v-else-if="account.ownership_type === 'joint' && account.joint_owner_name" class="bg-purple-50 rounded-lg p-4 border border-purple-200">
              <p class="text-sm text-gray-600">Joint Owner</p>
              <p class="text-xl font-bold text-purple-700">{{ account.joint_owner_name }}</p>
              <p class="text-xs text-gray-500 mt-1">{{ 100 - (account.ownership_percentage ?? 50) }}% share</p>
            </div>
            <!-- Holdings Card (for non-ISA, non-joint accounts) -->
            <div v-else class="bg-gray-50 rounded-lg p-4">
              <p class="text-sm text-gray-600">Holdings</p>
              <p class="text-2xl font-bold text-gray-900">{{ holdingsCount }}</p>
            </div>
          </div>
        </template>
      </div>

      <!-- Specialized Detail Views for Employee Share Schemes and Private Investments -->
      <EmployeeShareSchemeDetail
        v-if="detailComponentType === 'employee-share-scheme'"
        :account="account"
      />

      <PrivateInvestmentDetail
        v-else-if="detailComponentType === 'private-investment'"
        :account="account"
      />

      <!-- Standard Tab Content (for ISA, GIA, SIPP, etc.) -->
      <div v-else class="bg-white rounded-lg shadow-md">
        <div class="p-6">
          <!-- Overview Tab -->
          <AccountSummaryPanel
            v-if="activeTab === 'overview'"
            :account="account"
            @add-holding="openHoldingModal(null)"
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
            @change-tab="handleTabChange"
            @add-holding="openHoldingModal(null)"
            @edit-account="showEditModal = true"
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
      :show="showHoldingModal"
      :holding="editingHolding"
      :accounts="[account]"
      :default-account-id="account.id"
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
import EmployeeShareSchemeDetail from '@/views/Investment/EmployeeShareSchemeDetail.vue';
import PrivateInvestmentDetail from '@/views/Investment/PrivateInvestmentDetail.vue';
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
    EmployeeShareSchemeDetail,
    PrivateInvestmentDetail,
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
      activeTab: 'performance',
      loading: false,
      showEditModal: false,
      showDeleteConfirm: false,
      showHoldingModal: false,
      editingHolding: null,
    };
  },

  computed: {
    // Determine which detail component to render based on account type
    detailComponentType() {
      const type = this.account.account_type;
      const employeeShareSchemes = ['saye', 'csop', 'emi', 'unapproved_options', 'rsu'];
      const privateInvestments = ['private_company', 'crowdfunding'];

      if (employeeShareSchemes.includes(type)) {
        return 'employee-share-scheme';
      }
      if (privateInvestments.includes(type)) {
        return 'private-investment';
      }
      return 'standard';
    },

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

    // Back button text - contextual based on current tab and view type
    backButtonText() {
      // Specialized views (employee share schemes, private investments) always go back to list
      if (this.detailComponentType !== 'standard') {
        return 'Back to Investments';
      }
      // Standard view: If on a sub-tab (not performance), show "Back to {account}"
      if (this.activeTab !== 'performance') {
        return `Back to ${this.account.provider || this.account.account_name || 'Account'}`;
      }
      // On performance tab, show "Back to Investments"
      return 'Back to Investments';
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

    // Get monthly contribution - use actual value if set, otherwise estimate from YTD
    estimatedMonthlyContribution() {
      // Use actual monthly contribution amount if set
      if (this.account.monthly_contribution_amount > 0) {
        // Convert to monthly based on frequency
        const amount = this.account.monthly_contribution_amount;
        const frequency = this.account.contribution_frequency || 'monthly';
        switch (frequency) {
          case 'quarterly':
            return amount / 3;
          case 'annually':
            return amount / 12;
          default:
            return amount;
        }
      }

      // Fallback: estimate from YTD (UK tax year starts April 6)
      const ytd = this.account.contributions_ytd || 0;
      if (ytd <= 0) return 0;

      const now = new Date();
      const currentYear = now.getFullYear();
      const taxYearStart = new Date(currentYear, 3, 6); // April 6

      // If before April 6, tax year started previous year
      if (now < taxYearStart) {
        taxYearStart.setFullYear(currentYear - 1);
      }

      const monthsElapsed = Math.max(1, Math.ceil((now - taxYearStart) / (1000 * 60 * 60 * 24 * 30)));
      return ytd / monthsElapsed;
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

    // Platform fee display (for fees tab header)
    platformFeeDisplay() {
      if (this.account.platform_fee_type === 'fixed') {
        const amount = parseFloat(this.account.platform_fee_amount) || 0;
        const freq = { monthly: '/month', quarterly: '/quarter', annually: '/year' };
        return `${this.formatCurrency(amount)}${freq[this.account.platform_fee_frequency] || '/year'}`;
      }
      return `${(parseFloat(this.account.platform_fee_percent) || 0).toFixed(2)}%`;
    },

    // Weighted average OCF across holdings
    weightedAverageOCF() {
      if (!this.account.holdings?.length || this.totalHoldingsValue === 0) return 0;
      const totalWeightedOCF = this.account.holdings.reduce((sum, h) => {
        return sum + ((h.current_value || 0) * (parseFloat(h.ocf_percent) || 0));
      }, 0);
      return totalWeightedOCF / this.totalHoldingsValue;
    },

    // Advisor fee percentage
    advisorFeePercent() {
      return parseFloat(this.account.advisor_fee_percent) || 0;
    },

    // Total annual fee cost in pounds
    totalAnnualFeeCost() {
      const accountValue = parseFloat(this.account.current_value) || 0;
      return accountValue * (this.totalFeePercent / 100);
    },

    // Total fee percentage (matching Fees tab calculation)
    totalFeePercent() {
      let platformFee = 0;
      if (this.account.platform_fee_type === 'fixed') {
        const amount = parseFloat(this.account.platform_fee_amount) || 0;
        let annualAmount = amount;
        if (this.account.platform_fee_frequency === 'monthly') annualAmount = amount * 12;
        else if (this.account.platform_fee_frequency === 'quarterly') annualAmount = amount * 4;
        const accountValue = parseFloat(this.account.current_value) || 0;
        platformFee = accountValue > 0 ? (annualAmount / accountValue) * 100 : 0;
      } else {
        platformFee = parseFloat(this.account.platform_fee_percent) || 0;
      }
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
    ...mapActions('investment', ['updateAccount', 'deleteAccount', 'fetchInvestmentData', 'createHolding', 'updateHolding']),

    handleBackClick() {
      // Specialized views (employee share schemes, private investments) always go back to list
      if (this.detailComponentType !== 'standard') {
        this.$emit('back');
        return;
      }
      // Standard view: If on a sub-tab, go back to performance tab
      if (this.activeTab !== 'performance') {
        this.activeTab = 'performance';
      } else {
        // On performance tab, go back to investments list
        this.$emit('back');
      }
    },

    formatReturnPercent(value) {
      if (value === null || value === undefined) return 'N/A';
      const sign = value >= 0 ? '+' : '';
      return `${sign}${this.formatPercentage(value)}`;
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
        'saye': 'Save As You Earn',
        'csop': 'Company Share Option Plan',
        'emi': 'Enterprise Management Incentive Options',
        'unapproved_options': 'Unapproved Options',
        'rsu': 'Restricted Stock Units',
        'private_company': 'Private Company',
        'crowdfunding': 'Crowdfunding',
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
        saye: 'badge-employee-scheme',
        csop: 'badge-employee-scheme',
        emi: 'badge-employee-scheme',
        unapproved_options: 'badge-employee-scheme',
        rsu: 'badge-employee-scheme',
        private_company: 'badge-private',
        crowdfunding: 'badge-private',
        other: 'badge-other',
      };
      return classes[type] || 'badge-other';
    },

    getReturnColorClass(value) {
      if (!value && value !== 0) return 'text-gray-600';
      return value >= 0 ? 'text-green-600' : 'text-gray-600';
    },

    getIsaRemainingClass() {
      if (this.isaRemaining <= 0) return 'text-gray-600';
      if (this.isaRemaining < 5000) return 'text-blue-600';
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

    async handleHoldingSave(holdingData) {
      try {
        if (holdingData.id) {
          // Update existing holding
          await this.updateHolding({ id: holdingData.id, data: holdingData });
        } else {
          // Create new holding
          await this.createHolding(holdingData);
        }
        this.closeHoldingModal();
        await this.fetchInvestmentData();
        this.$emit('updated');
      } catch (error) {
        console.error('Error saving holding:', error);
        // Modal stays open so user can see/fix any issues
      }
    },

    handleTabChange(tabId) {
      this.activeTab = tabId;
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
  @apply border border-gray-200;
  border-radius: 8px;
  @apply text-gray-700;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.back-button:hover {
  @apply bg-gray-100;
  @apply border-gray-300;
}

.badge {
  display: inline-block;
  padding: 4px 10px;
  font-size: 12px;
  font-weight: 600;
  border-radius: 6px;
}

.badge-individual {
  @apply bg-gray-100;
  @apply text-gray-700;
}

.badge-joint {
  @apply bg-purple-50;
  @apply text-purple-600;
}

.badge-trust {
  @apply bg-blue-100;
  @apply text-blue-800;
}

.badge-isa {
  @apply bg-green-100;
  @apply text-green-800;
}

.badge-gia {
  @apply bg-blue-100;
  @apply text-blue-800;
}

.badge-sipp {
  @apply bg-purple-100;
  @apply text-purple-800;
}

.badge-nsi {
  @apply bg-indigo-100;
  @apply text-indigo-800;
}

.badge-bond {
  @apply bg-blue-100;
  @apply text-blue-800;
}

.badge-vct {
  @apply bg-pink-100;
  @apply text-pink-800;
}

.badge-other {
  @apply bg-gray-100;
  @apply text-gray-700;
}

.badge-employee-scheme {
  @apply bg-teal-100;
  @apply text-teal-800;
}

.badge-private {
  @apply bg-slate-100;
  @apply text-slate-800;
}
</style>
