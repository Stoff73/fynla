<template>
  <div class="px-4 py-3 flex items-start gap-3">
    <div class="flex-1 min-w-0">
      <h4 class="text-sm font-bold text-horizon-500 truncate">{{ policy.provider || policy.policy_name || 'Policy' }}</h4>
      <p class="text-xs text-neutral-500 mt-0.5">{{ policyTypeLabel }}</p>
      <div class="flex items-center gap-3 mt-1.5">
        <div>
          <p class="text-xs text-neutral-400">Cover</p>
          <p class="text-sm font-semibold text-horizon-500">{{ formatCurrency(policy.coverage_amount || policy.sum_assured || 0) }}</p>
        </div>
        <div>
          <p class="text-xs text-neutral-400">Premium</p>
          <p class="text-sm font-semibold text-horizon-500">{{ formatCurrency(policy.premium || 0) }}/mo</p>
        </div>
      </div>
      <p v-if="policy.end_date" class="text-xs text-neutral-400 mt-1">
        Expires {{ formatDate(policy.end_date) }}
      </p>
    </div>
    <span
      class="px-2 py-0.5 rounded-full text-xs font-semibold"
      :class="statusClass"
    >
      {{ statusLabel }}
    </span>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'MobilePolicyCard',

  mixins: [currencyMixin],

  props: {
    policy: { type: Object, required: true },
    policyType: { type: String, default: 'life' },
  },

  computed: {
    policyTypeLabel() {
      const labels = {
        life: 'Life insurance',
        criticalIllness: 'Critical illness',
        incomeProtection: 'Income protection',
        disability: 'Disability',
        sicknessIllness: 'Sickness & illness',
      };
      return labels[this.policyType] || this.policyType;
    },

    statusLabel() {
      if (this.policy.status === 'active') return 'Active';
      if (this.policy.status === 'lapsed') return 'Lapsed';
      if (this.policy.status === 'expired') return 'Expired';
      return 'Active';
    },

    statusClass() {
      if (this.policy.status === 'lapsed' || this.policy.status === 'expired') {
        return 'bg-raspberry-50 text-raspberry-500';
      }
      return 'bg-spring-50 text-spring-500';
    },
  },

  methods: {
    formatDate(dateStr) {
      if (!dateStr) return '';
      const d = new Date(dateStr);
      return d.toLocaleDateString('en-GB', { month: 'short', year: 'numeric' });
    },
  },
};
</script>
