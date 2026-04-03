<template>
  <div class="card">
    <!-- Allowance List -->
    <div class="space-y-4">
      <!-- ISA Allowance -->
      <div class="allowance-item cursor-pointer hover:bg-savannah-100 rounded-lg p-2 -m-2 transition-colors" @click="navigateTo('/investment')">
        <div class="flex justify-between items-center mb-1">
          <span class="text-sm font-medium text-horizon-500">ISA</span>
          <span class="text-xs text-neutral-500">{{ formatCurrency(isaUsed) }} / {{ formatCurrency(isaLimit) }}</span>
        </div>
        <div class="h-2 rounded-full overflow-hidden bg-savannah-200">
          <div
            class="h-full rounded-full transition-all duration-300"
            :class="getProgressBarClass(isaPercent)"
            :style="{ width: Math.min(isaPercent, 100) + '%' }"
          ></div>
        </div>
        <div class="text-xs text-neutral-500 mt-1">{{ formatCurrency(isaRemaining) }} remaining</div>
      </div>

      <!-- Pension Annual Allowance -->
      <div class="allowance-item cursor-pointer hover:bg-savannah-100 rounded-lg p-2 -m-2 transition-colors" @click="navigateTo('/net-worth/retirement')">
        <div class="flex justify-between items-center mb-1">
          <span class="text-sm font-medium text-horizon-500">Pension ({{ currentTaxYear }})</span>
          <span class="text-xs text-neutral-500">{{ formatCurrency(pensionStandardUsed) }} / {{ formatCurrency(pensionLimit) }}</span>
        </div>
        <div class="h-2 rounded-full overflow-hidden bg-savannah-200">
          <div
            class="h-full rounded-full transition-all duration-300"
            :class="getProgressBarClass(pensionStandardPercent)"
            :style="{ width: Math.min(pensionStandardPercent, 100) + '%' }"
          ></div>
        </div>
        <div class="text-xs text-neutral-500 mt-1">{{ formatCurrency(pensionStandardRemaining) }} remaining</div>
      </div>

      <!-- Pension Carry Forward (only when exceeding standard allowance) -->
      <div v-if="pensionExceedsStandard && pensionCarryForwardUsed > 0" class="allowance-item cursor-pointer hover:bg-savannah-100 rounded-lg p-2 -m-2 transition-colors" @click="navigateTo('/net-worth/retirement')">
        <div class="flex justify-between items-center mb-1">
          <span class="text-sm font-medium text-horizon-500">Carry Forward ({{ carryForwardTaxYear }})</span>
          <span class="text-xs text-neutral-500">{{ formatCurrency(pensionCarryForwardUsed) }} / {{ formatCurrency(pensionLimit) }}</span>
        </div>
        <div class="h-2 rounded-full overflow-hidden bg-savannah-200">
          <div
            class="h-full rounded-full transition-all duration-300"
            :class="getProgressBarClass(carryForwardPercent)"
            :style="{ width: Math.min(carryForwardPercent, 100) + '%' }"
          ></div>
        </div>
        <div class="text-xs text-neutral-500 mt-1">{{ formatCurrency(carryForwardRemaining) }} remaining</div>
      </div>

      <!-- CGT Allowance (only if user has non-ISA investments) -->
      <div v-if="hasNonIsaInvestments" class="allowance-item cursor-pointer hover:bg-savannah-100 rounded-lg p-2 -m-2 transition-colors" @click="navigateTo('/investment')">
        <div class="flex justify-between items-center mb-1">
          <span class="text-sm font-medium text-horizon-500">Capital Gains Tax</span>
          <span class="text-xs text-neutral-500">{{ formatCurrency(cgtUsed) }} / {{ formatCurrency(cgtLimit) }}</span>
        </div>
        <div class="h-2 rounded-full overflow-hidden bg-savannah-200">
          <div
            class="h-full rounded-full transition-all duration-300"
            :class="getProgressBarClass(cgtPercent)"
            :style="{ width: Math.min(cgtPercent, 100) + '%' }"
          ></div>
        </div>
        <div class="text-xs text-neutral-500 mt-1">{{ formatCurrency(cgtRemaining) }} remaining</div>
      </div>

      <!-- Dividend Allowance (only if user receives dividend income) -->
      <div v-if="hasDividendIncome" class="allowance-item cursor-pointer hover:bg-savannah-100 rounded-lg p-2 -m-2 transition-colors" @click="navigateTo('/investment')">
        <div class="flex justify-between items-center mb-1">
          <span class="text-sm font-medium text-horizon-500">Dividend</span>
          <span class="text-xs text-neutral-500">{{ formatCurrency(dividendUsed) }} / {{ formatCurrency(dividendLimit) }}</span>
        </div>
        <div class="h-2 rounded-full overflow-hidden bg-savannah-200">
          <div
            class="h-full rounded-full transition-all duration-300"
            :class="getProgressBarClass(dividendPercent)"
            :style="{ width: Math.min(dividendPercent, 100) + '%' }"
          ></div>
        </div>
        <div class="text-xs text-neutral-500 mt-1">{{ formatCurrency(dividendRemaining) }} remaining</div>
      </div>
    </div>

    <!-- Expiring Warning -->
    <div
      v-if="hasExpiringAllowances"
      class="mt-4 p-3 bg-white border-2 border-violet-500 rounded-lg"
    >
      <div class="flex items-center gap-2">
        <svg class="w-5 h-5 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span class="text-sm font-medium text-violet-700">
          {{ expiringMessage }}
        </span>
      </div>
    </div>
  </div>
