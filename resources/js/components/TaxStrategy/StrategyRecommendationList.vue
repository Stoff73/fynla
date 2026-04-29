<template>
  <section class="rounded-lg bg-white border border-light-gray p-6">
    <h2 class="text-body font-bold text-horizon-500 mb-3">Recommended actions</h2>
    <div v-if="!suggestions.length" class="text-caption text-neutral-500">
      No actions needed — your allowances are well utilised.
    </div>
    <ul v-else class="space-y-3">
      <li
        v-for="suggestion in suggestions"
        :key="suggestion.type"
        class="rounded-md bg-eggshell-500 p-4"
      >
        <div class="flex items-start justify-between mb-1">
          <h3 class="text-body-sm font-semibold text-horizon-500">{{ suggestion.title }}</h3>
          <span
            v-if="suggestion.estimated_annual_tax_saved"
            class="text-body-sm font-semibold text-spring-600"
          >
            {{ formatCurrency(suggestion.estimated_annual_tax_saved) }}/yr
          </span>
        </div>
        <p class="text-caption text-neutral-500">{{ suggestion.description }}</p>
      </li>
    </ul>
  </section>
</template>

<script>
import { mapGetters } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'StrategyRecommendationList',
  mixins: [currencyMixin],
  computed: {
    ...mapGetters('taxStrategy', ['assetShiftingSuggestions', 'crossSpouseSuggestions']),
    suggestions() {
      // For path A (single), these are empty and the StrategySliderPanel sits beside this.
      // For path B (dual_earner), crossSpouseSuggestions populated.
      // For path C (single_earner_couple), assetShiftingSuggestions populated and rendered
      //   in the AssetShiftingPanel via HouseholdView; this list shows generic recommendations only.
      return [...this.crossSpouseSuggestions];
    },
  },
};
</script>
