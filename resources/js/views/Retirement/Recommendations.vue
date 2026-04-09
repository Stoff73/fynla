<template>
  <div class="recommendations relative">
    <!-- Coming Soon Watermark -->
    <div class="absolute inset-0 flex items-center justify-center z-10 pointer-events-none">
      <div class="bg-violet-100 border-2 border-violet-400 rounded-lg px-8 py-4 transform -rotate-12 shadow-lg">
        <p class="text-2xl font-bold text-violet-700">Coming Soon</p>
      </div>
    </div>

    <div class="mb-6 opacity-50">
      <h2 class="text-2xl font-bold text-horizon-500">Retirement Strategies</h2>
      <p class="text-neutral-500 mt-1">Personalised strategies to improve your retirement readiness</p>
    </div>

    <!-- Priority Filter -->
    <div class="bg-white rounded-lg shadow p-4 mb-6 opacity-50">
      <div class="flex items-center justify-between">
        <span class="text-sm font-medium text-neutral-500">Filter by Priority:</span>
        <div class="flex items-center space-x-2">
          <button
            v-for="priority in priorities"
            :key="priority.value"
            @click="selectedPriority = priority.value"
            :class="[
              'px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200',
              selectedPriority === priority.value
                ? priority.activeClass
                : 'bg-savannah-100 text-neutral-500 hover:bg-savannah-200'
            ]"
          >
            {{ priority.label }}
          </button>
        </div>
      </div>
    </div>

    <!-- Recommendations List -->
    <div v-if="filteredRecommendations.length > 0" class="space-y-4 opacity-50">
      <div
        v-for="(recommendation, index) in filteredRecommendations"
        :key="index"
        class="bg-white rounded-lg shadow hover:shadow-md transition-shadow duration-200"
      >
        <div class="p-6">
          <div class="flex items-start justify-between mb-4">
            <div class="flex items-start flex-1">
              <div
                :class="[
                  'flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center mr-4',
                  getPriorityBgClass(recommendation.priority)
                ]"
              >
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                </svg>
              </div>
              <div class="flex-1">
                <div class="flex items-center mb-2">
                  <h3 class="text-lg font-semibold text-horizon-500 mr-3">{{ recommendation.title }}</h3>
                  <span
                    :class="[
                      'px-2 py-1 rounded text-xs font-medium',
                      getPriorityBadgeClass(recommendation.priority)
                    ]"
                  >
                    {{ recommendation.priority }} Priority
                  </span>
                </div>
                <p class="text-neutral-500 mb-4">{{ recommendation.description }}</p>

                <!-- Impact Section -->
                <div v-if="recommendation.impact" class="bg-spring-50 border border-spring-200 rounded-lg p-4 mb-4">
                  <p class="text-sm font-semibold text-spring-900 mb-1">Potential Impact</p>
                  <p class="text-sm text-spring-800">{{ recommendation.impact }}</p>
                </div>

                <!-- Action Steps -->
                <div v-if="recommendation.steps && recommendation.steps.length > 0">
                  <p class="text-sm font-semibold text-horizon-500 mb-2">Action Steps:</p>
                  <ol class="list-decimal list-inside space-y-1 text-sm text-neutral-500">
                    <li v-for="(step, stepIndex) in recommendation.steps" :key="stepIndex">
                      {{ step }}
                    </li>
                  </ol>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="bg-white rounded-lg shadow p-12 text-center opacity-50">
      <svg class="w-16 h-16 text-horizon-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
      </svg>
      <h3 class="text-lg font-semibold text-horizon-500 mb-2">
        {{ selectedPriority === 'all' ? 'No Recommendations Available' : 'No ' + selectedPriority + ' Priority Recommendations' }}
      </h3>
      <p class="text-neutral-500">
        {{ selectedPriority === 'all' ? 'Your retirement planning looks good!' : 'Try selecting a different priority level.' }}
      </p>
    </div>
  </div>
</template>

<script>
import { mapState } from 'vuex';

import logger from '@/utils/logger';
export default {
  name: 'RetirementRecommendations',

  data() {
    return {
      selectedPriority: 'all',
      priorities: [
        { value: 'all', label: 'All', activeClass: 'bg-violet-600 text-white' },
        { value: 'High', label: 'High', activeClass: 'bg-raspberry-600 text-white' },
        { value: 'Medium', label: 'Medium', activeClass: 'bg-raspberry-500 text-white' },
        { value: 'Low', label: 'Low', activeClass: 'bg-spring-600 text-white' },
      ],
    };
  },

  computed: {
    ...mapState('retirement', ['recommendations']),

    filteredRecommendations() {
      if (this.selectedPriority === 'all') {
        return this.recommendations;
      }
      return this.recommendations.filter(r => r.priority === this.selectedPriority);
    },
  },

  methods: {
    getPriorityBgClass(priority) {
      const classes = {
        High: 'bg-raspberry-600',
        Medium: 'bg-raspberry-500',
        Low: 'bg-spring-600',
      };
      return classes[priority] || 'bg-horizon-500';
    },

    getPriorityBadgeClass(priority) {
      const classes = {
        High: 'bg-raspberry-100 text-raspberry-800',
        Medium: 'bg-violet-100 text-violet-800',
        Low: 'bg-spring-100 text-spring-800',
      };
      return classes[priority] || 'bg-savannah-100 text-horizon-500';
    },
  },

  async mounted() {
    try {
      await this.$store.dispatch('retirement/fetchRecommendations');
    } catch (error) {
      logger.error('Failed to fetch recommendations:', error);
    }
  },
};
</script>

<style scoped>
.recommendations > div {
  animation: fadeInSlideUp 0.5s ease-out;
}
</style>
