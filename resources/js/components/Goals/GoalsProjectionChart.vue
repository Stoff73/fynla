<template>
  <div class="goals-projection-chart">
    <!-- Header with controls -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
      <h3 class="text-lg font-semibold text-horizon-500">Financial Projection</h3>
      <div class="flex flex-wrap items-center gap-3">
        <!-- View selector dropdown -->
        <select
          v-model="chartView"
          class="px-3 py-2 text-sm border border-horizon-300 rounded-md shadow-sm focus:ring-violet-500 focus:border-raspberry-500"
        >
          <option value="net_worth">Net Worth</option>
          <option value="cash_flow">Cash Flow</option>
          <option value="asset_breakdown">Asset Breakdown</option>
        </select>

        <!-- Household toggle (if spouse permission) -->
        <ViewToggle
          v-if="hasSpousePermission"
          v-model="viewMode"
          :options="['Individual', 'Household']"
          @change="onViewModeChange"
        />
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="flex justify-center items-center py-16">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-raspberry-600"></div>
    </div>

    <!-- Content -->
    <div v-else-if="projection && isComponentMounted">
      <!-- Summary cards -->
      <ProjectionSummaryCards
        :projection="projection"
        :view="chartView"
        class="mb-6"
      />

      <!-- Event icons legend -->
      <EventIconLegend
        v-if="hasEvents"
        :events="projection.events"
        class="mb-4"
      />

      <!-- Chart container -->
      <div class="bg-white border border-light-gray rounded-lg p-4 sm:p-6">
        <div class="chart-wrapper relative" ref="chartWrapper">
          <!-- ApexChart -->
          <apexchart
            :key="`chart-${computedChartType}-${chartView}`"
            ref="chart"
            :type="computedChartType"
            :options="chartOptions"
            :series="chartSeries"
            height="400"
            @updated="onChartUpdated"
            @mounted="onChartMounted"
          ></apexchart>

          <!-- Event icons floating above bars (no connector lines) -->
          <div
            v-for="marker in eventMarkers"
            :key="`marker-${marker.event.type}-${marker.event.id}`"
            class="event-marker absolute transform -translate-x-1/2 cursor-pointer z-10 transition-transform hover:scale-110"
            :style="{
              left: `${marker.x}px`,
              top: `${marker.y}px`,
            }"
            @click="onEventClick(marker.event)"
            @mouseenter="showEventTooltip(marker.event, $event)"
            @mouseleave="hideEventTooltip"
            :title="`${marker.event.name}: ${formatCurrency(marker.event.amount)}`"
          >
            <EventIcon
              :event="marker.event"
              :size="24"
              :is-completed="marker.isCompleted"
            />
          </div>
        </div>
      </div>

      <!-- Event tooltip -->
      <div
        v-if="activeTooltip"
        class="fixed z-50 bg-horizon-600 text-white text-sm rounded-lg shadow-lg px-3 py-2 pointer-events-none"
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

      <!-- Assumptions disclosure -->
      <AssumptionsDisclosure
        :assumptions="projection.assumptions"
        class="mt-6"
      />
    </div>

    <!-- Empty state -->
    <div v-else class="text-center py-12 text-neutral-500">
      <p>No projection data available.</p>
      <p class="text-sm mt-2">Add a date of birth in your profile to generate projections.</p>
    </div>
  </div>
</template>

<script>
import { mapState, mapActions } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';
import {
  PRIMARY_COLORS,
  SECONDARY_COLORS,
  SUCCESS_COLORS,
  ERROR_COLORS,
  BORDER_COLORS,
  ASSET_COLORS,
  TEXT_COLORS,
  CHART_DEFAULTS,
} from '@/constants/designSystem';
import ViewToggle from '../Shared/ViewToggle.vue';
import ProjectionSummaryCards from './ProjectionSummaryCards.vue';
import EventIconLegend from './EventIconLegend.vue';
import AssumptionsDisclosure from './AssumptionsDisclosure.vue';
import EventIcon from './EventIcon.vue';

