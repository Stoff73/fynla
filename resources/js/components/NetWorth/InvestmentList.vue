<template>
  <div class="investment-list overflow-hidden">
    <!-- Investment Detail View (when an account is selected) -->
    <InvestmentDetailInline
      v-if="selectedAccount"
      :account="selectedAccount"
      @back="clearSelection"
      @deleted="handleAccountDeleted"
      @updated="handleAccountUpdated"
      @account-updated="handlePreviewAccountUpdated"
    />

    <!-- Investment List View (default) -->
    <template v-else>
      <div class="list-header">
        <div class="title-row">
          <h2 class="list-title">Investments</h2>
          <router-link
            to="/risk-profile"
            class="risk-profile-link"
          >
            <svg class="risk-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
            Risk Profile
          </router-link>
        </div>
        <div class="header-buttons">
          <button @click="showAccountForm = true" class="add-account-button">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="button-icon">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            Add Account
          </button>
          <button v-preview-disabled="'upload'" @click="showUploadModal = true" class="upload-button">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="button-icon">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
            </svg>
            Upload Statement
          </button>
        </div>
      </div>

      <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-5">
        <div class="flex items-start">
          <svg class="w-5 h-5 text-blue-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
          </svg>
          <p class="ml-3 text-sm text-blue-800">
            We will be connecting this section to Bloomberg, Morningstar or FE Analytics to give an in-depth view of investments and holdings. For now we offer a Monte Carlo (1,000 iterations) for a simple forward look, once connected we can include the past data for your account and holdings.
          </p>
        </div>
      </div>

      <div v-if="loading" class="loading-state">
        <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
        <p class="mt-3">Loading investments...</p>
      </div>

      <div v-else-if="error" class="error-state">
        <p>{{ error }}</p>
      </div>

      <div v-else-if="accounts.length === 0" class="empty-state">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="empty-icon">
          <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941" />
        </svg>
        <p>No investment accounts found</p>
        <p class="empty-subtitle">Add your first investment account to track your portfolio</p>
      </div>

      <!-- Two-column layout: Account Cards (left) + Performance Chart (right) -->
      <div v-else class="main-content-grid">
        <!-- Left Column: Compact Account Cards -->
        <div class="accounts-column">
          <div
            v-for="account in accounts"
            :key="account.id"
            @click="selectAccount(account)"
            class="compact-account-card"
          >
              <!-- Joint Badge - Top Right Corner -->
              <span
                v-if="account.ownership_type === 'joint'"
                class="joint-badge-corner"
              >
                Joint
              </span>
              <!-- Retirement Badge - Top Right Corner (below Joint if both) -->
              <span
                v-if="account.include_in_retirement"
                class="retirement-badge-corner"
                :class="{ 'has-joint': account.ownership_type === 'joint' }"
              >
                Retirement
              </span>
              <div class="card-header">
                <span :class="['badge', accountTypeBadgeClass(account.account_type)]">
                  {{ formatAccountType(account.account_type) }}
                </span>
              </div>
              <div class="card-content">
                <h4 class="account-provider">{{ getAccountDisplayName(account) }}</h4>
                <p class="account-name-text">{{ account.account_name }}</p>
                <div class="account-details">
                  <!-- Joint account display -->
                  <template v-if="account.ownership_type === 'joint'">
                    <div class="detail-row">
                      <span class="detail-label">Full Value</span>
                      <span class="detail-value">{{ formatCurrency(getDisplayValue(account)) }}</span>
                    </div>
                    <div class="detail-row">
                      <span class="detail-label">Your Share ({{ account.ownership_percentage || 50 }}%)</span>
                      <span class="detail-value text-purple-600">{{ formatCurrency(getDisplayValue(account) * ((account.ownership_percentage || 50) / 100)) }}</span>
                    </div>
                  </template>
                  <!-- Individual account -->
                  <template v-else>
                    <div class="detail-row">
                      <span class="detail-label">{{ getValueLabel(account) }}</span>
                      <span class="detail-value">{{ formatCurrency(getDisplayValue(account)) }}</span>
                    </div>
                  </template>

                  <!-- ISA allowance info -->
                  <div v-if="account.account_type === 'isa'" class="isa-info">
                    <div class="detail-row">
                      <span class="detail-label">ISA Used (YTD)</span>
                      <span class="detail-value text-green-600">{{ formatCurrency(account.isa_subscription_current_year || 0) }}</span>
                    </div>
                  </div>

                  <div v-if="account.ytd_return" class="detail-row">
                    <span class="detail-label">YTD Return</span>
                    <span class="detail-value" :class="getReturnColorClass(account.ytd_return)">
                      {{ formatReturn(account.ytd_return) }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>

        <!-- Right Column: Portfolio Performance -->
        <div class="performance-section">
          <Performance @navigate-to-tab="activePortfolioTab = $event" />
        </div>
      </div>

      <!-- Strategy Card (full width) -->
      <div
        v-if="hasRecommendations"
        class="bg-white rounded-lg shadow-md p-6 cursor-pointer transition-all hover:shadow-lg mb-6"
        @click="goToStrategy"
        role="button"
        tabindex="0"
        @keydown.enter="goToStrategy"
      >
        <div class="mb-4">
          <h3 class="text-lg font-semibold text-gray-800">{{ recommendationCount === 1 ? 'Strategy' : 'Strategies' }}</h3>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
          <div
            v-for="(rec, index) in topRecommendations"
            :key="index"
            class="flex flex-col gap-2 p-3 bg-gray-50 rounded-lg"
          >
            <span :class="getPriorityBadgeClass(rec.priority)" class="px-2 py-0.5 rounded text-xs font-medium w-fit">
              {{ getPriorityLabel(rec.priority) }}
            </span>
            <p class="font-medium text-gray-900 text-sm">{{ rec.title }}</p>
            <p class="text-xs text-gray-600 line-clamp-2">{{ rec.description }}</p>
          </div>
        </div>
      </div>

      <!-- Portfolio Features Tabs - Hidden from dashboard, components still available for detail views -->
      <!-- <div v-if="accounts.length > 0" class="portfolio-features">
        <h3 class="features-title">Portfolio Analysis</h3>
        <div class="features-tabs">
          <button
            v-for="tab in portfolioTabs"
            :key="tab.id"
            @click="activePortfolioTab = tab.id"
            :class="['feature-tab', { active: activePortfolioTab === tab.id }]"
          >
            {{ tab.label }}
          </button>
        </div>

        <div class="features-content">
          <Holdings
            v-if="activePortfolioTab === 'holdings'"
            :selected-account-id="null"
          />

          <Performance v-else-if="activePortfolioTab === 'performance'" />

          <div v-else-if="activePortfolioTab === 'optimization'" class="coming-soon-wrapper">
            <div class="coming-soon-banner">
              <p class="text-2xl font-bold text-blue-700">Coming Soon</p>
            </div>
            <div class="opacity-50">
              <PortfolioOptimization />
            </div>
          </div>

          <div v-else-if="activePortfolioTab === 'goals'" class="coming-soon-wrapper">
            <div class="coming-soon-banner">
              <p class="text-2xl font-bold text-blue-700">Coming Soon</p>
            </div>
            <div class="opacity-50">
              <Goals />
              <div class="mt-8">
                <GoalProjection />
              </div>
            </div>
          </div>

          <TaxEfficiencyPanel v-else-if="activePortfolioTab === 'taxefficiency'" />

          <FeeBreakdown v-else-if="activePortfolioTab === 'fees'" />

          <PortfolioStrategyPanel
            v-else-if="activePortfolioTab === 'strategy'"
            @navigate="handleStrategyNavigate"
          />
        </div>
      </div> -->
    </template>

    <!-- Account Form Modal -->
    <AccountForm
      :show="showAccountForm"
      :account="editingAccount"
      :is-edit="!!editingAccount"
      @close="closeAccountForm"
      @save="handleAccountSave"
    />

    <!-- Document Upload Modal -->
    <DocumentUploadModal
      v-if="showUploadModal"
      document-type="investment_statement"
      @close="showUploadModal = false"
      @saved="handleDocumentSaved"
      @manual-entry="showUploadModal = false; showAccountForm = true;"
    />

    <!-- Success/Error Messages -->
    <div v-if="successMessage" class="notification success">
      {{ successMessage }}
    </div>
    <div v-if="errorMessage" class="notification error">
      {{ errorMessage }}
    </div>
  </div>
</template>

<script>
import { mapState, mapGetters, mapActions } from 'vuex';
import InvestmentDetailInline from './InvestmentDetailInline.vue';
import AccountForm from '@/components/Investment/AccountForm.vue';
import DocumentUploadModal from '@/components/Shared/DocumentUploadModal.vue';
import Holdings from '@/components/Investment/Holdings.vue';
import Performance from '@/components/Investment/Performance.vue';
import PortfolioOptimization from '@/components/Investment/PortfolioOptimization.vue';
import Goals from '@/components/Investment/Goals.vue';
import GoalProjection from '@/components/Investment/GoalProjection.vue';
import AssetLocationOptimizer from '@/components/Investment/AssetLocationOptimizer.vue';
import WrapperOptimizer from '@/components/Investment/WrapperOptimizer.vue';
import FeeBreakdown from '@/components/Investment/FeeBreakdown.vue';
import TaxEfficiencyPanel from '@/components/Investment/TaxEfficiencyPanel.vue';
import PortfolioStrategyPanel from '@/views/Investment/PortfolioStrategyPanel.vue';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'InvestmentList',

  mixins: [currencyMixin],

  components: {
    InvestmentDetailInline,
    AccountForm,
    DocumentUploadModal,
    Holdings,
    Performance,
    PortfolioOptimization,
    Goals,
    GoalProjection,
    AssetLocationOptimizer,
    WrapperOptimizer,
    FeeBreakdown,
    TaxEfficiencyPanel,
    PortfolioStrategyPanel,
  },

  data() {
    return {
      selectedAccount: null,
      showAccountForm: false,
      showUploadModal: false,
      editingAccount: null,
      successMessage: null,
      errorMessage: null,
      successTimeout: null,
      errorTimeout: null,
      activePortfolioTab: 'holdings',
      portfolioTabs: [
        { id: 'holdings', label: 'Holdings' },
        { id: 'performance', label: 'Performance' },
        { id: 'optimization', label: 'Optimisation' },
        { id: 'goals', label: 'Goals' },
        { id: 'taxefficiency', label: 'Tax Efficiency' },
        { id: 'fees', label: 'Fees' },
        { id: 'strategy', label: 'Strategy' },
      ],
    };
  },

  computed: {
    ...mapState('investment', ['loading', 'error', 'recommendations']),
    ...mapGetters('investment', [
      'accounts',
      'totalPortfolioValue',
      'holdingsCount',
    ]),

    hasRecommendations() {
      return this.recommendations?.recommendations?.length > 0;
    },

    recommendationCount() {
      return this.recommendations?.recommendation_count || 0;
    },

    topRecommendations() {
      if (!this.recommendations?.recommendations) return [];
      return this.recommendations.recommendations.slice(0, 3);
    },

    // Calculate portfolio-wide diversification score (value-weighted average)
    portfolioDiversificationScore() {
      if (!this.accounts.length) return 0;

      let totalWeightedScore = 0;
      let totalValue = 0;

      for (const account of this.accounts) {
        const accountValue = parseFloat(account.current_value) || 0;
        if (accountValue <= 0) continue;

        const holdings = account.holdings || [];
        if (!holdings.length) continue;

        const accountScore = this.calculateAccountDiversificationScore(account);
        totalWeightedScore += accountScore * accountValue;
        totalValue += accountValue;
      }

      return totalValue > 0 ? Math.round(totalWeightedScore / totalValue) : 0;
    },

    diversificationLabel() {
      const score = this.portfolioDiversificationScore;
      if (score >= 80) return 'Excellent';
      if (score >= 60) return 'Good';
      if (score >= 40) return 'Fair';
      return 'Poor';
    },

    // Calculate weighted portfolio gross return
    portfolioGrossReturn() {
      if (!this.accounts.length) return null;

      let totalWeightedReturn = 0;
      let totalValue = 0;

      for (const account of this.accounts) {
        const accountValue = parseFloat(account.current_value) || 0;
        if (accountValue <= 0) continue;

        const accountReturn = this.calculateAccountGrossReturn(account);
        if (accountReturn !== null) {
          totalWeightedReturn += accountReturn * accountValue;
          totalValue += accountValue;
        }
      }

      return totalValue > 0 ? totalWeightedReturn / totalValue : null;
    },

    // Calculate weighted portfolio net return (after fees)
    portfolioNetReturn() {
      if (!this.accounts.length) return null;

      let totalWeightedReturn = 0;
      let totalValue = 0;

      for (const account of this.accounts) {
        const accountValue = parseFloat(account.current_value) || 0;
        if (accountValue <= 0) continue;

        const grossReturn = this.calculateAccountGrossReturn(account);
        const fees = this.calculateAccountFees(account);

        if (grossReturn !== null) {
          const netReturn = grossReturn - fees;
          totalWeightedReturn += netReturn * accountValue;
          totalValue += accountValue;
        }
      }

      return totalValue > 0 ? totalWeightedReturn / totalValue : null;
    },
  },

  methods: {
    ...mapActions('investment', ['fetchInvestmentData', 'analyseInvestment', 'fetchRecommendations', 'createAccount', 'updateAccount', 'deleteAccount']),
    ...mapActions('netWorth', ['setDetailView']),

    selectAccount(account) {
      this.selectedAccount = account;
      this.setDetailView(true);
    },

    goToStrategy() {
      const base = this.$route.path.startsWith('/preview') ? '/preview' : '';
      this.$router.push(`${base}/net-worth/strategy-detail`);
    },

    getPriorityLabel(priority) {
      if (priority <= 2) return 'High';
      if (priority <= 3) return 'Medium';
      return 'Low';
    },

    getPriorityBadgeClass(priority) {
      if (priority <= 2) return 'bg-red-500 text-white';
      if (priority <= 3) return 'bg-blue-500 text-white';
      return 'bg-blue-500 text-white';
    },

    handleStrategyNavigate(tab) {
      // Navigate to another tab from strategy panel
      const tabMap = {
        fees: 'fees',
        rebalancing: 'holdings', // Rebalancing is shown in holdings/account detail
        taxefficiency: 'taxefficiency',
      };
      if (tabMap[tab]) {
        this.activePortfolioTab = tabMap[tab];
      }
    },

    clearSelection() {
      this.selectedAccount = null;
      this.setDetailView(false);

      // In preview mode, don't reload from API (changes are session-only)
      const isPreview = this.$store.getters['preview/isPreviewMode'];
      if (!isPreview) {
        this.loadData();
      }
    },

    handleAccountDeleted() {
      this.selectedAccount = null;
      this.setDetailView(false);
      this.loadData();
      this.successMessage = 'Investment account deleted successfully';
      if (this.successTimeout) clearTimeout(this.successTimeout);
      this.successTimeout = setTimeout(() => {
        this.successMessage = null;
      }, 5000);
    },

    async handleAccountUpdated() {
      // In preview mode, we handle updates via handlePreviewAccountUpdated
      const isPreview = this.$store.getters['preview/isPreviewMode'];
      if (!isPreview) {
        await this.loadData();
        // Refresh selectedAccount reference from the updated store
        if (this.selectedAccount) {
          const fresh = this.accounts.find(a => a.id === this.selectedAccount.id);
          if (fresh) this.selectedAccount = fresh;
        }
      }
    },

    handlePreviewAccountUpdated(updatedAccount) {
      // In preview mode, update the selected account locally
      // This keeps the changes visible in the UI until page refresh
      this.selectedAccount = updatedAccount;
    },

    closeAccountForm() {
      this.showAccountForm = false;
      this.editingAccount = null;
    },

    async handleAccountSave(data) {
      try {
        if (this.editingAccount) {
          await this.updateAccount({ id: this.editingAccount.id, data });
        } else {
          await this.createAccount(data);
        }

        // In preview mode, don't reload from API as the data wasn't persisted
        const isPreview = this.$store.getters['preview/isPreviewMode'];
        if (!isPreview) {
          await this.loadData();
        }

        this.successMessage = 'Investment account saved successfully';
        if (this.successTimeout) clearTimeout(this.successTimeout);
        this.successTimeout = setTimeout(() => {
          this.successMessage = null;
        }, 5000);
      } catch (error) {
        console.error('Failed to save account:', error);
        this.errorMessage = 'Failed to save account. Please try again.';
        if (this.errorTimeout) clearTimeout(this.errorTimeout);
        this.errorTimeout = setTimeout(() => {
          this.errorMessage = null;
        }, 5000);
      }

      this.closeAccountForm();
    },

    async handleDocumentSaved() {
      this.showUploadModal = false;
      await this.loadData();
    },

    async loadData() {
      try {
        await this.fetchInvestmentData();
        // Fetch recommendations separately (same pattern as Retirement StrategiesTab)
        await this.fetchRecommendations();
      } catch (error) {
        console.error('Failed to load investment data:', error);
      }
    },

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
        'private_company': 'Private Co',
        'crowdfunding': 'Crowdfunding',
        'saye': 'Save As You Earn',
        'csop': 'Company Share Option Plan',
        'emi': 'Enterprise Management Incentive',
        'unapproved_options': 'Options',
        'rsu': 'Restricted Stock Units',
        'other': 'Other',
      };
      return types[type] || type;
    },

    getAccountDisplayName(account) {
      // For employee share schemes, show employer name
      const employeeShareSchemes = ['saye', 'csop', 'emi', 'unapproved_options', 'rsu'];
      if (employeeShareSchemes.includes(account.account_type)) {
        return account.employer_name || account.provider || 'Unnamed Scheme';
      }
      // For private investments, show company trading name or legal name
      const privateTypes = ['private_company', 'crowdfunding'];
      if (privateTypes.includes(account.account_type)) {
        return account.company_trading_name || account.company_legal_name || account.provider || 'Unnamed Investment';
      }
      // For regular investments, show provider
      return account.provider || 'Unnamed Account';
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
        private_company: 'badge-alternative',
        crowdfunding: 'badge-alternative',
        saye: 'badge-employee',
        csop: 'badge-employee',
        emi: 'badge-employee',
        unapproved_options: 'badge-employee',
        rsu: 'badge-employee',
        other: 'badge-other',
      };
      return classes[type] || 'badge-other';
    },

    getReturnColorClass(value) {
      if (!value && value !== 0) return 'text-gray-600';
      return value >= 0 ? 'text-green-600' : 'text-red-600';
    },

    getValueLabel(account) {
      const employeeShareSchemes = ['saye', 'csop', 'emi', 'unapproved_options', 'rsu'];
      const privateTypes = ['private_company', 'crowdfunding'];

      if (employeeShareSchemes.includes(account.account_type)) {
        return account.account_type === 'rsu' ? 'Grant Value' : 'Exercise Value';
      }
      if (privateTypes.includes(account.account_type)) {
        return 'Valuation';
      }
      return 'Current Value';
    },

    getDisplayValue(account) {
      const employeeShareSchemes = ['saye', 'csop', 'emi', 'unapproved_options', 'rsu'];
      const privateTypes = ['private_company', 'crowdfunding'];

      // Employee share schemes - calculate from units × price
      if (employeeShareSchemes.includes(account.account_type)) {
        const unitsGranted = parseFloat(account.units_granted) || 0;
        const exercisePrice = parseFloat(account.exercise_price) || 0;
        const marketValueAtGrant = parseFloat(account.market_value_at_grant) || 0;

        // RSUs: units × market value at grant
        if (account.account_type === 'rsu') {
          return unitsGranted * marketValueAtGrant;
        }

        // Options (SAYE, CSOP, EMI, Unapproved): units × exercise price
        return unitsGranted * exercisePrice;
      }

      // Private investments - use latest valuation or investment amount
      if (privateTypes.includes(account.account_type)) {
        if (account.latest_valuation && parseFloat(account.latest_valuation) > 0) {
          return parseFloat(account.latest_valuation);
        }
        if (account.investment_amount && parseFloat(account.investment_amount) > 0) {
          return parseFloat(account.investment_amount);
        }
        return 0;
      }

      // Standard accounts - use current_value
      return parseFloat(account.current_value) || 0;
    },

    calculateAccountGrossReturn(account) {
      const holdings = account.holdings || [];
      if (!holdings.length) return null;

      let totalCostBasis = 0;
      let totalCurrentValue = 0;
      let weightedYears = 0;
      let totalValueForWeighting = 0;

      for (const holding of holdings) {
        const costBasis = parseFloat(holding.cost_basis) || 0;
        const currentValue = parseFloat(holding.current_value) || 0;

        if (costBasis > 0) {
          totalCostBasis += costBasis;
          totalCurrentValue += currentValue;

          // Calculate holding period (default 3 years if no purchase date)
          let years = 3;
          if (holding.purchase_date) {
            const purchaseDate = new Date(holding.purchase_date);
            const now = new Date();
            years = (now - purchaseDate) / (365.25 * 24 * 60 * 60 * 1000);
            if (years < 0.01) years = 0.01;
          }

          weightedYears += years * currentValue;
          totalValueForWeighting += currentValue;
        }
      }

      if (totalCostBasis <= 0) return null;

      const avgYears = totalValueForWeighting > 0 ? weightedYears / totalValueForWeighting : 3;
      const totalReturn = (totalCurrentValue - totalCostBasis) / totalCostBasis;

      // Annualize: linear for <3 months, compound for longer
      if (avgYears < 0.25) {
        return (totalReturn / avgYears) * 100;
      } else {
        return (Math.pow(1 + totalReturn, 1 / avgYears) - 1) * 100;
      }
    },

    calculateAccountFees(account) {
      let platformFee = 0;
      if (account.platform_fee_type === 'fixed') {
        const amount = parseFloat(account.platform_fee_amount) || 0;
        let annualAmount = amount;
        if (account.platform_fee_frequency === 'monthly') annualAmount = amount * 12;
        else if (account.platform_fee_frequency === 'quarterly') annualAmount = amount * 4;
        const acctValue = parseFloat(account.current_value) || 0;
        platformFee = acctValue > 0 ? (annualAmount / acctValue) * 100 : 0;
      } else {
        platformFee = parseFloat(account.platform_fee_percent) || 0;
      }
      const advisorFee = parseFloat(account.advisor_fee_percent) || 0;

      // Weighted average OCF from holdings
      let weightedOCF = 0;
      const holdings = account.holdings || [];
      const totalValue = holdings.reduce((sum, h) => sum + (parseFloat(h.current_value) || 0), 0);

      if (totalValue > 0) {
        for (const holding of holdings) {
          const value = parseFloat(holding.current_value) || 0;
          const ocf = parseFloat(holding.ocf_percent) || 0;
          weightedOCF += (value / totalValue) * ocf;
        }
      }

      return platformFee + advisorFee + weightedOCF;
    },

    // Calculate diversification score for a single account (mirrors backend DiversificationAnalyzer)
    calculateAccountDiversificationScore(account) {
      const holdings = account.holdings || [];
      if (!holdings.length) return 0;

      const totalValue = holdings.reduce((sum, h) => sum + (parseFloat(h.current_value) || 0), 0);
      if (totalValue <= 0) return 0;

      // Calculate HHI (Herfindahl-Hirschman Index)
      let hhi = 0;
      for (const holding of holdings) {
        const weight = (parseFloat(holding.current_value) || 0) / totalValue;
        hhi += weight * weight;
      }

      // Calculate concentration metrics
      const percentages = holdings
        .map(h => ((parseFloat(h.current_value) || 0) / totalValue) * 100)
        .sort((a, b) => b - a);

      const topHoldingPercent = percentages[0] || 0;
      const top3Percent = percentages.slice(0, 3).reduce((a, b) => a + b, 0);

      // Calculate asset class diversity
      const assetClasses = new Set();
      const classMap = {
        'uk_equity': 'equities', 'us_equity': 'equities', 'international_equity': 'equities',
        'equity': 'equities', 'fund': 'equities', 'etf': 'equities',
        'bond': 'bonds', 'cash': 'cash', 'alternative': 'alternatives', 'property': 'alternatives',
      };
      for (const holding of holdings) {
        const assetType = (holding.asset_type || 'equity').toLowerCase();
        assetClasses.add(classMap[assetType] || 'equities');
      }

      // Score calculation (mirrors backend DiversificationAnalyzer)
      let score = 100;

      // HHI penalty (0-40 points)
      if (hhi >= 0.5) score -= 40;
      else if (hhi >= 0.25) score -= 25;
      else if (hhi >= 0.15) score -= 10;

      // Concentration penalties (0-30 points)
      if (topHoldingPercent > 40) score -= 20;
      else if (topHoldingPercent > 25) score -= 10;

      if (top3Percent > 80) score -= 10;
      else if (top3Percent > 60) score -= 5;

      // Asset class diversity bonus/penalty
      const classCount = assetClasses.size;
      if (classCount >= 4) score += 10;
      else if (classCount === 1) score -= 20;
      else if (classCount === 2) score -= 10;

      return Math.max(0, Math.min(100, score));
    },
  },

  beforeUnmount() {
    if (this.successTimeout) clearTimeout(this.successTimeout);
    if (this.errorTimeout) clearTimeout(this.errorTimeout);
  },

  async mounted() {
    this.setDetailView(false);
    await this.loadData();
  },
};
</script>

