<template>
  <div class="fund-depletion-chart">
    <div class="chart-header">
      <div class="header-left">
        <h4 class="chart-title">Fund Depletion Projection</h4>
        <p class="chart-subtitle">How your retirement funds will be drawn down over time</p>
      </div>
      <div v-if="hasDepletionWarning" class="warning-badge">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
        </svg>
        Funds may run out
      </div>
    </div>

    <!-- Chart -->
    <div class="chart-container">
      <apexchart
        :key="chartKey"
        type="area"
        height="350"
        :options="chartOptions"
        :series="chartSeries"
      />
    </div>

    <!-- Depletion Ages -->
    <div v-if="Object.keys(depletionAges).length > 0" class="depletion-ages">
      <h5 class="section-title">Estimated Fund Depletion Ages</h5>
      <div class="ages-grid">
        <div
          v-for="(age, fund) in depletionAges"
          :key="fund"
          :class="['age-item', { 'warning': age < 90 }]"
        >
          <span class="fund-name">{{ formatFundName(fund) }}</span>
          <span class="depletion-age">
            <template v-if="age >= 100">100+</template>
            <template v-else>Age {{ age }}</template>
          </span>
          <span v-if="age < 90" class="warning-text">Early depletion</span>
        </div>
      </div>
    </div>

    <!-- Tax Impact Note -->
    <div v-if="hasIsaDepletion" class="tax-impact-note">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="note-icon">
        <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
      </svg>
      <div class="note-content">
        <strong>Tax Impact:</strong> When your ISA funds are depleted at age {{ depletionAges.isa }},
        you'll need to draw more from taxable sources, increasing your tax liability.
      </div>
    </div>
  </div>
</template>

<script>
import VueApexCharts from 'vue3-apexcharts';

export default {
  name: 'FundDepletionChart',

  components: {
    apexchart: VueApexCharts,
  },

  props: {
    projections: {
      type: Array,
      required: true,
    },
    depletionAges: {
      type: Object,
      default: () => ({}),
    },
    retirementAge: {
      type: Number,
      default: 68,
    },
  },

  computed: {
    chartKey() {
      // Force re-render when projections change
      const total = this.projections.reduce((sum, p) => sum + (p.total_funds || 0), 0);
      return `depletion-${this.projections.length}-${total}`;
    },

    hasDepletionWarning() {
      return Object.values(this.depletionAges).some(age => age < 90);
    },

    hasIsaDepletion() {
      return this.depletionAges.isa && this.depletionAges.isa < 100;
    },

    fundTypes() {
      // Extract unique fund types from projections
      if (this.projections.length === 0) return [];

      const firstProjection = this.projections[0];
      const types = [];

      if (firstProjection.dc_pension !== undefined && firstProjection.dc_pension > 0) types.push('dc_pension');
      if (firstProjection.isa !== undefined && firstProjection.isa > 0) types.push('isa');
      if (firstProjection.gia !== undefined && firstProjection.gia > 0) types.push('gia');
      if (firstProjection.bond !== undefined && firstProjection.bond > 0) types.push('bond');
      if (firstProjection.savings !== undefined && firstProjection.savings > 0) types.push('savings');

      return types;
    },

    chartSeries() {
      if (this.projections.length === 0) return [];

      const series = [];

      // DC Pension
      if (this.projections[0].dc_pension !== undefined && this.projections[0].dc_pension > 0) {
        series.push({
          name: 'Pension',
          data: this.projections.map(p => Math.round(p.dc_pension || 0)),
        });
      }

      // ISA
      if (this.projections[0].isa !== undefined && this.projections[0].isa > 0) {
        series.push({
          name: 'ISA',
          data: this.projections.map(p => Math.round(p.isa || 0)),
        });
      }

      // GIA
      if (this.projections[0].gia !== undefined && this.projections[0].gia > 0) {
        series.push({
          name: 'GIA',
          data: this.projections.map(p => Math.round(p.gia || 0)),
        });
      }

      // Bond
      if (this.projections[0].bond !== undefined && this.projections[0].bond > 0) {
        series.push({
          name: 'Bond',
          data: this.projections.map(p => Math.round(p.bond || 0)),
        });
      }

      // Savings
      if (this.projections[0].savings !== undefined && this.projections[0].savings > 0) {
        series.push({
          name: 'Savings',
          data: this.projections.map(p => Math.round(p.savings || 0)),
        });
      }

      return series;
    },

    chartOptions() {
      const ages = this.projections.map(p => p.age);

      return {
        chart: {
          type: 'area',
          stacked: true,
          toolbar: { show: false },
          zoom: { enabled: false },
          animations: { enabled: true, speed: 500 },
        },
        colors: ['#3b82f6', '#10b981', '#8b5cf6', '#f59e0b', '#6366f1'],
        fill: {
          type: 'gradient',
          gradient: {
            shadeIntensity: 1,
            opacityFrom: 0.7,
            opacityTo: 0.3,
            stops: [0, 90, 100],
          },
        },
        stroke: {
          curve: 'smooth',
          width: 2,
        },
        xaxis: {
          categories: ages,
          labels: {
            style: { colors: '#6b7280', fontSize: '11px' },
            rotate: 0,
            formatter: (val) => {
              // Show every 5th age
              if (val % 5 === 0 || val === this.retirementAge) return val;
              return '';
            },
          },
          axisBorder: { show: false },
          axisTicks: { show: false },
          title: {
            text: 'Age',
            style: { color: '#6b7280', fontSize: '12px', fontWeight: 500 },
          },
        },
        yaxis: {
          labels: {
            style: { colors: '#6b7280', fontSize: '11px' },
            formatter: (val) => '£' + (val / 1000).toFixed(0) + 'k',
          },
          title: {
            text: 'Fund Value',
            style: { color: '#6b7280', fontSize: '12px', fontWeight: 500 },
          },
        },
        grid: {
          borderColor: '#e5e7eb',
          strokeDashArray: 4,
        },
        legend: {
          position: 'top',
          horizontalAlign: 'right',
          fontSize: '12px',
          markers: { width: 10, height: 10, radius: 2 },
          itemMargin: { horizontal: 12 },
        },
        tooltip: {
          y: {
            formatter: (val) => '£' + val.toLocaleString(),
          },
          x: {
            formatter: (val) => `Age ${val}`,
          },
        },
        dataLabels: { enabled: false },
        annotations: {
          xaxis: [
            {
              x: 67,
              borderColor: '#10b981',
              label: {
                borderColor: '#10b981',
                style: {
                  color: '#fff',
                  background: '#10b981',
                  fontSize: '10px',
                },
                text: 'State Pension Age',
                position: 'top',
              },
            },
          ],
        },
      };
    },
  },

  methods: {
    formatFundName(fund) {
      const names = {
        dc_pension: 'Pension',
        isa: 'ISA',
        gia: 'GIA',
        bond: 'Bond',
        savings: 'Savings',
        total: 'All Funds',
      };
      return names[fund] || fund;
    },

    formatCurrency(value) {
      if (value === null || value === undefined) return '£0';
      return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: 'GBP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
      }).format(value);
    },
  },
};
</script>

