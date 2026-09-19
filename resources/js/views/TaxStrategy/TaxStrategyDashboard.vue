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
        <!-- One line on what to do here ("landed on tax strategy — didn't know
             what to do next", Azlan 2026-09-18). Same words as /m. -->
        <p v-if="personalisedIntro" class="card p-4 mb-6 text-body text-horizon-800 bg-eggshell-500">{{ personalisedIntro }}</p>
        <!-- Strategies lead; the allowance detail sits below them. -->
        <HouseholdCoordinationPanel v-if="isHouseholdMode" />

        <StrategyRecommendationList />

        <HouseholdView v-if="isHouseholdMode" class="mt-8" />
        <AllowanceGrid v-else :allowances="userAllowances" class="mt-8" />
      </template>
    </div>
  </AppLayout>
</template>

<script>
import { mapState, mapGetters } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';
import AppLayout from '@/layouts/AppLayout.vue';
import TaxYearHeader from '@/components/TaxStrategy/TaxYearHeader.vue';
import AllowanceGrid from '@/components/TaxStrategy/AllowanceGrid.vue';
import HouseholdView from '@/components/TaxStrategy/HouseholdView.vue';
import HouseholdCoordinationPanel from '@/components/TaxStrategy/HouseholdCoordinationPanel.vue';
import StrategyRecommendationList from '@/components/TaxStrategy/StrategyRecommendationList.vue';

export default {
  name: 'TaxStrategyDashboard',
  components: {
    AppLayout,
    TaxYearHeader,
    AllowanceGrid,
    HouseholdView,
    HouseholdCoordinationPanel,
    StrategyRecommendationList,
  },
  mixins: [currencyMixin],
  computed: {
    ...mapState('taxStrategy', ['dashboard', 'loading', 'error']),
    ...mapGetters('taxStrategy', ['userAllowances', 'isHouseholdMode']),
    personalisedIntro() {
      const user = this.$store.state.auth?.user;
      if (!user || !user.onboarding_completed) return '';
      const name = user.first_name || 'there';
      const saving = Number(this.dashboard?.composed_plan?.combined_annual_saving) || 0;
      const next = ' Open any strategy to see the steps, or ask Fyn about it.';
      return (saving > 0
        ? `Here's your personal tax strategy, ${name}. From what you told us, we've found around ${this.formatCurrency(Math.round(saving))} a year you could keep.`
        : `Here's your personal tax strategy, ${name}. From what you told us, here's how to make the most of your allowances.`) + next;
    },
  },
  mounted() {
    this.$store.dispatch('taxStrategy/fetchDashboard');
  },
};
</script>
