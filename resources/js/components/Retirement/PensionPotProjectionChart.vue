<template>
  <div class="pension-pot-chart" style="position: relative;">
    <apexchart
      v-if="isReady && series.length > 0"
      ref="chart"
      :key="chartKey"
      type="area"
      :options="chartOptions"
      :series="series"
      height="400"
    />
    <div v-else class="chart-placeholder">
      <p>No projection data available</p>
    </div>
    <p v-if="riskMessage" class="chart-footer">{{ riskMessage }}</p>
    <!-- Life event tooltip -->
    <div
      v-if="eventTooltip.show"
      class="absolute z-50 px-3 py-2 rounded-lg shadow-lg text-xs whitespace-nowrap pointer-events-none"
      :class="eventTooltip.isIncome ? 'bg-spring-600 text-white' : 'bg-raspberry-600 text-white'"
      :style="{ left: eventTooltip.x + 'px', top: eventTooltip.y + 'px' }"
    >
      {{ eventTooltip.text }}
    </div>
  </div>
</template>

<script>
import VueApexCharts from 'vue3-apexcharts';
import { currencyMixin } from '@/mixins/currencyMixin';
import { PRIMARY_COLORS, SUCCESS_COLORS, ERROR_COLORS, BORDER_COLORS, CHART_DEFAULTS, TEXT_COLORS } from '@/constants/designSystem';
import { LIFE_EVENT_ICONS } from '@/constants/eventIcons';
import { EVENT_ICON_SVGS } from '@/constants/eventIconSvgs';

