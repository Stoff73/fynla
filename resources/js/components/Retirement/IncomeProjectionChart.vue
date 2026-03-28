<template>
  <div class="income-projection-chart">
    <apexchart
      :key="chartKey"
      type="area"
      :options="chartOptions"
      :series="series"
      height="400"
    ></apexchart>
  </div>
</template>

<script>
import { mapState } from 'vuex';
import { CHART_COLORS, BORDER_COLORS, WARNING_COLORS, CHART_DEFAULTS, TEXT_COLORS } from '@/constants/designSystem';

export default {
  name: 'IncomeProjectionChart',

  props: {
    viewMode: {
      type: String,
      default: 'stacked',
      validator: (value) => ['stacked', 'individual'].includes(value),
    },
    projectedIncome: {
      type: Number,
      default: 0,
    },
    targetIncome: {
      type: Number,
      default: 0,
    },
  },

  computed: {
    ...mapState('retirement', ['dcPensions', 'dbPensions', 'statePension', 'profile']),

    retirementAge() {
      return this.profile?.target_retirement_age || 67;
    },

    currentAge() {
      return this.profile?.current_age || 40;
    },

    lifeExpectancy() {
      return this.profile?.life_expectancy || 90;
    },

    ages() {
      const ages = [];
      for (let age = this.retirementAge; age <= this.lifeExpectancy; age++) {
        ages.push(age);
      }
      return ages;
    },

    chartKey() {
      const dcTotal = this.dcPensions.reduce((sum, p) => sum + parseFloat(p.current_fund_value || 0), 0);
      return `income-proj-${this.ages.length}-${Math.round(dcTotal)}`;
    },

    dcIncomeData() {
      // Calculate DC pension income using 4% drawdown rule
      const totalDCValue = this.dcPensions.reduce((sum, p) => {
        return sum + parseFloat(p.current_fund_value || 0);
      }, 0);

      // Project to retirement (simple compound growth)
      const yearsToRetirement = this.retirementAge - this.currentAge;
      const growthRate = 0.05; // 5% p.a.
      const projectedValue = totalDCValue * Math.pow(1 + growthRate, yearsToRetirement);

      // 4% drawdown
      const annualDrawdown = projectedValue * 0.04;

      // Return flat amount for each year (simplified - no drawdown of capital shown)
      return this.ages.map(() => Math.round(annualDrawdown));
    },

    dbIncomeData() {
      // DB pensions provide fixed annual income
      const totalDBIncome = this.dbPensions.reduce((sum, p) => {
        return sum + parseFloat(p.accrued_annual_pension || 0);
      }, 0);

      // Start DB pensions at their normal retirement age (simplified to all start at retirement age)
      return this.ages.map(() => Math.round(totalDBIncome));
    },

    statePensionData() {
      const statePensionAge = this.statePension?.state_pension_age || 67;
      const annualAmount = parseFloat(this.statePension?.state_pension_forecast_annual || 0);

      // State pension only starts at state pension age
      return this.ages.map((age) => {
        return age >= statePensionAge ? Math.round(annualAmount) : 0;
      });
    },

    series() {
      return [
        {
          name: 'Defined Contribution Pension',
          data: this.dcIncomeData,
        },
        {
          name: 'Defined Benefit Pension',
          data: this.dbIncomeData,
        },
        {
          name: 'State Pension',
          data: this.statePensionData,
        },
      ];
    },

    chartOptions() {
      return {
        chart: {
          ...CHART_DEFAULTS.chart,
          type: 'area',
          stacked: this.viewMode === 'stacked',
          height: 400,
          toolbar: {
            show: true,
            tools: {
              download: true,
              selection: false,
              zoom: true,
              zoomin: true,
              zoomout: true,
              pan: true,
              reset: true,
            },
          },
          animations: {
            enabled: true,
            easing: 'easeinout',
            speed: 800,
          },
        },
        dataLabels: {
          enabled: false,
        },
        stroke: {
          curve: 'smooth',
          width: 2,
        },
        fill: {
          type: 'gradient',
          gradient: {
            opacityFrom: 0.6,
            opacityTo: 0.3,
            stops: [0, 90, 100],
          },
        },
        colours: CHART_COLORS.slice(0, 3),
        xaxis: {
          categories: this.ages,
          title: {
            text: 'Age',
            style: {
              fontSize: '14px',
              fontWeight: 600,
            },
          },
          labels: {
            style: {
              colors: TEXT_COLORS.muted,
            },
          },
        },
        yaxis: {
          title: {
            text: 'Annual Income (£)',
            style: {
              fontSize: '14px',
              fontWeight: 600,
            },
          },
          labels: {
            formatter: (value) => {
              return '£' + value.toLocaleString();
            },
            style: {
              colors: TEXT_COLORS.muted,
            },
          },
        },
        tooltip: {
          shared: true,
          intersect: false,
          y: {
            formatter: (value) => {
              return '£' + value.toLocaleString();
            },
          },
        },
        legend: {
          position: 'top',
          horizontalAlign: 'centre',
          fontSize: '14px',
          markers: {
            width: 12,
            height: 12,
            radius: 2,
          },
        },
        grid: {
          borderColour: BORDER_COLORS.default,
          strokeDashArray: 4,
        },
        annotations: {
          yaxis: [
            {
              y: this.targetIncome,
              borderColour: WARNING_COLORS[500],
              strokeDashArray: 5,
              label: {
                borderColour: WARNING_COLORS[500],
                style: {
                  color: '#fff',
                  background: WARNING_COLORS[500],
                },
                text: 'Target Income: £' + this.targetIncome.toLocaleString(),
              },
            },
          ],
        },
      };
    },
  },
};
</script>

<style scoped>
.income-projection-chart {
  width: 100%;
}
</style>
