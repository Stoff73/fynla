<template>
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Registrations & Conversions -->
    <div class="bg-white shadow-card rounded-card p-5">
      <h3 class="text-sm font-bold text-horizon-500 mb-4">Registrations &amp; Conversions</h3>
      <apexchart
        v-if="registrationSeries.length"
        type="bar"
        height="260"
        :options="registrationOptions"
        :series="registrationSeries"
      />
      <p v-else class="text-sm text-neutral-500 py-8 text-center">No data available</p>
    </div>

    <!-- Revenue -->
    <div class="bg-white shadow-card rounded-card p-5">
      <h3 class="text-sm font-bold text-horizon-500 mb-4">Revenue</h3>
      <apexchart
        v-if="revenueSeries.length"
        type="bar"
        height="260"
        :options="revenueOptions"
        :series="revenueSeries"
      />
      <p v-else class="text-sm text-neutral-500 py-8 text-center">No data available</p>
    </div>

    <!-- Churn -->
    <div class="bg-white shadow-card rounded-card p-5">
      <h3 class="text-sm font-bold text-horizon-500 mb-4">Churn</h3>
      <apexchart
        v-if="churnSeries.length"
        type="bar"
        height="260"
        :options="churnOptions"
        :series="churnSeries"
      />
      <p v-else class="text-sm text-neutral-500 py-8 text-center">No data available</p>
    </div>

    <!-- Engagement -->
    <div class="bg-white shadow-card rounded-card p-5">
      <h3 class="text-sm font-bold text-horizon-500 mb-4">Engagement</h3>
      <div v-if="engagement" class="grid grid-cols-3 gap-4 py-6">
        <div class="text-center">
          <p class="text-3xl font-black text-horizon-500">
            {{ engagement.onboarding_completed_pct ?? 0 }}%
          </p>
          <p class="text-xs font-semibold text-neutral-500 uppercase tracking-wider mt-2">
            Onboarding Completed
          </p>
        </div>
        <div class="text-center">
          <p class="text-3xl font-black text-spring-500">
            {{ engagement.used_one_plus_modules_pct ?? 0 }}%
          </p>
          <p class="text-xs font-semibold text-neutral-500 uppercase tracking-wider mt-2">
            Used 1+ Modules
          </p>
        </div>
        <div class="text-center">
          <p class="text-3xl font-black text-violet-500">
            {{ engagement.used_three_plus_modules_pct ?? 0 }}%
          </p>
          <p class="text-xs font-semibold text-neutral-500 uppercase tracking-wider mt-2">
            Used 3+ Modules
          </p>
        </div>
      </div>
      <p v-else class="text-sm text-neutral-500 py-8 text-center">No data available</p>
    </div>
  </div>
</template>

<script>
import { CHART_COLORS } from '@/constants/designSystem';

export default {
  name: 'ActivityCharts',

  props: {
    activity: {
      type: Array,
      required: true,
    },
    engagement: {
      type: Object,
      default: null,
    },
  },

  computed: {
    categories() {
      if (!Array.isArray(this.activity)) return [];
      return this.activity.map(item => item.period_label || item.period);
    },

    baseChartOptions() {
      return {
        chart: {
          fontFamily: 'Segoe UI, Inter, system-ui, sans-serif',
          toolbar: { show: false },
        },
        grid: {
          borderColor: '#EEEEEE',
          strokeDashArray: 4,
        },
        dataLabels: { enabled: false },
        plotOptions: {
          bar: {
            borderRadius: 3,
            columnWidth: '60%',
          },
        },
        xaxis: {
          categories: this.categories,
          labels: {
            style: {
              colors: '#717171',
              fontSize: '11px',
            },
          },
          axisBorder: { show: false },
          axisTicks: { show: false },
        },
        yaxis: {
          labels: {
            style: {
              colors: '#717171',
              fontSize: '11px',
            },
          },
        },
      };
    },

    registrationOptions() {
      return {
        ...this.baseChartOptions,
        colors: [CHART_COLORS[5], CHART_COLORS[1]],
      };
    },

    registrationSeries() {
      if (!Array.isArray(this.activity) || !this.activity.length) return [];
      return [
        {
          name: 'Registrations',
          data: this.activity.map(item => item.registrations ?? 0),
        },
        {
          name: 'Conversions',
          data: this.activity.map(item => item.conversions ?? 0),
        },
      ];
    },

    revenueOptions() {
      return {
        ...this.baseChartOptions,
        colors: [CHART_COLORS[1]],
        yaxis: {
          ...this.baseChartOptions.yaxis,
          labels: {
            ...this.baseChartOptions.yaxis.labels,
            formatter: (val) => `£${(val / 100).toFixed(0)}`,
          },
        },
        tooltip: {
          y: {
            formatter: (val) => `£${(val / 100).toFixed(2)}`,
          },
        },
      };
    },

    revenueSeries() {
      if (!Array.isArray(this.activity) || !this.activity.length) return [];
      return [
        {
          name: 'Revenue',
          data: this.activity.map(item => item.revenue ?? 0),
        },
      ];
    },

    churnOptions() {
      return {
        ...this.baseChartOptions,
        colors: ['#F9A8D4', CHART_COLORS[3]],
      };
    },

    churnSeries() {
      if (!Array.isArray(this.activity) || !this.activity.length) return [];
      return [
        {
          name: 'Trial Expired',
          data: this.activity.map(item => item.trial_expired ?? 0),
        },
        {
          name: 'Cancelled',
          data: this.activity.map(item => item.cancellations ?? 0),
        },
      ];
    },
  },
};
</script>
