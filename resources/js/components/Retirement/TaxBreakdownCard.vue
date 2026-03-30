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
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'TaxBreakdownCard',

  mixins: [currencyMixin],

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
        dc_pension_pcls: 'Pension Commencement Lump Sum',
        dc_pension_drawdown: 'Pension',
        db_pension: 'Defined Benefit Pension',
        state_pension: 'State Pension',
        isa: 'ISA',
        gia: 'General Investment Account',
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
  @apply border border-light-gray;
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
  @apply text-horizon-500;
  margin: 0;
}

.effective-rate {
  font-size: 14px;
  font-weight: 600;
  @apply text-neutral-500;
  @apply bg-savannah-100;
  padding: 4px 12px;
  border-radius: 20px;
}

/* Income Sources */
.income-sources {
  margin-bottom: 20px;
}

.source-item {
  @apply bg-savannah-100;
  border-radius: 8px;
  padding: 12px 16px;
  margin-bottom: 8px;
  @apply border border-light-gray;
}

.source-item:last-child {
  margin-bottom: 0;
}

.source-item.taxable {
  @apply bg-violet-50;
  @apply border-violet-200;
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
  @apply bg-spring-100;
  @apply text-spring-800;
}

.badge-pension {
  @apply bg-violet-100;
  @apply text-violet-800;
}

.badge-isa {
  @apply bg-violet-100;
  @apply text-violet-800;
}

.badge-gia {
  @apply bg-purple-100;
  @apply text-purple-800;
}

.badge-savings {
  @apply bg-violet-100;
  @apply text-violet-800;
}

.badge-default {
  @apply bg-savannah-100;
  @apply text-neutral-500;
}

.source-name {
  font-size: 14px;
  font-weight: 500;
  @apply text-neutral-500;
}

.source-amount {
  text-align: right;
}

.source-amount .amount {
  display: block;
  font-size: 15px;
  font-weight: 600;
  @apply text-horizon-500;
}

.source-amount .tax-status {
  display: block;
  font-size: 12px;
  margin-top: 2px;
}

.source-amount .tax-status.tax-free {
  @apply text-spring-600;
}

.source-amount .tax-status.has-tax {
  @apply text-raspberry-600;
}

/* Band breakdown within source */
.band-breakdown {
  margin-top: 12px;
  padding-top: 12px;
  @apply border-t border-dashed border-light-gray;
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
  @apply text-neutral-500;
}

.band-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
}

.band-dot.pa {
  @apply bg-spring-500;
}

.band-dot.basic {
  @apply bg-violet-500;
}

.band-dot.higher {
  @apply bg-violet-500;
}

.band-dot.additional {
  @apply bg-raspberry-500;
}

.band-amount {
  width: 100px;
  text-align: right;
  font-weight: 500;
  @apply text-neutral-500;
}

.band-tax {
  width: 80px;
  text-align: right;
  font-weight: 600;
  @apply text-raspberry-600;
}

.band-tax.tax-free {
  @apply text-spring-600;
}

/* Summary Section */
.summary-section {
  @apply bg-savannah-100;
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
  @apply border-b border-light-gray;
}

.summary-row.total {
  @apply border-t-2 border-horizon-300;
  margin-top: 8px;
  padding-top: 16px;
}

.summary-label {
  font-size: 14px;
  @apply text-neutral-500;
}

.summary-value {
  font-size: 14px;
  font-weight: 600;
  @apply text-horizon-500;
}

.summary-value.tax-amount {
  @apply text-raspberry-600;
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
  @apply text-neutral-500;
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
  @apply text-neutral-500;
}

.band-rate {
  font-size: 12px;
  font-weight: 700;
  @apply text-neutral-500;
  @apply bg-savannah-100;
  padding: 2px 8px;
  border-radius: 4px;
}

.band-bar {
  height: 8px;
  @apply bg-savannah-200;
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
  @apply bg-gradient-to-r from-green-500 to-green-400;
}

.band-fill.basic {
  @apply bg-gradient-to-r from-blue-500 to-blue-400;
}

.band-fill.higher {
  @apply bg-gradient-to-r from-blue-500 to-blue-400;
}

.band-fill.additional {
  @apply bg-gradient-to-r from-red-500 to-red-400;
}

.band-values {
  display: flex;
  justify-content: space-between;
  font-size: 11px;
  @apply text-horizon-400;
}

/* Optimisation Tips */
.optimisation-tips {
  @apply bg-gradient-to-br from-blue-50 to-blue-100;
  border-radius: 8px;
  padding: 16px;
  @apply border border-violet-200;
}

.tip-icon {
  width: 16px;
  height: 16px;
  @apply text-raspberry-500;
}

.optimisation-tips .section-title {
  @apply text-violet-800;
  margin-bottom: 12px;
}

.tips-list {
  margin: 0;
  padding: 0 0 0 20px;
}

.tips-list li {
  font-size: 13px;
  @apply text-violet-800;
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
