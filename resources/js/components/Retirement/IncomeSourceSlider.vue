<template>
  <div class="income-source-slider">
    <div class="source-header">
      <div class="source-info">
        <div class="source-name-row">
          <span :class="['source-badge', typeBadgeClass]">{{ sourceTypeLabel }}</span>
          <h5 class="source-name">{{ allocation.name || accountName }}</h5>
        </div>
        <p class="source-balance">Available: {{ formatCurrency(maxAmount) }}</p>
      </div>
      <div class="tax-badge-container">
        <span :class="['tax-badge', taxBadgeClass]">
          {{ taxRateDisplay }}
        </span>
        <span class="tax-label">{{ taxTreatmentLabel }}</span>
      </div>
    </div>

    <div class="slider-section">
      <div class="slider-header">
        <span class="amount-label">Annual withdrawal</span>
        <span class="amount-value">{{ formatCurrency(localAmount) }}</span>
      </div>
      <div class="slider-wrapper">
        <input
          v-model.number="localAmount"
          type="range"
          class="slider"
          :min="0"
          :max="maxAmount"
          :step="sliderStep"
          @input="onSliderInput"
        />
        <div class="slider-track-bg"></div>
        <div class="slider-track-fill" :style="{ width: sliderFillWidth }"></div>
      </div>
      <div class="slider-labels">
        <span>£0</span>
        <span>{{ formatCurrency(maxAmount) }}</span>
      </div>
    </div>

    <div v-if="taxAmount > 0" class="tax-info">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="tax-icon">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <span>Est. tax on this source: {{ formatCurrency(taxAmount) }}</span>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'IncomeSourceSlider',

  mixins: [currencyMixin],

  props: {
    allocation: {
      type: Object,
      required: true,
    },
    account: {
      type: Object,
      default: null,
    },
  },

  emits: ['update'],

  data() {
    return {
      localAmount: this.allocation.annual_amount || 0,
      updateTimeout: null,
    };
  },

  computed: {
    sourceType() {
      return this.allocation.source_type || '';
    },

    sourceTypeLabel() {
      const labels = {
        dc_pension_pcls: 'PCLS',
        dc_pension_drawdown: 'Pension',
        db_pension: 'DB Pension',
        isa: 'ISA',
        gia: 'GIA',
        bond: 'Bond',
        savings: 'Savings',
      };
      return labels[this.sourceType] || 'Income';
    },

    typeBadgeClass() {
      if (this.sourceType.includes('pcls')) return 'badge-pcls';
      if (this.sourceType.includes('pension')) return 'badge-pension';
      if (this.sourceType === 'isa') return 'badge-isa';
      if (this.sourceType === 'gia') return 'badge-gia';
      if (this.sourceType === 'bond') return 'badge-bond';
      return 'badge-default';
    },

    accountName() {
      return this.account?.name || this.allocation.name || 'Income Source';
    },

    maxAmount() {
      // For PCLS, use the pcls_available from account
      if (this.sourceType === 'dc_pension_pcls') {
        return this.account?.pcls_available || this.allocation.max_amount || 0;
      }
      // For other sources, use the account value or allocation max
      return this.account?.value || this.allocation.max_amount || 50000;
    },

    sliderStep() {
      if (this.maxAmount > 100000) return 1000;
      if (this.maxAmount > 10000) return 500;
      return 100;
    },

    sliderFillWidth() {
      if (this.maxAmount === 0) return '0%';
      const percentage = (this.localAmount / this.maxAmount) * 100;
      return `${Math.min(100, percentage)}%`;
    },

    taxRate() {
      return this.allocation.tax_rate;
    },

    taxTreatment() {
      return this.allocation.tax_treatment || '';
    },

    isTaxable() {
      // Income is taxable if tax_treatment is 'taxable' (regardless of tax_rate being null)
      return this.taxTreatment === 'taxable';
    },

    taxRateDisplay() {
      // For taxable income with unknown rate (null), show indicator
      if (this.isTaxable && (this.taxRate === null || this.taxRate === undefined)) {
        return 'Var';
      }
      const rate = this.taxRate || 0;
      return `${Math.round(rate * 100)}%`;
    },

    taxBadgeClass() {
      // Taxable income with unknown/marginal rate
      if (this.isTaxable && (this.taxRate === null || this.taxRate === undefined)) {
        return 'tax-marginal';
      }
      const rate = this.taxRate || 0;
      if (rate === 0 && !this.isTaxable) return 'tax-free';
      if (rate <= 0.2) return 'tax-basic';
      if (rate <= 0.4) return 'tax-higher';
      return 'tax-additional';
    },

    taxTreatmentLabel() {
      const labels = {
        tax_free: 'Tax-free',
        pcls: 'Tax-free lump sum',
        personal_allowance: 'Personal Allowance',
        basic_rate: 'Basic Rate',
        higher_rate: 'Higher Rate',
        additional_rate: 'Additional Rate',
      };
      if (labels[this.taxTreatment]) return labels[this.taxTreatment];
      // For taxable income, show "Taxable" even if rate is 0/null
      if (this.isTaxable) return 'Taxable (marginal rate)';
      return 'Tax-free';
    },

    taxAmount() {
      // Can't calculate exact tax for marginal rate sources
      if (this.taxRate === null || this.taxRate === undefined) return 0;
      return Math.round(this.localAmount * this.taxRate);
    },
  },

  watch: {
    'allocation.annual_amount': {
      handler(newVal) {
        this.localAmount = newVal || 0;
      },
      immediate: true,
    },
  },

  beforeUnmount() {
    if (this.updateTimeout) {
      clearTimeout(this.updateTimeout);
    }
  },

  methods: {
    onSliderInput() {
      // Clear existing timeout
      if (this.updateTimeout) {
        clearTimeout(this.updateTimeout);
      }

      // Debounce the update emission
      this.updateTimeout = setTimeout(() => {
        this.$emit('update', {
          sourceType: this.allocation.source_type,
          sourceId: this.allocation.source_id,
          amount: this.localAmount,
        });
      }, 150);
    },

  },
};
</script>

