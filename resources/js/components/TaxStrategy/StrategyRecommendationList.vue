<template>
  <section class="rounded-lg bg-white border border-light-gray p-6">
    <h2 class="text-body font-bold text-horizon-500 mb-3">Recommended actions</h2>
    <div v-if="!groups.length" class="text-caption text-neutral-500">
      No actions needed — your allowances are well utilised.
    </div>
    <div v-else class="space-y-6">
      <div v-for="group in groups" :key="group.key">
        <h3 class="text-caption uppercase tracking-wide text-neutral-500 mb-2">
          {{ group.label }}
        </h3>
        <ul class="space-y-3">
          <li
            v-for="suggestion in group.items"
            :key="suggestion.type"
            class="rounded-md bg-eggshell-500 p-4"
          >
            <div class="flex items-start justify-between mb-1 gap-3">
              <h4 class="text-body-sm font-semibold text-horizon-500">
                {{ suggestion.title }}
              </h4>
              <span
                v-if="suggestion.estimated_annual_tax_saved"
                class="text-body-sm font-semibold text-spring-600 whitespace-nowrap"
              >
                {{ formatCurrency(suggestion.estimated_annual_tax_saved) }}/yr
              </span>
            </div>
            <p class="text-caption text-neutral-500">{{ suggestion.description }}</p>
          </li>
        </ul>
      </div>
    </div>
  </section>
</template>

<script>
import { mapGetters } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'StrategyRecommendationList',
  mixins: [currencyMixin],
  props: {
    /**
     * Optional category filter — when set, render only this slice.
     * Defaults to non-household categories (rendered alongside HouseholdView).
     */
    excludeCategories: {
      type: Array,
      default: () => ['household'],
    },
  },
  computed: {
    ...mapGetters('taxStrategy', ['recommendationsByCategory']),
    groups() {
      return this.recommendationsByCategory.filter(
        (group) => !this.excludeCategories.includes(group.key),
      );
    },
  },
};
</script>
