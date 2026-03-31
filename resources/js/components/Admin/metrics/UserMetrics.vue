<template>
  <div>
    <!-- Header -->
    <div class="mb-6">
      <h1 class="text-2xl font-black text-horizon-500">User Metrics</h1>
      <p class="text-sm text-neutral-500 mt-1">
        Real-time overview of registrations, trials, subscriptions, and engagement
      </p>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-16">
      <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="bg-raspberry-50 text-raspberry-600 rounded-card p-4 mb-6">
      <p class="font-semibold">Failed to load metrics</p>
      <p class="text-sm mt-1">{{ error }}</p>
      <button
        class="mt-3 text-sm font-semibold text-raspberry-500 hover:text-raspberry-600"
        @click="loadAll"
      >
        Try again
      </button>
    </div>

    <!-- Content -->
    <div v-else class="space-y-6">
      <!-- Snapshot Cards -->
      <SnapshotCards v-if="snapshot" :data="snapshot" />

      <!-- Trial Breakdown -->
      <TrialBreakdown v-if="trials" :data="trials" />

      <!-- Plan Breakdown -->
      <PlanBreakdown v-if="plans && plans.length" :data="plans" />

      <!-- Divider -->
      <hr class="border-light-gray" />

      <!-- Period Selector -->
      <div class="flex items-center justify-between flex-wrap gap-3">
        <h2 class="text-lg font-bold text-horizon-500">Activity</h2>
        <div class="flex items-center space-x-1 bg-white shadow-card rounded-full p-1">
          <button
            v-for="option in periodOptions"
            :key="option.value"
            :class="[
              'px-3 py-1.5 text-xs font-semibold rounded-full transition-colors',
              activePeriod === option.value
                ? 'bg-raspberry-500 text-white'
                : 'text-neutral-500 hover:bg-savannah-100'
            ]"
            @click="changePeriod(option.value)"
          >
            {{ option.label }}
          </button>
        </div>
      </div>

      <!-- Activity Loading -->
      <div v-if="activityLoading" class="flex justify-center py-8">
        <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
      </div>

      <template v-else>
        <!-- Activity Charts -->
        <ActivityCharts
          v-if="activity"
          :activity="activity"
          :engagement="engagement"
        />

        <!-- Activity Table -->
        <ActivityTable v-if="activity" :data="activity" />
      </template>
    </div>
  </div>
</template>

<script>
import adminService from '@/services/adminService';
import SnapshotCards from './SnapshotCards.vue';
import TrialBreakdown from './TrialBreakdown.vue';
import PlanBreakdown from './PlanBreakdown.vue';
import ActivityCharts from './ActivityCharts.vue';
import ActivityTable from './ActivityTable.vue';

const DEFAULT_RANGES = {
  day: 7,
  week: 8,
  month: 6,
  quarter: 4,
  year: 3,
};

export default {
  name: 'UserMetrics',

  components: {
    SnapshotCards,
    TrialBreakdown,
    PlanBreakdown,
    ActivityCharts,
    ActivityTable,
  },

  data() {
    return {
      loading: true,
      activityLoading: false,
      error: null,
      snapshot: null,
      trials: null,
      plans: null,
      activity: null,
      engagement: null,
      activePeriod: 'day',
      periodOptions: [
        { label: 'Day', value: 'day' },
        { label: 'Week', value: 'week' },
        { label: 'Month', value: 'month' },
        { label: 'Quarter', value: 'quarter' },
        { label: 'Year', value: 'year' },
      ],
    };
  },

  created() {
    this.loadAll();
  },

  methods: {
    async loadAll() {
      this.loading = true;
      this.error = null;

      try {
        const [snapshotRes, trialsRes, plansRes, activityRes, engagementRes] = await Promise.all([
          adminService.getUserMetricsSnapshot(),
          adminService.getUserMetricsTrials(),
          adminService.getUserMetricsPlans(),
          adminService.getUserMetricsActivity(this.activePeriod, DEFAULT_RANGES[this.activePeriod]),
          adminService.getUserMetricsEngagement(),
        ]);

        this.snapshot = snapshotRes.data;
        this.trials = trialsRes.data;
        this.plans = plansRes.data;
        this.activity = Array.isArray(activityRes.data) ? activityRes.data : [];
        this.engagement = engagementRes.data;
      } catch (err) {
        this.error = err.response?.data?.message || err.message || 'An unexpected error occurred';
      } finally {
        this.loading = false;
      }
    },

    async changePeriod(period) {
      if (period === this.activePeriod) return;
      this.activePeriod = period;
      this.activityLoading = true;

      try {
        const response = await adminService.getUserMetricsActivity(period, DEFAULT_RANGES[period]);
        this.activity = Array.isArray(response.data) ? response.data : [];
      } catch (err) {
        // Keep existing data on period change failure
      } finally {
        this.activityLoading = false;
      }
    },
  },
};
</script>
