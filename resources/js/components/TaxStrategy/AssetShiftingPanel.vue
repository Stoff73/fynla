<template>
  <section class="rounded-card bg-eggshell-500 p-6 mb-6">
    <h2 class="text-h4 font-bold text-horizon-500 mb-2">Move assets to use spouse allowances</h2>
    <p class="text-body-sm text-neutral-500 mb-4 max-w-3xl">
      The non-working spouse has a full set of unused tax-free allowances. Moving assets into their name uses those allowances without giving up household ownership — spousal transfers between UK-domiciled spouses are exempt from both Capital Gains Tax and Inheritance Tax.
    </p>
    <ul class="space-y-3">
      <li
        v-for="suggestion in suggestions"
        :key="suggestion.type"
        class="rounded-md bg-white p-4 border border-light-gray"
      >
        <div class="flex items-start justify-between mb-1 gap-3">
          <h3 class="text-body-sm font-semibold text-horizon-500">{{ suggestion.title }}</h3>
          <span
            v-if="suggestion.estimated_annual_tax_saved"
            class="text-body-sm font-semibold text-spring-600 whitespace-nowrap"
          >
            {{ formatCurrency(Math.round(suggestion.estimated_annual_tax_saved)) }}/yr
          </span>
        </div>
        <p class="text-caption text-neutral-500 leading-relaxed">{{ suggestion.description }}</p>
      </li>
    </ul>
  </section>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'AssetShiftingPanel',
  mixins: [currencyMixin],
  props: {
    suggestions: { type: Array, required: true },
  },
};
</script>
