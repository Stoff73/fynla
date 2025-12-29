<template>
  <div class="tax-breakdown-card">
    <div class="card-header">
      <h4 class="card-title">Tax Breakdown</h4>
      <span class="effective-rate">{{ formatPercent(breakdown.effective_rate || 0) }} effective rate</span>
    </div>

    <!-- Income Sources List -->
    <div class="income-sources">
      <div
        v-for="(source, index) in breakdown.sources || []"
        :key="index"
        :class="['source-item', { 'taxable': source.tax_treatment === 'taxable' }]"
      >
        <div class="source-row">
          <div class="source-info">
            <span :class="['source-badge', getBadgeClass(source)]">
              {{ getSourceTypeLabel(source.source_type) }}
            </span>
            <span class="source-name">{{ source.name }}</span>
          </div>
          <div class="source-amount">
            <span class="amount">{{ formatCurrency(source.amount) }}</span>
            <span :class="['tax-status', { 'tax-free': source.tax === 0, 'has-tax': source.tax > 0 }]">
              {{ source.tax === 0 ? 'Tax-free' : `-${formatCurrency(source.tax)} tax` }}
            </span>
          </div>
        </div>

        <!-- Band breakdown for taxable income -->
        <div v-if="source.band_breakdown && source.tax_treatment === 'taxable'" class="band-breakdown">
          <div v-if="source.band_breakdown.personal_allowance > 0" class="band-row">
            <span class="band-label">
              <span class="band-dot pa"></span>
              Personal Allowance (0%)
            </span>
            <span class="band-amount">{{ formatCurrency(source.band_breakdown.personal_allowance) }}</span>
            <span class="band-tax tax-free">£0</span>
          </div>
          <div v-if="source.band_breakdown.basic_rate > 0" class="band-row">
            <span class="band-label">
              <span class="band-dot basic"></span>
              Basic Rate (20%)
            </span>
            <span class="band-amount">{{ formatCurrency(source.band_breakdown.basic_rate) }}</span>
            <span class="band-tax">-{{ formatCurrency(source.band_breakdown.basic_rate * 0.2) }}</span>
          </div>
          <div v-if="source.band_breakdown.higher_rate > 0" class="band-row">
            <span class="band-label">
              <span class="band-dot higher"></span>
              Higher Rate (40%)
            </span>
            <span class="band-amount">{{ formatCurrency(source.band_breakdown.higher_rate) }}</span>
            <span class="band-tax">-{{ formatCurrency(source.band_breakdown.higher_rate * 0.4) }}</span>
          </div>
          <div v-if="source.band_breakdown.additional_rate > 0" class="band-row">
            <span class="band-label">
              <span class="band-dot additional"></span>
              Additional Rate (45%)
            </span>
            <span class="band-amount">{{ formatCurrency(source.band_breakdown.additional_rate) }}</span>
            <span class="band-tax">-{{ formatCurrency(source.band_breakdown.additional_rate * 0.45) }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Summary -->
    <div class="summary-section">
      <div class="summary-row">
        <span class="summary-label">Gross Income</span>
        <span class="summary-value">{{ formatCurrency(breakdown.gross_income || 0) }}</span>
      </div>
      <div class="summary-row">
        <span class="summary-label">Total Tax</span>
        <span class="summary-value tax-amount">-{{ formatCurrency(breakdown.total_tax || 0) }}</span>
      </div>
      <div class="summary-row total">
        <span class="summary-label">Net Income</span>
        <span class="summary-value">{{ formatCurrency(breakdown.net_income || 0) }}</span>
      </div>
    </div>

    <!-- Tax Band Usage -->
    <div v-if="breakdown.band_usage" class="band-usage">
      <h5 class="section-title">Tax Band Usage</h5>

      <!-- Personal Allowance -->
      <div class="band-item">
        <div class="band-header">
          <span class="band-name">Personal Allowance</span>
          <span class="band-rate">0%</span>
        </div>
        <div class="band-bar">
          <div
            class="band-fill pa"
            :style="{ width: getUsagePercent(breakdown.band_usage.personal_allowance) }"
          ></div>
        </div>
        <div class="band-values">
          <span>{{ formatCurrency(breakdown.band_usage.personal_allowance?.used || 0) }} used</span>
          <span>{{ formatCurrency(breakdown.band_usage.personal_allowance?.remaining || 0) }} remaining</span>
        </div>
      </div>

      <!-- Basic Rate -->
      <div class="band-item">
        <div class="band-header">
          <span class="band-name">Basic Rate</span>
          <span class="band-rate">20%</span>
        </div>
        <div class="band-bar">
          <div
            class="band-fill basic"
            :style="{ width: getUsagePercent(breakdown.band_usage.basic_rate) }"
          ></div>
        </div>
        <div class="band-values">
          <span>{{ formatCurrency(breakdown.band_usage.basic_rate?.used || 0) }} used</span>
          <span>{{ formatCurrency(breakdown.band_usage.basic_rate?.remaining || 0) }} remaining</span>
        </div>
      </div>

      <!-- Higher Rate (if used) -->
      <div v-if="breakdown.band_usage.higher_rate?.used > 0" class="band-item">
        <div class="band-header">
          <span class="band-name">Higher Rate</span>
          <span class="band-rate">40%</span>
        </div>
        <div class="band-bar">
          <div
            class="band-fill higher"
            :style="{ width: getUsagePercent(breakdown.band_usage.higher_rate) }"
          ></div>
        </div>
        <div class="band-values">
          <span>{{ formatCurrency(breakdown.band_usage.higher_rate?.used || 0) }} used</span>
          <span>{{ formatCurrency(breakdown.band_usage.higher_rate?.remaining || 0) }} remaining</span>
        </div>
      </div>

      <!-- Additional Rate (if used) -->
      <div v-if="breakdown.band_usage.additional_rate?.used > 0" class="band-item">
        <div class="band-header">
          <span class="band-name">Additional Rate</span>
          <span class="band-rate">45%</span>
        </div>
        <div class="band-bar">
          <div
            class="band-fill additional"
            :style="{ width: '100%' }"
          ></div>
        </div>
        <div class="band-values">
          <span>{{ formatCurrency(breakdown.band_usage.additional_rate?.used || 0) }} used</span>
          <span>No upper limit</span>
        </div>
      </div>
    </div>

    <!-- Tax Optimisation Tips -->
    <div v-if="tips.length > 0" class="optimisation-tips">
      <h5 class="section-title">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="tip-icon">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 18v-5.25m0 0a6.01 6.01 0 001.5-.189m-1.5.189a6.01 6.01 0 01-1.5-.189m3.75 7.478a12.06 12.06 0 01-4.5 0m3.75 2.383a14.406 14.406 0 01-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 10-7.517 0c.85.493 1.509 1.333 1.509 2.316V18" />
        </svg>
        Optimisation Tips
      </h5>
      <ul class="tips-list">
        <li v-for="(tip, index) in tips" :key="index">{{ tip }}</li>
      </ul>
    </div>
  </div>
</template>

<script>
export default {
  name: 'TaxBreakdownCard',

  props: {
    breakdown: {
      type: Object,
      required: true,
    },
  },

  computed: {
    tips() {
      const tips = [];
      const bandUsage = this.breakdown.band_usage;

      if (!bandUsage) return tips;

      // Check if personal allowance is not fully used
      if (bandUsage.personal_allowance?.remaining > 0) {
        tips.push(`You have £${this.formatNumber(bandUsage.personal_allowance.remaining)} of unused Personal Allowance. Consider drawing more pension income tax-free.`);
      }

      // Check if in higher rate but basic rate has room
      if (bandUsage.higher_rate?.used > 0 && bandUsage.basic_rate?.remaining > 0) {
        tips.push('Consider spreading income across tax years to stay within the basic rate band.');
      }

      return tips;
    },
  },

  methods: {
    getSourceTypeLabel(sourceType) {
      const labels = {
        dc_pension_pcls: 'PCLS',
        dc_pension_drawdown: 'Pension',
        db_pension: 'DB Pension',
        state_pension: 'State Pension',
        isa: 'ISA',
        gia: 'GIA',
        bond: 'Bond',
        savings: 'Savings',
      };
      return labels[sourceType] || 'Income';
    },

    getBadgeClass(source) {
      if (source.source_type?.includes('pcls')) return 'badge-pcls';
      if (source.source_type?.includes('pension')) return 'badge-pension';
      if (source.source_type === 'isa') return 'badge-isa';
      if (source.source_type === 'gia') return 'badge-gia';
      if (source.source_type === 'savings') return 'badge-savings';
      return 'badge-default';
    },

    formatCurrency(value) {
      if (value === null || value === undefined) return '£0';
      return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: 'GBP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
      }).format(value);
    },

    formatNumber(value) {
      return new Intl.NumberFormat('en-GB').format(value || 0);
    },

    formatPercent(value) {
      return (value * 100).toFixed(1) + '%';
    },

    getUsagePercent(band) {
      if (!band) return '0%';
      const total = (band.used || 0) + (band.remaining || 0);
      if (total === 0) return '0%';
      return ((band.used || 0) / total * 100) + '%';
    },
  },
};
</script>

