<template>
  <div class="net-worth-waterfall-chart">
    <apexchart
      v-if="chartOptions && mounted"
      ref="chart"
      type="bar"
      height="350"
      :options="chartOptions"
      :series="chartSeries"
    ></apexchart>
    <div v-else class="text-center py-8 text-gray-500">
      No data available for waterfall chart
    </div>
  </div>
</template>

<script>
import { SUCCESS_COLORS, ERROR_COLORS, PRIMARY_COLORS, TEXT_COLORS } from '@/constants/designSystem';

export default {
  name: 'NetWorthWaterfallChart',

  props: {
    assets: {
      type: Array,
      required: true,
      default: () => [],
    },
    liabilities: {
      type: Array,
      required: true,
      default: () => [],
    },
  },

  data() {
    return {
      mounted: false,
    };
  },

  mounted() {
    // Wait for next tick to ensure DOM is ready
    // Use setTimeout to give ApexCharts extra time
    this.$nextTick(() => {
      setTimeout(() => {
        this.mounted = true;
      }, 100);
    });
  },

  beforeUnmount() {
    this.mounted = false;
  },

  computed: {
    totalAssets() {
      return this.assets.reduce((sum, asset) => sum + parseFloat(asset.current_value || 0), 0);
    },

    totalLiabilities() {
      return this.liabilities.reduce((sum, liability) => sum + parseFloat(liability.current_balance || 0), 0);
    },

    netWorth() {
      return this.totalAssets - this.totalLiabilities;
    },

    chartSeries() {
      if (this.totalAssets === 0 && this.totalLiabilities === 0) {
        return [];
      }

      return [
        {
          name: 'Amount',
          data: [
            {
              x: 'Total Assets',
              y: this.totalAssets,
              fillColor: SUCCESS_COLORS[500],
            },
            {
              x: 'Total Liabilities',
              y: -this.totalLiabilities,
              fillColor: ERROR_COLORS[500],
            },
            {
              x: 'Net Worth',
              y: this.netWorth,
              fillColor: PRIMARY_COLORS[500],
            },
          ],
        },
      ];
    },

    chartOptions() {
      if (this.totalAssets === 0 && this.totalLiabilities === 0) {
        return null;
      }

      return {
        chart: {
          type: 'bar',
          height: 350,
          toolbar: {
            show: true,
            tools: {
              download: true,
            },
          },
        },
        plotOptions: {
          bar: {
            columnWidth: '50%',
            borderRadius: 4,
            dataLabels: {
              position: 'top',
            },
          },
        },
        dataLabels: {
          enabled: true,
          formatter: (val) => {
            return new Intl.NumberFormat('en-GB', {
              style: 'currency',
              currency: 'GBP',
              minimumFractionDigits: 0,
              maximumFractionDigits: 0,
            }).format(Math.abs(val));
          },
          offsetY: -20,
          style: {
            fontSize: '12px',
            colours: [TEXT_COLORS.secondary],
          },
        },
        xaxis: {
          type: 'category',
          labels: {
            style: {
              fontSize: '12px',
            },
          },
        },
        yaxis: {
          labels: {
            formatter: (val) => {
              return new Intl.NumberFormat('en-GB', {
                style: 'currency',
                currency: 'GBP',
                minimumFractionDigits: 0,
                maximumFractionDigits: 0,
              }).format(val);
            },
          },
        },
        tooltip: {
          y: {
            formatter: (val) => {
              return new Intl.NumberFormat('en-GB', {
                style: 'currency',
                currency: 'GBP',
                minimumFractionDigits: 0,
                maximumFractionDigits: 0,
              }).format(Math.abs(val));
            },
          },
        },
        legend: {
          show: false,
        },
      };
    },
  },
};
</script>