<style scoped>
.fund-depletion-chart {
  background: white;
  border-radius: 12px;
  padding: 24px;
  border: 1px solid #e5e7eb;
}

.chart-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 20px;
  gap: 16px;
  flex-wrap: wrap;
}

.header-left {
  flex: 1;
}

.chart-title {
  font-size: 18px;
  font-weight: 600;
  color: #111827;
  margin: 0 0 4px 0;
}

.chart-subtitle {
  font-size: 14px;
  color: #6b7280;
  margin: 0;
}

.warning-badge {
  display: flex;
  align-items: center;
  gap: 6px;
  background: #fef3c7;
  color: #92400e;
  padding: 6px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
}

.warning-badge svg {
  width: 16px;
  height: 16px;
}

.chart-container {
  margin-bottom: 24px;
}

/* Depletion Ages */
.depletion-ages {
  margin-bottom: 20px;
}

.section-title {
  font-size: 14px;
  font-weight: 600;
  color: #374151;
  margin: 0 0 12px 0;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.ages-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 12px;
}

.age-item {
  background: #f9fafb;
  border-radius: 8px;
  padding: 12px 16px;
  border: 1px solid #e5e7eb;
}

.age-item.warning {
  background: #fef3c7;
  border-color: #fde68a;
}

.fund-name {
  display: block;
  font-size: 12px;
  color: #6b7280;
  margin-bottom: 4px;
}

.depletion-age {
  display: block;
  font-size: 18px;
  font-weight: 700;
  color: #111827;
}

.age-item.warning .depletion-age {
  color: #92400e;
}

.warning-text {
  display: block;
  font-size: 11px;
  color: #d97706;
  margin-top: 4px;
}

/* Tax Impact Note */
.tax-impact-note {
  display: flex;
  gap: 12px;
  background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
  border: 1px solid #bfdbfe;
  border-radius: 8px;
  padding: 16px;
}

.note-icon {
  width: 20px;
  height: 20px;
  color: #3b82f6;
  flex-shrink: 0;
  margin-top: 2px;
}

.note-content {
  font-size: 13px;
  color: #1e40af;
  line-height: 1.5;
}

.note-content strong {
  font-weight: 600;
}

@media (max-width: 640px) {
  .chart-header {
    flex-direction: column;
  }

  .ages-grid {
    grid-template-columns: 1fr 1fr;
  }
}
</style>
