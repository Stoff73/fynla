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
      <span v-if="available && known" class="text-neutral-500">
        {{ formatCurrency(allowance.used) }} used
      </span>
    </div>
    <p v-if="budgetLimited" class="text-caption text-neutral-500 mt-1">
      Limited to what you can afford this year
    </p>
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
    available() {
      return this.allowance.available !== false;
    },
    // known:false = no confirmed current-year use (TaxStrategyCalculator::position);
    // worded as on /m (resources/mobile/views/TaxStrategy.vue).
    known() {
      return this.allowance.known !== false;
    },
    // The server caps pension headroom at a year of affordable surplus
    // (TaxStrategyService::withAffordablePensionHeadroom); say so when it bites.
    budgetLimited() {
      return this.allowance.budget_limited === true;
    },
    barClass() {
      return {
        'bg-spring-500': this.allowance.status === 'spring',
        'bg-violet-500': this.allowance.status === 'violet',
        'bg-raspberry-500': this.allowance.status === 'raspberry',
      };
    },
    textClass() {
      if (!this.available || !this.known) return 'text-neutral-500';
      if (this.allowance.status === 'spring') return 'text-spring-600';
      if (this.allowance.status === 'violet') return 'text-violet-600';
      return 'text-raspberry-500';
    },
    // The tile's state is the server's (TaxStrategyService `tile_state`, CSJ
    // 2026-10-01); the words are web's approved copy. budget_capped: brought to
    // £0 by what is affordable, not by use (fynla.org 2026-09-30).
    remainingLabel() {
      switch (this.allowance.tile_state) {
        case 'unavailable': return 'Not available';
        case 'unconfirmed': return 'Current-year use not confirmed';
        case 'budget_capped': return `${this.formatCurrency(0)} of headroom`;
        case 'open': return `${this.formatCurrency(this.allowance.remaining)} of headroom`;
        default: return 'Fully used';
      }
    },
  },
};
</script>
