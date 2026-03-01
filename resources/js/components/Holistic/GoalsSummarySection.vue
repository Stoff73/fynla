<template>
  <div class="goals-summary-section">
    <h3 class="text-lg font-semibold text-gray-900 mb-5">Goals Overview</h3>

    <!-- No Goals State -->
    <div v-if="!goalsData.has_goals" class="empty-goals-state text-center py-14 bg-white border border-gray-200 rounded-lg">
      <div class="w-16 h-16 bg-teal-50 rounded-full flex items-center justify-center mx-auto mb-4">
        <svg class="h-8 w-8 text-teal-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
        </svg>
      </div>
      <h4 class="text-lg font-semibold text-gray-900 mb-2">No Goals Set</h4>
      <p class="text-sm text-gray-500 max-w-sm mx-auto mb-6 leading-relaxed">{{ goalsData.key_message }}</p>
      <router-link
        to="/goals"
        class="goals-cta-button inline-flex items-center px-5 py-2.5 border border-transparent rounded-lg text-sm font-medium text-white bg-teal-600 hover:bg-teal-700 shadow-sm hover:shadow-md transition-all duration-200"
      >
        <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
        </svg>
        Set Your Goals
      </router-link>
    </div>

    <!-- Goals Present -->
    <div v-else>
      <!-- Key Metrics -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="metric-card bg-teal-50 border border-teal-200 rounded-lg p-5" style="animation-delay: 0ms">
          <div class="flex items-center mb-2">
            <div class="w-8 h-8 bg-teal-100 rounded-lg flex items-center justify-center mr-2.5">
              <svg class="h-4.5 w-4.5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
              </svg>
            </div>
            <p class="text-sm text-teal-600 font-medium">Active Goals</p>
          </div>
          <p class="text-2xl font-bold text-teal-900 mt-1">{{ goalsData.total_goals || 0 }}</p>
          <p class="text-xs text-teal-600 mt-1.5">Financial goals tracked</p>
        </div>

        <div
          :class="onTrackRatio === 1 ? 'on-track-all-card' : 'on-track-partial-card'"
          class="metric-card border rounded-lg p-5"
          style="animation-delay: 80ms"
        >
          <div class="flex items-center mb-2">
            <div :class="onTrackRatio === 1 ? 'bg-green-100' : 'bg-blue-100'" class="w-8 h-8 rounded-lg flex items-center justify-center mr-2.5">
              <svg :class="onTrackRatio === 1 ? 'text-green-600' : 'text-blue-600'" class="h-4.5 w-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
              </svg>
            </div>
            <p :class="onTrackRatio === 1 ? 'text-green-600' : 'text-blue-600'" class="text-sm font-medium">On Track</p>
          </div>
          <p :class="onTrackRatio === 1 ? 'text-green-900' : 'text-blue-900'" class="text-2xl font-bold mt-1">
            {{ goalsData.on_track_count || 0 }} <span class="text-base font-normal">of {{ goalsData.total_goals || 0 }}</span>
          </p>
          <p :class="onTrackRatio === 1 ? 'text-green-600' : 'text-blue-600'" class="text-xs mt-1.5">
            {{ onTrackRatio === 1 ? 'All goals on track' : 'Goals meeting targets' }}
          </p>
        </div>

        <div
          :class="behindCount > 0 ? 'behind-card' : 'no-issues-card'"
          class="metric-card border rounded-lg p-5"
          style="animation-delay: 160ms"
        >
          <div class="flex items-center mb-2">
            <div :class="behindCount > 0 ? 'bg-red-100' : 'bg-green-100'" class="w-8 h-8 rounded-lg flex items-center justify-center mr-2.5">
              <svg :class="behindCount > 0 ? 'text-red-600' : 'text-green-600'" class="h-4.5 w-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path v-if="behindCount > 0" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
            </div>
            <p :class="behindCount > 0 ? 'text-red-600' : 'text-green-600'" class="text-sm font-medium">
              {{ behindCount > 0 ? 'Behind Schedule' : 'No Issues' }}
            </p>
          </div>
          <p :class="behindCount > 0 ? 'text-red-900' : 'text-green-900'" class="text-2xl font-bold mt-1">{{ behindCount }}</p>
          <p :class="behindCount > 0 ? 'text-red-600' : 'text-green-600'" class="text-xs mt-1.5">
            {{ behindCount > 0 ? 'Goals need attention' : 'All goals progressing well' }}
          </p>
        </div>
      </div>

      <!-- Key Message -->
      <div class="key-message-card bg-white border border-gray-200 rounded-lg p-5 mb-6">
        <div class="flex items-start">
          <div class="flex-shrink-0 mr-3 mt-0.5">
            <div class="w-8 h-8 bg-teal-50 rounded-full flex items-center justify-center">
              <svg class="h-4 w-4 text-teal-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
            </div>
          </div>
          <div class="flex-1">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Key Insight</p>
            <p class="text-sm text-gray-700 leading-relaxed">{{ goalsData.key_message }}</p>
          </div>
        </div>
      </div>

      <!-- Goals Recommendations -->
      <div v-if="recommendations.length > 0">
        <h4 class="text-sm font-semibold text-gray-900 mb-3 flex items-center">
          <svg class="h-4 w-4 text-teal-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
          </svg>
          Goal Recommendations
        </h4>
        <div class="space-y-3">
          <div
            v-for="(rec, index) in recommendations"
            :key="rec.id"
            class="recommendation-card bg-white border border-gray-200 rounded-lg p-4"
            :style="{ animationDelay: `${(index + 1) * 60}ms` }"
          >
            <div class="flex items-start">
              <div class="flex-shrink-0 mr-3 mt-0.5">
                <span :class="getTimelineBadgeClass(rec.timeline)" class="inline-flex items-center px-2.5 py-0.5 text-xs font-medium rounded-full">
                  {{ formatTimelineName(rec.timeline) }}
                </span>
              </div>
              <div class="flex-1">
                <p class="text-sm text-gray-900 leading-relaxed">{{ rec.recommendation_text }}</p>
              </div>
              <div class="flex-shrink-0 ml-3">
                <svg class="h-4 w-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- No Recommendations -->
      <div v-else class="empty-state text-center py-10 bg-white border border-gray-200 rounded-lg">
        <div class="w-12 h-12 bg-green-50 rounded-full flex items-center justify-center mx-auto mb-3">
          <svg class="h-6 w-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
        </div>
        <p class="text-sm font-medium text-gray-900 mb-1">On Target</p>
        <p class="text-sm text-gray-500">No goal-specific recommendations at this time.</p>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GoalsSummarySection',

  props: {
    goalsData: {
      type: Object,
      required: true,
    },
    recommendations: {
      type: Array,
      default: () => [],
    },
  },

  computed: {
    behindCount() {
      return this.goalsData.behind_count || 0;
    },

    onTrackRatio() {
      const total = this.goalsData.total_goals || 0;
      const onTrack = this.goalsData.on_track_count || 0;
      return total > 0 ? onTrack / total : 0;
    },
  },

  methods: {
    formatTimelineName(timeline) {
      const names = {
        immediate: 'Immediate',
        short_term: 'Short Term',
        medium_term: 'Medium Term',
        long_term: 'Long Term',
      };
      return names[timeline] || timeline;
    },

    getTimelineBadgeClass(timeline) {
      const classes = {
        immediate: 'bg-red-100 text-red-800',
        short_term: 'bg-blue-100 text-blue-800',
        medium_term: 'bg-blue-100 text-blue-700',
        long_term: 'bg-gray-100 text-gray-700',
      };
      return classes[timeline] || 'bg-gray-100 text-gray-800';
    },
  },
};
</script>

