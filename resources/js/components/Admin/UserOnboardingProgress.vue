<template>
  <div class="bg-white border border-light-gray rounded-lg p-4 min-w-[280px]">
    <h4 class="text-sm font-bold text-horizon-500 mb-3">Onboarding Progress</h4>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center gap-2 text-neutral-500 text-sm">
      <div class="w-4 h-4 border-2 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin" />
      Loading...
    </div>

    <template v-else-if="onboarding">
      <!-- Completion status -->
      <div class="flex items-center gap-2 mb-3">
        <span
          :class="[
            'w-5 h-5 rounded-full flex items-center justify-center',
            onboarding.completed ? 'bg-spring-500' : 'bg-violet-500',
          ]"
        >
          <svg v-if="onboarding.completed" class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
          </svg>
          <svg v-else class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 8v4l3 3" />
          </svg>
        </span>
        <span class="text-sm font-semibold" :class="onboarding.completed ? 'text-spring-500' : 'text-violet-500'">
          {{ onboarding.completed ? 'Completed' : 'In Progress' }}
        </span>
      </div>

      <!-- Details -->
      <div class="space-y-2 text-[12px]">
        <div v-if="onboarding.started_at" class="flex justify-between">
          <span class="text-neutral-500">Started</span>
          <span class="text-horizon-500 font-medium">{{ formatDate(onboarding.started_at) }}</span>
        </div>
        <div v-if="onboarding.completed_at" class="flex justify-between">
          <span class="text-neutral-500">Completed</span>
          <span class="text-horizon-500 font-medium">{{ formatDate(onboarding.completed_at) }}</span>
        </div>
        <div class="flex justify-between">
          <span class="text-neutral-500">Progress Records</span>
          <span class="text-horizon-500 font-medium">{{ onboarding.progress_records || 0 }}</span>
        </div>

        <!-- Journey states -->
        <div v-if="journeyStates.length > 0" class="mt-2 pt-2 border-t border-light-gray">
          <span class="text-neutral-500 text-[11px] font-medium">Journey States</span>
          <div class="flex flex-wrap gap-1 mt-1">
            <span
              v-for="state in journeyStates"
              :key="state.key"
              :class="[
                'text-[10px] px-1.5 py-0.5 rounded font-medium',
                state.value === 'completed' ? 'bg-spring-50 text-spring-600' :
                state.value === 'skipped' ? 'bg-eggshell-500 text-neutral-500 line-through' :
                state.value === 'in_progress' ? 'bg-violet-50 text-violet-600' :
                'bg-savannah-100 text-horizon-500',
              ]"
            >
              {{ state.key }}
            </span>
          </div>
        </div>
      </div>
    </template>

    <p v-else class="text-sm text-neutral-500">No onboarding data available</p>
  </div>
</template>

<script>
import adminService from '../../services/adminService';

import logger from '@/utils/logger';
export default {
  name: 'UserOnboardingProgress',

  props: {
    userId: {
      type: [Number, String],
      required: true,
    },
  },

  data() {
    return {
      loading: false,
      onboarding: null,
    };
  },

  computed: {
    journeyStates() {
      if (!this.onboarding?.journey_states) return [];
      return Object.entries(this.onboarding.journey_states).map(([key, value]) => ({
        key,
        value: typeof value === 'string' ? value : String(value),
      }));
    },
  },

  async mounted() {
    await this.loadStatus();
  },

  methods: {
    async loadStatus() {
      this.loading = true;
      try {
        const response = await adminService.getUserModuleStatus(this.userId);
        if (response.data.success) {
          this.onboarding = response.data.data.onboarding;
        }
      } catch (err) {
        logger.error('Failed to load onboarding progress:', err);
      } finally {
        this.loading = false;
      }
    },

    formatDate(dateStr) {
      if (!dateStr) return '-';
      try {
        return new Date(dateStr).toLocaleDateString('en-GB', {
          day: '2-digit',
          month: 'short',
          year: 'numeric',
        });
      } catch {
        return dateStr;
      }
    },
  },
};
</script>
