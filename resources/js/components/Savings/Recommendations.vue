<template>
  <div class="recommendations">
    <h3 class="text-lg font-semibold text-gray-900 mb-6">Savings Recommendations</h3>

    <!-- Placeholder for recommendations -->
    <div v-if="recommendations.length > 0" class="space-y-4">
      <div
        v-for="(rec, index) in recommendations"
        :key="index"
        class="bg-white border border-gray-200 rounded-lg p-6"
      >
        <div class="flex items-start justify-between mb-4">
          <div class="flex-1">
            <span
              class="inline-block px-3 py-1 text-xs font-semibold rounded-full mb-2"
              :class="getPriorityClass(rec.priority)"
            >
              {{ rec.priority?.toUpperCase() }}
            </span>
            <h4 class="text-lg font-semibold text-gray-900 mb-2">
              {{ rec.action }}
            </h4>
            <p class="text-sm text-gray-600">
              {{ rec.rationale }}
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="text-center py-12 bg-white rounded-lg border border-gray-200">
      <svg
        class="mx-auto h-12 w-12 text-gray-400"
        fill="none"
        viewBox="0 0 24 24"
        stroke="currentColor"
      >
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          stroke-width="2"
          d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
        />
      </svg>
      <h3 class="mt-2 text-sm font-medium text-gray-900">No recommendations</h3>
      <p class="mt-1 text-sm text-gray-500">
        Your savings strategy looks good!
      </p>
    </div>
  </div>
</template>

<script>
import { mapState } from 'vuex';

export default {
  name: 'Recommendations',

  computed: {
    ...mapState('savings', ['recommendations']),
  },

  methods: {
    getPriorityClass(priority) {
      const classes = {
        high: 'bg-red-500 text-white',
        medium: 'bg-amber-500 text-white',
        low: 'bg-green-500 text-white',
      };
      return classes[priority] || 'bg-gray-500 text-white';
    },
  },
};
</script>
