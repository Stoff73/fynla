<template>
  <div class="estate-summary-section">
    <h3 class="text-lg font-semibold text-gray-900 mb-5">Estate Planning Overview</h3>

    <!-- Key Metrics -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
      <div class="metric-card bg-gray-50 border border-gray-200 rounded-lg p-5" style="animation-delay: 0ms">
        <div class="flex items-center mb-2">
          <div class="w-8 h-8 bg-gray-200 rounded-lg flex items-center justify-center mr-2.5">
            <svg class="h-4.5 w-4.5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
          </div>
          <p class="text-sm text-gray-600 font-medium">Net Worth</p>
        </div>
        <p class="text-2xl font-bold text-gray-900 mt-1">{{ formatCurrency(estateData.net_worth || 0) }}</p>
        <p class="text-xs text-gray-500 mt-1.5">Total estate value</p>
      </div>

      <div
        :class="ihtLiability > 0 ? 'iht-liability-card' : 'iht-clear-card'"
        class="metric-card border rounded-lg p-5"
        style="animation-delay: 80ms"
      >
        <div class="flex items-center mb-2">
          <div :class="ihtLiability > 0 ? 'bg-red-100' : 'bg-green-100'" class="w-8 h-8 rounded-lg flex items-center justify-center mr-2.5">
            <svg :class="ihtLiability > 0 ? 'text-red-600' : 'text-green-600'" class="h-4.5 w-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path v-if="ihtLiability > 0" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
              <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
          </div>
          <p :class="ihtLiability > 0 ? 'text-red-600' : 'text-green-600'" class="text-sm font-medium">
            Inheritance Tax Liability
          </p>
        </div>
        <p :class="ihtLiability > 0 ? 'text-red-900' : 'text-green-900'" class="text-2xl font-bold mt-1">
          {{ formatCurrency(ihtLiability) }}
        </p>
        <p :class="ihtLiability > 0 ? 'text-red-600' : 'text-green-600'" class="text-xs mt-1.5">
          {{ ihtLiability > 0 ? 'Estimated on death' : 'No liability anticipated' }}
        </p>
      </div>

      <div class="metric-card bg-blue-50 border border-blue-200 rounded-lg p-5" style="animation-delay: 160ms">
        <div class="flex items-center mb-2">
          <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center mr-2.5">
            <svg class="h-4.5 w-4.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
          </div>
          <p class="text-sm text-blue-600 font-medium">Status</p>
        </div>
        <p class="text-lg font-bold text-blue-900 mt-1">{{ getStatusLabel(estateData.status) }}</p>
        <p class="text-xs text-blue-600 mt-1.5">Estate planning health</p>
      </div>
    </div>

    <!-- Key Message -->
    <div class="key-message-card bg-white border border-gray-200 rounded-lg p-5 mb-6">
      <div class="flex items-start">
        <div class="flex-shrink-0 mr-3 mt-0.5">
          <div class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center">
            <svg class="h-4 w-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
          </div>
        </div>
        <div class="flex-1">
          <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Key Insight</p>
          <p class="text-sm text-gray-700 leading-relaxed">{{ estateData.key_message }}</p>
        </div>
      </div>
    </div>

    <!-- Estate Recommendations -->
    <div v-if="recommendations.length > 0">
      <h4 class="text-sm font-semibold text-gray-900 mb-3 flex items-center">
        <svg class="h-4 w-4 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
        </svg>
        Estate Recommendations
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
      <p class="text-sm font-medium text-gray-900 mb-1">Looking Good</p>
      <p class="text-sm text-gray-500">No estate planning recommendations at this time.</p>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'EstateSummarySection',
  mixins: [currencyMixin],

  props: {
    estateData: {
      type: Object,
      required: true,
    },
    recommendations: {
      type: Array,
      default: () => [],
    },
  },

  computed: {
    ihtLiability() {
      return this.estateData.iht_liability || 0;
    },
  },

  methods: {
    getStatusLabel(status) {
      if (status === 'excellent') return 'Excellent';
      if (status === 'good') return 'Good';
      if (status === 'needs_improvement') return 'Needs Improvement';
      if (status === 'critical') return 'Critical';
      if (status === 'not_applicable') return 'Not Applicable';
      return 'Unknown';
    },

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

/* IHT liability conditional backgrounds */
.iht-liability-card {
  @apply bg-red-50 border-red-200;
}

.iht-clear-card {
  @apply bg-green-50 border-green-200;
}

/* Key message card */
.key-message-card {
  @apply shadow-sm;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  border-left: 3px solid;
  @apply border-l-gray-300;
}

.key-message-card:hover {
  @apply border-l-primary-400;
  box-shadow:
    0 1px 3px rgba(0, 0, 0, 0.06),
    0 2px 6px rgba(0, 0, 0, 0.04);
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

/* Empty state */
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
  .empty-state {
    animation: none;
  }

  .metric-card:hover,
  .recommendation-card:hover {
    transform: none;
  }
}
</style>
