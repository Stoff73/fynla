<template>
  <div class="px-4 py-3 flex justify-between items-center">
    <span class="text-sm text-neutral-500">{{ label }}</span>
    <span
      class="text-sm font-medium text-right ml-4"
      :class="valueClass"
    >
      {{ formattedValue }}
    </span>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'MobileDataRow',

  mixins: [currencyMixin],

  props: {
    label: { type: String, required: true },
    value: { type: [String, Number], default: '—' },
    type: {
      type: String,
      default: 'text',
      validator: (v) => ['currency', 'percentage', 'text', 'status'].includes(v),
    },
    status: {
      type: String,
      default: null,
      validator: (v) => !v || ['good', 'warning', 'danger'].includes(v),
    },
  },

  computed: {
    formattedValue() {
      if (this.value == null || this.value === '') return '—';

      switch (this.type) {
      case 'currency':
        return this.formatCurrency(this.value);
      case 'percentage':
        return typeof this.value === 'number' ? `${this.value.toFixed(1)}%` : this.value;
      default:
        return String(this.value);
      }
    },

    valueClass() {
      if (this.status === 'good') return 'text-spring-500';
      if (this.status === 'warning') return 'text-violet-500';
      if (this.status === 'danger') return 'text-raspberry-500';
      return 'text-horizon-500';
    },
  },
};
</script>
