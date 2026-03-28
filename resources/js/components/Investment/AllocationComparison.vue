<template>
  <div class="allocation-comparison">
    <div class="bg-white rounded-lg shadow p-6">
      <h3 class="text-lg font-semibold text-horizon-500 mb-4">
        Current vs Target Allocation
      </h3>

      <!-- Chart -->
      <div v-if="chartData" class="mb-6">
        <apexchart
          :key="chartKey"
          type="bar"
          height="350"
          :options="chartOptions"
          :series="chartSeries"
        />
      </div>

      <!-- Legend / Details Table -->
      <div v-if="allocations && allocations.length > 0" class="overflow-x-auto scrollbar-thin">
        <table class="min-w-full divide-y divide-light-gray">
          <thead class="bg-eggshell-500">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">
                Security
              </th>
              <th class="px-4 py-3 text-right text-xs font-medium text-neutral-500 uppercase tracking-wider">
                Current
              </th>
              <th class="px-4 py-3 text-right text-xs font-medium text-neutral-500 uppercase tracking-wider">
                Target
              </th>
              <th class="px-4 py-3 text-right text-xs font-medium text-neutral-500 uppercase tracking-wider">
                Difference
              </th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-light-gray">
            <tr
              v-for="(allocation, index) in allocations"
              :key="index"
              class="hover:bg-eggshell-500"
            >
              <td class="px-4 py-3 text-sm font-medium text-horizon-500">
                {{ allocation.security_name }}
              </td>
              <td class="px-4 py-3 text-sm text-right text-neutral-500">
                {{ formatPercent(allocation.current_weight) }}
                <div class="text-xs text-neutral-500">
                  £{{ formatCurrency(allocation.current_value) }}
                </div>
              </td>
              <td class="px-4 py-3 text-sm text-right text-neutral-500">
                {{ formatPercent(allocation.target_weight) }}
                <div class="text-xs text-neutral-500">
                  £{{ formatCurrency(allocation.target_value) }}
                </div>
              </td>
              <td class="px-4 py-3 text-sm text-right">
                <span
                  :class="[
                    'font-medium',
                    allocation.difference > 0 ? 'text-spring-600' : 'text-raspberry-600'
                  ]"
                >
                  {{ allocation.difference > 0 ? '+' : '' }}£{{ formatCurrency(Math.abs(allocation.difference)) }}
                </span>
                <div class="text-xs text-neutral-500">
                  {{ allocation.difference_percent > 0 ? '+' : '' }}{{ allocation.difference_percent.toFixed(1) }}%
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Summary Stats -->
      <div v-if="metrics" class="mt-6 grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-eggshell-500 rounded-lg p-4">
          <p class="text-xs text-neutral-500 uppercase tracking-wide">Tracking Error</p>
          <p class="text-lg font-semibold text-horizon-500 mt-1">
            {{ (metrics.tracking_error * 100).toFixed(2) }}%
          </p>
        </div>
        <div class="bg-eggshell-500 rounded-lg p-4">
          <p class="text-xs text-neutral-500 uppercase tracking-wide">Total Turnover</p>
          <p class="text-lg font-semibold text-horizon-500 mt-1">
            £{{ formatCurrency(metrics.total_turnover) }}
          </p>
        </div>
        <div class="bg-eggshell-500 rounded-lg p-4">
          <p class="text-xs text-violet-600 uppercase tracking-wide">Buys</p>
          <p class="text-lg font-semibold text-violet-700 mt-1">
            £{{ formatCurrency(metrics.total_buys) }}
          </p>
        </div>
        <div class="bg-eggshell-500 rounded-lg p-4">
          <p class="text-xs text-raspberry-600 uppercase tracking-wide">Sells</p>
          <p class="text-lg font-semibold text-raspberry-700 mt-1">
            £{{ formatCurrency(metrics.total_sells) }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import VueApexCharts from 'vue3-apexcharts';
import { currencyMixin } from '@/mixins/currencyMixin';
import { PRIMARY_COLORS, SUCCESS_COLORS, TEXT_COLORS, CHART_DEFAULTS, BORDER_COLORS } from '@/constants/designSystem';

export default {
  name: 'AllocationComparison',
  mixins: [currencyMixin],

  components: {
    apexchart: VueApexCharts,
  },

  props: {
    currentAllocations: {
      type: Array,
      default: () => [],
    },
    targetAllocations: {
      type: Array,
      default: () => [],
    },
    metrics: {
      type: Object,
      default: null,
    },
  },

  computed: {
    allocations() {
      if (!this.currentAllocations.length || !this.targetAllocations.length) {
        return [];
      }

      return this.currentAllocations.map((current, index) => {
        const target = this.targetAllocations[index];
        return {
          security_name: current.security_name,
          ticker: current.ticker,
          current_value: current.current_value,
          current_weight: current.current_weight,
          target_value: target.target_value,
          target_weight: target.target_weight,
          difference: target.difference,
          difference_percent: target.difference_percent,
        };
      });
    },

    chartData() {
      if (!this.allocations.length) return null;

      return {
        labels: this.allocations.map(a => a.security_name),
        current: this.allocations.map(a => (a.current_weight * 100).toFixed(2)),
        target: this.allocations.map(a => (a.target_weight * 100).toFixed(2)),
      };
    },

    chartKey() {
      return `alloc-compare-${this.allocations.length}-${Math.round(this.allocations.reduce((sum, a) => sum + (a.current_value || 0), 0))}`;
    },

    chartSeries() {
      if (!this.chartData) return [];

      return [
        {
          name: 'Current Allocation',
          data: this.chartData.current,
        },
        {
          name: 'Target Allocation',
          data: this.chartData.target,
        },
      ];
    },

    chartOptions() {
      if (!this.chartData) return {};

      return {
        chart: {
          ...CHART_DEFAULTS.chart,
          type: 'bar',
          height: 350,
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
        },
        plotOptions: {
          bar: {
            horizontal: false,
            columnWidth: '70%',
            dataLabels: {
              position: 'top',
            },
          },
        },
        dataLabels: {
          enabled: true,
          formatter: (val) => `${parseFloat(val).toFixed(1)}%`,
          offsetY: -20,
          style: {
            fontSize: '10px',
            colors: [TEXT_COLORS.secondary],
          },
        },
        stroke: {
          show: true,
          width: 2,
          colors: ['transparent'],
        },
        xaxis: {
          categories: this.chartData.labels,
          labels: {
            rotate: -45,
            rotateAlways: true,
            style: {
              fontSize: '11px',
            },
          },
        },
        yaxis: {
          title: {
            text: 'Allocation (%)',
          },
          labels: {
            formatter: (val) => `${val.toFixed(0)}%`,
          },
        },
        fill: {
          opacity: 1,
        },
        colors: [PRIMARY_COLORS[500], SUCCESS_COLORS[500]],
        legend: {
          position: 'top',
          horizontalAlign: 'left',
          offsetX: 0,
        },
        tooltip: {
          y: {
            formatter: (val) => `${parseFloat(val).toFixed(2)}%`,
          },
        },
      };
    },
  },

  methods: {
    formatPercent(value) {
      return `${(value * 100).toFixed(2)}%`;
    },
  },
};
</script>

