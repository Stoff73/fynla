<template>
  <div class="investment-detail-inline">
    <!-- Back Button -->
    <button @click="$emit('back')" class="back-button mb-4">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
      </svg>
      Back to Investments
    </button>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
      <p class="mt-4 text-gray-600">Loading account details...</p>
    </div>

    <!-- Account Content -->
    <div v-else class="space-y-6">
      <!-- Header -->
      <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex justify-between items-start">
          <div>
            <div class="flex items-center gap-3 mb-2">
              <span :class="['badge', getOwnershipBadgeClass(account.ownership_type)]">
                {{ formatOwnershipType(account.ownership_type) }}
              </span>
              <span :class="['badge', accountTypeBadgeClass(account.account_type)]">
                {{ formatAccountType(account.account_type) }}
              </span>
            </div>
            <h1 class="text-3xl font-bold text-gray-900">{{ account.provider }}</h1>
            <p class="text-lg text-gray-600 mt-1">{{ account.account_name }}</p>
          </div>
          <div class="flex space-x-2">
            <button
              @click="showEditModal = true"
              class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
            >
              Edit
            </button>
            <button
              @click="confirmDelete"
              class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors"
            >
              Delete
            </button>
          </div>
        </div>

        <!-- Key Metrics -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
          <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
            <p class="text-sm text-gray-600">Current Value</p>
            <p class="text-2xl font-bold text-blue-600">{{ formatCurrency(account.current_value) }}</p>
            <p v-if="account.ownership_type === 'joint'" class="text-sm text-purple-600 mt-1">
              Your {{ account.ownership_percentage ?? 50 }}% share: {{ formatCurrency(userShareValue) }}
            </p>
          </div>
          <div class="bg-gray-50 rounded-lg p-4">
            <p class="text-sm text-gray-600">YTD Return</p>
            <p class="text-2xl font-bold" :class="getReturnColorClass(account.ytd_return)">
              {{ formatReturn(account.ytd_return) }}
            </p>
          </div>
          <div class="bg-gray-50 rounded-lg p-4">
            <p class="text-sm text-gray-600">Holdings</p>
            <p class="text-2xl font-bold text-gray-900">{{ holdingsCount }}</p>
          </div>
        </div>

        <!-- ISA Allowance Info -->
        <div v-if="account.account_type === 'isa'" class="mt-4 bg-green-50 rounded-lg p-4 border border-green-200">
          <div class="flex justify-between items-center">
            <div>
              <p class="text-sm text-gray-600">ISA Contributions (This Year)</p>
              <p class="text-xl font-bold text-green-600">{{ formatCurrency(account.isa_subscription_current_year || 0) }}</p>
            </div>
            <div class="text-right">
              <p class="text-sm text-gray-600">Allowance Remaining</p>
              <p class="text-xl font-bold" :class="getIsaRemainingClass()">{{ formatCurrency(isaRemaining) }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Tabs -->
      <div class="bg-white rounded-lg shadow-md">
        <div class="border-b border-gray-200">
          <nav class="flex -mb-px overflow-x-auto">
            <button
              v-for="tab in tabs"
              :key="tab.id"
              @click="activeTab = tab.id"
              class="px-6 py-3 border-b-2 font-medium text-sm transition-colors whitespace-nowrap"
              :class="
                activeTab === tab.id
                  ? 'border-blue-600 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              "
            >
              {{ tab.label }}
            </button>
          </nav>
        </div>

        <div class="p-6">
          <!-- Overview Tab -->
          <AccountSummaryPanel
            v-if="activeTab === 'overview'"
            :account="account"
          />

          <!-- Holdings Tab -->
          <AccountHoldingsPanel
            v-else-if="activeTab === 'holdings'"
            :account="account"
            @open-holding-modal="openHoldingModal"
          />

          <!-- Performance Tab (Coming Soon) -->
          <div v-else-if="activeTab === 'performance'" class="relative">
            <div class="absolute inset-0 flex items-center justify-center z-10 pointer-events-none">
              <div class="bg-amber-100 border-2 border-amber-400 rounded-lg px-8 py-4 transform -rotate-12 shadow-lg">
                <p class="text-2xl font-bold text-amber-700">Coming Soon</p>
              </div>
            </div>
            <div class="opacity-50">
              <AccountPerformancePanel :account="account" />
            </div>
          </div>

          <!-- Fees Tab (Coming Soon) -->
          <div v-else-if="activeTab === 'fees'" class="relative">
            <div class="absolute inset-0 flex items-center justify-center z-10 pointer-events-none">
              <div class="bg-amber-100 border-2 border-amber-400 rounded-lg px-8 py-4 transform -rotate-12 shadow-lg">
                <p class="text-2xl font-bold text-amber-700">Coming Soon</p>
              </div>
            </div>
            <div class="opacity-50">
              <AccountFeesPanel :account="account" />
            </div>
          </div>

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
      v-if="showHoldingModal"
      :account-id="account.id"
      :holding="editingHolding"
      :is-edit="!!editingHolding"
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
import HoldingForm from '@/components/Investment/HoldingForm.vue';
import { TAX_CONFIG } from '@/constants/taxConfig';

export default {
  name: 'InvestmentDetailInline',

  components: {
    AccountForm,
    ConfirmationModal,
    AccountSummaryPanel,
    AccountHoldingsPanel,
    AccountPerformancePanel,
    AccountFeesPanel,
    HoldingForm,
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
      activeTab: 'overview',
      loading: false,
      showEditModal: false,
      showDeleteConfirm: false,
      showHoldingModal: false,
      editingHolding: null,
    };
  },

  computed: {
    tabs() {
      return [
        { id: 'overview', label: 'Overview' },
        { id: 'holdings', label: 'Holdings' },
        { id: 'performance', label: 'Performance' },
        { id: 'fees', label: 'Fees' },
        { id: 'documents', label: 'Documents' },
      ];
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

    isaRemaining() {
      const contributions = this.account.isa_subscription_current_year || 0;
      return Math.max(0, TAX_CONFIG.ISA_ANNUAL_ALLOWANCE - contributions);
    },
  },

  methods: {
    ...mapActions('investment', ['updateAccount', 'deleteAccount', 'fetchInvestmentData']),

    formatCurrency(value) {
      if (value === null || value === undefined) return '£0';
      return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: 'GBP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
      }).format(value);
    },

    formatReturn(value) {
      if (!value && value !== 0) return 'N/A';
      const sign = value >= 0 ? '+' : '';
      return `${sign}${value.toFixed(2)}%`;
    },

    formatAccountType(type) {
      const types = {
        'isa': 'Stocks & Shares ISA',
        'sipp': 'Self-Invested Pension',
        'gia': 'General Investment',
        'pension': 'Pension',
        'nsi': 'NS&I',
        'onshore_bond': 'Onshore Bond',
        'offshore_bond': 'Offshore Bond',
        'vct': 'Venture Capital Trust',
        'eis': 'Enterprise Scheme',
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
        other: 'badge-other',
      };
      return classes[type] || 'badge-other';
    },

    getReturnColorClass(value) {
      if (!value && value !== 0) return 'text-gray-600';
      return value >= 0 ? 'text-green-600' : 'text-red-600';
    },

    getIsaRemainingClass() {
      if (this.isaRemaining <= 0) return 'text-red-600';
      if (this.isaRemaining < 5000) return 'text-amber-600';
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
        this.$emit('back');
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

    async handleHoldingSave() {
      this.closeHoldingModal();
      await this.fetchInvestmentData();
      this.$emit('updated');
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
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  color: #374151;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.back-button:hover {
  background: #f3f4f6;
  border-color: #d1d5db;
}

.badge {
  display: inline-block;
  padding: 4px 10px;
  font-size: 12px;
  font-weight: 600;
  border-radius: 6px;
}

.badge-individual {
  background: #f3f4f6;
  color: #374151;
}

.badge-joint {
  background: #f3e8ff;
  color: #7c3aed;
}

.badge-trust {
  background: #fef3c7;
  color: #92400e;
}

.badge-isa {
  background: #d1fae5;
  color: #065f46;
}

.badge-gia {
  background: #dbeafe;
  color: #1e40af;
}

.badge-sipp {
  background: #e9d5ff;
  color: #6b21a8;
}

.badge-nsi {
  background: #e0e7ff;
  color: #3730a3;
}

.badge-bond {
  background: #ffedd5;
  color: #9a3412;
}

.badge-vct {
  background: #fce7f3;
  color: #9d174d;
}

.badge-other {
  background: #f3f4f6;
  color: #374151;
}
</style>
