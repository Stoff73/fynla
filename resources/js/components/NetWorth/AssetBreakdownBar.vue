<template>
  <div class="asset-breakdown-bar">
    <h3 class="chart-title">Asset Breakdown</h3>
    <div v-if="hasData" class="chart-container">
      <apexchart
        :key="chartKey"
        type="bar"
        :options="chartOptions"
        :series="series"
        height="350"
      ></apexchart>
    </div>
    <div v-else class="no-data">
      <p>No asset data available</p>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';
import { ASSET_COLORS, TEXT_COLORS, BORDER_COLORS, CHART_DEFAULTS } from '@/constants/designSystem';

export default {
  name: 'AssetBreakdownBar',
  mixins: [currencyMixin],

  props: {
    breakdown: {
      type: Object,
      required: true,
      default: () => ({}),
    },
  },

  computed: {
    chartKey() {
      const total = this.series[0]?.data?.reduce((a, b) => a + b, 0) || 0;
      return `asset-bar-${this.series[0]?.data?.length || 0}-${Math.round(total)}`;
    },

    hasData() {
      return this.series[0].data.some(value => value > 0);
    },

    series() {
      return [
        {
          name: 'Value',
          data: [
            this.breakdown.property || 0,
            this.breakdown.investments || 0,
            this.breakdown.cash || 0,
            this.breakdown.business || 0,
            this.breakdown.chattels || 0,
          ],
        },
      ];
    },

    chartOptions() {
      return {
        chart: {
          type: 'bar',
          fontFamily: 'Segoe UI, Inter, system-ui, sans-serif',
          toolbar: {
            show: false,
          },
        },
        plotOptions: {
          bar: {
            horizontal: true,
            distributed: true,
            barHeight: '70%',
            dataLabels: {
              position: 'top',
            },
          },
        },
        colours: [ASSET_COLORS.property, ASSET_COLORS.investments, ASSET_COLORS.cash, ASSET_COLORS.business, ASSET_COLORS.chattels],
        dataLabels: {
          enabled: true,
          formatter: (val) => {
            return this.formatCurrency(val);
          },
          offsetX: 30,
          style: {
            fontSize: '12px',
            fontWeight: 600,
            colours: [TEXT_COLORS.primary],
          },
        },
        xaxis: {
          categories: ['Property', 'Investments', 'Cash', 'Business', 'Personal Valuables'],
          labels: {
            formatter: (val) => {
              return this.formatCurrency(val);
            },
            style: {
              fontSize: '12px',
              colours: TEXT_COLORS.muted,
            },
          },
        },
        yaxis: {
          labels: {
            style: {
              fontSize: '14px',
              fontWeight: 600,
              colours: TEXT_COLORS.primary,
            },
          },
        },
        tooltip: {
          y: {
            formatter: (val) => {
              return this.formatCurrency(val);
            },
          },
        },
        grid: {
          borderColour: BORDER_COLORS.default,
          strokeDashArray: 4,
        },
        legend: {
          show: false,
        },
        responsive: [
          {
            breakpoint: 768,
            options: {
              chart: {
                height: 400,
              },
              plotOptions: {
                bar: {
                  barHeight: '60%',
                },
              },
              dataLabels: {
                style: {
                  fontSize: '10px',
                },
              },
            },
          },
        ],
      };
    },
  },

};
</script>

<style scoped>
.asset-breakdown-bar {
  @apply bg-white rounded-card p-6 shadow-sm border border-light-gray transition-all duration-200;
}

.chart-title {
  @apply text-lg font-semibold text-horizon-500 mb-5;
}

.chart-container {
  @apply w-full;
}

.no-data {
  @apply text-center py-12 px-5 text-horizon-400;
}

.no-data p {
  @apply m-0 text-sm;
}

@media (max-width: 768px) {
  .asset-breakdown-bar {
    @apply p-4;
  }

  .chart-title {
    @apply text-base;
  }
}
</style>
