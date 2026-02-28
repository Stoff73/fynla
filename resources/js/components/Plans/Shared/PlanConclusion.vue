<template>
  <div class="mb-6">
    <PlanSectionHeader title="Conclusion" color="gray" />

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
      <p class="text-gray-700 leading-relaxed mb-4">{{ conclusion.summary_text }}</p>

      <!-- Action summary badges -->
      <div class="flex items-center space-x-3 mb-4">
        <span v-if="conclusion.critical_actions > 0" class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
          {{ conclusion.critical_actions }} critical
        </span>
        <span v-if="conclusion.high_priority_actions > 0" class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
          {{ conclusion.high_priority_actions }} high priority
        </span>
        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
          {{ conclusion.total_actions }} total actions
        </span>
      </div>

      <!-- Collapsible detailed breakdown -->
      <div v-if="conclusion.detailed_breakdown && conclusion.detailed_breakdown.length">
        <button
          class="flex items-center text-sm text-gray-500 hover:text-gray-700 transition-colors"
          @click="expanded = !expanded"
        >
          <svg
            class="w-4 h-4 mr-1 transition-transform"
            :class="{ 'rotate-90': expanded }"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
          {{ expanded ? 'Hide' : 'Show' }} detailed breakdown
        </button>

        <div v-if="expanded" class="mt-3 space-y-2">
          <div
            v-for="group in conclusion.detailed_breakdown"
            :key="group.category"
            class="bg-gray-50 rounded-lg px-4 py-3"
          >
            <p class="text-sm font-medium text-gray-900">
              {{ group.category }}
              <span class="text-gray-500 font-normal">({{ group.action_count }} action{{ group.action_count !== 1 ? 's' : '' }})</span>
            </p>
            <ul class="mt-1 space-y-0.5">
              <li v-for="action in group.actions" :key="action" class="text-sm text-gray-600 flex items-start">
                <span class="text-green-500 mr-1.5 mt-0.5">&#10003;</span>
                {{ action }}
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import PlanSectionHeader from './PlanSectionHeader.vue';

export default {
  name: 'PlanConclusion',

  components: { PlanSectionHeader },

  props: {
    conclusion: {
      type: Object,
      required: true,
    },
  },

  data() {
    return {
      expanded: false,
    };
  },
};
</script>
