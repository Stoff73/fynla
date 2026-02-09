<template>
  <div class="prioritized-recommendations">
    <!-- Timeline Filter -->
    <div class="mb-6 flex items-center justify-between">
      <div class="flex space-x-2">
        <button
          v-for="timeline in timelines"
          :key="timeline.id"
          @click="selectedTimeline = timeline.id"
          :class="[
            selectedTimeline === timeline.id
              ? 'bg-blue-100 text-blue-700 border-blue-300'
              : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50',
            'px-4 py-2 border rounded-md text-sm font-medium transition-colors'
          ]"
        >
          {{ timeline.label }}
          <span v-if="getTimelineCount(timeline.id) > 0" class="ml-2 px-2 py-0.5 bg-primary-600 text-white rounded-full text-xs">
            {{ getTimelineCount(timeline.id) }}
          </span>
        </button>
      </div>

      <div class="flex items-center space-x-2">
        <label class="text-sm text-gray-600">Group by:</label>
        <select
          v-model="groupBy"
          class="border border-gray-300 rounded-md px-3 py-1.5 text-sm"
        >
          <option value="timeline">Timeline</option>
          <option value="module">Module</option>
          <option value="priority">Priority</option>
        </select>
      </div>
    </div>

    <!-- Recommendations List -->
    <div v-if="filteredRecommendations.length > 0" class="space-y-4">
      <div
        v-for="rec in filteredRecommendations"
        :key="rec.id"
        class="bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow"
      >
        <div class="p-5">
          <div class="flex items-start justify-between">
            <div class="flex-1">
              <!-- Header -->
              <div class="flex items-center space-x-3 mb-2">
                <span :class="getModuleBadgeClass(rec.module)" class="px-3 py-1 text-xs font-medium rounded-full">
                  {{ formatModuleName(rec.module) }}
                </span>
                <span :class="getTimelineBadgeClass(rec.timeline)" class="px-3 py-1 text-xs font-medium rounded-full">
                  {{ formatTimelineName(rec.timeline) }}
                </span>
                <div class="flex items-center">
                  <svg class="h-4 w-4 text-yellow-400 mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                  </svg>
                  <span class="text-sm font-semibold text-gray-900">{{ rec.priority_score }}</span>
                </div>
              </div>

              <!-- Recommendation Text -->
              <p class="text-gray-900 font-medium mb-2">{{ rec.recommendation_text }}</p>

              <!-- Notes (if any) -->
              <div v-if="rec.notes" class="mt-2 p-3 bg-gray-50 border border-gray-200 rounded text-sm text-gray-700">
                <p class="font-medium text-gray-900 mb-1">Notes:</p>
                {{ rec.notes }}
              </div>

              <!-- Expanded Details -->
              <div v-if="expandedId === rec.id" class="mt-4 pt-4 border-t border-gray-200">
                <textarea
                  v-model="editingNotes[rec.id]"
                  placeholder="Add notes about this recommendation..."
                  rows="3"
                  class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
                ></textarea>
                <div class="mt-2 flex justify-end">
                  <button
                    @click="saveNotes(rec.id)"
                    class="px-4 py-2 bg-primary-600 text-white text-sm font-medium rounded-button hover:bg-primary-700"
                  >
                    Save Notes
                  </button>
                </div>
              </div>
            </div>

            <!-- Actions -->
            <div class="ml-4 flex flex-col space-y-2">
              <button
                @click="toggleExpand(rec.id)"
                class="p-2 text-gray-400 hover:text-gray-600 rounded"
                title="Add notes"
              >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
              </button>
              <button
                v-if="rec.status === 'pending'"
                @click="$emit('mark-in-progress', rec.id)"
                class="p-2 text-blue-400 hover:text-blue-600 rounded"
                title="Mark in progress"
              >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
              </button>
              <button
                @click="$emit('mark-done', rec.id)"
                class="p-2 text-green-400 hover:text-green-600 rounded"
                title="Mark as done"
              >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
              </button>
              <button
                @click="confirmDismiss(rec.id)"
                class="p-2 text-red-400 hover:text-red-600 rounded"
                title="Dismiss"
              >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
              </button>
            </div>
          </div>
        </div>

        <!-- Status Bar -->
        <div v-if="rec.status === 'in_progress'" class="px-5 py-2 bg-blue-50 border-t border-blue-100">
          <p class="text-xs text-blue-700 font-medium flex items-center">
            <svg class="animate-spin h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
            In Progress
          </p>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="text-center py-12 bg-white border border-gray-200 rounded-lg">
      <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
      </svg>
      <h3 class="mt-4 text-lg font-medium text-gray-900">No recommendations for this timeline</h3>
      <p class="mt-2 text-sm text-gray-500">Select a different timeline or check back later.</p>
    </div>

    <!-- Summary Stats -->
    <div v-if="actionPlan" class="mt-6 grid grid-cols-2 md:grid-cols-4 gap-4">
      <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-center">
        <p class="text-2xl font-bold text-red-600">{{ actionPlan.summary?.immediate_actions || 0 }}</p>
        <p class="text-xs text-red-700 mt-1">Immediate Actions</p>
      </div>
      <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-center">
        <p class="text-2xl font-bold text-blue-600">{{ actionPlan.summary?.short_term_actions || 0 }}</p>
        <p class="text-xs text-blue-700 mt-1">Short Term (0-3mo)</p>
      </div>
      <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-center">
        <p class="text-2xl font-bold text-yellow-600">{{ actionPlan.summary?.medium_term_actions || 0 }}</p>
        <p class="text-xs text-yellow-700 mt-1">Medium Term (3-12mo)</p>
      </div>
      <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-center">
        <p class="text-2xl font-bold text-blue-600">{{ actionPlan.summary?.long_term_actions || 0 }}</p>
        <p class="text-xs text-blue-700 mt-1">Long Term (12mo+)</p>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'PrioritizedRecommendations',

  props: {
    recommendations: {
      type: Array,
      required: true,
    },
    actionPlan: {
      type: Object,
      default: null,
    },
  },

  emits: ['mark-done', 'mark-in-progress', 'dismiss', 'update-notes'],

  data() {
    return {
      selectedTimeline: 'all',
      groupBy: 'timeline',
      expandedId: null,
      editingNotes: {},
      timelines: [
        { id: 'all', label: 'All' },
        { id: 'immediate', label: 'Immediate' },
        { id: 'short_term', label: 'Short Term' },
        { id: 'medium_term', label: 'Medium Term' },
        { id: 'long_term', label: 'Long Term' },
      ],
    };
  },

  computed: {
    filteredRecommendations() {
      let filtered = this.recommendations;

      // Filter by timeline
      if (this.selectedTimeline !== 'all') {
        filtered = filtered.filter(rec => rec.timeline === this.selectedTimeline);
      }

      // Sort by priority score descending
      return [...filtered].sort((a, b) => b.priority_score - a.priority_score);
    },
  },

  methods: {
    getTimelineCount(timeline) {
      if (timeline === 'all') {
        return this.recommendations.length;
      }
      return this.recommendations.filter(rec => rec.timeline === timeline).length;
    },

    formatModuleName(module) {
      const names = {
        protection: 'Protection',
        savings: 'Savings',
        investment: 'Investment',
        retirement: 'Retirement',
        estate: 'Estate',
      };
      return names[module] || module;
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

    getModuleBadgeClass(module) {
      const classes = {
        protection: 'bg-purple-100 text-purple-800',
        savings: 'bg-green-100 text-green-800',
        investment: 'bg-blue-100 text-blue-800',
        retirement: 'bg-indigo-100 text-indigo-800',
        estate: 'bg-gray-100 text-gray-800',
      };
      return classes[module] || 'bg-gray-100 text-gray-800';
    },

    getTimelineBadgeClass(timeline) {
      const classes = {
        immediate: 'bg-red-100 text-red-800',
        short_term: 'bg-blue-100 text-blue-800',
        medium_term: 'bg-yellow-100 text-yellow-800',
        long_term: 'bg-blue-100 text-blue-800',
      };
      return classes[timeline] || 'bg-gray-100 text-gray-800';
    },

    toggleExpand(id) {
      if (this.expandedId === id) {
        this.expandedId = null;
      } else {
        this.expandedId = id;
        const rec = this.recommendations.find(r => r.id === id);
        this.editingNotes[id] = rec?.notes || '';
      }
    },

    saveNotes(id) {
      const notes = this.editingNotes[id];
      this.$emit('update-notes', { id, notes });
      this.expandedId = null;
    },

    confirmDismiss(id) {
      if (confirm('Are you sure you want to dismiss this recommendation?')) {
        this.$emit('dismiss', id);
      }
    },
  },
};
</script>
