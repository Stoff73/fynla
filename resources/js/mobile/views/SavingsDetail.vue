<template>
  <div class="px-4 pt-4 pb-6">
    <div v-if="loading" class="space-y-3">
      <div class="bg-white rounded-xl p-6 animate-pulse">
        <div class="w-24 h-8 bg-savannah-100 rounded mx-auto"></div>
      </div>
      <div v-for="n in 3" :key="n" class="bg-white rounded-xl p-4 animate-pulse">
        <div class="w-40 h-4 bg-savannah-100 rounded"></div>
      </div>
    </div>

    <template v-else-if="hasData">
      <!-- Hero -->
      <div class="bg-white rounded-xl border border-light-gray p-6 text-center mb-4">
        <span class="text-3xl block mb-2">{{'💰'}}</span>
        <h2 class="text-lg font-bold text-horizon-500">Savings</h2>
        <p class="text-2xl font-black text-horizon-500 mt-3">{{ formatCurrency(totalSavings) }}</p>
        <p class="text-xs text-neutral-500 mt-1">Total savings</p>
      </div>

      <!-- Fyn -->
      <div class="bg-horizon-500 rounded-xl p-4 flex items-start gap-3 mb-4">
        <img src="/images/logos/favicon.png" alt="Fyn" class="w-8 h-8 rounded-full flex-shrink-0" />
        <p class="text-white text-sm leading-relaxed">{{ fynSummary }}</p>
      </div>

      <!-- Accounts -->
      <MobileAccordionSection
        title="Accounts"
        icon="🏦"
        :badge="accounts.length || null"
        :default-open="true"
        class="mb-3"
      >
        <template v-if="accounts.length">
          <div class="divide-y divide-light-gray">
            <MobileAccountCard
              v-for="account in accounts"
              :key="account.id"
              :account="account"
              variant="savings"
            />
          </div>
        </template>
        <p v-else class="px-4 py-6 text-sm text-neutral-500 text-center">No accounts added yet</p>
      </MobileAccordionSection>

      <!-- Emergency Fund -->
      <MobileAccordionSection title="Emergency fund" icon="🆘" class="mb-3">
        <div class="divide-y divide-light-gray">
          <MobileDataRow label="Emergency savings" :value="emergencyFundTotal" type="currency" />
          <MobileDataRow
            label="Months covered"
            :value="emergencyFundMonths"
            :status="emergencyFundMonths < 3 ? 'warning' : 'good'"
          />
          <MobileDataRow label="Target" value="3-6 months of expenditure" />
          <MobileDataRow
            v-if="monthlyExpenditure"
            label="Monthly expenditure"
            :value="monthlyExpenditure"
            type="currency"
          />
        </div>
      </MobileAccordionSection>

      <!-- ISA Allowance -->
      <MobileAccordionSection title="ISA allowance" icon="📊" class="mb-3">
        <div class="divide-y divide-light-gray">
          <MobileDataRow label="Total allowance" :value="isaTotal" type="currency" />
          <MobileDataRow label="Used this year" :value="isaUsed" type="currency" />
          <MobileDataRow
            label="Remaining"
            :value="isaRemaining"
            type="currency"
            :status="isaRemaining > 0 ? 'good' : 'warning'"
          />
          <MobileDataRow label="Usage" :value="isaUsagePercent" type="percentage" />
        </div>
      </MobileAccordionSection>
    </template>

    <div v-else class="text-center py-16">
      <span class="text-4xl block mb-3">{{'💰'}}</span>
      <h3 class="text-base font-bold text-horizon-500 mb-1">No savings data yet</h3>
      <p class="text-sm text-neutral-500">Your savings accounts will appear here</p>
    </div>
  </div>
</template>

<script>
import { mapState, mapGetters } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';
import MobileAccordionSection from '@/mobile/components/MobileAccordionSection.vue';
import MobileDataRow from '@/mobile/components/MobileDataRow.vue';
import MobileAccountCard from '@/mobile/components/MobileAccountCard.vue';

export default {
  name: 'SavingsDetail',

  components: { MobileAccordionSection, MobileDataRow, MobileAccountCard },

  mixins: [currencyMixin],

  data() {
    return { loading: false };
  },

  computed: {
    ...mapState('savings', ['accounts']),
    ...mapGetters('savings', [
      'totalSavings',
      'emergencyFundTotal',
      'emergencyFundRunway',
      'isaAllowanceRemaining',
      'isaUsagePercent',
      'currentYearISASubscription',
      'monthlyExpenditure',
    ]),

    hasData() {
      return this.accounts?.length > 0 || this.totalSavings > 0;
    },

    emergencyFundMonths() {
      return typeof this.emergencyFundRunway === 'number' ? parseFloat(this.emergencyFundRunway.toFixed(1)) : 0;
    },

    isaTotal() {
      const allowance = this.$store.state.savings.isaAllowance;
      return allowance?.total_allowance || 20000;
    },

    isaUsed() {
      return this.currentYearISASubscription || 0;
    },

    isaRemaining() {
      return this.isaAllowanceRemaining || 0;
    },

    fynSummary() {
      if (this.emergencyFundMonths < 3) {
        return `Your emergency fund covers ${this.emergencyFundMonths.toFixed(1)} months of expenditure. Building towards 3-6 months is recommended.`;
      }
      return `Your emergency fund covers ${this.emergencyFundMonths.toFixed(1)} months of expenditure. Well done!`;
    },
  },

  async created() {
    this.loading = true;
    try {
      await this.$store.dispatch('savings/fetchSavingsData');
    } catch {
      // Data unavailable
    } finally {
      this.loading = false;
    }
  },
};
</script>
