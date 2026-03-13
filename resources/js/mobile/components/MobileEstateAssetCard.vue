<template>
  <div class="px-4 py-3 flex items-start justify-between">
    <div class="flex-1 min-w-0">
      <h4 class="text-sm font-bold text-horizon-500 truncate">{{ asset.description || asset.name || 'Asset' }}</h4>
      <p class="text-xs text-neutral-500 mt-0.5">{{ assetTypeLabel }}</p>
      <p v-if="ownershipLabel" class="text-xs text-neutral-400 mt-0.5">{{ ownershipLabel }}</p>
    </div>
    <p class="text-sm font-bold text-horizon-500 ml-3">{{ formatCurrency(asset.value || asset.current_value || 0) }}</p>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'MobileEstateAssetCard',

  mixins: [currencyMixin],

  props: {
    asset: { type: Object, required: true },
  },

  computed: {
    assetTypeLabel() {
      const labels = {
        property: 'Property',
        main_residence: 'Main residence',
        secondary_residence: 'Secondary residence',
        buy_to_let: 'Buy to let',
        collectible: 'Collectible',
        business: 'Business interest',
        chattels: 'Chattels',
      };
      return labels[this.asset.asset_type || this.asset.type] || 'Asset';
    },

    ownershipLabel() {
      if (!this.asset.ownership_type || this.asset.ownership_type === 'individual') return null;
      if (this.asset.ownership_type === 'joint') return 'Joint ownership';
      if (this.asset.ownership_type === 'tenants_in_common') return 'Tenants in common';
      if (this.asset.ownership_type === 'trust') return 'Held in trust';
      return this.asset.ownership_type;
    },
  },
};
</script>
