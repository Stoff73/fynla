<template>
  <div class="px-4 py-3">
    <div class="flex items-start justify-between">
      <div class="flex-1 min-w-0">
        <h4 class="text-sm font-bold text-horizon-500 truncate">{{ trust.name || 'Trust' }}</h4>
        <p class="text-xs text-neutral-500 mt-0.5">{{ trustTypeLabel }}</p>
      </div>
      <p class="text-sm font-bold text-horizon-500 ml-3">{{ formatCurrency(trust.value || trust.total_value || 0) }}</p>
    </div>
    <div v-if="details.length" class="grid grid-cols-2 gap-x-4 gap-y-1 mt-2 pt-2 border-t border-light-gray">
      <div v-for="detail in details" :key="detail.label">
        <p class="text-xs text-neutral-400">{{ detail.label }}</p>
        <p class="text-xs font-medium text-horizon-500 truncate">{{ detail.value }}</p>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'MobileTrustCard',

  mixins: [currencyMixin],

  props: {
    trust: { type: Object, required: true },
  },

  computed: {
    trustTypeLabel() {
      const labels = {
        bare: 'Bare trust',
        discretionary: 'Discretionary trust',
        interest_in_possession: 'Interest in possession',
        life_interest: 'Life interest trust',
        accumulation_maintenance: 'Accumulation & maintenance',
      };
      return labels[this.trust.type || this.trust.trust_type] || 'Trust';
    },

    details() {
      const items = [];
      if (this.trust.settlor) items.push({ label: 'Settlor', value: this.trust.settlor });
      if (this.trust.trustees) {
        const trustees = Array.isArray(this.trust.trustees) ? this.trust.trustees.join(', ') : this.trust.trustees;
        items.push({ label: 'Trustees', value: trustees });
      }
      if (this.trust.beneficiaries) {
        const bens = Array.isArray(this.trust.beneficiaries) ? this.trust.beneficiaries.join(', ') : this.trust.beneficiaries;
        items.push({ label: 'Beneficiaries', value: bens });
      }
      return items;
    },
  },
};
</script>
