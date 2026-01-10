<template>
  <div class="account-details">
    <!-- Header with Add Button -->
    <div class="mb-6 flex justify-between items-center">
      <div>
        <h3 class="text-lg font-semibold text-gray-900">Your Accounts</h3>
        <p class="text-sm text-gray-600 mt-1">
          {{ accounts.length }} {{ accounts.length === 1 ? 'account' : 'accounts' }}
        </p>
      </div>
      <button
        @click="handleAddAccount"
        class="px-4 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          class="h-5 w-5"
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M12 4v16m8-8H4"
          />
        </svg>
        Add Account
      </button>
    </div>

    <!-- Accounts List -->
    <div v-if="accounts.length > 0" class="space-y-4">
      <div
        v-for="account in accounts"
        :key="account.id"
        class="bg-white rounded-lg border border-gray-200 p-6"
      >
        <div class="flex justify-between items-start mb-4">
          <div>
            <div class="flex items-center gap-2 mb-1">
              <h4 class="text-lg font-semibold text-gray-900">{{ account.institution }}</h4>
              <span
                v-if="account.is_isa"
                class="px-2 py-1 text-xs bg-blue-500 text-white rounded font-semibold"
              >
                ISA
              </span>
            </div>
            <p class="text-sm text-gray-600">{{ formatAccountType(account.account_type) }}</p>
          </div>
          <div class="text-right">
            <p class="text-2xl font-bold text-gray-900">
              {{ formatCurrency(account.current_balance) }}
            </p>
            <p class="text-sm text-gray-600">{{ formatInterestRate(account.interest_rate) }}% APY</p>
          </div>
        </div>

        <!-- Account Details -->
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-4 text-sm">
          <div>
            <p class="text-gray-600">Access Type</p>
            <p class="font-semibold">{{ formatAccessType(account.access_type) }}</p>
          </div>
          <div v-if="account.notice_period_days">
            <p class="text-gray-600">Notice Period</p>
            <p class="font-semibold">{{ account.notice_period_days }} days</p>
          </div>
          <div v-if="account.maturity_date">
            <p class="text-gray-600">Maturity Date</p>
            <p class="font-semibold">{{ formatDate(account.maturity_date) }}</p>
          </div>
        </div>

        <!-- Actions -->
        <div class="flex gap-3">
          <button
            disabled
            class="px-4 py-2 bg-gray-300 text-gray-500 text-sm font-medium rounded-lg cursor-not-allowed"
            title="This functionality is not available for this demo"
          >
            Edit
          </button>
          <button
            @click="handleDeleteAccount(account.id)"
            class="px-4 py-2 bg-red-50 text-red-600 text-sm font-medium rounded-lg hover:bg-red-100"
          >
            Delete
          </button>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="text-center py-12 bg-white rounded-lg border border-gray-200">
      <svg
        class="mx-auto h-12 w-12 text-gray-400"
        fill="none"
        viewBox="0 0 24 24"
        stroke="currentColor"
      >
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          stroke-width="2"
          d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"
        />
      </svg>
      <h3 class="mt-2 text-sm font-medium text-gray-900">No accounts yet</h3>
      <p class="mt-1 text-sm text-gray-500">
        Get started by adding your first savings account.
      </p>
      <button
        @click="handleAddAccount"
        class="mt-4 px-6 py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors inline-flex items-center gap-2"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          class="h-5 w-5"
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M12 4v16m8-8H4"
          />
        </svg>
        Add Your First Account
      </button>
    </div>

    <!-- Save Account Modal -->
    <SaveAccountModal
      v-if="showAddAccountModal"
      :account="selectedAccount"
      :is-editing="isEditingAccount"
      @save="handleSaveAccount"
      @close="handleCloseModal"
    />
  </div>
</template>

<script>
import { mapState, mapActions } from 'vuex';
import SaveAccountModal from './SaveAccountModal.vue';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'AccountDetails',
  mixins: [currencyMixin],

  components: {
    SaveAccountModal,
  },

  data() {
    return {
      showAddAccountModal: false,
      selectedAccount: null,
      isEditingAccount: false,
    };
  },

  computed: {
    ...mapState('savings', ['accounts']),
  },

  methods: {
    ...mapActions('savings', ['createAccount', 'updateAccount', 'deleteAccount', 'fetchSavingsData']),

    formatAccountType(type) {
      const types = {
        savings_account: 'Savings Account',
        current_account: 'Current Account',
        easy_access: 'Easy Access',
        notice: 'Notice Account',
        fixed: 'Fixed Term',
      };
      return types[type] || type;
    },

    formatAccessType(type) {
      const types = {
        immediate: 'Immediate',
        notice: 'Notice Required',
        fixed: 'Fixed Term',
      };
      return types[type] || type;
    },

    formatDate(dateString) {
      if (!dateString) return 'N/A';
      return new Date(dateString).toLocaleDateString('en-GB');
    },

    formatInterestRate(rate) {
      // Rate is stored as a percentage (e.g., 4.55 = 4.55%)
      // Display directly without multiplying
      return parseFloat(rate || 0).toFixed(2);
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

    handleEditAccount(account) {
      this.selectedAccount = account;
      this.isEditingAccount = true;
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

    async handleDeleteAccount(accountId) {
      if (!confirm('Are you sure you want to delete this account?')) {
        return;
      }

      try {
        await this.deleteAccount(accountId);
        await this.fetchSavingsData();
      } catch (error) {
        console.error('Failed to delete account:', error);
        alert('Failed to delete account. Please try again.');
      }
    },
  },
};
</script>