<style scoped>
.investment-list {
  padding: 24px;
  box-sizing: border-box;
  width: 100%;
  max-width: 100%;
}

.list-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
  flex-wrap: wrap;
  gap: 16px;
}

.title-row {
  display: flex;
  align-items: center;
  gap: 16px;
}

.list-title {
  font-size: 24px;
  font-weight: 700;
  @apply text-gray-900;
  margin: 0;
}

.risk-profile-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  @apply bg-blue-50;
  @apply text-blue-600;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 500;
  text-decoration: none;
  transition: all 0.2s;
}

.risk-profile-link:hover {
  @apply bg-blue-100;
}

.risk-icon {
  width: 16px;
  height: 16px;
}

.header-buttons {
  display: flex;
  gap: 12px;
}

.add-account-button {
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

.add-account-button:hover {
  @apply bg-primary-600;
}

.upload-button {
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

.upload-button:hover {
  @apply bg-blue-50;
}

.button-icon {
  width: 20px;
  height: 20px;
}

/* Two-column layout: Account Cards (left) + Performance Chart (right) */
.main-content-grid {
  display: grid;
  grid-template-columns: 280px 1fr;
  gap: 20px;
  margin-bottom: 24px;
}

.accounts-column {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.compact-account-card {
  background: white;
  border-radius: 8px;
  @apply border border-gray-200;
  padding: 12px;
  cursor: pointer;
  transition: all 0.2s ease;
  position: relative;
}

.joint-badge-corner {
  position: absolute;
  top: 8px;
  right: 8px;
  z-index: 10;
  @apply px-2 py-0.5 text-xs font-medium rounded-full bg-purple-100 text-purple-800;
}

.retirement-badge-corner {
  position: absolute;
  top: 8px;
  right: 8px;
  z-index: 10;
  @apply px-2 py-0.5 text-xs font-medium rounded-full bg-teal-100 text-teal-800;
}

.retirement-badge-corner.has-joint {
  top: 30px;
}

.compact-account-card:hover {
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
  @apply border-primary-500;
}

.performance-section {
  min-width: 0;
}

.investment-card {
  background: white;
  border-radius: 12px;
  @apply border border-gray-200;
  padding: 16px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.investment-card:hover {
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  transform: translateY(-2px);
  @apply border-primary-500;
}

.card-header {
  display: flex;
  justify-content: flex-start;
  align-items: center;
  margin-bottom: 8px;
  flex-wrap: wrap;
  gap: 6px;
}

.compact-account-card .card-header {
  margin-bottom: 6px;
}

.badge {
  display: inline-block;
  padding: 3px 8px;
  font-size: 10px;
  font-weight: 600;
  border-radius: 4px;
}

.compact-account-card .badge {
  padding: 2px 6px;
  font-size: 9px;
}

.badge-individual {
  @apply bg-gray-100;
  @apply text-gray-700;
}

.badge-joint {
  @apply bg-purple-100;
  @apply text-purple-500;
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

.badge-alternative {
  @apply bg-rose-100;
  @apply text-rose-800;
}

.badge-employee {
  @apply bg-teal-100;
  @apply text-teal-800;
}

.card-content {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.compact-account-card .card-content {
  gap: 4px;
}

.account-provider {
  font-size: 18px;
  font-weight: 700;
  @apply text-gray-900;
  margin: 0;
}

.compact-account-card .account-provider {
  font-size: 14px;
  font-weight: 600;
}

.account-name-text {
  font-size: 14px;
  @apply text-gray-500;
  margin: 0;
  min-height: 20px;
}

.compact-account-card .account-name-text {
  font-size: 12px;
  min-height: auto;
}

.account-details {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 8px;
  padding-top: 12px;
  @apply border-t border-gray-200;
}

.compact-account-card .account-details {
  gap: 4px;
  margin-top: 6px;
  padding-top: 8px;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.detail-label {
  font-size: 14px;
  @apply text-gray-500;
}

.detail-value {
  font-size: 16px;
  @apply text-gray-900;
  font-weight: 700;
}

.compact-account-card .detail-label {
  font-size: 11px;
}

.compact-account-card .detail-value {
  font-size: 13px;
  font-weight: 600;
}

.isa-info {
  @apply bg-green-50;
  border-radius: 6px;
  padding: 8px;
  margin: 4px 0;
}

.compact-account-card .isa-info {
  padding: 6px;
  margin: 2px 0;
}

.loading-state,
.error-state,
.empty-state {
  text-align: center;
  padding: 60px 20px;
}

.loading-state p,
.error-state p {
  @apply text-gray-500;
  font-size: 16px;
  margin: 0;
}

.error-state p {
  @apply text-red-500;
}

.empty-state {
  background: white;
  border-radius: 12px;
  padding: 80px 40px;
  @apply border-2 border-dashed border-gray-300;
}

.empty-icon {
  width: 64px;
  height: 64px;
  @apply text-gray-400;
  margin: 0 auto 16px;
}

.empty-state p {
  @apply text-gray-500;
  font-size: 18px;
  font-weight: 600;
  margin: 0 0 8px 0;
}

.empty-subtitle {
  @apply text-gray-400;
  font-size: 14px;
  font-weight: 400;
}

/* Wealth Summary */
.wealth-summary {
  background: white;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  @apply border border-gray-200;
}


.summary-title {
  font-size: 18px;
  font-weight: 600;
  @apply text-gray-900;
  margin: 0 0 20px 0;
}

.summary-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 24px;
}

.summary-item {
  padding-left: 16px;
  border-left: 4px solid;
}

.summary-item.portfolio {
  @apply border-l-primary-500;
}

.summary-item.returns.positive {
  @apply border-l-green-500;
}

.summary-item.returns.negative {
  @apply border-l-red-500;
}

.summary-item.diversification {
  @apply border-l-purple-500;
}

.summary-label {
  font-size: 14px;
  @apply text-gray-500;
  margin: 0 0 4px 0;
}

.summary-value {
  font-size: 24px;
  font-weight: 700;
  @apply text-gray-900;
  margin: 0;
}

.summary-count {
  font-size: 13px;
  @apply text-gray-400;
  margin: 4px 0 0 0;
}

.return-values {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.return-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.return-label {
  font-size: 13px;
  @apply text-gray-500;
}

.return-value {
  font-size: 18px;
  font-weight: 700;
}

/* Portfolio Features Section */
.portfolio-features {
  background: white;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  @apply border border-gray-200;
}

.features-title {
  font-size: 18px;
  font-weight: 600;
  @apply text-gray-900;
  margin: 0 0 16px 0;
}

.features-tabs {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  margin-bottom: 20px;
  padding-bottom: 16px;
  @apply border-b border-gray-200;
}

.feature-tab {
  padding: 8px 16px;
  @apply bg-gray-100;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  @apply text-gray-500;
  cursor: pointer;
  transition: all 0.2s;
}

.feature-tab:hover {
  @apply bg-gray-200;
  @apply text-gray-700;
}

.feature-tab.active {
  @apply bg-primary-500;
  color: white;
}

.features-content {
  min-height: 200px;
}

.coming-soon-wrapper {
  position: relative;
}

.coming-soon-banner {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%) rotate(-12deg);
  @apply bg-blue-100;
  @apply border-2 border-blue-500;
  border-radius: 8px;
  padding: 16px 32px;
  z-index: 10;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.notification {
  position: fixed;
  top: 20px;
  right: 20px;
  padding: 16px 20px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  z-index: 100;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  animation: slideIn 0.3s ease-out;
}

.notification.success {
  @apply bg-green-500 text-white;
}

.notification.error {
  @apply bg-red-500 text-white;
}

@keyframes slideIn {
  from {
    transform: translateX(400px);
    opacity: 0;
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
}

@media (max-width: 1024px) {
  .main-content-grid {
    grid-template-columns: 1fr;
  }

  .accounts-column {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    order: 2;
  }

  .performance-section {
    order: 1;
  }
}

@media (max-width: 768px) {
  .investment-list {
    padding: 16px;
  }

  .list-header {
    flex-direction: column;
    align-items: flex-start;
  }

  .header-buttons {
    width: 100%;
    flex-direction: column;
  }

  .add-account-button,
  .upload-button {
    width: 100%;
    justify-content: center;
  }

  .main-content-grid {
    grid-template-columns: 1fr;
    gap: 16px;
  }

  .accounts-column {
    grid-template-columns: 1fr;
  }

  .features-tabs {
    overflow-x: auto;
    flex-wrap: nowrap;
    -webkit-overflow-scrolling: touch;
  }

  .feature-tab {
    flex-shrink: 0;
  }
}
</style>
