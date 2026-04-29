<template>
  <div class="p-6 max-w-6xl mx-auto">
    <TaxYearHeader />

    <div v-if="loading" class="flex justify-center py-12">
      <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
    </div>

    <div v-else-if="error" class="rounded-lg bg-raspberry-100 text-raspberry-700 p-4">
      {{ error }}
    </div>

    <template v-else-if="dashboard">
      <HouseholdView v-if="isHouseholdMode" />
      <AllowanceGrid v-else :allowances="userAllowances" class="mb-8" />

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-8">
        <StrategySliderPanel />
        <StrategyRecommendationList />
      </div>
    </template>
  </div>
</template>

<script>
import { mapState, mapGetters } from 'vuex';
import TaxYearHeader from '@/components/TaxStrategy/TaxYearHeader.vue';
import AllowanceGrid from '@/components/TaxStrategy/AllowanceGrid.vue';
import HouseholdView from '@/components/TaxStrategy/HouseholdView.vue';
import StrategySliderPanel from '@/components/TaxStrategy/StrategySliderPanel.vue';
import StrategyRecommendationList from '@/components/TaxStrategy/StrategyRecommendationList.vue';

export default {
  name: 'TaxStrategyDashboard',
  components: {
    TaxYearHeader,
    AllowanceGrid,
    HouseholdView,
    StrategySliderPanel,
    StrategyRecommendationList,
  },
  computed: {
    ...mapState('taxStrategy', ['dashboard', 'loading', 'error']),
    ...mapGetters('taxStrategy', ['userAllowances', 'isHouseholdMode']),
  },
  mounted() {
    this.$store.dispatch('taxStrategy/fetchDashboard');
  },
};
</script>
