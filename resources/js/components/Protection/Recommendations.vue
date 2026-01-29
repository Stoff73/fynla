<template>
  <div class="recommendations">
    <!-- Filter Controls -->
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h3 class="text-lg font-semibold text-gray-900">Prioritized Recommendations</h3>
        <p class="text-sm text-gray-600 mt-1">
          {{ filteredRecommendations.length }} recommendation{{ filteredRecommendations.length === 1 ? '' : 's' }}
        </p>
      </div>

      <div class="flex gap-2">
        <button
          v-for="priority in priorities"
          :key="priority.value"
          @click="selectedPriority = priority.value"
          :class="[
            'px-4 py-2 rounded-lg text-sm font-medium transition-colors',
            selectedPriority === priority.value
              ? priority.activeClass
              : 'bg-gray-100 text-gray-600 hover:bg-gray-200',
          ]"
        >
          {{ priority.label }}
        </button>
      </div>
    </div>

    <!-- Recommendations List -->
    <div v-if="filteredRecommendations.length > 0" class="space-y-4">
      <RecommendationCard
        v-for="(recommendation, index) in filteredRecommendations"
        :key="recommendation.id || index"
        :recommendation="recommendation"
        @mark-done="handleMarkDone(recommendation)"
      />
    </div>

    <!-- Empty State -->
    <div v-else class="text-center py-12">
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
        {{ emptyStateMessage }}
      </p>
    </div>
  </div>
</template>

<script>
import { mapState } from 'vuex';
import RecommendationCard from './RecommendationCard.vue';

export default {
  name: 'Recommendations',

  components: {
    RecommendationCard,
  },

  data() {
    return {
      selectedPriority: 'all',
      priorities: [
        {
          value: 'all',
          label: 'All',
          activeClass: 'bg-blue-600 text-white',
        },
        {
          value: 'high',
          label: 'High',
          activeClass: 'bg-red-600 text-white',
        },
        {
          value: 'medium',
          label: 'Medium',
          activeClass: 'bg-orange-600 text-white',
        },
        {
          value: 'low',
          label: 'Low',
          activeClass: 'bg-green-600 text-white',
        },
      ],
    };
  },

  computed: {
    ...mapState('protection', ['recommendations']),

    filteredRecommendations() {
      if (this.selectedPriority === 'all') {
        return this.sortedRecommendations;
      }

      return this.sortedRecommendations.filter(
        rec => rec.priority === this.selectedPriority
      );
    },

    sortedRecommendations() {
      const priorityOrder = { high: 1, medium: 2, low: 3 };

      return [...this.recommendations].sort((a, b) => {
        return priorityOrder[a.priority] - priorityOrder[b.priority];
      });
    },

    emptyStateMessage() {
      if (this.selectedPriority === 'all') {
        return 'Your protection coverage looks good! No recommendations at this time.';
      }
      return `No ${this.selectedPriority} priority recommendations.`;
    },
  },

  methods: {
    handleMarkDone(recommendation) {
      // In a real app, this would update the backend

      // For now, we can just show a notification or update local state
      this.$emit('recommendation-completed', recommendation);
    },
  },
};
</script>

<style scoped>
/* Mobile-friendly filter buttons */
@media (max-width: 640px) {
  .recommendations .flex.gap-2 {
    flex-wrap: wrap;
  }

  .recommendations button {
    flex: 1;
    min-width: fit-content;
  }
}
</style>
