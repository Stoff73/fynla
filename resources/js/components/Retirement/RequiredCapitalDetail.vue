<template>
  <div class="required-capital-detail">
    <!-- Back Button -->
    <button
      @click="$emit('back')"
      class="detail-inline-back"
    >
      <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
      </svg>
      Back to Projections
    </button>

    <!-- Loading State -->
    <div v-if="loading" class="loading-state">
      <div class="w-12 h-12 border-[3px] border-light-gray border-t-raspberry-500 rounded-full animate-spin mb-4"></div>
      <p>Calculating required capital...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="error-state">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="error-icon">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
      </svg>
      <p>{{ error }}</p>
      <button @click="fetchData" class="retry-button">Retry</button>
    </div>

    <!-- Content -->
    <template v-else-if="data">
      <!-- Summary Cards -->
      <div class="summary-grid">
        <div class="summary-card blue">
          <p class="summary-label">Target Retirement Income</p>
          <p class="summary-value">{{ formatCurrency(data.required_income) }}<span class="per-year">/year</span></p>
          <p class="summary-subtitle">
            {{ data.income_source === 'profile' ? 'From retirement profile' : '75% of gross income (less pension contributions)' }}
          </p>
        </div>
        <div class="summary-card purple">
          <p class="summary-label">Required Capital at Retirement</p>
          <p class="summary-value">{{ formatCurrency(data.required_capital_at_retirement) }}</p>
          <p class="summary-subtitle">Nominal value at age {{ data.retirement_info.retirement_age }}</p>
        </div>
        <div class="summary-card teal">
          <p class="summary-label">Projected Pension Pot</p>
          <p class="summary-value">{{ formatCurrency(projectedPotAtRetirement) }}</p>
          <p class="summary-subtitle">At age {{ data.retirement_info.retirement_age }} (80% confidence)</p>
        </div>
        <div class="summary-card indigo">
          <p class="summary-label">Other Assets at Retirement</p>
          <p class="summary-value">{{ formatCurrency(totalProjectedOtherAssets) }}</p>
          <p class="summary-subtitle">Projected at age {{ data.retirement_info.retirement_age }}</p>
        </div>
        <div class="summary-card" :class="gapToTarget >= 0 ? 'red' : 'green'">
          <p class="summary-label">{{ gapToTarget >= 0 ? 'Gap to Target' : 'Surplus' }}</p>
          <p class="summary-value">{{ formatCurrency(Math.abs(gapToTarget)) }}</p>
          <p class="summary-subtitle">{{ gapToTarget >= 0 ? 'Shortfall at retirement' : 'Exceeds target at retirement' }}</p>
        </div>
      </div>

      <!-- Assets Included -->
      <div class="assets-section">
        <h3 class="section-label">Assets included in calculation <span class="total-value">{{ formatCurrency(projectedPotAtRetirement + totalProjectedOtherAssets) }}</span></h3>
        <p class="section-note">Values projected to age {{ data.retirement_info.retirement_age }} (80% confidence)</p>
        <div class="asset-cards">
          <div v-for="pension in dcPensions" :key="pension.id" class="asset-card pension">
            <span class="asset-name">{{ pension.scheme_name || pension.provider || 'Defined Contribution Pension' }}</span>
            <span class="asset-value">{{ formatCurrency(projectedPotAtRetirement) }}</span>
            <span class="asset-type">Defined Contribution Pension - Projected (80% confidence)</span>
          </div>
          <div v-for="account in includedInvestments" :key="'inv-' + account.id" class="asset-card investment">
            <span class="asset-name">{{ account.account_name || account.provider || formatAccountType(account.account_type) }}</span>
            <span class="asset-value">{{ formatCurrency(getProjectedValue(account)) }}</span>
            <span class="asset-type">{{ formatAccountType(account.account_type) }} - Projected (80% confidence)</span>
            <label class="toggle-row">
              <span class="toggle-label">Exclude</span>
              <input
                type="checkbox"
                class="toggle-switch"
                checked
                @change="toggleAsset('investment', account.id)"
              />
            </label>
          </div>
          <div v-for="account in includedCash" :key="'cash-' + account.id" class="asset-card cash">
            <span class="asset-name">{{ account.account_name || account.name }}</span>
            <span class="asset-value">{{ formatCurrency(getProjectedCashValue(account)) }}</span>
            <span class="asset-type">Cash - Projected</span>
            <label class="toggle-row">
              <span class="toggle-label">Exclude</span>
              <input
                type="checkbox"
                class="toggle-switch"
                checked
                @change="toggleAsset('cash', account.id)"
              />
            </label>
          </div>
          <div v-if="(!dcPensions || dcPensions.length === 0) && includedInvestments.length === 0 && includedCash.length === 0" class="no-items">No assets included</div>
        </div>
      </div>

      <!-- Other Assets -->
      <div v-if="excludedInvestments.length > 0 || excludedCash.length > 0" class="assets-section">
        <h3 class="section-label">Other assets (not included)</h3>
        <div class="asset-cards">
          <div v-for="account in excludedInvestments" :key="'inv-other-' + account.id" class="asset-card investment">
            <span class="asset-name">{{ account.account_name || account.provider || formatAccountType(account.account_type) }}</span>
            <span class="asset-value">{{ formatCurrency(getProjectedValue(account)) }}</span>
            <span class="asset-type">{{ formatAccountType(account.account_type) }} - Projected (80% confidence)</span>
            <label class="toggle-row">
              <span class="toggle-label">Include</span>
              <input
                type="checkbox"
                class="toggle-switch"
                @change="toggleAsset('investment', account.id)"
              />
            </label>
          </div>
          <div v-for="account in excludedCash" :key="'cash-other-' + account.id" class="asset-card cash">
            <span class="asset-name">{{ account.account_name || account.name }}</span>
            <span class="asset-value">{{ formatCurrency(getProjectedCashValue(account)) }}</span>
            <span class="asset-type">Cash - Projected</span>
            <label class="toggle-row">
              <span class="toggle-label">Include</span>
              <input
                type="checkbox"
                class="toggle-switch"
                @change="toggleAsset('cash', account.id)"
              />
            </label>
          </div>
        </div>
      </div>

      <!-- Progress and Assumptions Row -->
      <div class="progress-assumptions-row">
        <!-- Progress Towards Target -->
        <div class="progress-section">
          <h3 class="progress-title">Progress Towards Target</h3>

          <!-- Current Progress -->
          <div class="progress-row">
            <div class="progress-label-row">
              <span class="progress-type">Current</span>
              <span class="progress-percentage" :class="progressColorClass">{{ progressPercentage }}%</span>
            </div>
            <div class="progress-bar-container">
              <div class="progress-bar" :style="{ width: Math.min(progressPercentage, 100) + '%' }" :class="progressColorClass"></div>
            </div>
            <div class="progress-values">
              <span>{{ formatCurrency(totalIncludedAssets) }}</span>
              <span class="target-label">of {{ formatCurrency(data.required_capital_today) }}</span>
            </div>
          </div>

          <!-- Forecasted Progress -->
          <div class="progress-row">
            <div class="progress-label-row">
              <span class="progress-type">Forecasted at Retirement</span>
              <span class="progress-percentage" :class="forecastedProgressColorClass">{{ forecastedProgressPercentage }}%</span>
            </div>
            <div class="progress-bar-container">
              <div class="progress-bar" :style="{ width: Math.min(forecastedProgressPercentage, 100) + '%' }" :class="forecastedProgressColorClass"></div>
            </div>
            <div class="progress-values">
              <span>{{ formatCurrency(projectedPotAtRetirement + totalProjectedOtherAssets) }}</span>
              <span class="target-label">of {{ formatCurrency(data.required_capital_at_retirement) }}</span>
            </div>
          </div>
        </div>

        <!-- Assumptions Panel -->
        <div class="assumptions-panel">
          <div class="assumptions-header">
            <h3>Calculation Assumptions</h3>
            <router-link to="/settings?tab=assumptions" class="edit-link">
              Edit Assumptions
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="link-icon">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
              </svg>
            </router-link>
          </div>
          <div class="assumptions-list">
            <span class="assumption-item"><span class="label">Return:</span> {{ data.assumptions.return_rate }}% <span class="note">(Net: {{ data.assumptions.net_return_rate }}%)</span></span>
            <span class="assumption-item"><span class="label">Fees:</span> {{ displayFees }}%<span v-if="isDefaultFees" class="note"> (default estimate)</span></span>
            <span class="assumption-item"><span class="label">Inflation:</span> {{ data.assumptions.inflation_rate }}%</span>
            <span class="assumption-item"><span class="label">Compounding:</span> {{ compoundingLabel }}</span>
            <span class="assumption-item"><span class="label">Withdrawal:</span> {{ data.assumptions.withdrawal_rate }}%</span>
            <span class="assumption-item"><span class="label">Contributions:</span> {{ formatCurrency(data.assumptions.monthly_contributions) }}/month</span>
            <span class="assumption-item"><span class="label">Years to Retirement:</span> {{ data.retirement_info.years_to_retirement }}</span>
          </div>
        </div>
      </div>

      <!-- Year-by-Year Table -->
      <div class="table-section">
        <h3 class="section-title">Year-by-Year Projection</h3>
        <div class="table-container">
          <table class="projection-table">
            <thead>
              <tr>
                <th>Year</th>
                <th>Age</th>
                <th class="text-right">Projected Pot Value</th>
                <th class="text-right">Pot in Today's Money</th>
                <th class="text-right">Target in Today's Money</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in data.year_by_year"
                :key="row.year_number"
                :class="{ 'retirement-row': row.is_retirement_year }"
              >
                <td>{{ row.calendar_year }}</td>
                <td>{{ row.age }}</td>
                <td class="text-right">{{ formatCurrency(row.accumulated_value) }}</td>
                <td class="text-right">{{ formatCurrency(row.present_value_today) }}</td>
                <td class="text-right">{{ formatCurrency(row.target_in_today_money) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Formula Explanation -->
      <div class="formula-section">
        <h3 class="section-title">How This Is Calculated</h3>
        <div class="formula-grid">
          <div class="formula-item">
            <div class="formula-icon blue">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
              </svg>
            </div>
            <div class="formula-content">
              <h4>Required Capital</h4>
              <p class="formula-text">Target Income / {{ data.assumptions.withdrawal_rate }}% withdrawal rate</p>
              <p class="formula-example">{{ formatCurrency(data.required_income) }} / 0.047 = {{ formatCurrency(data.required_capital_at_retirement) }}</p>
            </div>
          </div>
          <div class="formula-item">
            <div class="formula-icon purple">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941" />
              </svg>
            </div>
            <div class="formula-content">
              <h4>Accumulated Value (Future Value)</h4>
              <p class="formula-text">FV = PV x (1 + r/m)^(m x n) + PMT x [((1 + r/m)^(m x n) - 1) / (r/m)]</p>
              <p class="formula-example">Compounds {{ compoundingLabel.toLowerCase() }} at {{ data.assumptions.net_return_rate }}% with {{ formatCurrency(data.assumptions.monthly_contributions) }}/month contributions</p>
            </div>
          </div>
          <div class="formula-item">
            <div class="formula-icon green">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6L9 12.75l4.286-4.286a11.948 11.948 0 014.306 6.43l.776 2.898m0 0l3.182-5.511m-3.182 5.51l-5.511-3.181" />
              </svg>
            </div>
            <div class="formula-content">
              <h4>Today's Money (Present Value)</h4>
              <p class="formula-text">PV = FV / (1 + inflation)^n</p>
              <p class="formula-example">Discounts by {{ data.assumptions.inflation_rate }}% inflation per year</p>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script>
import { mapState, mapGetters, mapActions } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';

import logger from '@/utils/logger';
export default {
  name: 'RequiredCapitalDetail',

  mixins: [currencyMixin],

  emits: ['back'],

  data() {
    return {
      error: null,
    };
  },

  computed: {
    ...mapState('retirement', [
      'projections',
      'dcPensions',
      'requiredCapital',
      'requiredCapitalLoading',
      'includedInvestmentIds',
      'includedCashIds',
      'retirementIncome',
    ]),
    ...mapGetters('retirement', ['requiredCapitalData', 'retirementIncomeAvailableAccounts']),
    ...mapState('investment', ['accounts']),
    ...mapState('savings', { savingsAccounts: 'accounts' }),

    loading() {
      return this.requiredCapitalLoading;
    },

    data() {
      return this.requiredCapital;
    },

    investmentAccounts() {
      // Exclude illiquid investments and employee share schemes from retirement capital
      const excludedTypes = [
        'vct',
        'eis',
        'private_company',
        'crowdfunding',
        'saye',
        'csop',
        'emi',
        'unapproved_options',
        'rsu',
        'other',
      ];
      return (this.accounts || []).filter(
        a => this.getDisplayValue(a) > 0 && !excludedTypes.includes(a.account_type)
      );
    },

    cashAccounts() {
      return (this.savingsAccounts || []).filter(a => parseFloat(a.current_balance) > 0);
    },

    includedInvestments() {
      return this.investmentAccounts.filter(a => this.storeIncludedInvestmentIds.includes(a.id));
    },

    excludedInvestments() {
      return this.investmentAccounts.filter(a => !this.storeIncludedInvestmentIds.includes(a.id));
    },

    includedCash() {
      return this.cashAccounts.filter(a => this.storeIncludedCashIds.includes(a.id));
    },

    excludedCash() {
      return this.cashAccounts.filter(a => !this.storeIncludedCashIds.includes(a.id));
    },

    storeIncludedInvestmentIds() {
      return this.includedInvestmentIds || [];
    },

    storeIncludedCashIds() {
      return this.includedCashIds || [];
    },

    totalPensions() {
      return (this.dcPensions || []).reduce((sum, p) => sum + (parseFloat(p.current_fund_value) || 0), 0);
    },

    totalIncludedInvestments() {
      return this.includedInvestments.reduce((sum, a) => sum + this.getDisplayValue(a), 0);
    },

    totalIncludedCash() {
      return this.includedCash.reduce((sum, a) => sum + (parseFloat(a.current_balance) || 0), 0);
    },

    totalIncludedAssets() {
      return this.totalPensions + this.totalIncludedInvestments + this.totalIncludedCash;
    },

    compoundingLabel() {
      const periods = this.data?.assumptions?.compound_periods || 4;
      switch (periods) {
        case 1:
          return 'Annually';
        case 2:
          return 'Semi-annually';
        case 4:
          return 'Quarterly';
        case 12:
          return 'Monthly';
        case 365:
          return 'Daily';
        default:
          return `${periods}x/year`;
      }
    },

    progressPercentage() {
      if (!this.data?.required_capital_today) {
        return 0;
      }
      return Math.round((this.totalIncludedAssets / this.data.required_capital_today) * 100);
    },

    progressColorClass() {
      const pct = this.progressPercentage;
      if (pct >= 80) return 'green';
      if (pct >= 50) return 'blue';
      return 'red';
    },

    projectedPotAtRetirement() {
      // Use the Monte Carlo 80% confidence projection from the store
      return this.projections?.pension_pot_projection?.percentile_20_at_retirement || 0;
    },

    isDefaultFees() {
      const fees = this.data?.assumptions?.fees_total;
      return !fees || fees === 0;
    },

    displayFees() {
      const fees = this.data?.assumptions?.fees_total;
      return (!fees || fees === 0) ? 1 : fees;
    },

    totalOtherAssets() {
      return this.totalIncludedInvestments + this.totalIncludedCash;
    },

    /**
     * Total projected investments at retirement (Monte Carlo 80%)
     */
    totalProjectedInvestments() {
      return this.includedInvestments.reduce((sum, a) => {
        return sum + this.getProjectedValue(a);
      }, 0);
    },

    /**
     * Total projected cash at retirement
     */
    totalProjectedCash() {
      return this.includedCash.reduce((sum, a) => {
        return sum + this.getProjectedCashValue(a);
      }, 0);
    },

    /**
     * Total other assets projected to retirement
     */
    totalProjectedOtherAssets() {
      return this.totalProjectedInvestments + this.totalProjectedCash;
    },

    gapToTarget() {
      // Gap = Required Capital at Retirement - (Projected Pot + Projected Other Assets)
      const projectedTotal = this.projectedPotAtRetirement + this.totalProjectedOtherAssets;
      return this.data?.required_capital_at_retirement - projectedTotal;
    },

    forecastedProgressPercentage() {
      if (!this.data?.required_capital_at_retirement) {
        return 0;
      }
      const projectedTotal = this.projectedPotAtRetirement + this.totalProjectedOtherAssets;
      return Math.round((projectedTotal / this.data.required_capital_at_retirement) * 100);
    },

    forecastedProgressColorClass() {
      const pct = this.forecastedProgressPercentage;
      if (pct >= 80) return 'green';
      if (pct >= 50) return 'blue';
      return 'red';
    },
  },

  mounted() {
    this.loadData();
  },

  methods: {
    ...mapActions('retirement', [
      'fetchRetirementData',
      'fetchRequiredCapital',
      'fetchRetirementIncome',
      'toggleIncludedInvestment',
      'toggleIncludedCash',
      'setIncludedInvestmentIds',
    ]),
    ...mapActions('investment', { fetchInvestmentAccounts: 'fetchAccounts' }),
    ...mapActions('savings', { fetchSavingsAccounts: 'fetchAccounts' }),

    async loadData() {
      this.error = null;

      try {
        // Fetch all data in parallel
        // dcPensions come from fetchRetirementData if not already loaded
        const promises = [
          this.fetchRequiredCapital(),
          this.fetchRetirementIncome(), // Fetch retirement income for Monte Carlo projections
          this.fetchInvestmentAccounts(),
          this.fetchSavingsAccounts(),
        ];
        // Only fetch retirement data if dcPensions not yet loaded
        if (!this.dcPensions || this.dcPensions.length === 0) {
          promises.push(this.fetchRetirementData());
        }
        await Promise.all(promises);

        // Initialize includedInvestmentIds from accounts with include_in_retirement = true
        const includedIds = (this.accounts || [])
          .filter(a => a.include_in_retirement)
          .map(a => a.id);
        this.setIncludedInvestmentIds(includedIds);
      } catch (err) {
        logger.error('Error fetching required capital:', err);
        this.error = err.response?.data?.message || 'Failed to load required capital data';
      }
    },

    async fetchData() {
      // Alias for retry button
      await this.loadData();
    },

    toggleAsset(type, id) {
      if (type === 'investment') {
        this.toggleIncludedInvestment(id);
      } else if (type === 'cash') {
        this.toggleIncludedCash(id);
      }
    },

    formatAccountType(type) {
      if (!type) return 'Investment';
      const typeMap = {
        isa: 'ISA',
        sipp: 'Self-Invested Personal Pension',
        gia: 'General Investment Account',
        lisa: 'Lifetime ISA',
        jisa: 'Junior ISA',
        private_company: 'Private Company',
        crowdfunding: 'Crowdfunding',
        saye: 'Save As You Earn',
        csop: 'Company Share Option Plan',
        emi: 'Enterprise Management Incentive',
        rsu: 'Restricted Stock Units',
        unapproved_options: 'Unapproved Options',
      };
      return typeMap[type] || type.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
    },

    /**
     * Get the appropriate value label for the account type.
     * Matches logic from InvestmentList.vue
     */
    getValueLabel(account) {
      const employeeShareSchemes = ['saye', 'csop', 'emi', 'unapproved_options', 'rsu'];
      const privateTypes = ['private_company', 'crowdfunding'];

      if (employeeShareSchemes.includes(account.account_type)) {
        return account.account_type === 'rsu' ? 'Grant Value' : 'Exercise Value';
      }
      if (privateTypes.includes(account.account_type)) {
        return 'Valuation';
      }
      return 'Current Value';
    },

    /**
     * Get projected value for an investment account from retirement income Monte Carlo projections.
     * Falls back to current value if no projection available.
     */
    getProjectedValue(account) {
      // Look up the Monte Carlo 80% projected value from retirement income data
      const availableAccounts = this.retirementIncomeAvailableAccounts || [];

      // Match by account ID - retirement income API uses source_id for investments
      const projected = availableAccounts.find(a =>
        a.id === account.id ||
        a.source_id === account.id
      );

      if (projected && projected.value) {
        return parseFloat(projected.value);
      }

      // Fallback to current value if no projection found
      return this.getDisplayValue(account);
    },

    /**
     * Get projected value for a cash/savings account from retirement income data.
     * Falls back to current value if no projection available.
     */
    getProjectedCashValue(account) {
      // Look up from retirement income available accounts
      const availableAccounts = this.retirementIncomeAvailableAccounts || [];

      // Match by account ID
      const projected = availableAccounts.find(a =>
        a.id === account.id ||
        a.source_id === account.id
      );

      if (projected && projected.value) {
        return parseFloat(projected.value);
      }

      // Fallback to current value if no projection found
      return parseFloat(account.current_balance) || 0;
    },

    /**
     * Calculate the display value for an account based on its type.
     * Matches logic from InvestmentList.vue for consistency.
     */
    getDisplayValue(account) {
      const employeeShareSchemes = ['saye', 'csop', 'emi', 'unapproved_options', 'rsu'];
      const privateTypes = ['private_company', 'crowdfunding'];

      // Employee share schemes - calculate from units × price
      if (employeeShareSchemes.includes(account.account_type)) {
        const unitsGranted = parseFloat(account.units_granted) || 0;
        const exercisePrice = parseFloat(account.exercise_price) || 0;
        const marketValueAtGrant = parseFloat(account.market_value_at_grant) || 0;

        // RSUs: units × market value at grant
        if (account.account_type === 'rsu') {
          return unitsGranted * marketValueAtGrant;
        }

        // Options (SAYE, CSOP, EMI, Unapproved): units × exercise price
        return unitsGranted * exercisePrice;
      }

      // Private investments - use latest valuation or investment amount
      if (privateTypes.includes(account.account_type)) {
        if (account.latest_valuation && parseFloat(account.latest_valuation) > 0) {
          return parseFloat(account.latest_valuation);
        }
        if (account.investment_amount && parseFloat(account.investment_amount) > 0) {
          return parseFloat(account.investment_amount);
        }
        return 0;
      }

      // Standard accounts - use current_value
      return parseFloat(account.current_value) || 0;
    },
  },
};
</script>

<style scoped>
.required-capital-detail {
  animation: fadeInSlideUp 0.3s ease-out;
}

/* Loading State */
.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 80px 20px;
  text-align: center;
}

.loading-state p {
  @apply text-neutral-500;
  font-size: 16px;
  margin: 0;
}

/* Error State */
.error-state {
  text-align: center;
  padding: 60px 40px;
  background: white;
  border-radius: 12px;
  @apply border border-raspberry-200;
}

.error-icon {
  width: 48px;
  height: 48px;
  @apply text-raspberry-400;
  margin: 0 auto 16px;
}

.error-state p {
  @apply text-neutral-500;
  font-size: 16px;
  margin: 0 0 16px 0;
}

.retry-button {
  padding: 8px 20px;
  @apply bg-raspberry-500 text-white;
  border: none;
  border-radius: 6px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.15s;
}

.retry-button:hover {
  @apply bg-raspberry-500;
}

/* Summary Cards */
.summary-grid {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 16px;
  margin-bottom: 24px;
}

.summary-card {
  display: flex;
  flex-direction: column;
  background: white;
  border-radius: 12px;
  padding: 20px;
  @apply border border-light-gray;
}

.summary-card.blue {
  @apply bg-gradient-to-br from-blue-50 to-blue-100;
  @apply border-violet-200;
}

.summary-card.purple {
  @apply bg-gradient-to-br from-purple-50 to-purple-100;
  @apply border-purple-300;
}

.summary-card.green {
  @apply bg-gradient-to-br from-green-50 to-green-100;
  @apply border-spring-200;
}

.summary-card.teal {
  @apply bg-gradient-to-br from-teal-50 to-teal-100;
  @apply border-teal-200;
}

.summary-card.indigo {
  @apply bg-gradient-to-br from-indigo-50 to-indigo-100;
  @apply border-violet-200;
}

.summary-card.red {
  @apply bg-gradient-to-br from-red-50 to-red-100;
  @apply border-raspberry-200;
}

.summary-label {
  font-size: 14px;
  line-height: 1.3;
  @apply text-neutral-500;
  margin: 0 0 8px 0;
  font-weight: 500;
  height: 36px;
  display: flex;
  align-items: flex-end;
}

.summary-value {
  font-size: 28px;
  font-weight: 700;
  @apply text-horizon-500;
  margin: 0;
}

.per-year {
  font-size: 14px;
  font-weight: 500;
  @apply text-neutral-500;
}

.summary-subtitle {
  font-size: 13px;
  @apply text-neutral-500;
  margin-top: auto;
  padding-top: 8px;
}

/* Assets Sections */
.assets-section {
  margin-bottom: 20px;
}

.section-label {
  font-size: 14px;
  font-weight: 600;
  @apply text-neutral-500;
  margin: 0 0 4px 0;
}

.section-note {
  font-size: 12px;
  @apply text-horizon-400;
  margin: 0 0 12px 0;
}

.no-items {
  @apply text-horizon-400;
  font-style: italic;
  font-size: 14px;
}

.asset-cards {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.asset-card {
  display: flex;
  flex-direction: column;
  padding: 12px 16px;
  border-radius: 8px;
  min-width: 160px;
  @apply border;
}

.asset-card.pension {
  @apply bg-purple-50 border-purple-200;
}

.asset-card.investment {
  @apply bg-violet-50 border-violet-200;
}

.asset-card.cash {
  @apply bg-emerald-50 border-emerald-200;
}

.asset-name {
  font-size: 14px;
  font-weight: 500;
  @apply text-horizon-500;
  margin-bottom: 4px;
}

.asset-value {
  font-size: 18px;
  font-weight: 700;
  @apply text-horizon-500;
}

.asset-type {
  font-size: 12px;
  @apply text-neutral-500;
  margin-top: 4px;
}

.total-value {
  font-weight: 600;
  @apply text-horizon-500;
  margin-left: 8px;
}

.toggle-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 12px;
  padding-top: 12px;
  border-top: 1px solid;
  @apply border-light-gray;
  cursor: pointer;
}

.toggle-label {
  font-size: 13px;
  @apply text-neutral-500;
}

.toggle-switch {
  position: relative;
  width: 36px;
  height: 20px;
  appearance: none;
  @apply bg-horizon-300;
  border-radius: 10px;
  cursor: pointer;
  transition: background-color 0.2s ease;
}

.toggle-switch:checked {
  @apply bg-raspberry-500;
}

.toggle-switch::before {
  content: '';
  position: absolute;
  top: 2px;
  left: 2px;
  width: 16px;
  height: 16px;
  background: white;
  border-radius: 50%;
  transition: transform 0.2s ease;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
}

.toggle-switch:checked::before {
  transform: translateX(16px);
}

/* Progress and Assumptions Row */
.progress-assumptions-row {
  display: flex;
  gap: 20px;
  margin-bottom: 24px;
}

/* Progress Section */
.progress-section {
  background: white;
  border-radius: 12px;
  padding: 20px;
  flex: 0 0 400px;
  @apply border border-light-gray;
}

.progress-title {
  font-size: 16px;
  font-weight: 600;
  @apply text-horizon-500;
  margin: 0 0 16px 0;
}

.progress-row {
  margin-bottom: 16px;
}

.progress-row:last-child {
  margin-bottom: 0;
}

.progress-label-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 6px;
}

