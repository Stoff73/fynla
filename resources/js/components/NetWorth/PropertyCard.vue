<template>
  <div class="property-card module-gradient" @click="viewDetails">
    <div class="card-header">
      <span class="property-type-badge" :class="typeClass">
        {{ propertyTypeLabel }}
      </span>
      <span v-if="isJoint" class="ownership-badge joint-badge">
        Joint ({{ property.ownership_percentage }}%)
      </span>
      <span v-if="isTenantsInCommon" class="ownership-badge tic-badge">
        Tenants in Common ({{ property.ownership_percentage }}%)
      </span>
    </div>

    <div class="card-content">
      <h3 class="property-address">{{ property.address_line_1 }}</h3>
      <p v-if="property.address_line_2" class="property-address-2">
        {{ property.address_line_2 }}
      </p>
      <p class="property-location">
        {{ property.city }}, {{ property.postcode }}
      </p>

      <div class="property-details">
        <!-- Fixed height container for value rows -->
        <div class="value-rows">
          <!-- Show full property value for joint or tenants in common properties -->
          <div v-if="isSharedOwnership" class="detail-row">
            <span class="detail-label">Full Property Value</span>
            <span class="detail-value full-value">{{ formatCurrency(fullPropertyValue) }}</span>
          </div>

          <div class="detail-row">
            <span class="detail-label">{{ isSharedOwnership ? `Your Share (${property.ownership_percentage}%)` : 'Current Value' }}</span>
            <span class="detail-value">{{ formatCurrency(userShare) }}</span>
          </div>

          <div v-if="hasMortgage" class="detail-row">
            <span class="detail-label">{{ mortgageLabel }}</span>
            <span class="detail-value mortgage">{{ formatCurrency(mortgageAmount) }}</span>
          </div>
        </div>

        <!-- Equity row always at bottom -->
        <div class="detail-row equity">
          <span class="detail-label">Equity</span>
          <span class="detail-value">{{ formatCurrency(equity) }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'PropertyCard',
  mixins: [currencyMixin],

  props: {
    property: {
      type: Object,
      required: true,
    },
  },

  emits: ['select-property'],

  computed: {
    propertyTypeLabel() {
      const labels = {
        main_residence: 'Main Residence',
        secondary_residence: 'Secondary Residence',
        buy_to_let: 'Buy to Let',
      };
      return labels[this.property.property_type] || this.property.property_type;
    },

    typeClass() {
      return `type-${this.property.property_type}`;
    },

    isJoint() {
      return this.property.ownership_type === 'joint';
    },

    isTenantsInCommon() {
      return this.property.ownership_type === 'tenants_in_common';
    },

    isSharedOwnership() {
      return this.isJoint || this.isTenantsInCommon;
    },

    fullPropertyValue() {
      // Single-record pattern: current_value in DB is the FULL value
      // Use full_value from API response if available, otherwise current_value
      return this.property.full_value ?? this.property.current_value ?? 0;
    },

    userShare() {
      // Single-record pattern: API provides user_share, or calculate from full value
      if (this.property.user_share !== undefined) {
        return this.property.user_share;
      }
      // Fallback: calculate from full value
      if (this.isSharedOwnership && this.property.ownership_percentage) {
        return this.fullPropertyValue * (this.property.ownership_percentage / 100);
      }
      return this.fullPropertyValue;
    },

    mortgageLabel() {
      // For shared ownership (joint or tenants in common), show user's share label
      if (this.isSharedOwnership && this.property.ownership_percentage) {
        return `Your share of mortgage (${this.property.ownership_percentage}%)`;
      }
      return 'Mortgage Outstanding';
    },

    hasMortgage() {
      // Check if property has mortgages relationship loaded
      if (this.property.mortgages && this.property.mortgages.length > 0) {
        return this.property.mortgages.some(m => m.outstanding_balance > 0);
      }
      return (this.property.mortgage_balance > 0) || (this.property.outstanding_mortgage > 0);
    },

    mortgageAmount() {
      // Get mortgage balance, applying ownership split based on property ownership type
      if (this.property.mortgages && this.property.mortgages.length > 0) {
        const total = this.property.mortgages.reduce((sum, m) => sum + (m.outstanding_balance || 0), 0);
        // Apply ownership split for shared ownership (joint or tenants in common)
        if (this.isSharedOwnership && this.property.ownership_percentage) {
          return total * (this.property.ownership_percentage / 100);
        }
        return total;
      }

      // Fallback for properties without detailed mortgage records
      const fullMortgage = this.property.mortgage_balance || this.property.outstanding_mortgage || 0;
      if (this.isSharedOwnership && this.property.ownership_percentage) {
        return fullMortgage * (this.property.ownership_percentage / 100);
      }
      return fullMortgage;
    },

    equity() {
      // Single-record pattern: Calculate equity from user's share values
      return this.userShare - this.mortgageAmount;
    },
  },

  methods: {
    viewDetails() {
      this.$emit('select-property', this.property);
    },
  },
};
</script>

<style scoped>
.property-card {
  background: white;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  @apply border border-light-gray;
  cursor: pointer;
  transition: all 0.2s;
}

.property-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  @apply border-horizon-300;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
  flex-wrap: wrap;
  gap: 8px;
}

.property-type-badge {
  padding: 4px 12px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
}

.type-main_residence {
  @apply bg-violet-100;
  @apply text-violet-800;
}

.type-secondary_residence {
  @apply bg-violet-100;
  @apply text-violet-800;
}

.type-buy_to_let {
  @apply bg-spring-100;
  @apply text-spring-800;
}

.ownership-badge {
  padding: 4px 12px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
}

.joint-badge {
  @apply bg-purple-50;
  @apply text-purple-800;
}

.tic-badge {
  @apply bg-spring-100;
  @apply text-spring-800;
}

.card-content {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.property-address {
  font-size: 18px;
  font-weight: 600;
  @apply text-horizon-500;
  margin: 0;
}

.property-address-2 {
  font-size: 14px;
  @apply text-neutral-500;
  margin: 0;
}

.property-location {
  font-size: 14px;
  @apply text-neutral-500;
  margin: 0;
}

.property-details {
  display: flex;
  flex-direction: column;
  padding-top: 12px;
  @apply border-t border-light-gray;
}

.value-rows {
  display: flex;
  flex-direction: column;
  gap: 8px;
  min-height: 78px; /* Space for 3 rows: 3 * 22px (line height) + 2 * 8px (gaps) = 78px */
}

.detail-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 14px;
  min-height: 22px;
}

.detail-row.equity {
  margin-top: auto;
  padding-top: 8px;
  @apply border-t border-light-gray;
  font-weight: 600;
}

.detail-label {
  @apply text-neutral-500;
}

.detail-value {
  @apply text-horizon-500;
  font-weight: 600;
}

.detail-value.full-value {
  @apply text-violet-600;
  font-weight: 700;
}

.detail-value.mortgage {
  @apply text-raspberry-500;
}

@media (max-width: 768px) {
  .property-card {
    padding: 16px;
  }

  .property-address {
    font-size: 16px;
  }
}
</style>
