<template>
  <div class="cashflow-projection-chart">
    <div class="chart-header">
      <h3>Cash Flow Projection</h3>
      <p class="subtitle">{{ projectionYears }}-year forward view</p>
    </div>

    <div class="chart-controls">
      <div class="control-group">
        <label for="projection-years">Projection Period:</label>
        <select
          id="projection-years"
          v-model.number="projectionYears"
          class="form-control-sm"
          @change="updateChart"
        >
          <option :value="5">5 Years</option>
          <option :value="10">10 Years</option>
          <option :value="15">15 Years</option>
          <option :value="20">20 Years</option>
        </select>
      </div>

      <div class="control-group">
        <label for="growth-rate">Annual Growth Rate:</label>
        <select
          id="growth-rate"
          v-model.number="growthRate"
          class="form-control-sm"
          @change="updateChart"
        >
          <option :value="0">0% (Flat)</option>
          <option :value="2">2%</option>
          <option :value="3">3%</option>
          <option :value="5">5%</option>
        </select>
      </div>
    </div>

    <div v-if="hasData" class="chart-container">
      <apexchart
        v-if="mounted"
        :key="chartKey"
        type="bar"
        height="400"
        :options="chartOptions"
        :series="series"
      />
    </div>

    <div v-else class="empty-state">
      <i class="fas fa-chart-line fa-3x"></i>
      <p>No cash flow data available</p>
      <p class="subtitle">Enter your income and expenses to see projections</p>
    </div>

    <!-- Summary Cards -->
    <div v-if="hasData" class="summary-cards">
      <div class="summary-card">
        <div class="card-icon positive">
          <i class="fas fa-arrow-up"></i>
        </div>
        <div class="card-content">
          <span class="card-label">Total Projected Income</span>
          <span class="card-value positive">{{ formatCurrency(totalProjectedIncome) }}</span>
        </div>
      </div>

      <div class="summary-card">
        <div class="card-icon negative">
          <i class="fas fa-arrow-down"></i>
        </div>
        <div class="card-content">
          <span class="card-label">Total Projected Expenses</span>
          <span class="card-value negative">{{ formatCurrency(totalProjectedExpenses) }}</span>
        </div>
      </div>

      <div class="summary-card">
        <div class="card-icon" :class="cumulativeSurplusClass">
          <i :class="cumulativeSurplusIcon"></i>
        </div>
        <div class="card-content">
          <span class="card-label">Cumulative {{ cumulativeSurplus >= 0 ? 'Surplus' : 'Deficit' }}</span>
          <span class="card-value" :class="cumulativeSurplusClass">
            {{ formatCurrency(Math.abs(cumulativeSurplus)) }}
          </span>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import VueApexCharts from 'vue3-apexcharts';
import { currencyMixin } from '@/mixins/currencyMixin';
import { SUCCESS_COLORS, ERROR_COLORS, PRIMARY_COLORS, BORDER_COLORS, TEXT_COLORS, CHART_DEFAULTS } from '@/constants/designSystem';