</template>

<script>
import { mapState, mapGetters } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';
import { ANNUAL_ALLOWANCE, ISA_ANNUAL_ALLOWANCE, CGT_ANNUAL_ALLOWANCE, DIVIDEND_ALLOWANCE } from '@/constants/taxConfig';
import { getCurrentTaxYear } from '@/utils/dateFormatter';

export default {
  name: 'TaxOptimisationCard',
  mixins: [currencyMixin],

  data() {
    return {
      // UK Tax Year allowances (from taxConfig.js fallback constants)
      isaLimit: ISA_ANNUAL_ALLOWANCE,
      pensionLimit: ANNUAL_ALLOWANCE,
      cgtLimit: CGT_ANNUAL_ALLOWANCE,
      dividendLimit: DIVIDEND_ALLOWANCE,
    };
  },

  computed: {
    ...mapState('savings', ['accounts']),
    ...mapState('investment', { investmentAccounts: 'accounts' }),
    ...mapState('retirement', ['annualAllowance', 'dcPensions']),
    ...mapGetters('investment', ['totalISAContributions']),
    ...mapGetters('userProfile', ['totalAnnualIncome', 'incomeOccupation']),

    // Check if user has dividend income
    hasDividendIncome() {
      const annualDividends = this.incomeOccupation?.annual_dividend_income || 0;
      return annualDividends > 0;
    },

    // Check if user has non-ISA investment accounts (GIA where CGT applies)
    hasNonIsaInvestments() {
      if (!this.investmentAccounts || this.investmentAccounts.length === 0) {
        return false;
      }
      // Check for any account that is NOT an ISA (GIA, SIPP pension wrapper gains are tax-free)
      return this.investmentAccounts.some(account => {
        const type = (account.account_type || '').toLowerCase();
        return type === 'gia' || type === 'general_investment_account' || type === 'trading';
      });
    },

    // ISA usage from savings and investment ISA accounts
    isaUsed() {
      // Get ISA contributions from investment accounts
      const investmentIsaContribs = this.totalISAContributions || 0;

      // Get ISA contributions from savings accounts
      const savingsIsaContribs = (this.accounts || [])
        .filter(a => a.account_type === 'cash_isa' || a.account_type === 'isa')
        .reduce((sum, a) => sum + parseFloat(a.contributions_ytd || 0), 0);

      return investmentIsaContribs + savingsIsaContribs;
    },

    isaRemaining() {
      return Math.max(0, this.isaLimit - this.isaUsed);
    },

    isaPercent() {
      return (this.isaUsed / this.isaLimit) * 100;
    },

    // Pension annual allowance usage
    pensionUsed() {
      // From annual allowance data if available
      if (this.annualAllowance?.total_contributions) {
        return this.annualAllowance.total_contributions;
      }
      // Fallback: sum DC pension contributions
      return (this.dcPensions || []).reduce((sum, p) => {
        if (p.scheme_type === 'personal' || p.scheme_type === 'sipp') {
          return sum + parseFloat(p.monthly_contribution_amount || 0) * 12;
        }
        // Workplace pensions: calculate from percentage of salary
        const employeePercent = parseFloat(p.employee_contribution_percent || 0);
        const employerPercent = parseFloat(p.employer_contribution_percent || 0);
        const salary = parseFloat(p.annual_salary || 0);
        return sum + (salary * (employeePercent + employerPercent)) / 100;
      }, 0);
    },

    pensionCarryForward() {
      return this.annualAllowance?.carry_forward_available || this.annualAllowance?.carry_forward || 0;
    },

    pensionExceedsStandard() {
      return this.pensionUsed > this.pensionLimit;
    },

    pensionStandardUsed() {
      return Math.min(this.pensionUsed, this.pensionLimit);
    },

    pensionStandardPercent() {
      return (this.pensionStandardUsed / this.pensionLimit) * 100;
    },

    pensionStandardRemaining() {
      return Math.max(0, this.pensionLimit - this.pensionStandardUsed);
    },

    pensionCarryForwardUsed() {
      return Math.min(this.pensionCarryForward, Math.max(0, this.pensionUsed - this.pensionLimit));
    },

    carryForwardPercent() {
      return (this.pensionCarryForwardUsed / this.pensionLimit) * 100;
    },

    carryForwardRemaining() {
      return this.pensionLimit - this.pensionCarryForwardUsed;
    },

    currentTaxYear() {
      return getCurrentTaxYear();
    },

    carryForwardTaxYear() {
      const now = new Date();
      const year = now.getFullYear();
      const month = now.getMonth();
      const day = now.getDate();
      const taxYearStart = (month > 3 || (month === 3 && day >= 6)) ? year : year - 1;
      const cfStart = taxYearStart - 3;
      return `${cfStart}/${String(cfStart + 1).slice(-2)}`;
    },

    // Keep original for backward compatibility with expiring message
    pensionRemaining() {
      return Math.max(0, this.pensionLimit - this.pensionUsed);
    },

    pensionPercent() {
      return (this.pensionUsed / this.pensionLimit) * 100;
    },

    // CGT usage (estimated from gains - would need actual disposal data)
    cgtUsed() {
      // This would come from actual capital gains data
      // For now, show 0 as we don't track disposals
      return 0;
    },

    cgtRemaining() {
      return Math.max(0, this.cgtLimit - this.cgtUsed);
    },

    cgtPercent() {
      return (this.cgtUsed / this.cgtLimit) * 100;
    },

    // Dividend usage from user profile
    dividendUsed() {
      const annualDividends = this.incomeOccupation?.annual_dividend_income || 0;
      return Math.min(annualDividends, this.dividendLimit);
    },

    dividendRemaining() {
      return Math.max(0, this.dividendLimit - this.dividendUsed);
    },

    dividendPercent() {
      return (this.dividendUsed / this.dividendLimit) * 100;
    },

    // Check if within 3 months of tax year end
    isNearTaxYearEnd() {
      const now = new Date();
      const taxYearEnd = new Date(now.getFullYear(), 3, 5); // April 5th
      if (now > taxYearEnd) {
        taxYearEnd.setFullYear(taxYearEnd.getFullYear() + 1);
      }
      const monthsUntilEnd = (taxYearEnd - now) / (1000 * 60 * 60 * 24 * 30);
      return monthsUntilEnd <= 3;
    },

    // Check if any use-it-or-lose-it allowances are expiring
    // Note: Pension allowance can carry forward, so NOT included
    hasExpiringAllowances() {
      if (!this.isNearTaxYearEnd) return false;

      // ISA allowance is always use-it-or-lose-it
      if (this.isaRemaining > 5000) return true;

      // CGT allowance expires (if user can use it)
      if (this.hasNonIsaInvestments && this.cgtRemaining > 1000) return true;

      // Dividend allowance expires (if user has dividend income)
      if (this.hasDividendIncome && this.dividendRemaining > 100) return true;

      return false;
    },

    expiringMessage() {
      const messages = [];

      // ISA (always use-it-or-lose-it)
      if (this.isaRemaining > 5000) {
        messages.push(`${this.formatCurrency(this.isaRemaining)} ISA`);
      }

      // CGT (if applicable)
      if (this.hasNonIsaInvestments && this.cgtRemaining > 1000) {
        messages.push(`${this.formatCurrency(this.cgtRemaining)} Capital Gains Tax`);
      }

      // Dividend (if applicable)
      if (this.hasDividendIncome && this.dividendRemaining > 100) {
        messages.push(`${this.formatCurrency(this.dividendRemaining)} Dividend`);
      }

      return `${messages.join(', ')} allowance expires 5 April`;
    },
  },

  methods: {
    navigateTo(route) {
      this.$router.push(route);
    },

    getProgressBarClass(percent) {
      if (percent >= 90) return 'bg-spring-600';
      if (percent >= 50) return 'bg-raspberry-500';
      if (percent >= 25) return 'bg-violet-500';
      return 'bg-horizon-400';
    },
  },
};
</script>
