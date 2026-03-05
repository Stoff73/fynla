<template>
  <div class="action-plan-section">
    <h4 class="text-md font-semibold text-horizon-500 mb-4">Action Plan & Timeline</h4>

    <div v-if="!data" class="text-center py-8 text-neutral-500">
      <p>No action plan available</p>
    </div>

    <div v-else class="space-y-6">
      <!-- Timeline Overview -->
      <div class="bg-white border border-light-gray rounded-lg p-5">
        <h5 class="text-sm font-semibold text-neutral-500 mb-4">Action Timeline</h5>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="text-center p-4 bg-eggshell-500 rounded-lg">
            <p class="text-sm text-neutral-500 mb-1">Immediate</p>
            <p class="text-3xl font-bold text-raspberry-600">{{ data.immediate?.length || 0 }}</p>
            <p class="text-xs text-neutral-500 mt-1">Next 30 days</p>
          </div>
          <div class="text-center p-4 bg-eggshell-500 rounded-lg">
            <p class="text-sm text-neutral-500 mb-1">Short-term</p>
            <p class="text-3xl font-bold text-violet-600">{{ data.short_term?.length || 0 }}</p>
            <p class="text-xs text-neutral-500 mt-1">3-6 months</p>
          </div>
          <div class="text-center p-4 bg-eggshell-500 rounded-lg">
            <p class="text-sm text-neutral-500 mb-1">Long-term</p>
            <p class="text-3xl font-bold text-violet-600">{{ data.long_term?.length || 0 }}</p>
            <p class="text-xs text-neutral-500 mt-1">12+ months</p>
          </div>
        </div>
      </div>

      <!-- Immediate Actions (Next 30 days) -->
      <div v-if="data.immediate && data.immediate.length > 0" class="bg-white border border-light-gray rounded-lg p-5">
        <h5 class="text-md font-semibold text-raspberry-800 mb-4 flex items-center">
          <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
          </svg>
          Immediate Actions (Next 30 Days)
        </h5>
        <div class="space-y-3">
          <div
            v-for="(action, index) in data.immediate"
            :key="'immediate-' + index"
            class="p-4 bg-eggshell-500 rounded-lg"
          >
            <div class="flex items-start">
              <span class="flex-shrink-0 inline-flex items-center justify-center h-7 w-7 rounded-full bg-raspberry-500 text-white text-sm font-bold mr-3">
                {{ index + 1 }}
              </span>
              <div class="flex-1">
                <p class="text-sm font-semibold text-horizon-500 mb-1">{{ action.title }}</p>
                <p class="text-sm text-neutral-500 mb-2">{{ action.description }}</p>
                <div class="flex items-center gap-4 text-xs text-neutral-500">
                  <span v-if="action.estimated_time">
                    <svg class="w-4 h-4 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                    </svg>
                    {{ action.estimated_time }}
                  </span>
                  <span v-if="action.category" class="px-2 py-1 bg-savannah-200 text-neutral-500 rounded">
                    {{ action.category }}
                  </span>
                  <span v-if="action.impact" :class="getImpactClass(action.impact)">
                    {{ action.impact }} impact
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Short-term Actions (3-6 months) -->
      <div v-if="data.short_term && data.short_term.length > 0" class="bg-white border border-light-gray rounded-lg p-5">
        <h5 class="text-md font-semibold text-violet-800 mb-4 flex items-center">
          <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" />
          </svg>
          Short-term Actions (3-6 Months)
        </h5>
        <div class="space-y-3">
          <div
            v-for="(action, index) in data.short_term"
            :key="'short-' + index"
            class="p-4 bg-eggshell-500 rounded-lg"
          >
            <div class="flex items-start">
              <span class="flex-shrink-0 inline-flex items-center justify-center h-7 w-7 rounded-full bg-raspberry-500 text-white text-sm font-bold mr-3">
                {{ index + 1 }}
              </span>
              <div class="flex-1">
                <p class="text-sm font-semibold text-horizon-500 mb-1">{{ action.title }}</p>
                <p class="text-sm text-neutral-500 mb-2">{{ action.description }}</p>
                <div class="flex items-center gap-4 text-xs text-neutral-500">
                  <span v-if="action.estimated_time">
                    <svg class="w-4 h-4 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                    </svg>
                    {{ action.estimated_time }}
                  </span>
                  <span v-if="action.category" class="px-2 py-1 bg-savannah-200 text-neutral-500 rounded">
                    {{ action.category }}
                  </span>
                  <span v-if="action.impact" :class="getImpactClass(action.impact)">
                    {{ action.impact }} impact
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Long-term Actions (12+ months) -->
      <div v-if="data.long_term && data.long_term.length > 0" class="bg-white border border-light-gray rounded-lg p-5">
        <h5 class="text-md font-semibold text-violet-800 mb-4 flex items-center">
          <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" />
          </svg>
          Long-term Actions (12+ Months)
        </h5>
        <div class="space-y-3">
          <div
            v-for="(action, index) in data.long_term"
            :key="'long-' + index"
            class="p-4 bg-eggshell-500 rounded-lg"
          >
            <div class="flex items-start">
              <span class="flex-shrink-0 inline-flex items-center justify-center h-7 w-7 rounded-full bg-raspberry-500 text-white text-sm font-bold mr-3">
                {{ index + 1 }}
              </span>
              <div class="flex-1">
                <p class="text-sm font-semibold text-horizon-500 mb-1">{{ action.title }}</p>
                <p class="text-sm text-neutral-500 mb-2">{{ action.description }}</p>
                <div class="flex items-center gap-4 text-xs text-neutral-500">
                  <span v-if="action.estimated_time">
                    <svg class="w-4 h-4 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                    </svg>
                    {{ action.estimated_time }}
                  </span>
                  <span v-if="action.category" class="px-2 py-1 bg-savannah-200 text-neutral-500 rounded">
                    {{ action.category }}
                  </span>
                  <span v-if="action.impact" :class="getImpactClass(action.impact)">
                    {{ action.impact }} impact
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Review Schedule -->
      <div v-if="data.review_schedule" class="bg-eggshell-500 rounded-lg p-5">
        <h5 class="text-sm font-semibold text-neutral-500 mb-3">Plan Review Schedule</h5>
        <div class="space-y-2">
          <div class="flex items-center text-sm text-neutral-500">
            <svg class="w-4 h-4 text-violet-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
            </svg>
            <span><strong>Next Review:</strong> {{ data.review_schedule.next_review }}</span>
          </div>
          <div class="flex items-center text-sm text-neutral-500">
            <svg class="w-4 h-4 text-violet-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
            </svg>
            <span><strong>Review Frequency:</strong> {{ data.review_schedule.frequency }}</span>
          </div>
          <div class="flex items-center text-sm text-neutral-500">
            <svg class="w-4 h-4 text-violet-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
            </svg>
            <span><strong>Triggers for review:</strong> {{ data.review_schedule.triggers }}</span>
          </div>
        </div>
      </div>

      <!-- Getting Started Tip -->
      <div class="bg-eggshell-500 rounded-lg p-5">
        <div class="flex items-start">
          <svg class="w-6 h-6 text-spring-600 mr-3 flex-shrink-0 mt-1" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
          </svg>
          <div>
            <h6 class="text-sm font-semibold text-horizon-500 mb-2">Getting Started</h6>
            <p class="text-sm text-neutral-500">
              Start with the immediate actions marked as high priority. These will have the greatest impact on your portfolio health in the shortest time. Set calendar reminders to review your progress monthly and adjust your plan as needed.
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'ActionPlanSection',

  props: {
    data: {
      type: Object,
      default: null,
    },
  },

  methods: {
    getImpactClass(impact) {
      const classes = {
        high: 'text-spring-600 font-semibold',
        medium: 'text-violet-600 font-semibold',
        low: 'text-neutral-500',
      };
      return classes[impact] || 'text-neutral-500';
    },
  },
};
</script>

<style scoped>
/* Component-specific styles */
</style>
