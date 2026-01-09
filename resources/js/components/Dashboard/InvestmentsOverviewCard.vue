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

    <!-- Primary Value Section with YTD Net -->
    <div class="border-b border-gray-200 pb-4 mb-4">
      <span class="text-sm text-gray-500">Total Portfolio Value</span>
      <div class="flex items-baseline gap-3 mt-1">
        <span class="text-3xl font-bold text-primary-600">
          {{ formatCurrency(totalValue) }}
        </span>
      </div>
      <div class="flex items-center gap-2 mt-2">
        <span class="text-sm text-gray-500">YTD:</span>
        <span
          v-if="portfolioYtdReturn !== null"
          class="text-sm font-semibold"
          :class="portfolioYtdReturn >= 0 ? 'text-green-600' : 'text-red-600'"
        >
          {{ portfolioYtdReturn >= 0 ? '+' : '' }}{{ portfolioYtdReturn.toFixed(2) }}%
        </span>
        <span v-else class="text-sm text-gray-400">N/A</span>
      </div>
    </div>

    <!-- Account List -->
    <div class="space-y-3 mb-4">
      <div
        v-for="account in accountsList"
        :key="account.id"
        class="flex justify-between items-start"
      >
        <div>
          <span class="text-sm font-medium text-gray-900">{{ account.name }}</span>
          <span
            class="ml-2 text-xs px-1.5 py-0.5 rounded border"
            :class="getAccountTypeBadgeClass(account.account_type)"
          >
            {{ formatAccountType(account.account_type) }}
          </span>
        </div>
        <div class="text-right">
          <div class="text-sm font-semibold text-gray-900">{{ formatCurrency(account.current_value) }}</div>
          <div
            v-if="account.ytd_return !== null && account.ytd_return !== undefined"
            class="text-xs"
            :class="account.ytd_return >= 0 ? 'text-green-600' : 'text-red-600'"
          >
            {{ account.ytd_return >= 0 ? '+' : '' }}{{ account.ytd_return.toFixed(2) }}%
          </div>
        </div>
      </div>
    </div>

    <!-- Risk & Diversification (if available) -->
    <div v-if="riskLevel !== 'Not Set' || diversificationScore > 0" class="grid grid-cols-2 gap-4 pt-3 border-t border-gray-200">
      <div v-if="riskLevel !== 'Not Set'">
        <span class="text-xs text-gray-500">Risk Level</span>
        <div class="mt-0.5">
          <span
            class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border"
            :class="riskBadgeClass"
          >
            {{ riskLevel }}
          </span>
        </div>
      </div>
      <div v-if="diversificationScore > 0">
        <span class="text-xs text-gray-500">Diversification</span>
        <div class="mt-0.5">
          <span class="text-sm font-semibold text-gray-900">{{ diversificationScore }}/100</span>
        </div>
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

    // Calculate weighted average portfolio YTD return percentage
    portfolioYtdReturn() {
      if (!this.accounts || this.accounts.length === 0) return null;

      let totalValue = 0;
      let weightedReturn = 0;

      this.accounts.forEach(account => {
        const ytdReturn = account.ytd_return;
        const currentValue = parseFloat(account.current_value || 0);

        if (ytdReturn !== null && ytdReturn !== undefined && currentValue > 0) {
          totalValue += currentValue;
          weightedReturn += currentValue * ytdReturn;
        }
      });

      if (totalValue === 0) return null;

      return weightedReturn / totalValue;
    },

    // List of accounts with their YTD returns
    accountsList() {
      if (!this.accounts || this.accounts.length === 0) return [];

      return this.accounts.map(account => {
        const currentValue = parseFloat(account.current_value || 0);
        const ytdReturn = account.ytd_return;

        // Calculate YTD gain from the return percentage
        let ytdGain = null;
        if (ytdReturn !== null && ytdReturn !== undefined) {
          ytdGain = currentValue * (ytdReturn / (100 + ytdReturn));
        }

        return {
          id: account.id,
          name: account.account_name || account.name || 'Unnamed Account',
          account_type: account.account_type,
          current_value: currentValue,
          ytd_return: ytdReturn,
          ytd_gain: ytdGain,
        };
      });
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

    getAccountTypeBadgeClass(accountType) {
      const type = (accountType || '').toLowerCase();
      if (type.includes('isa') || type === 'stocks_and_shares_isa') {
        return 'bg-white text-blue-700 border-blue-500';
      }
      if (type === 'sipp' || type.includes('pension')) {
        return 'bg-white text-purple-700 border-purple-500';
      }
      if (type === 'gia' || type === 'general_investment_account' || type === 'trading') {
        return 'bg-white text-gray-700 border-gray-400';
      }
      return 'bg-white text-gray-700 border-gray-400';
    },

    formatAccountType(accountType) {
      const typeMap = {
        stocks_and_shares_isa: 'ISA',
        cash_isa: 'Cash ISA',
        isa: 'ISA',
        sipp: 'SIPP',
        gia: 'GIA',
        general_investment_account: 'GIA',
        trading: 'Trading',
        pension: 'Pension',
      };
      const type = (accountType || '').toLowerCase();
      return typeMap[type] || accountType?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) || 'Account';
    },
  },
};
</script>
