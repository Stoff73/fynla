<template>
  <div class="flex items-center justify-between py-2 border-b border-savannah-100 last:border-b-0">
    <span class="text-sm text-neutral-500">{{ label }}</span>
    <div class="flex items-center space-x-2">
      <span class="text-sm font-semibold text-horizon-500">{{ formattedValue }}</span>
      <span
        v-if="delta !== null && delta !== undefined && delta !== 0"
        class="text-xs font-medium px-1.5 py-0.5 rounded"
        :class="deltaClasses"
      >
        {{ deltaPrefix }}{{ formattedDelta }}
      </span>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'PlanWhatIfMetricRow',

  mixins: [currencyMixin],

  props: {
    label: { type: String, required: true },
    value: { type: [Number, String], default: null },
    delta: { type: Number, default: null },
    format: { type: String, default: 'currency' },
    invertDelta: { type: Boolean, default: false },
    suffix: { type: String, default: '' },
  },

  computed: {
    formattedValue() {
      if (this.value === null || this.value === undefined) return 'N/A';
      if (this.format === 'currency') return this.formatCurrency(this.value);
      if (this.format === 'percentage') return `${this.value}%`;
      return `${this.value}${this.suffix ? ` ${this.suffix}` : ''}`;
    },

    formattedDelta() {
      if (this.delta === null || this.delta === undefined) return '';
      const abs = Math.abs(this.delta);
      if (this.format === 'currency') return this.formatCurrency(abs);
      if (this.format === 'percentage') return `${abs}%`;
      return `${abs}${this.suffix ? ` ${this.suffix}` : ''}`;
    },

    deltaPrefix() {
      if (!this.delta) return '';
      return this.delta > 0 ? '+' : '-';
    },

    isPositive() {
      if (this.invertDelta) return this.delta < 0;
      return this.delta > 0;
    },

    deltaClasses() {
      if (!this.delta) return '';
      return this.isPositive
        ? 'bg-spring-100 text-spring-700'
        : 'bg-raspberry-100 text-raspberry-700';
    },
  },
};
</script>
