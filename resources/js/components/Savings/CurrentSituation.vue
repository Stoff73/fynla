<template>
  <div class="current-situation">
    <!-- Account Overview -->
    <div class="account-overview">
      <div class="section-header-row">
        <h3 class="section-title">Account Overview</h3>
        <div class="flex gap-3">
          <button v-preview-disabled="'add'" @click="handleAddAccount" class="add-account-btn">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="btn-icon">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            Add Account
          </button>
          <button @click="showUploadModal = true" class="upload-btn">
            <svg class="btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
            </svg>
            Upload Statement
          </button>
        </div>
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
    </div>

    <!-- ISA Allowance Tracker -->
    <div class="mt-8">
      <ISAAllowanceTracker />
    </div>

    <!-- Total Savings Summary -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-8">
      <div class="bg-gray-50 rounded-lg p-6 border border-gray-200">
        <h3 class="text-sm font-medium text-gray-600 mb-2">Total Savings</h3>
        <p class="text-3xl font-bold text-gray-900">
          {{ formatCurrency(totalSavings) }}
        </p>
      </div>

      <div class="bg-gray-50 rounded-lg p-6 border border-gray-200">
        <h3 class="text-sm font-medium text-gray-600 mb-2">Emergency Fund Runway</h3>
        <p class="text-3xl font-bold" :class="runwayColour">
          {{ emergencyFundRunway.toFixed(1) }} months
        </p>
      </div>

      <div class="bg-gray-50 rounded-lg p-6 border border-gray-200">
        <h3 class="text-sm font-medium text-gray-600 mb-2">Number of Accounts</h3>
        <p class="text-3xl font-bold text-gray-900">
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

    runwayColour() {
      if (this.emergencyFundRunway >= 6) return 'text-green-600';
      if (this.emergencyFundRunway >= 3) return 'text-amber-600';
      return 'text-red-600';
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
        individual: 'bg-gray-500 text-white',
        joint: 'bg-purple-500 text-white',
        trust: 'bg-amber-500 text-white',
      };
      return classes[type] || 'bg-gray-500 text-white';
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
        console.error('Failed to save account:', error);
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
  color: #111827;
  margin: 0;
}

.add-account-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  background: #3b82f6;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}

.add-account-btn:hover {
  background: #2563eb;
}

.upload-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  background: white;
  color: #3b82f6;
  border: 2px solid #3b82f6;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.upload-btn:hover {
  background: #eff6ff;
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
  border: 1px solid #e5e7eb;
  padding: 20px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.account-card:hover {
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  transform: translateY(-2px);
  border-color: #3b82f6;
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
  background: #10b981;
  color: white;
}

.badge-isa {
  background: #3b82f6;
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
  color: #111827;
  margin: 0;
}

.account-type {
  font-size: 14px;
  color: #6b7280;
  margin: 0;
}

.account-details {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-top: 4px;
  padding-top: 12px;
  border-top: 1px solid #e5e7eb;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.detail-label {
  font-size: 14px;
  color: #6b7280;
  font-weight: 500;
}

.detail-value {
  font-size: 16px;
  color: #111827;
  font-weight: 700;
}

.detail-value.interest {
  color: #10b981;
}

.empty-state {
  text-align: center;
  padding: 60px 20px;
  background: white;
  border-radius: 12px;
  border: 2px dashed #d1d5db;
}

.empty-message {
  color: #6b7280;
  font-size: 16px;
  margin-bottom: 20px;
}

.add-account-button {
  padding: 12px 24px;
  background: #3b82f6;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}

.add-account-button:hover {
  background: #2563eb;
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
