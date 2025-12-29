<template>
  <div class="what-if-scenarios">
    <div class="mb-6">
      <h2 class="text-2xl font-bold text-gray-900">What-If Scenarios</h2>
      <p class="text-gray-600 mt-1">Explore different retirement scenarios and their impact</p>
    </div>

    <!-- Scenario Builder -->
    <div class="bg-white rounded-lg shadow p-6 mb-8">
      <h3 class="text-lg font-semibold text-gray-900 mb-6">Scenario Builder</h3>

      <div class="space-y-6">
        <!-- Retirement Age Adjustment -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Retirement Age: {{ scenarioData.retirementAge }}
          </label>
          <input
            v-model.number="scenarioData.retirementAge"
            type="range"
            min="55"
            max="75"
            step="1"
            class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer"
            @input="calculateScenario"
          />
          <div class="flex items-center justify-between text-xs text-gray-500 mt-1">
            <span>55</span>
            <span>75</span>
          </div>
        </div>

        <!-- Additional Contributions -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Extra Monthly Contributions: {{ formatCurrency(scenarioData.extraContributions) }}
          </label>
          <input
            v-model.number="scenarioData.extraContributions"
            type="range"
            min="0"
            max="2000"
            step="50"
            class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer"
            @input="calculateScenario"
          />
          <div class="flex items-center justify-between text-xs text-gray-500 mt-1">
            <span>£0</span>
            <span>£2,000</span>
          </div>
        </div>

        <!-- Investment Return Rate -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Expected Return Rate: {{ scenarioData.returnRate }}% p.a.
          </label>
          <input
            v-model.number="scenarioData.returnRate"
            type="range"
            min="0"
            max="10"
            step="0.5"
            class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer"
            @input="calculateScenario"
          />
          <div class="flex items-center justify-between text-xs text-gray-500 mt-1">
            <span>0%</span>
            <span>10%</span>
          </div>
        </div>

        <!-- Calculate Button -->
        <button
          @click="calculateScenario"
          class="w-full bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-3 rounded-lg font-medium transition-colors duration-200"
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
          <h4 class="text-lg font-semibold text-gray-900">Current Plan</h4>
          <span class="px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-sm font-medium">Baseline</span>
        </div>

        <div class="space-y-4">
          <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
            <span class="text-sm text-gray-600">Retirement Age</span>
            <span class="font-semibold text-gray-900">{{ baseline.retirementAge }}</span>
          </div>
          <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
            <span class="text-sm text-gray-600">Projected Income</span>
            <span class="font-semibold text-gray-900">{{ formatCurrency(baseline.income) }}/year</span>
          </div>
          <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
            <span class="text-sm text-gray-600">Pension Pot</span>
            <span class="font-semibold text-gray-900">{{ formatCurrency(baseline.pot) }}</span>
          </div>
        </div>
      </div>

      <!-- Scenario Results -->
      <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between mb-4">
          <h4 class="text-lg font-semibold text-gray-900">Scenario Result</h4>
          <span class="px-3 py-1 bg-indigo-100 text-indigo-700 rounded-full text-sm font-medium">Modified</span>
        </div>

        <div class="space-y-4">
          <div class="flex items-center justify-between p-3 bg-indigo-50 rounded-lg">
            <span class="text-sm text-gray-600">Retirement Age</span>
            <div class="text-right">
              <span class="font-semibold text-gray-900">{{ scenarioResults.retirementAge }}</span>
              <span
                v-if="scenarioResults.retirementAge !== baseline.retirementAge"
                :class="['text-xs ml-2', scenarioResults.retirementAge > baseline.retirementAge ? 'text-red-600' : 'text-green-600']"
              >
                {{ scenarioResults.retirementAge > baseline.retirementAge ? '+' : '' }}{{ scenarioResults.retirementAge - baseline.retirementAge }}
              </span>
            </div>
          </div>
          <div class="flex items-center justify-between p-3 bg-indigo-50 rounded-lg">
            <span class="text-sm text-gray-600">Projected Income</span>
            <div class="text-right">
              <span class="font-semibold text-gray-900">{{ formatCurrency(scenarioResults.income) }}/year</span>
              <span
                v-if="scenarioResults.income !== baseline.income"
                :class="['text-xs ml-2', scenarioResults.income > baseline.income ? 'text-green-600' : 'text-red-600']"
              >
                {{ scenarioResults.income > baseline.income ? '+' : '' }}{{ formatCurrency(scenarioResults.income - baseline.income) }}
              </span>
            </div>
          </div>
          <div class="flex items-center justify-between p-3 bg-indigo-50 rounded-lg">
            <span class="text-sm text-gray-600">Pension Pot</span>
            <div class="text-right">
              <span class="font-semibold text-gray-900">{{ formatCurrency(scenarioResults.pot) }}</span>
              <span
                v-if="scenarioResults.pot !== baseline.pot"
                :class="['text-xs ml-2', scenarioResults.pot > baseline.pot ? 'text-green-600' : 'text-red-600']"
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
  appearance: none;
  width: 20px;
  height: 20px;
  background: #4f46e5;
  cursor: pointer;
  border-radius: 50%;
}

input[type="range"]::-moz-range-thumb {
  width: 20px;
  height: 20px;
  background: #4f46e5;
  cursor: pointer;
  border-radius: 50%;
  border: none;
}
</style>
