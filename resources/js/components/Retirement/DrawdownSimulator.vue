<template>
  <div class="drawdown-simulator bg-white rounded-lg shadow p-6">
    <h3 class="text-lg font-semibold text-horizon-500 mb-6">Drawdown Simulator</h3>

    <!-- Simulator Controls -->
    <div class="space-y-6 mb-8">
      <!-- Initial Pot Size -->
      <div>
        <label class="block text-sm font-medium text-neutral-500 mb-2">
          Initial Pension Pot: £{{ formatNumber(simulatorData.initialPot) }}
        </label>
        <input
          v-model.number="simulatorData.initialPot"
          type="range"
          :min="50000"
          :max="1000000"
          :step="10000"
          class="w-full h-2 bg-savannah-200 rounded-lg appearance-none cursor-pointer"
          @input="runSimulation"
        />
        <div class="flex items-center justify-between text-xs text-neutral-500 mt-1">
          <span>£50k</span>
          <span>£1m</span>
        </div>
      </div>

      <!-- Withdrawal Rate -->
      <div>
        <label class="block text-sm font-medium text-neutral-500 mb-2">
          Annual Withdrawal Rate: {{ simulatorData.withdrawalRate }}%
        </label>
        <input
          v-model.number="simulatorData.withdrawalRate"
          type="range"
          :min="2"
          :max="6"
          :step="0.5"
          class="w-full h-2 bg-savannah-200 rounded-lg appearance-none cursor-pointer"
          @input="runSimulation"
        />
        <div class="flex items-center justify-between text-xs text-neutral-500 mt-1">
          <span>2%</span>
          <span>3%</span>
          <span>4%</span>
          <span>5%</span>
          <span>6%</span>
        </div>
        <p class="text-xs text-neutral-500 mt-2">
          Annual withdrawal: £{{ formatNumber(annualWithdrawal) }}
        </p>
      </div>

      <!-- Growth Rate -->
      <div>
        <label class="block text-sm font-medium text-neutral-500 mb-2">
          Investment Growth Rate: {{ simulatorData.growthRate }}% p.a.
        </label>
        <input
          v-model.number="simulatorData.growthRate"
          type="range"
          :min="0"
          :max="8"
          :step="0.5"
          class="w-full h-2 bg-savannah-200 rounded-lg appearance-none cursor-pointer"
          @input="runSimulation"
        />
        <div class="flex items-center justify-between text-xs text-neutral-500 mt-1">
          <span>0%</span>
          <span>8%</span>
        </div>
      </div>

      <!-- Inflation Rate -->
      <div>
        <label class="block text-sm font-medium text-neutral-500 mb-2">
          Inflation Rate: {{ simulatorData.inflationRate }}% p.a.
        </label>
        <input
          v-model.number="simulatorData.inflationRate"
          type="range"
          :min="0"
          :max="5"
          :step="0.5"
          class="w-full h-2 bg-savannah-200 rounded-lg appearance-none cursor-pointer"
          @input="runSimulation"
        />
        <div class="flex items-center justify-between text-xs text-neutral-500 mt-1">
          <span>0%</span>
          <span>5%</span>
        </div>
      </div>
    </div>

    <!-- Results -->
    <div v-if="simulationResults" class="space-y-6">
      <!-- Result Summary -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="p-4 bg-savannah-100 rounded-lg">
          <p class="text-sm text-neutral-500 mb-1">Portfolio Depletion</p>
          <p
            class="text-2xl font-bold"
            :class="simulationResults.depletes ? 'text-raspberry-600' : 'text-spring-600'"
          >
            {{ simulationResults.depletes ? `Age ${simulationResults.depletionAge}` : 'No' }}
          </p>
          <p class="text-xs text-neutral-500 mt-1">
            {{ simulationResults.depletes ? 'Portfolio runs out' : 'Sustainable' }}
          </p>
        </div>

        <div class="p-4 bg-savannah-100 rounded-lg">
          <p class="text-sm text-neutral-500 mb-1">Final Balance at 95</p>
          <p class="text-2xl font-bold text-horizon-500">
            £{{ formatNumber(simulationResults.finalBalance) }}
          </p>
          <p class="text-xs text-neutral-500 mt-1">Remaining pot value</p>
        </div>

        <div class="p-4 bg-savannah-100 rounded-lg">
          <p class="text-sm text-neutral-500 mb-1">Real Value Lost (Inflation)</p>
          <p class="text-2xl font-bold text-violet-600">
            {{ simulationResults.realValueLoss }}%
          </p>
          <p class="text-xs text-neutral-500 mt-1">Purchasing power erosion</p>
        </div>
      </div>

      <!-- Chart -->
      <div>
        <apexchart
          :key="chartKey"
          type="line"
          :options="chartOptions"
          :series="chartSeries"
          height="300"
        ></apexchart>
      </div>

      <!-- Warning/Success Message -->
      <div
        v-if="simulationResults.depletes"
        class="bg-savannah-100 rounded-lg p-4 flex items-start"
      >
        <svg class="w-5 h-5 text-raspberry-600 mr-3 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
        </svg>
        <div>
          <p class="text-sm font-bold text-raspberry-900">Warning: Portfolio Depletion</p>
          <p class="text-sm text-raspberry-800 mt-1">
            At this withdrawal rate, your pension pot would run out at age {{ simulationResults.depletionAge }}.
            Consider reducing your withdrawal rate or increasing investment growth.
          </p>
        </div>
      </div>

      <div
        v-else
        class="bg-savannah-100 rounded-lg p-4 flex items-start"
      >
        <svg class="w-5 h-5 text-spring-600 mr-3 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <div>
          <p class="text-sm font-bold text-spring-900">Sustainable Drawdown</p>
          <p class="text-sm text-spring-800 mt-1">
            Your pension pot should sustain this withdrawal rate throughout retirement.
            You would have approximately £{{ formatNumber(simulationResults.finalBalance) }} remaining at age 95.
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { SUCCESS_COLORS, ERROR_COLORS, BORDER_COLORS, PRIMARY_COLORS, CHART_DEFAULTS, TEXT_COLORS } from '@/constants/designSystem';

