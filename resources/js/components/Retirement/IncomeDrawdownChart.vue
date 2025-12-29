<template>
  <div class="income-drawdown-chart">
    <apexchart
      v-if="isReady && series.length > 0"
      type="bar"
      :options="chartOptions"
      :series="series"
      height="400"
    />
    <div v-else class="chart-placeholder">
      <p>No income projection data available</p>
    </div>
  </div>
</template>

<script>
import VueApexCharts from 'vue3-apexcharts';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'IncomeDrawdownChart',

  mixins: [currencyMixin],

  components: {
    apexchart: VueApexCharts,
  },

  props: {
    data: {
      type: Object,
      required: true,
    },
  },

  data() {
    return {
      isReady: false,
    };
  },

  computed: {
    ages() {
      if (!this.data?.yearly_income) return [];
      return this.data.yearly_income.map(y => `Age ${y.age}`);
    },

    series() {
      if (!this.data?.yearly_income || this.data.yearly_income.length === 0) return [];

      // Create bar series with colors based on income vs target
      return [
        {
          name: 'Annual Income',
          data: this.data.yearly_income.map(y => ({
            x: `Age ${y.age}`,
            y: y.total_income,
            fillColor: this.getBarColor(y),
          })),
        },
      ];
    },

    targetIncome() {
      return this.data?.target_income || 0;
    },

    barColors() {
      if (!this.data?.yearly_income) return [];
      return this.data.yearly_income.map(y => this.getBarColor(y));
    },

    chartOptions() {
      return {
        chart: {
          type: 'bar',
          fontFamily: 'Inter, system-ui, sans-serif',
          toolbar: {
            show: true,
            tools: {
              download: true,
              selection: false,
              zoom: false,
              zoomin: false,
              zoomout: false,
              pan: false,
              reset: false,
            },
          },
          zoom: {
            enabled: false,
          },
          animations: {
            enabled: true,
            easing: 'easeinout',
            speed: 600,
          },
        },
        colors: this.barColors,
        plotOptions: {
          bar: {
            borderRadius: 4,
            columnWidth: '70%',
            distributed: true,
          },
        },
        xaxis: {
          categories: this.ages,
          title: {
            text: 'Age',
            style: {
              fontWeight: 600,
              fontSize: '12px',
            },
          },
          labels: {
            rotate: -45,
            rotateAlways: this.ages.length > 15,
            style: {
              fontSize: '10px',
            },
          },
          tickAmount: Math.min(this.ages.length, 15),
        },
        yaxis: {
          title: {
            text: 'Annual Income',
            style: {
              fontWeight: 600,
              fontSize: '12px',
            },
          },
          labels: {
            formatter: (val) => this.formatCurrencyShort(val),
            style: {
              fontSize: '11px',
            },
          },
        },
        annotations: {
          yaxis: [
            {
              y: this.targetIncome,
              borderColor: '#f59e0b',
              strokeDashArray: 5,
              borderWidth: 2,
              label: {
                borderColor: '#f59e0b',
                style: {
                  color: '#fff',
                  background: '#f59e0b',
                  fontSize: '11px',
                  fontWeight: 600,
                },
                text: `Target: ${this.formatCurrencyShort(this.targetIncome)}`,
                position: 'left',
                offsetX: 0,
              },
            },
          ],
        },
        tooltip: {
          y: {
            formatter: (val) => this.formatCurrency(val),
          },
          custom: ({ dataPointIndex }) => {
            const yearData = this.data.yearly_income[dataPointIndex];
            if (!yearData) return '';

            const status = yearData.above_target ? 'Above Target' : 'Below Target';
            const statusClass = yearData.above_target ? 'status-above' : 'status-below';

            return `
              <div class="custom-tooltip">
                <div class="tooltip-header">Age ${yearData.age}</div>
                <div class="tooltip-row">
                  <span>DC Drawdown:</span>
                  <strong>${this.formatCurrency(yearData.dc_drawdown)}</strong>
                </div>
                <div class="tooltip-row">
                  <span>DB Income:</span>
                  <strong>${this.formatCurrency(yearData.db_income)}</strong>
                </div>
                <div class="tooltip-row">
                  <span>State Pension:</span>
                  <strong>${this.formatCurrency(yearData.state_pension)}</strong>
                </div>
                <div class="tooltip-divider"></div>
                <div class="tooltip-row total">
                  <span>Total Income:</span>
                  <strong>${this.formatCurrency(yearData.total_income)}</strong>
                </div>
                <div class="tooltip-row target">
                  <span>Target:</span>
                  <strong>${this.formatCurrency(yearData.target_income)}</strong>
                </div>
                <div class="tooltip-status ${statusClass}">${status}</div>
                <div class="tooltip-row fund">
                  <span>Remaining Fund:</span>
                  <strong>${this.formatCurrency(yearData.remaining_fund)}</strong>
                </div>
              </div>
            `;
          },
        },
        legend: {
          show: false,
        },
        grid: {
          borderColor: '#e5e7eb',
          strokeDashArray: 4,
        },
        dataLabels: {
          enabled: false,
        },
      };
    },
  },

  mounted() {
    this.$nextTick(() => {
      setTimeout(() => {
        this.isReady = true;
      }, 100);
    });
  },

  methods: {
    getBarColor(yearData) {
      // Red when fund is depleted
      if (yearData.remaining_fund <= 0) {
        return '#ef4444';
      }

      // Dark green when on or above target
      if (yearData.above_target) {
        return '#059669';
      }

      // Calculate how far below target
      const percentBelow = (yearData.target_income - yearData.total_income) / yearData.target_income;

      // Green when less than 25% below target
      if (percentBelow < 0.25) {
        return '#10b981';
      }

      // Light green when more than 25% below target
      return '#6ee7b7';
    },

    formatCurrencyShort(value) {
      if (value === null || value === undefined) return '£0';
      if (value >= 1000000) {
        return '£' + (value / 1000000).toFixed(1) + 'M';
      }
      if (value >= 1000) {
        return '£' + (value / 1000).toFixed(0) + 'K';
      }
      return this.formatCurrency(value);
    },
  },
};
</script>

