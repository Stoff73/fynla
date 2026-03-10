<template>
  <div class="bg-white rounded-xl shadow-sm p-5">
    <p class="text-xs font-semibold text-neutral-500 uppercase tracking-wide mb-1">Net worth</p>
    <p class="text-3xl font-black text-horizon-500">{{ formatCurrency(netWorth) }}</p>
    <div v-if="change !== null && change !== undefined" class="flex items-center gap-1 mt-1">
      <svg v-if="change >= 0" class="w-4 h-4 text-spring-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" />
      </svg>
      <svg v-else class="w-4 h-4 text-raspberry-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
      </svg>
      <span class="text-sm font-semibold" :class="change >= 0 ? 'text-spring-500' : 'text-raspberry-500'">
        {{ formatCurrency(Math.abs(change)) }}
      </span>
      <span class="text-xs text-neutral-500">this month</span>
    </div>
    <NetWorthSparkline v-if="history && history.length" :data="history" class="mt-3" />
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';
import NetWorthSparkline from '@/mobile/charts/NetWorthSparkline.vue';

export default {
  name: 'MobileNetWorthCard',
  mixins: [currencyMixin],
  components: { NetWorthSparkline },
  props: {
    netWorth: { type: Number, default: 0 },
    change: { type: Number, default: null },
    history: { type: Array, default: () => [] },
  },
};
</script>
