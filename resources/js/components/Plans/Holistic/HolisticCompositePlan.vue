<template>
  <div class="mb-6">
    <PlanSectionHeader
      title="Your Cross-Module Plan"
      subtitle="Every recommended action across your modules, ranked against the monthly surplus you have to put to work"
      color="horizon"
    />

    <div class="bg-white rounded-lg shadow-sm border border-light-gray p-6">
      <!-- Surplus overview -->
      <div class="mb-6 space-y-2">
        <div class="flex items-center justify-between">
          <span class="text-sm font-medium text-horizon-500">Monthly surplus available</span>
          <span class="text-sm font-bold text-horizon-500">{{ formatCurrency(availableSurplus) }}</span>
        </div>
        <div v-if="goalCommitments > 0" class="flex items-center justify-between">
          <span class="text-sm font-medium text-horizon-500">Already committed to your goals</span>
          <span class="text-sm font-semibold text-neutral-500">&minus;{{ formatCurrency(goalCommitments) }}</span>
        </div>
        <div class="flex items-center justify-between border-t border-light-gray pt-2">
          <span class="text-sm font-semibold text-horizon-500">Left to put to work</span>
          <span class="text-sm font-bold" :class="effectiveSurplus > 0 ? 'text-spring-700' : 'text-horizon-400'">
            {{ formatCurrency(effectiveSurplus) }}
          </span>
        </div>
        <p v-if="nearTermCapital !== 0" class="text-xs text-neutral-500 pt-1">
          {{ nearTermCapitalText }}
        </p>
      </div>

      <!-- Ranked actions -->
      <div v-if="items.length" class="space-y-2">
        <div
          v-for="(item, index) in items"
          :key="item.module + '_' + item.type + '_' + index"
          class="py-3 px-4 rounded-lg border"
          :class="itemRowClasses(item)"
        >
          <div class="flex items-start justify-between gap-4">
            <div class="flex-1 min-w-0">
              <div class="flex flex-wrap items-center gap-2 mb-1">
                <span
                  class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                  :class="priorityClasses(item.priority)"
                >
                  {{ priorityLabel(item.priority) }}
                </span>
                <span
                  class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                  :class="moduleBadgeClasses(item.module)"
                >
                  {{ formatModuleName(item.module) }}
                </span>
              </div>
              <h4 class="text-sm font-semibold text-horizon-500">{{ item.title }}</h4>
              <p v-if="item.description" class="text-xs text-neutral-500 mt-0.5">{{ item.description }}</p>
              <p v-if="item.conflict_note" class="text-xs text-violet-700 mt-1">{{ item.conflict_note }}</p>
            </div>
            <div class="flex-shrink-0 text-right">
              <p v-if="hasMonthlyCost(item)" class="text-sm font-semibold text-horizon-500">
                {{ formatCurrency(item.required_monthly_cost) }}<span class="text-xs font-normal text-neutral-500">/mo</span>
              </p>
              <p class="text-xs mt-0.5 font-medium" :class="affordabilityTextClasses(item.affordability)">
                {{ affordabilityLabel(item.affordability) }}
              </p>
            </div>
          </div>
          <p v-if="hasMonthlyCost(item)" class="text-xs text-neutral-500 mt-1">
            {{ formatCurrency(item.surplus_consumed_to_here) }} of your
            {{ formatCurrency(effectiveSurplus) }} surplus committed by this point
          </p>
        </div>
      </div>

      <div v-else class="text-center py-8 text-neutral-500">
        <p class="text-sm">No cross-module actions just yet. Add more detail to your modules to build your plan.</p>
      </div>

      <!-- Locked strategies — surfaced, never silently dropped -->
      <div v-if="locked.length" class="mt-6 pt-4 border-t border-light-gray">
        <h4 class="text-sm font-semibold text-horizon-500 mb-1">Unlock more of your plan</h4>
        <p class="text-xs text-neutral-500 mb-3">
          These strategies need a little more detail before we can rank them.
        </p>
        <ul class="space-y-1.5">
          <li
            v-for="(lock, i) in locked"
            :key="lock.module + '_' + lock.strategy_type + '_' + i"
            class="text-xs text-horizon-500"
          >
            <span class="font-medium">{{ formatModuleName(lock.module) }}:</span>
            {{ formatStrategyType(lock.strategy_type) }}
            <span class="text-neutral-500">&mdash; add more detail in your {{ formatModuleName(lock.module) }} module</span>
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';
import PlanSectionHeader from '@/components/Plans/Shared/PlanSectionHeader.vue';

