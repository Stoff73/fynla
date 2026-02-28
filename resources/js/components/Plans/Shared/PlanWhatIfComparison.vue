<template>
  <div class="mb-6">
    <PlanSectionHeader title="What-If Comparison" subtitle="See how your plan changes with recommended actions" color="purple" />

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
      <!-- Approximate indicator -->
      <div v-if="isApproximate" class="bg-blue-50 border-b border-blue-200 px-4 py-2">
        <p class="text-xs text-blue-700 flex items-center">
          <svg class="w-3.5 h-3.5 mr-1.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
          </svg>
          These projections are approximate. Click "Recalculate" for precise figures.
        </p>
      </div>

      <!-- Chart comparison -->
      <div v-if="chartMetrics && chartMetrics.length && currentScenario && projectedScenario" class="px-5 pt-5">
        <PlanWhatIfChart
          :current-scenario="currentScenario"
          :projected-scenario="projectedScenario"
          :metrics="chartMetrics"
        />
      </div>

      <!-- Side-by-side columns -->
      <div class="grid grid-cols-2 divide-x divide-gray-200">
        <!-- Current Scenario -->
        <div class="p-5">
          <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Current Position</h3>
          <slot name="current" />
        </div>

        <!-- Projected Scenario -->
        <div class="p-5 bg-green-50/30">
          <h3 class="text-sm font-semibold text-green-700 uppercase tracking-wider mb-4">With Actions</h3>
          <slot name="projected" />
        </div>
      </div>

      <!-- Plan-specific controls slot -->
      <div v-if="$slots.controls" class="border-t border-gray-200 px-5 py-3 bg-gray-50">
        <slot name="controls" />
      </div>

      <!-- Recalculate button -->
      <div class="border-t border-gray-200 px-5 py-3 bg-gray-50 flex justify-end">
        <button
          class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-[#1257A0] rounded-lg hover:bg-[#0E3A66] transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          :disabled="recalculating"
          @click="$emit('recalculate')"
        >
          <svg v-if="recalculating" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
          </svg>
          <svg v-else class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
          {{ recalculating ? 'Recalculating...' : 'Recalculate' }}
        </button>
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
    isApproximate: {
      type: Boolean,
      default: true,
    },
    recalculating: {
      type: Boolean,
      default: false,
    },
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

  emits: ['recalculate'],
};
</script>
