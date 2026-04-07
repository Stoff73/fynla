<template>
  <div class="goals-projection-chart-dashboard">
    <!-- Loading State -->
    <div v-if="loading" class="flex justify-center items-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-raspberry-500"></div>
    </div>

    <!-- Chart -->
    <div v-else-if="hasData && isComponentMounted" class="relative">
      <div class="chart-wrapper" ref="chartWrapper">
        <apexchart
          ref="chart"
          :key="chartKey"
          type="bar"
          :options="chartOptions"
          :series="chartSeries"
          height="300"
          @updated="updateEventMarkers"
          @mounted="updateEventMarkers"
        ></apexchart>

        <!-- Event icons floating above bars -->
        <div
          v-for="marker in eventMarkers"
          :key="`marker-${marker.event.type}-${marker.event.id}`"
          class="event-marker absolute transform -translate-x-1/2 cursor-pointer z-10 transition-transform hover:scale-110"
          :style="{
            left: `${marker.x}px`,
            top: `${marker.y}px`,
          }"
          @mouseenter="showEventTooltip($event, marker.event)"
          @mouseleave="hideEventTooltip"
        >
          <EventIcon
            :event="marker.event"
            :size="20"
            :is-completed="marker.isCompleted"
          />
        </div>
      </div>

      <!-- Event tooltip -->
      <div
        v-if="activeTooltip"
        class="fixed z-50 bg-horizon-500 text-white text-sm rounded-lg shadow-lg px-3 py-2 pointer-events-none"
        :style="{
          left: `${tooltipPosition.x}px`,
          top: `${tooltipPosition.y}px`,
          transform: 'translate(-50%, -100%) translateY(-8px)',
        }"
      >
        <div class="text-xs text-horizon-400 uppercase tracking-wide mb-1">
          {{ activeTooltip.type === 'goal' ? 'Goal' : 'Life Event' }}
        </div>
        <div class="font-semibold">{{ activeTooltip.name }}</div>
        <div class="text-horizon-300">
          Age {{ activeTooltip.age }} · {{ formatCurrency(activeTooltip.amount) }}
        </div>
        <div class="text-xs text-horizon-400 capitalize">
          {{ activeTooltip.impact === 'income' ? 'Income' : 'Expense' }} · {{ activeTooltip.certainty || 'Planned' }}
        </div>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else class="text-center py-8 text-neutral-500">
      <p class="text-sm">Add a date of birth in your profile to see projections</p>
    </div>
  </div>
</template>

<script>
import { mapState, mapActions } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';
import { PRIMARY_COLORS, SECONDARY_COLORS, BORDER_COLORS, SUCCESS_COLORS, ERROR_COLORS, TEXT_COLORS, CHART_DEFAULTS } from '@/constants/designSystem';
import EventIcon from '@/components/Goals/EventIcon.vue';

