<template>
  <div class="mb-6">
    <PlanSectionHeader title="Current Situation" subtitle="Your investment and savings overview" color="violet" />

    <div class="space-y-4">
      <!-- Investment Accounts -->
      <div v-if="situation.investment_accounts && situation.investment_accounts.length" class="bg-white rounded-lg shadow-sm border border-light-gray p-5">
        <h3 class="text-sm font-semibold text-horizon-500 mb-3">Investment Accounts</h3>
        <div class="space-y-2">
          <div
            v-for="account in situation.investment_accounts"
            :key="account.id"
            class="flex items-center justify-between py-2 border-b border-savannah-100 last:border-b-0"
          >
            <div>
              <p class="text-sm font-medium text-horizon-500">{{ account.name }}</p>
              <p class="text-xs text-neutral-500">{{ formatAccountType(account.type) }} &middot; {{ account.provider || 'No provider' }} &middot; {{ account.holdings_count }} holding(s)</p>
            </div>
            <p class="text-sm font-semibold text-horizon-500">{{ formatCurrency(account.value) }}</p>
          </div>
        </div>
        <div class="mt-3 pt-3 border-t border-light-gray flex justify-between">
          <span class="text-sm font-medium text-neutral-500">Total Investment Value</span>
          <span class="text-sm font-bold text-horizon-500">{{ formatCurrency(situation.total_investment_value) }}</span>
        </div>
      </div>

      <!-- Savings Accounts -->
      <div v-if="situation.savings_accounts && situation.savings_accounts.length" class="bg-white rounded-lg shadow-sm border border-light-gray p-5">
        <h3 class="text-sm font-semibold text-horizon-500 mb-3">Savings Accounts</h3>
        <div class="space-y-2">
          <div
            v-for="account in situation.savings_accounts"
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
          <span class="text-sm font-medium text-neutral-500">Total Savings Value</span>
          <span class="text-sm font-bold text-horizon-500">{{ formatCurrency(situation.total_savings_value) }}</span>
        </div>
      </div>

      <!-- Key Indicators Row -->
      <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
        <div class="bg-white rounded-lg border border-light-gray p-4 text-center">
          <p class="text-xs text-neutral-500 uppercase">Emergency Fund</p>
          <p class="text-lg font-bold" :class="emergencyFundColor">{{ emergencyFundMonths }} months</p>
          <p class="text-xs text-neutral-500">{{ situation.emergency_fund?.category || '' }}</p>
        </div>
        <div class="bg-white rounded-lg border border-light-gray p-4 text-center">
          <p class="text-xs text-neutral-500 uppercase">ISA Used</p>
          <p class="text-lg font-bold text-horizon-500">{{ formatCurrency(situation.isa_allowance?.used || 0) }}</p>
        </div>
        <div class="bg-white rounded-lg border border-light-gray p-4 text-center">
          <p class="text-xs text-neutral-500 uppercase">ISA Remaining</p>
          <p class="text-lg font-bold text-spring-700">{{ formatCurrency(situation.isa_allowance?.remaining || 0) }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';
import PlanSectionHeader from '@/components/Plans/Shared/PlanSectionHeader.vue';

export default {
  name: 'InvestmentCurrentSituation',

  components: { PlanSectionHeader },

  mixins: [currencyMixin],

  props: {
    situation: { type: Object, required: true },
  },

  computed: {
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