.progress-type {
  font-size: 13px;
  font-weight: 500;
  @apply text-neutral-500;
}

.progress-percentage {
  font-size: 16px;
  font-weight: 700;
}

.progress-values {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  @apply text-neutral-500;
  margin-top: 4px;
}

.target-label {
  @apply text-horizon-400;
}

.progress-percentage.green {
  @apply text-spring-600;
}

.progress-percentage.blue {
  @apply text-violet-600;
}

.progress-percentage.red {
  @apply text-raspberry-600;
}

.progress-bar-container {
  height: 12px;
  @apply bg-savannah-200;
  border-radius: 6px;
  overflow: hidden;
  margin-bottom: 8px;
}

.progress-bar {
  height: 100%;
  border-radius: 6px;
  transition: width 0.5s ease-out;
}

.progress-bar.green {
  @apply bg-spring-500;
}

.progress-bar.blue {
  @apply bg-violet-500;
}

.progress-bar.red {
  @apply bg-raspberry-500;
}

.progress-labels {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
  @apply text-neutral-500;
}

/* Assumptions Panel */
.assumptions-panel {
  background: white;
  border-radius: 12px;
  padding: 20px;
  flex: 1;
  @apply border border-light-gray;
}

.assumptions-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.assumptions-header h3 {
  font-size: 16px;
  font-weight: 600;
  @apply text-horizon-500;
  margin: 0;
}

