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
      v-if="calculationMode === 'single_earner_couple' && assetShiftingSuggestions.length"
      :suggestions="assetShiftingSuggestions"
    />

    <section
      v-if="calculationMode === 'dual_earner' && crossSpouseSuggestions.length"
      class="rounded-lg bg-eggshell-500 p-6"
    >
      <h2 class="text-body font-bold text-horizon-500 mb-3">Coordinate as a household</h2>
      <ul class="space-y-3">
        <li
          v-for="suggestion in crossSpouseSuggestions"
          :key="suggestion.type"
          class="rounded-md bg-white p-4 border border-light-gray"
        >
          <h3 class="text-body-sm font-semibold text-horizon-500 mb-1">{{ suggestion.title }}</h3>
          <p class="text-caption text-neutral-500">{{ suggestion.description }}</p>
        </li>
      </ul>
    </section>
  </div>
</template>

<script>
import { mapGetters } from 'vuex';
import AllowanceGrid from './AllowanceGrid.vue';
import AssetShiftingPanel from './AssetShiftingPanel.vue';

export default {
  name: 'HouseholdView',
  components: { AllowanceGrid, AssetShiftingPanel },
  computed: {
    ...mapGetters('taxStrategy', [
      'userAllowances',
      'spouseAllowances',
      'calculationMode',
      'assetShiftingSuggestions',
      'crossSpouseSuggestions',
    ]),
  },
};
</script>
