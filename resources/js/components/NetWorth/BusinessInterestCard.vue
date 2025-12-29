<template>
  <div class="business-interest-card" @click="handleClick">
    <div class="card-header">
      <div class="badges">
        <span class="business-type-badge" :class="typeClass">
          {{ businessTypeLabel }}
        </span>
        <span v-if="business.trading_status" class="status-badge" :class="statusClass">
          {{ tradingStatusLabel }}
        </span>
      </div>
      <div class="actions" @click.stop>
        <button @click="handleEdit" class="action-btn edit-btn" title="Edit">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
          </svg>
        </button>
        <button @click="handleDelete" class="action-btn delete-btn" title="Delete">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
          </svg>
        </button>
      </div>
    </div>

    <div class="card-content">
      <h3 class="business-name">{{ business.business_name }}</h3>

      <div class="business-details">
        <div class="detail-row highlighted">
          <span class="detail-label">{{ isJoint ? 'Your Share' : 'Valuation' }}</span>
          <span class="detail-value">{{ formatCurrency(displayValue) }}</span>
        </div>

        <div v-if="isJoint" class="detail-row">
          <span class="detail-label">Total Valuation</span>
          <span class="detail-value text-gray">{{ formatCurrency(business.current_valuation) }}</span>
        </div>

        <div v-if="isJoint" class="detail-row">
          <span class="detail-label">Ownership</span>
          <span class="detail-value">{{ business.ownership_percentage }}%</span>
        </div>

        <div v-if="hasRevenue" class="detail-row">
          <span class="detail-label">Annual Revenue</span>
          <span class="detail-value">{{ formatCurrency(business.annual_revenue) }}</span>
        </div>

        <div v-if="hasProfit" class="detail-row">
          <span class="detail-label">Annual Profit</span>
          <span class="detail-value">{{ formatCurrency(business.annual_profit) }}</span>
        </div>

        <div v-if="business.bpr_eligible" class="detail-row">
          <span class="bpr-badge" title="May qualify for 100% Inheritance Tax relief">Business Relief Eligible</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'BusinessInterestCard',

  mixins: [currencyMixin],

  props: {
    business: {
      type: Object,
      required: true,
    },
  },

  emits: ['click', 'edit', 'delete'],

  computed: {
    businessTypeLabel() {
      const labels = {
        sole_trader: 'Sole Trader',
        partnership: 'Partnership',
        limited_company: 'Limited Company',
        llp: 'LLP',
      };
      return labels[this.business.business_type] || this.business.business_type;
    },

    typeClass() {
      return `type-${this.business.business_type}`;
    },

    tradingStatusLabel() {
      const labels = {
        trading: 'Trading',
        dormant: 'Dormant',
        pre_trading: 'Pre-Trading',
      };
      return labels[this.business.trading_status] || this.business.trading_status;
    },

    statusClass() {
      return `status-${this.business.trading_status}`;
    },

    isJoint() {
      return this.business.ownership_percentage < 100;
    },

    displayValue() {
      // Show user's share if joint ownership
      return this.business.user_share || this.business.current_valuation || 0;
    },

    hasRevenue() {
      return this.business.annual_revenue && this.business.annual_revenue > 0;
    },

    hasProfit() {
      return this.business.annual_profit && this.business.annual_profit > 0;
    },
  },

  methods: {
    handleClick() {
      this.$emit('click', this.business.id);
    },

    handleEdit() {
      this.$emit('edit', this.business);
    },

    handleDelete() {
      this.$emit('delete', this.business);
    },
  },
};
</script>

<style scoped>
.business-interest-card {
  background: white;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  border: 1px solid #e5e7eb;
  cursor: pointer;
  transition: all 0.2s;
}

.business-interest-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  border-color: #a21caf;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 16px;
  gap: 8px;
}

.badges {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.business-type-badge {
  padding: 4px 12px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
}

.type-sole_trader {
  background: #dbeafe;
  color: #1e40af;
}

.type-partnership {
  background: #fef3c7;
  color: #92400e;
}

.type-limited_company {
  background: #d1fae5;
  color: #065f46;
}

.type-llp {
  background: #f3e8ff;
  color: #6b21a8;
}

.status-badge {
  padding: 4px 12px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
}

.status-trading {
  background: #dcfce7;
  color: #166534;
}

.status-dormant {
  background: #f3f4f6;
  color: #6b7280;
}

.status-pre_trading {
  background: #fef3c7;
  color: #92400e;
}

.actions {
  display: flex;
  gap: 4px;
  opacity: 0;
  transition: opacity 0.2s;
}

.business-interest-card:hover .actions {
  opacity: 1;
}

.action-btn {
  padding: 6px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
}

.action-btn svg {
  width: 16px;
  height: 16px;
}

.edit-btn {
  background: #f3f4f6;
  color: #374151;
}

.edit-btn:hover {
  background: #e5e7eb;
  color: #111827;
}

.delete-btn {
  background: #fef2f2;
  color: #dc2626;
}

.delete-btn:hover {
  background: #fee2e2;
  color: #b91c1c;
}

.card-content {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.business-name {
  font-size: 18px;
  font-weight: 600;
  color: #111827;
  margin: 0;
}

.business-details {
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

.detail-row.highlighted {
  font-weight: 600;
  padding-bottom: 8px;
  border-bottom: 1px solid #e5e7eb;
}

.detail-label {
  color: #6b7280;
}

.detail-value {
  color: #111827;
  font-weight: 600;
}

.detail-value.text-gray {
  color: #6b7280;
  font-weight: 500;
}

.bpr-badge {
  display: inline-flex;
  align-items: center;
  padding: 4px 10px;
  background: #f0fdf4;
  color: #166534;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
  border: 1px solid #bbf7d0;
}

@media (max-width: 768px) {
  .business-interest-card {
    padding: 16px;
  }

  .business-name {
    font-size: 16px;
  }

  .actions {
    opacity: 1;
  }
}
</style>
