<template>
  <div class="account-fees-panel relative">
    <!-- Coming Soon Watermark -->
    <div class="absolute inset-0 flex items-center justify-center z-10 pointer-events-none">
      <div class="bg-amber-100 border-2 border-amber-400 rounded-lg px-8 py-4 transform -rotate-12 shadow-lg">
        <p class="text-2xl font-bold text-amber-700">Coming Soon</p>
      </div>
    </div>

    <!-- Placeholder Content -->
    <div class="opacity-50">
      <!-- Platform Fee Display -->
      <div class="fee-summary">
        <div class="summary-card">
          <div class="card-icon bg-amber-100">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="icon text-amber-600">
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
            </svg>
          </div>
          <div class="card-content">
            <span class="card-label">Platform Fee</span>
            <span class="card-value">{{ formatPercentage(account.platform_fee) }}</span>
          </div>
        </div>

        <div class="summary-card">
          <div class="card-icon bg-blue-100">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="icon text-blue-600">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5" />
            </svg>
          </div>
          <div class="card-content">
            <span class="card-label">Average Fund Fee</span>
            <span class="card-value text-gray-400">--</span>
          </div>
        </div>

        <div class="summary-card">
          <div class="card-icon bg-red-100">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="icon text-red-600">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div class="card-content">
            <span class="card-label">Total Cost (OCF)</span>
            <span class="card-value text-gray-400">--</span>
          </div>
        </div>
      </div>

      <!-- Annual Cost Estimate -->
      <div class="cost-section">
        <h4 class="section-title">Annual Cost Estimate</h4>
        <div class="cost-breakdown">
          <div class="cost-row">
            <span class="cost-label">Platform Fee</span>
            <span class="cost-value">{{ formatCurrency(estimatedPlatformFee) }}</span>
          </div>
          <div class="cost-row">
            <span class="cost-label">Fund Fees (Estimated)</span>
            <span class="cost-value text-gray-400">--</span>
          </div>
          <div class="cost-row total">
            <span class="cost-label">Total Annual Cost</span>
            <span class="cost-value text-gray-400">--</span>
          </div>
        </div>
      </div>

      <!-- Fund Fees Placeholder -->
      <div class="funds-section">
        <h4 class="section-title">Fund Fee Breakdown</h4>
        <div class="funds-placeholder">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="placeholder-icon">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z" />
          </svg>
          <p class="placeholder-text">Individual fund fees will be displayed here</p>
        </div>
      </div>

      <!-- Fee Comparison Placeholder -->
      <div class="comparison-section">
        <h4 class="section-title">Fee Comparison</h4>
        <div class="comparison-placeholder">
          <div class="comparison-row">
            <span class="comparison-label">Your Total Cost</span>
            <span class="comparison-value text-gray-400">--</span>
          </div>
          <div class="comparison-row">
            <span class="comparison-label">Industry Average</span>
            <span class="comparison-value text-gray-400">0.75%</span>
          </div>
          <div class="comparison-row">
            <span class="comparison-label">Potential Savings</span>
            <span class="comparison-value text-gray-400">--</span>
          </div>
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
    estimatedPlatformFee() {
      const fee = parseFloat(this.account.platform_fee) || 0;
      const value = parseFloat(this.account.current_value) || 0;
      return (value * fee) / 100;
    },
  },

  methods: {
    formatPercentage(value) {
      if (value === null || value === undefined) return 'N/A';
      return `${parseFloat(value).toFixed(2)}%`;
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
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
  margin-bottom: 24px;
}

.summary-card {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 20px;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
}

.card-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 48px;
  height: 48px;
  border-radius: 12px;
  flex-shrink: 0;
}

.icon {
  width: 24px;
  height: 24px;
}

.card-content {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.card-label {
  font-size: 12px;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.card-value {
  font-size: 24px;
  font-weight: 700;
  color: #111827;
}

.section-title {
  font-size: 16px;
  font-weight: 600;
  color: #111827;
  margin: 0 0 16px 0;
}

.cost-section,
.funds-section,
.comparison-section {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 20px;
  margin-bottom: 24px;
}

.cost-breakdown {
  display: flex;
  flex-direction: column;
  gap: 12px;
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

.funds-placeholder,
.comparison-placeholder {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 40px 20px;
  background: #f9fafb;
  border-radius: 8px;
}

.placeholder-icon {
  width: 48px;
  height: 48px;
  color: #d1d5db;
  margin-bottom: 12px;
}

.placeholder-text {
  font-size: 14px;
  color: #9ca3af;
  margin: 0;
}

.comparison-placeholder {
  align-items: stretch;
  padding: 0;
  background: transparent;
}

.comparison-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  background: #f9fafb;
  border-radius: 8px;
  margin-bottom: 8px;
}

.comparison-row:last-child {
  margin-bottom: 0;
}

.comparison-label {
  font-size: 14px;
  color: #374151;
}

.comparison-value {
  font-size: 16px;
  font-weight: 600;
}

@media (max-width: 768px) {
  .fee-summary {
    grid-template-columns: 1fr;
  }
}
</style>
