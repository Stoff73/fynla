<template>
  <div class="bg-white rounded-lg shadow-sm border border-light-gray p-6 mb-6">
    <!-- Greeting -->
    <p class="text-neutral-500 leading-relaxed mb-4">{{ summary.greeting }}</p>

    <!-- Opening -->
    <p v-if="summary.opening" class="text-neutral-500 leading-relaxed mb-4">{{ summary.opening }}</p>

    <!-- Introduction -->
    <p class="text-neutral-500 leading-relaxed mb-5">{{ summary.introduction }}</p>

    <!-- Goals Summary -->
    <div v-if="summary.goals_summary && summary.goals_summary.length > 0" class="mb-5">
      <h4 class="text-sm font-semibold text-horizon-500 mb-2">Your Investment Goals</h4>
      <div class="overflow-x-auto border border-light-gray rounded-lg">
        <table class="min-w-full divide-y divide-light-gray">
          <thead class="bg-eggshell-500">
            <tr>
              <th scope="col" class="px-4 py-2.5 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Goal</th>
              <th scope="col" class="px-4 py-2.5 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Target</th>
              <th scope="col" class="px-4 py-2.5 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Progress</th>
              <th scope="col" class="px-4 py-2.5 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Status</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-light-gray">
            <tr v-for="goal in summary.goals_summary" :key="goal.name">
              <td class="px-4 py-2.5 text-sm text-horizon-500">{{ goal.name }}</td>
              <td class="px-4 py-2.5 text-sm text-horizon-500">{{ formatCurrency(goal.target) }}</td>
              <td class="px-4 py-2.5 text-sm text-neutral-500">{{ Math.round(goal.progress) }}%</td>
              <td class="px-4 py-2.5">
                <span
                  class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                  :class="goal.on_track ? 'bg-spring-100 text-spring-800' : 'bg-raspberry-100 text-raspberry-800'"
                >
                  {{ goal.on_track ? 'On track' : 'Needs attention' }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Key Actions Summary -->
    <div v-if="summary.actions_summary && summary.actions_summary.length > 0" class="mb-5">
      <h4 class="text-sm font-semibold text-horizon-500 mb-2">Key Actions</h4>
      <div class="overflow-x-auto border border-light-gray rounded-lg">
        <table class="min-w-full divide-y divide-light-gray">
          <thead class="bg-eggshell-500">
            <tr>
              <th scope="col" class="px-4 py-2.5 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Action</th>
              <th scope="col" class="px-4 py-2.5 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Priority</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-light-gray">
            <tr v-for="(action, index) in summary.actions_summary" :key="index">
              <td class="px-4 py-2.5 text-sm text-horizon-500">{{ action.title }}</td>
              <td class="px-4 py-2.5">
                <span
                  class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                  :class="priorityClass(action.priority)"
                >
                  {{ capitalise(action.priority) }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-if="summary.total_actions > summary.actions_summary.length" class="text-xs text-neutral-500 mt-1.5">
        Showing top {{ summary.actions_summary.length }} of {{ summary.total_actions }} actions. See the full list below.
      </p>
    </div>

    <!-- Closing Statement -->
    <p v-if="summary.closing" class="text-neutral-500 leading-relaxed">{{ summary.closing }}</p>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'InvestmentExecutiveSummary',
  mixins: [currencyMixin],
  props: {
    summary: {
      type: Object,
      required: true,
    },
  },
  methods: {
    priorityClass(priority) {
      const classes = {
        critical: 'bg-raspberry-100 text-raspberry-800',
        high: 'bg-violet-100 text-violet-800',
        medium: 'bg-savannah-100 text-neutral-500',
        low: 'bg-savannah-100 text-neutral-500',
      };
      return classes[priority] || classes.medium;
    },
    capitalise(str) {
      if (!str) return '';
      return str.charAt(0).toUpperCase() + str.slice(1);
    },
  },
};
</script>
