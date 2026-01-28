<template>
  <div class="scenario-builder bg-white rounded-lg border border-gray-200 p-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Build a What-If Scenario</h3>

    <!-- Scenario Type Selection -->
    <div class="mb-6">
      <label class="block text-sm font-medium text-gray-700 mb-2">
        Scenario Type
      </label>
      <select
        v-model="selectedScenario"
        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
        @change="loadScenarioTemplate"
      >
        <option value="">Select a scenario...</option>
        <option value="death">Death Benefit Scenario</option>
        <option value="critical_illness">Critical Illness Scenario</option>
        <option value="disability">Disability Income Scenario</option>
        <option value="custom">Custom Scenario</option>
      </select>
    </div>

    <!-- Scenario Configuration -->
    <div v-if="selectedScenario" class="space-y-6">
      <!-- Scenario Description -->
      <div class="p-4 bg-blue-50 rounded-lg">
        <p class="text-sm text-blue-900">
          {{ scenarioDescription }}
        </p>
      </div>

      <!-- Additional Coverage Amount -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">
          Additional Coverage Amount
        </label>
        <div class="relative">
          <span class="absolute left-3 top-2.5 text-gray-500">£</span>
          <input
            v-model.number="additionalCoverage"
            type="number"
            step="1000"
            class="w-full pl-8 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            placeholder="0"
          />
        </div>
        <div class="mt-2">
          <label class="text-xs text-gray-600">Quick amounts:</label>
          <div class="flex gap-2 mt-1">
            <button
              v-for="amount in quickAmounts"
              :key="amount"
              @click="additionalCoverage = amount"
              class="px-3 py-1 text-xs bg-gray-100 hover:bg-gray-200 rounded-md transition-colors"
            >
              £{{ formatNumber(amount) }}
            </button>
          </div>
        </div>
      </div>

      <!-- Term Length (for death and critical illness scenarios) -->
      <div v-if="selectedScenario === 'death' || selectedScenario === 'critical_illness'">
        <label class="block text-sm font-medium text-gray-700 mb-2">
          Policy Term (years)
        </label>
        <input
          v-model.number="termYears"
          type="range"
          min="5"
          max="40"
          step="5"
          class="w-full"
        />
        <div class="flex justify-between text-sm text-gray-600 mt-1">
          <span>5 years</span>
          <span class="font-semibold text-gray-900">{{ termYears }} years</span>
          <span>40 years</span>
        </div>
      </div>

      <!-- Benefit Period (for disability scenarios) -->
      <div v-if="selectedScenario === 'disability'">
        <label class="block text-sm font-medium text-gray-700 mb-2">
          Benefit Period (months)
        </label>
        <select
          v-model.number="benefitPeriod"
          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
        >
          <option :value="12">12 months</option>
          <option :value="24">24 months</option>
          <option :value="60">5 years (60 months)</option>
          <option :value="120">10 years (120 months)</option>
          <option :value="999">Until retirement</option>
        </select>
      </div>

      <!-- Estimated Premium -->
      <div class="p-4 bg-gray-50 rounded-lg">
        <div class="flex justify-between items-center">
          <span class="text-sm font-medium text-gray-700">Estimated Additional Premium:</span>
          <span class="text-lg font-bold text-gray-900">
            £{{ estimatedPremium.toFixed(2)}} <span class="text-sm font-normal">/month</span>
          </span>
        </div>
        <p class="text-xs text-gray-500 mt-1">
          This is a rough estimate. Actual premiums depend on age, health, and underwriting.
        </p>
      </div>

      <!-- Action Buttons -->
      <div class="flex gap-3">
        <button
          @click="runScenario"
          :disabled="!canRunScenario"
          class="flex-1 px-6 py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 disabled:bg-gray-300 disabled:cursor-not-allowed transition-colors"
        >
          Run Scenario
        </button>
        <button
          @click="resetScenario"
          class="px-6 py-3 bg-gray-100 text-gray-700 font-medium rounded-lg hover:bg-gray-200 transition-colors"
        >
          Reset
        </button>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'ScenarioBuilder',

  data() {
    return {
      selectedScenario: '',
      additionalCoverage: 0,
      termYears: 20,
      benefitPeriod: 60,
      quickAmounts: [50000, 100000, 250000, 500000],
    };
  },

  computed: {
    scenarioDescription() {
      const descriptions = {
        death: 'Analyse the impact of increasing your life insurance coverage to better protect your family in the event of your death.',
        critical_illness: 'Assess how additional critical illness coverage would help you maintain financial stability if diagnosed with a serious illness.',
        disability: 'Evaluate the benefit of enhanced disability income protection to replace lost earnings if you cannot work.',
        custom: 'Create a custom scenario by adjusting coverage amounts and terms to match your specific needs.',
      };
      return descriptions[this.selectedScenario] || '';
    },

    estimatedPremium() {
      // Simple estimation formula (in reality, this would call an API)
      // Base rate per £1000 of coverage varies by scenario type
      const ratesPerThousand = {
        death: 0.05,
        critical_illness: 0.08,
        disability: 0.10,
        custom: 0.07,
      };

      const rate = ratesPerThousand[this.selectedScenario] || 0.07;
      let premium = (this.additionalCoverage / 1000) * rate;

      // Adjust for term length
      if (this.termYears < 10) {
        premium *= 0.9; // Shorter term, slightly lower premium
      } else if (this.termYears > 30) {
        premium *= 1.2; // Longer term, higher premium
      }

      return premium;
    },

    canRunScenario() {
      return this.selectedScenario && this.additionalCoverage > 0;
    },
  },

  methods: {
    loadScenarioTemplate() {
      // Reset values when scenario type changes
      this.additionalCoverage = 0;
      this.termYears = 20;
      this.benefitPeriod = 60;

      // Load default values based on scenario type
      if (this.selectedScenario === 'death') {
        this.additionalCoverage = 100000;
      } else if (this.selectedScenario === 'critical_illness') {
        this.additionalCoverage = 50000;
      } else if (this.selectedScenario === 'disability') {
        this.additionalCoverage = 30000;
      }
    },

    runScenario() {
      const scenarioData = {
        type: this.selectedScenario,
        additionalCoverage: this.additionalCoverage,
        additionalPremium: this.estimatedPremium,
        termYears: this.termYears,
        benefitPeriod: this.benefitPeriod,
      };

      this.$emit('scenario-run', scenarioData);
    },

    resetScenario() {
      this.selectedScenario = '';
      this.additionalCoverage = 0;
      this.termYears = 20;
      this.benefitPeriod = 60;
    },

    formatNumber(num) {
      return new Intl.NumberFormat('en-GB').format(num);
    },
  },
};
</script>

<style scoped>
/* Range slider styling */
input[type="range"] {
  -webkit-appearance: none;
  appearance: none;
  height: 6px;
  border-radius: 3px;
  @apply bg-gray-200;
  outline: none;
}

input[type="range"]::-webkit-slider-thumb {
  -webkit-appearance: none;
  appearance: none;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  @apply bg-blue-500;
  cursor: pointer;
}

input[type="range"]::-moz-range-thumb {
  width: 18px;
  height: 18px;
  border-radius: 50%;
  @apply bg-blue-500;
  cursor: pointer;
  border: none;
}

/* Mobile responsive */
@media (max-width: 640px) {
  .scenario-builder .flex.gap-3 {
    flex-direction: column;
  }
}
</style>
