<template>
  <div class="px-4 pt-4 pb-6">
    <MobileDetailSkeleton v-if="loading" :rows="5" />

    <template v-else-if="hasData">
      <MobileHeroCard title="Investment" :value="formatCurrency(totalPortfolioValue)" subtitle="Portfolio value" />
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
              variant="investment"
            />
          </div>
        </template>
        <p v-else class="px-4 py-6 text-sm text-neutral-500 text-center">No accounts added yet</p>
      </MobileAccordionSection>

      <!-- Holdings -->
      <MobileAccordionSection
        title="Holdings"
        :badge="holdingsCount || null"
        class="mb-3"
      >
        <template v-if="allHoldings.length">
          <div class="divide-y divide-light-gray">
            <MobileHoldingRow
              v-for="holding in allHoldings"
              :key="holding.id"
              :holding="holding"
              :allocation-pct="holdingAllocation(holding)"
            />
          </div>
        </template>
        <p v-else class="px-4 py-6 text-sm text-neutral-500 text-center">No holdings data</p>
      </MobileAccordionSection>

      <!-- Allocation -->
      <MobileAccordionSection title="Allocation" class="mb-3">
        <MobileAllocationChart v-if="allocationItems.length" :items="allocationItems" />
        <p v-else class="px-4 py-6 text-sm text-neutral-500 text-center">No allocation data</p>
      </MobileAccordionSection>

      <!-- Performance -->
      <MobileAccordionSection title="Performance" class="mb-3">
        <div class="divide-y divide-light-gray">
          <MobileDataRow label="Portfolio value" :value="totalPortfolioValue" type="currency" />
          <MobileDataRow label="Unrealised gains" :value="unrealisedGains" type="currency" :status="unrealisedGains >= 0 ? 'good' : 'danger'" />
          <MobileDataRow label="Accounts" :value="accountsCount" />
          <MobileDataRow label="Holdings" :value="holdingsCount" />
        </div>
      </MobileAccordionSection>

      <!-- Fees -->
      <MobileAccordionSection title="Fees" class="mb-3">
        <div class="divide-y divide-light-gray">
          <MobileDataRow label="Total annual fees" :value="totalFees" type="currency" />
          <MobileDataRow label="Fee drag" :value="feeDragPercent" type="percentage" :status="feeDragPercent > 1 ? 'warning' : 'good'" />
        </div>
      </MobileAccordionSection>
    </template>

    <MobileEmptyState v-else title="No investment data yet" subtitle="Your investment portfolio will appear here" />
  </div>
</template>

<script>
import { mapState, mapGetters } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';
import MobileAccordionSection from '@/mobile/components/MobileAccordionSection.vue';
import MobileDataRow from '@/mobile/components/MobileDataRow.vue';
import MobileAccountCard from '@/mobile/components/MobileAccountCard.vue';
import MobileHoldingRow from '@/mobile/components/MobileHoldingRow.vue';
import MobileAllocationChart from '@/mobile/components/MobileAllocationChart.vue';
import MobileHeroCard from '@/mobile/components/MobileHeroCard.vue';
import MobileFynCard from '@/mobile/components/MobileFynCard.vue';
import MobileDetailSkeleton from '@/mobile/components/MobileDetailSkeleton.vue';
import MobileEmptyState from '@/mobile/components/MobileEmptyState.vue';

export default {
  name: 'InvestmentDetail',

  components: { MobileAccordionSection, MobileDataRow, MobileAccountCard, MobileHoldingRow, MobileAllocationChart, MobileHeroCard, MobileFynCard, MobileDetailSkeleton, MobileEmptyState },

  mixins: [currencyMixin],

  data() {
    return { loading: false };
  },

  computed: {
    ...mapState('investment', ['accounts']),
    ...mapGetters('investment', [
      'totalPortfolioValue',
      'allHoldings',
      'holdingsCount',
      'accountsCount',
      'totalFees',
      'feeDragPercent',
      'unrealisedGains',
      'assetAllocation',
    ]),

    hasData() {
      return this.accounts?.length > 0 || this.totalPortfolioValue > 0;
    },

    allocationItems() {
      if (!this.assetAllocation) return [];
      return Object.entries(this.assetAllocation)
        .filter(([, val]) => val > 0)
        .map(([label, value]) => ({
          label: label.charAt(0).toUpperCase() + label.slice(1).replace(/_/g, ' '),
          value,
          percentage: value,
        }));
    },

    fynSummary() {
      return 'Your investment portfolio is working to grow your wealth over time.';
    },
  },

  methods: {
    holdingAllocation(holding) {
      if (!this.totalPortfolioValue || !holding.current_value) return null;
      return (holding.current_value / this.totalPortfolioValue) * 100;
    },
  },

  async created() {
    this.loading = true;
    try {
      await this.$store.dispatch('investment/fetchAccounts');
      // Fetch analysis (fees, allocation, gains) in parallel
      await this.$store.dispatch('investment/analyseInvestment').catch(() => {});
    } catch {
      // Data unavailable
    } finally {
      this.loading = false;
    }
  },
};
</script>
