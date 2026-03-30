<template>
  <div class="accumulation-chart">
    <apexchart
      :key="chartKey"
      type="line"
      :options="chartOptions"
      :series="series"
      height="350"
    ></apexchart>
  </div>
</template>

<script>
import { mapState } from 'vuex';
import { PRIMARY_COLORS, SECONDARY_COLORS, BORDER_COLORS, CHART_DEFAULTS, TEXT_COLORS } from '@/constants/designSystem';

export default {
  name: 'AccumulationChart',

  computed: {
    ...mapState('retirement', ['dcPensions', 'profile']),

    chartKey() {
      const finalValue = this.projectionData?.[this.projectionData?.length - 1] || 0;
      return `accumulation-${this.ages.length}-${Math.round(finalValue)}`;
    },

    currentAge() {
      return this.profile?.current_age || 40;
    },

    retirementAge() {
      return this.profile?.target_retirement_age || 67;
    },

    currentIncome() {
      return this.profile?.current_income || 50000;
    },

    yearsToRetirement() {
      return Math.max(0, this.retirementAge - this.currentAge);
    },

    ages() {
      const ages = [];
      for (let age = this.currentAge; age <= this.retirementAge; age++) {
        ages.push(age);
      }
      return ages;
    },

    projectionData() {
      const data = [];
      let currentValue = this.dcPensions.reduce((sum, p) => {
        return sum + parseFloat(p.current_fund_value || 0);
      }, 0);

      // Calculate total annual contributions
      const totalContributions = this.dcPensions.reduce((sum, p) => {
        const employeePercent = parseFloat(p.employee_contribution_percent || 0);
        const employerPercent = parseFloat(p.employer_contribution_percent || 0);
        const totalPercent = employeePercent + employerPercent;
        return sum + (this.currentIncome * totalPercent / 100);
      }, 0);

      // Average growth rate
      const avgGrowthRate = this.dcPensions.reduce((sum, p) => {
        return sum + parseFloat(p.expected_return_percent || 5.0);
      }, 0) / (this.dcPensions.length || 1) / 100;

      // Project forward year by year
      data.push(Math.round(currentValue));

      for (let year = 1; year <= this.yearsToRetirement; year++) {
        // Add contributions at the start of the year
        currentValue += totalContributions;
        // Apply growth
        currentValue *= (1 + avgGrowthRate);
        data.push(Math.round(currentValue));
      }

      return data;
    },

    contributionsOnlyData() {
      const data = [];
      let contributionTotal = this.dcPensions.reduce((sum, p) => {
        return sum + parseFloat(p.current_fund_value || 0);
      }, 0);

      const totalAnnualContributions = this.dcPensions.reduce((sum, p) => {
        const employeePercent = parseFloat(p.employee_contribution_percent || 0);
        const employerPercent = parseFloat(p.employer_contribution_percent || 0);
        const totalPercent = employeePercent + employerPercent;
        return sum + (this.currentIncome * totalPercent / 100);
      }, 0);

      data.push(Math.round(contributionTotal));

      for (let year = 1; year <= this.yearsToRetirement; year++) {
        contributionTotal += totalAnnualContributions;
        data.push(Math.round(contributionTotal));
      }

      return data;
    },

    series() {
      return [
        {
          name: 'With Investment Growth',
          data: this.projectionData,
        },
        {
          name: 'Contributions Only',
          data: this.contributionsOnlyData,
        },
      ];
    },

    chartOptions() {
      return {
        chart: {
          ...CHART_DEFAULTS.chart,
          type: 'line',
          height: 350,
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
        stroke: {
          curve: 'smooth',
          width: [3, 2],
          dashArray: [0, 5],
        },
        colors: [PRIMARY_COLORS[500], SECONDARY_COLORS[500]], // Blue for growth, Gray for contributions only
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
            text: 'Pension Pot Value (£)',
            style: {
              fontSize: '14px',
              fontWeight: 600,
            },
          },
          labels: {
            formatter: (value) => {
              return '£' + Math.round(value).toLocaleString();
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
              return '£' + Math.round(value).toLocaleString();
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
          borderColor: BORDER_COLORS.default,
          strokeDashArray: 4,
        },
        markers: {
          size: [4, 0],
          colors: [PRIMARY_COLORS[500]],
          strokeColors: '#fff',
          strokeWidth: 2,
          hover: {
            size: 6,
          },
        },
      };
    },
  },
};
</script>

<style scoped>
.accumulation-chart {
  width: 100%;
}
</style>
