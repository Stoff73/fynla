<template>
  <div class="cash-overview module-gradient">
    <ModuleStatusBar />
    <!-- Account Detail View (when account selected) -->
    <SavingsAccountDetailInline
      v-if="selectedAccount"
      :account-id="selectedAccount.id"
      @back="clearSelection"
      @deleted="handleAccountDeleted"
    />

    <!-- Main Dashboard View -->
    <template v-else>
      <!-- Loading State -->
      <div v-if="loading" class="flex justify-center items-center py-12">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-raspberry-500"></div>
      </div>

      <!-- Error State -->
      <div
        v-else-if="error"
        class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6"
      >
        <div class="flex">
          <div class="flex-shrink-0">
            <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
            </svg>
          </div>
          <div class="ml-3">
            <p class="text-sm text-red-700">{{ error }}</p>
          </div>
        </div>
      </div>

      <!-- Main 3-Column Layout (Preview Users Only) -->
      <div v-else-if="isPreviewMode" class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Left Panel - Account Summary (3 cols) -->
        <div class="lg:col-span-3">
          <AccountSummaryPanel
            :accounts="accounts"
            :credit-cards="creditCards"
            :monthly-income="monthlyIncome"
            :monthly-expenditure="currentAccountExpenditure"
            @select-account="selectAccount"
            @add-account="openAddAccountModal"
          />
        </div>

        <!-- Center Panel - Insights (6 cols) -->
        <div class="lg:col-span-6 space-y-6">
          <CashInsightsPanel :financial-commitments="financialCommitments" />
        </div>

        <!-- Right Panel - Actions (3 cols) -->
        <div class="lg:col-span-3 space-y-6">
          <CashActionsPanel />
        </div>
      </div>

      <!-- Real Users: Account Cards + Open Banking -->
      <div v-else class="space-y-6 pt-6">
        <!-- Account Cards - 4 Column Grid -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
          <!-- Current Account Card -->
          <div class="account-card module-gradient">
            <div class="card-header">
              <h4 class="card-title">Current Accounts</h4>
              <button @click="openAddAccountModal('current_account')" class="add-icon-btn" title="Add Account">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
              </button>
            </div>
            <template v-if="currentAccounts.length > 0">
              <div class="card-total">
                <span class="text-2xl sm:text-3xl lg:text-4xl font-black text-spring-600">{{ formatCurrency(currentAccountsTotal) }}</span>
              </div>
              <div
                v-for="account in currentAccounts"
                :key="account.id"
                class="account-item"
                @click="selectAccount(account)"
              >
                <div class="account-info">
                  <span class="account-name" :title="account.institution || 'Current Account'">
                    {{ account.institution || 'Current Account' }}
                    <span v-if="isJointAccount(account)" class="joint-badge">(Joint)</span>
                    <span v-else-if="account.ownership_type === 'tenants_in_common'" class="joint-badge">
                      (TiC{{ account.ownership_percentage ? ' - ' + account.ownership_percentage + '%' : '' }})
                    </span>
                  </span>
                </div>
                <div class="account-balances">
                  <span class="account-balance">{{ formatCurrency(getUserShare(account)) }}</span>
                  <span v-if="isJointAccount(account) || account.ownership_type === 'tenants_in_common'" class="total-balance">
                    Total: {{ formatCurrency(account.current_balance) }}
                  </span>
                </div>
              </div>
            </template>
            <template v-else>
              <button @click="openAddAccountModal('current_account')" class="add-account-btn">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Add Account
              </button>
            </template>
          </div>

          <!-- Savings Account Card -->
          <div class="account-card module-gradient">
            <div class="card-header">
              <h4 class="card-title">Savings Accounts</h4>
              <button @click="openAddAccountModal('savings_account')" class="add-icon-btn" title="Add Account">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
              </button>
            </div>
            <template v-if="savingsAccounts.length > 0">
              <div class="card-total">
                <span class="text-2xl sm:text-3xl lg:text-4xl font-black text-spring-600">{{ formatCurrency(savingsAccountsTotal) }}</span>
              </div>
              <div
                v-for="account in savingsAccounts"
                :key="account.id"
                class="account-item"
                @click="selectAccount(account)"
              >
                <div class="account-info">
                  <span class="account-name" :title="account.institution || 'Savings Account'">
                    {{ account.institution || 'Savings Account' }}
                    <span v-if="isJointAccount(account)" class="joint-badge">(Joint)</span>
                    <span v-else-if="account.ownership_type === 'tenants_in_common'" class="joint-badge">
                      (TiC{{ account.ownership_percentage ? ' - ' + account.ownership_percentage + '%' : '' }})
                    </span>
                  </span>
                </div>
                <div class="account-balances">
                  <span class="account-balance">{{ formatCurrency(getUserShare(account)) }}</span>
                  <span v-if="isJointAccount(account) || account.ownership_type === 'tenants_in_common'" class="total-balance">
                    Total: {{ formatCurrency(account.current_balance) }}
                  </span>
                </div>
              </div>
            </template>
            <template v-else>
              <button @click="openAddAccountModal('savings_account')" class="add-account-btn">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Add Account
              </button>
            </template>
          </div>

          <!-- Cash ISA Card -->
          <div class="account-card module-gradient">
            <div class="card-header">
              <h4 class="card-title">Cash ISAs</h4>
              <button @click="openAddAccountModal('cash_isa')" class="add-icon-btn" title="Add Account">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
              </button>
            </div>
            <template v-if="isaAccounts.length > 0">
              <div class="card-total">
                <span class="text-2xl sm:text-3xl lg:text-4xl font-black text-spring-600">{{ formatCurrency(isaAccountsTotal) }}</span>
              </div>
              <div
                v-for="account in isaAccounts"
                :key="account.id"
                class="account-item"
                @click="selectAccount(account)"
              >
                <div class="account-info">
                  <span class="account-name" :title="account.institution || 'Cash ISA'">
                    {{ account.institution || 'Cash ISA' }}
                    <span v-if="isJointAccount(account)" class="joint-badge">(Joint)</span>
                    <span v-else-if="account.ownership_type === 'tenants_in_common'" class="joint-badge">
                      (TiC{{ account.ownership_percentage ? ' - ' + account.ownership_percentage + '%' : '' }})
                    </span>
                  </span>
                </div>
                <div class="account-balances">
                  <span class="account-balance">{{ formatCurrency(getUserShare(account)) }}</span>
                  <span v-if="isJointAccount(account) || account.ownership_type === 'tenants_in_common'" class="total-balance">
                    Total: {{ formatCurrency(account.current_balance) }}
                  </span>
                </div>
              </div>
            </template>
            <template v-else>
              <button @click="openAddAccountModal('cash_isa')" class="add-account-btn">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Add Account
              </button>
            </template>
          </div>

          <!-- NS&I Card -->
          <div class="account-card module-gradient">
            <div class="card-header">
              <h4 class="card-title">NS&I</h4>
              <button @click="openAddAccountModal('premium_bonds')" class="add-icon-btn" title="Add Account">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
              </button>
            </div>
            <template v-if="nsiAccounts.length > 0">
              <div class="card-total">
                <span class="text-2xl sm:text-3xl lg:text-4xl font-black text-spring-600">{{ formatCurrency(nsiAccountsTotal) }}</span>
              </div>
              <div
                v-for="account in nsiAccounts"
                :key="account.id"
                class="account-item"
                @click="selectAccount(account)"
              >
                <div class="account-info">
                  <span class="account-name" :title="account.institution || 'NS&I'">
                    {{ account.institution || 'NS&I' }}
                    <span v-if="isJointAccount(account)" class="joint-badge">(Joint)</span>
                    <span v-else-if="account.ownership_type === 'tenants_in_common'" class="joint-badge">
                      (TiC{{ account.ownership_percentage ? ' - ' + account.ownership_percentage + '%' : '' }})
                    </span>
                  </span>
                </div>
                <div class="account-balances">
                  <span class="account-balance">{{ formatCurrency(getUserShare(account)) }}</span>
                  <span v-if="isJointAccount(account) || account.ownership_type === 'tenants_in_common'" class="total-balance">
                    Total: {{ formatCurrency(account.current_balance) }}
                  </span>
                </div>
              </div>
            </template>
            <template v-else>
              <button @click="openAddAccountModal('premium_bonds')" class="add-account-btn">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Add Account
              </button>
            </template>
          </div>
        </div>

        <!-- Open Banking Card -->
        <div class="bg-light-blue-50 rounded-lg border border-light-blue-200 p-6">
          <div class="flex items-center gap-2.5 mb-4">
            <h3 class="text-lg font-semibold text-horizon-500">Open Banking</h3>
            <span class="text-xs font-semibold text-neutral-600 bg-neutral-200 px-2.5 py-0.5 rounded-full">Coming Soon</span>
          </div>
          <p class="text-sm text-neutral-500 mb-4">
            Securely connect your bank accounts to unlock powerful financial insights and automated tracking.
          </p>
          <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
            <div>
              <p class="font-medium text-horizon-500">Real-time Balances</p>
              <p class="text-neutral-500">Auto-sync all accounts</p>
            </div>
            <div>
              <p class="font-medium text-horizon-500">Budget Tracking</p>
              <p class="text-neutral-500">Set and monitor budgets</p>
            </div>
            <div>
              <p class="font-medium text-horizon-500">Credit Card Spending</p>
              <p class="text-neutral-500">Track and categorise</p>
            </div>
            <div>
              <p class="font-medium text-horizon-500">Cash Flow Forecast</p>
              <p class="text-neutral-500">Predict future balances</p>
            </div>
            <div>
              <p class="font-medium text-horizon-500">Spending Insights</p>
              <p class="text-neutral-500">Where your money goes</p>
            </div>
            <div>
              <p class="font-medium text-horizon-500">Payday Tracking</p>
              <p class="text-neutral-500">Income detection</p>
            </div>
            <div>
              <p class="font-medium text-horizon-500">Bill Reminders</p>
              <p class="text-neutral-500">Never miss a payment</p>
            </div>
            <div>
              <p class="font-medium text-horizon-500">Bank-Grade Security</p>
              <p class="text-neutral-500">Read-only access</p>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Save Account Modal -->
    <Teleport to="body">
      <SaveAccountModal
        v-if="showAccountModal"
        :account="editingAccount"
        :default-account-type="defaultAccountType"
        @save="handleSaveAccount"
        @close="closeAccountModal"
      />
    </Teleport>
  </div>
