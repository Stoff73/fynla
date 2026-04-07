<template>
  <div class="strategy-section">
    <h4 class="text-lg font-bold text-horizon-500 mb-4">Strategies</h4>

    <!-- Recommendations Grid -->
    <div v-if="accountRecommendations.length > 0" class="strategy-grid">
      <div
        v-for="(rec, index) in displayedRecommendations"
        :key="index"
        class="strategy-card cursor-pointer hover:shadow-md transition-shadow"
        @click="handleRecommendationAction(rec)"
      >
        <span :class="getPriorityBadgeClass(rec.priority)">
          {{ getPriorityLabel(rec.priority) }}
        </span>
        <h5 class="font-medium text-horizon-500 mt-2">{{ rec.title }}</h5>
        <p class="text-sm text-neutral-500 mt-1">{{ rec.description }}</p>
      </div>
    </div>

    <!-- All good state -->
    <div v-else class="text-center py-6">
      <p class="text-sm text-spring-600 font-medium">Looking Good</p>
      <p class="text-xs text-neutral-500 mt-1">No recommendations for this account</p>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';
import { ISA_ANNUAL_ALLOWANCE } from '@/constants/taxConfig';

export default {
  name: 'AccountStrategyCard',

  mixins: [currencyMixin],

  props: {
    account: {
      type: Object,
      required: true,
    },
    rebalancingData: {
      type: Object,
      default: null,
    },
  },

  emits: ['change-tab', 'add-holding'],

  computed: {
    holdings() {
      return this.account.holdings || [];
    },

    hasRiskProfile() {
      return this.$store.getters['investment/hasRiskProfile'];
    },

    totalHoldingsValue() {
      if (!this.holdings.length) return 0;
      return this.holdings.reduce((sum, h) => sum + (h.current_value || 0), 0);
    },

    displayedRecommendations() {
      return this.accountRecommendations.slice(0, 3);
    },

    accountRecommendations() {
      const recs = [];

      // 1. No holdings
      if (this.holdings.length === 0) {
        recs.push({
          priority: 1,
          title: 'Add Your Holdings',
          description: 'Add your fund holdings to get detailed fee analysis, diversification scores, and tax efficiency recommendations.',
          action: 'add-holding',
        });
      }

      // 10. No risk profile
      if (!this.hasRiskProfile) {
        recs.push({
          priority: 1,
          title: 'Set Risk Profile',
          description: 'Complete your risk profile to get personalised allocation targets and rebalancing recommendations for this account.',
          action: 'risk',
        });
      }

      // High account fees check (runs even without holdings)
      // Uses account's current_value if no holdings
      const accountValue = this.totalHoldingsValue > 0
        ? this.totalHoldingsValue
        : parseFloat(this.account.current_value) || 0;

      let platformFee = 0;
      if (this.account.platform_fee_type === 'fixed') {
        const feeAmount = parseFloat(this.account.platform_fee_amount) || 0;
        let annualAmount = feeAmount;
        if (this.account.platform_fee_frequency === 'monthly') annualAmount = feeAmount * 12;
        else if (this.account.platform_fee_frequency === 'quarterly') annualAmount = feeAmount * 4;
        platformFee = accountValue > 0 ? (annualAmount / accountValue) * 100 : 0;
      } else {
        platformFee = parseFloat(this.account.platform_fee_percent) || 0;
      }

      const weightedOCF = this.totalHoldingsValue > 0
        ? this.holdings.reduce((sum, h) => sum + ((h.current_value || 0) * (parseFloat(h.ocf_percent) || 0)), 0) / this.totalHoldingsValue
        : 0;

      const totalFees = platformFee + weightedOCF;
      if (totalFees > 0.8 && accountValue > 0) {
        const annualCost = (accountValue * totalFees) / 100;
        recs.push({
          priority: 2,
          title: 'Review Account Fees',
          description: `Your total fees are ${totalFees.toFixed(2)}%, costing ${this.formatCurrency(annualCost)}/year. Consider switching to a lower-cost platform.`,
          action: 'fees',
        });
      }

      // If no holdings, return early (remaining checks need holdings)
      if (this.holdings.length === 0) {
        return recs.sort((a, b) => a.priority - b.priority);
      }

      // 2. High concentration
      if (this.totalHoldingsValue > 0 && this.holdings.length > 1) {
        const topHolding = this.holdings.reduce((max, h) =>
          (h.current_value || 0) > (max.current_value || 0) ? h : max, this.holdings[0]);
        const topPercent = (topHolding.current_value / this.totalHoldingsValue) * 100;
        if (topPercent > 40) {
          recs.push({
            priority: 2,
            title: 'High Concentration',
            description: `${topHolding.fund_name || topHolding.holding_name || 'Top holding'} represents ${topPercent.toFixed(0)}% of this account. Consider spreading across more holdings.`,
            action: 'diversification',
          });
        }
      }

      // 3. Limited asset classes
      const assetClasses = new Set(this.holdings.map(h => h.asset_type).filter(Boolean));
      if (assetClasses.size <= 1) {
        const singleClass = [...assetClasses][0] || 'one type';
        recs.push({
          priority: 3,
          title: 'Limited Diversification',
          description: `All holdings in this account are ${this.formatAssetType(singleClass)}. Adding other asset classes can reduce volatility.`,
          action: 'diversification',
        });
      }

      // 5. High-fee individual holdings
      const highFeeHoldings = this.holdings.filter(h => (parseFloat(h.ocf_percent) || 0) > 0.8);
      if (highFeeHoldings.length > 0) {
        recs.push({
          priority: 3,
          title: 'High-Fee Holdings',
          description: `${highFeeHoldings.length} holding(s) have fees above 0.8%. Consider lower-cost index alternatives.`,
          action: 'fees',
        });
      }

      // 6. Needs rebalancing
      if (this.hasRiskProfile && this.rebalancingData?.drift_analysis?.needs_rebalancing) {
        const driftScore = this.rebalancingData.drift_analysis.drift_score;
        if (driftScore > 5) {
          recs.push({
            priority: 2,
            title: 'Rebalancing Needed',
            description: `Portfolio drift is ${driftScore.toFixed(1)}%. Consider rebalancing to match your target allocation.`,
            action: 'rebalancing',
          });
        }
      }

      // 7. ISA allowance available
      if (this.account.account_type === 'isa') {
        const remaining = ISA_ANNUAL_ALLOWANCE - (parseFloat(this.account.isa_subscription_current_year) || 0);
        if (remaining > 5000) {
          recs.push({
            priority: 3,
            title: 'ISA Allowance Available',
            description: `You have ${this.formatCurrency(remaining)} of ISA allowance remaining this tax year. Consider maximising your tax-free contributions before 5 April.`,
            action: 'info',
          });
        }
      }

      // 8. No contributions
      if ((parseFloat(this.account.contributions_ytd) || 0) === 0
          && (this.account.current_value || 0) < 100000
          && this.account.account_type !== 'nsi') {
        recs.push({
          priority: 4,
          title: 'No Contributions',
          description: 'No contributions recorded this tax year. Regular contributions benefit from pound-cost averaging.',
          action: 'info',
        });
      }

      // 9. Tax loss opportunity (GIA only)
      if (this.account.account_type === 'gia') {
        const lossHoldings = this.holdings.filter(h => {
          const costBasis = h.cost_basis || ((h.quantity || 0) * (h.purchase_price || 0)) || 0;
          const currentValue = h.current_value || 0;
          return costBasis > 0 && (costBasis - currentValue) > 500;
        });
        if (lossHoldings.length > 0) {
          recs.push({
            priority: 3,
            title: 'Tax Loss Opportunity',
            description: `${lossHoldings.length} holding(s) with unrealised losses. Selling and repurchasing after 30 days could offset capital gains tax.`,
            action: 'holdings',
          });
        }
      }

      // Sort by priority
      return recs.sort((a, b) => a.priority - b.priority);
    },
  },

  methods: {
    getPriorityLabel(priority) {
      switch (priority) {
        case 1: return 'High';
        case 2: return 'High';
        case 3: return 'Medium';
        case 4: return 'Low';
        default: return 'Low';
      }
    },

    getPriorityBadgeClass(priority) {
      const base = 'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium';
      switch (priority) {
        case 1: return `${base} bg-raspberry-500 text-white`;
        case 2: return `${base} bg-raspberry-500 text-white`;
        case 3: return `${base} bg-violet-500 text-white`;
        case 4: return `${base} bg-savannah-300 text-white`;
        default: return `${base} bg-savannah-300 text-white`;
      }
    },

    handleRecommendationAction(rec) {
      switch (rec.action) {
        case 'add-holding':
          this.$emit('add-holding');
          break;
        case 'diversification':
        case 'rebalancing':
        case 'fees':
        case 'holdings':
          this.$emit('change-tab', rec.action);
          break;
        case 'risk':
        case 'info':
        default:
          break;
      }
    },

    formatAssetType(type) {
      const types = {
        equity: 'Equity',
        equities: 'Equities',
        fixed_income: 'Fixed Income',
        bonds: 'Bonds',
        property: 'Property',
        cash: 'Cash',
        alternatives: 'Alternatives',
        other: 'Other',
      };
      return types[type] || type?.charAt(0).toUpperCase() + type?.slice(1).replace(/_/g, ' ') || 'Other';
    },
  },
};
</script>

<style scoped>
.strategy-section {
  background: white;
  @apply border border-light-gray;
  border-radius: 12px;
  padding: 20px;
  margin-top: 24px;
}

.strategy-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 16px;
}

.strategy-card {
  background: white;
  @apply border border-light-gray;
  border-radius: 12px;
  padding: 16px;
}

@media (max-width: 768px) {
  .strategy-grid {
    grid-template-columns: 1fr;
  }
}
</style>