<style scoped>
.tax-breakdown-card {
  background: white;
  border-radius: 12px;
  padding: 24px;
  border: 1px solid #e5e7eb;
  margin-bottom: 24px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.card-title {
  font-size: 18px;
  font-weight: 600;
  color: #111827;
  margin: 0;
}

.effective-rate {
  font-size: 14px;
  font-weight: 600;
  color: #6b7280;
  background: #f3f4f6;
  padding: 4px 12px;
  border-radius: 20px;
}

/* Income Sources */
.income-sources {
  margin-bottom: 20px;
}

.source-item {
  background: #f9fafb;
  border-radius: 8px;
  padding: 12px 16px;
  margin-bottom: 8px;
  border: 1px solid #e5e7eb;
}

.source-item:last-child {
  margin-bottom: 0;
}

.source-item.taxable {
  background: #fefce8;
  border-color: #fef08a;
}

.source-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.source-info {
  display: flex;
  align-items: center;
  gap: 10px;
  flex: 1;
}

.source-badge {
  display: inline-block;
  padding: 2px 8px;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  border-radius: 4px;
  letter-spacing: 0.5px;
  flex-shrink: 0;
}

.badge-pcls {
  background: #d1fae5;
  color: #065f46;
}

.badge-pension {
  background: #dbeafe;
  color: #1e40af;
}

.badge-isa {
  background: #fef3c7;
  color: #92400e;
}

.badge-gia {
  background: #e9d5ff;
  color: #6b21a8;
}

.badge-savings {
  background: #e0e7ff;
  color: #3730a3;
}

.badge-default {
  background: #f3f4f6;
  color: #374151;
}

.source-name {
  font-size: 14px;
  font-weight: 500;
  color: #374151;
}

.source-amount {
  text-align: right;
}

.source-amount .amount {
  display: block;
  font-size: 15px;
  font-weight: 600;
  color: #111827;
}

.source-amount .tax-status {
  display: block;
  font-size: 12px;
  margin-top: 2px;
}

.source-amount .tax-status.tax-free {
  color: #059669;
}

.source-amount .tax-status.has-tax {
  color: #dc2626;
}

/* Band breakdown within source */
.band-breakdown {
  margin-top: 12px;
  padding-top: 12px;
  border-top: 1px dashed #e5e7eb;
}

.band-row {
  display: flex;
  align-items: center;
  padding: 6px 0;
  font-size: 13px;
}

.band-label {
  display: flex;
  align-items: center;
  gap: 8px;
  flex: 1;
  color: #6b7280;
}

.band-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
}