</template>

<script>
import { mapState, mapActions, mapGetters } from 'vuex';
import estateService from '@/services/estateService';
import userProfileService from '@/services/userProfileService';
import currencyMixin from '@/mixins/currencyMixin';
import AccountSummaryPanel from '@/components/Cash/AccountSummaryPanel.vue';
import CashInsightsPanel from '@/components/Cash/CashInsightsPanel.vue';
import CashActionsPanel from '@/components/Cash/CashActionsPanel.vue';
import SaveAccountModal from '@/components/Savings/SaveAccountModal.vue';
import SavingsAccountDetailInline from '@/views/Savings/SavingsAccountDetailInline.vue';
import ModuleStatusBar from '@/components/Shared/ModuleStatusBar.vue';

import logger from '@/utils/logger';
export default {
  name: 'CashOverview',

  components: {
    AccountSummaryPanel,
    CashInsightsPanel,
    CashActionsPanel,
    SaveAccountModal,
    SavingsAccountDetailInline,
    ModuleStatusBar,
  },

  mixins: [currencyMixin],

  data() {
    return {
      creditCards: [],
      creditCardsLoading: false,
      selectedAccount: null,
      showAccountModal: false,
      editingAccount: null,
      defaultAccountType: '',
      // Financial commitments from user profile API
      financialCommitmentsData: null,
    };
  },

  computed: {
    ...mapState('savings', ['accounts', 'loading', 'error', 'expenditureProfile']),
    ...mapState('userProfile', ['incomeOccupation']),
    ...mapGetters('savings', ['totalSavings']),
    ...mapGetters('userProfile', ['totalAnnualIncome']),
    ...mapGetters('preview', ['isPreviewMode']),
    ...mapGetters('subNav', ['pendingAction', 'actionCounter']),

    // Filter accounts by type for real users view
    currentAccounts() {
      return this.accounts.filter(a => a.account_type === 'current_account');
    },

    savingsAccounts() {
      return this.accounts.filter(a =>
        ['savings_account', 'easy_access', 'instant_access', 'notice', 'fixed'].includes(a.account_type)
      );
    },

    isaAccounts() {
      return this.accounts.filter(a =>
        ['cash_isa', 'junior_isa'].includes(a.account_type) || a.is_isa
      );
    },

    nsiAccounts() {
      return this.accounts.filter(a =>
        ['premium_bonds', 'nsi'].includes(a.account_type)
      );
    },

    currentAccountsTotal() {
      return this.currentAccounts.reduce((sum, a) => sum + this.getUserShare(a), 0);
    },

    savingsAccountsTotal() {
      return this.savingsAccounts.reduce((sum, a) => sum + this.getUserShare(a), 0);
    },

    isaAccountsTotal() {
      return this.isaAccounts.reduce((sum, a) => sum + this.getUserShare(a), 0);
    },

    nsiAccountsTotal() {
      return this.nsiAccounts.reduce((sum, a) => sum + this.getUserShare(a), 0);
    },

    // Monthly income from user profile (full month - assumed payday has occurred)
    monthlyIncome() {
      return (this.totalAnnualIncome || 0) / 12;
    },

    // Pro-rata factor for current month (day of month / days in month)
    monthProRata() {
      const today = new Date();
      const day = today.getDate();
      const daysInMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0).getDate();
      return day / daysInMonth;
    },

    // Sum of actual discretionary expenditure categories (not the user-entered total)
    discretionarySpendingMonthly() {
      const profile = this.expenditureProfile;
      if (!profile) return 0;

      // Sum all expenditure category fields
      return (
        (parseFloat(profile.food_groceries) || 0) +
        (parseFloat(profile.transport_fuel) || 0) +
        (parseFloat(profile.healthcare_medical) || 0) +
        (parseFloat(profile.insurance) || 0) +
        (parseFloat(profile.mobile_phones) || 0) +
        (parseFloat(profile.internet_tv) || 0) +
        (parseFloat(profile.subscriptions) || 0) +
        (parseFloat(profile.clothing_personal_care) || 0) +
        (parseFloat(profile.entertainment_dining) || 0) +
        (parseFloat(profile.holidays_travel) || 0) +
        (parseFloat(profile.pets) || 0) +
        (parseFloat(profile.childcare) || 0) +
        (parseFloat(profile.school_fees) || 0) +
        (parseFloat(profile.children_activities) || 0) +
        (parseFloat(profile.gifts_charity) || 0) +
        (parseFloat(profile.other_expenditure) || 0)
      );
    },

    // Month-to-date expenditure from current account
    // Discretionary: pro-rata based on day of month
    // Financial commitments: full monthly amount (assumed already paid)
    currentAccountExpenditure() {
      const discretionaryMTD = this.discretionarySpendingMonthly * this.monthProRata;
      const commitments = this.financialCommitmentsData?.totals?.total || 0;
      return discretionaryMTD + commitments;
    },

    // Financial commitments for the spending chart from user profile API
    financialCommitments() {
      const commitments = {};
      if (!this.financialCommitmentsData?.totals) return commitments;

      const totals = this.financialCommitmentsData.totals;

      // Property expenses (mortgages, council tax, utilities, etc.)
      if (totals.properties > 0) {
        commitments['Property Expenses'] = totals.properties;
      }

      // Pension contributions
      if (totals.retirement > 0) {
        commitments['Pension Contributions'] = totals.retirement;
      }

      // Protection premiums
      if (totals.protection > 0) {
        commitments['Protection Premiums'] = totals.protection;
      }

      // Liability payments (loans, credit cards, etc.)
      if (totals.liabilities > 0) {
        commitments['Loan Payments'] = totals.liabilities;
      }

      return commitments;
    },
  },

  watch: {
    actionCounter() {
      if (this.pendingAction === 'addAccount') {
        this.openAddAccountModal('current_account');
        this.$store.dispatch('subNav/consumeCta');
      }
    },
    '$store.state.aiFormFill.pendingFill'(fill) {
      if (fill && fill.entityType === 'savings_account') {
        if (fill.mode === 'edit' && fill.entityId) {
          const record = this.accounts.find(a => a.id === fill.entityId);
          if (record) {
            this.editingAccount = record;
            this.showAccountModal = true;
          }
        } else {
          this.openAddAccountModal('');
        }
      }
    },
  },

  async mounted() {
    // Check for pendingFill that was set before this component mounted
    const fill = this.$store.state.aiFormFill?.pendingFill;
    if (fill && fill.entityType === 'savings_account' && fill.mode !== 'edit') {
      this.openAddAccountModal('');
    }

    await this.loadAllData();
  },

  methods: {
    ...mapActions('savings', ['fetchSavingsData', 'createAccount', 'updateAccount']),
    ...mapActions('userProfile', ['fetchProfile']),
    ...mapActions('netWorth', ['setDetailView']),

    async loadAllData() {
      try {
        // Load all data in parallel
        await Promise.all([
          this.fetchSavingsData(),
          this.loadEstateData(),
          this.loadFinancialCommitments(),
          this.loadProfileData(),
        ]);
      } catch (error) {
        logger.error('Failed to load cash overview data:', error);
      }
    },

    async loadEstateData() {
      try {
        this.creditCardsLoading = true;
        const estateData = await estateService.getEstateData();
        const liabilities = estateData.liabilities || [];
        this.creditCards = liabilities.filter(l => l.liability_type === 'credit_card');
      } catch (error) {
        logger.error('Failed to load estate data:', error);
        this.creditCards = [];
      } finally {
        this.creditCardsLoading = false;
      }
    },

    async loadFinancialCommitments() {
      try {
        const response = await userProfileService.getFinancialCommitments();
        if (response.success) {
          this.financialCommitmentsData = response.data;
        }
      } catch (error) {
        logger.error('Failed to load financial commitments:', error);
        this.financialCommitmentsData = null;
      }
    },

    async loadProfileData() {
      if (!this.incomeOccupation) {
        await this.fetchProfile();
      }
    },

    selectAccount(account) {
      this.selectedAccount = account;
      this.setDetailView(true);
    },

    clearSelection() {
      this.selectedAccount = null;
      this.setDetailView(false);
      this.loadAllData();
    },

    handleAccountDeleted() {
      this.selectedAccount = null;
      this.setDetailView(false);
      this.loadAllData();
    },

    async handleSaveAccount(accountData) {
      try {
        if (this.editingAccount) {
          await this.updateAccount({ id: this.editingAccount.id, accountData });
        } else {
          await this.createAccount(accountData);
        }
        // Complete AI fill if this was an AI-driven save
        if (this.$store.state.aiFormFill.pendingFill) {
          this.$store.dispatch('aiFormFill/completeFill');
        }
        this.closeAccountModal();
        await this.fetchSavingsData();
      } catch (error) {
        logger.error('Failed to save account:', error);
      }
    },

    closeAccountModal() {
      this.showAccountModal = false;
      this.editingAccount = null;
      this.defaultAccountType = '';
    },

    openAddAccountModal(accountType) {
      this.editingAccount = null;
      this.defaultAccountType = accountType;
      this.showAccountModal = true;
    },

    isJointAccount(account) {
      return account.ownership_type === 'joint';
    },

    getUserShare(account) {
      const balance = parseFloat(account.current_balance) || 0;
      if ((this.isJointAccount(account) || account.ownership_type === 'tenants_in_common') && account.ownership_percentage) {
        return balance * (parseFloat(account.ownership_percentage) / 100);
      }
      return balance;
    },
  },
};
</script>

