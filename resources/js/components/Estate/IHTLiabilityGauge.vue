<template>
  <div class="iht-liability-gauge">
    <div class="gauge-header">
      <h3>IHT Liability Indicator</h3>
      <p class="subtitle">{{ gaugeDescription }}</p>
    </div>

    <div class="gauge-container">
      <apexchart
        v-if="mounted"
        type="radialBar"
        height="300"
        :options="chartOptions"
        :series="series"
      />
    </div>

    <div class="gauge-details">
      <div class="detail-row">
        <span class="label">Total Estate Value:</span>
        <span class="value">{{ formatCurrency(estateValue) }}</span>
      </div>
      <div class="detail-row">
        <span class="label">IHT Liability:</span>
        <span class="value liability-value" :class="liabilityColourClass">
          {{ formatCurrency(ihtLiability) }}
        </span>
      </div>
      <div class="detail-row">
        <span class="label">Effective IHT Rate:</span>
        <span class="value">{{ ihtPercentage.toFixed(1) }}% of estate</span>
      </div>
    </div>

    <div class="status-indicator" :class="statusClass">
      <i :class="statusIcon"></i>
      <span>{{ statusMessage }}</span>
    </div>
  </div>
</template>

<script>
import VueApexCharts from 'vue3-apexcharts';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'IHTLiabilityGauge',
  mixins: [currencyMixin],

  components: {
    apexchart: VueApexCharts,
  },

  props: {
    ihtLiability: {
      type: Number,
      required: true,
      default: 0,
    },
    estateValue: {
      type: Number,
      required: true,
      default: 0,
    },
  },

  data() {
    return {
      mounted: false,
    };
  },

  mounted() {
    this.$nextTick(() => {
      this.mounted = true;
    });
  },

  beforeUnmount() {
    this.mounted = false;
  },

  computed: {
    ihtPercentage() {
      if (this.estateValue === 0) return 0;
      return (this.ihtLiability / this.estateValue) * 100;
    },

    series() {
      return [Math.min(this.ihtPercentage, 100)];
    },

    gaugeColour() {
      if (this.ihtPercentage >= 20) return '#EF4444'; // Red
      if (this.ihtPercentage >= 10) return '#F59E0B'; // Amber
      return '#10B981'; // Green
    },

    liabilityColourClass() {
      if (this.ihtPercentage >= 20) return 'text-red-600';
      if (this.ihtPercentage >= 10) return 'text-amber-600';
      return 'text-green-600';
    },

    statusClass() {
      if (this.ihtPercentage >= 20) return 'status-critical';
      if (this.ihtPercentage >= 10) return 'status-warning';
      return 'status-good';
    },

    statusIcon() {
      if (this.ihtPercentage >= 20) return 'fas fa-exclamation-triangle';
      if (this.ihtPercentage >= 10) return 'fas fa-exclamation-circle';
      return 'fas fa-check-circle';
    },

    statusMessage() {
      if (this.ihtPercentage >= 20) {
        return 'High IHT exposure - consider mitigation strategies';
      }
      if (this.ihtPercentage >= 10) {
        return 'Moderate IHT liability - review planning options';
      }
      return 'Low IHT exposure - estate planning on track';
    },

    gaugeDescription() {
      if (this.ihtPercentage >= 20) {
        return 'Your estate has significant IHT liability';
      }
      if (this.ihtPercentage >= 10) {
        return 'Your estate has moderate IHT exposure';
      }
      return 'Your estate has minimal IHT exposure';
    },

    chartOptions() {
      return {
        chart: {
          type: 'radialBar',
          sparkline: {
            enabled: false,
          },
        },
        plotOptions: {
          radialBar: {
            startAngle: -135,
            endAngle: 135,
            hollow: {
              margin: 0,
              size: '70%',
              background: '#fff',
            },
            track: {
              background: '#e7e7e7',
              strokeWidth: '97%',
              margin: 5,
            },
            dataLabels: {
              show: true,
              name: {
                offsetY: -10,
                show: true,
                color: '#888',
                fontSize: '14px',
              },
              value: {
                formatter: (val) => {
                  return this.formatCurrency(this.ihtLiability);
                },
                color: this.gaugeColour,
                fontSize: '28px',
                fontWeight: 'bold',
                show: true,
                offsetY: 5,
              },
            },
          },
        },
        fill: {
          type: 'gradient',
          gradient: {
            shade: 'dark',
            type: 'horizontal',
            shadeIntensity: 0.5,
            gradientToColours: [this.gaugeColour],
            inverseColours: true,
            opacityFrom: 1,
            opacityTo: 1,
            stops: [0, 100],
          },
        },
        colours: [this.gaugeColour],
        stroke: {
          lineCap: 'round',
        },
        labels: ['IHT Liability'],
      };
    },
  },

};
</script>

<style scoped>
.iht-liability-gauge {
  background: white;
  border-radius: 8px;
  padding: 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.gauge-header {
  text-align: center;
  margin-bottom: 20px;
}

.gauge-header h3 {
  font-size: 18px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 8px 0;
}

.subtitle {
  font-size: 14px;
  color: #6b7280;
  margin: 0;
}

.gauge-container {
  margin: 20px 0;
}

.gauge-details {
  margin-top: 24px;
  padding-top: 20px;
  border-top: 1px solid #e5e7eb;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  padding: 8px 0;
  font-size: 14px;
}

.detail-row .label {
  color: #6b7280;
  font-weight: 500;
}

.detail-row .value {
  color: #1f2937;
  font-weight: 600;
}

.liability-value {
  font-size: 16px;
}

.status-indicator {
  margin-top: 20px;
  padding: 12px 16px;
  border-radius: 6px;
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 14px;
  font-weight: 500;
}

.status-indicator i {
  font-size: 18px;
}

.status-good {
  background-color: #d1fae5;
  color: #065f46;
  border: 1px solid #10b981;
}

.status-warning {
  background-color: #fef3c7;
  color: #92400e;
  border: 1px solid #f59e0b;
}

.status-critical {
  background-color: #fee2e2;
  color: #991b1b;
  border: 1px solid #ef4444;
}
</style>
