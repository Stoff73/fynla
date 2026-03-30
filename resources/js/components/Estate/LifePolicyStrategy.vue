<template>
  <div class="life-policy-strategy-tab">
    <!-- Back to Dashboard Link -->
    <button
      @click="$emit('switch-tab', 'iht')"
      class="inline-flex items-center text-sm text-violet-600 hover:text-violet-800 mb-4"
    >
      <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
      </svg>
      Back to Estate Dashboard
    </button>
    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-violet-600"></div>
      <p class="mt-4 text-neutral-500">Calculating life policy strategy...</p>
    </div>

    <!-- No Inheritance Tax Liability State -->
    <div v-else-if="noIHTLiability" class="bg-white border border-light-gray rounded-lg p-6">
      <div class="flex items-start">
        <svg class="h-6 w-6 text-spring-600 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <div class="ml-3">
          <h3 class="text-lg font-semibold text-spring-900">No Life Insurance Required</h3>
          <p class="mt-2 text-spring-700">{{ noIHTMessage }}</p>
          <p class="mt-2 text-sm text-spring-600">You have no projected Inheritance Tax liability at expected death. Life insurance for Inheritance Tax planning is not necessary.</p>
        </div>
      </div>
    </div>

    <!-- Strategy Comparison -->
    <div v-else-if="strategy" class="space-y-6">
      <!-- Whole of Life Insurance Details -->
      <div class="bg-white rounded-lg border border-light-gray shadow-lg">
        <div class="bg-savannah-100 px-6 py-4 border-b border-light-gray">
          <div>
            <h3 class="text-xl font-bold text-horizon-500">{{ policy.policy_type }}</h3>
            <p class="text-sm text-neutral-500 mt-1">{{ policy.description }}</p>
          </div>
        </div>

        <div class="p-4 sm:p-6">
          <!-- Key Metrics -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4 mb-6">
            <div class="bg-white rounded-lg p-3 sm:p-4 border border-light-gray">
              <p class="text-xs sm:text-sm text-horizon-500 font-medium mb-1">Cover Amount</p>
              <p class="text-lg sm:text-xl lg:text-2xl font-bold text-horizon-500">{{ formatCurrency(policy.cover_amount) }}</p>
              <p class="text-xs text-neutral-500 mt-1">Guaranteed payout</p>
            </div>
            <div class="bg-white rounded-lg p-3 sm:p-4 border border-light-gray">
              <p class="text-xs sm:text-sm text-spring-700 font-medium mb-1">Monthly Premium</p>
              <p class="text-lg sm:text-xl lg:text-2xl font-bold text-spring-900">{{ formatCurrency(policy.monthly_premium) }}</p>
            </div>
            <div class="bg-white rounded-lg p-3 sm:p-4 border border-light-gray">
              <p class="text-xs sm:text-sm text-violet-700 font-medium mb-1">Annual Premium</p>
              <p class="text-lg sm:text-xl lg:text-2xl font-bold text-violet-900">{{ formatCurrency(policy.annual_premium) }}</p>
              <p class="text-xs text-violet-600 mt-1">Per year</p>
            </div>
            <div class="bg-white rounded-lg p-3 sm:p-4 border border-light-gray">
              <p class="text-xs sm:text-sm text-raspberry-700 font-medium mb-1">Total Premiums</p>
              <p class="text-lg sm:text-xl lg:text-2xl font-bold text-raspberry-700">{{ formatCurrency(policy.total_premiums_paid) }}</p>
              <p class="text-xs text-neutral-500 mt-1">Over {{ policy.term_years }} years</p>
            </div>
          </div>

          <!-- Key Features -->
          <div class="mb-6">
            <h4 class="text-md font-semibold text-horizon-500 mb-3">Key Features</h4>
            <ul class="space-y-2">
              <li v-for="(feature, index) in policy.key_features" :key="index" class="flex items-start">
                <svg class="h-5 w-5 text-spring-600 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-neutral-500">{{ feature }}</span>
              </li>
            </ul>
          </div>

          <!-- Implementation Steps -->
          <div>
            <h4 class="text-md font-semibold text-horizon-500 mb-3">Implementation Steps</h4>
            <ol class="space-y-2">
              <li v-for="(step, index) in policy.implementation_steps" :key="index" class="flex items-start">
                <span class="flex-shrink-0 w-6 h-6 bg-white border-b-2 border-horizon-400 text-horizon-500 rounded-full flex items-center justify-center text-sm font-semibold mr-3">
                  {{ index + 1 }}
                </span>
                <span class="text-neutral-500 pt-0.5">{{ step }}</span>
              </li>
            </ol>
          </div>
        </div>
      </div>

      <!-- Decision Framework -->
      <div class="bg-white rounded-lg border border-horizon-300 shadow-lg">
        <div class="bg-savannah-100 px-6 py-4 border-b border-light-gray">
          <h3 class="text-xl font-bold text-horizon-500">Decision Framework</h3>
          <p class="text-sm text-neutral-500 mt-1">Use this framework to help decide which approach is best for you</p>
        </div>
        <div class="p-6">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div v-for="(items, decision) in strategy.comparison.decision_framework" :key="decision">
              <h4 class="text-md font-semibold mb-3" :class="{
                'text-horizon-500': decision.includes('Insurance'),
                'text-violet-500': decision.includes('Self-Insurance'),
                'text-raspberry-700': decision.includes('Hybrid')
              }">{{ decision }}</h4>
              <ul class="space-y-2">
                <li v-for="(item, index) in items" :key="index" class="flex items-start text-sm">
                  <span class="mr-2" :class="{
                    'text-horizon-500': decision.includes('Insurance'),
                    'text-violet-500': decision.includes('Self-Insurance'),
                    'text-raspberry-500': decision.includes('Hybrid')
                  }">•</span>
                  <span class="text-neutral-500">{{ item }}</span>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>

    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-white border border-light-gray rounded-lg p-6">
      <div class="flex items-start">
        <svg class="h-6 w-6 text-raspberry-600 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <div class="ml-3">
          <h3 class="text-lg font-semibold text-raspberry-900">Error Loading Strategy</h3>
          <p class="mt-2 text-raspberry-700">{{ error }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { mapGetters } from 'vuex';
import estateService from '../../services/estateService';
import { currencyMixin } from '@/mixins/currencyMixin';

import logger from '@/utils/logger';
export default {
  name: 'LifePolicyStrategy',

  emits: ['switch-tab'],

  mixins: [currencyMixin],

  data() {
    return {
      loading: false,
      error: null,
      strategy: null,
      noIHTLiability: false,
      noIHTMessage: '',
    };
  },

  computed: {
    ...mapGetters('estate', ['assets', 'investmentAccounts', 'liabilities']),

    isPreviewMode() {
      return this.$store.getters['preview/isPreviewMode'];
    },
    policy() {
      return this.strategy?.whole_of_life_policy || {};
    },
  },

  mounted() {
    // Preview users are real DB users - use normal API to fetch their data
    this.loadStrategy();
  },

  methods: {
    async loadStrategy() {
      this.loading = true;
      this.error = null;
      this.noIHTLiability = false;

      try {
        const response = await estateService.getLifePolicyStrategy();

        if (response.success) {
          if (response.no_iht_liability) {
            this.noIHTLiability = true;
            this.noIHTMessage = response.message;
          } else {
            this.strategy = response.data;
          }
        } else {
          this.error = response.message || 'Failed to load life policy strategy';
        }
      } catch (err) {
        logger.error('Failed to load life policy strategy:', err);
        this.error = err.response?.data?.message || 'An error occurred while loading the strategy';
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>
