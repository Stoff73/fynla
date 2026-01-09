<template>
  <div
    class="card cursor-pointer hover:shadow-lg hover:-translate-y-0.5 hover:border-primary-500 transition-all duration-200"
    @click="navigateToInvestments"
  >
    <!-- Card Header -->
    <div class="flex items-start justify-between mb-4">
      <div>
        <h3 class="text-h3 text-gray-900">Investments</h3>
        <p class="text-sm text-gray-500 mt-1">Portfolio Overview</p>
      </div>
      <span class="text-gray-400">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
        </svg>
      </span>
    </div>

    <!-- Primary Value Section -->
    <div class="border-b border-gray-200 pb-4 mb-4">
      <span class="text-sm text-gray-500">Total Portfolio Value</span>
      <div class="flex items-baseline gap-3 mt-1">
        <span class="text-3xl font-bold text-primary-600">
          {{ formatCurrency(totalValue) }}
        </span>
        <span
          v-if="ytdReturn !== 0"
          class="text-sm font-semibold px-2 py-0.5 rounded"
          :class="ytdReturn >= 0 ? 'bg-green-600 text-white' : 'bg-red-600 text-white'"
        >
          {{ ytdReturn >= 0 ? '+' : '' }}{{ ytdReturn.toFixed(1) }}% YTD
        </span>
      </div>
    </div>

    <!-- Metrics Grid -->
    <div class="grid grid-cols-2 gap-4 mb-4">
      <div>
        <span class="text-sm text-gray-500">Risk Level</span>
        <div class="mt-1">
          <span
            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border-2"
            :class="riskBadgeClass"
          >
            {{ riskLevel }}
          </span>
        </div>
      </div>
      <div>
        <span class="text-sm text-gray-500">Diversification</span>
        <div class="mt-1">
          <span class="text-lg font-semibold text-gray-900">{{ diversificationScore }}/100</span>
        </div>
      </div>
    </div>

    <!-- Asset Allocation Mini Bar -->
    <div v-if="assetAllocation.length > 0" class="mb-4">
      <span class="text-sm text-gray-500 mb-2 block">Asset Allocation</span>
      <div class="h-3 rounded-full overflow-hidden flex bg-gray-200">
        <div
          v-for="(asset, index) in assetAllocation"
          :key="asset.name"
          class="h-full"
          :style="{ width: asset.percentage + '%', backgroundColor: allocationColors[index % allocationColors.length] }"
          :title="`${asset.name}: ${asset.percentage}%`"
        ></div>
      </div>
      <div class="flex flex-wrap gap-2 mt-2">
        <div
          v-for="(asset, index) in assetAllocation.slice(0, 4)"
          :key="asset.name"
          class="flex items-center gap-1 text-xs text-gray-600"
        >
          <span
            class="w-2 h-2 rounded-full"
            :style="{ backgroundColor: allocationColors[index % allocationColors.length] }"
          ></span>
          {{ asset.name }} {{ asset.percentage }}%
        </div>
      </div>
    </div>

    <!-- Account Count -->
    <div class="pt-3 border-t border-gray-200">
      <div class="flex justify-between items-center text-sm">
        <span class="text-gray-600">{{ accountsCount }} {{ accountsCount === 1 ? 'account' : 'accounts' }}</span>
        <span class="text-primary-600 font-medium">View details &rarr;</span>
      </div>
    </div>
  </div>
</template>

<script>
import { mapState, mapGetters } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'InvestmentsOverviewCard',
  mixins: [currencyMixin],

  data() {
    return {
      allocationColors: ['#1257A0', '#15803D', '#D97706', '#7C3AED', '#B91C1C', '#475569'],
    };
  },

  computed: {
    ...mapState('investment', ['accounts', 'analysis', 'riskProfile']),
    ...mapGetters('investment', ['totalPortfolioValue', 'ytdReturn', 'assetAllocation', 'accountsCount']),

    totalValue() {
      return this.totalPortfolioValue || 0;
    },

    riskLevel() {
      if (this.riskProfile?.risk_category) {
        return this.formatRiskLevel(this.riskProfile.risk_category);
      }
      if (this.analysis?.risk_metrics?.overall_risk) {
        return this.formatRiskLevel(this.analysis.risk_metrics.overall_risk);
      }
      return 'Not Set';
    },

    riskBadgeClass() {
      const level = this.riskLevel.toLowerCase();
      if (level.includes('low') || level.includes('cautious')) {
        return 'bg-white text-green-700 border-green-500';
      }
      if (level.includes('medium') || level.includes('balanced')) {
        return 'bg-white text-amber-700 border-amber-500';
      }
      if (level.includes('high') || level.includes('aggressive')) {
        return 'bg-white text-red-700 border-red-500';
      }
      return 'bg-white text-gray-700 border-gray-400';
    },

    diversificationScore() {
      return this.analysis?.diversification_score || 0;
    },
  },

  methods: {
    navigateToInvestments() {
      this.$router.push('/net-worth/investments');
    },

    formatRiskLevel(level) {
      if (!level) return 'Not Set';
      return level.charAt(0).toUpperCase() + level.slice(1).replace(/_/g, ' ');
    },
  },
};
</script>
