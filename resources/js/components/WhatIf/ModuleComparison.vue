<template>
  <div class="border border-light-gray rounded-lg overflow-hidden">
    <button
      class="w-full px-4 py-3 flex items-center justify-between bg-savannah-100 hover:bg-savannah-200 transition-colors"
      @click="expanded = !expanded"
    >
      <span class="text-sm font-semibold text-horizon-500 capitalize">{{ module }}</span>
      <svg
        class="w-4 h-4 text-neutral-500 transition-transform"
        :class="expanded ? 'rotate-180' : ''"
        fill="none" stroke="currentColor" viewBox="0 0 24 24"
      >
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
      </svg>
    </button>
    <div v-if="expanded" class="p-4">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-light-gray">
            <th class="text-left py-2 text-neutral-500 font-medium">Metric</th>
            <th class="text-right py-2 text-neutral-500 font-medium">Now</th>
            <th class="text-right py-2 text-neutral-500 font-medium">What If</th>
            <th class="text-right py-2 text-neutral-500 font-medium">Change</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(value, key) in currentMetrics" :key="key" class="border-b border-light-gray last:border-0">
            <td class="py-2 text-horizon-500">{{ formatMetricName(key) }}</td>
            <td class="py-2 text-right text-horizon-500">{{ formatMetricValue(value, key) }}</td>
            <td class="py-2 text-right text-horizon-500">{{ formatMetricValue(whatIfMetrics[key], key) }}</td>
            <td class="py-2 text-right font-medium" :class="deltaClass(deltas[key])">
              {{ formatDelta(deltas[key], key) }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'ModuleComparison',
  mixins: [currencyMixin],
  props: {
    module: { type: String, required: true },
    currentMetrics: { type: Object, default: () => ({}) },
    whatIfMetrics: { type: Object, default: () => ({}) },
    deltas: { type: Object, default: () => ({}) },
  },
  data() {
    return { expanded: true };
  },
  methods: {
    formatMetricName(key) {
      return key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    },
    formatMetricValue(value, key) {
      if (value === null || value === undefined) return '-';
      if (key.includes('runway') || key.includes('years')) return `${Number(value).toFixed(1)} months`;
      if (key.includes('rate')) return `${Number(value).toFixed(1)}%`;
      return this.formatCurrency(Number(value));
    },
    formatDelta(delta, key) {
      if (delta === null || delta === undefined || delta === 0) return '-';
      const sign = delta > 0 ? '+' : '';
      if (key.includes('runway') || key.includes('years')) return `${sign}${Number(delta).toFixed(1)} months`;
      if (key.includes('rate')) return `${sign}${Number(delta).toFixed(1)}%`;
      return `${sign}${this.formatCurrency(Number(delta))}`;
    },
    deltaClass(delta) {
      if (!delta || delta === 0) return 'text-neutral-500';
      return delta > 0 ? 'text-spring-600' : 'text-raspberry-600';
    },
  },
};
</script>