export default {
  name: 'GoalsProjectionChart',

  emits: ['event-click'],

  mixins: [currencyMixin],

  components: {
    ViewToggle,
    ProjectionSummaryCards,
    EventIconLegend,
    AssumptionsDisclosure,
    EventIcon,
  },

  data() {
    return {
      chartView: 'net_worth',
      chartType: 'bar',
      viewMode: 'Individual',
      chartInstance: null,
      eventMarkers: [],
      activeTooltip: null,
      tooltipPosition: { x: 0, y: 0 },
      isComponentMounted: false,
      markerTimeout: null,
    };
  },

  computed: {
    ...mapState('goals', ['projectionData', 'projectionLoading']),
    ...mapState('auth', ['user']),

    projection() {
      return this.projectionData;
    },

    loading() {
      return this.projectionLoading;
    },

    hasSpousePermission() {
      return this.user?.has_accepted_spouse_permission ?? false;
    },

    hasEvents() {
      return this.projection?.events?.length > 0;
    },

    computedChartType() {
      if (this.chartView === 'asset_breakdown') {
        return this.chartType === 'line' ? 'area' : 'bar';
      }
      if (this.chartView === 'cash_flow') {
        return 'bar';
      }
      return this.chartType === 'line' ? 'area' : 'bar';
    },

    // Build a map of age -> net worth for quick lookup
    netWorthByAge() {
      if (!this.projection?.yearly_data) return {};
      const map = {};
      this.projection.yearly_data.forEach(d => {
        map[d.age] = d.net_worth;
      });
      return map;
    },

    // Build a map of age -> bar top value based on current chart view
    // This is used for icon positioning
    barTopValueByAge() {
      if (!this.projection?.yearly_data) return {};
      const map = {};
      this.projection.yearly_data.forEach(d => {
        if (this.chartView === 'net_worth') {
          map[d.age] = d.net_worth;
        } else if (this.chartView === 'cash_flow') {
          // For cash flow, use the max of income and expenditure
          map[d.age] = Math.max(d.income || 0, d.expenditure || 0);
        } else if (this.chartView === 'asset_breakdown') {
          // For stacked assets, use the total
          map[d.age] = (d.assets?.pensions || 0) +
                       (d.assets?.property || 0) +
                       (d.assets?.investments || 0) +
                       (d.assets?.cash || 0);
        } else {
          map[d.age] = d.net_worth;
        }
      });
      return map;
    },

    chartSeries() {
      if (!this.projection?.yearly_data) return [];

      const data = this.projection.yearly_data;

      if (this.chartView === 'net_worth') {
        return [{
          name: 'Net Worth',
          data: data.map(d => ({ x: d.age, y: d.net_worth })),
        }];
      }

      if (this.chartView === 'cash_flow') {
        return [
          {
            name: 'Income',
            data: data.map(d => ({ x: d.age, y: d.income })),
          },
          {
            name: 'Expenditure',
            data: data.map(d => ({ x: d.age, y: d.expenditure })),
          },
        ];
      }

      if (this.chartView === 'asset_breakdown') {
        return [
          {
            name: 'Pensions',
            data: data.map(d => ({ x: d.age, y: d.assets?.pensions || 0 })),
          },
          {
            name: 'Property',
            data: data.map(d => ({ x: d.age, y: d.assets?.property || 0 })),
          },
          {
            name: 'Investments',
            data: data.map(d => ({ x: d.age, y: d.assets?.investments || 0 })),
          },
          {
            name: 'Cash',
            data: data.map(d => ({ x: d.age, y: d.assets?.cash || 0 })),
          },
        ];
      }

      return [];
    },

    chartOptions() {
      const baseOptions = {
        chart: {
          ...CHART_DEFAULTS.chart,
          id: 'goals-projection-chart',
          type: this.computedChartType,
          stacked: this.chartView === 'asset_breakdown',
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
            speed: 500,
          },
          events: {
            updated: () => this.updateEventMarkers(),
          },
        },
        dataLabels: {
          enabled: false,
        },
        stroke: {
          curve: 'smooth',
          width: this.computedChartType === 'bar' ? 0 : 2,
        },
        xaxis: {
          type: 'category',
          title: {
            text: 'Age',
            style: {
              fontSize: '12px',
              fontWeight: 500,
              color: TEXT_COLORS.muted,
            },
          },
          labels: {
            style: {
              fontSize: '11px',
              colors: TEXT_COLORS.muted,
            },
          },
          axisBorder: { show: false },
          axisTicks: { show: false },
          tickAmount: 10,
        },
        yaxis: {
          title: {
            text: this.yAxisTitle,
            style: {
              fontSize: '12px',
              fontWeight: 500,
              color: TEXT_COLORS.muted,
            },
          },
          labels: {
            formatter: (val) => this.formatCompact(val),
            style: {
              fontSize: '11px',
              colors: TEXT_COLORS.muted,
            },
          },
        },
        tooltip: {
          shared: true,
          intersect: false,
          x: {
            formatter: (val) => `Age ${Math.round(val)}`,
          },
          custom: ({ series, seriesIndex, dataPointIndex, w }) => {
            return this.buildCustomTooltip(series, seriesIndex, dataPointIndex, w);
          },
        },
        legend: {
          position: 'top',
          horizontalAlign: 'left',
          fontSize: '13px',
          fontFamily: CHART_DEFAULTS.chart.fontFamily,
          markers: {
            radius: 3,
          },
        },
        grid: {
          borderColor: BORDER_COLORS.default,
          strokeDashArray: 4,
          padding: {
            top: 120, // Extra padding for event icons floating high above bars
          },
        },
        // Add retirement age annotation
        annotations: this.retirementAnnotation,
      };

      // View-specific options
      if (this.chartView === 'net_worth') {
        return {
          ...baseOptions,
          colors: ['#A8B8D8'], // Muted periwinkle blue (matches bar.png reference)
          fill: {
            type: this.chartType === 'line' ? 'gradient' : 'solid',
            gradient: {
              shade: 'light',
              type: 'vertical',
              opacityFrom: 0.5,
              opacityTo: 0.1,
            },
          },
          plotOptions: {
            bar: {
              horizontal: false,
              columnWidth: '50%', // Thin elegant bars like bar.png
              borderRadius: 3, // Subtle rounded tops
              borderRadiusApplication: 'end',
            },
          },
        };
      }

      if (this.chartView === 'cash_flow') {
        return {
          ...baseOptions,
          colors: [SUCCESS_COLORS[500], ERROR_COLORS[500]],
          plotOptions: {
            bar: {
              horizontal: false,
              columnWidth: '70%',
              borderRadius: 2,
            },
          },
        };
      }

      if (this.chartView === 'asset_breakdown') {
        return {
          ...baseOptions,
          colors: [
            ASSET_COLORS.pensions,
            ASSET_COLORS.property,
            ASSET_COLORS.investments,
            ASSET_COLORS.cash,
          ],
          fill: {
            type: this.chartType === 'line' ? 'gradient' : 'solid',
            gradient: {
              opacityFrom: 0.6,
              opacityTo: 0.1,
            },
          },
          plotOptions: {
            bar: {
              horizontal: false,
              columnWidth: '80%',
              borderRadius: 2,
            },
          },
        };
      }

      return baseOptions;
    },

    yAxisTitle() {
      if (this.chartView === 'net_worth') return 'Net Worth (£)';
      if (this.chartView === 'cash_flow') return 'Amount (£)';
      if (this.chartView === 'asset_breakdown') return 'Asset Value (£)';
      return 'Value (£)';
    },

    retirementAnnotation() {
      if (!this.projection?.retirement_age) return {};

      // Use blue per design system (amber/orange are FORBIDDEN)
      const annotationColor = PRIMARY_COLORS[500]; // Raspberry 500

      return {
        xaxis: [
          {
            x: this.projection.retirement_age,
            borderColor: annotationColor,
            strokeDashArray: 5,
            label: {
              borderColor: annotationColor,
              style: {
                color: '#fff',
                background: annotationColor,
                fontSize: '11px',
                fontWeight: 500,
              },
              text: 'Retirement',
              position: 'top',
              orientation: 'vertical',
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
        return `£${Math.round(value / 1000000)}M`;
      }
      if (value >= 1000) {
        return `£${Math.round(value / 1000)}K`;
      }
      return `£${Math.round(value)}`;
    },

    onViewModeChange(mode) {
      this.fetchProjection({ household: mode === 'Household' });
    },

    onChartMounted() {
      if (!this.isComponentMounted) return;
      this.$nextTick(() => {
        if (this.isComponentMounted) {
          this.updateEventMarkers();
        }
      });
    },

    onChartUpdated() {
      if (!this.isComponentMounted) return;
      this.$nextTick(() => {
        if (this.isComponentMounted) {
          this.updateEventMarkers();
        }
      });
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

      // Get the chart dimensions and scales
      const w = apexChart.w;
      const globals = w.globals;

      // Get the plotable area dimensions
      const gridWidth = globals.gridWidth;
      const gridHeight = globals.gridHeight;
      const translateX = globals.translateX;
      const translateY = globals.translateY;

      // Get x and y axis ranges
      const xMin = globals.minX;
      const xMax = globals.maxX;
      const yMin = globals.minY;
      const yMax = globals.maxY;

      // Icon sizing - icons float above bars and stack vertically
      const iconSize = 26; // Size of icon in pixels
      const iconGap = 8; // Gap between stacked icons (vertical space)
      const floatGap = 20; // Gap between lowest icon and bar top

      // Get current age for completed state
      const currentAge = this.projection?.current_age || 0;

      // Group events by age for vertical stacking
      // CRITICAL: Use integer age as string key for consistent grouping
      const eventsByAge = {};
      this.projection.events.forEach(event => {
        const ageKey = String(Math.round(Number(event.age)));
        if (!eventsByAge[ageKey]) {
          eventsByAge[ageKey] = [];
        }
        eventsByAge[ageKey].push(event);
      });

      const markers = [];

      // Process each age group
      Object.keys(eventsByAge).forEach(ageKey => {
        const ageNum = parseInt(ageKey, 10);
        const eventsAtAge = eventsByAge[ageKey];
        const barTopValue = this.barTopValueByAge[ageNum];

        if (barTopValue === undefined) {
          return;
        }

        // Calculate x position (age to pixel)
        const xRatio = (ageNum - xMin) / (xMax - xMin);
        const x = translateX + (xRatio * gridWidth);

        // Calculate y position of the bar top (value to pixel - inverted because y=0 is top)
        const yRatio = (barTopValue - yMin) / (yMax - yMin);
        const barTopY = translateY + gridHeight - (yRatio * gridHeight);

        // Stack icons VERTICALLY above the bar - first icon closest to bar, others stack upward
        eventsAtAge.forEach((event, stackIndex) => {
          // Each icon stacks upward: stackIndex 0 is closest to bar, higher indices go up
          const stackOffset = stackIndex * (iconSize + iconGap);
          const y = barTopY - floatGap - iconSize - stackOffset;

          // Check if event is completed (past current age or marked completed)
          const isCompleted = event.is_completed || ageNum < currentAge;

          markers.push({
            event,
            x,
            y: Math.max(10, y), // Don't let icons go off the top
            isCompleted,
          });
        });
      });

      this.eventMarkers = markers;
    },

    onEventClick(event) {
      this.$emit('event-click', event);
    },

    showEventTooltip(event, domEvent) {
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
      // Get age from the data point - with category x-axis, use the x value from series data
      const seriesData = w.config.series[0]?.data[dataPointIndex];
      const age = seriesData?.x || w.globals.labels[dataPointIndex];
      const yearData = this.projection?.yearly_data?.find(d => d.age === age);
      const allEvents = this.projection?.events || [];

      // Find events at this age
      const eventsAtAge = allEvents.filter(e => e.age === age);

      let tooltipHtml = `
        <div class="apexcharts-tooltip-custom" style="padding: 12px; font-family: Inter, sans-serif; min-width: 200px;">
          <div style="font-weight: 600; margin-bottom: 8px; @apply text-horizon-500; font-size: 14px;">Age ${age}</div>
      `;

      // Add series values based on chart view
      if (this.chartView === 'net_worth' && yearData) {
        tooltipHtml += `
          <div style="display: flex; align-items: center; margin-bottom: 4px;">
            <span style="width: 10px; height: 10px; border-radius: 50%; background: ${PRIMARY_COLORS[600]}; margin-right: 8px;"></span>
            <span style="color: ${TEXT_COLORS.muted};">Net Worth:</span>
            <span style="font-weight: 600; margin-left: auto; @apply text-horizon-500;">${this.formatCurrency(yearData.net_worth)}</span>
          </div>
        `;
      } else if (this.chartView === 'cash_flow' && yearData) {
        tooltipHtml += `
          <div style="display: flex; align-items: center; margin-bottom: 4px;">
            <span style="width: 10px; height: 10px; border-radius: 50%; background: ${SUCCESS_COLORS[500]}; margin-right: 8px;"></span>
            <span style="color: ${TEXT_COLORS.muted};">Income:</span>
            <span style="font-weight: 600; margin-left: auto; @apply text-horizon-500;">${this.formatCurrency(yearData.income)}</span>
          </div>
          <div style="display: flex; align-items: center; margin-bottom: 4px;">
            <span style="width: 10px; height: 10px; border-radius: 50%; background: ${ERROR_COLORS[500]}; margin-right: 8px;"></span>
            <span style="color: ${TEXT_COLORS.muted};">Expenditure:</span>
            <span style="font-weight: 600; margin-left: auto; @apply text-horizon-500;">${this.formatCurrency(yearData.expenditure)}</span>
          </div>
        `;
      } else if (this.chartView === 'asset_breakdown' && yearData) {
        // Show each asset category with its value
        const assets = yearData.assets || {};
        const pensions = assets.pensions || 0;
        const property = assets.property || 0;
        const investments = assets.investments || 0;
        const cash = assets.cash || 0;
        const total = pensions + property + investments + cash;

        tooltipHtml += `
          <div style="display: flex; align-items: center; margin-bottom: 4px;">
            <span style="width: 10px; height: 10px; border-radius: 50%; background: ${ASSET_COLORS.pensions}; margin-right: 8px;"></span>
            <span style="color: ${TEXT_COLORS.muted};">Pensions:</span>
            <span style="font-weight: 600; margin-left: auto; @apply text-horizon-500;">${this.formatCurrency(pensions)}</span>
          </div>
          <div style="display: flex; align-items: center; margin-bottom: 4px;">
            <span style="width: 10px; height: 10px; border-radius: 50%; background: ${ASSET_COLORS.property}; margin-right: 8px;"></span>
            <span style="color: ${TEXT_COLORS.muted};">Property:</span>
            <span style="font-weight: 600; margin-left: auto; @apply text-horizon-500;">${this.formatCurrency(property)}</span>
          </div>
          <div style="display: flex; align-items: center; margin-bottom: 4px;">
            <span style="width: 10px; height: 10px; border-radius: 50%; background: ${ASSET_COLORS.investments}; margin-right: 8px;"></span>
            <span style="color: ${TEXT_COLORS.muted};">Investments:</span>
            <span style="font-weight: 600; margin-left: auto; @apply text-horizon-500;">${this.formatCurrency(investments)}</span>
          </div>
          <div style="display: flex; align-items: center; margin-bottom: 4px;">
            <span style="width: 10px; height: 10px; border-radius: 50%; background: ${ASSET_COLORS.cash}; margin-right: 8px;"></span>
            <span style="color: ${TEXT_COLORS.muted};">Cash:</span>
            <span style="font-weight: 600; margin-left: auto; @apply text-horizon-500;">${this.formatCurrency(cash)}</span>
          </div>
          <div style="display: flex; align-items: center; margin-top: 6px; padding-top: 6px; border-top: 1px solid ${BORDER_COLORS.default};">
            <span style="@apply text-neutral-500; font-weight: 600;">Total Assets:</span>
            <span style="font-weight: 700; margin-left: auto; @apply text-horizon-500;">${this.formatCurrency(total)}</span>
          </div>
        `;
      }

      // Add events at this age (goals, life events)
      if (eventsAtAge.length > 0) {
        // Group by type
        const goals = eventsAtAge.filter(e => e.type === 'goal');
        const lifeEvents = eventsAtAge.filter(e => e.type === 'life_event');

        tooltipHtml += `<div style="border-top: 1px solid ${BORDER_COLORS.default}; margin-top: 8px; padding-top: 8px;">`;

        // Show goals at this age
        if (goals.length > 0) {
          tooltipHtml += `<div style="font-size: 11px; color: ${TEXT_COLORS.muted}; margin-bottom: 4px; font-weight: 600;">Goals at this age:</div>`;
          goals.forEach(event => {
            const sign = event.impact === 'income' ? '+' : '-';
            const color = event.impact === 'income' ? SUCCESS_COLORS[600] : ERROR_COLORS[600];
            tooltipHtml += `
              <div style="display: flex; align-items: center; margin-bottom: 4px;">
                <span style="width: 8px; height: 8px; border-radius: 50%; background: ${event.color}; margin-right: 6px;"></span>
                <span style="@apply text-neutral-500; font-size: 12px;">${event.name}</span>
                <span style="font-weight: 600; margin-left: auto; color: ${color}; font-size: 12px;">${sign}${this.formatCurrency(event.amount)}</span>
              </div>
            `;
          });
        }

        // Show life events at this age
        if (lifeEvents.length > 0) {
          tooltipHtml += `<div style="font-size: 11px; color: ${TEXT_COLORS.muted}; margin-bottom: 4px; margin-top: ${goals.length > 0 ? '8px' : '0'}; font-weight: 600;">Life Events at this age:</div>`;
          lifeEvents.forEach(event => {
            const sign = event.impact === 'income' ? '+' : '-';
            const color = event.impact === 'income' ? SUCCESS_COLORS[600] : ERROR_COLORS[600];
            tooltipHtml += `
              <div style="display: flex; align-items: center; margin-bottom: 4px;">
                <span style="width: 8px; height: 8px; border-radius: 50%; background: ${event.color}; margin-right: 6px;"></span>
                <span style="@apply text-neutral-500; font-size: 12px;">${event.name}</span>
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
    viewMode(newMode) {
      this.onViewModeChange(newMode);
    },
    chartView() {
      if (!this.isComponentMounted) return;
      this.$nextTick(() => {
        if (this.isComponentMounted) {
          this.updateEventMarkers();
        }
      });
    },
    chartType() {
      if (!this.isComponentMounted) return;
      this.$nextTick(() => {
        if (this.isComponentMounted) {
          this.updateEventMarkers();
        }
      });
    },
    projection() {
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
</style>
