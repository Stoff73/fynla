<template>
  <div class="savings-account-detail-inline animate-fade-in">
    <!-- Back Button -->
    <button
      @click="$emit('back')"
      class="detail-inline-back mb-4"
    >
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
      </svg>
      Back to Cash Overview
    </button>

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

        <!-- Balance Display -->
        <div class="mt-4 inline-block bg-eggshell-500 rounded-lg px-4 py-2">
          <span class="text-sm text-neutral-500">Balance: </span>
          <span class="text-lg font-bold text-horizon-500">{{ formatCurrency(fullBalance) }}</span>
          <span v-if="isJointAccount" class="text-sm text-neutral-500 ml-2">
            (Your Share: {{ formatCurrency(userShare) }})
          </span>
        </div>
      </div>

      <!-- Tab Navigation -->
      <div class="border-b border-light-gray">
        <nav class="flex overflow-x-auto scrollbar-hide -webkit-overflow-scrolling-touch">
          <button
            v-for="tab in tabs"
            :key="tab.id"
            @click="activeTab = tab.id"
            :class="[
              'px-4 py-3 text-sm font-medium whitespace-nowrap transition-colors duration-200 flex-shrink-0',
              activeTab === tab.id
                ? 'text-violet-600 border-b-2 border-violet-600'
                : 'text-neutral-500 hover:text-horizon-500 border-b-2 border-transparent'
            ]"
          >
            {{ tab.label }}
          </button>
        </nav>
      </div>

      <!-- Tab Content -->
      <transition name="fade" mode="out-in">
        <!-- Overview Tab -->
        <div v-if="activeTab === 'overview'" class="bg-white rounded-lg shadow-md p-6" :key="'overview'">
          <h3 class="text-lg font-semibold text-horizon-500 mb-6">Account Information</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <dl class="space-y-3">
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
              <div class="flex justify-between">
                <dt class="text-sm text-neutral-500">Ownership:</dt>
                <dd class="text-sm font-medium text-horizon-500 capitalize">{{ account.ownership_type || 'Individual' }}</dd>
              </div>
              <!-- Joint account owners -->
              <div v-if="isJointAccount" class="flex justify-between">
                <dt class="text-sm text-neutral-500">Owners:</dt>
                <dd class="text-sm font-medium text-horizon-500">
                  {{ account.owner_name || 'Primary' }} &amp; {{ account.joint_owner_name || 'Partner' }}
                </dd>
              </div>
            </dl>
            <dl class="space-y-3">
              <!-- Interest Rate -->
              <div class="flex justify-between">
                <dt class="text-sm text-neutral-500">Interest Rate:</dt>
                <dd class="text-sm font-medium text-violet-600">{{ formatInterestRate(account.interest_rate) }}</dd>
              </div>
              <div class="flex justify-between">
                <dt class="text-sm text-neutral-500">Annual Interest:</dt>
                <dd class="text-sm font-medium text-spring-600">{{ formatCurrency(annualInterest) }}</dd>
              </div>
              <div class="flex justify-between">
                <dt class="text-sm text-neutral-500">Emergency Fund:</dt>
                <dd class="text-sm font-medium" :class="account.is_emergency_fund ? 'text-spring-600' : 'text-neutral-500'">
                  {{ account.is_emergency_fund ? 'Yes' : 'No' }}
                </dd>
              </div>
              <div class="flex justify-between">
                <dt class="text-sm text-neutral-500">ISA Account:</dt>
                <dd class="text-sm font-medium" :class="account.is_isa ? 'text-violet-600' : 'text-neutral-500'">
                  {{ account.is_isa ? 'Yes' : 'No' }}
                </dd>
              </div>
            </dl>
          </div>
        </div>

        <!-- Access & Terms Tab -->
        <div v-else-if="activeTab === 'access'" class="bg-white rounded-lg shadow-md p-6" :key="'access'">
          <h3 class="text-lg font-semibold text-horizon-500 mb-6">Access & Terms</h3>
          <dl class="space-y-3 max-w-md">
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

        <!-- ISA Details Tab (conditional) -->
        <div v-else-if="activeTab === 'isa' && account.is_isa" class="bg-white rounded-lg shadow-md p-6" :key="'isa'">
          <h3 class="text-lg font-semibold text-horizon-500 mb-6">ISA Details</h3>
          <dl class="space-y-3 max-w-md">
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
              <dd class="text-sm font-medium text-spring-600">Tax-Free Interest</dd>
            </div>
          </dl>
        </div>

        <!-- Tax Status Tab -->
        <TaxStatusPanel
          v-else-if="activeTab === 'tax-status'"
          product-category="savings"
          :product-type="taxProductType"
          :is-isa="account.is_isa"
          :key="'tax-status'"
        />
      </transition>
    </div>

    <!-- Modals -->
    <SaveAccountModal
      v-if="showEditModal"
      :account="account"
      @close="showEditModal = false"
      @save="handleAccountSaved"
    />

    <ConfirmDialog
      :show="showDeleteConfirm"
      title="Delete Account"
      message="Are you sure you want to delete this savings account? This action cannot be undone."
      @confirm="handleDelete"
      @cancel="showDeleteConfirm = false"
    />
  </div>
