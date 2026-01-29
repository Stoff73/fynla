<template>
  <div class="account-fees-panel">
    <!-- Annual Cost Breakdown -->
    <div class="cost-section">
      <h4 class="section-title">Annual Cost Breakdown</h4>
      <div class="cost-breakdown">
        <div class="cost-row">
          <span class="cost-label">{{ platformFeeCostLabel }}</span>
          <span class="cost-value">{{ formatCurrency(annualPlatformFee) }}</span>
        </div>
        <div class="cost-row">
          <span class="cost-label">Fund Fees - OCF ({{ formatPercentage(weightedAverageOCF) }})</span>
          <span class="cost-value">{{ formatCurrency(annualFundFees) }}</span>
        </div>
        <div class="cost-row" v-if="advisorFeePercent > 0">
          <span class="cost-label">Advisor Fee ({{ formatPercentage(advisorFeePercent) }})</span>
          <span class="cost-value">{{ formatCurrency(annualAdvisorFee) }}</span>
        </div>
        <div class="cost-row total">
          <span class="cost-label">Total Annual Cost</span>
          <span class="cost-value">{{ formatCurrency(totalAnnualFees) }}</span>
        </div>
      </div>
    </div>

    <!-- Per-Holding Fee Breakdown -->
    <div class="funds-section" v-if="holdings.length > 0">
      <h4 class="section-title">Fund Fee Breakdown (OCF)</h4>
      <div class="holdings-table-wrapper">
        <table class="holdings-table">
          <thead>
            <tr>
              <th class="text-left">Holding</th>
              <th class="text-right">Value</th>
              <th class="text-right">OCF</th>
              <th class="text-right">Annual Cost</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="holding in holdings" :key="holding.id">
              <td class="holding-name">{{ holding.security_name }}</td>
              <td class="text-right">{{ formatCurrency(holding.current_value) }}</td>
              <td class="text-right">{{ formatPercentage(holding.ocf_percent) }}</td>
              <td class="text-right">{{ formatCurrency(getHoldingAnnualFee(holding)) }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="totals-row">
              <td class="font-semibold">Total Fund Fees</td>
              <td class="text-right font-semibold">{{ formatCurrency(totalHoldingsValue) }}</td>
              <td class="text-right font-semibold">{{ formatPercentage(weightedAverageOCF) }} avg</td>
              <td class="text-right font-semibold">{{ formatCurrency(annualFundFees) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    <!-- No Holdings Message -->
    <div v-else class="no-holdings-message">
      <p>No holdings data available for fee breakdown.</p>
    </div>

    <!-- 10-Year Fee Impact -->
    <div class="impact-section">
      <h4 class="section-title">10-Year Fee Impact</h4>
      <p class="section-subtitle">Projected cumulative fees assuming 5% annual growth</p>
      <div class="impact-grid">
        <div class="impact-card">
          <span class="impact-label">Total Fees Over 10 Years</span>
          <span class="impact-value text-red-600">{{ formatCurrency(tenYearTotalFees) }}</span>
        </div>
        <div class="impact-card">
          <span class="impact-label">Projected Portfolio (Without Fees)</span>
          <span class="impact-value text-green-600">{{ formatCurrency(tenYearValueWithoutFees) }}</span>
        </div>
        <div class="impact-card">
          <span class="impact-label">Projected Portfolio (With Fees)</span>
          <span class="impact-value">{{ formatCurrency(tenYearValueWithFees) }}</span>
        </div>
        <div class="impact-card highlight">
          <span class="impact-label">Fee Drag (Lost Growth)</span>
          <span class="impact-value text-orange-600">{{ formatCurrency(tenYearFeeDrag) }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'AccountFeesPanel',

  mixins: [currencyMixin],

  props: {
    account: {
      type: Object,
      required: true,
    },
  },

  computed: {
    holdings() {
      return this.account.holdings || [];
    },

    totalHoldingsValue() {
      return this.holdings.reduce((sum, h) => sum + (parseFloat(h.current_value) || 0), 0);
    },

    // Whether platform fee is fixed (£) or percentage
    platformFeeType() {
      return this.account.platform_fee_type || 'percentage';
    },

    platformFeeFrequency() {
      return this.account.platform_fee_frequency || 'annually';
    },

    // Display text for platform fee card
    platformFeeDisplay() {
      if (this.platformFeeType === 'fixed') {
        const amount = parseFloat(this.account.platform_fee_amount) || 0;
        const freq = { monthly: '/month', quarterly: '/quarter', annually: '/year' };
        return `${this.formatCurrency(amount)}${freq[this.platformFeeFrequency] || '/year'}`;
      }
      return this.formatPercentage(this.platformFeePercent);
    },

    // Label for cost breakdown row
    platformFeeCostLabel() {
      if (this.platformFeeType === 'fixed') {
        return `Platform Fee (${this.platformFeeDisplay})`;
      }
      return `Platform Fee (${this.formatPercentage(this.platformFeePercent)})`;
    },

    // Annual platform fee in £
    annualPlatformFeeAmount() {
      if (this.platformFeeType === 'fixed') {
        const amount = parseFloat(this.account.platform_fee_amount) || 0;
        if (this.platformFeeFrequency === 'monthly') return amount * 12;
        if (this.platformFeeFrequency === 'quarterly') return amount * 4;
        return amount;
      }
      const accountValue = parseFloat(this.account.current_value) || 0;
      return accountValue * (this.platformFeePercent / 100);
    },

    // Platform fee as effective percentage (for totals and projections)
    platformFeePercent() {
      if (this.platformFeeType === 'fixed') {
        const accountValue = parseFloat(this.account.current_value) || 0;
        if (accountValue === 0) return 0;
        return (this.annualPlatformFeeAmount / accountValue) * 100;
      }
      return parseFloat(this.account.platform_fee_percent) || 0;
    },

    // Advisor fee percentage
    advisorFeePercent() {
      return parseFloat(this.account.advisor_fee_percent) || 0;
    },

    // Weighted average OCF across holdings
    weightedAverageOCF() {
      if (this.holdings.length === 0 || this.totalHoldingsValue === 0) return 0;
      const weightedSum = this.holdings.reduce((sum, h) => {
        const value = parseFloat(h.current_value) || 0;
        const ocf = parseFloat(h.ocf_percent) || 0;
        return sum + (value * ocf);
      }, 0);
      return weightedSum / this.totalHoldingsValue;
    },

    // Total fee percentage
    totalFeePercentage() {
      return this.platformFeePercent + this.weightedAverageOCF + this.advisorFeePercent;
    },

    // Annual costs in £
    annualPlatformFee() {
      return this.annualPlatformFeeAmount;
    },

    annualAdvisorFee() {
      const accountValue = parseFloat(this.account.current_value) || 0;
      return accountValue * (this.advisorFeePercent / 100);
    },

    annualFundFees() {
      return this.holdings.reduce((sum, h) => {
        const value = parseFloat(h.current_value) || 0;
        const ocf = parseFloat(h.ocf_percent) || 0;
        return sum + (value * ocf / 100);
      }, 0);
    },

    totalAnnualFees() {
      return this.annualPlatformFee + this.annualFundFees + this.annualAdvisorFee;
    },

    // 10-Year projections
    tenYearValueWithoutFees() {
      const currentValue = parseFloat(this.account.current_value) || 0;
      const growthRate = 0.05; // 5% annual growth
      return currentValue * Math.pow(1 + growthRate, 10);
    },

    tenYearValueWithFees() {
      const currentValue = parseFloat(this.account.current_value) || 0;
      const grossReturn = 0.05; // 5% gross return
      const totalFeeRate = this.totalFeePercentage / 100;
      const netReturn = grossReturn - totalFeeRate;
      return currentValue * Math.pow(1 + netReturn, 10);
    },

    tenYearFeeDrag() {
      return this.tenYearValueWithoutFees - this.tenYearValueWithFees;
    },

    tenYearTotalFees() {
      // Approximate cumulative fees over 10 years with growth
      const currentValue = parseFloat(this.account.current_value) || 0;
      const growthRate = 0.05;
      const feeRate = this.totalFeePercentage / 100;
      let totalFees = 0;
      let portfolioValue = currentValue;

      for (let year = 0; year < 10; year++) {
        totalFees += portfolioValue * feeRate;
        portfolioValue *= (1 + growthRate - feeRate);
      }
      return totalFees;
    },
  },

  methods: {
    formatPercentage(value) {
      if (value === null || value === undefined) return '0.00%';
      return `${parseFloat(value).toFixed(2)}%`;
    },

    getHoldingAnnualFee(holding) {
      const value = parseFloat(holding.current_value) || 0;
      const ocf = parseFloat(holding.ocf_percent) || 0;
      return value * ocf / 100;
    },
  },
};
</script>

<style scoped>
.account-fees-panel {
  min-height: 400px;
}

.section-title {
  font-size: 16px;
  font-weight: 600;
  color: #111827;
  margin: 0 0 4px 0;
}

.section-subtitle {
  font-size: 13px;
  color: #6b7280;
  margin: 0 0 16px 0;
}

.cost-section,
.funds-section,
.impact-section {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 20px;
  margin-bottom: 24px;
}

.cost-breakdown {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.cost-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  background: #f9fafb;
  border-radius: 8px;
}

.cost-row.total {
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  margin-top: 8px;
}

.cost-label {
  font-size: 14px;
  color: #374151;
}

.cost-row.total .cost-label {
  font-weight: 600;
  color: #1e40af;
}

.cost-value {
  font-size: 16px;
  font-weight: 600;
  color: #111827;
}

.cost-row.total .cost-value {
  color: #1e40af;
}

/* Holdings Table */
.holdings-table-wrapper {
  overflow-x: auto;
}

.holdings-table {
  width: 100%;
  border-collapse: collapse;
}

.holdings-table th,
.holdings-table td {
  padding: 12px 16px;
  border-bottom: 1px solid #e5e7eb;
}

.holdings-table th {
  font-size: 12px;
  font-weight: 600;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  background: #f9fafb;
}

.holdings-table td {
  font-size: 14px;
  color: #374151;
}

.holding-name {
  font-weight: 500;
  color: #111827;
}

.totals-row {
  background: #f0f9ff;
}

.totals-row td {
  border-bottom: none;
  color: #1e40af;
}

.no-holdings-message {
  background: #f9fafb;
  border: 1px dashed #d1d5db;
  border-radius: 8px;
  padding: 40px;
  text-align: center;
  color: #6b7280;
  margin-bottom: 24px;
}

/* Impact Grid */
.impact-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
  margin-top: 16px;
}

.impact-card {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 16px;
  background: #f9fafb;
  border-radius: 8px;
}

.impact-card.highlight {
  background: #fffbeb;
  border: 1px solid #fcd34d;
}

.impact-label {
  font-size: 12px;
  color: #6b7280;
}

.impact-value {
  font-size: 20px;
  font-weight: 700;
  color: #111827;
}

@media (max-width: 768px) {
  .fee-summary {
    grid-template-columns: 1fr 1fr;
  }

  .impact-grid {
    grid-template-columns: 1fr;
  }

  .card-value,
  .impact-value {
    font-size: 18px;
  }

  .holdings-table th,
  .holdings-table td {
    padding: 8px 12px;
    font-size: 13px;
  }
}

@media (max-width: 480px) {
  .fee-summary {
    grid-template-columns: 1fr;
  }
}
</style>
