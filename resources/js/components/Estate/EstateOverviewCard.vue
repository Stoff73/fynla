<template>
  <div
    class="estate-overview-card bg-white rounded-lg shadow-md p-6 cursor-pointer hover:shadow-lg hover:-translate-y-0.5 hover:border-primary-500 transition-all duration-200 border border-gray-200"
    @click="navigateToEstate"
  >
    <!-- Taxable Estate Now (Primary Value with border) -->
    <div class="primary-value-section">
      <span class="value-label">Taxable Estate on {{ isMarried ? 'Joint' : 'Single' }} Death Now</span>
      <span class="value-amount value-amount-primary">{{ formattedTaxableEstate }}</span>
    </div>

    <!-- IHT Liability Now Section -->
    <div class="section-breakdown">
      <div class="section-header">Current IHT Liability</div>
      <div class="breakdown-item">
        <span class="breakdown-label">Amount Due</span>
        <span class="breakdown-value" :class="ihtLiabilityColour">
          {{ formattedIHTLiability }}
        </span>
      </div>
    </div>

    <!-- Future Values Section -->
    <div class="section-breakdown">
      <div class="section-header">{{ isMarried ? 'Joint' : 'Single' }} Death at Age {{ futureDeathAge || 'TBC' }}</div>
      <div class="breakdown-item">
        <span class="breakdown-label">Taxable Estate</span>
        <span class="breakdown-value breakdown-value-asset">
          {{ formattedFutureTaxableEstate }}
        </span>
      </div>
      <div class="breakdown-item">
        <span class="breakdown-label">IHT Liability</span>
        <span class="breakdown-value" :class="futureIHTLiabilityColour">
          {{ formattedFutureIHTLiability }}
        </span>
      </div>
    </div>

    <!-- Status Banner -->
    <div
      v-if="ihtLiability > 0"
      class="status-banner status-banner-warning"
    >
      <svg
        xmlns="http://www.w3.org/2000/svg"
        class="status-icon"
        viewBox="0 0 20 20"
        fill="currentColor"
      >
        <path
          fill-rule="evenodd"
          d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
          clip-rule="evenodd"
        />
      </svg>
      <span class="status-text">IHT planning recommended</span>
    </div>

    <div
      v-else
      class="status-banner status-banner-success"
    >
      <svg
        xmlns="http://www.w3.org/2000/svg"
        class="status-icon"
        viewBox="0 0 20 20"
        fill="currentColor"
      >
        <path
          fill-rule="evenodd"
          d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
          clip-rule="evenodd"
        />
      </svg>
      <span class="status-text">No IHT liability forecast</span>
    </div>
  </div>
</template>

