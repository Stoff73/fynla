<template>
  <div class="card">
    <!-- Portfolio Section (clickable) -->
    <div
      class="cursor-pointer hover:bg-savannah-100 -m-6 p-6 pb-4 rounded-t-lg transition-colors"
      @click="navigateToInvestments"
    >
      <!-- Primary Value Section with YTD Net -->
      <div class="border-b border-light-gray pb-4 mb-4">
        <span class="text-sm text-neutral-500">Total Portfolio Value</span>
        <div class="flex items-baseline gap-3 mt-1">
          <span class="text-3xl font-bold text-raspberry-500">
            {{ formatCurrency(totalValue) }}
          </span>
        </div>
        <div class="flex items-center gap-2 mt-2">
          <span class="text-sm text-neutral-500">Annualised Return:</span>
          <span
            v-if="portfolioAnnualisedReturn !== null"
            class="text-sm font-semibold"
            :class="portfolioAnnualisedReturn >= 0 ? 'text-spring-600' : 'text-raspberry-600'"
          >
            {{ portfolioAnnualisedReturn >= 0 ? '+' : '' }}{{ portfolioAnnualisedReturn.toFixed(2) }}%
          </span>
          <span v-else class="text-sm text-horizon-400">N/A</span>
        </div>
      </div>

      <!-- Account List -->
      <div class="space-y-3">
        <div
          v-for="account in accountsList"
          :key="account.id"
          class="flex justify-between items-center"
        >
          <div class="flex items-center gap-2">
            <span class="text-sm font-medium text-horizon-500">{{ account.displayName }}</span>
            <span
              v-if="account.isJoint"
              class="text-xs text-violet-600 font-medium"
            >
              (Joint)
            </span>
          </div>
          <div class="text-sm font-semibold text-horizon-500">{{ formatCurrency(account.current_value) }}</div>
        </div>
      </div>
    </div>

    <!-- Divider -->
    <div class="border-t border-light-gray my-4"></div>

    <!-- Cash Flow Section (clickable) -->
    <div
      class="cursor-pointer hover:bg-savannah-100 -mx-6 -mb-6 p-6 pt-0 rounded-b-lg transition-colors"
      @click="navigateToCash"
    >
      <div class="border-b border-light-gray pb-4 mb-4">
        <span class="text-sm text-neutral-500">{{ currentMonth }} Cash Flow</span>
        <div class="flex items-baseline gap-2 mt-1">
          <span
            class="text-3xl font-bold"
            :class="monthlySurplus >= 0 ? 'text-spring-600' : 'text-raspberry-600'"
          >
            {{ formatCurrency(Math.abs(monthlySurplus)) }}
          </span>
          <span
            class="text-sm font-medium"
            :class="monthlySurplus >= 0 ? 'text-spring-600' : 'text-raspberry-600'"
          >
            {{ monthlySurplus >= 0 ? 'surplus' : 'deficit' }}
          </span>
        </div>
      </div>

      <!-- Breakdown -->
      <div class="space-y-3">
        <div class="flex justify-between items-center">
          <span class="text-sm text-neutral-500">Money In</span>
          <span class="text-sm font-semibold text-spring-600">{{ formatCurrency(monthlyIncome) }}</span>
        </div>
        <div class="flex justify-between items-center">
          <span class="text-sm text-neutral-500">Money Out</span>
          <span class="text-sm font-semibold text-raspberry-600">{{ formatCurrency(monthlyExpenditure) }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { mapState, mapGetters, mapActions } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';
import userProfileService from '@/services/userProfileService';

import logger from '@/utils/logger';
export default {
  name: 'InvestmentsOverviewCard',
  mixins: [currencyMixin],

  data() {
    return {
      financialCommitmentsData: null,
    };
  },

  computed: {
    ...mapState('investment', ['accounts', 'analysis', 'riskProfile']),
    ...mapGetters('investment', ['totalPortfolioValue', 'ytdReturn', 'assetAllocation', 'accountsCount']),
    ...mapState('userProfile', ['incomeOccupation']),
    ...mapState('savings', ['expenditureProfile']),
    ...mapGetters('userProfile', ['totalAnnualIncome']),

    totalValue() {
      return this.totalPortfolioValue || 0;
    },

    // Calculate weighted average portfolio annualised return percentage
    portfolioAnnualisedReturn() {
      if (!this.accounts || this.accounts.length === 0) return null;

      let totalValue = 0;
      let weightedReturn = 0;

      this.accounts.forEach(account => {
        const annualisedReturn = account.annualised_return;
        const currentValue = parseFloat(account.current_value || 0);

        if (annualisedReturn !== null && annualisedReturn !== undefined && currentValue > 0) {
          totalValue += currentValue;
          weightedReturn += currentValue * annualisedReturn;
        }
      });

      if (totalValue === 0) return null;

      return weightedReturn / totalValue;
    },

    // List of accounts with provider/type and joint status
    accountsList() {
      if (!this.accounts || this.accounts.length === 0) return [];

      return this.accounts.map(account => {
        const currentValue = parseFloat(account.current_value || 0);

        // Display name: provider if available, otherwise formatted account type
        let displayName = account.provider;
        if (!displayName) {
          displayName = this.formatAccountType(account.account_type);
        }

        // Check if joint ownership
        const isJoint = account.ownership_type === 'joint' ||
                        account.ownership_type === 'tenants_in_common' ||
                        !!account.joint_owner_id;

        return {
          id: account.id,
          displayName: displayName,
          current_value: currentValue,
          isJoint: isJoint,
        };
      });
    },

    // Cash Flow computed properties
    currentMonth() {
      return new Date().toLocaleString('en-GB', { month: 'long' });
    },

    monthlyIncome() {
      const annual = this.totalAnnualIncome || 0;
      return annual / 12;
    },

    financialCommitmentsTotal() {
      return this.financialCommitmentsData?.totals?.total || 0;
    },

    monthlyExpenditure() {
      const discretionary = this.expenditureProfile?.total_monthly_expenditure || 0;
      return discretionary + this.financialCommitmentsTotal;
    },

    monthlySurplus() {
      return this.monthlyIncome - this.monthlyExpenditure;
    },
  },

  async mounted() {
    await this.loadFinancialCommitments();
  },

  methods: {
    ...mapActions('userProfile', ['fetchProfile']),

    async loadFinancialCommitments() {
      try {
        const response = await userProfileService.getFinancialCommitments();
        if (response.success) {
          this.financialCommitmentsData = response.data;
        }
      } catch (error) {
        logger.error('Failed to load financial commitments:', error);
        this.financialCommitmentsData = null;
      }
    },

    navigateToInvestments() {
      this.$router.push('/net-worth/investments');
    },

    navigateToCash() {
      this.$router.push('/net-worth/cash');
    },

    formatAccountType(accountType) {
      const typeMap = {
        stocks_and_shares_isa: 'ISA',
        cash_isa: 'Cash ISA',
        isa: 'ISA',
        sipp: 'Self-Invested Personal Pension',
        gia: 'General Investment Account',
        general_investment_account: 'General Investment Account',
        trading: 'Trading',
        pension: 'Pension',
      };
      const type = (accountType || '').toLowerCase();
      return typeMap[type] || accountType?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) || 'Account';
    },
  },
};
</script>