<style scoped>
.income-source-slider {
  @apply bg-gray-50;
  border-radius: 12px;
  padding: 20px;
  @apply border border-gray-200;
  transition: border-color 0.2s, box-shadow 0.2s;
}

.income-source-slider:hover {
  @apply border-gray-300;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.source-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 16px;
}

.source-info {
  flex: 1;
}

.source-name-row {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 4px;
}

.source-badge {
  display: inline-block;
  padding: 2px 8px;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  border-radius: 4px;
  letter-spacing: 0.5px;
}

.badge-pcls {
  @apply bg-green-100;
  @apply text-green-800;
}

.badge-pension {
  @apply bg-blue-100;
  @apply text-blue-800;
}

.badge-isa {
  @apply bg-orange-100;
  @apply text-orange-800;
}

.badge-gia {
  @apply bg-purple-100;
  @apply text-purple-800;
}

.badge-bond {
  @apply bg-pink-100;
  @apply text-pink-800;
}

.badge-default {
  @apply bg-gray-100;
  @apply text-gray-700;
}

.source-name {
  font-size: 16px;
  font-weight: 600;
  @apply text-gray-900;
  margin: 0;
}

.source-balance {
  font-size: 13px;
  @apply text-gray-500;
  margin: 0;
}

.tax-badge-container {
  text-align: right;
}

.tax-badge {
  display: inline-block;
  padding: 4px 12px;
  font-size: 14px;
  font-weight: 700;
  border-radius: 20px;
  margin-bottom: 4px;
}

.tax-badge.tax-free {
  @apply bg-green-100;
  @apply text-green-800;
}

.tax-badge.tax-basic {
  @apply bg-orange-100;
  @apply text-orange-800;
}

.tax-badge.tax-higher {
  @apply bg-orange-200;
  @apply text-orange-800;
}

.tax-badge.tax-additional {
  @apply bg-red-200;
  @apply text-red-800;
}

.tax-badge.tax-marginal {
  @apply bg-indigo-100;
  @apply text-indigo-800;
}

.tax-label {
  display: block;
  font-size: 11px;
  @apply text-gray-500;
}

/* Slider Section */
.slider-section {
  margin-bottom: 12px;
}

.slider-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.amount-label {
  font-size: 12px;
  @apply text-gray-500;
}

.amount-value {
  font-size: 18px;
  font-weight: 700;
  @apply text-primary-500;
}

.slider-wrapper {
  position: relative;
  height: 24px;
  margin-bottom: 6px;
}

.slider {
  width: 100%;
  height: 24px;
  -webkit-appearance: none;
  appearance: none;
  background: transparent;
  position: relative;
  z-index: 2;
  cursor: pointer;
}

.slider::-webkit-slider-thumb {
  -webkit-appearance: none;
  appearance: none;
  width: 24px;
  height: 24px;
  @apply bg-primary-500;
  border-radius: 50%;
  cursor: pointer;
  border: 3px solid white;
  @apply shadow-md;
  transition: transform 0.1s, box-shadow 0.1s;
}

.slider::-webkit-slider-thumb:hover {
  transform: scale(1.1);
  @apply shadow-lg;
}

.slider::-webkit-slider-thumb:active {
  transform: scale(0.95);
}

.slider::-moz-range-thumb {
  width: 24px;
  height: 24px;
  @apply bg-primary-500;
  border-radius: 50%;
  cursor: pointer;
  border: 3px solid white;
  @apply shadow-md;
}

.slider-track-bg {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  left: 0;
  right: 0;
  height: 8px;
  @apply bg-gray-200;
  border-radius: 4px;
  z-index: 0;
}

.slider-track-fill {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  left: 0;
  height: 8px;
  @apply bg-gradient-to-r from-primary-500 to-primary-400;
  border-radius: 4px;
  z-index: 1;
  pointer-events: none;
  transition: width 0.1s;
}

.slider-labels {
  display: flex;
  justify-content: space-between;
  font-size: 11px;
  @apply text-gray-400;
}

/* Tax Info */
.tax-info {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  @apply bg-orange-100;
  border-radius: 6px;
  font-size: 12px;
  @apply text-orange-800;
}

.tax-icon {
  width: 16px;
  height: 16px;
  flex-shrink: 0;
}

@media (max-width: 480px) {
  .source-header {
    flex-direction: column;
    gap: 12px;
  }

  .tax-badge-container {
    text-align: left;
  }
}
</style>
