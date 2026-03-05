<template>
  <div class="account-summary-panel">
    <!-- Current Accounts -->
    <AccountGroupList
      title="Current Accounts"
      :accounts="groupedAccounts.current"
      empty-message="No current accounts"
      :show-add-button="true"
      @select-account="handleSelectAccount"
      @add-account="handleAddCurrentAccount"
    />

    <!-- Current Accounts Monthly Flow -->
    <div class="flow-card">
      <h4 class="card-title">{{ currentMonth }} Current Accounts</h4>
      <div class="flow-list">
        <div class="flow-row">
          <span>Money In</span>
          <span class="text-green-600 font-bold">{{ formatCurrency(monthlyIncome) }}</span>
        </div>
        <div class="flow-row">
          <span>Money Out</span>
          <span class="text-red-600 font-bold">{{ formatCurrency(monthlyExpenditure) }}</span>
        </div>
        <div class="flow-row net-row">
          <span>Net</span>
          <span class="font-bold" :class="netFlowClass">{{ formatCurrency(netFlow) }}</span>
        </div>
      </div>
    </div>

    <!-- Savings Accounts -->
    <AccountGroupList
      title="Savings Accounts"
      :accounts="groupedAccounts.savings"
      empty-message="No savings accounts"
      :show-add-button="true"
      @select-account="handleSelectAccount"
      @add-account="handleAddSavingsAccount"
    />

    <!-- Savings Monthly Flow -->
    <div class="flow-card">
      <h4 class="card-title">{{ currentMonth }} Savings</h4>
      <div class="flow-list">
        <div class="flow-row">
          <span>Deposits</span>
          <span class="text-green-600 font-bold">{{ formatCurrency(savingsFlow.income.amount) }}</span>
        </div>
        <div class="flow-row">
          <span>Withdrawals</span>
          <span class="text-red-600 font-bold">{{ formatCurrency(savingsFlow.expenses.amount) }}</span>
        </div>
        <div class="flow-row net-row">
          <span>Net</span>
          <span class="font-bold" :class="netSavingsFlowClass">{{ formatCurrency(netSavingsFlow) }}</span>
        </div>
      </div>
    </div>

    <!-- Credit Cards -->
    <AccountGroupList
      v-if="creditCards.length > 0"
      title="Credit Cards"
      :accounts="creditCards"
      :is-liability="true"
      empty-message="No credit cards"
      @select-account="handleSelectAccount"
    />
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';
import AccountGroupList from './AccountGroupList.vue';
import { MOCK_SAVINGS_FLOW } from './mockData';

export default {
  name: 'AccountSummaryPanel',

  mixins: [currencyMixin],

  components: {
    AccountGroupList,
  },

  props: {
    accounts: {
      type: Array,
      default: () => [],
    },
    creditCards: {
      type: Array,
      default: () => [],
    },
    monthlyIncome: {
      type: Number,
      default: 0,
    },
    monthlyExpenditure: {
      type: Number,
      default: 0,
    },
  },

  emits: ['select-account', 'add-account'],

  data() {
    return {
      savingsFlow: MOCK_SAVINGS_FLOW,
    };
  },

  computed: {
    currentMonth() {
      return new Date().toLocaleString('en-GB', { month: 'long' });
    },

    groupedAccounts() {
      return {
        current: this.accounts.filter(a => a.account_type === 'current_account'),
        savings: this.accounts.filter(a => a.account_type !== 'current_account'),
      };
    },

    netFlow() {
      return this.monthlyIncome - this.monthlyExpenditure;
    },

    netFlowClass() {
      return this.netFlow >= 0 ? 'text-green-600' : 'text-red-600';
    },

    netSavingsFlow() {
      return this.savingsFlow.income.amount - this.savingsFlow.expenses.amount;
    },

    netSavingsFlowClass() {
      return this.netSavingsFlow >= 0 ? 'text-green-600' : 'text-red-600';
    },
  },

  methods: {
    handleSelectAccount(account) {
      this.$emit('select-account', account);
    },

    handleAddCurrentAccount() {
      this.$emit('add-account', 'current_account');
    },

    handleAddSavingsAccount() {
      this.$emit('add-account', 'savings_account');
    },
  },
};
</script>

<style scoped>
.account-summary-panel {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.flow-card {
  background: white;
  border-radius: 12px;
  padding: 16px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.card-title {
  font-size: 14px;
  font-weight: 600;
  @apply text-neutral-500;
  margin: 0 0 12px 0;
}

.flow-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.flow-row {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
  @apply text-neutral-500;
}

.flow-row.net-row {
  padding-top: 8px;
  margin-top: 4px;
  @apply border-t border-light-gray;
}

.font-bold {
  font-weight: 700;
}
</style>
