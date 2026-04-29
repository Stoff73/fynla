<template>
  <section class="rounded-lg bg-eggshell-500 p-6 mb-6">
    <h2 class="text-body font-bold text-horizon-500 mb-2">Asset-shifting opportunities</h2>
    <p class="text-body-sm text-neutral-600 mb-4">
      Your spouse has unused tax allowances we can put to work. These suggestions are based on what you've told us
      about your household — spousal transfers are exempt from Capital Gains Tax and Inheritance Tax.
    </p>
    <ul class="space-y-3">
      <li
        v-for="suggestion in suggestions"
        :key="suggestion.type"
        class="rounded-md bg-white p-4 border border-light-gray"
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
    <p class="text-caption text-neutral-500 mt-4">
      Note: spousal transfers also reduce your taxable estate for inheritance-tax purposes.
    </p>
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
