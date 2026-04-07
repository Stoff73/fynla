<template>
  <div class="investment-list overflow-hidden">
    <ModuleStatusBar />
    <!-- Investment Detail View (when an account is selected) -->
    <InvestmentProjections
      v-if="selectedAccount"
      :account="selectedAccount"
      @back="clearSelection"
      @deleted="handleAccountDeleted"
      @updated="handleAccountUpdated"
      @account-updated="handlePreviewAccountUpdated"
    />

    <!-- Investment List View (default) -->
    <template v-else>
      <!-- Risk Mismatch Warning -->
      <RiskMismatchWarning
        v-if="riskMismatch"
        :mismatch="riskMismatch"
      />

      <div v-if="loading" class="loading-state">
        <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-violet-600"></div>
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
        <button v-preview-disabled="'add'" @click="editingAccount = null; showAccountForm = true;" class="add-first-button">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
          </svg>
          Add Your First Investment
        </button>
      </div>

      <!-- Two-column layout: Account cards left, chart right -->
      <div v-else class="investment-layout">
        <!-- Account Cards (left column) -->
        <div class="accounts-column">
          <div
            v-for="account in accounts"
            :key="account.id"
            @click="selectAccount(account)"
            class="compact-account-card module-gradient"
          >
              <!-- Joint Badge - Top Right Corner (only if share < 100%) -->
              <span
                v-if="account.ownership_type === 'joint' && (!account.ownership_percentage || account.ownership_percentage < 100)"
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
                      <span class="detail-value text-violet-600">{{ formatCurrency(getDisplayValue(account) * ((account.ownership_percentage || 50) / 100)) }}</span>
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
                      <span class="detail-value text-spring-600">{{ formatCurrency(account.isa_subscription_current_year || 0) }}</span>
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

        <!-- Portfolio Performance (right column) -->
        <div class="chart-column">
          <div class="performance-section module-gradient">
            <Performance @navigate-to-tab="activePortfolioTab = $event" />
          </div>
        </div>
      </div>

      <!-- Data integration notice -->
      <div class="bg-eggshell-500 rounded-lg border border-light-gray p-5 mt-5">
        <div class="flex items-start gap-3 mb-3">
          <h4 class="text-base font-semibold text-horizon-500">Analytics</h4>
          <span class="text-xs font-semibold text-neutral-600 bg-neutral-200 px-2.5 py-0.5 rounded-full whitespace-nowrap flex-shrink-0 mt-0.5">Coming Soon</span>
        </div>
        <p class="text-sm text-neutral-500 mb-3">
          We will be connecting this section to give an in-depth view of investments and holdings. For now we offer a Monte Carlo (1,000 iterations) for a simple forward look, once connected we can include the past data for your account and holdings.
        </p>
        <div class="flex flex-wrap gap-3">
          <div class="flex items-center gap-2 bg-white rounded-lg border border-light-gray px-3 py-2">
            <span class="text-sm font-semibold text-horizon-500">Bloomberg</span>
          </div>
          <div class="flex items-center gap-2 bg-white rounded-lg border border-light-gray px-3 py-2">
            <span class="text-sm font-semibold text-horizon-500">Morningstar</span>
          </div>
          <div class="flex items-center gap-2 bg-white rounded-lg border border-light-gray px-3 py-2">
            <span class="text-sm font-semibold text-horizon-500">FE Analytics</span>
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
              <p class="text-2xl font-bold text-violet-700">Coming Soon</p>
            </div>
            <div class="opacity-50">
              <PortfolioOptimization />
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
    <Teleport to="body">
      <AccountForm
        :show="showAccountForm"
        :account="editingAccount"
        :is-edit="!!editingAccount"
        @close="closeAccountForm"
        @save="handleAccountSave"
      />
    </Teleport>

    <!-- Document Upload Modal -->
    <Teleport to="body">
      <DocumentUploadModal
        v-if="showUploadModal"
        document-type="investment_statement"
        @close="showUploadModal = false"
        @saved="handleDocumentSaved"
        @manual-entry="showUploadModal = false; showAccountForm = true;"
      />
    </Teleport>

    <!-- Success/Error Messages -->
    <div v-if="successMessage" class="notification success animate-slide-in-right">
      {{ successMessage }}
    </div>
    <div v-if="errorMessage" class="notification error animate-slide-in-right">
      {{ errorMessage }}
    </div>
  </div>
</template>

