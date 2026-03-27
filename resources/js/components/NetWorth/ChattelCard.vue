<template>
  <div class="chattel-card module-gradient" @click="$emit('click')">
    <div class="card-header">
      <div class="header-left">
        <span class="chattel-type-badge" :class="typeClass">
          {{ chattelTypeLabel }}
        </span>
        <span v-if="chattel.is_wasting_asset" class="wasting-badge" title="Capital Gains Tax exempt - wasting asset">
          Capital Gains Tax Exempt
        </span>
      </div>
      <span v-if="isJoint" class="ownership-badge">
        {{ chattel.ownership_percentage }}%
      </span>
    </div>

    <div class="card-content">
      <h3 class="chattel-name">{{ chattel.name }}</h3>

      <div v-if="isVehicle && vehicleDescription" class="vehicle-details">
        <p class="vehicle-info">{{ vehicleDescription }}</p>
      </div>

      <div class="chattel-details">
        <div class="detail-row">
          <span class="detail-label">{{ isJoint ? 'Your Share' : 'Current Value' }}</span>
          <span class="detail-value">{{ formatCurrency(displayValue) }}</span>
        </div>
        <div v-if="isJoint" class="detail-row">
          <span class="detail-label">Full Value</span>
          <span class="detail-value text-neutral-500">{{ formatCurrency(chattel.current_value) }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'ChattelCard',
  mixins: [currencyMixin],

  props: {
    chattel: {
      type: Object,
      required: true,
    },
  },

  emits: ['click'],

  computed: {
    chattelTypeLabel() {
      const labels = {
        vehicle: 'Vehicle',
        art: 'Art',
        antique: 'Antique',
        jewelry: 'Jewellery',
        collectible: 'Collectible',
        other: 'Other',
      };
      return labels[this.chattel.chattel_type] || this.chattel.chattel_type;
    },

    typeClass() {
      return `type-${this.chattel.chattel_type}`;
    },

    isJoint() {
      return this.chattel.is_shared || this.chattel.ownership_type === 'joint' || this.chattel.ownership_type === 'tenants_in_common';
    },

    isVehicle() {
      return this.chattel.chattel_type === 'vehicle';
    },

    displayValue() {
      return this.chattel.user_share || this.chattel.current_value || 0;
    },

    vehicleDescription() {
      const parts = [];
      if (this.chattel.year) parts.push(this.chattel.year);
      if (this.chattel.make) parts.push(this.chattel.make);
      if (this.chattel.model) parts.push(this.chattel.model);
      if (this.chattel.registration_number) parts.push(`(${this.chattel.registration_number})`);
      return parts.join(' ');
    },
  },
};
</script>

<style scoped>
.chattel-card {
  background: white;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  @apply border border-light-gray;
  cursor: pointer;
  transition: all 0.2s;
}

.chattel-card:hover {
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

.header-left {
  display: flex;
  align-items: center;
  gap: 8px;
}

.chattel-type-badge {
  padding: 4px 12px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
}

.wasting-badge {
  padding: 4px 8px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 600;
  @apply bg-spring-100;
  @apply text-spring-800;
}

.type-vehicle {
  @apply bg-violet-100;
  @apply text-violet-800;
}

.type-art {
  @apply bg-pink-100;
  @apply text-rose-800;
}

.type-antique {
  @apply bg-violet-100;
  @apply text-violet-800;
}

.type-jewelry {
  @apply bg-purple-50;
  @apply text-purple-800;
}

.type-collectible {
  @apply bg-spring-100;
  @apply text-spring-800;
}

.type-other {
  @apply bg-savannah-100;
  @apply text-neutral-500;
}

.ownership-badge {
  padding: 4px 12px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
  @apply bg-indigo-100;
  @apply text-indigo-800;
}

.card-content {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.chattel-name {
  font-size: 18px;
  font-weight: 600;
  @apply text-horizon-500;
  margin: 0;
}

.vehicle-details {
  padding-bottom: 8px;
  @apply border-b border-light-gray;
}

.vehicle-info {
  font-size: 14px;
  @apply text-neutral-500;
  margin: 0;
}

.chattel-details {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding-top: 12px;
  @apply border-t border-light-gray;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 14px;
}

.detail-label {
  @apply text-neutral-500;
}

.detail-value {
  @apply text-horizon-500;
  font-weight: 600;
}

/* Font weight override for muted text */
.chattel-muted-text {
  @apply text-neutral-500;
  font-weight: 400;
}

@media (max-width: 768px) {
  .chattel-card {
    padding: 16px;
  }

  .chattel-name {
    font-size: 16px;
  }
}
</style>
