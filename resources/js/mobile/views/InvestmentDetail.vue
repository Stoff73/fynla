<template>
  <div class="px-4 pt-4 pb-6">
    <div v-if="loading" class="space-y-3">
      <div class="bg-white rounded-xl p-6 animate-pulse">
        <div class="w-24 h-8 bg-savannah-100 rounded mx-auto"></div>
      </div>
      <div v-for="n in 5" :key="n" class="bg-white rounded-xl p-4 animate-pulse">
        <div class="w-40 h-4 bg-savannah-100 rounded"></div>
      </div>
    </div>

    <template v-else-if="hasData">
      <!-- Hero -->
      <div class="bg-white rounded-xl border border-light-gray p-6 text-center mb-4">
        <span class="text-3xl block mb-2">{{'📈'}}</span>
        <h2 class="text-lg font-bold text-horizon-500">Investment</h2>
        <p class="text-2xl font-black text-horizon-500 mt-3">{{ formatCurrency(totalPortfolioValue) }}</p>
        <p class="text-xs text-neutral-500 mt-1">Portfolio value</p>
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
              variant="investment"
            />
          </div>
        </template>
        <p v-else class="px-4 py-6 text-sm text-neutral-500 text-center">No accounts added yet</p>
      </MobileAccordionSection>

      <!-- Holdings -->
      <MobileAccordionSection
        title="Holdings"
        icon="📄"
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
      <MobileAccordionSection title="Allocation" icon="🎯" class="mb-3">
        <MobileAllocationChart v-if="allocationItems.length" :items="allocationItems" />
        <p v-else class="px-4 py-6 text-sm text-neutral-500 text-center">No allocation data</p>
      </MobileAccordionSection>

      <!-- Performance -->
      <MobileAccordionSection title="Performance" icon="📊" class="mb-3">
        <div class="divide-y divide-light-gray">
          <MobileDataRow label="Portfolio value" :value="totalPortfolioValue" type="currency" />
          <MobileDataRow label="Unrealised gains" :value="unrealisedGains" type="currency" :status="unrealisedGains >= 0 ? 'good' : 'danger'" />
          <MobileDataRow label="Accounts" :value="accountsCount" />
          <MobileDataRow label="Holdings" :value="holdingsCount" />
        </div>
      </MobileAccordionSection>

      <!-- Fees -->
      <MobileAccordionSection title="Fees" icon="💷" class="mb-3">
        <div class="divide-y divide-light-gray">
          <MobileDataRow label="Total annual fees" :value="totalFees" type="currency" />
          <MobileDataRow label="Fee drag" :value="feeDragPercent" type="percentage" :status="feeDragPercent > 1 ? 'warning' : 'good'" />
        </div>
      </MobileAccordionSection>
    </template>

    <div v-else class="text-center py-16">
      <span class="text-4xl block mb-3">{{'📈'}}</span>
      <h3 class="text-base font-bold text-horizon-500 mb-1">No investment data yet</h3>
      <p class="text-sm text-neutral-500">Your investment portfolio will appear here</p>
    </div>
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

export default {
  name: 'InvestmentDetail',

  components: { MobileAccordionSection, MobileDataRow, MobileAccountCard, MobileHoldingRow, MobileAllocationChart },

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
