<template>
  <div class="current-situation">
    <!-- Account Overview -->
    <div class="account-overview">
      <div class="section-header-row">
        <h3 class="section-title">Account Overview</h3>
      </div>

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

              <div v-if="isSharedRecord(account)" class="detail-row">
                <span class="detail-label">Your Share ({{ formatSharePercent(account) }})</span>
                <span class="detail-value">{{ formatCurrency(getUserShare(account)) }}</span>
              </div>
              <div v-if="coOwnerOf(account)" class="detail-row">
                <span class="detail-label">Held with</span>
                <span class="detail-value">{{ coOwnerOf(account) }}</span>
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
    </div>

    <div class="mt-8">
      <ISAAllowanceTracker />
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-8">
      <div class="bg-eggshell-500 rounded-lg p-6 border border-light-gray">
        <h3 class="text-sm font-medium text-neutral-500 mb-2">Total Savings</h3>
        <p class="text-3xl font-bold text-horizon-500">
          {{ formatCurrency(totalSavings) }}
        </p>
      </div>

      <!--
        W-0495. This printed "0.0 months" whenever no expenditure was recorded,
        which is not a low runway but an unmeasurable one — and it read as an
        alarm to a household with ample cash. The prompt and the wording come
        come from the server (SavingsPosition) so `/m` and iOS say exactly the same thing.
      -->
      <div class="bg-eggshell-500 rounded-lg p-6 border border-light-gray">
        <h3 class="text-sm font-medium text-neutral-500 mb-2">Emergency Fund Runway</h3>
        <template v-if="emergencyFund.runway_hint">
          <p class="text-xl font-bold text-horizon-500">{{ emergencyFund.runway_label }}</p>
          <p class="text-sm text-neutral-500 mt-1">{{ emergencyFund.runway_hint }}</p>
        </template>
        <p v-else class="text-3xl font-bold" :class="runwayColour">
          {{ emergencyFund.runway_label }}
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
import { formatInterestRate } from '@/utils/interestRate';
import { mapState, mapGetters, mapActions } from 'vuex';
import ISAAllowanceTracker from './ISAAllowanceTracker.vue';
import SaveAccountModal from './SaveAccountModal.vue';
import DocumentUploadModal from '@/components/Shared/DocumentUploadModal.vue';
import { currencyMixin } from '@/mixins/currencyMixin';

import logger from '@/utils/logger';
import { calculateUserShare, coOwnerName, isSharedRecord, userSharePercent } from '@/utils/ownership';
export default {
  name: 'SavingsModuleOverview',

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
    ...mapGetters('savings', ['totalSavings', 'emergencyFund']),
    ...mapGetters('subNav', ['pendingAction', 'actionCounter']),


    // The server's status against the user's own target (SavingsPosition).
    runwayColour() {
      return { on_track: 'text-spring-600', part: 'text-violet-600', low: 'text-raspberry-600' }[this.emergencyFund.status] || 'text-horizon-500';
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

    // Ownership display via resources/js/utils/ownership.js — the ONE home,
    // shared with the investment, property and chattel cards (W-0015).
    isSharedRecord,

    getUserShare(account) {
      return calculateUserShare(account, { valueField: 'current_balance' });
    },

    formatSharePercent(account) {
      return `${userSharePercent(account).toFixed(2)}%`;
    },

    coOwnerOf(account) {
      return coOwnerName(account);
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
        joint: 'bg-violet-500 text-white',
        trust: 'bg-violet-500 text-white',
      };
      return classes[type] || 'bg-eggshell-5000 text-white';
    },

    formatInterestRate,

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

    async handleDocumentSaved() {
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
