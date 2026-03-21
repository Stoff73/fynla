<template>
  <div>
    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-12">
      <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
    </div>

    <!-- Comparison -->
    <div v-else-if="comparison">
      <!-- AI Narrative -->
      <div v-if="comparison.ai_narrative" class="card p-5 mb-6">
        <div class="flex items-start gap-3">
          <div class="w-8 h-8 rounded-full bg-raspberry-100 flex items-center justify-center flex-shrink-0">
            <svg class="w-4 h-4 text-raspberry-500" fill="currentColor" viewBox="0 0 20 20">
              <path d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7z" />
            </svg>
          </div>
          <div>
            <p class="text-xs font-medium text-neutral-500 mb-1">Fyn's Analysis</p>
            <p class="text-sm text-horizon-500 leading-relaxed">{{ comparison.ai_narrative }}</p>
          </div>
        </div>
      </div>

      <!-- Module Comparisons -->
      <div class="space-y-4">
        <ModuleComparison
          v-for="module in comparison.affected_modules"
          :key="module"
          :module="module"
          :current-metrics="comparison.current?.[module] || {}"
          :what-if-metrics="comparison.what_if?.[module] || {}"
          :deltas="comparison.deltas?.[module] || {}"
        />
      </div>
    </div>

    <!-- Empty -->
    <div v-else class="text-center py-12">
      <p class="text-neutral-500">Select a scenario to view the comparison</p>
    </div>
  </div>
</template>

<script>
import ModuleComparison from './ModuleComparison.vue';

export default {
  name: 'ScenarioDetail',
  components: { ModuleComparison },
  props: {
    comparison: { type: Object, default: null },
    loading: { type: Boolean, default: false },
  },
};
</script>