export default {
  name: 'GoalsProjectionChartDashboard',
  mixins: [currencyMixin],

  components: {
    EventIcon,
  },

  data() {
    return {
      eventMarkers: [],
      isComponentMounted: false,
      activeTooltip: null,
      tooltipPosition: { x: 0, y: 0 },
      markerTimeout: null,
    };
  },

  computed: {
    ...mapState('goals', ['projectionData', 'projectionLoading']),

    loading() {
      return this.projectionLoading;
    },

    hasData() {
      return this.projectionData?.yearly_data?.length > 0;
    },

    projection() {
      return this.projectionData;
    },

    chartKey() {
      const data = this.projectionData?.yearly_data;
      return `dashboard-${data?.length || 0}-${Math.round(data?.[data?.length - 1]?.net_worth || 0)}`;
    },

    chartSeries() {
      if (!this.hasData) return [];

      const data = this.projectionData.yearly_data;
      return [{
        name: 'Net Worth',
        data: data.map(d => ({ x: d.age, y: d.net_worth })),
      }];
    },

    // Map of age -> net worth for icon positioning
    netWorthByAge() {
      if (!this.projection?.yearly_data) return {};
      const map = {};
      this.projection.yearly_data.forEach(d => {
        map[d.age] = d.net_worth;
      });
      return map;
    },

    // Calculate Y-axis max to minimize white space above bars
    yAxisMax() {
      if (!this.hasData) return undefined;
      const maxValue = Math.max(...this.projectionData.yearly_data.map(d => d.net_worth));
      // Add 15% headroom for event icons
      return Math.ceil(maxValue * 1.15);
    },

    chartOptions() {
      return {
        chart: {
          ...CHART_DEFAULTS.chart,
          id: 'goals-projection-dashboard',
          type: 'bar',
          animations: {
            enabled: true,
            easing: 'easeinout',
            speed: 500,
          },
          events: {
            updated: () => this.updateEventMarkers(),
          },
        },
        colors: [SECONDARY_COLORS[500]],
        fill: { type: 'solid' },
        states: {
          hover: {
            filter: {
              type: 'none',
            },
          },
          active: {
            filter: {
              type: 'none',
            },
          },
        },
        dataLabels: { enabled: false },
        stroke: { width: 0 },
        plotOptions: {
          bar: {
            horizontal: false,
            columnWidth: '50%',
            borderRadius: 3,
            borderRadiusApplication: 'end',
          },
        },
        xaxis: {
          type: 'category',
          title: {
            text: 'Age',
            style: { fontSize: '11px', fontWeight: 500, color: TEXT_COLORS.muted },
          },
          labels: {
            style: { fontSize: '10px', colors: TEXT_COLORS.muted },
          },
          axisBorder: { show: false },
          axisTicks: { show: false },
          tickAmount: 8,
        },
        yaxis: {
          title: {
            text: 'Net Worth',
            style: { fontSize: '11px', fontWeight: 500, color: TEXT_COLORS.muted },
          },
          labels: {
            formatter: (val) => this.formatCompact(val),
            style: { fontSize: '10px', colors: TEXT_COLORS.muted },
          },
          min: 0,
          max: this.yAxisMax,
          forceNiceScale: false,
        },
        tooltip: {
          enabled: true,
          shared: true,
          intersect: false,
          x: {
            formatter: (val) => `Age ${Math.round(val)}`,
          },
          custom: ({ series, seriesIndex, dataPointIndex, w }) => {
            return this.buildCustomTooltip(series, seriesIndex, dataPointIndex, w);
          },
        },
        legend: { show: false },
        grid: {
          borderColor: BORDER_COLORS.default,
          strokeDashArray: 4,
          padding: {
            top: 80, // Space for event icons
          },
        },
        annotations: this.retirementAnnotation,
      };
    },

    retirementAnnotation() {
      if (!this.projection?.retirement_age) return {};

      return {
        xaxis: [
          {
            x: this.projection.retirement_age,
            borderColor: PRIMARY_COLORS[600],
            strokeDashArray: 5,
            label: {
              borderColor: PRIMARY_COLORS[600],
              style: {
                color: '#fff',
                background: PRIMARY_COLORS[600],
                fontSize: '10px',
                fontWeight: 500,
              },
              text: 'Retire',
              position: 'top',
            },
          },
        ],
      };
    },
  },

  mounted() {
    this.isComponentMounted = true;
    this.fetchProjection();
  },

  beforeUnmount() {
    this.isComponentMounted = false;
    if (this.markerTimeout) clearTimeout(this.markerTimeout);
  },

  methods: {
    ...mapActions('goals', ['fetchProjection']),

    formatCompact(value) {
      if (value >= 1000000) {
        return `£${(value / 1000000).toFixed(1)}M`;
      }
      if (value >= 1000) {
        return `£${Math.round(value / 1000)}K`;
      }
      return `£${Math.round(value)}`;
    },

    updateEventMarkers() {
      if (!this.isComponentMounted || !this.$refs.chart || !this.projection?.events || !this.projection?.yearly_data) {
        this.eventMarkers = [];
        return;
      }

      const chart = this.$refs.chart;
      const apexChart = chart.chart;

      if (!apexChart) {
        this.eventMarkers = [];
        return;
      }

      const w = apexChart.w;
      const globals = w.globals;

      const gridWidth = globals.gridWidth;
      const gridHeight = globals.gridHeight;
      const translateX = globals.translateX;
      const translateY = globals.translateY;

      const xMin = globals.minX;
      const xMax = globals.maxX;
      const yMin = globals.minY;
      const yMax = globals.maxY;

      const iconSize = 22;
      const iconGap = 6;
      const floatGap = 16;

      const currentAge = this.projection?.current_age || 0;

      // Group events by age
      const eventsByAge = {};
      this.projection.events.forEach(event => {
        const ageKey = String(Math.round(Number(event.age)));
        if (!eventsByAge[ageKey]) {
          eventsByAge[ageKey] = [];
        }
        eventsByAge[ageKey].push(event);
      });

      const markers = [];

      Object.keys(eventsByAge).forEach(ageKey => {
        const ageNum = parseInt(ageKey, 10);
        const eventsAtAge = eventsByAge[ageKey];
        const barTopValue = this.netWorthByAge[ageNum];

        if (barTopValue === undefined) return;

        const xRatio = (ageNum - xMin) / (xMax - xMin);
        const x = translateX + (xRatio * gridWidth);

        const yRatio = (barTopValue - yMin) / (yMax - yMin);
        const barTopY = translateY + gridHeight - (yRatio * gridHeight);

        eventsAtAge.forEach((event, stackIndex) => {
          const stackOffset = stackIndex * (iconSize + iconGap);
          const y = barTopY - floatGap - iconSize - stackOffset;

          const isCompleted = event.is_completed || ageNum < currentAge;

          markers.push({
            event,
            x,
            y: Math.max(10, y),
            isCompleted,
          });
        });
      });

      this.eventMarkers = markers;
    },

    showEventTooltip(domEvent, event) {
      const rect = domEvent.target.getBoundingClientRect();
      this.activeTooltip = event;
      this.tooltipPosition = {
        x: rect.left + rect.width / 2,
        y: rect.top,
      };
    },

    hideEventTooltip() {
      this.activeTooltip = null;
    },

    buildCustomTooltip(series, seriesIndex, dataPointIndex, w) {
      const seriesData = w.config.series[0]?.data[dataPointIndex];
      const age = seriesData?.x || w.globals.labels[dataPointIndex];
      const yearData = this.projection?.yearly_data?.find(d => d.age === age);
      const allEvents = this.projection?.events || [];
      const eventsAtAge = allEvents.filter(e => e.age === age);

      let tooltipHtml = `
        <div class="apexcharts-tooltip-custom" style="padding: 12px; font-family: Inter, sans-serif; min-width: 200px;">
          <div style="font-weight: 600; margin-bottom: 8px; color: ${SECONDARY_COLORS[700]}; font-size: 14px;">Age ${age}</div>
      `;

      if (yearData) {
        tooltipHtml += `
          <div style="display: flex; align-items: center; margin-bottom: 4px;">
            <span style="width: 10px; height: 10px; border-radius: 50%; background: ${PRIMARY_COLORS[600]}; margin-right: 8px;"></span>
            <span style="color: ${TEXT_COLORS.muted};">Net Worth:</span>
            <span style="font-weight: 600; margin-left: auto; color: ${SECONDARY_COLORS[700]};">${this.formatCurrency(yearData.net_worth)}</span>
          </div>
        `;
      }

      if (eventsAtAge.length > 0) {
        const goals = eventsAtAge.filter(e => e.type === 'goal');
        const lifeEvents = eventsAtAge.filter(e => e.type === 'life_event');

        tooltipHtml += `<div style="border-top: 1px solid ${BORDER_COLORS.default}; margin-top: 8px; padding-top: 8px;">`;

        if (goals.length > 0) {
          tooltipHtml += `<div style="font-size: 11px; color: ${TEXT_COLORS.muted}; margin-bottom: 4px; font-weight: 600;">Goals:</div>`;
          goals.forEach(event => {
            const sign = event.impact === 'income' ? '+' : '-';
            const color = event.impact === 'income' ? SUCCESS_COLORS[600] : ERROR_COLORS[600];
            tooltipHtml += `
              <div style="display: flex; align-items: center; margin-bottom: 4px;">
                <span style="width: 8px; height: 8px; border-radius: 50%; background: ${event.color}; margin-right: 6px;"></span>
                <span style="color: ${SECONDARY_COLORS[500]}; font-size: 12px;">${event.name}</span>
                <span style="font-weight: 600; margin-left: auto; color: ${color}; font-size: 12px;">${sign}${this.formatCurrency(event.amount)}</span>
              </div>
            `;
          });
        }

        if (lifeEvents.length > 0) {
          tooltipHtml += `<div style="font-size: 11px; color: ${TEXT_COLORS.muted}; margin-bottom: 4px; margin-top: ${goals.length > 0 ? '8px' : '0'}; font-weight: 600;">Life Events:</div>`;
          lifeEvents.forEach(event => {
            const sign = event.impact === 'income' ? '+' : '-';
            const color = event.impact === 'income' ? SUCCESS_COLORS[600] : ERROR_COLORS[600];
            tooltipHtml += `
              <div style="display: flex; align-items: center; margin-bottom: 4px;">
                <span style="width: 8px; height: 8px; border-radius: 50%; background: ${event.color}; margin-right: 6px;"></span>
                <span style="color: ${SECONDARY_COLORS[500]}; font-size: 12px;">${event.name}</span>
                <span style="font-weight: 600; margin-left: auto; color: ${color}; font-size: 12px;">${sign}${this.formatCurrency(event.amount)}</span>
              </div>
            `;
          });
        }

        tooltipHtml += `</div>`;
      }

      tooltipHtml += `</div>`;
      return tooltipHtml;
    },
  },

  watch: {
    projection: {
      handler() {
        if (!this.isComponentMounted) return;
        this.$nextTick(() => {
          if (this.isComponentMounted) {
            if (this.markerTimeout) clearTimeout(this.markerTimeout);
            this.markerTimeout = setTimeout(() => {
              if (this.isComponentMounted) {
                this.updateEventMarkers();
              }
            }, 100);
          }
        });
      },
      deep: true,
    },
  },
};
</script>

<style scoped>
.chart-wrapper {
  position: relative;
}

.event-marker {
  pointer-events: auto;
}

/* Custom tooltip styling */
:deep(.apexcharts-tooltip) {
  border: none !important;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06) !important;
  border-radius: 8px !important;
}

:deep(.apexcharts-tooltip-custom) {
  min-width: 180px;
}

/* Light blue hover on bar chart bars */
:deep(.apexcharts-bar-area:hover) {
  fill: theme('colors.light-blue.100') !important;
}
</style>
