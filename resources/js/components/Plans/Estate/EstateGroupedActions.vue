<template>
  <div class="mb-6">
    <PlanSectionHeader
      title="Recommended Actions"
      :subtitle="enabledCountLabel"
      color="violet"
    />

    <template v-if="hasActions">
      <div class="mb-5">
        <div class="space-y-3">
          <div
            v-for="action in sortByPriority(actions)"
            :key="action.id"
            class="bg-white rounded-lg border p-4 transition-all duration-200"
            :class="action.enabled ? 'border-violet-200 bg-violet-50/30' : 'border-light-gray opacity-75'"
          >
            <!-- Action header with toggle -->
            <div class="flex items-start justify-between">
              <div class="flex-1 min-w-0 mr-4">
                <div class="flex items-center space-x-2 mb-1">
                  <span
                    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                    :class="priorityClasses(action.priority)"
                  >
                    {{ priorityLabel(action.priority) }}
                  </span>
                  <span class="text-xs text-neutral-500">{{ action.category }}</span>
                </div>
                <h4 class="text-sm font-semibold text-horizon-500">{{ action.title }}</h4>
                <p class="text-sm text-neutral-500 mt-1">{{ action.description }}</p>

                <!-- Estimated impact -->
                <p v-if="action.estimated_impact" class="text-xs text-spring-700 mt-1 font-medium">
                  Estimated impact: {{ formatCurrency(action.estimated_impact) }}
                </p>

                <!-- Affordability indicator for life cover -->
                <div v-if="action.affordability" class="mt-2">
                  <div
                    class="inline-flex items-center px-2.5 py-1 rounded text-xs font-medium"
                    :class="action.affordability.is_affordable
                      ? 'bg-spring-50 text-spring-700 border border-spring-200'
                      : 'bg-raspberry-50 text-raspberry-700 border border-raspberry-200'"
                  >
                    <svg class="w-3.5 h-3.5 mr-1" fill="currentColor" viewBox="0 0 20 20">
                      <path v-if="action.affordability.is_affordable" fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                      <path v-else fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                    {{ action.affordability.is_affordable ? 'Affordable' : 'May exceed budget' }}
                    <span class="ml-1 text-neutral-500">
                      ({{ formatCurrency(action.affordability.monthly_premium_estimate) }}/month)
                    </span>
                  </div>
                  <p v-if="action.affordability_warning" class="text-xs text-raspberry-600 mt-1">
                    {{ action.affordability_warning }}
                  </p>
                </div>

                <!-- Funding source for charitable/gifting -->
                <div v-if="action.funding_source" class="mt-2 p-2 bg-eggshell-500 rounded text-xs text-neutral-500">
                  <span class="font-medium text-horizon-500">Funding:</span>
                  {{ action.funding_source.note }}
                  <span v-if="action.funding_source.liquid_assets_available > 0" class="text-neutral-500 ml-1">
                    ({{ formatCurrency(action.funding_source.liquid_assets_available) }} liquid assets available)
                  </span>
                </div>

                <!-- PET Gifting Schedule -->
                <div v-if="action.category === 'pet_gifting' && action.gift_schedule && action.gift_schedule.length > 0" class="mt-3">
                  <button
                    class="text-xs font-medium text-raspberry-500 hover:text-raspberry-600 transition-colors duration-150 flex items-center"
                    @click="toggleSchedule(action.id)"
                  >
                    <svg
                      class="w-3.5 h-3.5 mr-1 transition-transform duration-200"
                      :class="{ 'rotate-90': expandedSchedule[action.id] }"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                    {{ expandedSchedule[action.id] ? 'Hide' : 'Show' }} year-by-year gifting schedule
                  </button>

                  <div v-if="expandedSchedule[action.id]" class="mt-2">
                    <div class="overflow-x-auto border border-light-gray rounded-lg">
                      <table class="min-w-full divide-y divide-light-gray">
                        <thead class="bg-eggshell-500">
                          <tr>
                            <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Year</th>
                            <th scope="col" class="px-3 py-2 text-right text-xs font-medium text-neutral-500 uppercase tracking-wider">Gift Amount</th>
                            <th scope="col" class="px-3 py-2 text-right text-xs font-medium text-neutral-500 uppercase tracking-wider">Inheritance Tax Reduction</th>
                            <th scope="col" class="px-3 py-2 text-right text-xs font-medium text-neutral-500 uppercase tracking-wider">Exempt After Year</th>
                          </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-light-gray">
                          <tr v-for="(entry, idx) in action.gift_schedule" :key="idx">
                            <td class="px-3 py-2 text-xs text-horizon-500">Year {{ entry.year + 1 }}</td>
                            <td class="px-3 py-2 text-xs text-horizon-500 text-right">{{ formatCurrency(entry.amount) }}</td>
                            <td class="px-3 py-2 text-xs text-spring-700 text-right">{{ formatCurrency(entry.iht_reduction) }}</td>
                            <td class="px-3 py-2 text-xs text-neutral-500 text-right">Year {{ entry.becomes_exempt }}</td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                    <p v-if="action.seven_year_cycles" class="text-xs text-neutral-500 mt-1.5">
                      {{ action.seven_year_cycles }} complete 7-year cycle{{ action.seven_year_cycles !== 1 ? 's' : '' }} of {{ formatCurrency(action.amount_per_cycle) }} each.
                    </p>
                  </div>
                </div>

                <!-- Annual Gifting Detail -->
                <div v-if="action.category === 'annual_gifting' && action.annual_gifting_detail" class="mt-3">
                  <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                    <div class="bg-eggshell-500 rounded p-2">
                      <p class="text-xs text-neutral-500">Annual Amount</p>
                      <p class="text-xs font-semibold text-horizon-500">{{ formatCurrency(action.annual_gifting_detail.annual_amount) }}</p>
                    </div>
                    <div class="bg-eggshell-500 rounded p-2">
                      <p class="text-xs text-neutral-500">Over</p>
                      <p class="text-xs font-semibold text-horizon-500">{{ action.annual_gifting_detail.years }} years</p>
                    </div>
                    <div class="bg-eggshell-500 rounded p-2">
                      <p class="text-xs text-neutral-500">Total Gifted</p>
                      <p class="text-xs font-semibold text-horizon-500">{{ formatCurrency(action.annual_gifting_detail.total_gifted) }}</p>
                    </div>
                    <div class="bg-eggshell-500 rounded p-2">
                      <p class="text-xs text-neutral-500">Inheritance Tax Saved</p>
                      <p class="text-xs font-semibold text-spring-700">{{ formatCurrency(action.annual_gifting_detail.iht_saved) }}</p>
                    </div>
                  </div>
                </div>

                <!-- Expandable guidance section -->
                <div v-if="action.guidance && action.guidance.steps && action.guidance.steps.length > 0" class="mt-3">
                  <button
                    class="text-xs font-medium text-raspberry-500 hover:text-raspberry-600 transition-colors duration-150 flex items-center"
                    @click="toggleGuidance(action.id)"
                  >
                    <svg
                      class="w-3.5 h-3.5 mr-1 transition-transform duration-200"
                      :class="{ 'rotate-90': expandedGuidance[action.id] }"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                    {{ expandedGuidance[action.id] ? 'Hide' : 'Show' }} step-by-step guidance
                  </button>

                  <div v-if="expandedGuidance[action.id]" class="mt-2 pl-1">
                    <ol class="list-decimal list-inside space-y-1.5 text-xs text-neutral-500">
                      <li v-for="(step, idx) in action.guidance.steps" :key="idx">
                        {{ step }}
                      </li>
                    </ol>
                    <div class="mt-2 flex flex-wrap gap-3 text-xs">
                      <span v-if="action.guidance.timeframe" class="text-neutral-500">
                        <span class="font-medium text-horizon-500">Timeframe:</span> {{ action.guidance.timeframe }}
                      </span>
                      <span v-if="action.guidance.professional_advice" class="text-neutral-500">
                        <span class="font-medium text-horizon-500">Advice:</span> {{ action.guidance.professional_advice }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Toggle switch -->
              <div class="flex-shrink-0">
                <button
                  class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2"
                  :class="action.enabled ? 'bg-raspberry-500' : 'bg-horizon-300'"
                  role="switch"
                  :aria-checked="action.enabled"
                  :aria-label="`${action.enabled ? 'Disable' : 'Enable'} action: ${action.title}`"
                  @click="$emit('toggle', action.id)"
                >
                  <span
                    class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                    :class="action.enabled ? 'translate-x-6' : 'translate-x-1'"
                  />
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- What-if comparison -->
        <div v-if="hasWhatIfData" class="bg-white rounded-lg border border-light-gray p-4 mt-3">
          <div class="grid grid-cols-2 divide-x divide-light-gray">
            <div class="pr-4">
              <h5 class="text-xs font-semibold text-neutral-500 uppercase tracking-wider mb-3">Current Position</h5>
              <EstateWhatIfControls :scenario="whatIf.current_scenario" />
            </div>
            <div class="pl-4">
              <h5 class="text-xs font-semibold text-raspberry-700 uppercase tracking-wider mb-3">With Actions</h5>
              <EstateWhatIfControls :scenario="projectedScenario" show-savings />
            </div>
          </div>
        </div>
      </div>
    </template>

    <div v-else class="bg-eggshell-500 rounded-lg border border-light-gray p-6 text-center">
      <p class="text-neutral-500 text-sm">No recommendations available for this plan.</p>
    </div>
  </div>
</template>

<script>
import PlanSectionHeader from '@/components/Plans/Shared/PlanSectionHeader.vue';
import EstateWhatIfControls from './EstateWhatIfControls.vue';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'EstateGroupedActions',

  mixins: [currencyMixin],

  components: {
    PlanSectionHeader,
    EstateWhatIfControls,
  },

  props: {
    actions: {
      type: Array,
      default: () => [],
    },
    whatIf: {
      type: Object,
      default: null,
    },
  },

  emits: ['toggle'],

  data() {
    return {
      expandedGuidance: {},
      expandedSchedule: {},
    };
  },

  computed: {
    hasActions() {
      return this.actions && this.actions.length > 0;
    },

    enabledCount() {
      return this.actions.filter(a => a.enabled).length;
    },

    enabledCountLabel() {
      return `${this.enabledCount} of ${this.actions.length} actions enabled`;
    },

    hasWhatIfData() {
      return this.whatIf
        && this.whatIf.current_scenario
        && this.whatIf.projected_scenario;
    },

    projectedScenario() {
      if (!this.hasWhatIfData || !this.whatIf.frontend_calc_params) {
        return this.whatIf?.projected_scenario || {};
      }

      const params = this.whatIf.frontend_calc_params;
      const savingsMap = params.savings_map || {};
      const grossEstate = params.gross_estate || 0;
      const netEstate = params.net_estate || 0;
      const currentLiability = params.current_iht_liability || 0;

      // Sum savings from enabled actions
      let totalSavings = 0;
      this.actions.filter(a => a.enabled).forEach(action => {
        totalSavings += savingsMap[action.id] || action.estimated_impact || 0;
      });

      const projectedLiability = Math.max(0, currentLiability - totalSavings);
      const projectedRate = grossEstate > 0 ? (projectedLiability / grossEstate) * 100 : 0;
      const projectedToBeneficiaries = Math.max(0, netEstate - projectedLiability);

      return {
        iht_liability: projectedLiability,
        effective_tax_rate: Math.round(projectedRate * 10) / 10,
        estate_to_beneficiaries: projectedToBeneficiaries,
        total_mitigation_savings: totalSavings,
      };
    },
  },

  methods: {
    sortByPriority(actions) {
      const priorityOrder = { critical: 0, high: 1, medium: 2, low: 3 };
      return [...actions].sort((a, b) => {
        return (priorityOrder[a.priority] ?? 2) - (priorityOrder[b.priority] ?? 2);
      });
    },

    priorityLabel(priority) {
      const labels = { critical: 'Critical', high: 'High', medium: 'Medium', low: 'Low' };
      return labels[priority] || 'Medium';
    },

    priorityClasses(priority) {
      const map = {
        critical: 'bg-raspberry-100 text-raspberry-800',
        high: 'bg-violet-100 text-violet-800',
        medium: 'bg-savannah-100 text-horizon-500',
        low: 'bg-spring-100 text-spring-800',
      };
      return map[priority] || map.medium;
    },

    toggleGuidance(actionId) {
      this.expandedGuidance = {
        ...this.expandedGuidance,
        [actionId]: !this.expandedGuidance[actionId],
      };
    },

    toggleSchedule(actionId) {
      this.expandedSchedule = {
        ...this.expandedSchedule,
        [actionId]: !this.expandedSchedule[actionId],
      };
    },
  },
};
</script>
