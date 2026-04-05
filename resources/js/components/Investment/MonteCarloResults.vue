<template>
  <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto" @click.self="closeModal">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
      <!-- Background overlay -->
      <div class="fixed inset-0 transition-opacity bg-horizon-500 bg-opacity-75" @click="closeModal"></div>

      <!-- Modal panel -->
      <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-5xl sm:w-full">
        <!-- Header -->
        <div class="bg-white px-6 py-4 border-b border-light-gray">
          <div class="flex justify-between items-center">
            <div>
              <h3 class="text-xl font-semibold text-horizon-500">Monte Carlo Simulation Results</h3>
              <p v-if="goalName" class="text-sm text-neutral-500 mt-1">{{ goalName }}</p>
            </div>
            <button
              @click="closeModal"
              class="text-horizon-400 hover:text-neutral-500 transition-colors"
            >
              <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>

        <!-- Content -->
        <div class="bg-white px-6 py-4">
          <!-- Loading State -->
          <div v-if="loading" class="flex flex-col items-center justify-center py-12">
            <div class="animate-spin rounded-full h-16 w-16 border-b-4 border-violet-600 mb-4"></div>
            <p class="text-neutral-500 mb-2">Running Monte Carlo simulation...</p>
            <p class="text-sm text-neutral-500">This may take a few moments (1,000 iterations)</p>
          </div>

          <!-- Results -->
          <div v-else-if="results">
            <!-- Key Metrics Summary -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
              <div class="bg-eggshell-500 rounded-lg p-4">
                <h4 class="text-xs font-medium text-violet-900 mb-1">Success Probability</h4>
                <p class="text-2xl font-bold" :class="getProbabilityColour(results.success_probability)">
                  {{ results.success_probability }}%
                </p>
              </div>
              <div class="bg-eggshell-500 border border-light-gray rounded-lg p-4">
                <h4 class="text-xs font-medium text-neutral-500 mb-1">Median Outcome</h4>
                <p class="text-2xl font-bold text-horizon-500">{{ formatCurrency(results.median_outcome) }}</p>
              </div>
              <div class="bg-eggshell-500 rounded-lg p-4">
                <h4 class="text-xs font-medium text-spring-900 mb-1">90th Percentile</h4>
                <p class="text-2xl font-bold text-spring-600">{{ formatCurrency(results.percentile_90) }}</p>
              </div>
              <div class="bg-eggshell-500 rounded-lg p-4">
                <h4 class="text-xs font-medium text-raspberry-900 mb-1">10th Percentile</h4>
                <p class="text-2xl font-bold text-raspberry-600">{{ formatCurrency(results.percentile_10) }}</p>
              </div>
            </div>

            <!-- Projection Chart -->
            <div class="bg-white border border-light-gray rounded-lg p-6 mb-6">
              <h4 class="text-lg font-semibold text-horizon-500 mb-4">Portfolio Value Projections</h4>
              <apexchart
                v-if="series && series.length > 0"
                :key="chartKey"
                type="area"
                :options="chartOptions"
                :series="series"
                height="400"
              />
            </div>

            <!-- Additional Metrics -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
              <!-- Statistical Summary -->
              <div class="bg-white border border-light-gray rounded-lg p-4">
                <h4 class="text-sm font-semibold text-horizon-500 mb-3">Statistical Summary</h4>
                <div class="space-y-2 text-sm">
                  <div class="flex justify-between">
                    <span class="text-neutral-500">Mean Outcome:</span>
                    <span class="font-medium text-horizon-500">{{ formatCurrency(results.mean_outcome) }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-neutral-500">Standard Deviation:</span>
                    <span class="font-medium text-horizon-500">{{ formatCurrency(results.standard_deviation) }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-neutral-500">Required Return:</span>
                    <span class="font-medium text-horizon-500">{{ results.required_return?.toFixed(2) }}%</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-neutral-500">Expected Return:</span>
                    <span class="font-medium text-horizon-500">{{ results.expected_return?.toFixed(2) }}%</span>
                  </div>
                </div>
              </div>

              <!-- Scenario Breakdown -->
              <div class="bg-white border border-light-gray rounded-lg p-4">
                <h4 class="text-sm font-semibold text-horizon-500 mb-3">Scenario Breakdown</h4>
                <div class="space-y-3">
                  <div>
                    <div class="flex justify-between text-sm mb-1">
                      <span class="text-spring-700 font-medium">Exceeds Target</span>
                      <span class="text-spring-700 font-semibold">{{ results.success_probability }}%</span>
                    </div>
                    <div class="w-full bg-savannah-200 rounded-full h-2">
                      <div
                        class="bg-spring-600 h-2 rounded-full"
                        :style="{ width: results.success_probability + '%' }"
                      ></div>
                    </div>
                  </div>
                  <div>
                    <div class="flex justify-between text-sm mb-1">
                      <span class="text-violet-700 font-medium">Within 10% of Target</span>
                      <span class="text-violet-700 font-semibold">{{ nearTargetPercent }}%</span>
                    </div>
                    <div class="w-full bg-savannah-200 rounded-full h-2">
                      <div
                        class="bg-violet-500 h-2 rounded-full"
                        :style="{ width: nearTargetPercent + '%' }"
                      ></div>
                    </div>
                  </div>
                  <div>
                    <div class="flex justify-between text-sm mb-1">
                      <span class="text-raspberry-700 font-medium">Below Target</span>
                      <span class="text-raspberry-700 font-semibold">{{ belowTargetPercent }}%</span>
                    </div>
                    <div class="w-full bg-savannah-200 rounded-full h-2">
                      <div
                        class="bg-raspberry-600 h-2 rounded-full"
                        :style="{ width: belowTargetPercent + '%' }"
                      ></div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Interpretation -->
            <div class="bg-eggshell-500 rounded-lg p-4">
              <h4 class="text-sm font-semibold text-violet-900 mb-2">Interpretation</h4>
              <p class="text-sm text-violet-800">{{ interpretation }}</p>
            </div>
          </div>

          <!-- Error State -->
          <div v-else-if="error" class="text-center py-12">
            <svg class="mx-auto h-12 w-12 text-raspberry-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="text-horizon-500 font-medium mb-2">Failed to load simulation results</p>
            <p class="text-sm text-neutral-500">{{ error }}</p>
          </div>
        </div>

        <!-- Footer -->
        <div class="bg-eggshell-500 px-6 py-4 flex justify-end gap-3">
          <button
            @click="closeModal"
            class="px-4 py-2 border border-horizon-300 rounded-md text-sm font-medium text-neutral-500 hover:bg-savannah-100 transition-colors"
          >
            Close
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import VueApexCharts from 'vue3-apexcharts';
import { currencyMixin } from '@/mixins/currencyMixin';
import { TEXT_COLORS, BORDER_COLORS, CHART_DEFAULTS, WARNING_COLORS } from '@/constants/designSystem';

// Target annotation color (violet-500 from Fynla design system)
const TARGET_COLOR = WARNING_COLORS[500];

// Monte Carlo probability band colors (dark blue to light teal)
const PROBABILITY_COLORS = {
  p90: '#1e3a5f',  // Dark navy - 90% probability
  p85: '#3b82f6',  // Blue - 85% probability
  p80: '#14b8a6',  // Teal - 80% probability
  p75: '#a7f3d0',  // Light mint - 75% probability
};

export default {
  name: 'MonteCarloResults',

  emits: ['close'],

  mixins: [currencyMixin],

  components: {
    apexchart: VueApexCharts,
  },

  props: {
    show: {
      type: Boolean,
      required: true,
    },
    results: {
      type: Object,
      default: null,
    },
    goalName: {
      type: String,
      default: '',
    },
    targetAmount: {
      type: Number,
      default: 0,
    },
    loading: {
      type: Boolean,
      default: false,
    },
    error: {
      type: String,
      default: null,
    },
  },

  computed: {
    nearTargetPercent() {
      // Estimate scenarios within 10% of target (approximation)
      return Math.max(0, Math.min(15, 100 - this.results.success_probability - this.belowTargetPercent));
    },

    belowTargetPercent() {
      return Math.max(0, 100 - this.results.success_probability);
    },

    interpretation() {
      const prob = this.results.success_probability;
      if (prob >= 90) {
        return 'Excellent! Your goal has a very high probability of success. Your current strategy is well-positioned to meet your target.';
      } else if (prob >= 75) {
        return 'Good progress. Your goal has a strong chance of success, though there is some uncertainty. Consider maintaining or slightly increasing contributions.';
      } else if (prob >= 60) {
        return 'Fair outlook. Your goal is achievable but may require adjustments. Consider increasing monthly contributions or extending the time horizon.';
      } else if (prob >= 40) {
        return 'Needs attention. Your goal faces significant challenges. Consider increasing contributions substantially, reducing the target, or extending the timeline.';
      } else {
        return 'Off track. Your current strategy is unlikely to meet the goal. Significant changes are needed: increase contributions materially, reduce target amount, or extend timeline considerably.';
      }
    },

    chartKey() {
      const projections = this.results?.projections;
      return `montecarlo-${projections?.length || 0}-${Math.round(this.results?.median_outcome || 0)}`;
    },

    series() {
      if (!this.results || !this.results.projections) return [];

      const projections = this.results.projections;

      // Create probability bands - each band shows the range for that confidence level
      // 90% probability: 10th percentile
      // 85% probability: 15th percentile (interpolated)
      // 80% probability: 20th percentile (interpolated)
      // 75% probability: 25th percentile

      return [
        {
          name: '90% Probability',
          data: projections.map(p => ({ x: p.year, y: p.percentile_10 })),
        },
        {
          name: '85% Probability',
          data: projections.map(p => ({ x: p.year, y: p.percentile_15 || (p.percentile_10 + (p.percentile_25 - p.percentile_10) * 0.33) })),
        },
        {
          name: '80% Probability',
          data: projections.map(p => ({ x: p.year, y: p.percentile_20 || (p.percentile_10 + (p.percentile_25 - p.percentile_10) * 0.67) })),
        },
        {
          name: '75% Probability',
          data: projections.map(p => ({ x: p.year, y: p.percentile_25 })),
        },
      ];
    },

    chartOptions() {
      return {
        chart: {
          ...CHART_DEFAULTS.chart,
          type: 'area',
          toolbar: {
            show: true,
            tools: {
              download: true,
              zoom: false,
              zoomin: false,
              zoomout: false,
              pan: false,
              reset: false,
            },
          },
          stacked: false,
        },
        colors: [PROBABILITY_COLORS.p95, PROBABILITY_COLORS.p90, PROBABILITY_COLORS.p85, PROBABILITY_COLORS.p80],
        stroke: {
          width: 2,
          curve: 'smooth',
        },
        fill: {
          type: 'solid',
          opacity: 0.6,
        },
        xaxis: {
          type: 'numeric',
          title: {
            text: 'Year',
            style: {
              fontSize: '12px',
              fontWeight: 600,
              color: TEXT_COLORS.muted,
            },
          },
          labels: {
            style: {
              colors: TEXT_COLORS.muted,
              fontSize: '12px',
            },
            formatter: (val) => Math.round(val),
          },
        },
        yaxis: {
          title: {
            text: 'Investment Value',
            style: {
              fontSize: '12px',
              fontWeight: 600,
              color: TEXT_COLORS.muted,
            },
          },
          labels: {
            formatter: (val) => this.formatCurrencyShort(val),
            style: {
              colors: TEXT_COLORS.muted,
              fontSize: '12px',
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
          fontWeight: 500,
          labels: {
            colors: TEXT_COLORS.secondary,
          },
          markers: {
            width: 10,
            height: 10,
            radius: 10,
          },
          itemMargin: {
            horizontal: 12,
          },
        },
        grid: {
          borderColor: BORDER_COLORS.default,
          strokeDashArray: 3,
        },
        dataLabels: {
          enabled: false,
        },
        annotations: this.targetAmount ? {
          yaxis: [
            {
              y: this.targetAmount,
              borderColor: TARGET_COLOR,
              strokeDashArray: 5,
              label: {
                borderColor: TARGET_COLOR,
                style: {
                  color: '#fff',
                  background: TARGET_COLOR,
                },
                text: `Target: ${this.formatCurrencyShort(this.targetAmount)}`,
              },
            },
          ],
        } : {},
      };
    },
  },

  methods: {
    closeModal() {
      this.$emit('close');
    },

    formatCurrencyShort(value) {
      if (value >= 1000000) {
        return `£${(value / 1000000).toFixed(1)}M`;
      } else if (value >= 1000) {
        return `£${(value / 1000).toFixed(0)}K`;
      }
      return this.formatCurrency(value);
    },

    getProbabilityColour(probability) {
      if (probability >= 80) return 'text-spring-600';
      if (probability >= 60) return 'text-violet-600';
      if (probability >= 40) return 'text-violet-600';
      return 'text-raspberry-600';
    },
  },
};
</script>
