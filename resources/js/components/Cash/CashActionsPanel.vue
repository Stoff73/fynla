<template>
  <div class="cash-actions-panel">
    <!-- Payday Widget -->
    <div class="action-card">
      <template v-if="hasIncomeData && paydayDayOfMonth">
        <div class="payday-header">
          <h4 class="card-title">Payday</h4>
          <span class="payday-date">{{ formatPaydayDate }}</span>
        </div>
        <div class="payday-value">{{ payday.daysUntil }} days</div>
        <div class="payday-amount">
          <span class="text-green-600 font-bold">{{ formatCurrency(expectedPayAmount) }}</span>
        </div>
      </template>
      <template v-else-if="hasIncomeData">
        <h4 class="card-title">Payday</h4>
        <p class="empty-prompt">Set your payday to track days until your next pay.</p>
        <router-link to="/profile" class="add-info-link">Set payday</router-link>
      </template>
      <template v-else>
        <h4 class="card-title">Payday</h4>
        <p class="empty-prompt">Add your income details to track your payday and expected earnings.</p>
        <router-link to="/profile" class="add-info-link">Add income info</router-link>
      </template>
    </div>

    <!-- Cash Before Payday Widget -->
    <div class="action-card">
      <h4 class="card-title">Cash Before Payday</h4>
      <div class="clear-cash-value">{{ formatCurrency(clearCash.predictedAvailable) }}</div>
      <div class="detail-list">
        <div class="detail-row">
          <span>Pending transactions</span>
          <span class="text-blue-600">-{{ formatCurrency(clearCash.pendingTransactions) }}</span>
        </div>
        <div class="detail-row">
          <span>Upcoming bills</span>
          <span class="text-red-600">-{{ formatCurrency(clearCash.upcomingBills) }}</span>
        </div>
      </div>
    </div>

    <!-- Credit Card Spending Widget -->
    <div class="action-card">
      <h4 class="card-title">Credit Card Spending</h4>
      <div class="credit-card-value text-red-600">{{ formatCurrency(creditCardSpending.amount) }}</div>
      <div class="credit-card-subtext">This {{ creditCardSpending.month }}</div>
    </div>

    <!-- Alerts Widget -->
    <div v-if="alerts.length > 0" class="action-card">
      <h4 class="card-title">Alerts</h4>
      <div class="alerts-list">
        <div
          v-for="alert in alerts"
          :key="alert.id"
          class="alert-item"
          :class="alert.type"
        >
          <span class="alert-dot"></span>
          <span class="alert-message">{{ alert.message }}</span>
        </div>
      </div>
    </div>

    <!-- Tips Widget -->
    <div class="action-card">
      <div class="card-header">
        <h4 class="card-title">Tips</h4>
        <button @click="refreshTip" class="refresh-btn">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
          </svg>
        </button>
      </div>
      <p class="tip-content">{{ currentTip }}</p>
    </div>
  </div>
</template>

<script>
import { mapGetters, mapActions, mapState } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';
import { MOCK_CLEAR_CASH, MOCK_ALERTS, MOCK_CREDIT_CARD_SPENDING, getRandomTip } from './mockData';

