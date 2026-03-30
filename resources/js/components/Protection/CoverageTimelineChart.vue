<template>
  <div class="coverage-timeline-chart">
    <apexchart
      v-if="hasData && isReady"
      :key="chartKey"
      type="rangeBar"
      :options="chartOptions"
      :series="series"
      height="350"
    />
    <div v-if="!hasData" class="flex items-center justify-center h-64 text-horizon-400">
      <p>No policy timeline data available</p>
    </div>
  </div>
</template>

<script>
import { CHART_COLORS, SUCCESS_COLORS, WARNING_COLORS, ERROR_COLORS, TEXT_COLORS, CHART_DEFAULTS } from '@/constants/designSystem';

export default {
  name: 'CoverageTimelineChart',

  props: {
    policies: {
      type: Array,
      required: true,
      default: () => [],
    },
  },

  data() {
    return {
      isReady: false,
    };
  },

  mounted() {
    this.$nextTick(() => {
      this.isReady = true;
    });
  },

  computed: {
    chartKey() {
      return `timeline-${this.policies.length}-${this.policies[0]?.id || 0}`;
    },

    hasData() {
      return this.policies && this.policies.length > 0;
    },

    series() {
      const policyTypeColours = {
        life: CHART_COLORS[0],
        criticalIllness: CHART_COLORS[5],
        incomeProtection: SUCCESS_COLORS[500],
        disability: WARNING_COLORS[500],
        sicknessIllness: ERROR_COLORS[500],
      };

      const seriesData = this.policies.map((policy) => {
        const startDate = new Date(policy.start_date || Date.now());
        let endDate;

        if (policy.term_years) {
          endDate = new Date(startDate);
          endDate.setFullYear(endDate.getFullYear() + parseInt(policy.term_years));
        } else if (policy.benefit_period_months) {
          endDate = new Date(startDate);
          endDate.setMonth(endDate.getMonth() + parseInt(policy.benefit_period_months));
        } else {
          // Default to 10 years if no term specified
          endDate = new Date(startDate);
          endDate.setFullYear(endDate.getFullYear() + 10);
        }

        const policyType = policy.policy_type || 'life';
        const policyLabel = this.getPolicyLabel(policy, policyType);

        return {
          x: policyLabel,
          y: [startDate.getTime(), endDate.getTime()],
          fillColour: policyTypeColours[policyType] || TEXT_COLORS.muted,
        };
      });

      return [
        {
          name: 'Policy Term',
          data: seriesData,
        },
      ];
    },

    chartOptions() {
      return {
        chart: { ...CHART_DEFAULTS.chart, type: 'rangeBar' },
        plotOptions: {
          bar: {
            horizontal: true,
            distributed: true,
            dataLabels: {
              hideOverflowingLabels: false,
            },
          },
        },
        dataLabels: {
          enabled: false,
        },
        xaxis: {
          type: 'datetime',
          labels: {
            datetimeUTC: false,
            format: 'yyyy',
          },
        },
        yaxis: {
          labels: {
            style: {
              fontSize: '12px',
            },
          },
        },
        legend: {
          show: false,
        },
        tooltip: {
          custom: ({ seriesIndex, dataPointIndex, w }) => {
            const data = w.config.series[seriesIndex].data[dataPointIndex];
            const startDate = new Date(data.y[0]);
            const endDate = new Date(data.y[1]);

            return `
              <div class="apexcharts-tooltip-rangebar" style="padding: 8px 12px;">
                <div style="font-weight: 600; margin-bottom: 4px;">${data.x}</div>
                <div style="font-size: 12px;">
                  Start: ${startDate.toLocaleDateString('en-GB')}<br/>
                  End: ${endDate.toLocaleDateString('en-GB')}
                </div>
              </div>
            `;
          },
        },
        grid: {
          xaxis: {
            lines: {
              show: true,
            },
          },
          yaxis: {
            lines: {
              show: false,
            },
          },
        },
      };
    },
  },

  methods: {
    getPolicyLabel(policy, policyType) {
      const typeLabels = {
        life: 'Life',
        criticalIllness: 'Critical Illness',
        incomeProtection: 'Income Protection',
        disability: 'Disability',
        sicknessIllness: 'Sickness/Illness',
      };

      const typeLabel = typeLabels[policyType] || 'Policy';
      const provider = policy.provider || 'Unknown';

      return `${typeLabel} - ${provider}`;
    },
  },
};
</script>

<style scoped>
.coverage-timeline-chart {
  width: 100%;
  min-height: 350px;
}
</style>
