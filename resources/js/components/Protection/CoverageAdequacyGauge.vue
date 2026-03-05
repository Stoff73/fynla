<template>
  <div class="coverage-adequacy-gauge">
    <apexchart
      :key="chartKey"
      type="radialBar"
      :options="chartOptions"
      :series="[score]"
      height="300"
    />
  </div>
</template>

<script>
import { getColorByThreshold, TEXT_COLORS, BORDER_COLORS } from '@/constants/designSystem';

export default {
  name: 'CoverageAdequacyGauge',

  props: {
    score: {
      type: Number,
      required: true,
      validator: (value) => value >= 0 && value <= 100,
      default: 0,
    },
  },

  computed: {
    chartKey() {
      return `adequacy-${Math.round(this.score)}`;
    },

    scoreColour() {
      // Use design system threshold-based color helper
      return getColorByThreshold(this.score, { success: 80, warning: 60 });
    },

    chartOptions() {
      return {
        chart: {
          type: 'radialBar',
          fontFamily: 'Segoe UI, Inter, sans-serif',
        },
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
                formatter: (val) => {
                  return parseInt(val) + '%';
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
          colours: [this.scoreColour],
        },
        stroke: {
          lineCap: 'round',
        },
        labels: ['Adequacy Score'],
      };
    },
  },
};
</script>

<style scoped>
.coverage-adequacy-gauge {
  width: 100%;
  max-width: 400px;
  margin: 0 auto;
}
</style>
