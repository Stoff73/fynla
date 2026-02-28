<template>
  <div
    class="bg-white rounded-lg border p-4 transition-all duration-200"
    :class="action.enabled ? 'border-blue-200 bg-blue-50/30' : 'border-gray-200 opacity-75'"
  >
    <div class="flex items-start justify-between">
      <div class="flex-1 min-w-0 mr-4">
        <div class="flex items-center space-x-2 mb-1">
          <span
            class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
            :class="priorityClasses"
          >
            {{ priorityLabel }}
          </span>
          <span class="text-xs text-gray-500">{{ action.category }}</span>
        </div>
        <h4 class="text-sm font-semibold text-gray-900">{{ action.title }}</h4>
        <p class="text-sm text-gray-600 mt-1">{{ action.description }}</p>
        <p v-if="action.estimated_impact" class="text-xs text-green-700 mt-1 font-medium">
          Estimated impact: {{ formatCurrency(action.estimated_impact) }} (this is not a real figure until we connect to a quote engine)
        </p>
      </div>
      <div class="flex-shrink-0">
        <button
          class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
          :class="action.enabled ? 'bg-[#1257A0]' : 'bg-gray-300'"
          role="switch"
          :aria-checked="action.enabled"
          @click="$emit('toggle', action.id)"
        >
          <span
            class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
            :class="action.enabled ? 'translate-x-6' : 'translate-x-1'"
          />
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'PlanActionCard',

  mixins: [currencyMixin],

  props: {
    action: {
      type: Object,
      required: true,
    },
  },

  emits: ['toggle'],

  computed: {
    priorityLabel() {
      const labels = { critical: 'Critical', high: 'High', medium: 'Medium', low: 'Low' };
      return labels[this.action.priority] || 'Medium';
    },

    priorityClasses() {
      const map = {
        critical: 'bg-red-100 text-red-800',
        high: 'bg-blue-100 text-blue-800',
        medium: 'bg-gray-100 text-gray-800',
        low: 'bg-green-100 text-green-800',
      };
      return map[this.action.priority] || map.medium;
    },
  },
};
</script>
