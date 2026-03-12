<template>
  <div class="px-4 py-2.5 flex items-center justify-between">
    <div class="flex-1 min-w-0">
      <p class="text-sm font-medium text-horizon-500 truncate">{{ holding.security_name || holding.name || 'Holding' }}</p>
      <p v-if="holding.ticker || holding.asset_type" class="text-xs text-neutral-400 mt-0.5">
        <span v-if="holding.ticker">{{ holding.ticker }}</span>
        <span v-if="holding.ticker && holding.asset_type"> &middot; </span>
        <span v-if="holding.asset_type">{{ holding.asset_type }}</span>
      </p>
    </div>
    <div class="text-right ml-3">
      <p class="text-sm font-semibold text-horizon-500">{{ formatCurrency(holding.current_value || holding.value || 0) }}</p>
      <p v-if="allocationPct != null" class="text-xs text-neutral-400 mt-0.5">{{ allocationPct.toFixed(1) }}%</p>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'MobileHoldingRow',

  mixins: [currencyMixin],

  props: {
    holding: { type: Object, required: true },
    allocationPct: { type: Number, default: null },
  },
};
</script>
