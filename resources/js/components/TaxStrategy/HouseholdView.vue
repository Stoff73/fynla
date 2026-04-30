<template>
  <div>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
      <div>
        <h3 class="text-body-sm uppercase tracking-wide text-neutral-500 mb-2">You</h3>
        <AllowanceGrid :allowances="userAllowances" />
      </div>
      <div>
        <h3 class="text-body-sm uppercase tracking-wide text-neutral-500 mb-2">Your spouse</h3>
        <AllowanceGrid :allowances="spouseAllowances" />
      </div>
    </div>

    <AssetShiftingPanel
      v-if="calculationMode === 'single_earner_couple' && householdRecommendations.length"
      :suggestions="householdRecommendations"
    />

    <section
      v-if="calculationMode === 'dual_earner' && householdRecommendations.length"
      class="rounded-lg bg-eggshell-500 p-6"
    >
      <h2 class="text-body font-bold text-horizon-500 mb-3">Coordinate as a household</h2>
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
              {{ formatCurrency(suggestion.estimated_annual_tax_saved) }}/yr
            </span>
          </div>
          <p class="text-caption text-neutral-500">{{ suggestion.description }}</p>
        </li>
      </ul>
    </section>
  </div>
</template>

<script>
import { mapGetters } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';
import AllowanceGrid from './AllowanceGrid.vue';
import AssetShiftingPanel from './AssetShiftingPanel.vue';

export default {
  name: 'HouseholdView',
  mixins: [currencyMixin],
  components: { AllowanceGrid, AssetShiftingPanel },
  computed: {
    ...mapGetters('taxStrategy', [
      'userAllowances',
      'spouseAllowances',
      'calculationMode',
      'householdRecommendations',
    ]),
  },
};
</script>
