<template>
  <div class="account-group">
    <h4 class="group-title">{{ title }}</h4>

    <div v-if="accounts.length > 0" class="account-list">
      <div
        v-for="account in accounts"
        :key="account.id"
        @click="$emit('select-account', account)"
        class="account-item"
      >
        <div class="account-info">
          <div class="account-name-row">
            <span class="account-name">{{ getAccountName(account) }}</span>
            <span v-if="isJointAccount(account)" class="joint-badge">
              Joint ({{ account.ownership_percentage }}%)
            </span>
          </div>
          <span class="account-provider">{{ getProvider(account) }}</span>
        </div>
        <div class="account-balance-col">
          <span
            class="account-balance"
            :class="balanceClass(account)"
          >
            {{ formatUserShare(account) }}
          </span>
          <span v-if="isJointAccount(account)" class="full-balance">
            Full: {{ formatBalance(account) }}
          </span>
        </div>
      </div>
    </div>

    <div v-else class="empty-state">
      <p class="empty-message">{{ emptyMessage }}</p>
      <button
        v-if="showAddButton"
        @click="$emit('add-account')"
        class="add-account-btn"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Add Account
      </button>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'AccountGroupList',

  mixins: [currencyMixin],

  props: {
    title: {
      type: String,
      required: true,
    },
    accounts: {
      type: Array,
      default: () => [],
    },
    emptyMessage: {
      type: String,
      default: 'No accounts',
    },
    isLiability: {
      type: Boolean,
      default: false,
    },
    showAddButton: {
      type: Boolean,
      default: false,
    },
  },

  emits: ['select-account', 'add-account'],

  methods: {
    getAccountName(account) {
      if (account.account_name) return account.account_name;
      if (account.liability_name) return account.liability_name;
      if (account.institution) return account.institution;
      return 'Unknown Account';
    },

    getProvider(account) {
      if (account.institution && account.account_name !== account.institution) {
        return account.institution;
      }
      if (account.lender) return account.lender;
      if (account.account_type) return this.formatAccountType(account.account_type);
      return '';
    },

    formatAccountType(type) {
      const types = {
        current_account: 'Current Account',
        savings_account: 'Savings Account',
        easy_access: 'Easy Access',
        notice: 'Notice Account',
        fixed: 'Fixed Term',
        cash_isa: 'Cash ISA',
        junior_isa: 'Junior ISA',
        premium_bonds: 'Premium Bonds',
      };
      return types[type] || type;
    },

    isJointAccount(account) {
      return account.ownership_type === 'joint' || account.ownership_type === 'tenants_in_common';
    },

    getUserShare(account) {
      const balance = account.current_balance || 0;
      if (this.isJointAccount(account) && account.ownership_percentage) {
        return balance * (account.ownership_percentage / 100);
      }
      return balance;
    },

    formatUserShare(account) {
      const share = this.getUserShare(account);
      if (this.isLiability) {
        return `-${this.formatCurrency(Math.abs(share))}`;
      }
      return this.formatCurrency(share);
    },

    formatBalance(account) {
      const balance = account.current_balance || 0;
      if (this.isLiability) {
        return `-${this.formatCurrency(Math.abs(balance))}`;
      }
      return this.formatCurrency(balance);
    },

    balanceClass(account) {
      if (this.isLiability) return 'text-red-600';
      const balance = this.getUserShare(account);
      return balance >= 0 ? 'text-green-600' : 'text-red-600';
    },
  },
};
</script>

<style scoped>
.account-group {
  background: white;
  border-radius: 12px;
  padding: 16px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.group-title {
  font-size: 14px;
  font-weight: 600;
  @apply text-neutral-500;
  margin: 0 0 12px 0;
}

.account-list {
  display: flex;
  flex-direction: column;
}

.account-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 0;
  cursor: pointer;
  @apply border-b border-savannah-100;
  transition: all 0.2s;
}

.account-item:last-child {
  border-bottom: none;
}

.account-item:hover {
  padding-left: 8px;
}

.account-item:hover .account-name {
  @apply text-violet-600;
}

.account-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
  flex: 1;
}

.account-name-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.account-name {
  font-size: 14px;
  font-weight: 600;
  @apply text-horizon-500;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  transition: color 0.2s;
}

.joint-badge {
  padding: 2px 6px;
  border-radius: 4px;
  font-size: 10px;
  font-weight: 600;
  @apply bg-violet-50;
  @apply text-violet-700;
  white-space: nowrap;
}

.account-provider {
  font-size: 12px;
  @apply text-horizon-400;
}

.account-balance-col {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 2px;
  flex-shrink: 0;
  margin-left: 12px;
}

.account-balance {
  font-size: 14px;
  font-weight: 700;
}

.full-balance {
  font-size: 11px;
  @apply text-horizon-400;
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  padding: 8px 0;
}

.empty-message {
  font-size: 13px;
  @apply text-horizon-400;
  text-align: center;
  margin: 0;
}

.add-account-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  font-size: 13px;
  font-weight: 500;
  @apply text-horizon-500 bg-light-pink-100;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s;
}

.add-account-btn:hover {
  @apply bg-light-pink-200;
}
</style>
