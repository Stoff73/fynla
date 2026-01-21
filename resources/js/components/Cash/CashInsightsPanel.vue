<template>
  <div class="cash-insights-panel">
    <!-- Spending Chart -->
    <SpendingDonutChart :financial-commitments="financialCommitments" />

    <!-- Balance Trend Chart -->
    <BalanceTrendChart />
  </div>
</template>

<script>
import SpendingDonutChart from './SpendingDonutChart.vue';
import BalanceTrendChart from './BalanceTrendChart.vue';

export default {
  name: 'CashInsightsPanel',

  components: {
    SpendingDonutChart,
    BalanceTrendChart,
  },

  props: {
    financialCommitments: {
      type: Object,
      default: () => ({}),
    },
  },
};
</script>

<style scoped>
.cash-insights-panel {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
</style>
