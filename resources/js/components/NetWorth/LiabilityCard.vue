<template>
  <div class="liability-card" @click="$emit('click')">
    <div class="card-header">
      <div class="header-left">
        <span class="liability-type-badge" :class="typeClass">
          {{ liabilityTypeLabel }}
        </span>
        <span v-if="liability.is_priority_debt" class="priority-badge">
          Priority Debt
        </span>
      </div>
    </div>

    <div class="card-content">
      <h3 class="liability-name">{{ liability.liability_name || 'Unnamed' }}</h3>

      <div class="liability-details">
        <div class="detail-row">
          <span class="detail-label">Balance Owed</span>
          <span class="detail-value text-raspberry-600">{{ formatCurrency(liability.current_balance) }}</span>
        </div>
        <div v-if="liability.monthly_payment" class="detail-row">
          <span class="detail-label">Monthly Payment</span>
          <span class="detail-value">{{ formatCurrency(liability.monthly_payment) }}</span>
        </div>
        <div v-if="liability.interest_rate !== null && liability.interest_rate !== undefined" class="detail-row">
          <span class="detail-label">Interest Rate</span>
          <span class="detail-value">{{ formatPercentage(liability.interest_rate / 100) }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'LiabilityCard',
  mixins: [currencyMixin],

  props: {
    liability: {
      type: Object,
      required: true,
    },
  },

  emits: ['click'],

  computed: {
    liabilityTypeLabel() {
      const labels = {
        secured_loan: 'Secured Loan',
        personal_loan: 'Personal Loan',
        credit_card: 'Credit Card',
        overdraft: 'Overdraft',
        hire_purchase: 'Hire Purchase',
        student_loan: 'Student Loan',
        business_loan: 'Business Loan',
        other: 'Other',
      };
      return labels[this.liability.liability_type] || this.liability.liability_type;
    },

    typeClass() {
      return `type-${this.liability.liability_type}`;
    },
  },
};
</script>

<style scoped>
.liability-card {
  background: white;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  @apply border border-light-gray;
  cursor: pointer;
  transition: all 0.2s;
}

.liability-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  @apply border-raspberry-500;
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

.liability-type-badge {
  padding: 4px 12px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
}

.priority-badge {
  padding: 4px 8px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 600;
  @apply bg-raspberry-100;
  @apply text-raspberry-800;
}

.type-student_loan {
  @apply bg-violet-100;
  @apply text-violet-800;
}

.type-personal_loan {
  @apply bg-indigo-100;
  @apply text-indigo-800;
}

.type-secured_loan {
  @apply bg-slate-100;
  @apply text-slate-800;
}

.type-business_loan {
  @apply bg-purple-100;
  @apply text-purple-800;
}

.type-hire_purchase {
  @apply bg-teal-100;
  @apply text-teal-800;
}

.type-credit_card {
  @apply bg-raspberry-100;
  @apply text-raspberry-800;
}

.type-overdraft {
  @apply bg-savannah-100;
  @apply text-neutral-500;
}

.type-other {
  @apply bg-savannah-100;
  @apply text-neutral-500;
}

.card-content {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.liability-name {
  font-size: 18px;
  font-weight: 600;
  @apply text-horizon-500;
  margin: 0;
}

.liability-details {
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

@media (max-width: 768px) {
  .liability-card {
    padding: 16px;
  }

  .liability-name {
    font-size: 16px;
  }
}
</style>
