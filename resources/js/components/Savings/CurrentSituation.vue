<template>
  <div class="current-situation">
    <!-- Account Overview -->
    <div class="account-overview">
      <div class="section-header-row">
        <h3 class="section-title">Account Overview</h3>
      </div>

      <!-- Preview Mode: Show full dashboard -->
      <template v-if="isPreviewMode">
        <div v-if="accounts.length > 0" class="accounts-grid">
          <div
            v-for="account in accounts"
            :key="account.id"
            @click="viewAccountDetail(account.id)"
            class="account-card"
          >
            <div class="card-header">
              <span
                :class="getOwnershipBadgeClass(account.ownership_type)"
                class="ownership-badge"
              >
                {{ formatOwnershipType(account.ownership_type) }}
              </span>
              <div class="badge-group">
                <span v-if="account.is_emergency_fund" class="badge badge-emergency">
                  Emergency Fund
                </span>
                <span v-if="account.is_isa" class="badge badge-isa">
                  ISA
                </span>
              </div>
            </div>

            <div class="card-content">
              <h4 class="account-institution">{{ account.institution }}</h4>
              <p class="account-type">{{ formatAccountType(account.account_type) }}</p>

              <div class="account-details">
                <div class="detail-row">
                  <span class="detail-label">{{ getBalanceLabel(account) }}</span>
                  <span class="detail-value">{{ formatCurrency(getFullBalance(account)) }}</span>
                </div>

                <div v-if="account.ownership_type === 'joint'" class="detail-row">
                  <span class="detail-label">Your Share ({{ account.ownership_percentage }}%)</span>
                  <span class="detail-value">{{ formatCurrency(getUserShare(account)) }}</span>
                </div>

                <div v-if="account.interest_rate > 0" class="detail-row">
                  <span class="detail-label">Interest Rate</span>
                  <span class="detail-value interest">{{ formatInterestRate(account.interest_rate) }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div v-else class="empty-state">
          <p class="empty-message">No savings accounts added yet.</p>
          <button @click="handleAddAccount" class="add-account-button">
            Add Your First Account
          </button>
        </div>
      </template>

      <!-- Real Users: Show Open Banking promotion -->
      <div v-else class="open-banking-promo">
        <div class="promo-icon">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72m-13.5 8.65h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.75c0 .415.336.75.75.75z" />
          </svg>
        </div>
        <h3 class="promo-title">Connect to Open Banking</h3>
        <p class="promo-description">
          Link your bank accounts securely to unlock powerful insights:
        </p>
        <ul class="promo-features">
          <li>
            <svg class="feature-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            Real-time balance tracking across all accounts
          </li>
          <li>
            <svg class="feature-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            Automatic spending categorisation
          </li>
          <li>
            <svg class="feature-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            Payday tracking and cash flow forecasting
          </li>
          <li>
            <svg class="feature-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            Personalised savings recommendations
          </li>
        </ul>
        <div class="promo-badge">Coming Soon</div>
      </div>
    </div>

    <!-- Preview Mode Only: ISA Allowance Tracker -->
    <div v-if="isPreviewMode" class="mt-8">
      <ISAAllowanceTracker />
    </div>

    <!-- Preview Mode Only: Total Savings Summary -->
    <div v-if="isPreviewMode" class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-8">
      <div class="bg-eggshell-500 rounded-lg p-6 border border-light-gray">
        <h3 class="text-sm font-medium text-neutral-500 mb-2">Total Savings</h3>
        <p class="text-3xl font-bold text-horizon-500">
          {{ formatCurrency(totalSavings) }}
        </p>
      </div>

      <div class="bg-eggshell-500 rounded-lg p-6 border border-light-gray">
        <h3 class="text-sm font-medium text-neutral-500 mb-2">Emergency Fund Runway</h3>
        <p class="text-3xl font-bold" :class="runwayColour">
          {{ emergencyFundRunway.toFixed(1) }} months
        </p>
      </div>

      <div class="bg-eggshell-500 rounded-lg p-6 border border-light-gray">
        <h3 class="text-sm font-medium text-neutral-500 mb-2">Number of Accounts</h3>
        <p class="text-3xl font-bold text-horizon-500">
          {{ accounts.length }}
        </p>
      </div>
    </div>

    <!-- Save Account Modal -->
    <SaveAccountModal
      v-if="showAddAccountModal"
      :account="selectedAccount"
      :is-editing="isEditingAccount"
      @save="handleSaveAccount"
      @close="handleCloseModal"
    />

    <!-- Document Upload Modal -->
    <DocumentUploadModal
      v-if="showUploadModal"
      document-type="savings_statement"
      @close="closeUploadModal"
      @saved="handleDocumentSaved"
      @manual-entry="closeUploadModal(); handleAddAccount();"
    />
  </div>
</template>

<script>
import { mapState, mapGetters, mapActions } from 'vuex';
import ISAAllowanceTracker from './ISAAllowanceTracker.vue';
import SaveAccountModal from './SaveAccountModal.vue';
import DocumentUploadModal from '@/components/Shared/DocumentUploadModal.vue';
import { currencyMixin } from '@/mixins/currencyMixin';

import logger from '@/utils/logger';
export default {
  name: 'CurrentSituation',

  mixins: [currencyMixin],

  components: {
    ISAAllowanceTracker,
    SaveAccountModal,
    DocumentUploadModal,
  },

  emits: ['select-account'],

  data() {
    return {
      showAddAccountModal: false,
      showUploadModal: false,
      selectedAccount: null,
      isEditingAccount: false,
    };
  },

  computed: {
    ...mapState('savings', ['accounts']),
    ...mapGetters('savings', ['totalSavings', 'emergencyFundRunway']),
    ...mapGetters('subNav', ['pendingAction', 'actionCounter']),

    isPreviewMode() {
      return this.$store.getters['preview/isPreviewMode'];
    },

    runwayColour() {
      if (this.emergencyFundRunway >= 6) return 'text-spring-600';
      if (this.emergencyFundRunway >= 3) return 'text-violet-600';
      return 'text-raspberry-600';
    },
  },

  watch: {
    actionCounter() {
      if (this.pendingAction === 'addAccount') {
        this.handleAddAccount();
        this.$store.dispatch('subNav/consumeCta');
      } else if (this.pendingAction === 'uploadStatement') {
        this.showUploadModal = true;
        this.$store.dispatch('subNav/consumeCta');
      }
    },
  },

  methods: {
    ...mapActions('savings', ['createAccount', 'updateAccount', 'fetchSavingsData']),

    viewAccountDetail(accountId) {
      const account = this.accounts.find(a => a.id === accountId);
      if (account) {
        this.$emit('select-account', account);
      }
    },

    getBalanceLabel(account) {
      if (account.ownership_type === 'joint') {
        return 'Full Balance';
      }
      return 'Balance';
    },

    getFullBalance(account) {
      // Single-record pattern: current_balance in DB is the FULL value
      // Use full_value from API if available, otherwise current_balance
      return account.full_value ?? account.current_balance ?? 0;
    },

    getUserShare(account) {
      // Single-record pattern: API provides user_share, or calculate from full balance
      if (account.user_share !== undefined) {
        return account.user_share;
      }
      // Fallback: calculate from full balance
      if (account.ownership_type === 'joint' && account.ownership_percentage) {
        return this.getFullBalance(account) * (account.ownership_percentage / 100);
      }
      return this.getFullBalance(account);
    },

    formatAccountType(type) {
      const types = {
        savings_account: 'Savings Account',
        current_account: 'Current Account',
        easy_access: 'Easy Access',
        instant_access: 'Instant Access',
        notice: 'Notice Account',
        fixed: 'Fixed Term',
        cash_isa: 'Cash ISA',
        junior_isa: 'Junior ISA',
        premium_bonds: 'Premium Bonds',
        nsi: 'NS&I Savings',
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
        individual: 'bg-eggshell-5000 text-white',
        joint: 'bg-purple-500 text-white',
        trust: 'bg-indigo-500 text-white',
      };
      return classes[type] || 'bg-eggshell-5000 text-white';
    },

    formatInterestRate(rate) {
      // Rate is stored as a percentage (e.g., 4.55 = 4.55%)
      // Display directly without multiplying
      return `${parseFloat(rate || 0).toFixed(2)}%`;
    },

    // Modal handlers
    handleCloseModal() {
      this.showAddAccountModal = false;
      this.selectedAccount = null;
      this.isEditingAccount = false;
    },

    handleAddAccount() {
      this.selectedAccount = null;
      this.isEditingAccount = false;
      this.showAddAccountModal = true;
    },

    async handleSaveAccount(accountData) {
      try {
        if (this.isEditingAccount && this.selectedAccount) {
          // Update existing account
          await this.updateAccount({
            id: this.selectedAccount.id,
            accountData,
          });
        } else {
          // Create new account
          await this.createAccount(accountData);
        }

        // Refresh data
        await this.fetchSavingsData();

        // Close modal
        this.handleCloseModal();
      } catch (error) {
        logger.error('Failed to save account:', error);
        alert('Failed to save account. Please try again.');
      }
    },

    closeUploadModal() {
      this.showUploadModal = false;
    },

    async handleDocumentSaved(savedData) {
      this.showUploadModal = false;
      // Refresh savings data
      await this.fetchSavingsData();
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

.badge-group {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}

.badge {
  display: inline-block;
  padding: 4px 10px;
  font-size: 11px;
  font-weight: 600;
  border-radius: 6px;
}

.badge-emergency {
  @apply bg-spring-500;
  color: white;
}

.badge-isa {
  @apply bg-raspberry-500;
  color: white;
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

.detail-value.interest {
  @apply text-spring-500;
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

/* Open Banking Promo Card */
.open-banking-promo {
  background: linear-gradient(135deg, theme('colors.sky.50') 0%, theme('colors.sky.100') 100%);
  @apply border-2 border-sky-500;
  border-radius: 16px;
  padding: 40px;
  text-align: center;
  position: relative;
  overflow: hidden;
}

.promo-icon {
  display: flex;
  justify-content: center;
  margin-bottom: 20px;
  @apply text-sky-600;
}

.promo-title {
  font-size: 24px;
  font-weight: 700;
  @apply text-sky-900;
  margin: 0 0 12px 0;
}

.promo-description {
  font-size: 16px;
  @apply text-sky-700;
  margin: 0 0 24px 0;
}

.promo-features {
  list-style: none;
  padding: 0;
  margin: 0 auto 24px auto;
  max-width: 400px;
  text-align: left;
}

.promo-features li {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 8px 0;
  font-size: 15px;
  @apply text-sky-900;
}

.feature-icon {
  width: 20px;
  height: 20px;
  @apply text-sky-500;
  flex-shrink: 0;
}

.promo-badge {
  display: inline-block;
  padding: 8px 20px;
  @apply bg-sky-500;
  color: white;
  font-size: 14px;
  font-weight: 600;
  border-radius: 20px;
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

  .open-banking-promo {
    padding: 24px;
  }

  .promo-title {
    font-size: 20px;
  }
}
</style>
