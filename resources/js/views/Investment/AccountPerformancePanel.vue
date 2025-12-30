<template>
  <div class="account-performance-panel">
    <!-- Loading State -->
    <div v-if="loading" class="loading-state">
      <div class="loading-spinner"></div>
      <p>Loading projections...</p>
    </div>

    <!-- Content -->
    <div v-else>
      <!-- Performance Summary Cards -->
      <div class="performance-summary">
        <div class="summary-card">
          <div class="card-icon bg-green-100">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="icon text-green-600">
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941" />
            </svg>
          </div>
          <div class="card-content">
            <span class="card-label">Current Value</span>
            <span class="card-value text-blue-600">{{ formatCurrency(account.current_value) }}</span>
          </div>
        </div>

        <div class="summary-card">
          <div class="card-icon bg-blue-100">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="icon text-blue-600">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
            </svg>
          </div>
          <div class="card-content">
            <span class="card-label">Projected (20 yrs, 50th)</span>
            <span class="card-value text-blue-600">{{ formatProjectedValue }}</span>
          </div>
        </div>

        <div class="summary-card">
          <div class="card-icon bg-purple-100">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="icon text-purple-600">
              <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 14.25v2.25m3-4.5v4.5m3-6.75v6.75m3-9v9M6 20.25h12A2.25 2.25 0 0020.25 18V6A2.25 2.25 0 0018 3.75H6A2.25 2.25 0 003.75 6v12A2.25 2.25 0 006 20.25z" />
            </svg>
          </div>
          <div class="card-content">
            <span class="card-label">Potential Growth</span>
            <span class="card-value text-purple-600">{{ formatGrowthMultiple }}</span>
          </div>
        </div>
      </div>

      <!-- Monte Carlo Projection Chart -->
      <div class="chart-section">
        <h4 class="section-title">Projected Growth (Monte Carlo Simulation)</h4>
        <p class="section-subtitle">Based on 1,000 simulations using historical market data</p>

        <div v-if="hasProjectionData" class="chart-container">
          <apexchart
            v-if="isChartReady"
            type="area"
            :options="chartOptions"
            :series="series"
            height="400"
          />
        </div>
        <div v-else class="chart-placeholder">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="placeholder-icon">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5M9 11.25v1.5M12 9v3.75m3-6v6" />
          </svg>
          <p class="placeholder-text">{{ error || 'No projection data available' }}</p>
        </div>
      </div>

      <!-- Legend -->
      <div v-if="hasProjectionData" class="legend-section">
        <h4 class="section-title">Understanding the Projections</h4>
        <div class="legend-grid">
          <div class="legend-item">
            <span class="legend-color" style="background-color: #1e3a5f;"></span>
            <div class="legend-content">
              <span class="legend-label">95% Probability</span>
              <span class="legend-desc">Conservative estimate - very high chance of achieving</span>
            </div>
          </div>
          <div class="legend-item">
            <span class="legend-color" style="background-color: #2563eb;"></span>
            <div class="legend-content">
              <span class="legend-label">90% Probability</span>
              <span class="legend-desc">Highly likely outcome based on historical data</span>
            </div>
          </div>
          <div class="legend-item">
            <span class="legend-color" style="background-color: #3b82f6;"></span>
            <div class="legend-content">
              <span class="legend-label">80% Probability</span>
              <span class="legend-desc">Good chance of reaching this level</span>
            </div>
          </div>
          <div class="legend-item">
            <span class="legend-color" style="background-color: #60a5fa;"></span>
            <div class="legend-content">
              <span class="legend-label">50% Probability (Median)</span>
              <span class="legend-desc">Middle estimate - equally likely to be above or below</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import VueApexCharts from 'vue3-apexcharts';
import { currencyMixin } from '@/mixins/currencyMixin';
import investmentService from '@/services/investmentService';

