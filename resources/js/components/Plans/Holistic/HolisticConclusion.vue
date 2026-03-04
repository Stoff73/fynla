<template>
  <div class="mb-6">
    <PlanSectionHeader title="Conclusion" subtitle="Aggregated summary across all plans" color="gray" />

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
      <p class="text-gray-700 leading-relaxed mb-4">
        This holistic plan brings together recommendations from {{ moduleCount }} area{{ moduleCount !== 1 ? 's' : '' }}
        of your financial life. Below is a consolidated view of the actions that matter most.
      </p>

      <!-- Essential actions across all plans -->
      <div v-if="essentialActions.length" class="mb-4">
        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Priority Actions</h4>
        <ul class="space-y-1.5">
          <li
            v-for="(action, idx) in essentialActions"
            :key="'essential-' + idx"
            class="flex items-start text-sm"
          >
            <span
              class="flex-shrink-0 w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold mr-2 mt-0.5"
              :class="action.priority === 'critical' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700'"
            >
              {{ idx + 1 }}
            </span>
            <span class="text-gray-800">
              {{ action.title }}
              <span class="text-xs text-gray-500 ml-1">({{ formatModuleName(action.sourceModule) }})</span>
            </span>
          </li>
        </ul>
      </div>

      <!-- Optional actions -->
      <div v-if="optionalActions.length" class="mb-4">
        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Optional Improvements</h4>
        <ul class="space-y-1.5">
          <li
            v-for="(action, idx) in optionalActions"
            :key="'optional-' + idx"
            class="flex items-start text-sm text-gray-600"
          >
            <span class="flex-shrink-0 text-gray-400 mr-2 mt-0.5">&mdash;</span>
            <span>
              {{ action.title }}
              <span class="text-xs text-gray-500 ml-1">({{ formatModuleName(action.sourceModule) }})</span>
            </span>
          </li>
        </ul>
      </div>

      <div v-if="!essentialActions.length && !optionalActions.length" class="text-sm text-gray-500 italic">
        Enable actions above to see your personalised priority list here.
      </div>
    </div>
  </div>
</template>

<script>
import PlanSectionHeader from '@/components/Plans/Shared/PlanSectionHeader.vue';

export default {
  name: 'HolisticConclusion',

  components: { PlanSectionHeader },

  props: {
    conclusions: {
      type: Object,
      default: () => ({}),
    },
    allActions: {
      type: Array,
      default: () => [],
    },
  },

  computed: {
    moduleCount() {
      return Object.keys(this.conclusions).length;
    },

    enabledActions() {
      return this.allActions.filter(a => a.enabled);
    },

    essentialActions() {
      const priorityRank = { critical: 0, high: 1 };
      return this.enabledActions
        .filter(a => priorityRank[a.priority] !== undefined)
        .sort((a, b) => (priorityRank[a.priority] ?? 2) - (priorityRank[b.priority] ?? 2));
    },

    optionalActions() {
      return this.enabledActions
        .filter(a => a.priority !== 'critical' && a.priority !== 'high');
    },
  },

  methods: {
    formatModuleName(module) {
      const names = {
        protection: 'Protection',
        investment: 'Investment & Savings',
        retirement: 'Retirement',
        estate: 'Estate',
      };
      return names[module] || module;
    },
  },
};
</script>
