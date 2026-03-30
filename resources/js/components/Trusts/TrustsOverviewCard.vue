<template>
  <div
    class="trusts-overview-card bg-white rounded-lg shadow-md p-6 cursor-pointer hover:shadow-lg hover:-translate-y-0.5 hover:border-purple-500 transition-all duration-200 border border-light-gray"
    @click="navigateToTrusts"
  >
    <!-- Loading State -->
    <div v-if="loading" class="flex justify-center items-center py-8">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-raspberry-500"></div>
    </div>

    <!-- Content -->
    <div v-else>
      <!-- Trust List -->
      <div class="trust-sections" v-if="trusts.length > 0">
        <div
          v-for="trust in displayedTrusts"
          :key="trust.id"
          class="trust-item"
        >
          <div class="trust-info">
            <div class="trust-name-row">
              <span class="trust-name">{{ trust.trust_name }}</span>
            </div>
            <p class="trust-details">{{ formatTrustType(trust.trust_type) }}</p>
          </div>
          <span class="trust-value">{{ formatCurrency(trust.current_value) }}</span>
        </div>

        <p v-if="trusts.length > 3" class="text-sm text-neutral-500 mt-2">
          +{{ trusts.length - 3 }} more {{ trusts.length - 3 === 1 ? 'trust' : 'trusts' }}
        </p>
      </div>

      <!-- Empty State -->
      <div v-else class="empty-state">
        <p class="text-sm text-neutral-500">No trusts set up</p>
        <p class="text-xs text-horizon-400 mt-1">Click to explore trust planning options</p>
      </div>

      <!-- Tax Info Banner -->
      <div v-if="hasRelevantPropertyTrusts" class="info-banner">
        <svg class="info-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span class="info-text">
          6% charge on asset value on {{ nextChargeDate }}
        </span>
      </div>
    </div>
  </div>
</template>

<script>
import { mapState, mapActions } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';

import logger from '@/utils/logger';
export default {
  name: 'TrustsOverviewCard',
  mixins: [currencyMixin],

  data() {
    return {
      loading: false,
    };
  },

  computed: {
    ...mapState('trusts', ['trusts']),

    safeTrusts() {
      return this.trusts || [];
    },

    activeTrusts() {
      return this.safeTrusts.filter(t => t.is_active);
    },

    activeTrustsCount() {
      return this.activeTrusts.length;
    },

    totalTrustValue() {
      return this.safeTrusts.reduce((sum, trust) => {
        const value = parseFloat(trust.current_value || trust.total_asset_value || 0);
        return sum + (isNaN(value) ? 0 : value);
      }, 0);
    },

    displayedTrusts() {
      // Show up to 3 trusts, prioritizing active ones
      return [...this.safeTrusts]
        .sort((a, b) => {
          if (a.is_active && !b.is_active) return -1;
          if (!a.is_active && b.is_active) return 1;
          return parseFloat(b.current_value || 0) - parseFloat(a.current_value || 0);
        })
        .slice(0, 3);
    },

    hasRelevantPropertyTrusts() {
      return this.safeTrusts.some(t => t.is_relevant_property_trust);
    },

    relevantPropertyTrustsCount() {
      return this.safeTrusts.filter(t => t.is_relevant_property_trust).length;
    },

    nextChargeDate() {
      // Find the earliest next 10-year charge date from relevant property trusts
      const rptTrusts = this.safeTrusts.filter(t => t.is_relevant_property_trust);
      if (rptTrusts.length === 0) return '';

      // Get next charge dates, calculating from trust creation if not set
      const chargeDates = rptTrusts.map(trust => {
        if (trust.next_10_year_charge_date) {
          return new Date(trust.next_10_year_charge_date);
        }
        // Calculate from trust creation date if available
        if (trust.trust_creation_date || trust.created_at) {
          const creationDate = new Date(trust.trust_creation_date || trust.created_at);
          const nextCharge = new Date(creationDate);
          // Find next 10-year anniversary
          const now = new Date();
          while (nextCharge <= now) {
            nextCharge.setFullYear(nextCharge.getFullYear() + 10);
          }
          return nextCharge;
        }
        return null;
      }).filter(d => d !== null);

      if (chargeDates.length === 0) return 'next anniversary';

      // Return earliest date
      const earliest = chargeDates.reduce((a, b) => a < b ? a : b);
      return earliest.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
    },
  },

  async mounted() {
    await this.loadTrusts();
  },

  methods: {
    ...mapActions('trusts', ['fetchTrusts']),

    async loadTrusts() {
      this.loading = true;
      try {
        await this.fetchTrusts();
      } catch (error) {
        logger.error('Failed to load trusts:', error);
      } finally {
        this.loading = false;
      }
    },

    navigateToTrusts() {
      this.$router.push('/trusts');
    },

    formatTrustType(type) {
      const types = {
        bare: 'Bare Trust',
        interest_in_possession: 'Interest in Possession',
        discretionary: 'Discretionary Trust',
        accumulation_maintenance: 'Accumulation & Maintenance',
        life_insurance: 'Life Insurance Trust',
        discounted_gift: 'Discounted Gift Trust',
        loan: 'Loan Trust',
        mixed: 'Mixed Trust',
        settlor_interested: 'Settlor-Interested Trust',
      };
      return types[type] || type;
    },
  },
};
</script>

<style scoped>
.trusts-overview-card {
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

/* Primary Value Section */
.primary-value-section {
  display: flex;
  flex-direction: column;
  gap: 4px;
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
}

/* Trust Sections */
.trust-sections {
  margin-top: 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.trust-item {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding-bottom: 12px;
  @apply border-b border-savannah-100;
}

.trust-item:last-of-type {
  border-bottom: none;
  padding-bottom: 0;
}

.trust-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.trust-name-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.trust-name {
  font-weight: 600;
  font-size: 14px;
  @apply text-horizon-500;
}

.rpt-badge {
  display: inline-flex;
  align-items: center;
  padding: 2px 6px;
  border-radius: 9999px;
  font-size: 10px;
  font-weight: 600;
  @apply bg-white;
  @apply text-blue-800;
  @apply border-2 border-blue-500;
}

.trust-details {
  font-size: 12px;
  @apply text-neutral-500;
}

.trust-value {
  font-weight: 600;
  font-size: 14px;
  @apply text-purple-600;
  white-space: nowrap;
  margin-left: 12px;
}

/* Empty State */
.empty-state {
  margin-top: 16px;
  text-align: center;
  padding: 24px 0;
}

/* Info Banner */
.info-banner {
  margin-top: 16px;
  padding: 12px;
  border-radius: 8px;
  @apply bg-white;
  @apply border-2 border-blue-500;
  display: flex;
  align-items: center;
  gap: 8px;
}

.info-icon {
  width: 20px;
  height: 20px;
  @apply text-blue-800;
  flex-shrink: 0;
}

.info-text {
  font-size: 13px;
  font-weight: 500;
  @apply text-blue-800;
}

@media (min-width: 640px) {
  .trusts-overview-card {
    min-width: 320px;
  }
}

@media (min-width: 1024px) {
  .trusts-overview-card {
    min-width: 360px;
  }
}
</style>