export default {
  name: 'DrawdownSimulator',

  data() {
    return {
      simulatorData: {
        initialPot: 500000,
        withdrawalRate: 4.0,
        growthRate: 5.0,
        inflationRate: 2.5,
      },
      simulationResults: null,
    };
  },

  computed: {
    annualWithdrawal() {
      return Math.round((this.simulatorData.initialPot * this.simulatorData.withdrawalRate) / 100);
    },

    chartKey() {
      const values = this.simulationResults?.portfolioValues;
      return `drawdown-sim-${values?.length || 0}-${Math.round(values?.[values?.length - 1] || 0)}`;
    },

    chartSeries() {
      if (!this.simulationResults) return [];
      return [
        {
          name: 'Portfolio Value',
          data: this.simulationResults.portfolioValues,
        },
      ];
    },

    chartOptions() {
      return {
        chart: {
          ...CHART_DEFAULTS.chart,
          type: 'line',
          height: 300,
        },
        stroke: {
          curve: 'smooth',
          width: 3,
        },
        colours: [this.simulationResults?.depletes ? ERROR_COLORS[500] : SUCCESS_COLORS[500]],
        xaxis: {
          categories: this.simulationResults?.ages || [],
          title: {
            text: 'Age',
            style: {
              fontSize: '14px',
              fontWeight: 600,
            },
          },
          labels: {
            style: {
              colors: TEXT_COLORS.muted,
            },
          },
        },
        yaxis: {
          title: {
            text: 'Portfolio Value (£)',
            style: {
              fontSize: '14px',
              fontWeight: 600,
            },
          },
          labels: {
            formatter: (value) => {
              return '£' + Math.round(value).toLocaleString();
            },
          },
        },
        tooltip: {
          y: {
            formatter: (value) => {
              return '£' + Math.round(value).toLocaleString();
            },
          },
        },
        grid: {
          borderColor: BORDER_COLORS.default,
        },
      };
    },
  },

  methods: {
    runSimulation() {
      const startAge = 67;
      const endAge = 95;
      let portfolioValue = this.simulatorData.initialPot;
      const portfolioValues = [portfolioValue];
      const ages = [startAge];
      let depletes = false;
      let depletionAge = null;

      for (let age = startAge + 1; age <= endAge; age++) {
        // Deduct annual withdrawal
        const withdrawal = portfolioValue * (this.simulatorData.withdrawalRate / 100);
        portfolioValue -= withdrawal;

        // Apply investment growth
        portfolioValue *= (1 + this.simulatorData.growthRate / 100);

        // Check if depleted
        if (portfolioValue <= 0 && !depletes) {
          depletes = true;
          depletionAge = age;
          portfolioValue = 0;
        }

        portfolioValues.push(Math.round(Math.max(0, portfolioValue)));
        ages.push(age);

        if (depletes && age > depletionAge + 2) {
          // Stop simulation a few years after depletion
          break;
        }
      }

      // Calculate real value loss due to inflation
      const years = endAge - startAge;
      const realValueLoss = Math.round((1 - Math.pow(1 + this.simulatorData.inflationRate / 100, -years)) * 100);

      this.simulationResults = {
        portfolioValues,
        ages,
        depletes,
        depletionAge,
        finalBalance: portfolioValues[portfolioValues.length - 1],
        realValueLoss,
      };
    },

    formatNumber(value) {
      return Math.round(value).toLocaleString();
    },
  },

  mounted() {
    this.runSimulation();
  },
};
</script>