<script>
import { mapState, mapGetters, mapActions } from 'vuex';
import InvestmentProjections from './InvestmentProjections.vue';
import AccountForm from '@/components/Investment/AccountForm.vue';
import DocumentUploadModal from '@/components/Shared/DocumentUploadModal.vue';
import Holdings from '@/components/Investment/Holdings.vue';
import Performance from '@/components/Investment/Performance.vue';
import PortfolioOptimization from '@/components/Investment/PortfolioOptimization.vue';
import AssetLocationOptimizer from '@/components/Investment/AssetLocationOptimizer.vue';
import WrapperOptimizer from '@/components/Investment/WrapperOptimizer.vue';
import FeeBreakdown from '@/components/Investment/FeeBreakdown.vue';
import TaxEfficiencyPanel from '@/components/Investment/TaxEfficiencyPanel.vue';
import RiskMismatchWarning from '@/components/Investment/RiskMismatchWarning.vue';
import ModuleStatusBar from '@/components/Shared/ModuleStatusBar.vue';
import riskService from '@/services/riskService';
import { currencyMixin } from '@/mixins/currencyMixin';

import logger from '@/utils/logger';
export default {
  name: 'InvestmentList',

  mixins: [currencyMixin],

  components: {
    InvestmentProjections,
    AccountForm,
    DocumentUploadModal,
    Holdings,
    Performance,
    PortfolioOptimization,
    AssetLocationOptimizer,
    WrapperOptimizer,
    FeeBreakdown,
    TaxEfficiencyPanel,
    RiskMismatchWarning,
    ModuleStatusBar,
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
      riskMismatch: null,
      activePortfolioTab: 'holdings',
      portfolioTabs: [
        { id: 'holdings', label: 'Holdings' },
        { id: 'performance', label: 'Performance' },
        { id: 'optimization', label: 'Optimisation' },
        { id: 'taxefficiency', label: 'Tax Efficiency' },
        { id: 'fees', label: 'Fees' },
      ],
    };
  },

  computed: {
    ...mapState('investment', ['loading', 'error']),
    ...mapGetters('investment', [
      'accounts',
      'totalPortfolioValue',
      'holdingsCount',
    ]),
    ...mapGetters('subNav', ['pendingAction', 'actionCounter']),

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

  watch: {
    actionCounter() {
      if (this.pendingAction === 'addAccount') {
        this.showAccountForm = true;
        this.$store.dispatch('subNav/consumeCta');
      } else if (this.pendingAction === 'uploadStatement') {
        this.showUploadModal = true;
        this.$store.dispatch('subNav/consumeCta');
      }
    },
    '$store.state.aiFormFill.pendingFill'(fill) {
      if (!fill) return;
      if (fill.entityType === 'investment_account') {
        if (fill.mode === 'edit' && fill.entityId) {
          const record = this.accounts.find(a => a.id === fill.entityId);
          if (record) {
            this.editingAccount = record;
            this.showAccountForm = true;
          }
        } else {
          this.editingAccount = null;
          this.showAccountForm = true;
        }
      } else if (fill.entityType === 'investment_holding') {
        // Navigate into the account detail view so the holding form can open
        const accountId = fill.fields?.investment_account_id;
        if (accountId) {
          const account = this.accounts.find(a => a.id === accountId);
          if (account) {
            this.selectAccount(account);
          }
        }
      }
    },
  },

  methods: {
    ...mapActions('investment', ['fetchInvestmentData', 'analyseInvestment', 'fetchRecommendations', 'createAccount', 'updateAccount', 'deleteAccount']),
    ...mapActions('netWorth', ['setDetailView']),

    selectAccount(account) {
      this.selectedAccount = account;
      this.setDetailView(true);
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
          await this.updateAccount({ id: this.editingAccount.id, accountData: data });
        } else {
          await this.createAccount(data);
        }

        // Complete AI fill if this was an AI-driven save
        if (this.$store.state.aiFormFill.pendingFill) {
          this.$store.dispatch('aiFormFill/completeFill');
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
        logger.error('Failed to save account:', error);
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
        // Check for risk mismatch
        await this.loadRiskMismatch();
      } catch (error) {
        logger.error('Failed to load investment data:', error);
      }
    },

    async loadRiskMismatch() {
      try {
        const response = await riskService.getProfile();
        if (response.data?.risk_mismatch) {
          this.riskMismatch = response.data.risk_mismatch;
        }
      } catch {
        // Non-critical - silently ignore
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
      if (!value && value !== 0) return 'text-neutral-500';
      return value >= 0 ? 'text-spring-600' : 'text-raspberry-600';
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

      // Private investments - use latest valuation, current value, or investment amount
      if (privateTypes.includes(account.account_type)) {
        if (account.latest_valuation && parseFloat(account.latest_valuation) > 0) {
          return parseFloat(account.latest_valuation);
        }
        if (account.current_value && parseFloat(account.current_value) > 0) {
          return parseFloat(account.current_value);
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
    // Check for pendingFill that was set before this component mounted
    const fill = this.$store.state.aiFormFill?.pendingFill;
    if (fill && fill.entityType === 'investment_account' && fill.mode !== 'edit') {
      this.editingAccount = null;
      this.showAccountForm = true;
    } else if (fill && fill.entityType === 'investment_holding') {
      const accountId = fill.fields?.investment_account_id;
      if (accountId) {
        const account = this.accounts.find(a => a.id === accountId);
        if (account) {
          this.selectAccount(account);
        }
      }
    }

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
  @apply bg-eggshell-500;
}


.investment-layout {
  display: grid;
  grid-template-columns: 300px 1fr;
  gap: 20px;
  align-items: start;
}

.accounts-column {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.chart-column {
  min-width: 0;
}

.compact-account-card {
  background: white;
  border-radius: 8px;
  @apply border border-light-gray;
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
  @apply border-horizon-300;
}

.performance-section {
  min-width: 0;
  background: white;
  border-radius: 12px;
  padding: 24px;
  @apply border border-light-gray;
  transition: border-color 0.2s;
}

.performance-section:hover {
  @apply border-horizon-300;
}

.investment-card {
  background: white;
  border-radius: 12px;
  @apply border border-light-gray;
  padding: 16px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.investment-card:hover {
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  transform: translateY(-2px);
  @apply border-raspberry-500;
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
  @apply bg-savannah-100;
  @apply text-neutral-500;
}

.badge-joint {
  @apply bg-purple-100;
  @apply text-purple-500;
}

.badge-trust {
  @apply bg-violet-100;
  @apply text-violet-800;
}


.badge-other {
  @apply bg-savannah-100;
  @apply text-neutral-500;
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
  @apply text-horizon-500;
  margin: 0;
}

.compact-account-card .account-provider {
  font-size: 14px;
  font-weight: 600;
}

.account-name-text {
  font-size: 14px;
  @apply text-neutral-500;
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
  @apply border-t border-light-gray;
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
  @apply text-neutral-500;
}

.detail-value {
  font-size: 16px;
  @apply text-horizon-500;
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
  @apply bg-spring-50;
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
  @apply text-neutral-500;
  font-size: 16px;
  margin: 0;
}

.error-state p {
  @apply text-raspberry-500;
}

.empty-state {
  border-radius: 12px;
  padding: 80px 40px;
  @apply bg-light-blue-100 border border-light-gray;
}

.empty-icon {
  width: 64px;
  height: 64px;
  @apply text-horizon-400;
  margin: 0 auto 16px;
}

.empty-state p {
  @apply text-neutral-500;
  font-size: 18px;
  font-weight: 600;
  margin: 0 0 8px 0;
}

.empty-subtitle {
  @apply text-horizon-400;
  font-size: 14px;
  font-weight: 400;
}

.add-first-button {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  margin-top: 24px;
  padding: 12px 24px;
  @apply bg-horizon-500;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}

.add-first-button:hover {
  @apply bg-horizon-600;
}

/* Wealth Summary */
.wealth-summary {
  background: white;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  @apply border border-light-gray;
}


.summary-title {
  font-size: 18px;
  font-weight: 600;
  @apply text-horizon-500;
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
  @apply border-l-raspberry-500;
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
  @apply text-neutral-500;
  margin: 0 0 4px 0;
}

.summary-value {
  font-size: 24px;
  font-weight: 700;
  @apply text-horizon-500;
  margin: 0;
}

.summary-count {
  font-size: 13px;
  @apply text-horizon-400;
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
  @apply text-neutral-500;
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
  @apply border border-light-gray;
}

.features-title {
  font-size: 18px;
  font-weight: 600;
  @apply text-horizon-500;
  margin: 0 0 16px 0;
}

.features-tabs {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  margin-bottom: 20px;
  padding-bottom: 16px;
  @apply border-b border-light-gray;
}

.feature-tab {
  padding: 8px 16px;
  @apply bg-savannah-100;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  @apply text-neutral-500;
  cursor: pointer;
  transition: all 0.2s;
}

.feature-tab:hover {
  @apply bg-savannah-200;
  @apply text-neutral-500;
}

.feature-tab.active {
  @apply bg-raspberry-500;
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
  @apply bg-violet-100;
  @apply border-2 border-violet-500;
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
}

.notification.success {
  @apply bg-spring-500 text-white;
}

.notification.error {
  @apply bg-raspberry-500 text-white;
}

@media (max-width: 1024px) {
  .investment-layout {
    grid-template-columns: 1fr;
  }

  .accounts-column {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  }
}

@media (max-width: 768px) {
  .investment-list {
    padding: 16px;
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
