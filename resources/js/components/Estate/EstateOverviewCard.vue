<template>
  <div
    class="estate-overview-card bg-white rounded-lg shadow-md p-6 cursor-pointer hover:shadow-lg hover:-translate-y-0.5 hover:bg-light-gray transition-all duration-200 border border-light-gray"
    @click="navigateToEstate"
  >
    <!-- Taxable Estate Now (Primary Value with border) -->
    <div class="primary-value-section">
      <span class="value-label">Taxable Estate on {{ isUserMarried ? 'Joint' : 'Single' }} Death Now</span>
      <span class="value-amount value-amount-primary">{{ formattedTaxableEstate }}</span>
    </div>

    <!-- Inheritance Tax Liability Now Section -->
    <div class="section-breakdown">
      <div class="section-header">Current Inheritance Tax Liability</div>
      <div class="breakdown-item">
        <span class="breakdown-label">Amount Due</span>
        <span class="breakdown-value" :class="ihtLiabilityColour">
          {{ formattedIHTLiability }}
        </span>
      </div>
    </div>

    <!-- Future Values Section -->
    <div class="section-breakdown">
      <div class="section-header">{{ isUserMarried ? 'Joint' : 'Single' }} Death at Age {{ futureDeathAge || 'TBC' }}</div>
      <div class="breakdown-item">
        <span class="breakdown-label">Taxable Estate</span>
        <span class="breakdown-value breakdown-value-asset">
          {{ formattedFutureTaxableEstate }}
        </span>
      </div>
      <div class="breakdown-item">
        <span class="breakdown-label">Inheritance Tax Liability</span>
        <span class="breakdown-value" :class="futureIHTLiabilityColour">
          {{ formattedFutureIHTLiability }}
        </span>
      </div>
    </div>

    <!-- Status Banner -->
    <div
      v-if="ihtLiability > 0"
      class="mt-4 pt-4 border-t border-light-gray"
    >
      <div class="p-3 bg-white border border-light-gray rounded-lg">
        <div class="flex items-center gap-2">
          <svg class="w-5 h-5 text-violet-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
          <span class="text-sm font-medium text-violet-700">Inheritance Tax planning recommended</span>
        </div>
      </div>
    </div>

    <div
      v-else
      class="mt-4 pt-4 border-t border-light-gray"
    >
      <div class="p-3 bg-white border border-light-gray rounded-lg">
        <div class="flex items-center gap-2">
          <svg class="w-5 h-5 text-spring-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <span class="text-sm font-medium text-spring-700">No Inheritance Tax liability forecast</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'EstateOverviewCard',
  mixins: [currencyMixin],

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
    isUserMarried() {
      // Get marital status directly from store as fallback
      const user = this.$store?.state?.auth?.user;
      return this.isMarried || (user && user.marital_status === 'married');
    },

    formattedTaxableEstate() {
      return this.formatCurrency(this.taxableEstate);
    },

    formattedIHTLiability() {
      return this.formatCurrency(this.ihtLiability);
    },

    ihtLiabilityColour() {
      if (this.ihtLiability === 0) {
        return 'text-spring-600';
      } else if (this.ihtLiability < 100000) {
        return 'text-violet-600';
      } else {
        return 'text-raspberry-600';
      }
    },

    probateReadinessColour() {
      if (this.probateReadiness >= 80) {
        return 'text-spring-600';
      } else if (this.probateReadiness >= 50) {
        return 'text-violet-600';
      } else {
        return 'text-raspberry-600';
      }
    },

    formattedFutureTaxableEstate() {
      if (this.futureTaxableEstate === null || this.futureTaxableEstate === undefined) return '£0';
      return this.formatCurrency(this.futureTaxableEstate);
    },

    formattedFutureIHTLiability() {
      // Use the passed prop if available
      let ihtValue = this.futureIHTLiability;

      // If IHT liability is null but we have a taxable estate, calculate it (40% of taxable estate)
      if ((ihtValue === null || ihtValue === undefined || ihtValue === 0) && this.futureTaxableEstate > 0) {
        ihtValue = this.futureTaxableEstate * 0.40;
      }

      if (ihtValue === null || ihtValue === undefined) return '£0';
      return this.formatCurrency(ihtValue);
    },

    futureIHTLiabilityColour() {
      // Use the passed prop if available, otherwise calculate from taxable estate
      let ihtValue = this.futureIHTLiability;
      if ((ihtValue === null || ihtValue === undefined || ihtValue === 0) && this.futureTaxableEstate > 0) {
        ihtValue = this.futureTaxableEstate * 0.40;
      }

      if (ihtValue === null || ihtValue === 0) {
        return 'text-spring-600';
      } else if (ihtValue < 100000) {
        return 'text-violet-600';
      } else {
        return 'text-raspberry-600';
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
  @apply text-horizon-500;
}

.card-icon {
  display: flex;
  align-items: center;
  @apply text-horizon-400;
}

/* Primary Value Section (with border) */
.primary-value-section {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding-bottom: 16px;
  @apply border-b border-light-gray;
}

.value-label {
  font-size: 14px;
  @apply text-neutral-500;
  font-weight: 500;
}

.value-amount {
  font-size: 32px;
  font-weight: 700;
  @apply text-horizon-500;
}

.value-amount-primary {
  @apply text-violet-600;
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
  @apply border-t border-light-gray;
}

.section-header {
  font-size: 14px;
  font-weight: 600;
  @apply text-neutral-500;
  margin-bottom: 4px;
}

.breakdown-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 14px;
}

.breakdown-label {
  @apply text-neutral-500;
  font-weight: 500;
}

.breakdown-value {
  @apply text-horizon-500;
  font-weight: 600;
}

.breakdown-value-asset {
  @apply text-violet-600;
}

/* Status Banner */
.status-banner {
  margin-top: 16px;
  padding-top: 16px;
  @apply border-t border-light-gray;
  display: flex;
  align-items: center;
  padding: 12px;
  border-radius: 6px;
}

.status-banner-warning {
  @apply bg-violet-500;
}

.status-banner-success {
  @apply bg-spring-500;
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

/* Inheritance Tax Liability Color Classes - using Tailwind utilities directly in template */

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
