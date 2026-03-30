<template>
  <div class="coverage-gap-chart">
    <apexchart
      v-if="isReady"
      :key="chartKey"
      type="heatmap"
      :options="chartOptions"
      :series="series"
      height="350"
    />
  </div>
</template>

<script>
import { SUCCESS_COLORS, WARNING_COLORS, ERROR_COLORS, CHART_DEFAULTS } from '@/constants/designSystem';
import { formatCurrency } from '@/utils/currency';

export default {
  name: 'CoverageGapChart',

  props: {
    gaps: {
      type: Array,
      required: false,
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
      return `heatmap-${this.series?.length || 0}-${this.gaps?.length || 0}`;
    },

    hasData() {
      return this.gaps && this.gaps.length > 0;
    },

    series() {
      if (!this.hasData) {
        // Return default data structure for demonstration
        return this.getDefaultSeries();
      }

      // Transform gaps data into series format
      return this.gaps;
    },

    chartOptions() {
      return {
        chart: { ...CHART_DEFAULTS.chart, type: 'heatmap' },
        dataLabels: {
          enabled: false,
        },
        colours: [ERROR_COLORS[500]],
        plotOptions: {
          heatmap: {
            shadeIntensity: 0.5,
            radius: 4,
            useFillColourAsStroke: false,
            colourScale: {
              ranges: [
                {
                  from: 0,
                  to: 50000,
                  color: SUCCESS_COLORS[500],
                  name: 'Low Gap',
                },
                {
                  from: 50001,
                  to: 150000,
                  color: WARNING_COLORS[500],
                  name: 'Medium Gap',
                },
                {
                  from: 150001,
                  to: 500000,
                  color: ERROR_COLORS[500],
                  name: 'High Gap',
                },
                {
                  from: 500001,
                  to: 10000000,
                  color: ERROR_COLORS[700],
                  name: 'Critical Gap',
                },
              ],
            },
          },
        },
        xaxis: {
          type: 'category',
          categories: ['Death', 'Critical Illness', 'Disability', 'Unemployment'],
          labels: {
            style: {
              fontSize: '12px',
            },
          },
        },
        yaxis: {
          labels: {
            style: {
              fontSize: '12px',
            },
          },
        },
        tooltip: {
          y: {
            formatter: (value) => {
              if (value === null || value === undefined) return 'No data';
              return formatCurrency(value);
            },
          },
        },
        legend: {
          show: true,
          position: 'bottom',
          horizontalAlign: 'centre',
          fontSize: '12px',
          markers: {
            width: 20,
            height: 8,
            radius: 2,
          },
        },
      };
    },
  },

  methods: {
    getDefaultSeries() {
      // Default series structure for demonstration
      return [
        {
          name: 'Now',
          data: [
            { x: 'Death', y: 200000 },
            { x: 'Critical Illness', y: 150000 },
            { x: 'Disability', y: 100000 },
            { x: 'Unemployment', y: 50000 },
          ],
        },
        {
          name: 'Age 40',
          data: [
            { x: 'Death', y: 180000 },
            { x: 'Critical Illness', y: 130000 },
            { x: 'Disability', y: 90000 },
            { x: 'Unemployment', y: 45000 },
          ],
        },
        {
          name: 'Age 50',
          data: [
            { x: 'Death', y: 150000 },
            { x: 'Critical Illness', y: 100000 },
            { x: 'Disability', y: 70000 },
            { x: 'Unemployment', y: 35000 },
          ],
        },
        {
          name: 'Age 60',
          data: [
            { x: 'Death', y: 80000 },
            { x: 'Critical Illness', y: 60000 },
            { x: 'Disability', y: 40000 },
            { x: 'Unemployment', y: 20000 },
          ],
        },
        {
          name: 'Retirement',
          data: [
            { x: 'Death', y: 30000 },
            { x: 'Critical Illness', y: 25000 },
            { x: 'Disability', y: 15000 },
            { x: 'Unemployment', y: 0 },
          ],
        },
      ];
    },
  },
};
</script>

<style scoped>
.coverage-gap-chart {
  width: 100%;
  min-height: 350px;
}
</style>
