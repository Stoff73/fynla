<template>
  <AppLayout>
    <div class="container mx-auto px-4 py-8">
      <!-- Loading State -->
      <div v-if="loading" class="text-center py-12">
        <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-violet-600"></div>
        <p class="mt-4 text-neutral-500">Loading account details...</p>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="bg-raspberry-50 border border-raspberry-200 rounded-lg p-6 text-center">
        <p class="text-raspberry-600">{{ error }}</p>
        <button
          @click="loadAccount"
          class="mt-4 px-4 py-2 bg-raspberry-600 text-white rounded-button hover:bg-raspberry-700 transition-colors"
        >
          Retry
        </button>
      </div>

      <!-- Account Content -->
      <div v-else-if="account" class="space-y-6">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-md p-6">
          <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
            <div>
              <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold text-horizon-500">{{ account.institution }}</h1>
              <p class="text-base sm:text-lg text-neutral-500 mt-1">{{ formatAccountType(account.account_type) }}</p>
              <div class="flex flex-wrap gap-2 mt-2">
                <span v-if="account.is_emergency_fund" class="inline-block px-2 py-1 text-xs bg-spring-100 text-spring-800 rounded">
                  Emergency Fund
                </span>
                <span v-if="account.is_isa" class="inline-block px-2 py-1 text-xs bg-violet-100 text-violet-800 rounded">
                  ISA
                </span>
              </div>
            </div>
            <div class="flex space-x-2 w-full sm:w-auto">
              <button
                @click="showEditModal = true"
                class="px-4 py-2 bg-raspberry-500 text-white rounded-button hover:bg-raspberry-600 transition-colors"
              >
                Edit
              </button>
              <button
                @click="confirmDelete"
                class="px-4 py-2 bg-raspberry-600 text-white rounded-button hover:bg-raspberry-700 transition-colors"
              >
                Delete
              </button>
            </div>
          </div>

          <!-- Key Metrics -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
            <div class="bg-eggshell-500 rounded-lg p-4">
              <p class="text-sm text-neutral-500">Full Balance</p>
              <p class="text-2xl font-bold text-horizon-500">{{ formatCurrency(fullBalance) }}</p>
              <p v-if="account.ownership_type === 'joint'" class="text-xs text-neutral-500 mt-1">
                Your Share ({{ account.ownership_percentage }}%): {{ formatCurrency(userShare) }}
              </p>
            </div>
            <div class="bg-eggshell-500 rounded-lg p-4">
              <p class="text-sm text-neutral-500">Interest Rate</p>
              <p class="text-2xl font-bold text-violet-600">{{ formatInterestRate(account.interest_rate) }}</p>
            </div>
            <div class="bg-eggshell-500 rounded-lg p-4">
              <p class="text-sm text-neutral-500">Annual Interest</p>
              <p class="text-2xl font-bold text-spring-600">{{ formatCurrency(annualInterest) }}</p>
            </div>
          </div>
        </div>

        <!-- Account Details -->
        <div class="bg-white rounded-lg shadow-md p-6">
          <h3 class="text-lg font-semibold text-horizon-500 mb-6">Account Details</h3>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Account Information -->
            <div>
              <h5 class="text-sm font-semibold text-horizon-500 mb-3">Account Information</h5>
              <dl class="space-y-2">
                <div class="flex justify-between">
                  <dt class="text-sm text-neutral-500">Institution:</dt>
                  <dd class="text-sm font-medium text-horizon-500 text-right">{{ account.institution }}</dd>
                </div>
                <div class="flex justify-between">
                  <dt class="text-sm text-neutral-500">Account Type:</dt>
                  <dd class="text-sm font-medium text-horizon-500">{{ formatAccountType(account.account_type) }}</dd>
                </div>
                <div v-if="account.account_number" class="flex justify-between">
                  <dt class="text-sm text-neutral-500">Account Number:</dt>
                  <dd class="text-sm font-medium text-horizon-500">{{ account.account_number }}</dd>
                </div>
                <div v-if="account.country" class="flex justify-between">
                  <dt class="text-sm text-neutral-500">Country:</dt>
                  <dd class="text-sm font-medium text-horizon-500">{{ account.country }}</dd>
                </div>
              </dl>
            </div>

            <!-- Balance & Interest -->
            <div>
              <h5 class="text-sm font-semibold text-horizon-500 mb-3">Balance & Interest</h5>
              <dl class="space-y-2">
                <div class="flex justify-between">
                  <dt class="text-sm text-neutral-500">Full Balance:</dt>
                  <dd class="text-sm font-medium text-horizon-500 font-semibold">{{ formatCurrency(fullBalance) }}</dd>
                </div>
                <div v-if="account.ownership_type === 'joint'" class="flex justify-between">
                  <dt class="text-sm text-neutral-500">Your Share ({{ account.ownership_percentage }}%):</dt>
                  <dd class="text-sm font-medium text-violet-600">{{ formatCurrency(userShare) }}</dd>
                </div>
                <div class="flex justify-between">
                  <dt class="text-sm text-neutral-500">Interest Rate:</dt>
                  <dd class="text-sm font-medium text-horizon-500">{{ formatInterestRate(account.interest_rate) }}</dd>
                </div>
                <div class="flex justify-between">
                  <dt class="text-sm text-neutral-500">Monthly Interest:</dt>
                  <dd class="text-sm font-medium text-spring-600">{{ formatCurrency(monthlyInterest) }}</dd>
                </div>
                <div class="flex justify-between">
                  <dt class="text-sm text-neutral-500">Annual Interest:</dt>
                  <dd class="text-sm font-medium text-spring-600">{{ formatCurrency(annualInterest) }}</dd>
                </div>
              </dl>
            </div>

            <!-- Access & Terms -->
            <div>
              <h5 class="text-sm font-semibold text-horizon-500 mb-3">Access & Terms</h5>
              <dl class="space-y-2">
                <div class="flex justify-between">
                  <dt class="text-sm text-neutral-500">Access Type:</dt>
                  <dd class="text-sm font-medium text-horizon-500 capitalize">{{ formatAccessType(account.access_type) }}</dd>
                </div>
                <div v-if="account.access_type === 'notice' && account.notice_period_days" class="flex justify-between">
                  <dt class="text-sm text-neutral-500">Notice Period:</dt>
                  <dd class="text-sm font-medium text-violet-600">{{ account.notice_period_days }} days</dd>
                </div>
                <div v-if="account.access_type === 'fixed' && account.maturity_date" class="flex justify-between">
                  <dt class="text-sm text-neutral-500">Maturity Date:</dt>
                  <dd class="text-sm font-medium" :class="isMatured ? 'text-neutral-500' : 'text-violet-600'">
                    {{ formatDate(account.maturity_date) }}
                  </dd>
                </div>
                <div v-if="account.access_type === 'fixed' && account.maturity_date" class="flex justify-between">
                  <dt class="text-sm text-neutral-500">Time to Maturity:</dt>
                  <dd class="text-sm font-medium text-horizon-500">{{ calculateTimeToMaturity }}</dd>
                </div>
              </dl>
            </div>

            <!-- ISA Details (if applicable) -->
            <div v-if="account.is_isa">
              <h5 class="text-sm font-semibold text-horizon-500 mb-3">ISA Details</h5>
              <dl class="space-y-2">
                <div class="flex justify-between">
                  <dt class="text-sm text-neutral-500">ISA Type:</dt>
                  <dd class="text-sm font-medium text-horizon-500">{{ formatISAType(account.isa_type) }}</dd>
                </div>
                <div v-if="account.isa_subscription_year" class="flex justify-between">
                  <dt class="text-sm text-neutral-500">Subscription Year:</dt>
                  <dd class="text-sm font-medium text-horizon-500">{{ account.isa_subscription_year }}</dd>
                </div>
                <div v-if="account.isa_subscription_amount" class="flex justify-between">
                  <dt class="text-sm text-neutral-500">Subscription Amount:</dt>
                  <dd class="text-sm font-medium text-horizon-500">{{ formatCurrency(account.isa_subscription_amount) }}</dd>
                </div>
                <div class="flex justify-between">
                  <dt class="text-sm text-neutral-500">Tax-Free Status:</dt>
                  <dd class="text-sm font-medium text-spring-600">✓ Tax-Free Interest</dd>
                </div>
              </dl>
            </div>
          </div>
        </div>
      </div>

      <!-- Modals -->
      <SaveAccountModal
        v-if="showEditModal"
        :account="account"
        @close="showEditModal = false"
        @saved="handleAccountSaved"
      />

      <ConfirmDialog
        :show="showDeleteConfirm"
        title="Delete Account"
        message="Are you sure you want to delete this savings account? This action cannot be undone."
        @confirm="handleDelete"
        @cancel="showDeleteConfirm = false"
      />
    </div>
  </AppLayout>
