<template>
  <div class="iht-liability-gauge">
    <div class="gauge-header">
      <h3>Inheritance Tax Liability Indicator</h3>
      <p class="subtitle">{{ gaugeDescription }}</p>
    </div>

    <div class="gauge-container">
      <apexchart
        v-if="mounted"
        :key="chartKey"
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
        <span class="label">Inheritance Tax Liability:</span>
        <span class="value liability-value" :class="liabilityColourClass">
          {{ formatCurrency(ihtLiability) }}
        </span>
      </div>
      <div class="detail-row">
        <span class="label">Effective Inheritance Tax Rate:</span>
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
import { SUCCESS_COLORS, WARNING_COLORS, ERROR_COLORS, BORDER_COLORS, BG_COLORS, CHART_DEFAULTS } from '@/constants/designSystem';

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
    chartKey() {
      return `iht-gauge-${Math.round(this.ihtPercentage)}`;
    },

    ihtPercentage() {
      if (this.estateValue === 0) return 0;
      return (this.ihtLiability / this.estateValue) * 100;
    },

    series() {
      return [Math.min(this.ihtPercentage, 100)];
    },

    gaugeColour() {
      // Use design system semantic colors for threshold-based coloring
      if (this.ihtPercentage >= 20) return ERROR_COLORS[500];
      if (this.ihtPercentage >= 10) return WARNING_COLORS[500];
      return SUCCESS_COLORS[500];
    },

    liabilityColourClass() {
      if (this.ihtPercentage >= 20) return 'text-raspberry-600';
      if (this.ihtPercentage >= 10) return 'text-violet-600';
      return 'text-spring-600';
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
        return 'High Inheritance Tax exposure - consider mitigation strategies';
      }
      if (this.ihtPercentage >= 10) {
        return 'Moderate Inheritance Tax liability - review planning options';
      }
      return 'Low Inheritance Tax exposure - estate planning on track';
    },

    gaugeDescription() {
      if (this.ihtPercentage >= 20) {
        return 'Your estate has significant Inheritance Tax liability';
      }
      if (this.ihtPercentage >= 10) {
        return 'Your estate has moderate Inheritance Tax exposure';
      }
      return 'Your estate has minimal Inheritance Tax exposure';
    },

    chartOptions() {
      return {
        chart: {
          ...CHART_DEFAULTS.chart,
          type: 'radialBar',
          sparkline: { enabled: false },
        },
        plotOptions: {
          radialBar: {
            startAngle: -135,
            endAngle: 135,
            hollow: {
              margin: 0,
              size: '70%',
              background: BG_COLORS.card,
            },
            track: {
              background: BORDER_COLORS.default,
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
        labels: ['Inheritance Tax Liability'],
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
  @apply text-horizon-500;
  margin: 0 0 8px 0;
}

.subtitle {
  font-size: 14px;
  @apply text-neutral-500;
  margin: 0;
}

.gauge-container {
  margin: 20px 0;
}

.gauge-details {
  margin-top: 24px;
  padding-top: 20px;
  @apply border-t border-light-gray;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  padding: 8px 0;
  font-size: 14px;
}

.detail-row .label {
  @apply text-neutral-500;
  font-weight: 500;
}

.detail-row .value {
  @apply text-horizon-500;
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
  @apply bg-spring-100 text-spring-800 border border-spring-500;
}

.status-warning {
  @apply bg-violet-100 text-violet-800 border border-violet-500;
}

.status-critical {
  @apply bg-raspberry-100 text-raspberry-800 border border-raspberry-500;
}
</style>
