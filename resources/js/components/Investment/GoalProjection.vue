<template>
  <div class="goal-projection">
    <!-- Loading State -->
    <div v-if="loading" class="flex justify-center items-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-raspberry-500"></div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-eggshell-500 rounded-lg p-4 mb-6">
      <div class="flex items-center">
        <svg class="h-5 w-5 text-raspberry-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
        </svg>
        <span class="text-sm font-medium text-raspberry-800">{{ error }}</span>
      </div>
    </div>

    <!-- Main Content -->
    <div v-else class="space-y-6">
      <!-- Goal Selector -->
      <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
          <h2 class="text-xl font-semibold text-horizon-500">Goal-Based Projection Analysis</h2>

          <select
            v-model="selectedGoalId"
            @change="loadGoalProjection"
            class="px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
          >
            <option value="" disabled>Select a goal...</option>
            <option v-for="goal in goals" :key="goal.id" :value="goal.id">
              {{ goal.goal_name }} - £{{ formatNumber(goal.target_value) }}
            </option>
          </select>
        </div>
      </div>

      <!-- Goal Summary Card -->
      <div v-if="projectionData" class="bg-gradient-to-br from-violet-50 to-violet-100 rounded-lg shadow-md p-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
          <!-- Target Value -->
          <div>
            <p class="text-sm text-neutral-500 mb-2">Target Value</p>
            <p class="text-3xl font-bold text-horizon-500">
              £{{ formatNumber(projectionData.goal.target_value) }}
            </p>
            <p class="text-xs text-neutral-500">by {{ formatDate(projectionData.goal.target_date) }}</p>
          </div>

          <!-- Current Progress -->
          <div>
            <p class="text-sm text-neutral-500 mb-2">Current Value</p>
            <p class="text-3xl font-bold text-violet-600">
              £{{ formatNumber(projectionData.current_value) }}
            </p>
            <p class="text-xs text-neutral-500">{{ formatPercent(projectionData.progress_percent / 100) }} to goal</p>
          </div>

          <!-- Success Probability -->
          <div>
            <p class="text-sm text-neutral-500 mb-2">Success Probability</p>
            <p class="text-3xl font-bold" :class="getProbabilityColour(projectionData.probability_percent)">
              {{ Math.round(projectionData.probability_percent) }}%
            </p>
            <p class="text-xs text-neutral-500">Monte Carlo (1000 sims)</p>
          </div>

          <!-- On Track Status -->
          <div>
            <p class="text-sm text-neutral-500 mb-2">Status</p>
            <div class="flex items-center">
              <span class="px-4 py-2 rounded-full text-sm font-semibold" :class="getStatusClass(projectionData.on_track_status)">
                {{ projectionData.on_track_status }}
              </span>
            </div>
            <p class="text-xs text-neutral-500 mt-2">{{ projectionData.time_remaining.years }} years remaining</p>
          </div>
        </div>
      </div>

      <!-- Monte Carlo Probability Distribution -->
      <div v-if="projectionData" class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-horizon-500 mb-6">Monte Carlo Probability Distribution</h3>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <!-- Distribution Chart -->
          <div>
            <apexchart
              type="area"
              :options="distributionChartOptions"
              :series="distributionChartSeries"
              height="300"
            />
          </div>

          <!-- Probability Breakdown -->
          <div class="space-y-4">
            <div class="border border-light-gray rounded-lg p-4">
              <div class="flex justify-between items-center mb-2">
                <span class="text-sm font-medium text-neutral-500">Median Outcome (50th percentile)</span>
                <span class="text-xl font-bold text-horizon-500">
                  £{{ formatNumber(projectionData.monte_carlo?.median_value || 0) }}
                </span>
              </div>
              <div class="w-full bg-savannah-200 rounded-full h-2">
                <div class="h-2 bg-raspberry-500 rounded-full" style="width: 50%"></div>
              </div>
            </div>

            <div class="bg-eggshell-500 rounded-lg p-4">
              <div class="flex justify-between items-center mb-2">
                <span class="text-sm font-medium text-neutral-500">Best Case (95th percentile)</span>
                <span class="text-xl font-bold text-spring-600">
                  £{{ formatNumber(projectionData.monte_carlo?.percentile_95 || 0) }}
                </span>
              </div>
              <p class="text-xs text-neutral-500">Only 5% chance of exceeding this</p>
            </div>

            <div class="bg-eggshell-500 rounded-lg p-4">
              <div class="flex justify-between items-center mb-2">
                <span class="text-sm font-medium text-neutral-500">Conservative (20th percentile)</span>
                <span class="text-xl font-bold text-raspberry-600">
                  £{{ formatNumber(projectionData.monte_carlo?.percentile_20 || 0) }}
                </span>
              </div>
              <p class="text-xs text-neutral-500">80% chance of beating this</p>
            </div>

            <div class="bg-eggshell-500 p-4 rounded-lg">
              <p class="text-sm font-semibold text-horizon-500 mb-1">Success Rate</p>
              <p class="text-sm text-neutral-500">
                {{ Math.round(projectionData.probability_percent) }}% probability of reaching £{{ formatNumber(projectionData.goal.target_value) }}
                by {{ formatDate(projectionData.goal.target_date) }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Projection Timeline -->
      <div v-if="projectionData" class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-horizon-500 mb-6">Projection Timeline</h3>

        <apexchart
          type="line"
          :options="timelineChartOptions"
          :series="timelineChartSeries"
          height="350"
        />

        <div class="mt-4 grid grid-cols-3 gap-4">
          <div class="text-center p-3 bg-eggshell-500 rounded-lg">
            <p class="text-xs text-neutral-500 mb-1">Conservative (5th %ile)</p>
            <p class="text-lg font-semibold text-raspberry-600">
              £{{ formatNumber(projectionData.trajectory?.conservative_final || 0) }}
            </p>
          </div>
          <div class="text-center p-3 bg-eggshell-500 rounded-lg">
            <p class="text-xs text-neutral-500 mb-1">Expected (Median)</p>
            <p class="text-lg font-semibold text-violet-600">
              £{{ formatNumber(projectionData.trajectory?.expected_final || 0) }}
            </p>
          </div>
          <div class="text-center p-3 bg-eggshell-500 rounded-lg">
            <p class="text-xs text-neutral-500 mb-1">Optimistic (95th %ile)</p>
            <p class="text-lg font-semibold text-spring-600">
              £{{ formatNumber(projectionData.trajectory?.optimistic_final || 0) }}
            </p>
          </div>
        </div>
      </div>

      <!-- Shortfall Analysis (if applicable) -->
      <div v-if="shortfallData && shortfallData.is_shortfall" class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-horizon-500 mb-6">Shortfall Analysis</h3>

        <div class="bg-eggshell-500 rounded-lg p-6 mb-6">
          <div class="flex items-start">
            <svg class="h-6 w-6 text-violet-600 mr-3 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
            </svg>
            <div>
              <h4 class="text-lg font-semibold text-horizon-500 mb-2">Projected Shortfall</h4>
              <p class="text-sm text-neutral-500 mb-2">
                At current contribution rates, you are projected to fall short of your goal by
                <strong class="text-raspberry-600">£{{ formatNumber(shortfallData.shortfall_amount) }}</strong>.
              </p>
              <p class="text-sm text-neutral-500">
                Current trajectory will reach only {{ formatPercent(shortfallData.achievement_percent / 100) }} of your target.
              </p>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- Options to Close Gap -->
          <div>
            <h4 class="text-md font-semibold text-horizon-500 mb-4">Options to Close the Gap</h4>
            <div class="space-y-3">
              <div class="border border-light-gray rounded-lg p-4">
                <p class="text-sm font-medium text-neutral-500 mb-2">Increase Monthly Contribution</p>
                <p class="text-2xl font-bold text-violet-600 mb-1">
                  +£{{ formatNumber(shortfallData.additional_monthly_needed || 0) }}
                </p>
                <p class="text-xs text-neutral-500">
                  From £{{ formatNumber(projectionData.goal.monthly_contribution || 0) }} to
                  £{{ formatNumber((projectionData.goal.monthly_contribution || 0) + (shortfallData.additional_monthly_needed || 0)) }}
                </p>
              </div>

              <div class="border border-light-gray rounded-lg p-4">
                <p class="text-sm font-medium text-neutral-500 mb-2">Reduce Target Amount</p>
                <p class="text-2xl font-bold text-violet-600 mb-1">
                  £{{ formatNumber(shortfallData.achievable_target || 0) }}
                </p>
                <p class="text-xs text-neutral-500">Realistic target at current contribution rate</p>
              </div>

              <div class="border border-light-gray rounded-lg p-4">
                <p class="text-sm font-medium text-neutral-500 mb-2">Extend Timeline</p>
                <p class="text-2xl font-bold text-violet-600 mb-1">
                  +{{ shortfallData.additional_years_needed || 0 }} years
                </p>
                <p class="text-xs text-neutral-500">Additional time needed to reach target</p>
              </div>
            </div>
          </div>

          <!-- Sensitivity Analysis -->
          <div>
            <h4 class="text-md font-semibold text-horizon-500 mb-4">Sensitivity Analysis</h4>
            <apexchart
              v-if="shortfallData.sensitivity"
              type="bar"
              :options="sensitivityChartOptions"
              :series="sensitivityChartSeries"
              height="280"
            />
          </div>
        </div>
      </div>

      <!-- Glide Path Recommendation -->
      <div v-if="glidePath" class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-horizon-500 mb-6">Recommended Glide Path</h3>

        <div class="mb-6">
          <apexchart
            type="area"
            :options="glidePathChartOptions"
            :series="glidePathChartSeries"
            height="300"
          />
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div v-for="(phase, index) in glidePath.phases" :key="index" class="border border-light-gray rounded-lg p-4">
            <p class="text-xs text-neutral-500 mb-2">{{ phase.phase_name }}</p>
            <div class="space-y-1">
              <div class="flex justify-between text-xs">
                <span class="text-neutral-500">Equities:</span>
                <span class="font-semibold text-violet-600">{{ phase.equity_percent }}%</span>
              </div>
              <div class="flex justify-between text-xs">
                <span class="text-neutral-500">Bonds:</span>
                <span class="font-semibold text-spring-600">{{ phase.bond_percent }}%</span>
              </div>
              <div class="flex justify-between text-xs">
                <span class="text-neutral-500">Cash:</span>
                <span class="font-semibold text-neutral-500">{{ phase.cash_percent }}%</span>
              </div>
            </div>
            <p class="text-xs text-neutral-500 mt-2">Years {{ phase.years_from_now }} to {{ phase.years_from_now + phase.duration }}</p>
          </div>
        </div>

        <div class="mt-6 p-4 bg-eggshell-500 rounded-lg">
          <p class="text-sm text-neutral-500">
            <strong>Recommendation:</strong> {{ glidePath.recommendation }}
          </p>
        </div>
      </div>

      <!-- Action Items -->
      <div v-if="projectionData && projectionData.action_items" class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-horizon-500 mb-4">Recommended Actions</h3>

        <div class="space-y-3">
          <div
            v-for="(action, index) in projectionData.action_items"
            :key="index"
            class="flex items-start p-4 border border-light-gray rounded-lg hover:bg-eggshell-500 transition-colors"
          >
            <div class="flex-shrink-0 w-8 h-8 bg-raspberry-500 text-white rounded-full flex items-center justify-center font-semibold mr-4">
              {{ index + 1 }}
            </div>
            <div class="flex-1">
              <h4 class="font-semibold text-horizon-500 mb-1">{{ action.title }}</h4>
              <p class="text-sm text-neutral-500 mb-2">{{ action.description }}</p>
              <div v-if="action.impact" class="text-sm font-medium text-spring-600">
                Estimated Impact: {{ action.impact }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import api from '@/services/api';
import { CHART_COLORS, PRIMARY_COLORS, SUCCESS_COLORS, ERROR_COLORS, WARNING_COLORS, SECONDARY_COLORS, CHART_DEFAULTS, TEXT_COLORS, BORDER_COLORS } from '@/constants/designSystem';
import { currencyMixin } from '@/mixins/currencyMixin';

import logger from '@/utils/logger';
export default {
  name: 'GoalProjection',

  mixins: [currencyMixin],

  data() {
    return {
      loading: false,
      error: null,
      goals: [],
      selectedGoalId: '',
      projectionData: null,
      shortfallData: null,
      glidePath: null,
    };
  },

  computed: {
    isPreviewMode() {
      return this.$store.getters['preview/isPreviewMode'];
    },

    distributionChartOptions() {
      return {
        chart: {
          ...CHART_DEFAULTS.chart,
          type: 'area',
        },
        dataLabels: {
          enabled: false,
        },
        stroke: {
          curve: 'smooth',
          width: 2,
        },
        xaxis: {
          title: {
            text: 'Final Portfolio Value (£)',
          },
          labels: {
            formatter: (val) => '£' + this.formatNumber(val),
          },
        },
        yaxis: {
          title: {
            text: 'Probability',
          },
          labels: {
            formatter: (val) => val.toFixed(1) + '%',
          },
        },
        fill: {
          type: 'gradient',
          gradient: {
            shadeIntensity: 1,
            opacityFrom: 0.7,
            opacityTo: 0.2,
          },
        },
        colors: [PRIMARY_COLORS[500]],
        tooltip: {
          y: {
            formatter: (val) => val.toFixed(2) + '%',
          },
        },
      };
    },

    distributionChartSeries() {
      if (!this.projectionData?.monte_carlo?.distribution) return [];

      return [{
        name: 'Probability',
        data: this.projectionData.monte_carlo.distribution,
      }];
    },

    timelineChartOptions() {
      return {
        chart: {
          ...CHART_DEFAULTS.chart,
          type: 'line',
          toolbar: {
            show: true,
          },
        },
        stroke: {
          width: [3, 2, 2, 2],
          curve: 'smooth',
          dashArray: [0, 0, 5, 5],
        },
        xaxis: {
          title: {
            text: 'Year',
          },
        },
        yaxis: {
          title: {
            text: 'Portfolio Value (£)',
          },
          labels: {
            formatter: (val) => '£' + this.formatNumber(val),
          },
        },
        colors: [PRIMARY_COLORS[500], SUCCESS_COLORS[500], ERROR_COLORS[500], WARNING_COLORS[500]],
        legend: {
          position: 'top',
        },
        annotations: {
          yaxis: [{
            y: this.projectionData?.goal?.target_value || 0,
            borderColor: '#000',
            strokeDashArray: 4,
            label: {
              text: 'Target',
              style: {
                color: '#fff',
                background: '#000',
              },
            },
          }],
        },
      };
    },

    timelineChartSeries() {
      if (!this.projectionData?.trajectory) return [];

      return [
        {
          name: 'Expected Path',
          data: this.projectionData.trajectory.expected_path || [],
        },
        {
          name: 'Optimistic (95%)',
          data: this.projectionData.trajectory.optimistic_path || [],
        },
        {
          name: 'Conservative (5%)',
          data: this.projectionData.trajectory.conservative_path || [],
        },
        {
          name: 'Target',
          data: this.projectionData.trajectory.target_line || [],
        },
      ];
    },

    sensitivityChartOptions() {
      return {
        chart: {
          ...CHART_DEFAULTS.chart,
          type: 'bar',
        },
        plotOptions: {
          bar: {
            horizontal: true,
          },
        },
        xaxis: {
          categories: ['Return +2%', 'Return +1%', 'Current', 'Return -1%', 'Return -2%'],
        },
        colors: [SUCCESS_COLORS[500]],
      };
    },

    sensitivityChartSeries() {
      if (!this.shortfallData?.sensitivity) return [];

      return [{
        name: 'Final Value',
        data: this.shortfallData.sensitivity,
      }];
    },

    glidePathChartOptions() {
      return {
        chart: {
          ...CHART_DEFAULTS.chart,
          type: 'area',
          stacked: true,
        },
        dataLabels: {
          enabled: false,
        },
        stroke: {
          curve: 'smooth',
          width: 2,
        },
        xaxis: {
          title: {
            text: 'Years from Now',
          },
        },
        yaxis: {
          title: {
            text: 'Allocation (%)',
          },
          max: 100,
        },
        colors: [PRIMARY_COLORS[500], SUCCESS_COLORS[500], SECONDARY_COLORS[500]],
        legend: {
          position: 'top',
        },
      };
    },

    glidePathChartSeries() {
      if (!this.glidePath?.timeseries) return [];

      return [
        {
          name: 'Equities',
          data: this.glidePath.timeseries.equity || [],
        },
        {
          name: 'Bonds',
          data: this.glidePath.timeseries.bonds || [],
        },
        {
          name: 'Cash',
          data: this.glidePath.timeseries.cash || [],
        },
      ];
    },
  },

  mounted() {
    // Preview users are real DB users - use normal API
    this.loadGoals();
  },

  methods: {
    async loadGoals() {
      try {
        // This would typically come from a Vuex store or API call
        const response = await api.get('/investment/goals');
        this.goals = response.data.goals || [];

        if (this.goals.length > 0) {
          this.selectedGoalId = this.goals[0].id;
          await this.loadGoalProjection();
        }
      } catch (err) {
        logger.error('Error loading goals:', err);
      }
    },

    async loadGoalProjection() {
      if (!this.selectedGoalId) return;

      this.loading = true;
      this.error = null;

      try {
        // Load projection data
        const projResponse = await api.get(`/investment/goal-progress/${this.selectedGoalId}`);
        this.projectionData = projResponse.data.data;

        // Load shortfall analysis if goal is off-track
        if (this.projectionData.on_track_status !== 'On Track') {
          const shortfallResponse = await api.get(`/investment/goal-progress/${this.selectedGoalId}/shortfall`);
          this.shortfallData = shortfallResponse.data.data;
        }

        // Load glide path recommendation
        const glidePathResponse = await api.get('/investment/goal-progress/glide-path', {
          params: {
            goal_id: this.selectedGoalId,
          },
        });
        this.glidePath = glidePathResponse.data.data;
      } catch (err) {
        logger.error('Error loading goal projection:', err);
        this.error = err.response?.data?.message || 'Failed to load goal projection. Please try again.';
      } finally {
        this.loading = false;
      }
    },

    formatPercent(value) {
      if (value === null || value === undefined) return 'N/A';
      return (value * 100).toFixed(1) + '%';
    },

    formatDate(dateString) {
      if (!dateString) return 'N/A';
      const date = new Date(dateString);
      return date.toLocaleDateString('en-GB', { year: 'numeric', month: 'short' });
    },

    getProbabilityColour(probability) {
      if (probability >= 85) return 'text-spring-600';
      if (probability >= 70) return 'text-violet-600';
      if (probability >= 50) return 'text-violet-600';
      return 'text-raspberry-600';
    },

    getStatusClass(status) {
      if (status === 'On Track') return 'bg-spring-500 text-white';
      if (status === 'Needs Attention') return 'bg-violet-500 text-white';
      return 'bg-raspberry-500 text-white';
    },
  },
};
</script>

<style scoped>
/* Component-specific styles */
</style>