export default {
  name: 'PensionPotProjectionChart',

  mixins: [currencyMixin],

  components: {
    apexchart: VueApexCharts,
  },

  props: {
    data: {
      type: Object,
      required: true,
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
    lifeEvents: {
      type: Array,
      default: () => [],
    },
  },

  data() {
    return {
      isReady: false,
      renderTimeout: null,
      eventTooltip: { show: false, text: '', x: 0, y: 0, isIncome: false },
    };
  },

  computed: {
    years() {
      if (!this.data?.year_by_year) return [];
      return this.data.year_by_year.map(y => y.year);
    },

    chartKey() {
      const yearByYear = this.data?.year_by_year;
      return `pension-pot-${yearByYear?.length || 0}-${Math.round(yearByYear?.[yearByYear?.length - 1]?.percentile_10 || 0)}`;
    },

    series() {
      if (!this.data?.year_by_year || this.data.year_by_year.length === 0) return [];

      return [
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
        {
          name: '75% Probability',
          data: this.data.year_by_year.map(y => y.percentile_25),
        },
      ];
    },

    maxChartValue() {
      if (!this.data?.year_by_year || this.data.year_by_year.length === 0) return 0;
      return Math.max(...this.data.year_by_year.map(y => y.percentile_25));
    },

    riskMessage() {
      if (!this.riskSource || !this.expectedReturn || this.riskSource === 'default') {
        return null;
      }

      const levelDisplay = this.formatRiskLevel(this.riskLevel);
      const formattedReturn = Number(this.expectedReturn).toFixed(2);
      return `Using ${levelDisplay} risk profile (${formattedReturn}% expected return)`;
    },

    filteredLifeEvents() {
      if (!this.lifeEvents || this.lifeEvents.length === 0 || !this.years.length) return [];
      const currentYear = this.years[0];
      const lastYear = this.years[this.years.length - 1];
      return this.lifeEvents.filter(e => {
        const eventYear = new Date(e.expected_date).getFullYear();
        return eventYear >= currentYear && eventYear <= lastYear;
      });
    },

    lifeEventAnnotations() {
      return this.filteredLifeEvents.map(e => {
        const eventYear = new Date(e.expected_date).getFullYear();
        const isIncome = e.impact_type === 'income';
        return {
          x: eventYear,
          borderColor: isIncome ? SUCCESS_COLORS[500] : ERROR_COLORS[500],
          strokeDashArray: 4,
        };
      });
    },

    lifeEventPointAnnotations() {
      if (!this.filteredLifeEvents || this.filteredLifeEvents.length === 0 || !this.maxChartValue) return [];
      const self = this;
      const iconY = this.maxChartValue * 0.93;

      return this.filteredLifeEvents.map(e => {
        const eventYear = new Date(e.expected_date).getFullYear();
        const isIncome = e.impact_type === 'income';
        const color = isIncome ? SUCCESS_COLORS[500] : ERROR_COLORS[500];
        const svgPath = this.getEventSvgPath(e);
        const svgString = `<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="${color}" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="${svgPath}"/></svg>`;

        return {
          x: eventYear,
          y: iconY,
          seriesIndex: 3,
          marker: { size: 0 },
          image: {
            path: 'data:image/svg+xml;base64,' + btoa(svgString),
            width: 22,
            height: 22,
            offsetX: 0,
            offsetY: 0,
          },
          mouseEnter(event) {
            const info = self.lifeEventTooltipMap[eventYear];
            if (info) {
              const target = event.target || event.srcElement;
              const rect = target.getBoundingClientRect();
              const chartRect = self.$refs.chart?.$el?.getBoundingClientRect();
              if (chartRect) {
                self.eventTooltip = {
                  show: true,
                  text: info.text,
                  x: rect.left - chartRect.left + rect.width / 2,
                  y: rect.top - chartRect.top - 30,
                  isIncome: info.isIncome,
                };
              }
            }
          },
          mouseLeave() {
            self.eventTooltip.show = false;
          },
        };
      });
    },

    lifeEventTooltipMap() {
      if (!this.lifeEvents || this.lifeEvents.length === 0) return {};
      const map = {};
      this.lifeEvents.forEach(e => {
        const year = new Date(e.expected_date).getFullYear();
        const isIncome = e.impact_type === 'income';
        map[year] = {
          text: `${e.event_name} (${isIncome ? '+' : '-'}${this.formatCurrencyCompact(e.amount)})`,
          isIncome,
        };
      });
      return map;
    },

    chartOptions() {
      const self = this;
      return {
        chart: {
          ...CHART_DEFAULTS.chart,
          type: 'area',
          stacked: false,
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
          animations: {
            enabled: true,
            easing: 'easeinout',
            speed: 800,
          },
          events: {
            mounted() {
              setTimeout(() => self.clipAnnotationLines(), 150);
            },
            updated() {
              self.$nextTick(() => self.clipAnnotationLines());
            },
            mouseMove(event) {
              self.handleChartMouseMove(event);
            },
            mouseLeave() {
              self.eventTooltip.show = false;
            },
          },
        },
        colors: [PRIMARY_COLORS[900], PRIMARY_COLORS[600], SUCCESS_COLORS[500], SUCCESS_COLORS[100]],
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
        annotations: {
          xaxis: this.lifeEventAnnotations,
          points: this.lifeEventPointAnnotations,
        },
        xaxis: {
          categories: this.years,
          title: {
            text: 'Year',
            style: { fontWeight: 600, fontSize: '12px' },
          },
          labels: {
            style: { fontSize: '11px' },
            rotate: -45,
            rotateAlways: this.years.length > 15,
          },
          tickAmount: Math.min(this.years.length, 10),
        },
        yaxis: {
          title: {
            text: 'Pension Pot Value',
            style: { fontWeight: 600, fontSize: '12px' },
          },
          labels: {
            formatter: (val) => this.formatCurrencyCompact(val),
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
          position: 'top',
          horizontalAlign: 'center',
          fontSize: '12px',
          markers: { width: 12, height: 12, radius: 2 },
        },
        grid: {
          borderColor: BORDER_COLORS.default,
          strokeDashArray: 4,
        },
        dataLabels: { enabled: false },
      };
    },
  },

  mounted() {
    this.$nextTick(() => {
      this.renderTimeout = setTimeout(() => {
        this.isReady = true;
      }, 100);
    });
  },

  beforeUnmount() {
    if (this.renderTimeout) clearTimeout(this.renderTimeout);
  },

  methods: {
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

    clipAnnotationLines() {
      const chartEl = this.$refs.chart?.$el;
      if (!chartEl) return;

      const lines = chartEl.querySelectorAll('.apexcharts-xaxis-annotations line');
      const images = chartEl.querySelectorAll('.apexcharts-point-annotations image');
      if (!lines.length || !images.length) return;

      lines.forEach(line => {
        const lineX = parseFloat(line.getAttribute('x1'));
        let closestImage = null;
        let minDist = Infinity;

        images.forEach(img => {
          const imgX = parseFloat(img.getAttribute('x')) + parseFloat(img.getAttribute('width') || 22) / 2;
          const dist = Math.abs(imgX - lineX);
          if (dist < minDist) {
            minDist = dist;
            closestImage = img;
          }
        });

        if (closestImage && minDist < 50) {
          const imageY = parseFloat(closestImage.getAttribute('y'));
          const imageHeight = parseFloat(closestImage.getAttribute('height') || 22);
          line.setAttribute('y1', imageY + imageHeight + 2);
        }
      });
    },

    getEventSvgPath(event) {
      const iconConfig = LIFE_EVENT_ICONS[event.event_type] ||
        (event.impact_type === 'income' ? LIFE_EVENT_ICONS.custom_income : LIFE_EVENT_ICONS.custom_expense);
      const svgData = EVENT_ICON_SVGS[iconConfig?.icon] || EVENT_ICON_SVGS.FlagIcon;
      return svgData.d;
    },

    handleChartMouseMove(event) {
      const chartEl = this.$refs.chart?.$el;
      if (!chartEl) return;

      const imageEls = chartEl.querySelectorAll('.apexcharts-point-annotations image');
      if (!imageEls.length) return;

      let found = false;
      const chartBbox = chartEl.getBoundingClientRect();

      imageEls.forEach((el, index) => {
        const bbox = el.getBoundingClientRect();
        if (bbox.width === 0 || bbox.height === 0) return;

        const mouseX = event.clientX;
        const mouseY = event.clientY;

        if (mouseX >= bbox.left - 12 && mouseX <= bbox.right + 12 &&
            mouseY >= bbox.top - 12 && mouseY <= bbox.bottom + 12) {
          const filteredEvent = this.filteredLifeEvents[index];
          if (filteredEvent) {
            const eventYear = new Date(filteredEvent.expected_date).getFullYear();
            const info = this.lifeEventTooltipMap[eventYear];
            if (info) {
              this.eventTooltip = {
                show: true,
                text: info.text,
                x: bbox.left - chartBbox.left + bbox.width / 2,
                y: bbox.top - chartBbox.top - 30,
                isIncome: info.isIncome,
              };
              found = true;
            }
          }
        }
      });

      if (!found) {
        this.eventTooltip.show = false;
      }
    },
  },
};
</script>

<style scoped>
.pension-pot-chart {
  width: 100%;
}

.chart-placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 400px;
  @apply bg-savannah-100;
  border-radius: 8px;
  @apply border border-dashed border-horizon-300;
}

.chart-placeholder p {
  @apply text-neutral-500;
  font-size: 14px;
  margin: 0;
}

.chart-footer {
  text-align: center;
  font-size: 12px;
  @apply text-neutral-500;
  margin: 8px 0 0 0;
  font-style: italic;
}
</style>
