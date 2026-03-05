<template>
  <div class="bg-white rounded-lg border border-light-gray p-6">
    <h3 class="text-lg font-semibold text-horizon-500 mb-4">Life Cover Recommendations</h3>

    <!-- Recommendation Summary -->
    <div v-if="recommendations.recommendation" class="bg-violet-50 border border-violet-200 p-4 mb-6">
      <div class="flex items-start">
        <svg class="h-5 w-5 text-violet-400 mt-0.5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
          <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
        </svg>
        <div class="ml-3">
          <h4 class="text-sm font-medium text-violet-800">Recommended Approach</h4>
          <p class="text-sm text-violet-700 mt-1">
            {{ recommendations.recommendation.recommended_approach }}
          </p>
          <p class="text-xs text-violet-600 mt-2">
            {{ recommendations.recommendation.summary }}
          </p>
        </div>
      </div>
    </div>

    <!-- Policy Type Info -->
    <div class="mb-4 p-3 bg-eggshell-500 rounded text-sm">
      <p class="text-neutral-500">
        <strong>Policy Type:</strong> {{ recommendations.is_joint_policy ? 'Joint Life Second Death' : 'Whole of Life' }}
        <span class="ml-2 text-neutral-500">
          ({{ recommendations.user_age }} {{ recommendations.spouse_age ? `& ${recommendations.spouse_age}` : '' }} years old)
        </span>
      </p>
      <p class="text-xs text-neutral-500 mt-1">
        {{ recommendations.is_joint_policy ?
          'Joint life policies pay out on second death and have lower premiums than single life policies' :
          'Whole of life policy provides guaranteed payout on death'
        }}
      </p>
    </div>

    <!-- Scenario Tabs -->
    <div class="border-b border-light-gray mb-6">
      <nav class="-mb-px flex space-x-8">
        <button
          v-for="(scenario, key) in scenarios"
          :key="key"
          @click="activeScenario = key"
          class="py-2 px-1 border-b-2 font-medium text-sm transition"
          :class="activeScenario === key
            ? 'border-violet-500 text-violet-600'
            : 'border-transparent text-neutral-500 hover:text-neutral-500 hover:border-horizon-300'"
        >
          {{ getScenarioLabel(key) }}
        </button>
      </nav>
    </div>

    <!-- Scenario Content -->
    <div v-if="currentScenario">
      <!-- Scenario Header -->
      <div class="mb-6">
        <h4 class="text-base font-semibold text-horizon-500">{{ currentScenario.scenario_name }}</h4>
        <p class="text-sm text-neutral-500 mt-1">{{ currentScenario.description }}</p>
      </div>

      <!-- Scenario Metrics -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-violet-50 rounded-lg p-4">
          <p class="text-xs text-violet-500 font-medium">Cover Amount</p>
          <p class="text-2xl font-bold text-violet-500 mt-1">
            {{ formatCurrency(currentScenario.cover_amount || currentScenario.target_amount) }}
          </p>
        </div>

        <div class="bg-violet-50 rounded-lg p-4">
          <p class="text-xs text-violet-600 font-medium">Annual Premium</p>
          <p class="text-2xl font-bold text-violet-900 mt-1">
            {{ formatCurrency(currentScenario.annual_premium || currentScenario.annual_investment) }}
          </p>
          <p class="text-xs text-violet-500 mt-1">
            {{ formatCurrency((currentScenario.annual_premium || currentScenario.annual_investment) / 12) }}/month
          </p>
        </div>

        <div class="bg-violet-50 rounded-lg p-4">
          <p class="text-xs text-violet-600 font-medium">Total {{ activeScenario === 'self_insurance' ? 'Invested' : 'Premiums' }}</p>
          <p class="text-2xl font-bold text-violet-900 mt-1">
            {{ formatCurrency(currentScenario.total_premiums_paid || currentScenario.total_invested) }}
          </p>
          <p class="text-xs text-violet-500 mt-1">
            Over {{ currentScenario.term_years || currentScenario.investment_term_years }} years
          </p>
        </div>

        <div class="bg-spring-50 rounded-lg p-4">
          <p class="text-xs text-spring-600 font-medium">Cost-Benefit Ratio</p>
          <p class="text-2xl font-bold text-spring-900 mt-1">
            {{ currentScenario.cost_benefit_ratio ? currentScenario.cost_benefit_ratio.toFixed(1) : '0.0' }}:1
          </p>
          <p class="text-xs text-spring-500 mt-1">
            Payout vs {{ activeScenario === 'self_insurance' ? 'invested' : 'premiums' }}
          </p>
        </div>
      </div>

      <!-- Self-Insurance Specific Content -->
      <div v-if="activeScenario === 'self_insurance' && currentScenario.projected_value_at_death">
        <div class="bg-violet-50 rounded-lg p-4 mb-4">
          <h5 class="text-sm font-medium text-violet-900 mb-3">Investment Projection ({{ (currentScenario.assumed_return_rate * 100).toFixed(1) }}% return)</h5>
          <div class="grid grid-cols-3 gap-4 text-sm">
            <div>
              <p class="text-violet-600">Total Invested</p>
              <p class="font-bold text-violet-900">{{ formatCurrency(currentScenario.total_invested) }}</p>
            </div>
            <div>
              <p class="text-violet-600">Investment Growth</p>
              <p class="font-bold text-violet-900">{{ formatCurrency(currentScenario.investment_growth) }}</p>
            </div>
            <div>
              <p class="text-violet-600">Projected Value</p>
              <p class="font-bold text-violet-900">{{ formatCurrency(currentScenario.projected_value_at_death) }}</p>
            </div>
          </div>

          <!-- Coverage assessment -->
          <div class="mt-3 pt-3 border-t border-violet-200">
            <div class="flex justify-between items-center">
              <span class="text-sm text-violet-700">Coverage of Inheritance Tax liability:</span>
              <span
                class="font-bold text-lg"
                :class="currentScenario.coverage_percentage >= 100 ? 'text-spring-600' : 'text-violet-600'"
              >
                {{ currentScenario.coverage_percentage.toFixed(1) }}%
              </span>
            </div>

            <div v-if="currentScenario.shortfall > 0" class="mt-2 text-xs text-violet-700">
              <strong>Note:</strong> Shortfall of {{ formatCurrency(currentScenario.shortfall) }} - consider hybrid approach
            </div>
            <div v-else class="mt-2 text-xs text-spring-700">
              <strong>✓</strong> Self-insurance appears viable with surplus of {{ formatCurrency(currentScenario.surplus) }}
            </div>
          </div>
        </div>

        <!-- Pros and Cons -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
          <div class="border border-spring-200 rounded-lg p-4">
            <h6 class="text-sm font-semibold text-spring-900 mb-2">Pros</h6>
            <ul class="space-y-1">
              <li v-for="(pro, index) in currentScenario.pros" :key="index" class="text-xs text-spring-700 flex items-start">
                <span class="text-spring-500 mr-2">✓</span>
                <span>{{ pro }}</span>
              </li>
            </ul>
          </div>
          <div class="border border-raspberry-200 rounded-lg p-4">
            <h6 class="text-sm font-semibold text-raspberry-900 mb-2">Cons</h6>
            <ul class="space-y-1">
              <li v-for="(con, index) in currentScenario.cons" :key="index" class="text-xs text-raspberry-700 flex items-start">
                <span class="text-raspberry-500 mr-2">✗</span>
                <span>{{ con }}</span>
              </li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Implementation Steps -->
      <div class="border-t border-light-gray pt-4">
        <h5 class="text-sm font-medium text-horizon-500 mb-2">Implementation Steps:</h5>
        <ul class="space-y-2">
          <li
            v-for="(step, index) in currentScenario.implementation"
            :key="index"
            class="text-sm text-neutral-500 flex items-start"
          >
            <span class="text-violet-600 mr-2 mt-1">{{ index + 1 }}.</span>
            <span>{{ step }}</span>
          </li>
        </ul>
      </div>
    </div>

    <!-- Comparison Table -->
    <div class="mt-8 pt-8 border-t border-light-gray">
      <h4 class="text-base font-semibold text-horizon-500 mb-4">Scenario Comparison</h4>

      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-light-gray">
          <thead class="bg-eggshell-500">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Scenario</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-neutral-500 uppercase">Cover Amount</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-neutral-500 uppercase">Annual Premium</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-neutral-500 uppercase">Total Cost</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-neutral-500 uppercase">Cost-Benefit</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-light-gray">
            <tr v-for="(scenario, key) in scenarios" :key="key">
              <td class="px-4 py-3 text-sm font-medium text-horizon-500">
                {{ scenario.scenario_name }}
              </td>
              <td class="px-4 py-3 text-sm text-right text-neutral-500">
                {{ formatCurrency(scenario.cover_amount || scenario.target_amount) }}
              </td>
              <td class="px-4 py-3 text-sm text-right text-neutral-500">
                {{ formatCurrency(scenario.annual_premium || scenario.annual_investment) }}
              </td>
              <td class="px-4 py-3 text-sm text-right text-neutral-500">
                {{ formatCurrency(scenario.total_premiums_paid || scenario.total_invested) }}
              </td>
              <td class="px-4 py-3 text-sm text-right font-medium">
                <span :class="(scenario.cost_benefit_ratio || 0) >= 2 ? 'text-spring-600' : 'text-violet-600'">
                  {{ (scenario.cost_benefit_ratio || 0).toFixed(1) }}:1
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'LifeCoverRecommendations',
  mixins: [currencyMixin],

  props: {
    recommendations: {
      type: Object,
      required: true,
    },
    ihtLiability: {
      type: Number,
      required: true,
    },
  },

  data() {
    return {
      activeScenario: 'cover_less_gifting', // Default to recommended option
    };
  },

  computed: {
    scenarios() {
      return this.recommendations.scenarios || {};
    },

    currentScenario() {
      return this.scenarios[this.activeScenario];
    },
  },

  methods: {
    getScenarioLabel(key) {
      const labels = {
        full_cover: 'Full Cover',
        cover_less_gifting: 'Cover Less Gifting',
        self_insurance: 'Self-Insurance',
      };
      return labels[key] || key;
    },
  },
};
</script>
