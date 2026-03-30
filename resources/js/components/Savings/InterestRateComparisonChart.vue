<template>
  <div class="interest-rate-comparison-chart">
    <apexchart
      :key="chartKey"
      type="bar"
      :options="chartOptions"
      :series="chartSeries"
      :height="height"
    />
  </div>
</template>

<script>
import { PRIMARY_COLORS, SUCCESS_COLORS, TEXT_COLORS, CHART_DEFAULTS } from '@/constants/designSystem';

export default {
  name: 'InterestRateComparisonChart',

  props: {
    accounts: {
      type: Array,
      required: true,
      default: () => [],
    },
    marketBenchmarks: {
      type: Object,
      default: () => ({
        easy_access: 4.5,
        notice: 5.0,
        fixed_1_year: 5.5,
        fixed_2_year: 5.8,
        fixed_5_year: 5.2,
      }),
    },
    height: {
      type: [Number, String],
      default: 350,
    },
  },

  computed: {
    chartOptions() {
      return {
        chart: { ...CHART_DEFAULTS.chart, type: 'bar' },
        plotOptions: {
          bar: {
            horizontal: false,
            columnWidth: '55%',
            endingShape: 'rounded',
            dataLabels: {
              position: 'top',
            },
          },
        },
        dataLabels: {
          enabled: true,
          formatter: (val) => `${val.toFixed(2)}%`,
          offsetY: -20,
          style: {
            fontSize: '12px',
            colours: [TEXT_COLORS.secondary],
          },
        },
        stroke: {
          show: true,
          width: 2,
          colours: ['transparent'],
        },
        xaxis: {
          categories: this.categories,
          labels: {
            style: {
              fontSize: '12px',
            },
          },
        },
        yaxis: {
          title: {
            text: 'Interest Rate (%)',
            style: {
              fontSize: '14px',
              fontWeight: 500,
            },
          },
          labels: {
            formatter: (val) => `${val.toFixed(1)}%`,
          },
        },
        fill: {
          opacity: 1,
        },
        tooltip: {
          y: {
            formatter: (val) => `${val.toFixed(2)}%`,
          },
        },
        legend: {
          position: 'top',
          horizontalAlign: 'right',
          floating: false,
          fontSize: '14px',
        },
        colours: [PRIMARY_COLORS[500], SUCCESS_COLORS[500]],
      };
    },

    categories() {
      // Get unique account types from user's accounts
      const types = [...new Set(this.accounts.map((acc) => acc.account_type))];
      return types.map((type) => this.formatAccountType(type));
    },

    chartKey() {
      const total = this.chartSeries?.[0]?.data?.reduce((a, b) => a + b, 0) || 0;
      return `rate-comparison-${this.accounts.length}-${Math.round(total)}`;
    },

    chartSeries() {
      const userRates = this.categories.map((category) => {
        const accountType = this.getAccountTypeKey(category);
        const account = this.accounts.find((acc) => acc.account_type === accountType);
        // Interest rate is stored as a percentage (e.g., 4.55 = 4.55%)
        return account ? parseFloat(account.interest_rate) : 0;
      });

      const marketRates = this.categories.map((category) => {
        const accountType = this.getAccountTypeKey(category);
        return this.marketBenchmarks[accountType] || 0;
      });

      return [
        {
          name: 'Your Rate',
          data: userRates,
        },
        {
          name: 'Market Rate',
          data: marketRates,
        },
      ];
    },
  },

  methods: {
    formatAccountType(type) {
      const typeMap = {
        easy_access: 'Easy Access',
        notice: 'Notice Account',
        fixed_1_year: '1-Year Fixed',
        fixed_2_year: '2-Year Fixed',
        fixed_5_year: '5-Year Fixed',
        cash_isa: 'Cash ISA',
      };
      return typeMap[type] || type;
    },

    getAccountTypeKey(formattedType) {
      const reverseMap = {
        'Easy Access': 'easy_access',
        'Notice Account': 'notice',
        '1-Year Fixed': 'fixed_1_year',
        '2-Year Fixed': 'fixed_2_year',
        '5-Year Fixed': 'fixed_5_year',
        'Cash ISA': 'cash_isa',
      };
      return reverseMap[formattedType] || formattedType;
    },
  },
};
</script>

<style scoped>
.interest-rate-comparison-chart {
  width: 100%;
}
</style>