export default {
  name: 'AccountPerformancePanel',

  mixins: [currencyMixin],

  components: {
    apexchart: VueApexCharts,
  },

  props: {
    account: {
      type: Object,
      required: true,
    },
  },

  data() {
    return {
      loading: true,
      error: null,
      projectionData: null,
      isChartReady: false,
    };
  },

  computed: {
    hasProjectionData() {
      return this.projectionData?.year_by_year?.length > 0;
    },

    years() {
      if (!this.projectionData?.year_by_year) return [];
      return this.projectionData.year_by_year.map(y => y.year);
    },

    series() {
      if (!this.hasProjectionData) return [];

      // Create stacked areas for probability bands
      // Order from bottom to top: 95% (darkest) -> 50% (lightest)
      return [
        {
          name: '95% Probability',
          data: this.projectionData.year_by_year.map(y => Math.round(y.percentile_5)),
        },
        {
          name: '90% Probability',
          data: this.projectionData.year_by_year.map(y => Math.round(y.percentile_10)),
        },
        {
          name: '80% Probability',
          data: this.projectionData.year_by_year.map(y => Math.round(y.percentile_20)),
        },
        {
          name: '50% Probability',
          data: this.projectionData.year_by_year.map(y => Math.round(y.percentile_50)),
        },
      ];
    },

    chartOptions() {
      return {
        chart: {
          type: 'area',
          stacked: false,
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
          zoom: {
            enabled: false,
          },
          animations: {
            enabled: true,
            easing: 'easeinout',
            speed: 800,
          },
        },
        colors: ['#1e3a5f', '#2563eb', '#3b82f6', '#60a5fa'],
        stroke: {
          curve: 'smooth',
          width: [2, 2, 2, 2],
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
            text: 'Year',
            style: {
              fontWeight: 600,
              fontSize: '12px',
            },
          },
          labels: {
            style: {
              fontSize: '11px',
            },
            rotate: -45,
            rotateAlways: this.years.length > 15,
          },
          tickAmount: Math.min(this.years.length, 10),
        },
        yaxis: {
          title: {
            text: 'Investment Value',
            style: {
              fontWeight: 600,
              fontSize: '12px',
            },
          },
          labels: {
            formatter: (val) => this.formatCurrencyShort(val),
            style: {
              fontSize: '11px',
            },
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
          position: 'top',
          horizontalAlign: 'center',
          fontSize: '12px',
          markers: {
            width: 12,
            height: 12,
            radius: 2,
          },
        },
        grid: {
          borderColor: '#e5e7eb',
          strokeDashArray: 4,
        },
        dataLabels: {
          enabled: false,
        },
      };
    },

    formatProjectedValue() {
      if (!this.hasProjectionData) return '--';
      const lastYear = this.projectionData.year_by_year[this.projectionData.year_by_year.length - 1];
      return this.formatCurrency(lastYear?.percentile_50);
    },

    formatGrowthMultiple() {
      if (!this.hasProjectionData || !this.account.current_value) return '--';
      const lastYear = this.projectionData.year_by_year[this.projectionData.year_by_year.length - 1];
      const multiple = lastYear?.percentile_50 / this.account.current_value;
      if (!multiple || isNaN(multiple)) return '--';
      return `${multiple.toFixed(1)}x`;
    },
  },

  watch: {
    'account.id': {
      immediate: true,
      handler(newId) {
        if (newId) {
          this.loadProjections();
        }
      },
    },
  },

  methods: {
    async loadProjections() {
      this.loading = true;
      this.error = null;
      this.isChartReady = false;

      try {
        const response = await investmentService.getAccountProjections(this.account.id);
        if (response.success) {
          this.projectionData = response.data;
        } else {
          this.error = response.message || 'Failed to load projections';
        }
      } catch (err) {
        console.error('Error loading projections:', err);
        this.error = 'Failed to load projection data';
      } finally {
        this.loading = false;
        // Delay chart rendering for smooth animation
        this.$nextTick(() => {
          setTimeout(() => {
            this.isChartReady = true;
          }, 100);
        });
      }
    },

    formatCurrencyShort(value) {
      if (value === null || value === undefined) return '£0';
      if (value >= 1000000) {
        return '£' + (value / 1000000).toFixed(1) + 'M';
      }
      if (value >= 1000) {
        return '£' + (value / 1000).toFixed(0) + 'K';
      }
      return this.formatCurrency(value);
    },
  },
};
</script>

<style scoped>
.account-performance-panel {
  min-height: 400px;
}

.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 400px;
  gap: 16px;
}

.loading-spinner {
  width: 40px;
  height: 40px;
  border: 3px solid #e5e7eb;
  border-top-color: #3b82f6;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.loading-state p {
  color: #6b7280;
  font-size: 14px;
  margin: 0;
}

.performance-summary {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
  margin-bottom: 24px;
}

.summary-card {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 20px;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
}

.card-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 48px;
  height: 48px;
  border-radius: 12px;
  flex-shrink: 0;
}

.icon {
  width: 24px;
  height: 24px;
}

.card-content {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.card-label {
  font-size: 12px;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.card-value {
  font-size: 24px;
  font-weight: 700;
}

.section-title {
  font-size: 16px;
  font-weight: 600;
  color: #111827;
  margin: 0 0 4px 0;
}

.section-subtitle {
  font-size: 13px;
  color: #6b7280;
  margin: 0 0 16px 0;
}

.chart-section {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 20px;
  margin-bottom: 24px;
}

.chart-container {
  margin-top: 16px;
}

.chart-placeholder {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  background: #f9fafb;
  border-radius: 8px;
}

.placeholder-icon {
  width: 48px;
  height: 48px;
  color: #d1d5db;
  margin-bottom: 12px;
}

.placeholder-text {
  font-size: 14px;
  color: #9ca3af;
  margin: 0;
}

.legend-section {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 20px;
}

.legend-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 16px;
  margin-top: 16px;
}

.legend-item {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}

.legend-color {
  width: 16px;
  height: 16px;
  border-radius: 4px;
  flex-shrink: 0;
  margin-top: 2px;
}

.legend-content {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.legend-label {
  font-size: 14px;
  font-weight: 600;
  color: #111827;
}

.legend-desc {
  font-size: 12px;
  color: #6b7280;
}

@media (max-width: 768px) {
  .performance-summary {
    grid-template-columns: 1fr;
  }

  .legend-grid {
    grid-template-columns: 1fr;
  }

  .card-value {
    font-size: 20px;
  }
}
</style>