.edit-link {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 14px;
  font-weight: 500;
  @apply text-raspberry-500;
  text-decoration: none;
  transition: all 0.15s;
}

.edit-link:hover {
  @apply text-raspberry-600;
}

.link-icon {
  width: 16px;
  height: 16px;
}

.assumptions-list {
  display: flex;
  flex-wrap: wrap;
  gap: 4px 20px;
  font-size: 14px;
  @apply text-neutral-500;
}

.assumption-item {
  white-space: nowrap;
}

.assumption-item .label {
  @apply text-neutral-500;
}

.assumption-item .note {
  @apply text-horizon-400;
}

/* Table Section */
.table-section {
  background: white;
  border-radius: 12px;
  padding: 20px;
  margin-bottom: 24px;
  @apply border border-light-gray;
}

.section-title {
  font-size: 16px;
  font-weight: 600;
  @apply text-horizon-500;
  margin: 0 0 16px 0;
}

.table-container {
  overflow-x: auto;
  max-height: 400px;
  overflow-y: auto;
}

.projection-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 14px;
}

.projection-table th {
  position: sticky;
  top: 0;
  @apply bg-savannah-100;
  @apply text-neutral-500;
  font-weight: 600;
  text-align: left;
  padding: 12px 16px;
  border-bottom: 2px solid;
  @apply border-light-gray;
}