export default {
  name: 'CashFlowProjectionChart',
  mixins: [currencyMixin],

  components: {
    apexchart: VueApexCharts,
  },

  props: {
    currentIncome: {
      type: Number,
      default: 0,
    },
    currentExpenses: {
      type: Number,
      default: 0,
    },
  },

  data() {
    return {
      projectionYears: 10,
      growthRate: 3,
      mounted: false,
    };
  },

  mounted() {
    this.$nextTick(() => {
      this.mounted = true;
    });
  },

  beforeUnmount() {
    this.mounted = false;
  },

  computed: {
    chartKey() {
      return `cashflow-proj-${this.projectionYears}-${this.growthRate}-${Math.round(this.currentIncome)}`;
    },

    hasData() {
      return this.currentIncome > 0 || this.currentExpenses > 0;
    },

    projectionData() {
      if (!this.hasData) return [];

      const data = [];
      const growthFactor = 1 + this.growthRate / 100;

      for (let year = 1; year <= this.projectionYears; year++) {
        const income = this.currentIncome * Math.pow(growthFactor, year - 1);
        const expenses = this.currentExpenses * Math.pow(growthFactor, year - 1);
        const netCashFlow = income - expenses;

        data.push({
          year: new Date().getFullYear() + year - 1,
          income: Math.round(income),
          expenses: Math.round(expenses),
          netCashFlow: Math.round(netCashFlow),
        });
      }

      return data;
    },

    series() {
      if (!this.hasData) return [];

      return [
        {
          name: 'Net Cash Flow',
          data: this.projectionData.map((d) => d.netCashFlow),
        },
        {
          name: 'Cumulative',
          type: 'line',
          data: this.calculateCumulative(),
        },
      ];
    },

    totalProjectedIncome() {
      return this.projectionData.reduce((sum, d) => sum + d.income, 0);
    },

    totalProjectedExpenses() {
      return this.projectionData.reduce((sum, d) => sum + d.expenses, 0);
    },

    cumulativeSurplus() {
      const cumulative = this.calculateCumulative();
      return cumulative[cumulative.length - 1] || 0;
    },

    cumulativeSurplusClass() {
      return this.cumulativeSurplus >= 0 ? 'positive' : 'negative';
    },

    cumulativeSurplusIcon() {
      return this.cumulativeSurplus >= 0 ? 'fas fa-check-circle' : 'fas fa-exclamation-triangle';
    },

    chartOptions() {
      return {
        chart: {
          ...CHART_DEFAULTS.chart,
          type: 'bar',
          height: 400,
          toolbar: {
            show: true,
            tools: { download: true, zoom: false, zoomin: false, zoomout: false, pan: false, reset: false },
          },
          animations: { enabled: true, easing: 'easeinout', speed: 800 },
        },
        plotOptions: {
          bar: {
            borderRadius: 6,
            dataLabels: {
              position: 'top',
            },
            colours: {
              ranges: [
                {
                  from: -Infinity,
                  to: 0,
                  color: ERROR_COLORS[500],
                },
                {
                  from: 0,
                  to: Infinity,
                  color: SUCCESS_COLORS[500],
                },
              ],
            },
          },
        },
        dataLabels: {
          enabled: true,
          formatter: (val) => {
            return this.formatCurrency(val);
          },
          offsetY: -25,
          style: {
            fontSize: '11px',
            colours: [TEXT_COLORS.secondary],
            fontWeight: 600,
          },
        },
        stroke: {
          curve: 'smooth',
          width: [0, 3],
        },
        xaxis: {
          categories: this.projectionData.map((d) => d.year.toString()),
          labels: {
            style: {
              fontSize: '12px',
            },
          },
        },
        yaxis: [
          {
            title: {
              text: 'Annual Cash Flow (£)',
              style: {
                fontSize: '12px',
                fontWeight: 500,
              },
            },
            labels: {
              formatter: (val) => {
                return this.formatCurrencyCompact(val);
              },
            },
          },
          {
            opposite: true,
            title: {
              text: 'Cumulative (£)',
              style: {
                fontSize: '12px',
                fontWeight: 500,
              },
            },
            labels: {
              formatter: (val) => {
                return this.formatCurrencyCompact(val);
              },
            },
          },
        ],
        legend: {
          position: 'top',
          horizontalAlign: 'right',
          fontSize: '13px',
        },
        tooltip: {
          shared: true,
          intersect: false,
          y: {
            formatter: (val) => {
              return this.formatCurrency(val);
            },
          },
        },
        colours: [SUCCESS_COLORS[500], PRIMARY_COLORS[500]],
        grid: {
          borderColour: BORDER_COLORS.default,
          strokeDashArray: 4,
        },
      };
    },
  },

  methods: {
    calculateCumulative() {
      let cumulative = 0;
      return this.projectionData.map((d) => {
        cumulative += d.netCashFlow;
        return cumulative;
      });
    },

    updateChart() {
      // Chart will automatically update due to computed properties
    },

  },
};
</script>

<style scoped>
.cashflow-projection-chart {
  background: white;
  border-radius: 8px;
  padding: 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.chart-header {
  text-align: center;
  margin-bottom: 20px;
}

.chart-header h3 {
  font-size: 18px;
  font-weight: 600;
  @apply text-horizon-500;
  margin: 0 0 8px 0;
}

.subtitle {
  font-size: 14px;
  @apply text-neutral-500;
  margin: 0;
}

.chart-controls {
  display: flex;
  justify-content: center;
  gap: 32px;
  margin-bottom: 24px;
  padding: 16px;
  @apply bg-eggshell-500;
  border-radius: 6px;
}

.control-group {
  display: flex;
  align-items: center;
  gap: 10px;
}

.control-group label {
  font-size: 13px;
  font-weight: 500;
  @apply text-neutral-500;
  white-space: nowrap;
}

.form-control-sm {
  padding: 6px 10px;
  font-size: 13px;
  @apply border border-horizon-300;
  border-radius: 4px;
  background-color: white;
  cursor: pointer;
}

.form-control-sm:focus {
  outline: none;
  @apply border-raspberry-500;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.chart-container {
  margin: 20px 0;
}

.empty-state {
  text-align: center;
  padding: 80px 20px;
  @apply text-horizon-400;
}

.empty-state i {
  @apply text-savannah-300;
  margin-bottom: 16px;
}

.empty-state p {
  margin: 8px 0;
  font-size: 16px;
  @apply text-neutral-500;
}

.summary-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 20px;
  margin-top: 32px;
}

.summary-card {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 20px;
  @apply bg-eggshell-500;
  border-radius: 8px;
  @apply border border-light-gray;
}

.card-icon {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
}

.card-icon.positive {
  @apply bg-spring-100;
  @apply text-spring-600;
}

.card-icon.negative {
  @apply bg-raspberry-100;
  @apply text-raspberry-600;
}

.card-content {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.card-label {
  font-size: 13px;
  @apply text-neutral-500;
  font-weight: 500;
}

.card-value {
  font-size: 20px;
  font-weight: 700;
}

.card-value.positive {
  @apply text-spring-600;
}

.card-value.negative {
  @apply text-raspberry-600;
}

@media (max-width: 768px) {
  .chart-controls {
    flex-direction: column;
    gap: 16px;
  }

  .control-group {
    width: 100%;
    justify-content: space-between;
  }

  .summary-cards {
    grid-template-columns: 1fr;
  }
}
</style>
