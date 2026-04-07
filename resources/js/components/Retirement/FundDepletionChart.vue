<template>
  <div class="bg-white rounded-lg border border-light-gray p-6 mb-6">
    <!-- Header -->
    <div class="flex justify-between items-start mb-4">
      <div>
        <h4 class="text-lg font-semibold text-horizon-500">Fund Depletion Projection</h4>
        <p class="text-sm text-neutral-500">How your retirement funds will be drawn down over time</p>
      </div>
      <div v-if="hasDepletionWarning" class="flex items-center gap-1.5 bg-violet-100 text-violet-800 px-3 py-1.5 rounded-full text-xs font-semibold">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
        </svg>
        Funds may run out early
      </div>
    </div>

    <!-- Chart -->
    <div class="mb-6">
      <apexchart
        v-if="projections.length > 0"
        :key="chartKey"
        type="bar"
        height="350"
        :options="chartOptions"
        :series="chartSeries"
      />
    </div>

    <!-- Year-by-Year Table (Hidden from view - logic retained) -->
    <div v-if="false" class="mt-6">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-light-gray text-sm">
          <thead>
            <tr class="bg-savannah-100">
              <th class="px-3 py-2 text-left font-semibold text-horizon-500">Age</th>
              <th class="px-3 py-2 text-right font-semibold text-horizon-500">Withdrawal</th>
              <th v-for="type in activeFundTypes" :key="type" class="px-3 py-2 text-right font-semibold text-horizon-500">
                {{ formatFundName(type) }}
              </th>
              <th class="px-3 py-2 text-right font-semibold text-horizon-500">Growth</th>
              <th class="px-3 py-2 text-right font-semibold text-horizon-500">
                <div>Taxable</div>
                <div class="text-xs font-normal text-neutral-500">Drawdown</div>
              </th>
              <th class="px-3 py-2 text-right font-semibold text-horizon-500">Tax Paid</th>
              <th class="px-3 py-2 text-right font-semibold text-horizon-500">Total Balance</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-light-gray">
            <tr
              v-for="year in displayedYears"
              :key="year.age"
              :class="{ 'bg-violet-50': year.total_funds <= 0 }"
            >
              <td class="px-3 py-2 text-neutral-500 font-medium">{{ year.age }}</td>
              <td class="px-3 py-2 text-right text-raspberry-600">-{{ formatCurrency(year.total_income) }}</td>
              <td v-for="type in activeFundTypes" :key="type" class="px-3 py-2 text-right text-neutral-500">
                <div>{{ formatCurrency(year[type] || 0) }}</div>
                <div v-if="year.withdrawals && year.withdrawals[type] > 0" class="text-xs text-raspberry-500">
                  -{{ formatCurrency(year.withdrawals[type]) }}
                </div>
              </td>
              <td class="px-3 py-2 text-right text-spring-600">
                +{{ formatCurrency(totalGrowth(year)) }}
              </td>
              <!-- Taxable Drawdown (over PA) -->
              <td class="px-3 py-2 text-right">
                <div v-if="year.taxable_drawdown > 0" class="text-raspberry-600">
                  {{ formatCurrency(year.taxable_drawdown) }}
                </div>
                <div v-else class="text-spring-600">£0</div>
                <div v-if="year.pa_drawdown > 0" class="text-xs text-neutral-500">
                  ({{ formatCurrency(year.pa_drawdown) }} in PA)
                </div>
              </td>
              <!-- Tax Paid -->
              <td class="px-3 py-2 text-right" :class="year.tax_paid > 0 ? 'text-raspberry-600 font-medium' : 'text-spring-600'">
                {{ year.tax_paid > 0 ? '-' + formatCurrency(year.tax_paid) : '£0' }}
              </td>
              <td class="px-3 py-2 text-right font-semibold" :class="year.total_funds <= 0 ? 'text-violet-700' : 'text-horizon-500'">
                {{ formatCurrency(year.total_funds) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Tax Impact Note -->
    <div v-if="hasIsaDepletion" class="mt-4 flex gap-3 bg-violet-50 border border-violet-200 rounded-lg p-4">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-violet-500 flex-shrink-0 mt-0.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
      </svg>
      <div class="text-sm text-violet-800">
        <strong>Tax Impact:</strong> When your ISA funds are depleted at age {{ depletionAges.isa }},
        you'll need to draw more from taxable sources, increasing your tax liability.
      </div>
    </div>
  </div>
</template>

<script>
import VueApexCharts from 'vue3-apexcharts';
import { currencyMixin } from '@/mixins/currencyMixin';
import { CHART_COLORS, TEXT_COLORS, BORDER_COLORS, SUCCESS_COLORS, CHART_DEFAULTS } from '@/constants/designSystem';

export default {
  name: 'FundDepletionChart',

  mixins: [currencyMixin],

  components: {
    apexchart: VueApexCharts,
  },

  props: {
    projections: {
      type: Array,
      required: true,
    },
    depletionAges: {
      type: Object,
      default: () => ({}),
    },
    retirementAge: {
      type: Number,
      default: 68,
    },
  },

  data() {
    return {
      showAllYears: true,
    };
  },

  computed: {
    chartKey() {
      const total = this.projections.reduce((sum, p) => sum + (p.total_funds || 0), 0);
      return `depletion-${this.projections.length}-${total}`;
    },

    totalDepletionAge() {
      // Use total from depletionAges if available
      if (this.depletionAges.total) {
        return this.depletionAges.total;
      }
      // Otherwise calculate from projections - find when total_funds hits 0
      if (this.projections.length === 0) return null;
      const depletedYear = this.projections.find(p => p.total_funds <= 0);
      if (depletedYear) {
        return depletedYear.age;
      }
      // If never depletes, return 100+
      return 100;
    },

    hasDepletionWarning() {
      return this.totalDepletionAge && this.totalDepletionAge < 100;
    },

    hasIsaDepletion() {
      return this.depletionAges.isa && this.depletionAges.isa < 100;
    },

    activeFundTypes() {
      // Determine which fund types have non-zero starting values
      if (this.projections.length === 0) return [];

      const first = this.projections[0];
      const types = [];

      // PCLS and Drawdown are separate (not combined pension_pot)
      if (first.pcls > 0) types.push('pcls');
      if (first.drawdown > 0) types.push('drawdown');
      if (first.isa > 0) types.push('isa');
      if (first.bond > 0) types.push('bond');
      if (first.gia > 0) types.push('gia');
      if (first.savings > 0) types.push('savings');

      return types;
    },

    displayedYears() {
      if (this.showAllYears) {
        return this.projections;
      }

      // Show every 5 years plus first and last
      return this.projections.filter((year, index) => {
        if (index === 0 || index === this.projections.length - 1) return true;
        return year.age % 5 === 0;
      });
    },

    chartSeries() {
      if (this.projections.length === 0) return [];

      const series = [];

      // PCLS and Drawdown are separate series
      if (this.activeFundTypes.includes('pcls')) {
        series.push({
          name: 'Pension Commencement Lump Sum (Tax-Free)',
          data: this.projections.map(p => Math.round(p.pcls || 0)),
        });
      }

      if (this.activeFundTypes.includes('drawdown')) {
        series.push({
          name: 'Pension Drawdown',
          data: this.projections.map(p => Math.round(p.drawdown || 0)),
        });
      }

      if (this.activeFundTypes.includes('isa')) {
        series.push({
          name: 'ISA',
          data: this.projections.map(p => Math.round(p.isa || 0)),
        });
      }

      if (this.activeFundTypes.includes('bond')) {
        series.push({
          name: 'Bond',
          data: this.projections.map(p => Math.round(p.bond || 0)),
        });
      }

      if (this.activeFundTypes.includes('gia')) {
        series.push({
          name: 'General Investment Account',
          data: this.projections.map(p => Math.round(p.gia || 0)),
        });
      }

      if (this.activeFundTypes.includes('savings')) {
        series.push({
          name: 'Savings',
          data: this.projections.map(p => Math.round(p.savings || 0)),
        });
      }

      return series;
    },

    chartOptions() {
      const ages = this.projections.map(p => p.age);

      return {
        chart: {
          ...CHART_DEFAULTS.chart,
          type: 'bar',
          stacked: true,
          animations: { enabled: true, speed: 500 },
        },
        colors: CHART_COLORS.slice(0, 5),
        fill: {
          opacity: 0.9,
        },
        plotOptions: {
          bar: {
            columnWidth: '70%',
            borderRadius: 2,
          },
        },
        stroke: {
          width: 0,
        },
        xaxis: {
          categories: ages,
          labels: {
            style: { colors: TEXT_COLORS.muted, fontSize: '11px' },
            rotate: 0,
            formatter: (val) => {
              if (val % 5 === 0 || val === this.retirementAge) return val;
              return '';
            },
          },
          axisBorder: { show: false },
          axisTicks: { show: false },
          title: {
            text: 'Age',
            style: { color: TEXT_COLORS.muted, fontSize: '12px', fontWeight: 500 },
          },
        },
        yaxis: {
          labels: {
            style: { colors: TEXT_COLORS.muted, fontSize: '11px' },
            formatter: (val) => '£' + (val / 1000).toFixed(0) + 'k',
          },
          title: {
            text: 'Fund Value',
            style: { color: TEXT_COLORS.muted, fontSize: '12px', fontWeight: 500 },
          },
        },
        grid: {
          borderColor: BORDER_COLORS.default,
          strokeDashArray: 4,
        },
        legend: {
          position: 'top',
          horizontalAlign: 'right',
          fontSize: '12px',
          markers: { width: 10, height: 10, radius: 2 },
          itemMargin: { horizontal: 12 },
        },
        tooltip: {
          shared: true,
          intersect: false,
          y: {
            formatter: (val) => '£' + val.toLocaleString(),
          },
          x: {
            formatter: (val, { dataPointIndex }) => {
              // Use dataPointIndex to get actual age from projections
              const age = this.projections[dataPointIndex]?.age;
              return age ? `Age ${age}` : `Age ${val}`;
            },
          },
        },
        dataLabels: { enabled: false },
        annotations: {
          xaxis: [
            {
              x: 67,
              borderColor: SUCCESS_COLORS[500],
              label: {
                borderColor: SUCCESS_COLORS[500],
                style: {
                  color: '#fff',
                  background: SUCCESS_COLORS[500],
                  fontSize: '10px',
                },
                text: 'State Pension Age',
                position: 'top',
              },
            },
          ],
        },
      };
    },
  },

  methods: {
    formatFundName(fund) {
      const names = {
        pcls: 'Pension Commencement Lump Sum',
        drawdown: 'Drawdown',
        pension_pot: 'Pension Pot',
        dc_pension: 'Pension',
        isa: 'ISA',
        gia: 'General Investment Account',
        bond: 'Bond',
        savings: 'Savings',
        total: 'All Funds',
      };
      return names[fund] || fund;
    },

    totalGrowth(year) {
      if (!year.growth) return 0;
      return Object.values(year.growth).reduce((sum, val) => sum + (val || 0), 0);
    },
  },
};
</script>