<style scoped>
.income-drawdown-chart {
  width: 100%;
}

.chart-placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 400px;
  background: #f9fafb;
  border-radius: 8px;
  border: 1px dashed #d1d5db;
}

.chart-placeholder p {
  color: #6b7280;
  font-size: 14px;
  margin: 0;
}

/* Custom tooltip styles - need to be global for ApexCharts */
:deep(.custom-tooltip) {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 12px;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  min-width: 180px;
}

:deep(.tooltip-header) {
  font-size: 14px;
  font-weight: 700;
  color: #111827;
  margin-bottom: 8px;
  padding-bottom: 8px;
  border-bottom: 1px solid #e5e7eb;
}

:deep(.tooltip-row) {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  color: #6b7280;
  margin-bottom: 4px;
}

:deep(.tooltip-row strong) {
  color: #111827;
}

:deep(.tooltip-row.total) {
  font-weight: 600;
  color: #111827;
}

:deep(.tooltip-row.target) {
  color: #f59e0b;
}

:deep(.tooltip-row.fund) {
  margin-top: 4px;
  padding-top: 4px;
  border-top: 1px solid #e5e7eb;
}

:deep(.tooltip-divider) {
  height: 1px;
  background: #e5e7eb;
  margin: 8px 0;
}

:deep(.tooltip-status) {
  text-align: center;
  font-size: 11px;
  font-weight: 600;
  padding: 4px 8px;
  border-radius: 4px;
  margin: 8px 0;
}

:deep(.status-above) {
  background: #d1fae5;
  color: #065f46;
}

:deep(.status-below) {
  background: #fee2e2;
  color: #991b1b;
}
</style>
