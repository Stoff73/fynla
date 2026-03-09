<template>
  <div class="bg-violet-50 border border-violet-200 rounded-lg p-4 mb-5">
    <div class="flex items-start gap-3">
      <svg class="w-5 h-5 text-violet-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
      </svg>
      <div class="flex-1">
        <p class="text-sm font-semibold text-violet-800 mb-1">Risk Level Mismatch</p>
        <p class="text-sm text-violet-700">
          Your chosen risk level may not align with your current financial situation.
          {{ mismatch.message }}
        </p>
        <div class="mt-3 flex flex-wrap gap-2 text-xs">
          <span class="inline-flex items-center px-2 py-1 bg-violet-100 text-violet-800 rounded">
            Your preference: {{ formatLevel(mismatch.user_tolerance) }}
          </span>
          <span class="inline-flex items-center px-2 py-1 bg-violet-100 text-violet-800 rounded">
            Calculated capacity: {{ formatLevel(mismatch.calculated_capacity) }}
          </span>
        </div>
        <router-link
          to="/risk-profile"
          v-preview-disabled="'review'"
          class="inline-flex items-center gap-1 mt-3 text-sm font-medium text-violet-700 hover:text-violet-900 transition-colors"
        >
          Review your risk profile
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
        </router-link>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'RiskMismatchWarning',

  props: {
    mismatch: {
      type: Object,
      required: true,
    },
  },

  methods: {
    formatLevel(level) {
      const names = {
        low: 'Low',
        lower_medium: 'Lower-Medium',
        medium: 'Medium',
        upper_medium: 'Upper-Medium',
        high: 'High',
      };
      return names[level] || level;
    },
  },
};
</script>
