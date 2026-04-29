<template>
  <div class="rounded-lg border border-light-gray bg-white p-4">
    <div class="flex items-baseline justify-between mb-2">
      <h3 class="text-body-sm font-semibold text-horizon-500">{{ allowance.label }}</h3>
      <span class="text-caption text-neutral-500">{{ formatCurrency(allowance.amount) }}</span>
    </div>
    <div class="w-full bg-savannah-200 rounded-full h-2 mb-2 overflow-hidden">
      <div
        class="h-2 rounded-full transition-all"
        :class="barClass"
        :style="{ width: `${Math.min(allowance.utilisation_pct, 100)}%` }"
      ></div>
    </div>
    <div class="flex items-center justify-between text-caption">
      <span class="text-neutral-500">{{ formatCurrency(allowance.used) }} used</span>
      <span class="text-neutral-500">{{ formatCurrency(allowance.remaining) }} remaining</span>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'AllowanceCard',
  mixins: [currencyMixin],
  props: {
    allowance: { type: Object, required: true },
  },
  computed: {
    barClass() {
      return {
        'bg-spring-500': this.allowance.status === 'spring',
        'bg-violet-500': this.allowance.status === 'violet',
        'bg-raspberry-500': this.allowance.status === 'raspberry',
      };
    },
  },
};
</script>
