<template>
  <div class="emergency-fund-gauge">
    <apexchart
      :key="chartKey"
      type="radialBar"
      :options="chartOptions"
      :series="[percent]"
      height="300"
    />
  </div>
</template>

<script>
import { SUCCESS_COLORS, WARNING_COLORS, ERROR_COLORS, TEXT_COLORS, BORDER_COLORS, CHART_DEFAULTS } from '@/constants/designSystem';

export default {
  name: 'EmergencyFundGauge',

  // Every value is the server's (SavingsPosition, CSJ 2026-10-01): the share of
  // the target covered, its status against the user's own target, and the
  // runway figure as the label prints it.
  props: {
    percent: {
      type: Number,
      default: 0,
    },
    status: {
      type: String,
      default: 'on_track',
    },
    figure: {
      type: String,
      default: '',
    },
  },

  computed: {
    chartKey() {
      return `gauge-${Math.round(this.percent)}`;
    },

    runwayColour() {
      return { on_track: SUCCESS_COLORS[500], part: WARNING_COLORS[500], low: ERROR_COLORS[500] }[this.status] || SUCCESS_COLORS[500];
    },

    chartOptions() {
      return {
        chart: { ...CHART_DEFAULTS.chart, type: 'radialBar' },
        plotOptions: {
          radialBar: {
            startAngle: -135,
            endAngle: 135,
            hollow: {
              margin: 0,
              size: '70%',
              background: '#fff',
              position: 'front',
              dropShadow: {
                enabled: true,
                top: 3,
                left: 0,
                blur: 4,
                opacity: 0.24,
              },
            },
            track: {
              background: BORDER_COLORS.default,
              strokeWidth: '100%',
              margin: 0,
            },
            dataLabels: {
              show: true,
              name: {
                offsetY: -10,
                show: true,
                color: TEXT_COLORS.muted,
                fontSize: '14px',
              },
              value: {
                formatter: () => this.figure,
                color: TEXT_COLORS.primary,
                fontSize: '36px',
                fontWeight: 700,
                show: true,
                offsetY: 10,
              },
            },
          },
        },
        fill: {
          type: 'solid',
          // ApexCharts reads `colors`; this was `colours`, so the gauge was always
          // the library's default blue whatever the status.
          colors: [this.runwayColour],
        },
        stroke: {
          lineCap: 'round',
        },
        // Names the basis rather than implying the money is to hand: runway counts
        // ALL cash, including notice and fixed-term accounts (W-0276, Rule 20).
        labels: ['Months from cash savings'],
      };
    },
  },
};
</script>

<style scoped>
.emergency-fund-gauge {
  width: 100%;
  max-width: 400px;
  margin: 0 auto;
}
</style>
