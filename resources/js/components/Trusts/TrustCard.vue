<template>
  <div class="trust-card" @click="$emit('view', trust)">
    <!-- Card Header -->
    <div class="card-header">
      <div class="card-icon trusts">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
        </svg>
      </div>
      <div class="card-title-section">
        <h3 class="card-title">{{ trust.trust_name }}</h3>
        <p class="card-total">{{ formatCurrency(trust.total_asset_value || trust.current_value) }}</p>
      </div>
      <div class="card-arrow">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
        </svg>
      </div>
    </div>

    <!-- Card Items -->
    <div class="card-items">
      <!-- Trust Type -->
      <div class="item-row">
        <span class="item-name">
          {{ formatTrustType(trust.trust_type, trust.other_type_description) }}
          <span v-if="trust.is_relevant_property_trust" class="badge rpt">RPT</span>
          <span :class="['badge', trust.is_active ? 'active' : 'inactive']">
            {{ trust.is_active ? 'Active' : 'Inactive' }}
          </span>
        </span>
      </div>

      <!-- Country (for Other trusts) -->
      <div v-if="trust.trust_type === 'other' && trust.country" class="item-row">
        <span class="item-name">Country</span>
        <span class="item-value">{{ trust.country }}</span>
      </div>

      <!-- Creation Date -->
      <div class="item-row">
        <span class="item-name">Created</span>
        <span class="item-value">{{ formatDate(trust.trust_creation_date) }}</span>
      </div>

      <!-- Settlor -->
      <div class="item-row">
        <span class="item-name">Settlor</span>
        <span class="item-value">{{ trust.settlor || '-' }}</span>
      </div>

      <!-- Trustees -->
      <div class="item-row">
        <span class="item-name">Trustees</span>
        <span class="item-value">{{ trust.trustees || '-' }}</span>
      </div>

      <!-- Beneficiaries -->
      <div class="item-row">
        <span class="item-name">Beneficiaries</span>
        <span class="item-value">{{ trust.beneficiaries || '-' }}</span>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'TrustCard',
  mixins: [currencyMixin],

  props: {
    trust: {
      type: Object,
      required: true,
    },
  },

  emits: ['view'],

  methods: {
    formatDate(dateString) {
      if (!dateString) return '-';
      return new Date(dateString).toLocaleDateString('en-GB', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
      });
    },

    formatTrustType(type, otherDescription = null) {
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
        other: otherDescription || 'Other Trust',
      };
      return types[type] || type;
    },
  },
};
</script>

<style scoped>
.trust-card {
  background: white;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  @apply border border-light-gray;
  cursor: pointer;
  transition: all 0.2s;
}

.trust-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  @apply border-violet-500;
}

.card-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
  padding-bottom: 16px;
  @apply border-b border-light-gray;
}

.card-icon {
  width: 44px;
  height: 44px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.card-icon svg {
  width: 22px;
  height: 22px;
}

.card-icon.trusts {
  @apply bg-purple-100;
  @apply text-purple-600;
}

.card-title-section {
  flex: 1;
  min-width: 0;
}

.card-title {
  font-size: 16px;
  font-weight: 600;
  @apply text-neutral-500;
  margin: 0 0 4px 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.card-total {
  font-size: 24px;
  font-weight: 700;
  @apply text-horizon-500;
  margin: 0;
}

.card-arrow {
  width: 24px;
  height: 24px;
  @apply text-horizon-400;
  flex-shrink: 0;
}

.card-arrow svg {
  width: 100%;
  height: 100%;
}

.card-items {
  min-height: 80px;
}

.item-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 8px 0;
  @apply border-b border-savannah-100;
  gap: 12px;
}

.item-row:last-child {
  border-bottom: none;
}

.item-name {
  font-size: 14px;
  @apply text-neutral-500;
  display: flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
}

.item-value {
  font-size: 14px;
  font-weight: 600;
  @apply text-horizon-500;
  text-align: right;
  word-break: break-word;
}

.badge {
  font-size: 10px;
  padding: 2px 6px;
  border-radius: 4px;
  font-weight: 500;
  flex-shrink: 0;
}

.badge.rpt {
  @apply bg-blue-100;
  @apply text-blue-700;
}

.badge.active {
  @apply bg-green-100;
  @apply text-green-800;
}

.badge.inactive {
  @apply bg-savannah-100;
  @apply text-neutral-500;
}

@media (max-width: 768px) {
  .card-total {
    font-size: 20px;
  }

  .card-icon {
    width: 40px;
    height: 40px;
  }

  .card-icon svg {
    width: 20px;
    height: 20px;
  }
}
</style>
