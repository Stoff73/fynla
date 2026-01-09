<template>
  <div
    class="card cursor-pointer hover:shadow-lg hover:-translate-y-0.5 hover:border-primary-500 transition-all duration-200"
    @click="navigateToTaxInfo"
  >
    <!-- Card Header -->
    <div class="flex items-start justify-between mb-4">
      <div>
        <h3 class="text-h3 text-gray-900">Tax Allowances</h3>
        <p class="text-sm text-gray-500 mt-1">2025/26 Tax Year</p>
      </div>
      <span class="text-gray-400">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
        </svg>
      </span>
    </div>

    <!-- Allowance List -->
    <div class="space-y-4">
      <!-- ISA Allowance -->
      <div class="allowance-item">
        <div class="flex justify-between items-center mb-1">
          <span class="text-sm font-medium text-gray-700">ISA</span>
          <span class="text-xs text-gray-500">{{ formatCurrency(isaUsed) }} / {{ formatCurrency(isaLimit) }}</span>
        </div>
        <div class="h-2 rounded-full overflow-hidden bg-gray-200">
          <div
            class="h-full rounded-full transition-all duration-300"
            :class="getProgressBarClass(isaPercent)"
            :style="{ width: Math.min(isaPercent, 100) + '%' }"
          ></div>
        </div>
        <div class="text-xs text-gray-500 mt-1">{{ formatCurrency(isaRemaining) }} remaining</div>
      </div>

      <!-- Pension Annual Allowance -->
      <div class="allowance-item">
        <div class="flex justify-between items-center mb-1">
          <span class="text-sm font-medium text-gray-700">Pension</span>
          <span class="text-xs text-gray-500">{{ formatCurrency(pensionUsed) }} / {{ formatCurrency(pensionLimit) }}</span>
        </div>
        <div class="h-2 rounded-full overflow-hidden bg-gray-200">
          <div
            class="h-full rounded-full transition-all duration-300"
            :class="getProgressBarClass(pensionPercent)"
            :style="{ width: Math.min(pensionPercent, 100) + '%' }"
          ></div>
        </div>
        <div class="text-xs text-gray-500 mt-1">{{ formatCurrency(pensionRemaining) }} remaining</div>
      </div>

      <!-- CGT Allowance -->
      <div class="allowance-item">
        <div class="flex justify-between items-center mb-1">
          <span class="text-sm font-medium text-gray-700">CGT</span>
          <span class="text-xs text-gray-500">{{ formatCurrency(cgtUsed) }} / {{ formatCurrency(cgtLimit) }}</span>
        </div>
        <div class="h-2 rounded-full overflow-hidden bg-gray-200">
          <div
            class="h-full rounded-full transition-all duration-300"
            :class="getProgressBarClass(cgtPercent)"
            :style="{ width: Math.min(cgtPercent, 100) + '%' }"
          ></div>
        </div>
        <div class="text-xs text-gray-500 mt-1">{{ formatCurrency(cgtRemaining) }} remaining</div>
      </div>

      <!-- Dividend Allowance -->
      <div class="allowance-item">
        <div class="flex justify-between items-center mb-1">
          <span class="text-sm font-medium text-gray-700">Dividend</span>
          <span class="text-xs text-gray-500">{{ formatCurrency(dividendUsed) }} / {{ formatCurrency(dividendLimit) }}</span>
        </div>
        <div class="h-2 rounded-full overflow-hidden bg-gray-200">
          <div
            class="h-full rounded-full transition-all duration-300"
            :class="getProgressBarClass(dividendPercent)"
            :style="{ width: Math.min(dividendPercent, 100) + '%' }"
          ></div>
        </div>
        <div class="text-xs text-gray-500 mt-1">{{ formatCurrency(dividendRemaining) }} remaining</div>
      </div>
    </div>

    <!-- Expiring Warning -->
    <div
      v-if="hasExpiringAllowances"
      class="mt-4 p-3 bg-white border-2 border-amber-500 rounded-lg"
    >
      <div class="flex items-center gap-2">
        <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span class="text-sm font-medium text-amber-700">
          {{ expiringMessage }}
        </span>
      </div>
    </div>
  </div>
</template>

<script>
import { mapState, mapGetters } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'TaxOptimisationCard',
  mixins: [currencyMixin],

  data() {
    return {
      // UK Tax Year 2025/26 allowances
      isaLimit: 20000,
      pensionLimit: 60000,
      cgtLimit: 3000,
      dividendLimit: 500,
    };
  },

  computed: {
    ...mapState('savings', ['accounts']),
    ...mapState('investment', { investmentAccounts: 'accounts' }),
    ...mapState('retirement', ['annualAllowance', 'dcPensions']),
    ...mapGetters('investment', ['totalISAContributions']),
    ...mapGetters('userProfile', ['totalAnnualIncome']),

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
        const monthlyContrib = parseFloat(p.monthly_contribution_amount || 0);
        const employerContrib = parseFloat(p.employer_contribution_amount || 0);
        return sum + (monthlyContrib + employerContrib) * 12;
      }, 0);
    },

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
      const annualDividends = this.$store.state.userProfile?.incomeOccupation?.annual_dividend_income || 0;
      return Math.min(annualDividends, this.dividendLimit);
    },

    dividendRemaining() {
      return Math.max(0, this.dividendLimit - this.dividendUsed);
    },

    dividendPercent() {
      return (this.dividendUsed / this.dividendLimit) * 100;
    },

    // Check if any allowances are about to expire (within 3 months of tax year end)
    hasExpiringAllowances() {
      const now = new Date();
      const taxYearEnd = new Date(now.getFullYear(), 3, 5); // April 5th
      if (now > taxYearEnd) {
        taxYearEnd.setFullYear(taxYearEnd.getFullYear() + 1);
      }
      const monthsUntilEnd = (taxYearEnd - now) / (1000 * 60 * 60 * 24 * 30);

      // Warn if within 3 months and significant allowance unused
      return monthsUntilEnd <= 3 && (
        this.isaRemaining > 5000 ||
        this.pensionRemaining > 10000
      );
    },

    expiringMessage() {
      const messages = [];
      if (this.isaRemaining > 5000) {
        messages.push(`${this.formatCurrency(this.isaRemaining)} ISA`);
      }
      if (this.pensionRemaining > 10000) {
        messages.push(`${this.formatCurrency(this.pensionRemaining)} Pension`);
      }
      return `${messages.join(' and ')} allowance expires 5 April`;
    },
  },

  methods: {
    navigateToTaxInfo() {
      this.$router.push('/savings');
    },

    getProgressBarClass(percent) {
      if (percent >= 90) return 'bg-green-600';
      if (percent >= 50) return 'bg-primary-600';
      if (percent >= 25) return 'bg-amber-500';
      return 'bg-gray-400';
    },
  },
};
</script>
