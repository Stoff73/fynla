<template>
  <div
    class="trusts-overview-card bg-white rounded-lg shadow-md p-6 cursor-pointer hover:shadow-lg hover:-translate-y-0.5 hover:border-purple-500 transition-all duration-200 border border-gray-200"
    @click="navigateToTrusts"
  >
    <!-- Card Header -->
    <div class="card-header">
      <h3 class="card-title">Trusts</h3>
      <span class="card-icon">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
        </svg>
      </span>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="flex justify-center items-center py-8">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-purple-600"></div>
    </div>

    <!-- Content -->
    <div v-else>
      <!-- Primary Value Section -->
      <div class="primary-value-section">
        <span class="value-label">Total Trust Value</span>
        <div class="flex items-center gap-3">
          <span class="value-amount text-purple-600">{{ formatCurrency(totalTrustValue) }}</span>
        </div>
        <p class="text-sm text-gray-500 mt-1">{{ activeTrustsCount }} active {{ activeTrustsCount === 1 ? 'trust' : 'trusts' }}</p>
      </div>

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
              <span
                v-if="trust.is_relevant_property_trust"
                class="rpt-badge"
              >
                RPT
              </span>
            </div>
            <p class="trust-details">{{ formatTrustType(trust.trust_type) }}</p>
          </div>
          <span class="trust-value">{{ formatCurrency(trust.current_value) }}</span>
        </div>

        <p v-if="trusts.length > 3" class="text-sm text-gray-500 mt-2">
          +{{ trusts.length - 3 }} more {{ trusts.length - 3 === 1 ? 'trust' : 'trusts' }}
        </p>
      </div>

      <!-- Empty State -->
      <div v-else class="empty-state">
        <p class="text-sm text-gray-500">No trusts set up</p>
        <p class="text-xs text-gray-400 mt-1">Click to explore trust planning options</p>
      </div>

      <!-- Tax Info Banner -->
      <div v-if="hasRelevantPropertyTrusts" class="info-banner">
        <svg class="info-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span class="info-text">
          {{ relevantPropertyTrustsCount }} RPT{{ relevantPropertyTrustsCount > 1 ? 's' : '' }} - 10-year charges apply
        </span>
      </div>
    </div>
  </div>
</template>

<script>
import { mapState, mapActions } from 'vuex';

export default {
  name: 'TrustsOverviewCard',

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
        console.error('Failed to load trusts:', error);
      } finally {
        this.loading = false;
      }
    },

    navigateToTrusts() {
      this.$router.push('/trusts');
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
  color: #1f2937;
}

.card-icon {
  display: flex;
  align-items: center;
  color: #9ca3af;
}

/* Primary Value Section */
.primary-value-section {
  display: flex;
  flex-direction: column;
  gap: 4px;
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
  border-bottom: 1px solid #f3f4f6;
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
  color: #111827;
}

.rpt-badge {
  display: inline-flex;
  align-items: center;
  padding: 2px 6px;
  border-radius: 9999px;
  font-size: 10px;
  font-weight: 600;
  background-color: #fef3c7;
  color: #92400e;
}

.trust-details {
  font-size: 12px;
  color: #6b7280;
}

.trust-value {
  font-weight: 600;
  font-size: 14px;
  color: #7c3aed;
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
  background-color: #fef3c7;
  display: flex;
  align-items: center;
  gap: 8px;
}

.info-icon {
  width: 20px;
  height: 20px;
  color: #92400e;
  flex-shrink: 0;
}

.info-text {
  font-size: 13px;
  font-weight: 500;
  color: #92400e;
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
