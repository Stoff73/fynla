<template>
  <AppLayout>
    <div class="max-w-6xl mx-auto pb-12">
      <TaxYearHeader />

      <div v-if="loading" class="flex justify-center py-12">
        <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
      </div>

      <div v-else-if="error" class="rounded-card bg-raspberry-100 text-raspberry-700 p-4">
        {{ error }}
      </div>

      <template v-else-if="dashboard">
        <HouseholdView v-if="isHouseholdMode" class="mb-8" />
        <AllowanceGrid v-else :allowances="userAllowances" class="mb-8" />

        <StrategyRecommendationList />
      </template>
    </div>
  </AppLayout>
</template>

<script>
import { mapState, mapGetters } from 'vuex';
import AppLayout from '@/layouts/AppLayout.vue';
import TaxYearHeader from '@/components/TaxStrategy/TaxYearHeader.vue';
import AllowanceGrid from '@/components/TaxStrategy/AllowanceGrid.vue';
import HouseholdView from '@/components/TaxStrategy/HouseholdView.vue';
import StrategyRecommendationList from '@/components/TaxStrategy/StrategyRecommendationList.vue';

export default {
  name: 'TaxStrategyDashboard',
  components: {
    AppLayout,
    TaxYearHeader,
    AllowanceGrid,
    HouseholdView,
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
