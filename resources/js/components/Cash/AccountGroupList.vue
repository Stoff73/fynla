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
          <span class="account-name">{{ getAccountName(account) }}</span>
          <span class="account-provider">{{ getProvider(account) }}</span>
        </div>
        <span
          class="account-balance"
          :class="balanceClass(account)"
        >
          {{ formatBalance(account) }}
        </span>
      </div>
    </div>

    <p v-else class="empty-message">{{ emptyMessage }}</p>
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
  },

  emits: ['select-account'],

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

    formatBalance(account) {
      const balance = account.current_balance || 0;
      if (this.isLiability) {
        return `-${this.formatCurrency(Math.abs(balance))}`;
      }
      return this.formatCurrency(balance);
    },

    balanceClass(account) {
      if (this.isLiability) return 'text-red-600';
      const balance = account.current_balance || 0;
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
  color: #374151;
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
  border-bottom: 1px solid #f3f4f6;
  transition: all 0.2s;
}

.account-item:last-child {
  border-bottom: none;
}

.account-item:hover {
  padding-left: 8px;
}

.account-item:hover .account-name {
  color: #7c3aed;
}

.account-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
  flex: 1;
}

.account-name {
  font-size: 14px;
  font-weight: 600;
  color: #111827;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  transition: color 0.2s;
}

.account-provider {
  font-size: 12px;
  color: #9ca3af;
}

.account-balance {
  font-size: 14px;
  font-weight: 700;
  flex-shrink: 0;
  margin-left: 12px;
}

.empty-message {
  font-size: 13px;
  color: #9ca3af;
  text-align: center;
  padding: 8px 0;
  margin: 0;
}
</style>