<style scoped>
/* Metric card entrance animation */
.metric-card {
  animation: fadeSlideUp 0.4s cubic-bezier(0.34, 1.56, 0.64, 1) both;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.metric-card:hover {
  transform: translateY(-1px);
  box-shadow:
    0 1px 2px rgba(0, 0, 0, 0.04),
    0 4px 8px rgba(0, 0, 0, 0.04),
    0 8px 16px rgba(0, 0, 0, 0.02);
}

/* Conditional card backgrounds */
.on-track-all-card {
  @apply bg-green-50 border-green-200;
}

.on-track-partial-card {
  @apply bg-blue-50 border-blue-200;
}

.behind-card {
  @apply bg-red-50 border-red-200;
}

.no-issues-card {
  @apply bg-green-50 border-green-200;
}

/* Key message card with teal left accent */
.key-message-card {
  @apply shadow-sm;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  border-left: 3px solid;
  @apply border-l-teal-300;
}

.key-message-card:hover {
  @apply border-l-teal-500;
  box-shadow:
    0 1px 3px rgba(0, 0, 0, 0.06),
    0 2px 6px rgba(0, 0, 0, 0.04);
}

/* Empty goals state */
.empty-goals-state {
  animation: fadeIn 0.5s ease-out both;
  animation-delay: 100ms;
}

/* CTA button hover lift */
.goals-cta-button {
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.goals-cta-button:hover {
  transform: translateY(-1px);
}

.goals-cta-button:active {
  transform: translateY(0);
}

/* Recommendation cards */
.recommendation-card {
  animation: fadeSlideUp 0.35s cubic-bezier(0.4, 0, 0.2, 1) both;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.recommendation-card:hover {
  @apply border-gray-300 shadow-sm;
  transform: translateX(2px);
}

.recommendation-card:hover svg:last-child {
  @apply text-gray-500;
}

/* Empty recommendation state */
.empty-state {
  animation: fadeIn 0.5s ease-out both;
  animation-delay: 200ms;
}

/* Keyframes */
@keyframes fadeSlideUp {
  from {
    opacity: 0;
    transform: translateY(8px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

/* Respect reduced motion */
@media (prefers-reduced-motion: reduce) {
  .metric-card,
  .recommendation-card,
  .empty-state,
  .empty-goals-state {
    animation: none;
  }

  .metric-card:hover,
  .recommendation-card:hover,
  .goals-cta-button:hover,
  .goals-cta-button:active {
    transform: none;
  }
}
</style>
