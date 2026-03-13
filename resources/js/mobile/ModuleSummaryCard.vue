<template>
  <div
    class="bg-white rounded-xl border border-light-gray p-4 cursor-pointer active:bg-savannah-100 transition-colors"
    :class="`border-l-4 border-l-${statusColor}`"
    @click="$emit('click')"
  >
    <div class="mb-2">
      <p class="text-xs font-semibold text-neutral-500 uppercase truncate">{{ moduleData.label || moduleData.name }}</p>
    </div>
    <p class="text-lg font-bold text-horizon-500">{{ metricDisplay }}</p>
    <p v-if="moduleData.subtitle" class="text-xs text-neutral-500 mt-0.5">{{ moduleData.subtitle }}</p>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'ModuleSummaryCard',
  mixins: [currencyMixin],
  props: {
    moduleData: { type: Object, required: true },
  },
  emits: ['click'],
  computed: {
    metricDisplay() {
      if (this.moduleData.metric_type === 'currency') {
        return this.formatCurrency(this.moduleData.metric_value);
      }
      if (this.moduleData.metric_type === 'percentage') {
        return `${this.moduleData.metric_value}%`;
      }
      return this.moduleData.metric_value || '—';
    },
    statusColor() {
      const status = this.moduleData.status;
      if (status === 'good' || status === 'on_track') return 'spring-500';
      if (status === 'warning' || status === 'behind') return 'violet-500';
      if (status === 'action_needed' || status === 'at_risk') return 'raspberry-500';
      return 'light-gray';
    },
  },
};
</script>
