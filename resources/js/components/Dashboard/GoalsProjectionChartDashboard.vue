<template>
  <div class="goals-projection-chart-dashboard">
    <!-- Loading State -->
    <div v-if="loading" class="flex justify-center items-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
    </div>

    <!-- Chart -->
    <div v-else-if="hasData" class="relative">
      <div class="chart-wrapper" ref="chartWrapper">
        <apexchart
          ref="chart"
          type="bar"
          :options="chartOptions"
          :series="chartSeries"
          height="340"
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
          :title="`${marker.event.name}: ${formatCurrency(marker.event.amount)}`"
        >
          <EventIcon
            :event="marker.event"
            :size="20"
            :is-completed="marker.isCompleted"
          />
        </div>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else class="text-center py-8 text-gray-500">
      <p class="text-sm">Add a date of birth in your profile to see projections</p>
    </div>
  </div>
</template>

<script>
import { mapState, mapActions } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';
import { PRIMARY_COLORS, BORDER_COLORS } from '@/constants/designSystem';
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

    chartOptions() {
      return {
        chart: {
          id: 'goals-projection-dashboard',
          type: 'bar',
          toolbar: { show: false },
          zoom: { enabled: false },
          fontFamily: 'Inter, system-ui, sans-serif',
          animations: {
            enabled: true,
            easing: 'easeinout',
            speed: 500,
          },
          events: {
            updated: () => this.updateEventMarkers(),
          },
        },
        colors: ['#A8B8D8'], // Muted periwinkle blue
        fill: { type: 'solid' },
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
            style: { fontSize: '11px', fontWeight: 500, color: '#6B7280' },
          },
          labels: {
            style: { fontSize: '10px', colors: '#6B7280' },
          },
          axisBorder: { show: false },
          axisTicks: { show: false },
          tickAmount: 8,
        },
        yaxis: {
          title: {
            text: 'Net Worth',
            style: { fontSize: '11px', fontWeight: 500, color: '#6B7280' },
          },
          labels: {
            formatter: (val) => this.formatCompact(val),
            style: { fontSize: '10px', colors: '#6B7280' },
          },
        },
        tooltip: {
          enabled: true,
          x: {
            formatter: (val) => `Age ${Math.round(val)}`,
          },
          y: {
            formatter: (val) => this.formatCurrency(val),
          },
        },
        legend: { show: false },
        grid: {
          borderColor: BORDER_COLORS.default,
          strokeDashArray: 4,
          padding: {
            top: 60, // Space for event icons
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
  },

  watch: {
    projection: {
      handler() {
        if (!this.isComponentMounted) return;
        this.$nextTick(() => {
          if (this.isComponentMounted) {
            setTimeout(() => {
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
</style>
