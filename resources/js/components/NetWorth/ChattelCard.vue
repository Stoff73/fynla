<template>
  <div class="chattel-card" @click="$emit('click')">
    <div class="card-header">
      <div class="header-left">
        <span class="chattel-type-badge" :class="typeClass">
          {{ chattelTypeLabel }}
        </span>
        <span v-if="chattel.is_wasting_asset" class="wasting-badge" title="CGT exempt - wasting asset">
          CGT Exempt
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
          <span class="detail-value text-gray-500">{{ formatCurrency(chattel.current_value) }}</span>
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
      return this.chattel.ownership_percentage && this.chattel.ownership_percentage < 100;
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
  border: 1px solid #e5e7eb;
  cursor: pointer;
  transition: all 0.2s;
}

.chattel-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  border-color: #ec4899;
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
  background: #d1fae5;
  color: #065f46;
}

.type-vehicle {
  background: #dbeafe;
  color: #1e40af;
}

.type-art {
  background: #fce7f3;
  color: #9f1239;
}

.type-antique {
  background: #fef3c7;
  color: #92400e;
}

.type-jewelry {
  background: #f3e8ff;
  color: #6b21a8;
}

.type-collectible {
  background: #d1fae5;
  color: #065f46;
}

.type-other {
  background: #f3f4f6;
  color: #374151;
}

.ownership-badge {
  padding: 4px 12px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
  background: #e0e7ff;
  color: #3730a3;
}

.card-content {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.chattel-name {
  font-size: 18px;
  font-weight: 600;
  color: #111827;
  margin: 0;
}

.vehicle-details {
  padding-bottom: 8px;
  border-bottom: 1px solid #e5e7eb;
}

.vehicle-info {
  font-size: 14px;
  color: #6b7280;
  margin: 0;
}

.chattel-details {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding-top: 12px;
  border-top: 1px solid #e5e7eb;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 14px;
}

.detail-label {
  color: #6b7280;
}

.detail-value {
  color: #111827;
  font-weight: 600;
}

.text-gray-500 {
  color: #6b7280;
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
