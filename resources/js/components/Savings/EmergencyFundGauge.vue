<template>
  <div class="emergency-fund-gauge">
    <apexchart
      :key="chartKey"
      type="radialBar"
      :options="chartOptions"
      :series="[runwayPercentage]"
      height="300"
    />
  </div>
</template>

<script>
import { SUCCESS_COLORS, WARNING_COLORS, ERROR_COLORS, TEXT_COLORS, BORDER_COLORS, CHART_DEFAULTS } from '@/constants/designSystem';

export default {
  name: 'EmergencyFundGauge',

  props: {
    runwayMonths: {
      type: Number,
      required: true,
      default: 0,
    },
    // The cash held as a percentage of the target, from the server
    // (`analysis.emergency_fund.percent_of_target`). The gauge used to divide
    // the runway by a target of 6 on the client.
    percentOfTarget: {
      type: Number,
      default: null,
    },
  },

  computed: {
    chartKey() {
      return `gauge-${Math.round(this.runwayPercentage)}`;
    },

    runwayPercentage() {
      if (this.percentOfTarget === null) return 0;
      return Math.max(0, Math.min(this.percentOfTarget, 100));
    },

    // The same bands as `/m` and iOS: at or above the target, at least half of
    // it, below half. Measured against the user's own target, not a fixed 6.
    runwayColour() {
      if (this.percentOfTarget >= 100) return SUCCESS_COLORS[500];
      if (this.percentOfTarget >= 50) return WARNING_COLORS[500];
      return ERROR_COLORS[500];
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
                formatter: () => {
                  return this.runwayMonths.toFixed(1);
                },
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
          colours: [this.runwayColour],
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
