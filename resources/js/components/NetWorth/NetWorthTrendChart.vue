<template>
  <div class="net-worth-trend-chart disabled">
    <h3 class="chart-title">Net Worth Trend</h3>
    <div class="coming-soon-overlay">
      <div class="coming-soon-content">
        <svg class="coming-soon-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <p class="coming-soon-text">Coming Soon</p>
        <p class="coming-soon-description">Net worth trend tracking will be available in a future update</p>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'NetWorthTrendChart',
  mixins: [currencyMixin],

  props: {
    trend: {
      type: Array,
      required: true,
      default: () => [],
    },
  },

  computed: {
    hasData() {
      return this.trend && this.trend.length > 0;
    },

    series() {
      return [
        {
          name: 'Net Worth',
          data: this.trend.map(item => item.net_worth || 0),
        },
      ];
    },

    categories() {
      return this.trend.map(item => item.month || '');
    },

    chartOptions() {
      return {
        chart: {
          type: 'area',
          fontFamily: 'Inter, system-ui, sans-serif',
          toolbar: {
            show: true,
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
        },
        dataLabels: {
          enabled: false,
        },
        stroke: {
          curve: 'smooth',
          width: 3,
        },
        colours: ['#3B82F6'],
        fill: {
          type: 'gradient',
          gradient: {
            shadeIntensity: 1,
            opacityFrom: 0.7,
            opacityTo: 0.2,
            stops: [0, 90, 100],
          },
        },
        xaxis: {
          categories: this.categories,
          labels: {
            style: {
              fontSize: '12px',
              colours: '#6b7280',
            },
          },
        },
        yaxis: {
          labels: {
            style: {
              fontSize: '12px',
              colours: '#6b7280',
            },
            formatter: (val) => {
              return this.formatCurrency(val);
            },
          },
        },
        tooltip: {
          y: {
            formatter: (val) => {
              return this.formatCurrency(val);
            },
          },
        },
        grid: {
          borderColour: '#e5e7eb',
          strokeDashArray: 4,
        },
        responsive: [
          {
            breakpoint: 768,
            options: {
              chart: {
                height: 300,
              },
              xaxis: {
                labels: {
                  rotate: -45,
                },
              },
            },
          },
        ],
      };
    },
  },

};
</script>

<style scoped>
.net-worth-trend-chart {
  background: white;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  border: 1px solid #e5e7eb;
  position: relative;
}

.net-worth-trend-chart.disabled {
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  opacity: 0.6;
}

.chart-title {
  font-size: 18px;
  font-weight: 600;
  color: #111827;
  margin: 0 0 20px 0;
}

.net-worth-trend-chart.disabled .chart-title {
  color: #9ca3af;
}

.coming-soon-overlay {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 350px;
  padding: 60px 20px;
}

.coming-soon-content {
  text-align: center;
  max-width: 300px;
}

.coming-soon-icon {
  width: 64px;
  height: 64px;
  color: #d1d5db;
  margin: 0 auto 16px;
}

.coming-soon-text {
  font-size: 20px;
  font-weight: 600;
  color: #9ca3af;
  margin: 0 0 8px 0;
}

.coming-soon-description {
  font-size: 14px;
  color: #9ca3af;
  margin: 0;
  line-height: 1.5;
}

.chart-container {
  width: 100%;
}

.no-data {
  text-align: center;
  padding: 60px 20px;
  color: #9ca3af;
}

.no-data p {
  margin: 0;
  font-size: 14px;
}

@media (max-width: 768px) {
  .net-worth-trend-chart {
    padding: 16px;
  }

  .chart-title {
    font-size: 16px;
  }

  .coming-soon-overlay {
    min-height: 300px;
    padding: 40px 20px;
  }

  .coming-soon-icon {
    width: 48px;
    height: 48px;
  }

  .coming-soon-text {
    font-size: 18px;
  }

  .coming-soon-description {
    font-size: 13px;
  }
}
</style>