export default {
  name: 'CashActionsPanel',

  mixins: [currencyMixin],

  data() {
    return {
      creditCardSpending: MOCK_CREDIT_CARD_SPENDING,
      clearCash: MOCK_CLEAR_CASH,
      alerts: MOCK_ALERTS,
      currentTip: getRandomTip(),
    };
  },

  computed: {
    ...mapState('userProfile', ['incomeOccupation']),
    ...mapGetters('userProfile', ['totalAnnualIncome']),

    hasIncomeData() {
      return this.totalAnnualIncome > 0;
    },

    // Get payday day of month from user profile (1-31)
    paydayDayOfMonth() {
      return this.incomeOccupation?.payday_day_of_month || null;
    },

    // Calculate next payday date based on payday_day_of_month
    // Adjusts for weekends - if payday falls on Sat/Sun, moves to preceding Friday
    nextPaydayDate() {
      if (!this.paydayDayOfMonth) return null;

      const today = new Date();
      const currentDay = today.getDate();
      const currentMonth = today.getMonth();
      const currentYear = today.getFullYear();

      let paydayMonth = currentMonth;
      let paydayYear = currentYear;

      // Calculate this month's payday first to check if we've passed it
      let daysInMonth = new Date(paydayYear, paydayMonth + 1, 0).getDate();
      let actualPayday = Math.min(this.paydayDayOfMonth, daysInMonth);
      let paydayDate = new Date(paydayYear, paydayMonth, actualPayday);

      // Adjust for weekends (move to Friday)
      const dayOfWeek = paydayDate.getDay();
      if (dayOfWeek === 6) paydayDate.setDate(paydayDate.getDate() - 1); // Saturday -> Friday
      if (dayOfWeek === 0) paydayDate.setDate(paydayDate.getDate() - 2); // Sunday -> Friday

      // If we've passed this month's adjusted payday, calculate next month's
      const todayNormalized = new Date(currentYear, currentMonth, currentDay);
      if (todayNormalized > paydayDate) {
        paydayMonth++;
        if (paydayMonth > 11) {
          paydayMonth = 0;
          paydayYear++;
        }

        daysInMonth = new Date(paydayYear, paydayMonth + 1, 0).getDate();
        actualPayday = Math.min(this.paydayDayOfMonth, daysInMonth);
        paydayDate = new Date(paydayYear, paydayMonth, actualPayday);

        // Adjust for weekends again for next month
        const nextDayOfWeek = paydayDate.getDay();
        if (nextDayOfWeek === 6) paydayDate.setDate(paydayDate.getDate() - 1);
        if (nextDayOfWeek === 0) paydayDate.setDate(paydayDate.getDate() - 2);
      }

      return paydayDate;
    },

    // Calculate days until next payday
    daysUntilPayday() {
      if (!this.nextPaydayDate) return null;

      const today = new Date();
      today.setHours(0, 0, 0, 0);

      const payday = new Date(this.nextPaydayDate);
      payday.setHours(0, 0, 0, 0);

      const diffTime = payday - today;
      const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

      return diffDays;
    },

    // Computed payday object for template compatibility
    payday() {
      return {
        daysUntil: this.daysUntilPayday,
        expectedDate: this.nextPaydayDate?.toISOString().split('T')[0] || null,
      };
    },

    expectedPayAmount() {
      // Get monthly income from user profile (annual / 12)
      return this.totalAnnualIncome / 12;
    },

    formatPaydayDate() {
      if (!this.nextPaydayDate) return '';
      return this.nextPaydayDate.toLocaleDateString('en-GB', {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
      });
    },
  },

  async mounted() {
    // Fetch profile data if income data not already loaded
    if (!this.incomeOccupation) {
      await this.fetchProfile();
    }
  },

  methods: {
    ...mapActions('userProfile', ['fetchProfile']),

    refreshTip() {
      this.currentTip = getRandomTip();
    },
  },
};
</script>

<style scoped>
.cash-actions-panel {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.action-card {
  background: white;
  border-radius: 12px;
  padding: 16px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}

.card-title {
  font-size: 14px;
  font-weight: 600;
  @apply text-neutral-500;
  margin: 0 0 12px 0;
}

.card-header .card-title {
  margin: 0;
}

/* Predicted Cash */
.clear-cash-value {
  font-size: 24px;
  font-weight: 700;
  @apply text-horizon-500;
  margin: 0 0 12px 0;
}

/* Detail List */
.detail-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding-top: 12px;
  @apply border-t border-light-gray;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
  @apply text-neutral-500;
}

/* Payday */
.payday-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.payday-header .card-title {
  margin: 0;
}

.payday-date {
  font-size: 13px;
  @apply text-neutral-500;
}

.payday-value {
  font-size: 24px;
  font-weight: 700;
  @apply text-horizon-500;
  margin-bottom: 8px;
}

.payday-amount {
  font-size: 16px;
}

.empty-prompt {
  font-size: 13px;
  @apply text-neutral-500;
  line-height: 1.5;
  margin: 0 0 12px 0;
}

.add-info-link {
  font-size: 13px;
  @apply text-purple-600;
  font-weight: 500;
  text-decoration: none;
}

.add-info-link:hover {
  text-decoration: underline;
}

/* Credit Card Spending */
.credit-card-value {
  font-size: 24px;
  font-weight: 700;
  margin-bottom: 8px;
}

.credit-card-subtext {
  font-size: 13px;
  @apply text-neutral-500;
}

/* Alerts */
.alerts-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.alert-item {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  font-size: 12px;
  @apply text-neutral-500;
}

.alert-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
  margin-top: 4px;
}

.alert-item.warning .alert-dot {
  @apply bg-blue-500;
}

.alert-item.info .alert-dot {
  @apply bg-raspberry-500;
}

.alert-message {
  line-height: 1.4;
}

/* Tips */
.tip-content {
  font-size: 13px;
  @apply text-neutral-500;
  line-height: 1.5;
  margin: 0;
}

.refresh-btn {
  padding: 4px;
  background: none;
  border: none;
  @apply text-horizon-400;
  cursor: pointer;
  transition: color 0.2s;
}

.refresh-btn:hover {
  @apply text-purple-600;
}

.refresh-btn svg {
  width: 16px;
  height: 16px;
}
</style>
