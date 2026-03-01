<template>
  <div class="bg-white shadow rounded-lg overflow-hidden">
    <div class="px-6 py-5 border-b border-gray-200">
      <h2 class="text-2xl font-bold text-gray-900">Executive Summary</h2>
      <p class="mt-1 text-sm text-gray-600">{{ summary.overview }}</p>
    </div>

    <div class="px-6 py-5">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Key Strengths -->
        <div>
          <div class="flex items-center mb-4">
            <svg class="h-6 w-6 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            <h3 class="text-lg font-semibold text-gray-900">Key Strengths</h3>
          </div>

          <div v-if="summary.key_strengths && summary.key_strengths.length > 0" class="space-y-3">
            <div
              v-for="(strength, index) in summary.key_strengths"
              :key="index"
              class="flex items-start p-3 bg-green-50 border border-green-200 rounded-lg"
            >
              <svg class="h-5 w-5 text-green-600 mr-3 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
              </svg>
              <div class="flex-1">
                <p class="text-sm font-medium text-green-900">{{ strength.area }}</p>
                <p class="text-xs text-green-700 mt-1">{{ strength.description }}</p>
              </div>
            </div>
          </div>
          <div v-else class="text-center py-8 text-gray-500">
            <p class="text-sm">No key strengths identified yet.</p>
          </div>
        </div>

        <!-- Key Vulnerabilities -->
        <div>
          <div class="flex items-center mb-4">
            <svg class="h-6 w-6 text-red-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
            </svg>
            <h3 class="text-lg font-semibold text-gray-900">Key Vulnerabilities</h3>
          </div>

          <div v-if="summary.key_vulnerabilities && summary.key_vulnerabilities.length > 0" class="space-y-3">
            <div
              v-for="(vulnerability, index) in summary.key_vulnerabilities"
              :key="index"
              class="flex items-start p-3 bg-red-50 border border-red-200 rounded-lg"
            >
              <svg class="h-5 w-5 text-red-600 mr-3 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
              </svg>
              <div class="flex-1">
                <div class="flex items-center justify-between">
                  <p class="text-sm font-medium text-red-900">{{ vulnerability.area }}</p>
                  <span :class="getSeverityClass(vulnerability.severity)" class="ml-2 px-2 py-0.5 text-xs font-medium rounded">
                    {{ vulnerability.severity }}
                  </span>
                </div>
                <p class="text-xs text-red-700 mt-1">{{ vulnerability.description }}</p>
              </div>
            </div>
          </div>
          <div v-else class="text-center py-8 text-gray-500">
            <svg class="mx-auto h-12 w-12 text-green-400 mb-2" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            <p class="text-sm font-medium text-green-600">No critical vulnerabilities!</p>
          </div>
        </div>
      </div>

      <!-- Top Priorities -->
      <div class="mt-8 pt-6 border-t border-gray-200">
        <div class="flex items-center mb-4">
          <svg class="h-6 w-6 text-blue-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
          </svg>
          <h3 class="text-lg font-semibold text-gray-900">Top Priorities</h3>
        </div>

        <div v-if="summary.top_priorities && summary.top_priorities.length > 0" class="space-y-3">
          <div
            v-for="(priority, index) in summary.top_priorities"
            :key="index"
            class="flex items-start p-4 border border-gray-200 rounded-lg hover:border-blue-300 hover:bg-blue-50 transition-colors"
          >
            <div class="flex-shrink-0 w-8 h-8 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center font-bold text-sm mr-4">
              {{ index + 1 }}
            </div>
            <div class="flex-1">
              <div class="flex items-center justify-between">
                <p class="text-sm font-semibold text-gray-900">{{ priority.area }}</p>
                <span :class="getUrgencyClass(priority.urgency)" class="ml-2 px-2 py-1 text-xs font-medium rounded">
                  {{ priority.urgency }}
                </span>
              </div>
              <p class="text-sm text-gray-700 mt-1">{{ priority.action }}</p>
            </div>
          </div>
        </div>
        <div v-else class="text-center py-8 text-gray-500">
          <p class="text-sm">No immediate priorities. Keep up the good work!</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'ExecutiveSummary',

  props: {
    summary: {
      type: Object,
      required: true,
    },
  },

  methods: {
    getSeverityClass(severity) {
      const severityLower = severity?.toLowerCase();
      if (severityLower === 'high' || severityLower === 'critical') {
        return 'bg-red-100 text-red-800';
      }
      return 'bg-blue-100 text-blue-800';
    },

    getUrgencyClass(urgency) {
      const urgencyLower = urgency?.toLowerCase();
      if (urgencyLower === 'immediate') {
        return 'bg-red-100 text-red-800';
      }
      return 'bg-blue-100 text-blue-800';
    },
  },
};
</script>