<style scoped>
.cash-overview {
  min-height: 400px;
}

.account-card {
  background: white;
  border-radius: 8px;
  padding: 16px;
  @apply border border-light-gray;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.card-total {
  width: 100%;
  text-align: left;
  margin-bottom: 12px;
  padding-bottom: 12px;
  @apply border-b border-light-gray;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  width: 100%;
  margin-bottom: 12px;
}

.card-title {
  font-size: 18px;
  font-weight: 700;
  @apply text-horizon-500;
  margin: 0;
}

.add-icon-btn {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  @apply bg-light-pink-100;
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  @apply text-horizon-500;
  transition: background 0.2s;
}

.add-icon-btn:hover {
  @apply bg-light-pink-200;
}

.empty-message {
  font-size: 13px;
  @apply text-horizon-400;
  text-align: center;
  margin: 0 0 12px 0;
}

.account-item {
  width: 100%;
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 12px;
  margin-bottom: 8px;
  @apply bg-eggshell-500 rounded-lg;
  cursor: pointer;
  transition: all 0.2s;
}

.account-item:hover {
  @apply bg-savannah-100;
}

.account-info {
  flex: 1;
  min-width: 0;
}

.account-name {
  font-size: 13px;
  @apply text-horizon-500;
  font-weight: 500;
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.joint-badge {
  font-size: 11px;
  @apply text-blue-600;
  font-weight: 500;
  margin-left: 4px;
}

.account-balances {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  flex-shrink: 0;
}

.account-balance {
  font-size: 13px;
  @apply text-green-600;
  font-weight: 600;
}

.total-balance {
  font-size: 10px;
  @apply text-neutral-500;
  font-weight: 400;
}

.add-account-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  font-size: 13px;
  font-weight: 500;
  @apply text-horizon-500 rounded-lg;
  @apply bg-light-pink-100;
  border: none;
  cursor: pointer;
  transition: all 0.2s;
}

.add-account-btn:hover {
  @apply bg-light-pink-200;
}
</style>
