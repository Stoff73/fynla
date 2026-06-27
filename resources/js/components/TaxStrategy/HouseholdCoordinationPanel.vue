<template>
  <AssetShiftingPanel
    v-if="calculationMode === 'single_earner_couple' && householdRecommendations.length"
    :suggestions="householdRecommendations"
  />

  <section
    v-else-if="calculationMode === 'dual_earner' && householdRecommendations.length"
    class="rounded-card bg-eggshell-500 p-6 mb-6"
  >
    <h2 class="text-h4 font-bold text-horizon-500 mb-3">Coordinate as a household</h2>
    <p class="text-body-sm text-neutral-500 mb-4">
      These actions only work because both spouses contribute. Spousal transfers between UK-domiciled spouses are exempt from Capital Gains Tax and Inheritance Tax.
    </p>
    <ul class="space-y-3">
      <li
        v-for="suggestion in householdRecommendations"
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
import { mapGetters } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';
import AssetShiftingPanel from './AssetShiftingPanel.vue';

export default {
  name: 'HouseholdCoordinationPanel',
  mixins: [currencyMixin],
  components: { AssetShiftingPanel },
  computed: {
    ...mapGetters('taxStrategy', ['calculationMode', 'householdRecommendations']),
  },
};
</script>
