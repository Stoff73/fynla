<template>
  <div class="asset-allocation-donut">
    <h3 class="chart-title">Wealth Allocation</h3>
    <div v-if="hasData" class="chart-container">
      <apexchart
        type="donut"
        :options="chartOptions"
        :series="filteredSeries"
        height="350"
      ></apexchart>
    </div>
    <div v-else class="no-data">
      <p>No wealth data available</p>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'AssetAllocationDonut',
  mixins: [currencyMixin],

  props: {
    breakdown: {
      type: Object,
      required: true,
      default: () => ({}),
    },
  },

  computed: {
    hasData() {
      return this.filteredSeries.some(value => value > 0);
    },

    allCategories() {
      // All possible categories with their values and labels
      return [
        { label: 'Pensions', value: this.breakdown.pensions || 0, color: '#6366F1' },
        { label: 'Property', value: this.breakdown.property || 0, color: '#10B981' },
        { label: 'Investments', value: this.breakdown.investments || 0, color: '#3B82F6' },
        { label: 'Cash & Savings', value: this.breakdown.cash || 0, color: '#F59E0B' },
        { label: 'Business', value: this.breakdown.business || 0, color: '#8B5CF6' },
        { label: 'Chattels', value: this.breakdown.chattels || 0, color: '#EC4899' },
      ];
    },

    filteredCategories() {
      // Filter out categories with zero values
      return this.allCategories.filter(cat => cat.value > 0);
    },

    filteredSeries() {
      // Array of values for non-zero categories
      return this.filteredCategories.map(cat => cat.value);
    },

    filteredLabels() {
      // Array of labels for non-zero categories
      return this.filteredCategories.map(cat => cat.label);
    },

    filteredColors() {
      // Array of colors for non-zero categories
      return this.filteredCategories.map(cat => cat.color);
    },

    chartOptions() {
      return {
        chart: {
          type: 'donut',
          fontFamily: 'Inter, system-ui, sans-serif',
        },
        labels: this.filteredLabels,
        colors: this.filteredColors,
        legend: {
          position: 'bottom',
          fontSize: '14px',
        },
        dataLabels: {
          enabled: true,
          formatter: (val) => {
            return val.toFixed(1) + '%';
          },
        },
        plotOptions: {
          pie: {
            donut: {
              size: '65%',
              labels: {
                show: true,
                name: {
                  show: true,
                  fontSize: '16px',
                  fontWeight: 600,
                },
                value: {
                  show: true,
                  fontSize: '24px',
                  fontWeight: 700,
                  formatter: (val) => {
                    return this.formatCurrency(val);
                  },
                },
                total: {
                  show: true,
                  label: 'Total Wealth',
                  fontSize: '14px',
                  fontWeight: 600,
                  color: '#6b7280',
                  formatter: () => {
                    const total = this.filteredSeries.reduce((sum, val) => sum + val, 0);
                    return this.formatCurrency(total);
                  },
                },
              },
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
        responsive: [
          {
            breakpoint: 768,
            options: {
              chart: {
                height: 300,
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

};
</script>

<style scoped>
.asset-allocation-donut {
  background: white;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  border: 1px solid #e5e7eb;
}

.chart-title {
  font-size: 18px;
  font-weight: 600;
  color: #111827;
  margin: 0 0 20px 0;
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
  .asset-allocation-donut {
    padding: 16px;
  }

  .chart-title {
    font-size: 16px;
  }
}
</style>
