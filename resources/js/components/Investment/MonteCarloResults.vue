<template>
  <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto" @click.self="closeModal">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
      <!-- Background overlay -->
      <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" @click="closeModal"></div>

      <!-- Modal panel -->
      <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-5xl sm:w-full">
        <!-- Header -->
        <div class="bg-white px-6 py-4 border-b border-gray-200">
          <div class="flex justify-between items-center">
            <div>
              <h3 class="text-xl font-semibold text-gray-900">Monte Carlo Simulation Results</h3>
              <p v-if="goalName" class="text-sm text-gray-600 mt-1">{{ goalName }}</p>
            </div>
            <button
              @click="closeModal"
              class="text-gray-400 hover:text-gray-600 transition-colors"
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
            <div class="animate-spin rounded-full h-16 w-16 border-b-4 border-blue-600 mb-4"></div>
            <p class="text-gray-600 mb-2">Running Monte Carlo simulation...</p>
            <p class="text-sm text-gray-500">This may take a few moments (1,000 iterations)</p>
          </div>

          <!-- Results -->
          <div v-else-if="results">
            <!-- Key Metrics Summary -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
              <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <h4 class="text-xs font-medium text-blue-900 mb-1">Success Probability</h4>
                <p class="text-2xl font-bold" :class="getProbabilityColour(results.success_probability)">
                  {{ results.success_probability }}%
                </p>
              </div>
              <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                <h4 class="text-xs font-medium text-gray-700 mb-1">Median Outcome</h4>
                <p class="text-2xl font-bold text-gray-900">{{ formatCurrency(results.median_outcome) }}</p>
              </div>
              <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                <h4 class="text-xs font-medium text-green-900 mb-1">90th Percentile</h4>
                <p class="text-2xl font-bold text-green-600">{{ formatCurrency(results.percentile_90) }}</p>
              </div>
              <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                <h4 class="text-xs font-medium text-red-900 mb-1">10th Percentile</h4>
                <p class="text-2xl font-bold text-red-600">{{ formatCurrency(results.percentile_10) }}</p>
              </div>
            </div>

            <!-- Projection Chart -->
            <div class="bg-white border border-gray-200 rounded-lg p-6 mb-6">
              <h4 class="text-lg font-semibold text-gray-900 mb-4">Portfolio Value Projections</h4>
              <apexchart
                v-if="series && series.length > 0"
                type="area"
                :options="chartOptions"
                :series="series"
                height="400"
              />
            </div>

            <!-- Additional Metrics -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
              <!-- Statistical Summary -->
              <div class="bg-white border border-gray-200 rounded-lg p-4">
                <h4 class="text-sm font-semibold text-gray-900 mb-3">Statistical Summary</h4>
                <div class="space-y-2 text-sm">
                  <div class="flex justify-between">
                    <span class="text-gray-600">Mean Outcome:</span>
                    <span class="font-medium text-gray-900">{{ formatCurrency(results.mean_outcome) }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-600">Standard Deviation:</span>
                    <span class="font-medium text-gray-900">{{ formatCurrency(results.standard_deviation) }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-600">Required Return:</span>
                    <span class="font-medium text-gray-900">{{ results.required_return?.toFixed(2) }}%</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-600">Expected Return:</span>
                    <span class="font-medium text-gray-900">{{ results.expected_return?.toFixed(2) }}%</span>
                  </div>
                </div>
              </div>

              <!-- Scenario Breakdown -->
              <div class="bg-white border border-gray-200 rounded-lg p-4">
                <h4 class="text-sm font-semibold text-gray-900 mb-3">Scenario Breakdown</h4>
                <div class="space-y-3">
                  <div>
                    <div class="flex justify-between text-sm mb-1">
                      <span class="text-green-700 font-medium">Exceeds Target</span>
                      <span class="text-green-700 font-semibold">{{ results.success_probability }}%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                      <div
                        class="bg-green-600 h-2 rounded-full"
                        :style="{ width: results.success_probability + '%' }"
                      ></div>
                    </div>
                  </div>
                  <div>
                    <div class="flex justify-between text-sm mb-1">
                      <span class="text-yellow-700 font-medium">Within 10% of Target</span>
                      <span class="text-yellow-700 font-semibold">{{ nearTargetPercent }}%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                      <div
                        class="bg-yellow-500 h-2 rounded-full"
                        :style="{ width: nearTargetPercent + '%' }"
                      ></div>
                    </div>
                  </div>
                  <div>
                    <div class="flex justify-between text-sm mb-1">
                      <span class="text-red-700 font-medium">Below Target</span>
                      <span class="text-red-700 font-semibold">{{ belowTargetPercent }}%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                      <div
                        class="bg-red-600 h-2 rounded-full"
                        :style="{ width: belowTargetPercent + '%' }"
                      ></div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Interpretation -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
              <h4 class="text-sm font-semibold text-blue-900 mb-2">Interpretation</h4>
              <p class="text-sm text-blue-800">{{ interpretation }}</p>
            </div>
          </div>

          <!-- Error State -->
          <div v-else-if="error" class="text-center py-12">
            <svg class="mx-auto h-12 w-12 text-red-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="text-gray-900 font-medium mb-2">Failed to load simulation results</p>
            <p class="text-sm text-gray-600">{{ error }}</p>
          </div>
        </div>

        <!-- Footer -->
        <div class="bg-gray-50 px-6 py-4 flex justify-end gap-3">
          <button
            @click="closeModal"
            class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-100 transition-colors"
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

export default {
  name: 'MonteCarloResults',
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

    series() {
      if (!this.results || !this.results.projections) return [];

      const projections = this.results.projections;

      return [
        {
          name: '90th Percentile',
          data: projections.map(p => ({ x: p.year, y: p.percentile_90 })),
        },
        {
          name: 'Median (50th)',
          data: projections.map(p => ({ x: p.year, y: p.percentile_50 })),
        },
        {
          name: '10th Percentile',
          data: projections.map(p => ({ x: p.year, y: p.percentile_10 })),
        },
      ];
    },

    chartOptions() {
      return {
        chart: {
          type: 'area',
          fontFamily: 'Inter, system-ui, sans-serif',
          toolbar: {
            show: true,
            tools: {
              download: true,
              zoom: true,
              zoomin: true,
              zoomout: true,
              pan: true,
              reset: true,
            },
          },
        },
        colours: ['#10b981', '#3b82f6', '#ef4444'],
        stroke: {
          width: 2,
          curve: 'smooth',
        },
        fill: {
          type: 'gradient',
          gradient: {
            opacityFrom: 0.6,
            opacityTo: 0.1,
          },
        },
        xaxis: {
          type: 'numeric',
          title: {
            text: 'Year',
            style: {
              fontSize: '12px',
              fontWeight: 600,
              color: '#6b7280',
            },
          },
          labels: {
            style: {
              colours: '#6b7280',
              fontSize: '12px',
            },
          },
        },
        yaxis: {
          title: {
            text: 'Portfolio Value (£)',
            style: {
              fontSize: '12px',
              fontWeight: 600,
              color: '#6b7280',
            },
          },
          labels: {
            formatter: (val) => this.formatCurrencyShort(val),
            style: {
              colours: '#6b7280',
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
          horizontalAlign: 'centre',
          fontSize: '14px',
          fontWeight: 500,
          labels: {
            colours: '#374151',
          },
        },
        grid: {
          borderColour: '#e5e7eb',
          strokeDashArray: 3,
        },
        dataLabels: {
          enabled: false,
        },
        annotations: this.targetAmount ? {
          yaxis: [
            {
              y: this.targetAmount,
              borderColour: '#8b5cf6',
              strokeDashArray: 5,
              label: {
                borderColour: '#8b5cf6',
                style: {
                  color: '#fff',
                  background: '#8b5cf6',
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
      if (probability >= 80) return 'text-green-600';
      if (probability >= 60) return 'text-blue-600';
      if (probability >= 40) return 'text-yellow-600';
      return 'text-red-600';
    },
  },
};
</script>