</template>

<script>
import { mapActions } from 'vuex';
import SaveAccountModal from '@/components/Savings/SaveAccountModal.vue';
import ConfirmDialog from '@/components/Common/ConfirmDialog.vue';
import TaxStatusPanel from '@/components/Common/TaxStatusPanel.vue';
import { currencyMixin } from '@/mixins/currencyMixin';

import logger from '@/utils/logger';
export default {
  name: 'SavingsAccountDetailInline',
  mixins: [currencyMixin],

  components: {
    SaveAccountModal,
    ConfirmDialog,
    TaxStatusPanel,
  },

  props: {
    accountId: {
      type: Number,
      required: true,
    },
  },

  emits: ['back', 'deleted'],

  data() {
    return {
      account: null,
      loading: true,
      error: null,
      showEditModal: false,
      showDeleteConfirm: false,
      activeTab: 'overview',
    };
  },

  computed: {
    /**
     * Dynamic tabs based on account type
     */
    tabs() {
      const baseTabs = [
        { id: 'overview', label: 'Overview' },
      ];

      // Only show Access & Terms tab if NOT immediate access
      if (this.account?.access_type && this.account.access_type !== 'immediate') {
        baseTabs.push({ id: 'access', label: 'Access & Terms' });
      }

      // Conditionally add ISA tab
      if (this.account?.is_isa) {
        baseTabs.push({ id: 'isa', label: 'ISA Details' });
      }

      // Always add Tax Status
      baseTabs.push({ id: 'tax-status', label: 'Tax Status' });

      return baseTabs;
    },

    /**
     * Check if this is a joint account
     */
    isJointAccount() {
      return this.account?.ownership_type === 'joint' || this.account?.ownership_type === 'tenants_in_common';
    },

    /**
     * Map account type to tax product type for TaxStatusPanel
     */
    taxProductType() {
      if (!this.account) return 'easy_access';

      // Special handling for specific account types
      if (this.account.account_type === 'premium_bonds') {
        return 'premium_bonds';
      }
      if (this.account.account_type === 'nsi' || this.account.account_type === 'nsi_savings') {
        return 'nsi';
      }
      if (this.account.is_isa) {
        if (this.account.isa_type === 'lifetime' || this.account.isa_type === 'lisa') {
          return 'lifetime_isa';
        }
        if (this.account.account_type === 'junior_isa') {
          return 'junior_isa';
        }
        return 'cash_isa';
      }

      // Map based on access type
      return this.account.access_type || 'easy_access';
    },

    fullBalance() {
      if (!this.account) return 0;
      // Single-record pattern: DB stores FULL balance
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
    ...mapActions('savings', ['fetchAccount', 'updateAccount', 'deleteAccount']),

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

    async handleAccountSaved(savedData) {
      try {
        // Call the API to update the account
        await this.updateAccount({ id: this.accountId, accountData: savedData });
        this.showEditModal = false;

        // In preview mode, update local state only (API returned fake success, DB not updated)
        const isPreview = this.$store.getters['preview/isPreviewMode'];
        if (isPreview) {
          // Update local account with submitted data
          this.account = { ...this.account, ...savedData };
        } else {
          // Normal mode: reload from API
          await this.loadAccount();
        }
      } catch (error) {
        logger.error('Failed to update account:', error);
        this.error = 'Failed to update account. Please try again.';
      }
    },

    confirmDelete() {
      this.showDeleteConfirm = true;
    },

    async handleDelete() {
      try {
        await this.deleteAccount(this.accountId);
        this.showDeleteConfirm = false;
        this.$emit('deleted');
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

<style scoped>
/* Smooth scrolling on iOS */
.-webkit-overflow-scrolling-touch {
  -webkit-overflow-scrolling: touch;
}

/* Fade transition for tab switching */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
