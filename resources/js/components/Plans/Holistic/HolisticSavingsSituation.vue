<template>
  <div v-if="hasSavingsData" class="space-y-4">
    <!-- Savings Accounts -->
    <div v-if="savingsAccounts.length" class="bg-white rounded-lg shadow-sm border border-light-gray p-5">
      <h3 class="text-sm font-semibold text-horizon-500 mb-3">Savings Accounts</h3>
      <div class="space-y-2">
        <div
          v-for="account in savingsAccounts"
          :key="account.id"
          class="flex items-center justify-between py-2 border-b border-savannah-100 last:border-b-0"
        >
          <div>
            <p class="text-sm font-medium text-horizon-500">{{ account.institution }}</p>
            <p class="text-xs text-neutral-500">{{ formatAccountType(account.type) }} &middot; {{ account.interest_rate }}% interest</p>
          </div>
          <p class="text-sm font-semibold text-horizon-500">{{ formatCurrency(account.balance) }}</p>
        </div>
      </div>
      <div class="mt-3 pt-3 border-t border-light-gray flex justify-between">
        <span class="text-sm font-medium text-horizon-500">Total Savings Value</span>
        <span class="text-sm font-bold text-horizon-500">{{ formatCurrency(situation.total_savings_value) }}</span>
      </div>
    </div>

    <!-- Emergency Fund Indicator -->
    <div v-if="situation.emergency_fund" class="grid grid-cols-1 md:grid-cols-2 gap-3">
      <div class="bg-white rounded-lg border border-light-gray p-4 text-center">
        <p class="text-xs text-neutral-500 uppercase">Emergency Fund</p>
        <p class="text-lg font-bold" :class="emergencyFundColor">{{ emergencyFundMonths }} months</p>
        <p class="text-xs text-neutral-500">{{ situation.emergency_fund.category || '' }}</p>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'HolisticSavingsSituation',

  mixins: [currencyMixin],

  props: {
    situation: {
      type: Object,
      required: true,
    },
  },

  computed: {
    savingsAccounts() {
      return this.situation.savings_accounts || [];
    },

    hasSavingsData() {
      return this.savingsAccounts.length > 0 || this.situation.total_savings_value;
    },

    emergencyFundMonths() {
      return Math.round(this.situation.emergency_fund?.runway_months || 0);
    },

    emergencyFundColor() {
      const months = this.emergencyFundMonths;
      if (months >= 6) return 'text-spring-700';
      if (months >= 3) return 'text-violet-700';
      return 'text-raspberry-700';
    },
  },
};
</script>
