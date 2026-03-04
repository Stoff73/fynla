<template>
  <div class="mb-6">
    <PlanSectionHeader
      title="Priority Area"
      subtitle="All actions ranked and allocated against your shared monthly disposable income"
      color="purple"
    />

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
      <!-- Budget overview -->
      <div class="mb-6">
        <div class="flex items-center justify-between mb-2">
          <span class="text-sm font-medium text-gray-700">Monthly Disposable Income</span>
          <span class="text-sm font-bold text-gray-900">{{ formatCurrency(monthlyDisposableIncome) }}</span>
        </div>
        <div class="flex items-center justify-between mb-2">
          <span class="text-sm font-medium text-gray-700">Total Allocated (enabled actions)</span>
          <span class="text-sm font-bold" :class="totalAllocated > monthlyDisposableIncome ? 'text-red-700' : 'text-green-700'">
            {{ formatCurrency(totalAllocated) }}
          </span>
        </div>
        <!-- Allocation bar -->
        <div class="w-full h-3 bg-gray-100 rounded-full overflow-hidden">
          <div
            class="h-full rounded-full transition-all duration-300"
            :class="allocationBarColor"
            :style="{ width: allocationPercentage + '%' }"
          />
        </div>
        <div class="flex justify-between mt-1">
          <span class="text-xs text-gray-500">{{ formatPercentage(allocationPercentage) }} allocated</span>
          <span v-if="remainingBudget >= 0" class="text-xs text-green-600">{{ formatCurrency(remainingBudget) }} remaining</span>
          <span v-else class="text-xs text-red-600">{{ formatCurrency(Math.abs(remainingBudget)) }} over budget</span>
        </div>
      </div>

      <!-- Action list -->
      <div v-if="sortedActions.length" class="space-y-2">
        <div
          v-for="(action, index) in sortedActions"
          :key="action.sourceModule + '_' + action.id"
          class="flex items-center justify-between py-3 px-4 rounded-lg border transition-all"
          :class="actionRowClasses(action, index)"
        >
          <div class="flex-1 min-w-0 mr-4">
            <div class="flex items-center space-x-2 mb-0.5">
              <span
                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                :class="priorityClasses(action.priority)"
              >
                {{ priorityLabel(action.priority) }}
              </span>
              <span
                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                :class="moduleBadgeClasses(action.sourceModule)"
              >
                {{ formatModuleName(action.sourceModule) }}
              </span>
              <span v-if="action.isGoalAction" class="text-xs text-gray-500">Goal</span>
            </div>
            <h4 class="text-sm font-semibold text-gray-900">{{ action.title }}</h4>
          </div>
          <div class="flex-shrink-0 text-right">
            <p class="text-sm font-semibold" :class="action.enabled ? 'text-gray-900' : 'text-gray-400'">
              {{ formatCurrency(action.monthlyCost) }}<span class="text-xs font-normal text-gray-500">/mo</span>
            </p>
            <p v-if="exceedsBudget(index) && action.enabled" class="text-xs text-red-600 mt-0.5">
              Exceeds available income
            </p>
          </div>
        </div>
      </div>

      <div v-else class="text-center py-8 text-gray-500">
        <p class="text-sm">No actions available across your plans.</p>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';
import PlanSectionHeader from '@/components/Plans/Shared/PlanSectionHeader.vue';

export default {
  name: 'HolisticPriorityArea',

  components: { PlanSectionHeader },

  mixins: [currencyMixin],

  props: {
    allActions: {
      type: Array,
      required: true,
    },
    monthlyDisposableIncome: {
      type: Number,
      default: 0,
    },
  },

  computed: {
    sortedActions() {
      return [...this.allActions].sort((a, b) => {
        // Goals first
        if (a.isGoalAction && !b.isGoalAction) return -1;
        if (!a.isGoalAction && b.isGoalAction) return 1;

        // Tax optimisation next
        if (a.isTaxOptimisation && !b.isTaxOptimisation) return -1;
        if (!a.isTaxOptimisation && b.isTaxOptimisation) return 1;

        // Then by priority rank
        const priorityRank = { critical: 0, high: 1, medium: 2, low: 3 };
        const rankA = priorityRank[a.priority] ?? 2;
        const rankB = priorityRank[b.priority] ?? 2;
        return rankA - rankB;
      });
    },

    totalAllocated() {
      return this.sortedActions
        .filter(a => a.enabled)
        .reduce((sum, a) => sum + (a.monthlyCost || 0), 0);
    },

    allocationPercentage() {
      if (!this.monthlyDisposableIncome || this.monthlyDisposableIncome <= 0) return 0;
      return Math.min(100, (this.totalAllocated / this.monthlyDisposableIncome) * 100);
    },

    allocationBarColor() {
      if (this.allocationPercentage >= 100) return 'bg-red-500';
      if (this.allocationPercentage >= 80) return 'bg-blue-500';
      return 'bg-green-500';
    },

    remainingBudget() {
      return this.monthlyDisposableIncome - this.totalAllocated;
    },
  },

  methods: {
    exceedsBudget(index) {
      let cumulative = 0;
      for (let i = 0; i <= index; i++) {
        if (this.sortedActions[i].enabled) {
          cumulative += this.sortedActions[i].monthlyCost || 0;
        }
      }
      return cumulative > this.monthlyDisposableIncome;
    },

    actionRowClasses(action, index) {
      if (!action.enabled) return 'border-gray-100 bg-gray-50 opacity-60';
      if (this.exceedsBudget(index)) return 'border-red-200 bg-red-50/30';
      return 'border-gray-200';
    },

    priorityLabel(priority) {
      const labels = { critical: 'Critical', high: 'High', medium: 'Medium', low: 'Low' };
      return labels[priority] || 'Medium';
    },

    priorityClasses(priority) {
      const map = {
        critical: 'bg-red-100 text-red-800',
        high: 'bg-blue-100 text-blue-800',
        medium: 'bg-gray-100 text-gray-800',
        low: 'bg-green-100 text-green-800',
      };
      return map[priority] || map.medium;
    },

    moduleBadgeClasses(module) {
      const map = {
        protection: 'bg-purple-100 text-purple-800',
        investment: 'bg-blue-100 text-blue-800',
        retirement: 'bg-green-100 text-green-800',
        estate: 'bg-gray-100 text-gray-800',
      };
      return map[module] || 'bg-gray-100 text-gray-800';
    },

    formatModuleName(module) {
      const names = {
        protection: 'Protection',
        investment: 'Investment & Savings',
        retirement: 'Retirement',
        estate: 'Estate',
      };
      return names[module] || module;
    },

    formatPercentage(val) {
      return Math.round(val) + '%';
    },
  },
};
</script>
