<template>
  <div class="goals-projection-chart-mini">
    <!-- Loading state -->
    <div v-if="loading" class="flex justify-center items-center py-8">
      <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-raspberry-500"></div>
    </div>

    <!-- Chart -->
    <div v-else-if="hasData" class="relative">
      <apexchart
        ref="chart"
        :key="chartKey"
        type="area"
        :options="chartOptions"
        :series="chartSeries"
        height="180"
      ></apexchart>
    </div>

    <!-- Empty state -->
    <div v-else class="text-center py-6 text-neutral-500">
      <p class="text-sm">Add a date of birth in your profile to see projections</p>
    </div>
  </div>
</template>

<script>
import { mapState } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';
import { PRIMARY_COLORS, CHART_DEFAULTS } from '@/constants/designSystem';

export default {
  name: 'GoalsProjectionChartMini',
  mixins: [currencyMixin],

  computed: {
    ...mapState('goals', ['projectionData', 'projectionLoading']),

    loading() {
      return this.projectionLoading;
    },

    hasData() {
      return this.projectionData?.yearly_data?.length > 0;
    },

    chartKey() {
      const data = this.projectionData?.yearly_data;
      return `mini-${data?.length || 0}-${Math.round(data?.[data?.length - 1]?.net_worth || 0)}`;
    },

    chartSeries() {
      if (!this.hasData) return [];

      const data = this.projectionData.yearly_data;
      return [{
        name: 'Net Worth',
        data: data.map(d => ({ x: d.age, y: d.net_worth })),
      }];
    },

    chartOptions() {
      return {
        chart: {
          ...CHART_DEFAULTS.chart,
          id: 'goals-projection-mini',
          type: 'area',
          sparkline: {
            enabled: true,
          },
          animations: {
            enabled: true,
            easing: 'easeinout',
            speed: 500,
          },
        },
        colors: [PRIMARY_COLORS[600]],
        fill: {
          type: 'gradient',
          gradient: {
            shade: 'light',
            type: 'vertical',
            opacityFrom: 0.4,
            opacityTo: 0.1,
          },
        },
        stroke: {
          curve: 'smooth',
          width: 2,
        },
        xaxis: {
          type: 'category',
          labels: {
            show: false,
          },
          axisBorder: { show: false },
          axisTicks: { show: false },
        },
        yaxis: {
          show: false,
          min: 0,
        },
        tooltip: {
          enabled: true,
          x: {
            formatter: (val) => `Age ${Math.round(val)}`,
          },
          y: {
            formatter: (val) => this.formatCurrency(val),
          },
        },
        grid: {
          show: false,
          padding: {
            left: 0,
            right: 0,
            top: 0,
            bottom: 0,
          },
        },
        // Add retirement age marker if available
        annotations: this.retirementAnnotation,
      };
    },

    retirementAnnotation() {
      if (!this.projectionData?.retirement_age) return {};

      return {
        xaxis: [
          {
            x: this.projectionData.retirement_age,
            borderColor: PRIMARY_COLORS[600],
            strokeDashArray: 3,
            label: {
              borderColor: 'transparent',
              style: {
                color: PRIMARY_COLORS[600],
                background: 'transparent',
                fontSize: '10px',
                fontWeight: 500,
              },
              text: 'Retire',
              position: 'top',
              offsetY: -5,
            },
          },
        ],
      };
    },
  },
};
</script>

<style scoped>
.goals-projection-chart-mini {
  width: 100%;
}
</style>
