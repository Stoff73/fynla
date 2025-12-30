<template>
  <div class="account-fees-panel">
    <!-- Fee Summary Cards -->
    <div class="fee-summary">
      <div class="summary-card">
        <div class="card-icon bg-amber-100">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="icon text-amber-600">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z" />
          </svg>
        </div>
        <div class="card-content">
          <span class="card-label">Platform Fee</span>
          <span class="card-value">{{ formatPercentage(platformFeePercent) }}</span>
        </div>
      </div>

      <div class="summary-card">
        <div class="card-icon bg-blue-100">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="icon text-blue-600">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5" />
          </svg>
        </div>
        <div class="card-content">
          <span class="card-label">Average Fund Fee (OCF)</span>
          <span class="card-value">{{ formatPercentage(weightedAverageOCF) }}</span>
        </div>
      </div>

      <div class="summary-card">
        <div class="card-icon bg-purple-100">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="icon text-purple-600">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
          </svg>
        </div>
        <div class="card-content">
          <span class="card-label">Advisor Fee</span>
          <span class="card-value">{{ formatPercentage(advisorFeePercent) }}</span>
        </div>
      </div>

      <div class="summary-card highlight">
        <div class="card-icon bg-red-100">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="icon text-red-600">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div class="card-content">
          <span class="card-label">Total Annual Cost</span>
          <span class="card-value text-red-600">{{ formatPercentage(totalFeePercentage) }}</span>
        </div>
      </div>
    </div>

    <!-- Annual Cost Breakdown -->
    <div class="cost-section">
      <h4 class="section-title">Annual Cost Breakdown</h4>
      <div class="cost-breakdown">
        <div class="cost-row">
          <span class="cost-label">Platform Fee ({{ formatPercentage(platformFeePercent) }})</span>
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
          <span class="impact-value text-amber-600">{{ formatCurrency(tenYearFeeDrag) }}</span>
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

    // Platform fee percentage
    platformFeePercent() {
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
      const accountValue = parseFloat(this.account.current_value) || 0;
      return accountValue * (this.platformFeePercent / 100);
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

.fee-summary {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 16px;
  margin-bottom: 24px;
}

.summary-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
}

.summary-card.highlight {
  border-color: #fecaca;
  background: #fef2f2;
}

.card-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 44px;
  height: 44px;
  border-radius: 10px;
  flex-shrink: 0;
}

.icon {
  width: 22px;
  height: 22px;
}

.card-content {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.card-label {
  font-size: 11px;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.card-value {
  font-size: 20px;
  font-weight: 700;
  color: #111827;
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
