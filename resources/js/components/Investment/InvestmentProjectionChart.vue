<template>
  <div class="investment-projection-chart">
    <apexchart
      v-if="isReady && series.length > 0"
      type="area"
      :options="chartOptions"
      :series="series"
      :height="compact ? 250 : 350"
    />
    <div v-else class="chart-placeholder">
      <p>No projection data available</p>
    </div>
    <p v-if="!compact && riskMessage" class="chart-footer">{{ riskMessage }}</p>
  </div>
</template>

<script>
import VueApexCharts from 'vue3-apexcharts';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'InvestmentProjectionChart',
  mixins: [currencyMixin],

  components: {
    apexchart: VueApexCharts,
  },

  props: {
    data: {
      type: Object,
      required: true,
    },
    title: {
      type: String,
      default: 'Portfolio Value',
    },
    compact: {
      type: Boolean,
      default: false,
    },
    riskSource: {
      type: String,
      default: null,
    },
    expectedReturn: {
      type: Number,
      default: null,
    },
    riskLevel: {
      type: String,
      default: null,
    },
  },

  data() {
    return {
      isReady: false,
    };
  },

  computed: {
    years() {
      if (!this.data?.year_by_year) return [];
      return this.data.year_by_year.map(y => y.year);
    },

    series() {
      if (!this.data?.year_by_year || this.data.year_by_year.length === 0) return [];

      // Create stacked areas for probability bands (4 bands to match retirement chart)
      // Order from bottom to top: 95% (darkest) -> 80% (lightest)
      return [
        {
          name: '95% Probability',
          data: this.data.year_by_year.map(y => y.percentile_5),
        },
        {
          name: '90% Probability',
          data: this.data.year_by_year.map(y => y.percentile_10),
        },
        {
          name: '85% Probability',
          data: this.data.year_by_year.map(y => y.percentile_15),
        },
        {
          name: '80% Probability',
          data: this.data.year_by_year.map(y => y.percentile_20),
        },
      ];
    },

    riskMessage() {
      // Don't show risk message if no risk profile set
      if (!this.riskSource || !this.expectedReturn || this.riskSource === 'default') {
        return null;
      }

      const levelDisplay = this.formatRiskLevel(this.riskLevel);
      return `Using ${levelDisplay} risk profile (${this.expectedReturn}% expected return)`;
    },

    chartOptions() {
      return {
        chart: {
          type: 'area',
          stacked: false,
          fontFamily: 'Inter, system-ui, sans-serif',
          toolbar: {
            show: !this.compact,
            tools: {
              download: true,
              selection: false,
              zoom: false,
              zoomin: false,
              zoomout: false,
              pan: false,
              reset: false,
            },
          },
          zoom: { enabled: false },
          animations: {
            enabled: true,
            easing: 'easeinout',
            speed: 800,
          },
        },
        // Blue and green gradient for more contrast
        colors: ['#1e3a5f', '#2563eb', '#059669', '#34d399'],
        stroke: {
          curve: 'smooth',
          width: [1, 1, 1, 1],
        },
        fill: {
          type: 'gradient',
          gradient: {
            opacityFrom: 0.5,
            opacityTo: 0.1,
            stops: [0, 90, 100],
          },
        },
        xaxis: {
          categories: this.years,
          title: {
            text: this.compact ? '' : 'Year',
            style: { fontWeight: 600, fontSize: '12px' },
          },
          labels: {
            style: { fontSize: '11px' },
            rotate: -45,
            rotateAlways: this.years.length > 15,
          },
          tickAmount: Math.min(this.years.length, this.compact ? 5 : 10),
        },
        yaxis: {
          title: {
            text: this.compact ? '' : this.title,
            style: { fontWeight: 600, fontSize: '12px' },
          },
          labels: {
            formatter: (val) => this.formatCurrencyShort(val),
            style: { fontSize: '11px' },
          },
        },
        tooltip: {
          shared: true,
          intersect: false,
          y: {
            formatter: (val) => this.formatCurrency(val),
          },
        },
        legend: {
          show: !this.compact,
          position: 'top',
          horizontalAlign: 'center',
          fontSize: '12px',
          markers: { width: 12, height: 12, radius: 2 },
        },
        grid: {
          borderColor: '#e5e7eb',
          strokeDashArray: 4,
        },
        dataLabels: { enabled: false },
      };
    },
  },

  mounted() {
    this.$nextTick(() => {
      setTimeout(() => {
        this.isReady = true;
      }, 100);
    });
  },

  methods: {
    formatCurrencyShort(value) {
      if (value === null || value === undefined) return '£0';
      if (value >= 1000000) return '£' + (value / 1000000).toFixed(1) + 'M';
      if (value >= 1000) return '£' + (value / 1000).toFixed(0) + 'K';
      return this.formatCurrency(value);
    },

    formatRiskLevel(level) {
      const levels = {
        low: 'Low',
        lower_medium: 'Lower-Medium',
        medium: 'Medium',
        upper_medium: 'Upper-Medium',
        high: 'High',
      };
      return levels[level] || level || 'Unknown';
    },
  },
};
</script>

<style scoped>
.investment-projection-chart {
  width: 100%;
}

.chart-placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 350px;
  background: #f9fafb;
  border-radius: 8px;
  border: 1px dashed #d1d5db;
}

.chart-placeholder p {
  color: #6b7280;
  font-size: 14px;
  margin: 0;
}

.chart-footer {
  text-align: center;
  font-size: 12px;
  color: #6b7280;
  margin: 8px 0 0 0;
  font-style: italic;
}
</style>