.projection-table td {
  padding: 10px 16px;
  @apply text-neutral-500;
  border-bottom: 1px solid;
  @apply border-light-gray;
}

.projection-table .text-right {
  text-align: right;
}

.projection-table tr:hover {
  @apply bg-savannah-100;
}

.projection-table tr.retirement-row {
  @apply bg-teal-50;
  font-weight: 600;
}

.projection-table tr.retirement-row td {
  @apply text-teal-800;
  @apply border-teal-200;
}

/* Formula Section */
.formula-section {
  background: white;
  border-radius: 12px;
  padding: 20px;
  @apply border border-light-gray;
}

.formula-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 20px;
}

.formula-item {
  display: flex;
  gap: 12px;
}

.formula-icon {
  flex-shrink: 0;
  width: 40px;
  height: 40px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.formula-icon svg {
  width: 24px;
  height: 24px;
}

.formula-icon.blue {
  @apply bg-violet-100;
  @apply text-violet-600;
}

.formula-icon.purple {
  @apply bg-purple-100;
  @apply text-purple-600;
}

.formula-icon.green {
  @apply bg-spring-100;
  @apply text-spring-600;
}

.formula-content {
  flex: 1;
}

.formula-content h4 {
  font-size: 14px;
  font-weight: 600;
  @apply text-horizon-500;
  margin: 0 0 4px 0;
}

.formula-text {
  font-size: 13px;
  @apply text-neutral-500;
  margin: 0 0 4px 0;
  font-family: 'SF Mono', 'Monaco', 'Inconsolata', 'Fira Mono', monospace;
}

.formula-example {
  font-size: 12px;
  @apply text-horizon-400;
  margin: 0;
}

@media (max-width: 1280px) {
  .summary-grid {
    grid-template-columns: repeat(3, 1fr);
  }
}

@media (max-width: 1024px) {
  .summary-grid {
    grid-template-columns: repeat(2, 1fr);
  }

  .progress-assumptions-row {
    flex-direction: column;
  }

  .progress-section {
    flex: none;
  }

  .formula-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 768px) {
  .summary-grid {
    grid-template-columns: 1fr;
  }

  .assumptions-list {
    gap: 4px 12px;
    font-size: 13px;
  }
}
</style>
