<template>
  <div class="gifting-timeline-chart">
    <div class="chart-header">
      <h3>7-Year Gifting Timeline</h3>
      <p class="subtitle">Gifts become Inheritance Tax-exempt after surviving 7 years</p>
    </div>

    <div v-if="gifts && gifts.length > 0" class="chart-container">
      <apexchart
        v-if="mounted"
        :key="chartKey"
        type="rangeBar"
        height="400"
        :options="chartOptions"
        :series="series"
      />
    </div>

    <div v-else class="empty-state">
      <i class="fas fa-gift fa-3x"></i>
      <p>No gifts recorded yet</p>
      <p class="subtitle">Start tracking your gifts to monitor the 7-year rule</p>
    </div>

    <!-- Legend -->
    <div class="legend">
      <div class="legend-item">
        <span class="legend-colour bg-spring-600"></span>
        <span>Exempt gifts (spouse/charity) - immediately Inheritance Tax-free</span>
      </div>
      <div class="legend-item">
        <span class="legend-colour bg-raspberry-500"></span>
        <span>Within 7 years (potentially taxable)</span>
      </div>
      <div class="legend-item">
        <span class="legend-colour bg-violet-500"></span>
        <span>Years 3-7 (taper relief applies)</span>
      </div>
      <div class="legend-item">
        <span class="legend-colour bg-spring-600"></span>
        <span>Survived 7 years (Inheritance Tax-exempt)</span>
      </div>
    </div>

    <!-- Taper Relief Table -->
    <div class="taper-relief-info">
      <h4>Taper Relief Rates</h4>
      <p class="relief-note">
        <strong>Note:</strong> Gifts to your spouse or civil partner are exempt from Inheritance Tax under the unlimited spouse exemption and do not need to survive 7 years. The 7-year rule only applies to Potentially Exempt Transfers to other individuals.
      </p>
      <table class="relief-table">
        <thead>
          <tr>
            <th>Years Since Gift</th>
            <th>Tax Rate on Gift</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>0-3 years</td>
            <td>40% (full rate)</td>
          </tr>
          <tr>
            <td>3-4 years</td>
            <td>32% (20% relief)</td>
          </tr>
          <tr>
            <td>4-5 years</td>
            <td>24% (40% relief)</td>
          </tr>
          <tr>
            <td>5-6 years</td>
            <td>16% (60% relief)</td>
          </tr>
          <tr>
            <td>6-7 years</td>
            <td>8% (80% relief)</td>
          </tr>
          <tr class="highlight">
            <td>7+ years</td>
            <td>0% (100% relief - exempt)</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script>
import VueApexCharts from 'vue3-apexcharts';
import { currencyMixin } from '@/mixins/currencyMixin';
import { SUCCESS_COLORS, WARNING_COLORS, ERROR_COLORS, CHART_DEFAULTS } from '@/constants/designSystem';

