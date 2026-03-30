<template>
  <div class="goal-card bg-white border border-light-gray rounded-lg p-6 hover:shadow-md hover:-translate-y-0.5 hover:bg-light-gray transition-all duration-200">
    <!-- Goal Header -->
    <div class="flex justify-between items-start mb-4">
      <div class="flex-1">
        <h3 class="text-lg font-semibold text-horizon-500">{{ goal.name }}</h3>
        <p v-if="goal.description" class="text-sm text-neutral-500 mt-1">{{ goal.description }}</p>
      </div>
      <div class="flex gap-2 ml-4">
        <button
          @click="$emit('edit', goal)"
          class="text-violet-600 hover:text-violet-800"
          title="Edit goal"
        >
          <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
          </svg>
        </button>
        <button
          @click="$emit('delete', goal)"
          class="text-raspberry-600 hover:text-raspberry-800"
          title="Delete goal"
        >
          <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Goal Metrics -->
    <div class="grid grid-cols-2 gap-4 mb-4">
      <div>
        <p class="text-xs text-neutral-500 mb-1">Target Amount</p>
        <p class="text-lg font-semibold text-horizon-500">{{ formatCurrency(goal.target_amount) }}</p>
      </div>
      <div>
        <p class="text-xs text-neutral-500 mb-1">Target Date</p>
        <p class="text-lg font-semibold text-horizon-500">{{ formatDate(goal.target_date) }}</p>
      </div>
      <div>
        <p class="text-xs text-neutral-500 mb-1">Current Value</p>
        <p class="text-lg font-semibold text-horizon-500">{{ formatCurrency(currentValue) }}</p>
      </div>
      <div>
        <p class="text-xs text-neutral-500 mb-1">Time Remaining</p>
        <p class="text-lg font-semibold text-horizon-500">{{ timeRemaining }}</p>
      </div>
    </div>

    <!-- Progress Bar -->
    <div class="mb-4">
      <div class="flex justify-between items-center mb-2">
        <span class="text-sm font-medium text-neutral-500">Progress</span>
        <span class="text-sm font-semibold" :class="progressClass">{{ progressPercent }}%</span>
      </div>
      <div class="w-full bg-savannah-200 rounded-full h-3">
        <div
          class="h-3 rounded-full transition-all"
          :class="progressBarClass"
          :style="{ width: Math.min(progressPercent, 100) + '%' }"
        ></div>
      </div>
    </div>

    <!-- Monthly Contribution -->
    <div class="flex justify-between items-center py-3 border-t border-light-gray mb-4">
      <span class="text-sm text-neutral-500">Monthly Contribution:</span>
      <span class="text-sm font-medium text-horizon-500">{{ formatCurrency(goal.monthly_contribution || 0) }}</span>
    </div>

    <!-- Monte Carlo Section -->
    <div v-if="monteCarloResult" class="border-t border-light-gray pt-4">
      <div class="flex justify-between items-center mb-3">
        <h4 class="text-sm font-semibold text-horizon-500">Monte Carlo Analysis</h4>
        <button
          @click="$emit('run-monte-carlo', goal)"
          class="text-xs text-violet-600 hover:text-violet-800 font-medium"
        >
          Re-run
        </button>
      </div>

      <!-- Success Probability Gauge -->
      <div class="mb-4">
        <div class="flex justify-between items-center mb-2">
          <span class="text-sm text-neutral-500">Success Probability</span>
          <span class="text-lg font-bold" :class="probabilityClass">{{ successProbability }}%</span>
        </div>
        <div class="w-full bg-savannah-200 rounded-full h-2">
          <div
            class="h-2 rounded-full transition-all"
            :class="probabilityBarClass"
            :style="{ width: successProbability + '%' }"
          ></div>
        </div>
      </div>

      <!-- Key Metrics -->
      <div class="grid grid-cols-2 gap-3 text-sm">
        <div>
          <span class="text-neutral-500">Median Outcome:</span>
          <span class="ml-1 font-medium text-horizon-500">{{ formatCurrency(monteCarloResult.median_outcome) }}</span>
        </div>
        <div>
          <span class="text-neutral-500">Best Case:</span>
          <span class="ml-1 font-medium text-spring-600">{{ formatCurrency(monteCarloResult.percentile_90) }}</span>
        </div>
        <div>
          <span class="text-neutral-500">Required Return:</span>
          <span class="ml-1 font-medium text-horizon-500">{{ monteCarloResult.required_return?.toFixed(2) }}%</span>
        </div>
        <div>
          <span class="text-neutral-500">Worst Case:</span>
          <span class="ml-1 font-medium text-raspberry-600">{{ formatCurrency(monteCarloResult.percentile_10) }}</span>
        </div>
      </div>

      <!-- View Chart Button -->
      <button
        @click="$emit('view-chart', goal)"
        class="w-full mt-4 bg-violet-500 text-white px-4 py-2 rounded-button text-sm font-medium hover:bg-raspberry-500 transition-colors"
      >
        View Detailed Chart
      </button>
    </div>

    <!-- No Monte Carlo Yet -->
    <div v-else class="border-t border-light-gray pt-4">
      <div class="text-center py-4">
        <svg class="mx-auto h-10 w-10 text-horizon-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
        </svg>
        <p class="text-sm text-neutral-500 mb-3">No Monte Carlo analysis yet</p>
        <button
          @click="$emit('run-monte-carlo', goal)"
          :disabled="runningMonteCarlo"
          class="bg-raspberry-500 text-white px-4 py-2 rounded-button text-sm font-medium hover:bg-raspberry-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {{ runningMonteCarlo ? 'Running...' : 'Run Monte Carlo Simulation' }}
        </button>
      </div>
    </div>

    <!-- Status Badge -->
    <div class="mt-4 pt-4 border-t border-light-gray">
      <span
        class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium"
        :class="statusBadgeClass"
      >
        <span class="w-2 h-2 rounded-full mr-2" :class="statusDotClass"></span>
        {{ statusText }}
      </span>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'GoalCard',

  emits: ['edit', 'delete', 'run-monte-carlo', 'view-chart'],

  mixins: [currencyMixin],

  props: {
    goal: {
      type: Object,
      required: true,
    },
    currentValue: {
      type: Number,
      default: 0,
    },
    monteCarloResult: {
      type: Object,
      default: null,
    },
    runningMonteCarlo: {
      type: Boolean,
      default: false,
    },
  },

  computed: {
    progressPercent() {
      if (!this.goal.target_amount) return 0;
      return Math.round((this.currentValue / this.goal.target_amount) * 100);
    },

    progressClass() {
      if (this.progressPercent >= 100) return 'text-spring-600';
      if (this.progressPercent >= 75) return 'text-violet-600';
      if (this.progressPercent >= 50) return 'text-violet-600';
      return 'text-violet-600';
    },

    progressBarClass() {
      if (this.progressPercent >= 100) return 'bg-spring-600';
      if (this.progressPercent >= 75) return 'bg-raspberry-500';
      if (this.progressPercent >= 50) return 'bg-violet-500';
      return 'bg-violet-500';
    },

    timeRemaining() {
      const target = new Date(this.goal.target_date);
      const now = new Date();
      const diffTime = target - now;
      const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

      if (diffDays < 0) return 'Overdue';
      if (diffDays === 0) return 'Today';
      if (diffDays === 1) return '1 day';
      if (diffDays < 30) return `${diffDays} days`;
      if (diffDays < 365) {
        const months = Math.floor(diffDays / 30);
        return `${months} ${months === 1 ? 'month' : 'months'}`;
      }
      const years = Math.floor(diffDays / 365);
      const months = Math.floor((diffDays % 365) / 30);
      if (months === 0) return `${years} ${years === 1 ? 'year' : 'years'}`;
      return `${years}y ${months}m`;
    },

    successProbability() {
      return this.monteCarloResult?.success_probability || 0;
    },

    probabilityClass() {
      if (this.successProbability >= 80) return 'text-spring-600';
      if (this.successProbability >= 60) return 'text-violet-600';
      if (this.successProbability >= 40) return 'text-violet-600';
      return 'text-raspberry-600';
    },

    probabilityBarClass() {
      if (this.successProbability >= 80) return 'bg-spring-600';
      if (this.successProbability >= 60) return 'bg-raspberry-500';
      if (this.successProbability >= 40) return 'bg-violet-500';
      return 'bg-raspberry-600';
    },

    statusText() {
      if (this.progressPercent >= 100) return 'Goal Achieved';
      if (this.monteCarloResult) {
        if (this.successProbability >= 80) return 'On Track';
        if (this.successProbability >= 60) return 'Fair Progress';
        if (this.successProbability >= 40) return 'Needs Attention';
        return 'Off Track';
      }
      if (this.progressPercent >= 75) return 'Good Progress';
      if (this.progressPercent >= 50) return 'Fair Progress';
      return 'Getting Started';
    },

    statusBadgeClass() {
      if (this.progressPercent >= 100) return 'bg-spring-500 text-white';
      if (this.monteCarloResult) {
        if (this.successProbability >= 80) return 'bg-spring-500 text-white';
        if (this.successProbability >= 60) return 'bg-violet-500 text-white';
        if (this.successProbability >= 40) return 'bg-violet-500 text-white';
        return 'bg-raspberry-500 text-white';
      }
      if (this.progressPercent >= 75) return 'bg-spring-500 text-white';
      if (this.progressPercent >= 50) return 'bg-violet-500 text-white';
      return 'bg-violet-500 text-white';
    },

    statusDotClass() {
      if (this.progressPercent >= 100) return 'bg-spring-600';
      if (this.monteCarloResult) {
        if (this.successProbability >= 80) return 'bg-spring-600';
        if (this.successProbability >= 60) return 'bg-raspberry-500';
        if (this.successProbability >= 40) return 'bg-violet-500';
        return 'bg-raspberry-600';
      }
      if (this.progressPercent >= 75) return 'bg-spring-600';
      if (this.progressPercent >= 50) return 'bg-violet-500';
      return 'bg-violet-500';
    },
  },

  methods: {
    formatDate(dateString) {
      if (!dateString) return 'N/A';
      const date = new Date(dateString);
      return date.toLocaleDateString('en-GB', { year: 'numeric', month: 'short', day: 'numeric' });
    },
  },
};
</script>
