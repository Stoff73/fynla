<template>
  <div class="mb-6">
    <PlanSectionHeader title="What-If Comparison" subtitle="See how your plan changes with recommended actions" color="violet" />

    <div class="bg-white rounded-lg shadow-sm border border-light-gray overflow-hidden">
      <!-- Chart comparison -->
      <div v-if="chartMetrics && chartMetrics.length && currentScenario && projectedScenario" class="px-5 pt-5">
        <PlanWhatIfChart
          :current-scenario="currentScenario"
          :projected-scenario="projectedScenario"
          :metrics="chartMetrics"
        />
      </div>

      <!-- Side-by-side columns -->
      <div class="grid grid-cols-2 divide-x divide-light-gray">
        <!-- Current Scenario -->
        <div class="p-5">
          <h3 class="text-sm font-semibold text-neutral-500 uppercase tracking-wider mb-4">Current Position</h3>
          <slot name="current" />
        </div>

        <!-- Projected Scenario -->
        <div class="p-5 bg-spring-50/30">
          <h3 class="text-sm font-semibold text-spring-700 uppercase tracking-wider mb-4">With Actions</h3>
          <slot name="projected" />
        </div>
      </div>

      <!-- Plan-specific controls slot -->
      <div v-if="$slots.controls" class="border-t border-light-gray px-5 py-3 bg-eggshell-500">
        <slot name="controls" />
      </div>
    </div>
  </div>
</template>

<script>
import PlanSectionHeader from './PlanSectionHeader.vue';
import PlanWhatIfChart from './PlanWhatIfChart.vue';

export default {
  name: 'PlanWhatIfComparison',

  components: { PlanSectionHeader, PlanWhatIfChart },

  props: {
    currentScenario: {
      type: Object,
      default: null,
    },
    projectedScenario: {
      type: Object,
      default: null,
    },
    chartMetrics: {
      type: Array,
      default: null,
    },
  },
};
</script>
