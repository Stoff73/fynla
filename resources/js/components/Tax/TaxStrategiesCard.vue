<template>
  <div class="card">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-h3 text-horizon-500">Tax Optimisation Strategies</h2>
      <button
        v-if="!loading && strategies.length > 0"
        class="text-sm text-raspberry-500 hover:text-raspberry-600 font-medium transition-colors"
        @click="fetchStrategies"
      >
        Refresh
      </button>
    </div>

    <!-- Loading state -->
    <div v-if="loading" class="flex items-center justify-center py-8">
      <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
    </div>

    <!-- Error state -->
    <div v-else-if="error" class="bg-light-pink-50 border border-light-pink-200 rounded-lg p-4">
      <p class="text-sm text-raspberry-600">{{ error }}</p>
      <button
        class="mt-2 text-sm text-raspberry-500 hover:text-raspberry-600 font-medium"
        @click="fetchStrategies"
      >
        Try again
      </button>
    </div>

    <!-- Empty state -->
    <div v-else-if="strategies.length === 0" class="text-center py-6">
      <svg class="w-12 h-12 text-spring-500 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <p class="text-body text-neutral-500">
        No additional tax optimisation opportunities identified at this time.
      </p>
    </div>

    <!-- Strategies list -->
    <div v-else class="space-y-4">
      <!-- Total estimated saving -->
      <div v-if="totalEstimatedSaving > 0" class="bg-spring-50 border border-spring-200 rounded-lg p-4 mb-4">
        <div class="flex items-center justify-between">
          <span class="text-sm font-medium text-spring-800">Total Estimated Annual Tax Saving</span>
          <span class="text-lg font-bold text-spring-700">{{ formatCurrency(totalEstimatedSaving) }}</span>
        </div>
      </div>

      <!-- Allowance usage summary -->
      <div v-if="allowanceUsage" class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
        <div class="bg-eggshell-500 rounded-lg p-3">
          <p class="text-xs text-neutral-500 mb-1">ISA Allowance Used</p>
          <p class="text-body font-semibold text-horizon-500">
            {{ formatCurrency(allowanceUsage.isa?.used || 0) }}
            <span class="text-xs text-neutral-500 font-normal">
              of {{ formatCurrency(allowanceUsage.isa?.allowance || 20000) }}
            </span>
          </p>
          <div class="mt-1 h-1.5 bg-horizon-100 rounded-full overflow-hidden">
            <div
              class="h-full rounded-full transition-all duration-500"
              :class="isaBarColour"
              :style="{ width: Math.min(100, allowanceUsage.isa?.utilisation_percent || 0) + '%' }"
            ></div>
          </div>
        </div>

        <div class="bg-eggshell-500 rounded-lg p-3">
          <p class="text-xs text-neutral-500 mb-1">Pension Annual Allowance Used</p>
          <p class="text-body font-semibold text-horizon-500">
            {{ formatCurrency(allowanceUsage.pension_annual_allowance?.total_contributions || 0) }}
            <span class="text-xs text-neutral-500 font-normal">
              of {{ formatCurrency(allowanceUsage.pension_annual_allowance?.available_allowance || 60000) }}
            </span>
          </p>
          <div class="mt-1 h-1.5 bg-horizon-100 rounded-full overflow-hidden">
            <div
              class="h-full rounded-full transition-all duration-500"
              :class="pensionBarColour"
              :style="{ width: Math.min(100, pensionUtilisation) + '%' }"
            ></div>
          </div>
        </div>

        <div class="bg-eggshell-500 rounded-lg p-3">
          <p class="text-xs text-neutral-500 mb-1">Capital Gains (Unrealised)</p>
          <p class="text-body font-semibold text-horizon-500">
            {{ formatCurrency(allowanceUsage.capital_gains?.net_gains || 0) }}
          </p>
          <p class="text-xs text-neutral-500 mt-1">
            Exempt amount: {{ formatCurrency(allowanceUsage.capital_gains?.annual_exempt_amount || 3000) }}
          </p>
        </div>
      </div>

      <!-- Strategy cards -->
      <div
        v-for="strategy in strategies"
        :key="strategy.type"
        class="border border-light-gray rounded-lg p-4 hover:border-savannah-300 transition-colors"
      >
        <div class="flex items-start gap-3">
          <div
            class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center text-white text-sm font-bold"
            :class="priorityBadgeColour(strategy.priority)"
          >
            {{ strategy.rank }}
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 mb-1">
              <h3 class="text-body font-semibold text-horizon-500">{{ strategy.title }}</h3>
              <span
                class="text-xs px-2 py-0.5 rounded-full font-medium"
                :class="priorityLabelColour(strategy.priority)"
              >
                {{ strategy.priority }}
              </span>
            </div>
            <p class="text-sm text-neutral-500 mb-2">{{ strategy.description }}</p>
            <p class="text-sm text-horizon-500">
              <span class="font-medium">Suggested action:</span> {{ strategy.action }}
            </p>
            <p v-if="strategy.estimated_annual_saving > 0" class="text-sm font-semibold text-spring-600 mt-2">
              Estimated annual saving: {{ formatCurrency(strategy.estimated_annual_saving) }}
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';
import { previewModeMixin } from '@/mixins/previewModeMixin';
import taxOptimisationService from '@/services/taxOptimisationService';

export default {
  name: 'TaxStrategiesCard',

  mixins: [currencyMixin, previewModeMixin],

  data() {
    return {
      loading: false,
      error: null,
      strategies: [],
      totalEstimatedSaving: 0,
      allowanceUsage: null,
    };
  },

  computed: {
    pensionUtilisation() {
      const aa = this.allowanceUsage?.pension_annual_allowance;
      if (!aa || !aa.available_allowance) return 0;
      return Math.min(100, (aa.total_contributions / aa.available_allowance) * 100);
    },

    isaBarColour() {
      const pct = this.allowanceUsage?.isa?.utilisation_percent || 0;
      if (pct >= 90) return 'bg-spring-500';
      if (pct >= 50) return 'bg-violet-500';
      return 'bg-raspberry-500';
    },

    pensionBarColour() {
      if (this.pensionUtilisation >= 90) return 'bg-spring-500';
      if (this.pensionUtilisation >= 50) return 'bg-violet-500';
      return 'bg-raspberry-500';
    },
  },

  mounted() {
    this.fetchStrategies();
  },

  methods: {
    async fetchStrategies() {
      this.loading = true;
      this.error = null;

      try {
        const response = await taxOptimisationService.getAnalysis();
        const data = response.data?.data || response.data || {};

        this.strategies = data.strategies || [];
        this.totalEstimatedSaving = data.total_estimated_saving || 0;
        this.allowanceUsage = data.allowance_usage || null;
      } catch (err) {
        if (err.response?.status === 404) {
          this.strategies = [];
          this.allowanceUsage = null;
        } else {
          this.error = 'Unable to load tax optimisation data. Please try again.';
        }
      } finally {
        this.loading = false;
      }
    },

    priorityBadgeColour(priority) {
      return {
        high: 'bg-raspberry-500',
        medium: 'bg-violet-500',
        low: 'bg-horizon-400',
      }[priority] || 'bg-horizon-400';
    },

    priorityLabelColour(priority) {
      return {
        high: 'bg-light-pink-100 text-raspberry-700',
        medium: 'bg-violet-100 text-violet-700',
        low: 'bg-eggshell-500 text-horizon-500',
      }[priority] || 'bg-eggshell-500 text-horizon-500';
    },
  },
};
</script>
