<template>
  <div class="what-if-scenarios">
    <div class="mb-6">
      <h2 class="text-2xl font-bold text-horizon-500">What-If Scenarios</h2>
      <p class="text-neutral-500 mt-1">Explore different retirement scenarios and their impact</p>
    </div>

    <!-- Scenario Builder -->
    <div class="bg-white rounded-lg shadow p-6 mb-8">
      <h3 class="text-lg font-semibold text-horizon-500 mb-6">Scenario Builder</h3>

      <div class="space-y-6">
        <!-- Retirement Age Adjustment -->
        <div>
          <label class="block text-sm font-medium text-neutral-500 mb-2">
            Retirement Age: {{ scenarioData.retirementAge }}
          </label>
          <input
            v-model.number="scenarioData.retirementAge"
            type="range"
            min="55"
            max="75"
            step="1"
            class="w-full h-2 bg-savannah-200 rounded-lg appearance-none cursor-pointer"
            @input="calculateScenario"
          />
          <div class="flex items-center justify-between text-xs text-neutral-500 mt-1">
            <span>55</span>
            <span>75</span>
          </div>
        </div>

        <!-- Additional Contributions -->
        <div>
          <label class="block text-sm font-medium text-neutral-500 mb-2">
            Extra Monthly Contributions: {{ formatCurrency(scenarioData.extraContributions) }}
          </label>
          <input
            v-model.number="scenarioData.extraContributions"
            type="range"
            min="0"
            max="2000"
            step="50"
            class="w-full h-2 bg-savannah-200 rounded-lg appearance-none cursor-pointer"
            @input="calculateScenario"
          />
          <div class="flex items-center justify-between text-xs text-neutral-500 mt-1">
            <span>£0</span>
            <span>£2,000</span>
          </div>
        </div>

        <!-- Investment Return Rate -->
        <div>
          <label class="block text-sm font-medium text-neutral-500 mb-2">
            Expected Return Rate: {{ scenarioData.returnRate }}% p.a.
          </label>
          <input
            v-model.number="scenarioData.returnRate"
            type="range"
            min="0"
            max="10"
            step="0.5"
            class="w-full h-2 bg-savannah-200 rounded-lg appearance-none cursor-pointer"
            @input="calculateScenario"
          />
          <div class="flex items-center justify-between text-xs text-neutral-500 mt-1">
            <span>0%</span>
            <span>10%</span>
          </div>
        </div>

        <!-- Calculate Button -->
        <button
          @click="calculateScenario"
          class="w-full bg-violet-600 hover:bg-violet-700 text-white px-6 py-3 rounded-lg font-medium transition-colors duration-200"
        >
          Calculate Scenario
        </button>
      </div>
    </div>

    <!-- Results Comparison -->
    <div v-if="scenarioResults" class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <!-- Current Plan -->
      <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between mb-4">
          <h4 class="text-lg font-semibold text-horizon-500">Current Plan</h4>
          <span class="px-3 py-1 bg-savannah-100 text-neutral-500 rounded-full text-sm font-medium">Baseline</span>
        </div>

        <div class="space-y-4">
          <div class="flex items-center justify-between p-3 bg-savannah-100 rounded-lg">
            <span class="text-sm text-neutral-500">Retirement Age</span>
            <span class="font-semibold text-horizon-500">{{ baseline.retirementAge }}</span>
          </div>
          <div class="flex items-center justify-between p-3 bg-savannah-100 rounded-lg">
            <span class="text-sm text-neutral-500">Projected Income</span>
            <span class="font-semibold text-horizon-500">{{ formatCurrency(baseline.income) }}/year</span>
          </div>
          <div class="flex items-center justify-between p-3 bg-savannah-100 rounded-lg">
            <span class="text-sm text-neutral-500">Pension Pot</span>
            <span class="font-semibold text-horizon-500">{{ formatCurrency(baseline.pot) }}</span>
          </div>
        </div>
      </div>

      <!-- Scenario Results -->
      <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between mb-4">
          <h4 class="text-lg font-semibold text-horizon-500">Scenario Result</h4>
          <span class="px-3 py-1 bg-violet-100 text-violet-700 rounded-full text-sm font-medium">Modified</span>
        </div>

        <div class="space-y-4">
          <div class="flex items-center justify-between p-3 bg-violet-50 rounded-lg">
            <span class="text-sm text-neutral-500">Retirement Age</span>
            <div class="text-right">
              <span class="font-semibold text-horizon-500">{{ scenarioResults.retirementAge }}</span>
              <span
                v-if="scenarioResults.retirementAge !== baseline.retirementAge"
                :class="['text-xs ml-2', scenarioResults.retirementAge > baseline.retirementAge ? 'text-raspberry-600' : 'text-spring-600']"
              >
                {{ scenarioResults.retirementAge > baseline.retirementAge ? '+' : '' }}{{ scenarioResults.retirementAge - baseline.retirementAge }}
              </span>
            </div>
          </div>
          <div class="flex items-center justify-between p-3 bg-violet-50 rounded-lg">
            <span class="text-sm text-neutral-500">Projected Income</span>
            <div class="text-right">
              <span class="font-semibold text-horizon-500">{{ formatCurrency(scenarioResults.income) }}/year</span>
              <span
                v-if="scenarioResults.income !== baseline.income"
                :class="['text-xs ml-2', scenarioResults.income > baseline.income ? 'text-spring-600' : 'text-raspberry-600']"
              >
                {{ scenarioResults.income > baseline.income ? '+' : '' }}{{ formatCurrency(scenarioResults.income - baseline.income) }}
              </span>
            </div>
          </div>
          <div class="flex items-center justify-between p-3 bg-violet-50 rounded-lg">
            <span class="text-sm text-neutral-500">Pension Pot</span>
            <div class="text-right">
              <span class="font-semibold text-horizon-500">{{ formatCurrency(scenarioResults.pot) }}</span>
              <span
                v-if="scenarioResults.pot !== baseline.pot"
                :class="['text-xs ml-2', scenarioResults.pot > baseline.pot ? 'text-spring-600' : 'text-raspberry-600']"
              >
                {{ scenarioResults.pot > baseline.pot ? '+' : '' }}{{ formatCurrency(scenarioResults.pot - baseline.pot) }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { mapState, mapGetters } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'WhatIfScenarios',
  mixins: [currencyMixin],

  data() {
    return {
      scenarioData: {
        retirementAge: 67,
        extraContributions: 0,
        returnRate: 5.0,
      },
      scenarioResults: null,
    };
  },

  computed: {
    ...mapState('retirement', ['profile']),
    ...mapGetters('retirement', ['totalPensionWealth', 'projectedIncome']),

    baseline() {
      return {
        retirementAge: this.profile?.target_retirement_age || 67,
        income: this.projectedIncome,
        pot: this.totalPensionWealth,
      };
    },
  },

  methods: {
    async calculateScenario() {
      // Simplified calculation for demonstration
      // In a real app, this would call the backend API
      const yearsToRetirement = Math.max(0, this.scenarioData.retirementAge - (this.profile?.current_age || 40));
      const currentPot = this.totalPensionWealth;
      const extraAnnual = this.scenarioData.extraContributions * 12;
      const rate = this.scenarioData.returnRate / 100;

      // Calculate projected pot with extra contributions
      let projectedPot = currentPot;
      for (let year = 0; year < yearsToRetirement; year++) {
        projectedPot = (projectedPot + extraAnnual) * (1 + rate);
      }

      // Estimate income using 4% rule
      const projectedIncome = Math.round(projectedPot * 0.04);

      this.scenarioResults = {
        retirementAge: this.scenarioData.retirementAge,
        income: projectedIncome,
        pot: Math.round(projectedPot),
      };
    },

  },

  mounted() {
    // Initialize with current values
    if (this.profile) {
      this.scenarioData.retirementAge = this.profile.target_retirement_age || 67;
    }
    this.calculateScenario();
  },
};
</script>

<style scoped>
/* Slider styling */
input[type="range"]::-webkit-slider-thumb {
  @apply appearance-none w-5 h-5 bg-raspberry-500 rounded-full cursor-pointer;
}

input[type="range"]::-moz-range-thumb {
  @apply w-5 h-5 bg-raspberry-500 rounded-full border-none cursor-pointer;
}
</style>
