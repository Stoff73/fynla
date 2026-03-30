<template>
  <div class="premium-breakdown-chart">
    <apexchart
      v-if="hasData && isReady"
      :key="chartKey"
      type="pie"
      :options="chartOptions"
      :series="series"
      height="300"
    />
    <div v-if="!hasData" class="flex items-center justify-center h-64 text-horizon-400">
      <p>No premium data available</p>
    </div>
  </div>
</template>

<script>
import { CHART_COLORS, CHART_DEFAULTS } from '@/constants/designSystem';
import { formatCurrencyWithPence } from '@/utils/currency';

import logger from '@/utils/logger';
export default {
  name: 'PremiumBreakdownChart',

  props: {
    premiums: {
      type: Object,
      required: true,
      default: () => ({
        life: 0,
        criticalIllness: 0,
        incomeProtection: 0,
        disability: 0,
        sicknessIllness: 0,
      }),
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
      const total = this.series?.reduce((a, b) => a + b, 0) || 0;
      return `premium-${this.series?.length || 0}-${Math.round(total)}`;
    },

    series() {
      // Ensure premiums object exists and has valid structure
      const validPremiums = this.premiums && typeof this.premiums === 'object' ? this.premiums : {};

      // Extract values with proper validation
      const values = [
        this.validatePremiumValue(validPremiums.life),
        this.validatePremiumValue(validPremiums.criticalIllness),
        this.validatePremiumValue(validPremiums.incomeProtection),
        this.validatePremiumValue(validPremiums.disability),
        this.validatePremiumValue(validPremiums.sicknessIllness),
      ];

      return values;
    },

    hasData() {
      // Check if series exists and has at least one valid positive value
      return Array.isArray(this.series) && this.series.some(value =>
        typeof value === 'number' && !isNaN(value) && value > 0
      );
    },

    chartOptions() {
      return {
        chart: { ...CHART_DEFAULTS.chart, type: 'pie' },
        labels: [
          'Life Insurance',
          'Critical Illness',
          'Income Protection',
          'Disability',
          'Sickness/Illness',
        ],
        colours: CHART_COLORS.slice(0, 5),
        dataLabels: {
          enabled: true,
          formatter: (val, opts) => {
            try {
              const value = opts.w.config.series[opts.seriesIndex];
              if (typeof value !== 'number' || isNaN(value)) {
                return '£0.00';
              }
              return `£${value.toFixed(2)}`;
            } catch (error) {
              logger.error('Error formatting data label:', error);
              return '£0.00';
            }
          },
        },
        legend: {
          position: 'bottom',
          horizontalAlign: 'centre',
          fontSize: '14px',
          markers: {
            width: 12,
            height: 12,
            radius: 6,
          },
          itemMargin: {
            horizontal: 8,
            vertical: 4,
          },
        },
        tooltip: {
          y: {
            formatter: (value) => {
              return formatCurrencyWithPence(value) + ' per month';
            },
          },
        },
        responsive: [
          {
            breakpoint: 480,
            options: {
              chart: {
                width: '100%',
              },
              legend: {
                position: 'bottom',
              },
            },
          },
        ],
      };
    },
  },

  methods: {
    validatePremiumValue(value) {
      // Validate and sanitize premium values
      if (value === null || value === undefined) {
        return 0;
      }

      const numValue = Number(value);

      if (isNaN(numValue) || !isFinite(numValue)) {
        console.warn('Invalid premium value:', value);
        return 0;
      }

      // Ensure non-negative
      return Math.max(0, numValue);
    },
  },
};
</script>

<style scoped>
.premium-breakdown-chart {
  width: 100%;
  min-height: 300px;
}
</style>
