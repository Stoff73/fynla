<template>
  <div class="nrb-rnrb-tracker">
    <div class="tracker-header">
      <h3>IHT Allowance Tracker</h3>
      <p class="subtitle">2025/26 Tax Year</p>
    </div>

    <!-- Nil Rate Band (NRB) -->
    <div class="allowance-section">
      <div class="allowance-header">
        <div class="allowance-title">
          <h4>Nil Rate Band (NRB)</h4>
          <span class="info-icon" title="Standard IHT allowance for all estates">
            <i class="fas fa-info-circle"></i>
          </span>
        </div>
        <div class="allowance-values">
          <span class="used">{{ formatCurrency(nrbUsed) }}</span>
          <span class="separator">/</span>
          <span class="total">{{ formatCurrency(nrbTotal) }}</span>
        </div>
      </div>
      <div class="progress-bar-container">
        <div
          class="progress-bar"
          :class="nrbProgressClass"
          :style="{ width: nrbPercentage + '%' }"
        >
          <span class="progress-label" v-if="nrbPercentage > 15">
            {{ nrbPercentage.toFixed(0) }}%
          </span>
        </div>
      </div>
      <div class="allowance-status">
        <span class="remaining">
          {{ formatCurrency(nrbRemaining) }} remaining
        </span>
        <span v-if="hasSpouseTransfer" class="badge badge-info">
          <i class="fas fa-users"></i>
          Spouse transfer: {{ formatCurrency(spouseNrbTransfer) }}
        </span>
      </div>
    </div>

    <!-- Residence Nil Rate Band (RNRB) -->
    <div class="allowance-section">
      <div class="allowance-header">
        <div class="allowance-title">
          <h4>Residence Nil Rate Band (RNRB)</h4>
          <span class="info-icon" title="Additional allowance for main residence passed to direct descendants">
            <i class="fas fa-info-circle"></i>
          </span>
        </div>
        <div class="allowance-values">
          <span class="used">{{ formatCurrency(rnrbUsed) }}</span>
          <span class="separator">/</span>
          <span class="total">{{ formatCurrency(rnrbTotal) }}</span>
        </div>
      </div>
      <div class="progress-bar-container">
        <div
          class="progress-bar"
          :class="rnrbProgressClass"
          :style="{ width: rnrbPercentage + '%' }"
        >
          <span class="progress-label" v-if="rnrbPercentage > 15">
            {{ rnrbPercentage.toFixed(0) }}%
          </span>
        </div>
      </div>
      <div class="allowance-status">
        <span class="remaining">
          {{ formatCurrency(rnrbRemaining) }} remaining
        </span>
        <span v-if="rnrbTapered" class="badge badge-warning">
          <i class="fas fa-exclamation-triangle"></i>
          Tapered (estate > £2m)
        </span>
        <span v-if="!isRnrbEligible" class="badge badge-error">
          <i class="fas fa-times-circle"></i>
          Not eligible
        </span>
      </div>
    </div>

    <!-- Combined Allowance Summary -->
    <div class="combined-summary">
      <div class="summary-row">
        <span class="label">Total Combined Allowance:</span>
        <span class="value total-value">{{ formatCurrency(totalAllowance) }}</span>
      </div>
      <div class="summary-row">
        <span class="label">Total Used:</span>
        <span class="value used-value">{{ formatCurrency(totalUsed) }}</span>
      </div>
      <div class="summary-row highlight">
        <span class="label">Taxable Estate Above Allowances:</span>
        <span class="value taxable-value" :class="taxableAmountClass">
          {{ formatCurrency(taxableAmount) }}
        </span>
      </div>
    </div>

    <!-- Eligibility Notes -->
    <div v-if="showEligibilityNotes" class="eligibility-notes">
      <div class="note-header">
        <i class="fas fa-lightbulb"></i>
        <span>Eligibility Notes</span>
      </div>
      <ul>
        <li v-if="!isRnrbEligible">
          RNRB only applies when leaving main residence to direct descendants (children, grandchildren)
        </li>
        <li v-if="rnrbTapered">
          RNRB is reduced by £1 for every £2 estate value exceeds £2 million
        </li>
        <li v-if="hasSpouseTransfer">
          Any unused NRB from deceased spouse can be transferred
        </li>
        <li v-if="canClaimRnrbTransfer">
          Unused RNRB from deceased spouse may be transferable
        </li>
      </ul>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'NRBRNRBTracker',
  mixins: [currencyMixin],

  props: {
    estateValue: {
      type: Number,
      required: true,
      default: 0,
    },
    nrbUsed: {
      type: Number,
      default: 0,
    },
    rnrbUsed: {
      type: Number,
      default: 0,
    },
    hasMainResidence: {
      type: Boolean,
      default: false,
    },
    hasDirectDescendants: {
      type: Boolean,
      default: false,
    },
    hasSpouseTransfer: {
      type: Boolean,
      default: false,
    },
    spouseNrbTransfer: {
      type: Number,
      default: 0,
    },
    canClaimRnrbTransfer: {
      type: Boolean,
      default: false,
    },
  },

  data() {
    return {
      // 2025/26 UK IHT allowances
      nrbStandard: 325000,
      rnrbStandard: 175000,
      rnrbTaperingThreshold: 2000000,
    };
  },

  computed: {
    nrbTotal() {
      return this.nrbStandard + (this.hasSpouseTransfer ? this.spouseNrbTransfer : 0);
    },

    nrbRemaining() {
      return Math.max(0, this.nrbTotal - this.nrbUsed);
    },

    nrbPercentage() {
      if (this.nrbTotal === 0) return 0;
      return Math.min(100, (this.nrbUsed / this.nrbTotal) * 100);
    },

    nrbProgressClass() {
      if (this.nrbPercentage >= 90) return 'progress-critical';
      if (this.nrbPercentage >= 70) return 'progress-warning';
      return 'progress-good';
    },

    isRnrbEligible() {
      return this.hasMainResidence && this.hasDirectDescendants;
    },

    rnrbTapered() {
      return this.estateValue > this.rnrbTaperingThreshold;
    },

    rnrbTotal() {
      if (!this.isRnrbEligible) return 0;

      if (this.rnrbTapered) {
        const excess = this.estateValue - this.rnrbTaperingThreshold;
        const reduction = excess / 2;
        return Math.max(0, this.rnrbStandard - reduction);
      }

      return this.rnrbStandard;
    },

    rnrbRemaining() {
      return Math.max(0, this.rnrbTotal - this.rnrbUsed);
    },

    rnrbPercentage() {
      if (this.rnrbTotal === 0) return 0;
      return Math.min(100, (this.rnrbUsed / this.rnrbTotal) * 100);
    },

    rnrbProgressClass() {
      if (!this.isRnrbEligible) return 'progress-disabled';
      if (this.rnrbPercentage >= 90) return 'progress-critical';
      if (this.rnrbPercentage >= 70) return 'progress-warning';
      return 'progress-good';
    },

    totalAllowance() {
      return this.nrbTotal + this.rnrbTotal;
    },

    totalUsed() {
      return this.nrbUsed + this.rnrbUsed;
    },

    taxableAmount() {
      return Math.max(0, this.estateValue - this.totalAllowance);
    },

    taxableAmountClass() {
      if (this.taxableAmount === 0) return 'text-green-600';
      if (this.taxableAmount > 500000) return 'text-red-600';
      return 'text-amber-600';
    },

    showEligibilityNotes() {
      return !this.isRnrbEligible || this.rnrbTapered || this.hasSpouseTransfer || this.canClaimRnrbTransfer;
    },
  },

};
</script>

