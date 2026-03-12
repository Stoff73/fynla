<template>
  <div class="px-4 py-3 flex items-start justify-between">
    <div class="flex-1 min-w-0">
      <h4 class="text-sm font-bold text-horizon-500 truncate">{{ gift.recipient || 'Gift' }}</h4>
      <p class="text-xs text-neutral-500 mt-0.5">{{ giftDate }}</p>
      <div class="flex items-center gap-2 mt-1">
        <span
          class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase"
          :class="gift.is_exempt ? 'bg-spring-50 text-spring-500' : 'bg-violet-50 text-violet-500'"
        >
          {{ gift.is_exempt ? 'Exempt' : 'PET' }}
        </span>
        <span v-if="yearsSinceGift != null" class="text-xs text-neutral-400">
          {{ yearsSinceGift }} year{{ yearsSinceGift !== 1 ? 's' : '' }} ago
        </span>
        <span v-if="taperPct != null" class="text-xs text-neutral-400">
          &middot; {{ taperPct }}% taper
        </span>
      </div>
    </div>
    <p class="text-sm font-bold text-horizon-500 ml-3">{{ formatCurrency(gift.value || gift.amount || 0) }}</p>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'MobileGiftCard',

  mixins: [currencyMixin],

  props: {
    gift: { type: Object, required: true },
  },

  computed: {
    giftDate() {
      if (!this.gift.date && !this.gift.gift_date) return '';
      const d = new Date(this.gift.date || this.gift.gift_date);
      return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
    },

    yearsSinceGift() {
      const dateStr = this.gift.date || this.gift.gift_date;
      if (!dateStr) return null;
      const d = new Date(dateStr);
      const years = Math.floor((Date.now() - d.getTime()) / (365.25 * 24 * 60 * 60 * 1000));
      return years;
    },

    taperPct() {
      if (this.gift.taper_percentage != null) return this.gift.taper_percentage;
      if (this.gift.taper_relief != null) return this.gift.taper_relief;
      return null;
    },
  },
};
</script>