export default {
  name: 'HolisticCompositePlan',

  components: { PlanSectionHeader },

  mixins: [currencyMixin],

  props: {
    plan: {
      type: Object,
      required: true,
    },
  },

  computed: {
    items() {
      return Array.isArray(this.plan.items) ? this.plan.items : [];
    },

    locked() {
      return Array.isArray(this.plan.locked) ? this.plan.locked : [];
    },

    availableSurplus() {
      return Number(this.plan.available_monthly_surplus) || 0;
    },

    goalCommitments() {
      return Number(this.plan.goal_commitments) || 0;
    },

    effectiveSurplus() {
      return Number(this.plan.effective_surplus) || 0;
    },

    nearTermCapital() {
      return Number(this.plan.near_term_capital) || 0;
    },

    nearTermCapitalText() {
      const amount = this.formatCurrency(Math.abs(this.nearTermCapital));
      return this.nearTermCapital > 0
        ? `Plus ${amount} of capital expected in the next 12 months, which could fund a one-off action.`
        : `Less ${amount} of expected outgoings in the next 12 months, which your plan keeps in reserve.`;
    },
  },

  methods: {
    hasMonthlyCost(item) {
      return item.required_monthly_cost !== undefined
        && item.required_monthly_cost !== null
        && Number(item.required_monthly_cost) > 0;
    },

    priorityLabel(priority) {
      const labels = { high: 'High', medium: 'Medium', low: 'Low' };
      return labels[priority] || 'Medium';
    },

    priorityClasses(priority) {
      const map = {
        high: 'bg-violet-100 text-violet-800',
        medium: 'bg-savannah-100 text-horizon-500',
        low: 'bg-spring-100 text-spring-800',
      };
      return map[priority] || map.medium;
    },

    moduleBadgeClasses(module) {
      const map = {
        tax: 'bg-horizon-100 text-horizon-500',
        retirement: 'bg-spring-100 text-spring-800',
        savings: 'bg-spring-100 text-spring-800',
        investment: 'bg-horizon-100 text-horizon-500',
        protection: 'bg-violet-100 text-violet-800',
        estate: 'bg-savannah-100 text-horizon-500',
      };
      return map[module] || 'bg-savannah-100 text-horizon-500';
    },

    formatModuleName(module) {
      const names = {
        tax: 'Tax',
        retirement: 'Retirement',
        savings: 'Savings',
        investment: 'Investment',
        protection: 'Protection',
        estate: 'Estate',
      };
      return names[module] || (module ? module.charAt(0).toUpperCase() + module.slice(1) : '');
    },

    affordabilityLabel(affordability) {
      const labels = {
        fits: 'Fits within your surplus',
        partially_fits: 'Partially fits',
        beyond_current_surplus: 'Beyond your current surplus',
      };
      return labels[affordability] || 'Fits within your surplus';
    },

    affordabilityTextClasses(affordability) {
      const map = {
        fits: 'text-spring-700',
        partially_fits: 'text-violet-700',
        beyond_current_surplus: 'text-neutral-500',
      };
      return map[affordability] || 'text-spring-700';
    },

    itemRowClasses(item) {
      const map = {
        fits: 'border-light-gray',
        partially_fits: 'border-violet-200 bg-violet-50/30',
        beyond_current_surplus: 'border-savannah-100 bg-eggshell-500 opacity-70',
      };
      return map[item.affordability] || 'border-light-gray';
    },

    formatStrategyType(strategyType) {
      if (!strategyType) return '';
      // Spell out embedded acronyms (Rule #9 — only ISA may stay abbreviated).
      const expansions = {
        aa: 'Annual Allowance',
        mpaa: 'Money Purchase Annual Allowance',
        gia: 'General Investment Account',
        psa: 'Personal Savings Allowance',
        isa: 'ISA',
        cgt: 'Capital Gains Tax',
        iht: 'Inheritance Tax',
        nrb: 'Nil-Rate Band',
        rnrb: 'Residence Nil-Rate Band',
        topup: 'top-up',
      };
      return strategyType
        .split('_')
        .map(word => expansions[word] || word)
        .join(' ')
        .replace(/^./, char => char.toUpperCase());
    },
  },
};
</script>
