<template>
  <div class="private-investment-detail space-y-6">
    <!-- Key Metrics Header -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <!-- Card 1: Valuation -->
      <div class="metric-card bg-blue-50 border border-blue-200">
        <p class="metric-label">{{ latestValuation ? 'Latest Valuation' : 'Investment Amount' }}</p>
        <p class="metric-value text-blue-600">{{ formatCurrency(displayValue) }}</p>
        <p v-if="account.latest_valuation_date" class="metric-sub">as of {{ formatDate(account.latest_valuation_date) }}</p>
      </div>

      <!-- Card 2: Return Multiple -->
      <div class="metric-card" :class="returnMultiple && returnMultiple >= 1 ? 'bg-green-50 border border-green-200' : 'bg-gray-50 border border-gray-200'">
        <p class="metric-label">Return Multiple</p>
        <template v-if="returnMultiple">
          <p class="metric-value" :class="returnMultiple >= 1 ? 'text-green-600' : 'text-red-600'">{{ returnMultiple.toFixed(2) }}x</p>
          <p class="metric-sub" :class="paperGainLoss >= 0 ? 'text-green-600' : 'text-red-600'">
            {{ paperGainLoss >= 0 ? '+' : '' }}{{ formatCurrency(paperGainLoss) }}
          </p>
        </template>
        <template v-else>
          <p class="metric-value text-gray-400">--</p>
          <p class="metric-sub">No valuation yet</p>
        </template>
      </div>

      <!-- Card 3: Tax Relief Status -->
      <div class="metric-card" :class="taxReliefCardClass">
        <p class="metric-label">Tax Relief</p>
        <template v-if="hasTaxRelief">
          <p class="metric-value" :class="taxReliefStatus === 'claimed' ? 'text-green-600' : 'text-orange-600'">
            {{ taxReliefLabel }}
          </p>
          <p class="metric-sub">{{ taxReliefStatusLabel }}</p>
        </template>
        <template v-else>
          <p class="metric-value text-gray-400">None</p>
          <p class="metric-sub">No tax relief</p>
        </template>
      </div>

      <!-- Card 4: Status / Disposal Restriction -->
      <div class="metric-card" :class="statusCardClass">
        <template v-if="disposalRestrictionDays">
          <p class="metric-label">Holding Period</p>
          <p class="metric-value text-orange-600">{{ disposalRestrictionDays }}</p>
          <p class="metric-sub">days remaining</p>
        </template>
        <template v-else-if="isHoldingPeriodComplete && hasTaxRelief">
          <p class="metric-label">Holding Period</p>
          <p class="metric-value text-green-600">Complete</p>
          <p class="metric-sub">Can sell without clawback</p>
        </template>
        <template v-else>
          <p class="metric-label">Company Status</p>
          <p class="metric-value" :class="companyStatusClass">{{ companyStatusLabel }}</p>
          <p v-if="account.status_notes" class="metric-sub truncate">{{ account.status_notes }}</p>
        </template>
      </div>
    </div>

    <!-- Company Details -->
    <div class="details-section">
      <h3 class="section-title">Company Details</h3>
      <div class="details-grid">
        <div class="detail-item">
          <span class="detail-label">Legal Name</span>
          <span class="detail-value">{{ account.company_legal_name || 'Not specified' }}</span>
        </div>
        <div v-if="account.company_trading_name && account.company_trading_name !== account.company_legal_name" class="detail-item">
          <span class="detail-label">Trading Name</span>
          <span class="detail-value">{{ account.company_trading_name }}</span>
        </div>
        <div v-if="account.company_registration_number" class="detail-item">
          <span class="detail-label">Company Number</span>
          <span class="detail-value">{{ account.company_registration_number }}</span>
        </div>
        <div v-if="account.company_sector" class="detail-item">
          <span class="detail-label">Sector</span>
          <span class="detail-value">{{ sectorLabel }}</span>
        </div>
        <div v-if="account.company_website" class="detail-item">
          <span class="detail-label">Website</span>
          <a :href="formatUrl(account.company_website)" target="_blank" rel="noopener noreferrer" class="detail-value text-blue-600 hover:underline">
            {{ account.company_website }}
          </a>
        </div>
        <div v-if="isCrowdfunding && account.crowdfunding_platform" class="detail-item">
          <span class="detail-label">Platform</span>
          <span class="detail-value">{{ formatPlatform(account.crowdfunding_platform) }}</span>
        </div>
      </div>
    </div>

    <!-- Investment Details -->
    <div class="details-section">
      <h3 class="section-title">Investment Details</h3>
      <div class="details-grid">
        <div class="detail-item">
          <span class="detail-label">Investment Date</span>
          <span class="detail-value">{{ formatDate(account.investment_date) }}</span>
        </div>
        <div class="detail-item">
          <span class="detail-label">Investment Amount</span>
          <span class="detail-value text-blue-600 font-bold">{{ formatCurrency(account.investment_amount) }}</span>
        </div>
        <div v-if="account.funding_round" class="detail-item">
          <span class="detail-label">Funding Round</span>
          <span class="detail-value">{{ fundingRoundLabel }}</span>
        </div>
        <div class="detail-item">
          <span class="detail-label">Instrument Type</span>
          <span class="detail-value">{{ instrumentTypeLabel }}</span>
        </div>
        <div v-if="account.number_of_shares" class="detail-item">
          <span class="detail-label">Number of Shares</span>
          <span class="detail-value">{{ formatNumber(account.number_of_shares) }}</span>
        </div>
        <div v-if="account.price_per_share" class="detail-item">
          <span class="detail-label">Price Per Share</span>
          <span class="detail-value">{{ formatCurrencyPence(account.price_per_share) }}</span>
        </div>
        <div v-if="account.pre_money_valuation" class="detail-item">
          <span class="detail-label">Pre-Money Valuation</span>
          <span class="detail-value">{{ formatCurrencyShort(account.pre_money_valuation) }}</span>
        </div>
        <div v-if="account.post_money_valuation" class="detail-item">
          <span class="detail-label">Post-Money Valuation</span>
          <span class="detail-value">{{ formatCurrencyShort(account.post_money_valuation) }}</span>
        </div>
      </div>
    </div>

    <!-- Ownership & Legal -->
    <div class="details-section">
      <h3 class="section-title">Ownership & Legal</h3>
      <div class="details-grid">
        <div v-if="account.share_class" class="detail-item">
          <span class="detail-label">Share Class</span>
          <span class="detail-value">{{ account.share_class }}</span>
        </div>
        <div class="detail-item">
          <span class="detail-label">Holding Structure</span>
          <span class="detail-value">{{ account.holding_structure === 'nominee' ? 'Nominee Held' : 'Direct' }}</span>
        </div>
        <div v-if="account.nominee_name" class="detail-item">
          <span class="detail-label">Nominee</span>
          <span class="detail-value">{{ account.nominee_name }}</span>
        </div>
        <div class="detail-item">
          <span class="detail-label">Voting Rights</span>
          <span class="detail-value">{{ account.has_voting_rights !== false ? 'Yes' : 'No' }}</span>
        </div>
        <div class="detail-item">
          <span class="detail-label">Dividend Rights</span>
          <span class="detail-value">{{ account.has_dividend_rights !== false ? 'Yes' : 'No' }}</span>
        </div>
        <div v-if="account.current_ownership_percent" class="detail-item">
          <span class="detail-label">Ownership %</span>
          <span class="detail-value">{{ parseFloat(account.current_ownership_percent).toFixed(4) }}%</span>
        </div>
        <div v-if="account.liquidation_preference" class="detail-item">
          <span class="detail-label">Liquidation Preference</span>
          <span class="detail-value">{{ account.liquidation_preference }}</span>
        </div>
      </div>
    </div>

    <!-- UK Tax Relief (if applicable) -->
    <div v-if="hasTaxRelief" class="details-section bg-blue-50 border-blue-200">
      <h3 class="section-title text-blue-800">UK Tax Relief</h3>

      <!-- Status Banner -->
      <div v-if="taxReliefStatus === 'claimed'" class="alert alert-success mb-4">
        <p class="font-medium">Tax Relief Claimed</p>
        <p class="text-sm">{{ formatCurrency(account.relief_amount_claimed) }} on {{ formatDate(account.relief_claimed_date) }}</p>
      </div>
      <div v-else-if="taxReliefStatus === 'pending'" class="alert alert-warning mb-4">
        <p class="font-medium">Certificate Received - Claim Your Relief</p>
        <p class="text-sm">Certificate: {{ account.eis3_certificate_number }}</p>
      </div>

      <!-- Holding Period Warning -->
      <div v-if="disposalRestrictionDays" class="alert alert-warning mb-4">
        <p class="font-medium">Holding Period Restriction</p>
        <p class="text-sm">{{ disposalRestrictionDays }} days remaining until {{ formatDate(account.disposal_restriction_date) }}</p>
        <p class="text-xs mt-1 opacity-75">Selling before this date may trigger clawback of tax relief</p>
      </div>
      <div v-else-if="isHoldingPeriodComplete" class="alert alert-success mb-4">
        <p class="font-medium">Holding Period Complete</p>
        <p class="text-sm">Shares can be sold without clawback risk</p>
      </div>

      <div class="details-grid">
        <div class="detail-item">
          <span class="detail-label">Relief Type</span>
          <span class="detail-value">{{ taxReliefLabel }}</span>
        </div>
        <div v-if="account.eis3_certificate_number" class="detail-item">
          <span class="detail-label">Certificate Number</span>
          <span class="detail-value">{{ account.eis3_certificate_number }}</span>
        </div>
        <div v-if="account.hmrc_reference" class="detail-item">
          <span class="detail-label">HMRC Reference</span>
          <span class="detail-value">{{ account.hmrc_reference }}</span>
        </div>
        <div v-if="account.disposal_restriction_date" class="detail-item">
          <span class="detail-label">Disposal Restriction Ends</span>
          <span class="detail-value">{{ formatDate(account.disposal_restriction_date) }}</span>
        </div>
        <div v-if="account.relief_amount_claimed" class="detail-item">
          <span class="detail-label">Relief Claimed</span>
          <span class="detail-value text-green-600">{{ formatCurrency(account.relief_amount_claimed) }}</span>
        </div>
        <div v-if="account.clawback_risk" class="detail-item">
          <span class="detail-label">Clawback Risk</span>
          <span class="detail-value text-red-600">Flagged</span>
        </div>
      </div>
    </div>

    <!-- Status & Valuation -->
    <div class="details-section">
      <h3 class="section-title">Status & Valuation</h3>
      <div class="details-grid">
        <div class="detail-item">
          <span class="detail-label">Company Status</span>
          <span class="detail-value px-2 py-1 rounded-md inline-block" :class="companyStatusBadgeClass">{{ companyStatusLabel }}</span>
        </div>
        <div v-if="latestValuation" class="detail-item">
          <span class="detail-label">Latest Valuation</span>
          <span class="detail-value text-blue-600 font-bold">{{ formatCurrency(latestValuation) }}</span>
        </div>
        <div v-if="account.latest_valuation_date" class="detail-item">
          <span class="detail-label">Valuation Date</span>
          <span class="detail-value text-gray-500">{{ formatDate(account.latest_valuation_date) }}</span>
        </div>
        <div v-if="returnMultiple" class="detail-item">
          <span class="detail-label">Return Multiple (MOIC)</span>
          <span class="detail-value" :class="returnMultiple >= 1 ? 'text-green-600' : 'text-red-600'">
            {{ returnMultiple.toFixed(2) }}x
          </span>
        </div>
        <div v-if="paperGainLoss !== null" class="detail-item">
          <span class="detail-label">Unrealised Gain/Loss</span>
          <span class="detail-value" :class="paperGainLoss >= 0 ? 'text-green-600' : 'text-red-600'">
            {{ paperGainLoss >= 0 ? '+' : '' }}{{ formatCurrency(paperGainLoss) }}
          </span>
        </div>
        <div v-if="account.status_notes" class="detail-item detail-item-wide">
          <span class="detail-label">Notes</span>
          <span class="detail-value">{{ account.status_notes }}</span>
        </div>
      </div>
    </div>

    <!-- Exit Details (if exited) -->
    <div v-if="isExited" class="details-section bg-blue-50 border-blue-200">
      <h3 class="section-title text-blue-800">Exit Details</h3>
      <div class="details-grid">
        <div v-if="account.exit_type" class="detail-item">
          <span class="detail-label">Exit Type</span>
          <span class="detail-value">{{ exitTypeLabel }}</span>
        </div>
        <div v-if="account.exit_date" class="detail-item">
          <span class="detail-label">Exit Date</span>
          <span class="detail-value">{{ formatDate(account.exit_date) }}</span>
        </div>
        <div v-if="account.exit_gross_proceeds" class="detail-item">
          <span class="detail-label">Gross Proceeds</span>
          <span class="detail-value">{{ formatCurrency(account.exit_gross_proceeds) }}</span>
        </div>
        <div v-if="account.exit_fees" class="detail-item">
          <span class="detail-label">Exit Fees</span>
          <span class="detail-value text-red-600">-{{ formatCurrency(account.exit_fees) }}</span>
        </div>
        <div v-if="account.exit_net_proceeds" class="detail-item">
          <span class="detail-label">Net Proceeds</span>
          <span class="detail-value text-green-600 font-bold">{{ formatCurrency(account.exit_net_proceeds) }}</span>
        </div>
        <div v-if="exitMOIC" class="detail-item">
          <span class="detail-label">Exit MOIC</span>
          <span class="detail-value" :class="exitMOIC >= 1 ? 'text-green-600' : 'text-red-600'">
            {{ exitMOIC.toFixed(2) }}x
          </span>
        </div>
        <div v-if="account.loss_relief_eligible" class="detail-item">
          <span class="detail-label">Loss Relief Eligible</span>
          <span class="detail-value text-blue-600">Yes</span>
        </div>
        <div v-if="account.capital_loss_amount" class="detail-item">
          <span class="detail-label">Capital Loss</span>
          <span class="detail-value text-red-600">{{ formatCurrency(account.capital_loss_amount) }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'PrivateInvestmentDetail',
  mixins: [currencyMixin],

  props: {
    account: {
      type: Object,
      required: true,
    },
  },

  computed: {
    isCrowdfunding() {
      return this.account.account_type === 'crowdfunding';
    },

    displayValue() {
      return parseFloat(this.account.latest_valuation) ||
             parseFloat(this.account.investment_amount) || 0;
    },

    investmentAmount() {
      return parseFloat(this.account.investment_amount) || 0;
    },

    latestValuation() {
      return parseFloat(this.account.latest_valuation) || null;
    },

    returnMultiple() {
      if (!this.investmentAmount || !this.latestValuation) return null;
      return this.latestValuation / this.investmentAmount;
    },

    paperGainLoss() {
      if (!this.investmentAmount || !this.latestValuation) return null;
      return this.latestValuation - this.investmentAmount;
    },

    hasTaxRelief() {
      return ['eis', 'seis', 'sitr', 'vct'].includes(this.account.tax_relief_type);
    },

    taxReliefLabel() {
      const labels = {
        'eis': 'EIS (30%)',
        'seis': 'SEIS (50%)',
        'sitr': 'SITR',
        'vct': 'VCT',
      };
      return labels[this.account.tax_relief_type] || 'None';
    },

    taxReliefStatus() {
      if (!this.hasTaxRelief) return 'none';
      if (this.account.relief_claimed_date) return 'claimed';
      if (this.account.eis3_certificate_number) return 'pending';
      return 'eligible';
    },

    taxReliefStatusLabel() {
      const labels = {
        'claimed': 'Relief claimed',
        'pending': 'Claim pending',
        'eligible': 'Eligible',
        'none': 'None',
      };
      return labels[this.taxReliefStatus];
    },

    taxReliefCardClass() {
      if (!this.hasTaxRelief) return 'bg-gray-50 border border-gray-200';
      if (this.taxReliefStatus === 'claimed') return 'bg-green-50 border border-green-200';
      return 'bg-orange-50 border border-orange-200';
    },

    disposalRestrictionDays() {
      if (!this.account.disposal_restriction_date) return null;
      const restrictionDate = new Date(this.account.disposal_restriction_date);
      const now = new Date();
      const diff = Math.ceil((restrictionDate - now) / (1000 * 60 * 60 * 24));
      return diff > 0 ? diff : null;
    },

    isHoldingPeriodComplete() {
      if (!this.account.disposal_restriction_date) return false;
      return new Date() >= new Date(this.account.disposal_restriction_date);
    },

    statusCardClass() {
      if (this.disposalRestrictionDays) return 'bg-orange-50 border border-orange-200';
      if (this.isHoldingPeriodComplete && this.hasTaxRelief) return 'bg-green-50 border border-green-200';
      return 'bg-gray-50 border border-gray-200';
    },

    companyStatusLabel() {
      const statuses = {
        'active': 'Active',
        'distressed': 'Distressed',
        'dormant': 'Dormant',
        'failed': 'Failed',
        'exited': 'Exited',
      };
      return statuses[this.account.company_status] || 'Active';
    },

    companyStatusClass() {
      const classes = {
        'active': 'text-green-600',
        'distressed': 'text-orange-600',
        'dormant': 'text-gray-600',
        'failed': 'text-red-600',
        'exited': 'text-blue-600',
      };
      return classes[this.account.company_status] || 'text-green-600';
    },

    companyStatusBadgeClass() {
      const classes = {
        'active': 'bg-green-100 text-green-800',
        'distressed': 'bg-orange-100 text-orange-800',
        'dormant': 'bg-gray-100 text-gray-800',
        'failed': 'bg-red-100 text-red-800',
        'exited': 'bg-blue-100 text-blue-800',
      };
      return classes[this.account.company_status] || 'bg-green-100 text-green-800';
    },

    isExited() {
      return this.account.company_status === 'exited';
    },

    exitMOIC() {
      if (!this.investmentAmount || !this.account.exit_net_proceeds) return null;
      return parseFloat(this.account.exit_net_proceeds) / this.investmentAmount;
    },

    fundingRoundLabel() {
      const rounds = {
        'pre_seed': 'Pre-Seed',
        'seed': 'Seed',
        'series_a': 'Series A',
        'series_b': 'Series B',
        'series_c': 'Series C',
        'bridge': 'Bridge',
        'safe': 'SAFE',
        'other': 'Other',
      };
      return rounds[this.account.funding_round] || this.account.funding_round || '--';
    },

    instrumentTypeLabel() {
      const types = {
        'ordinary_shares': 'Ordinary Shares',
        'preference_shares': 'Preference Shares',
        'convertible_loan_note': 'Convertible Loan Note',
        'safe': 'SAFE',
        'revenue_share': 'Revenue Share',
        'fund_nominee_interest': 'Fund/Nominee Interest',
      };
      return types[this.account.instrument_type] || this.account.instrument_type || '--';
    },

    sectorLabel() {
      const sectors = {
        'technology': 'Technology',
        'healthcare': 'Healthcare',
        'fintech': 'Fintech',
        'consumer': 'Consumer',
        'energy': 'Energy',
        'real_estate': 'Real Estate',
        'other': 'Other',
      };
      return sectors[this.account.company_sector] || this.account.company_sector || '--';
    },

    exitTypeLabel() {
      const types = {
        'acquisition': 'Acquisition',
        'secondary_sale': 'Secondary Sale',
        'buyback': 'Buyback',
        'ipo': 'IPO',
        'liquidation': 'Liquidation',
      };
      return types[this.account.exit_type] || this.account.exit_type || '--';
    },
  },

  methods: {
    formatDate(dateString) {
      if (!dateString) return '--';
      const date = new Date(dateString);
      return date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
    },
    formatNumber(value) {
      if (!value && value !== 0) return '--';
      return new Intl.NumberFormat('en-GB').format(value);
    },
    formatCurrencyPence(value) {
      if (!value && value !== 0) return '--';
      const num = parseFloat(value);
      if (num < 1) {
        return `${(num * 100).toFixed(1)}p`;
      }
      return this.formatCurrency(num);
    },
    formatCurrencyShort(value) {
      if (!value && value !== 0) return '--';
      const num = parseFloat(value);
      if (num >= 1000000) {
        return `£${(num / 1000000).toFixed(1)}M`;
      }
      if (num >= 1000) {
        return `£${(num / 1000).toFixed(0)}K`;
      }
      return this.formatCurrency(num);
    },
    formatUrl(url) {
      if (!url) return '#';
      if (url.startsWith('http://') || url.startsWith('https://')) {
        return url;
      }
      return `https://${url}`;
    },
    formatPlatform(platform) {
      const platforms = {
        'seedrs': 'Seedrs',
        'crowdcube': 'Crowdcube',
        'republic': 'Republic',
        'wefunder': 'Wefunder',
        'other': 'Other',
      };
      return platforms[platform] || platform || '--';
    },
  },
};
</script>

<style scoped>
.metric-card {
  @apply rounded-lg p-4;
}

.metric-label {
  @apply text-sm text-gray-600 mb-1;
}

.metric-value {
  @apply text-2xl font-bold;
}

.metric-sub {
  @apply text-xs text-gray-500 mt-1;
}

.details-section {
  @apply bg-white rounded-lg border border-gray-200 p-6;
}

.section-title {
  @apply text-lg font-semibold text-gray-900 mb-5 pb-3 border-b border-gray-200;
}

.details-grid {
  @apply grid gap-5;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
}

.detail-item {
  @apply flex flex-col gap-1;
}

.detail-item-wide {
  @apply col-span-full;
}

.detail-label {
  @apply text-sm font-medium text-gray-500;
}

.detail-value {
  @apply text-base font-semibold text-gray-900;
}

.alert {
  @apply rounded-lg p-4;
}

.alert-success {
  @apply bg-green-100 border border-green-300 text-green-800;
}

.alert-warning {
  @apply bg-orange-100 border border-orange-300 text-orange-800;
}

@media (max-width: 640px) {
  .details-grid {
    grid-template-columns: 1fr;
  }
}
</style>
