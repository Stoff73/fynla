<template>
  <div class="income-drawdown-chart">
    <apexchart
      v-if="isReady && series.length > 0"
      :key="chartKey"
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
import { SUCCESS_COLORS, WARNING_COLORS, ERROR_COLORS, BORDER_COLORS, CHART_DEFAULTS } from '@/constants/designSystem';

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
      renderTimeout: null,
    };
  },

  computed: {
    ages() {
      if (!this.data?.yearly_income) return [];
      return this.data.yearly_income.map(y => `Age ${y.age}`);
    },

    chartKey() {
      const yearly = this.data?.yearly_income;
      const total = yearly?.reduce((sum, y) => sum + (y.total_income || 0), 0) || 0;
      return `income-drawdown-${yearly?.length || 0}-${Math.round(total)}`;
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
          ...CHART_DEFAULTS.chart,
          type: 'bar',
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
              borderColor: WARNING_COLORS[500],
              strokeDashArray: 5,
              borderWidth: 2,
              label: {
                borderColor: WARNING_COLORS[500],
                style: {
                  color: '#fff',
                  background: WARNING_COLORS[500],
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
                  <span>Defined Contribution Drawdown:</span>
                  <strong>${this.formatCurrency(yearData.dc_drawdown)}</strong>
                </div>
                <div class="tooltip-row">
                  <span>Defined Benefit Income:</span>
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
          borderColor: BORDER_COLORS.default,
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
      this.renderTimeout = setTimeout(() => {
        this.isReady = true;
      }, 100);
    });
  },

  beforeUnmount() {
    if (this.renderTimeout) clearTimeout(this.renderTimeout);
  },

  methods: {
    getBarColor(yearData) {
      // Red when fund is depleted
      if (yearData.remaining_fund <= 0) {
        return ERROR_COLORS[500];
      }

      // Dark green when on or above target
      if (yearData.above_target) {
        return SUCCESS_COLORS[600];
      }

      // Calculate how far below target
      const percentBelow = (yearData.target_income - yearData.total_income) / yearData.target_income;

      // Green when less than 25% below target
      if (percentBelow < 0.25) {
        return SUCCESS_COLORS[500];
      }

      // Light green when more than 25% below target
      return SUCCESS_COLORS[600];
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
  @apply bg-savannah-100;
  border-radius: 8px;
  @apply border border-dashed border-horizon-300;
}

.chart-placeholder p {
  @apply text-neutral-500;
  font-size: 14px;
  margin: 0;
}

/* Custom tooltip styles - need to be global for ApexCharts */
:deep(.custom-tooltip) {
  background: white;
  @apply border border-light-gray;
  border-radius: 8px;
  padding: 12px;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  min-width: 180px;
}

:deep(.tooltip-header) {
  font-size: 14px;
  font-weight: 700;
  @apply text-horizon-500;
  margin-bottom: 8px;
  padding-bottom: 8px;
  @apply border-b border-light-gray;
}

:deep(.tooltip-row) {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  @apply text-neutral-500;
  margin-bottom: 4px;
}

:deep(.tooltip-row strong) {
  @apply text-horizon-500;
}

:deep(.tooltip-row.total) {
  font-weight: 600;
  @apply text-horizon-500;
}

:deep(.tooltip-row.target) {
  @apply text-violet-500;
}

:deep(.tooltip-row.fund) {
  margin-top: 4px;
  padding-top: 4px;
  @apply border-t border-light-gray;
}

:deep(.tooltip-divider) {
  height: 1px;
  @apply bg-savannah-200;
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
  @apply bg-spring-100;
  @apply text-spring-800;
}

:deep(.status-below) {
  @apply bg-raspberry-100;
  @apply text-raspberry-800;
}
</style>