export default {
  name: 'GiftingTimelineChart',
  mixins: [currencyMixin],

  components: {
    apexchart: VueApexCharts,
  },

  props: {
    gifts: {
      type: Array,
      required: true,
      default: () => [],
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
      const totalValue = this.gifts?.reduce((sum, g) => sum + (g.gift_value || 0), 0) || 0;
      return `gifting-${this.gifts?.length || 0}-${Math.round(totalValue)}`;
    },

    series() {
      if (!this.gifts || this.gifts.length === 0) {
        return [];
      }

      const today = new Date();

      return [
        {
          name: 'Gift Timeline',
          data: this.gifts.map((gift) => {
            const giftDate = new Date(gift.gift_date);
            const sevenYearsLater = new Date(giftDate);
            sevenYearsLater.setFullYear(sevenYearsLater.getFullYear() + 7);

            const yearsElapsed = this.calculateYearsElapsed(giftDate, today);
            const colour = this.getGiftColour(yearsElapsed, gift.gift_type);

            return {
              x: gift.recipient || 'Unknown',
              y: [giftDate.getTime(), sevenYearsLater.getTime()],
              fillColour: colour,
              meta: {
                gift_value: gift.gift_value,
                gift_type: gift.gift_type,
                gift_date: gift.gift_date,
                years_elapsed: yearsElapsed.toFixed(1),
                years_remaining: gift.gift_type === 'exempt' ? 'N/A (Exempt)' : Math.max(0, 7 - yearsElapsed).toFixed(1),
                taper_relief: gift.gift_type === 'exempt' ? 'N/A (Exempt)' : this.calculateTaperRelief(yearsElapsed),
                status: this.getGiftStatus(yearsElapsed, gift.gift_type),
              },
            };
          }),
        },
      ];
    },

    chartOptions() {
      return {
        chart: {
          ...CHART_DEFAULTS.chart,
          type: 'rangeBar',
          height: 400,
          toolbar: {
            show: true,
            tools: { download: true, zoom: false, zoomin: false, zoomout: false, pan: false, reset: false },
          },
        },
        plotOptions: {
          bar: {
            horizontal: true,
            rangeBarGroupRows: true,
            borderRadius: 4,
          },
        },
        xaxis: {
          type: 'datetime',
          labels: {
            datetimeUTC: false,
            format: 'yyyy',
          },
        },
        yaxis: {
          labels: {
            style: {
              fontSize: '12px',
            },
          },
        },
        dataLabels: {
          enabled: true,
          formatter: (val, opts) => {
            const meta = opts.w.config.series[0].data[opts.dataPointIndex].meta;
            return this.formatCurrency(meta.gift_value);
          },
          style: {
            fontSize: '11px',
            fontWeight: 'bold',
          },
          background: {
            enabled: true,
            foreColor: '#fff',
            padding: 4,
            borderRadius: 2,
            borderWidth: 0,
            opacity: 0.9,
            dropShadow: {
              enabled: false,
            },
          },
        },
        colors: this.gifts.map((gift) => {
          const giftDate = new Date(gift.gift_date);
          const today = new Date();
          const yearsElapsed = this.calculateYearsElapsed(giftDate, today);
          return this.getGiftColour(yearsElapsed, gift.gift_type);
        }),
        tooltip: {
          custom: ({ seriesIndex, dataPointIndex, w }) => {
            const data = w.config.series[seriesIndex].data[dataPointIndex];
            const meta = data.meta;

            return `
              <div class="custom-tooltip">
                <div class="tooltip-header">
                  <strong>${data.x}</strong>
                </div>
                <div class="tooltip-body">
                  <div class="tooltip-row">
                    <span>Gift Value:</span>
                    <strong>${this.formatCurrency(meta.gift_value)}</strong>
                  </div>
                  <div class="tooltip-row">
                    <span>Gift Type:</span>
                    <strong>${this.formatGiftType(meta.gift_type)}</strong>
                  </div>
                  <div class="tooltip-row">
                    <span>Gift Date:</span>
                    <strong>${this.formatDate(meta.gift_date)}</strong>
                  </div>
                  <div class="tooltip-row">
                    <span>Years Elapsed:</span>
                    <strong>${meta.years_elapsed} years</strong>
                  </div>
                  <div class="tooltip-row">
                    <span>Years Remaining:</span>
                    <strong>${meta.years_remaining} years</strong>
                  </div>
                  <div class="tooltip-row">
                    <span>Taper Relief:</span>
                    <strong>${meta.taper_relief}%</strong>
                  </div>
                  <div class="tooltip-row highlight">
                    <span>Status:</span>
                    <strong>${meta.status}</strong>
                  </div>
                </div>
              </div>
            `;
          },
        },
        legend: {
          show: false,
        },
        grid: {
          xaxis: {
            lines: {
              show: true,
            },
          },
        },
      };
    },
  },

  methods: {
    calculateYearsElapsed(giftDate, currentDate) {
      const diffTime = Math.abs(currentDate - giftDate);
      const diffYears = diffTime / (1000 * 60 * 60 * 24 * 365.25);
      return diffYears;
    },

    getGiftColour(yearsElapsed, giftType) {
      // Exempt gifts (spouse, charity) are always green - immediately Inheritance Tax-free
      if (giftType === 'exempt') {
        return SUCCESS_COLORS[500]; // Green - exempt
      }

      if (yearsElapsed >= 7) {
        return SUCCESS_COLORS[500]; // Green - survived 7 years
      } else if (yearsElapsed >= 3) {
        return WARNING_COLORS[500]; // Amber - taper relief applies
      } else {
        return ERROR_COLORS[500]; // Red - full rate
      }
    },

    calculateTaperRelief(yearsElapsed) {
      if (yearsElapsed < 3) return 0;
      if (yearsElapsed < 4) return 20;
      if (yearsElapsed < 5) return 40;
      if (yearsElapsed < 6) return 60;
      if (yearsElapsed < 7) return 80;
      return 100;
    },

    getGiftStatus(yearsElapsed, giftType) {
      // Exempt gifts (spouse, charity) are immediately Inheritance Tax-exempt
      if (giftType === 'exempt') {
        return 'Exempt Gift - Inheritance Tax-Free';
      }

      if (yearsElapsed >= 7) {
        return 'Inheritance Tax-Exempt (7 years survived)';
      } else if (yearsElapsed >= 3) {
        return `Taper Relief (${this.calculateTaperRelief(yearsElapsed)}%)`;
      } else {
        return 'Potentially Taxable';
      }
    },

    formatDate(dateString) {
      if (!dateString) return 'Unknown';
      const date = new Date(dateString);
      return date.toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
      });
    },

    formatGiftType(type) {
      const types = {
        pet: 'Potentially Exempt Transfer',
        clt: 'Chargeable Lifetime Transfer',
        exempt: 'Exempt (Spouse/Charity)',
        small_gift: 'Small Gift Exemption',
        annual_exemption: 'Annual Exemption',
      };
      return types[type] || type;
    },
  },
};
</script>