.band-dot.pa {
  background: #10b981;
}

.band-dot.basic {
  background: #f59e0b;
}

.band-dot.higher {
  background: #f97316;
}

.band-dot.additional {
  background: #ef4444;
}

.band-amount {
  width: 100px;
  text-align: right;
  font-weight: 500;
  color: #374151;
}

.band-tax {
  width: 80px;
  text-align: right;
  font-weight: 600;
  color: #dc2626;
}

.band-tax.tax-free {
  color: #059669;
}

/* Summary Section */
.summary-section {
  background: #f3f4f6;
  border-radius: 8px;
  padding: 16px;
  margin-bottom: 24px;
}

.summary-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 0;
}

.summary-row:not(:last-child) {
  border-bottom: 1px solid #e5e7eb;
}

.summary-row.total {
  border-top: 2px solid #d1d5db;
  margin-top: 8px;
  padding-top: 16px;
}

.summary-label {
  font-size: 14px;
  color: #6b7280;
}

.summary-value {
  font-size: 14px;
  font-weight: 600;
  color: #111827;
}

.summary-value.tax-amount {
  color: #dc2626;
}

.summary-row.total .summary-label,
.summary-row.total .summary-value {
  font-size: 16px;
  font-weight: 700;
}

/* Band Usage */
.band-usage {
  margin-bottom: 24px;
}

.section-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  font-weight: 600;
  color: #374151;
  margin: 0 0 16px 0;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.band-item {
  margin-bottom: 16px;
}

.band-item:last-child {
  margin-bottom: 0;
}

.band-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 6px;
}

.band-name {
  font-size: 13px;
  font-weight: 500;
  color: #374151;
}

.band-rate {
  font-size: 12px;
  font-weight: 700;
  color: #6b7280;
  background: #f3f4f6;
  padding: 2px 8px;
  border-radius: 4px;
}

.band-bar {
  height: 8px;
  background: #e5e7eb;
  border-radius: 4px;
  overflow: hidden;
  margin-bottom: 4px;
}

.band-fill {
  height: 100%;
  border-radius: 4px;
  transition: width 0.3s ease;
}

.band-fill.pa {
  background: linear-gradient(90deg, #10b981, #34d399);
}

.band-fill.basic {
  background: linear-gradient(90deg, #f59e0b, #fbbf24);
}

.band-fill.higher {
  background: linear-gradient(90deg, #f97316, #fb923c);
}

.band-fill.additional {
  background: linear-gradient(90deg, #ef4444, #f87171);
}

.band-values {
  display: flex;
  justify-content: space-between;
  font-size: 11px;
  color: #9ca3af;
}

/* Optimisation Tips */
.optimisation-tips {
  background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
  border-radius: 8px;
  padding: 16px;
  border: 1px solid #bfdbfe;
}

.tip-icon {
  width: 16px;
  height: 16px;
  color: #3b82f6;
}

.optimisation-tips .section-title {
  color: #1e40af;
  margin-bottom: 12px;
}

.tips-list {
  margin: 0;
  padding: 0 0 0 20px;
}

.tips-list li {
  font-size: 13px;
  color: #1e40af;
  margin-bottom: 8px;
  line-height: 1.5;
}

.tips-list li:last-child {
  margin-bottom: 0;
}

@media (max-width: 640px) {
  .card-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
  }

  .source-row {
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
  }

  .source-amount {
    text-align: left;
  }

  .band-row {
    flex-wrap: wrap;
  }

  .band-amount,
  .band-tax {
    width: auto;
  }

  .band-values {
    flex-direction: column;
    gap: 2px;
  }
}
</style>
