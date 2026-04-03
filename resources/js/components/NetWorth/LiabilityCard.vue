<template>
  <div class="liability-card module-gradient" :class="{ 'is-external': isExternalSource }" @click="handleClick">
    <div class="card-header">
      <div class="header-left">
        <span class="liability-type-badge" :class="typeClass">
          {{ liabilityTypeLabel }}
        </span>
        <span v-if="liability.is_priority_debt" class="priority-badge">
          Priority Debt
        </span>
      </div>
      <span v-if="isExternalSource" class="external-badge">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
        </svg>
        {{ sourceLabel }}
      </span>
    </div>

    <div class="card-content">
      <h3 class="liability-name">{{ liability.liability_name || 'Unnamed' }}</h3>
      <p v-if="liability.notes && isExternalSource" class="text-xs text-neutral-500 mt-0.5">{{ liability.notes }}</p>

      <div class="liability-details">
        <div v-if="isJoint" class="detail-row">
          <span class="detail-label">Ownership</span>
          <span class="detail-value">
            Joint{{ liability.ownership_percentage ? ' (' + liability.ownership_percentage + '% yours)' : '' }}
          </span>
        </div>
        <div class="detail-row">
          <span class="detail-label">{{ isJoint ? 'Total Balance' : 'Balance Owed' }}</span>
          <span class="detail-value text-raspberry-600">{{ formatCurrency(liability.current_balance) }}</span>
        </div>
        <div v-if="isJoint && userShare !== null" class="detail-row">
          <span class="detail-label">Your Share</span>
          <span class="detail-value text-raspberry-600">{{ formatCurrency(userShare) }}</span>
        </div>
        <div v-if="liability.monthly_payment" class="detail-row">
          <span class="detail-label">Monthly Payment</span>
          <span class="detail-value">{{ formatCurrency(liability.monthly_payment) }}</span>
        </div>
        <div v-if="liability.interest_rate !== null && liability.interest_rate !== undefined" class="detail-row">
          <span class="detail-label">Interest Rate</span>
          <span class="detail-value">{{ formatPercentage(liability.interest_rate) }}</span>
        </div>
      </div>

      <!-- Link to source module for external liabilities -->
      <router-link
        v-if="isExternalSource"
        :to="sourceRoute"
        class="mt-3 inline-flex items-center gap-1.5 text-xs font-medium text-raspberry-500 hover:text-raspberry-600 transition-colors"
        @click.stop
      >
        Edit in {{ sourceLabel }}
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
        </svg>
      </router-link>
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
    isExternalSource() {
      return !!this.liability.source;
    },

    sourceLabel() {
      const labels = {
        property_module: 'Property',
      };
      return labels[this.liability.source] || 'Other Module';
    },

    sourceRoute() {
      if (this.liability.source === 'property_module') {
        return '/net-worth/property';
      }
      return '/net-worth/wealth-summary';
    },

    liabilityTypeLabel() {
      const labels = {
        mortgage: 'Mortgage',
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

    isJoint() {
      return this.liability.ownership_type === 'joint' || this.liability.ownership_type === 'tenants_in_common';
    },

    userShare() {
      if (!this.isJoint) return null;
      const balance = parseFloat(this.liability.current_balance) || 0;
      const pct = parseFloat(this.liability.ownership_percentage) || 50;
      return balance * (pct / 100);
    },

    typeClass() {
      return `type-${this.liability.liability_type}`;
    },
  },

  methods: {
    handleClick() {
      if (this.isExternalSource) {
        this.$router.push(this.sourceRoute);
      } else {
        this.$emit('click');
      }
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

.liability-card:not(.is-external):hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  @apply border-horizon-300;
}

.liability-card.is-external {
  cursor: pointer;
  @apply bg-savannah-50;
}

.liability-card.is-external:hover {
  @apply border-horizon-300;
}

.external-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 8px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 500;
  @apply bg-horizon-100;
  @apply text-horizon-600;
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

.type-mortgage {
  @apply bg-horizon-100;
  @apply text-horizon-700;
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