<style scoped>
.gifting-timeline-chart {
  background: white;
  border-radius: 8px;
  padding: 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.chart-header {
  text-align: center;
  margin-bottom: 24px;
}

.chart-header h3 {
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

.chart-container {
  margin: 20px 0;
}

.empty-state {
  text-align: center;
  padding: 60px 20px;
  @apply text-horizon-400;
}

.empty-state i {
  @apply text-savannah-300;
  margin-bottom: 16px;
}

.empty-state p {
  margin: 8px 0;
  font-size: 16px;
  @apply text-neutral-500;
}

.legend {
  display: flex;
  flex-wrap: wrap;
  gap: 20px;
  justify-content: center;
  margin: 24px 0;
  padding: 16px;
  @apply bg-eggshell-500;
  border-radius: 6px;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  @apply text-neutral-500;
}

.legend-colour {
  width: 16px;
  height: 16px;
  border-radius: 3px;
  display: inline-block;
}

.taper-relief-info {
  margin-top: 32px;
  padding-top: 24px;
  @apply border-t border-light-gray;
}

.taper-relief-info h4 {
  font-size: 16px;
  font-weight: 600;
  @apply text-horizon-500;
  margin: 0 0 12px 0;
}

.relief-note {
  @apply text-sm text-neutral-500 bg-spring-50 border border-spring-200 p-3 mb-4 rounded leading-relaxed;
}

.relief-note strong {
  @apply text-spring-800;
}

.relief-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 14px;
}

.relief-table thead {
  @apply bg-savannah-100;
}

.relief-table th {
  padding: 12px;
  text-align: left;
  font-weight: 600;
  @apply text-neutral-500;
  @apply border-b-2 border-light-gray;
}

.relief-table td {
  padding: 12px;
  @apply text-neutral-500;
  @apply border-b border-light-gray;
}

.relief-table tbody tr:hover {
  @apply bg-eggshell-500;
}

.relief-table tbody tr.highlight {
  @apply bg-spring-100;
  font-weight: 600;
}

.relief-table tbody tr.highlight td {
  @apply text-spring-800;
}
</style>

<style>
/* Custom tooltip styles */
.custom-tooltip {
  background: white;
  border-radius: 6px;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  padding: 0;
  min-width: 250px;
}

.tooltip-header {
  @apply bg-savannah-100;
  padding: 12px 16px;
  @apply border-b border-light-gray;
  border-radius: 6px 6px 0 0;
  font-size: 14px;
  @apply text-horizon-500;
}

.tooltip-body {
  padding: 12px 16px;
}

.tooltip-row {
  display: flex;
  justify-content: space-between;
  padding: 6px 0;
  font-size: 13px;
}

.tooltip-row span {
  @apply text-neutral-500;
}

.tooltip-row strong {
  @apply text-horizon-500;
}

.tooltip-row.highlight {
  margin-top: 8px;
  padding-top: 8px;
  @apply border-t border-light-gray;
}
</style>
