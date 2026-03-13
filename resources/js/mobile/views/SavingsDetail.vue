<template>
  <div class="px-4 pt-4 pb-6">
    <MobileDetailSkeleton v-if="loading" :rows="3" />

    <template v-else-if="hasData">
      <MobileHeroCard title="Savings" :value="formatCurrency(totalSavings)" subtitle="Total savings" />
      <MobileFynCard :summary="fynSummary" />

      <!-- Accounts -->
      <MobileAccordionSection
        title="Accounts"
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
      <MobileAccordionSection title="Emergency fund" class="mb-3">
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
      <MobileAccordionSection title="ISA allowance" class="mb-3">
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

    <MobileEmptyState v-else title="No savings data yet" subtitle="Your savings accounts will appear here" />
  </div>
</template>

<script>
import { mapState, mapGetters } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';
import MobileAccordionSection from '@/mobile/components/MobileAccordionSection.vue';
import MobileDataRow from '@/mobile/components/MobileDataRow.vue';
import MobileAccountCard from '@/mobile/components/MobileAccountCard.vue';
import MobileHeroCard from '@/mobile/components/MobileHeroCard.vue';
import MobileFynCard from '@/mobile/components/MobileFynCard.vue';
import MobileDetailSkeleton from '@/mobile/components/MobileDetailSkeleton.vue';
import MobileEmptyState from '@/mobile/components/MobileEmptyState.vue';

export default {
  name: 'SavingsDetail',

  components: { MobileAccordionSection, MobileDataRow, MobileAccountCard, MobileHeroCard, MobileFynCard, MobileDetailSkeleton, MobileEmptyState },

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
      const allowance = this.$store.state.savings.isaAllowance;
      if (!allowance) return 0;
      return (allowance.cash_isa_used || 0) + (allowance.stocks_shares_isa_used || 0);
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
