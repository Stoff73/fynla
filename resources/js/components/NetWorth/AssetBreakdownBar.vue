<template>
  <div class="asset-breakdown-bar">
    <h3 class="chart-title">Asset Breakdown</h3>
    <div v-if="hasData" class="chart-container">
      <apexchart
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
          fontFamily: 'Inter, system-ui, sans-serif',
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
        colours: ['#10B981', '#3B82F6', '#F59E0B', '#8B5CF6', '#EC4899'],
        dataLabels: {
          enabled: true,
          formatter: (val) => {
            return this.formatCurrency(val);
          },
          offsetX: 30,
          style: {
            fontSize: '12px',
            fontWeight: 600,
            colours: ['#111827'],
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
              colours: '#6b7280',
            },
          },
        },
        yaxis: {
          labels: {
            style: {
              fontSize: '14px',
              fontWeight: 600,
              colours: '#111827',
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
          borderColour: '#e5e7eb',
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
  background: white;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  border: 1px solid #e5e7eb;
}

.chart-title {
  font-size: 18px;
  font-weight: 600;
  color: #111827;
  margin: 0 0 20px 0;
}

.chart-container {
  width: 100%;
}

.no-data {
  text-align: center;
  padding: 60px 20px;
  color: #9ca3af;
}

.no-data p {
  margin: 0;
  font-size: 14px;
}

@media (max-width: 768px) {
  .asset-breakdown-bar {
    padding: 16px;
  }

  .chart-title {
    font-size: 16px;
  }
}
</style>