<script>
export default {
  name: 'EstateOverviewCard',

  props: {
    taxableEstate: {
      type: Number,
      required: true,
      default: 0,
    },
    ihtLiability: {
      type: Number,
      required: true,
      default: 0,
    },
    probateReadiness: {
      type: Number,
      required: true,
      default: 0,
    },
    futureDeathAge: {
      type: Number,
      default: null,
    },
    futureTaxableEstate: {
      type: Number,
      default: null,
    },
    futureIHTLiability: {
      type: Number,
      default: null,
    },
    isMarried: {
      type: Boolean,
      default: false,
    },
  },

  computed: {
    formattedTaxableEstate() {
      return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: 'GBP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
      }).format(this.taxableEstate);
    },

    formattedIHTLiability() {
      return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: 'GBP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
      }).format(this.ihtLiability);
    },

    ihtLiabilityColour() {
      if (this.ihtLiability === 0) {
        return 'text-green-600';
      } else if (this.ihtLiability < 100000) {
        return 'text-amber-600';
      } else {
        return 'text-red-600';
      }
    },

    probateReadinessColour() {
      if (this.probateReadiness >= 80) {
        return 'text-green-600';
      } else if (this.probateReadiness >= 50) {
        return 'text-amber-600';
      } else {
        return 'text-red-600';
      }
    },

    formattedFutureTaxableEstate() {
      if (this.futureTaxableEstate === null || this.futureTaxableEstate === undefined) return '£0';
      return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: 'GBP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
      }).format(this.futureTaxableEstate);
    },

    formattedFutureIHTLiability() {
      // Use the passed prop if available
      let ihtValue = this.futureIHTLiability;

      // If IHT liability is null but we have a taxable estate, calculate it (40% of taxable estate)
      if ((ihtValue === null || ihtValue === undefined || ihtValue === 0) && this.futureTaxableEstate > 0) {
        ihtValue = this.futureTaxableEstate * 0.40;
      }

      if (ihtValue === null || ihtValue === undefined) return '£0';
      return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: 'GBP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
      }).format(ihtValue);
    },

    futureIHTLiabilityColour() {
      // Use the passed prop if available, otherwise calculate from taxable estate
      let ihtValue = this.futureIHTLiability;
      if ((ihtValue === null || ihtValue === undefined || ihtValue === 0) && this.futureTaxableEstate > 0) {
        ihtValue = this.futureTaxableEstate * 0.40;
      }

      if (ihtValue === null || ihtValue === 0) {
        return 'text-green-600';
      } else if (ihtValue < 100000) {
        return 'text-amber-600';
      } else {
        return 'text-red-600';
      }
    },
  },

  methods: {
    navigateToEstate() {
      this.$router.push('/estate');
    },
  },
};
</script>

<style scoped>
.estate-overview-card {
  min-width: 280px;
  max-width: 100%;
  display: flex;
  flex-direction: column;
  gap: 0;
}

/* Card Header */
.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.card-title {
  font-size: 20px;
  font-weight: 600;
  color: #1f2937;
}

.card-icon {
  display: flex;
  align-items: center;
  color: #9ca3af;
}

/* Primary Value Section (with border) */
.primary-value-section {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding-bottom: 16px;
  border-bottom: 1px solid #e5e7eb;
}

.value-label {
  font-size: 14px;
  color: #6b7280;
  font-weight: 500;
}

.value-amount {
  font-size: 32px;
  font-weight: 700;
  color: #111827;
}

.value-amount-primary {
  color: #2563eb;
}

/* Section Breakdown (with grey dividers) */
.section-breakdown {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 16px;
}

/* Subsequent sections - padding AND border */
.section-breakdown + .section-breakdown {
  padding-top: 16px;
  border-top: 1px solid #e5e7eb;
}

.section-header {
  font-size: 14px;
  font-weight: 600;
  color: #374151;
  margin-bottom: 4px;
}

.breakdown-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 14px;
}

.breakdown-label {
  color: #6b7280;
  font-weight: 500;
}

.breakdown-value {
  color: #111827;
  font-weight: 600;
}

.breakdown-value-asset {
  color: #2563eb;
}

/* Status Banner */
.status-banner {
  margin-top: 16px;
  padding-top: 16px;
  border-top: 1px solid #e5e7eb;
  display: flex;
  align-items: center;
  padding: 12px;
  border-radius: 6px;
}

.status-banner-warning {
  background-color: #f59e0b;
}

.status-banner-success {
  background-color: #10b981;
}

.status-icon {
  height: 20px;
  width: 20px;
  color: white;
  margin-right: 8px;
  flex-shrink: 0;
}

.status-text {
  font-size: 14px;
  font-weight: 500;
  color: white;
}

/* IHT Liability Color Classes */
.text-green-600 {
  color: #10b981;
}

.text-amber-600 {
  color: #f59e0b;
}

.text-red-600 {
  color: #dc2626;
}

@media (min-width: 640px) {
  .estate-overview-card {
    min-width: 320px;
  }
}

@media (min-width: 1024px) {
  .estate-overview-card {
    min-width: 360px;
  }
}
</style>