</template>

<script>
import { mapActions } from 'vuex';
import AppLayout from '@/layouts/AppLayout.vue';
import SaveAccountModal from '@/components/Savings/SaveAccountModal.vue';
import ConfirmDialog from '@/components/Common/ConfirmDialog.vue';
import { currencyMixin } from '@/mixins/currencyMixin';

import logger from '@/utils/logger';
export default {
  name: 'SavingsAccountDetail',
  mixins: [currencyMixin],

  components: {
    AppLayout,
    SaveAccountModal,
    ConfirmDialog,
  },

  data() {
    return {
      accountId: parseInt(this.$route.params.id),
      account: null,
      loading: true,
      error: null,
      showEditModal: false,
      showDeleteConfirm: false,
    };
  },

  computed: {
    fullBalance() {
      if (!this.account) return 0;
      // Single-record pattern: DB stores FULL balance
      // Use full_balance from API if available, otherwise current_balance is already full
      return this.account.full_balance ?? this.account.current_balance ?? 0;
    },

    userShare() {
      if (!this.account) return 0;
      // Single-record pattern: Use user_share from API if available
      if (this.account.user_share !== undefined) {
        return this.account.user_share;
      }
      // Fallback: calculate from full balance
      if (this.account.ownership_type === 'joint' && this.account.ownership_percentage) {
        return this.fullBalance * (this.account.ownership_percentage / 100);
      }
      return this.fullBalance;
    },

    monthlyInterest() {
      if (!this.account) return 0;
      return (this.account.current_balance * (this.account.interest_rate / 100)) / 12;
    },

    annualInterest() {
      if (!this.account) return 0;
      return this.account.current_balance * (this.account.interest_rate / 100);
    },

    isMatured() {
      if (!this.account || !this.account.maturity_date) return false;
      return new Date(this.account.maturity_date) < new Date();
    },

    calculateTimeToMaturity() {
      if (!this.account || !this.account.maturity_date) return 'N/A';

      const today = new Date();
      const maturity = new Date(this.account.maturity_date);
      const diffTime = maturity - today;
      const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

      if (diffDays <= 0) return 'Matured';
      if (diffDays < 31) return `${diffDays} days`;

      const diffMonths = Math.ceil(diffDays / 30.44);
      const years = Math.floor(diffMonths / 12);
      const months = diffMonths % 12;

      if (years === 0) return `${months} month${months !== 1 ? 's' : ''}`;
      if (months === 0) return `${years} year${years !== 1 ? 's' : ''}`;
      return `${years} year${years !== 1 ? 's' : ''}, ${months} month${months !== 1 ? 's' : ''}`;
    },
  },

  mounted() {
    this.loadAccount();
  },

  methods: {
    ...mapActions('savings', ['fetchAccount', 'deleteAccount']),

    async loadAccount() {
      this.loading = true;
      this.error = null;

      try {
        this.account = await this.fetchAccount(this.accountId);
      } catch (error) {
        logger.error('Failed to load account:', error);
        this.error = 'Failed to load account details. Please try again.';
      } finally {
        this.loading = false;
      }
    },

    async handleAccountSaved() {
      this.showEditModal = false;
      await this.loadAccount();
    },

    confirmDelete() {
      this.showDeleteConfirm = true;
    },

    async handleDelete() {
      try {
        await this.deleteAccount(this.accountId);
        this.showDeleteConfirm = false;
        this.$router.push('/savings');
      } catch (error) {
        logger.error('Failed to delete account:', error);
        this.error = 'Failed to delete account. Please try again.';
      }
    },

    formatDate(date) {
      if (!date) return '';
      return new Date(date).toLocaleDateString('en-GB', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
      });
    },

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
        immediate: 'Immediate Access',
        notice: 'Notice Required',
        fixed: 'Fixed Term',
      };
      return types[type] || type;
    },

    formatISAType(type) {
      const types = {
        cash: 'Cash ISA',
        stocks_and_shares: 'Stocks & Shares ISA',
        lifetime: 'Lifetime ISA',
        innovative_finance: 'Innovative Finance ISA',
      };
      return types[type] || type;
    },

    formatInterestRate(rate) {
      // Rate is stored as a percentage (e.g., 4.55 = 4.55%)
      // Display directly without multiplying
      return `${parseFloat(rate || 0).toFixed(2)}%`;
    },
  },
};
</script>