<style scoped>
.nrb-rnrb-tracker {
  background: white;
  border-radius: 8px;
  padding: 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.tracker-header {
  text-align: center;
  margin-bottom: 24px;
  padding-bottom: 16px;
  border-bottom: 2px solid #e5e7eb;
}

.tracker-header h3 {
  font-size: 20px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 4px 0;
}

.subtitle {
  font-size: 13px;
  color: #6b7280;
  margin: 0;
}

.allowance-section {
  margin-bottom: 28px;
}

.allowance-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}

.allowance-title {
  display: flex;
  align-items: center;
  gap: 8px;
}

.allowance-title h4 {
  font-size: 15px;
  font-weight: 600;
  color: #374151;
  margin: 0;
}

.info-icon {
  color: #9ca3af;
  cursor: help;
  font-size: 14px;
}

.allowance-values {
  font-size: 14px;
  font-weight: 600;
}

.used {
  color: #ef4444;
}

.separator {
  color: #9ca3af;
  margin: 0 4px;
}

.total {
  color: #6b7280;
}

.progress-bar-container {
  height: 24px;
  background-color: #f3f4f6;
  border-radius: 12px;
  overflow: hidden;
  position: relative;
}

.progress-bar {
  height: 100%;
  transition: width 0.3s ease;
  display: flex;
  align-items: center;
  justify-content: flex-end;
  padding-right: 8px;
  border-radius: 12px;
}

.progress-good {
  background: linear-gradient(90deg, #10b981, #059669);
}

.progress-warning {
  background: linear-gradient(90deg, #f59e0b, #d97706);
}

.progress-critical {
  background: linear-gradient(90deg, #ef4444, #dc2626);
}

.progress-disabled {
  background: #d1d5db;
}

.progress-label {
  color: white;
  font-size: 12px;
  font-weight: 600;
}

.allowance-status {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 8px;
  font-size: 13px;
}

.remaining {
  color: #6b7280;
  font-weight: 500;
}

.badge {
  padding: 4px 10px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 500;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.badge-info {
  background-color: #dbeafe;
  color: #1e40af;
}

.badge-warning {
  background-color: #fef3c7;
  color: #92400e;
}

.badge-error {
  background-color: #fee2e2;
  color: #991b1b;
}

.combined-summary {
  background-color: #f9fafb;
  border-radius: 8px;
  padding: 16px;
  margin-top: 24px;
}

.summary-row {
  display: flex;
  justify-content: space-between;
  padding: 8px 0;
  font-size: 14px;
}

.summary-row.highlight {
  margin-top: 8px;
  padding-top: 12px;
  border-top: 1px solid #e5e7eb;
}

.summary-row .label {
  color: #6b7280;
  font-weight: 500;
}

.summary-row .value {
  font-weight: 600;
}

.total-value {
  color: #1f2937;
  font-size: 15px;
}

.used-value {
  color: #ef4444;
}

.taxable-value {
  font-size: 16px;
}

.eligibility-notes {
  margin-top: 20px;
  padding: 16px;
  background-color: #fffbeb;
  border-left: 4px solid #f59e0b;
  border-radius: 4px;
}

.note-header {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 600;
  color: #92400e;
  margin-bottom: 12px;
  font-size: 14px;
}

.eligibility-notes ul {
  margin: 0;
  padding-left: 20px;
  list-style-type: disc;
}

.eligibility-notes li {
  font-size: 13px;
  color: #78350f;
  margin-bottom: 6px;
  line-height: 1.5;
}

.eligibility-notes li:last-child {
  margin-bottom: 0;
}
</style>
