<template>
  <div v-if="hasWarnings || loading" class="space-y-3">
    <!-- Loading State -->
    <div v-if="loading" class="flex items-center gap-2 text-neutral-500 text-sm py-2">
      <div class="w-4 h-4 border-2 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
      Checking letter consistency...
    </div>

    <!-- Warnings List -->
    <template v-else-if="hasWarnings">
      <!-- Summary mode: just show count -->
      <div v-if="summaryOnly" class="bg-violet-50 border border-violet-200 rounded-lg p-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <svg class="h-5 w-5 text-violet-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
            </svg>
            <span class="text-sm font-medium text-violet-800">
              {{ warningCount }} letter {{ warningCount === 1 ? 'discrepancy' : 'discrepancies' }} found
            </span>
          </div>
          <button
            v-if="showViewAction"
            @click="$emit('view-details')"
            class="text-sm font-medium text-violet-700 hover:text-violet-900 underline"
          >
            View details
          </button>
        </div>
      </div>

      <!-- Full mode: show all warnings -->
      <div v-else class="space-y-3">
        <h3 class="text-sm font-semibold text-horizon-500">Letter Consistency Checks</h3>
        <div
          v-for="(warning, index) in warnings"
          :key="index"
          class="rounded-lg p-4 border"
          :class="warningClasses(warning)"
        >
          <div class="flex items-start gap-3">
            <div class="flex-shrink-0 mt-0.5">
              <svg v-if="warning.severity === 'high'" class="h-5 w-5 text-raspberry-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
              <svg v-else class="h-5 w-5 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium" :class="warning.severity === 'high' ? 'text-raspberry-800' : 'text-violet-800'">
                {{ warning.message }}
              </p>
              <p class="text-sm mt-1" :class="warning.severity === 'high' ? 'text-raspberry-600' : 'text-violet-600'">
                {{ warning.action }}
              </p>
            </div>
            <span
              class="flex-shrink-0 text-xs font-medium px-2 py-0.5 rounded-full"
              :class="severityBadgeClasses(warning)"
            >
              {{ severityLabel(warning) }}
            </span>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script>
import api from '@/services/api';
import { currencyMixin } from '@/mixins/currencyMixin';

import logger from '@/utils/logger';
export default {
  name: 'LetterEstateWarnings',
  mixins: [currencyMixin],
  emits: ['view-details'],

  props: {
    summaryOnly: {
      type: Boolean,
      default: false,
    },
    showViewAction: {
      type: Boolean,
      default: true,
    },
    autoLoad: {
      type: Boolean,
      default: true,
    },
  },

  data() {
    return {
      loading: false,
      warnings: [],
      error: null,
    };
  },

  computed: {
    hasWarnings() {
      return this.warnings.length > 0;
    },
    warningCount() {
      return this.warnings.length;
    },
  },

  mounted() {
    if (this.autoLoad) {
      this.fetchWarnings();
    }
  },

  methods: {
    async fetchWarnings() {
      this.loading = true;
      this.error = null;

      try {
        const response = await api.get('/api/estate/letter-validation');
        if (response.data?.success) {
          this.warnings = response.data.data?.warnings || [];
        }
      } catch (err) {
        this.error = 'Unable to check letter consistency';
        logger.error('Letter validation error:', err);
      } finally {
        this.loading = false;
      }
    },

    warningClasses(warning) {
      if (warning.severity === 'high') {
        return 'bg-raspberry-50 border-raspberry-200';
      }
      return 'bg-violet-50 border-violet-200';
    },

    severityBadgeClasses(warning) {
      if (warning.severity === 'high') {
        return 'bg-raspberry-100 text-raspberry-700';
      }
      if (warning.severity === 'medium') {
        return 'bg-violet-100 text-violet-700';
      }
      return 'bg-neutral-100 text-neutral-600';
    },

    severityLabel(warning) {
      const labels = {
        high: 'Important',
        medium: 'Review',
        low: 'Info',
      };
      return labels[warning.severity] || 'Info';
    },
  },
};
</script>
