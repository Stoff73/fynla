<template>
  <div
    class="rounded-md border border-light-gray bg-white"
    :class="compact ? 'p-3' : 'p-4'"
  >
    <div class="flex items-baseline justify-between mb-2">
      <h4 class="text-body-sm font-semibold text-horizon-500">{{ allowance.label }}</h4>
      <span class="text-caption text-neutral-500">
        of {{ formatCurrency(allowance.amount) }}
      </span>
    </div>
    <div class="w-full bg-savannah-200 rounded-full h-1.5 mb-2 overflow-hidden">
      <div
        class="h-1.5 rounded-full transition-all duration-300"
        :class="barClass"
        :style="{ width: `${Math.min(allowance.utilisation_pct, 100)}%` }"
      ></div>
    </div>
    <div class="flex items-center justify-between text-caption">
      <span class="font-semibold" :class="textClass">
        {{ remainingLabel }}
      </span>
      <span class="text-neutral-500">
        {{ formatCurrency(allowance.used) }} used
      </span>
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
    compact: { type: Boolean, default: false },
  },
  computed: {
    barClass() {
      return {
        'bg-spring-500': this.allowance.status === 'spring',
        'bg-violet-500': this.allowance.status === 'violet',
        'bg-raspberry-500': this.allowance.status === 'raspberry',
      };
    },
    textClass() {
      if (this.allowance.status === 'spring') return 'text-spring-600';
      if (this.allowance.status === 'violet') return 'text-violet-600';
      return 'text-raspberry-500';
    },
    remainingLabel() {
      if (this.allowance.utilisation_pct >= 100) return 'Fully used';
      if (this.allowance.remaining > 0) {
        return `${this.formatCurrency(this.allowance.remaining)} of headroom`;
      }
      return 'Fully used';
    },
  },
};
</script>
