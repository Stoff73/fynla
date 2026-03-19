<template>
  <div
    class="fixed inset-0 z-50 overflow-y-auto"
    aria-labelledby="plan-modal-title"
    role="dialog"
    aria-modal="true"
  >
    <div class="flex items-center justify-center min-h-screen px-4 py-8">
      <div class="fixed inset-0 bg-savannah-1000/75 transition-opacity" @click="$emit('close')"></div>

      <div class="relative bg-white rounded-lg shadow-xl max-w-3xl w-full p-6 z-10">
        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
          <div>
            <h2 id="plan-modal-title" class="text-h3 font-semibold text-horizon-500">Choose Your Plan</h2>
            <p class="mt-1 text-body-sm text-neutral-500">Select a plan that works for you</p>
          </div>
          <button
            @click="$emit('close')"
            class="p-1 text-horizon-400 hover:text-neutral-500 transition-colors"
            aria-label="Close"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Billing Cycle Toggle -->
        <div class="flex justify-center mb-6">
          <div class="inline-flex items-center bg-savannah-100 rounded-lg p-1">
            <button
              @click="billingCycle = 'monthly'"
              :class="[
                'px-4 py-2 text-sm font-medium rounded-md transition-colors',
                billingCycle === 'monthly'
                  ? 'bg-white text-horizon-500 shadow-sm'
                  : 'text-neutral-500 hover:text-horizon-500'
              ]"
            >
              Monthly
            </button>
            <button
              @click="billingCycle = 'yearly'"
              :class="[
                'px-4 py-2 text-sm font-medium rounded-md transition-colors',
                billingCycle === 'yearly'
                  ? 'bg-white text-horizon-500 shadow-sm'
                  : 'text-neutral-500 hover:text-horizon-500'
              ]"
            >
              Yearly
              <span v-if="billingCycle === 'yearly'" class="ml-1 text-xs text-spring-600 font-semibold">Save up to {{ maxSavings }}%</span>
            </button>
          </div>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="flex justify-center py-12">
          <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-raspberry-600"></div>
        </div>

        <!-- Error -->
        <div v-else-if="error" class="text-center py-8">
          <p class="text-body-sm text-raspberry-600 mb-4">{{ error }}</p>
          <button @click="fetchPlans" class="btn-secondary text-sm">Try Again</button>
        </div>

        <!-- Plan Cards -->
        <div v-else class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div
            v-for="plan in plans"
            :key="plan.slug"
            :class="[
              'relative border rounded-lg p-5 transition-all cursor-pointer',
              selectedPlan === plan.slug
                ? 'border-raspberry-500 ring-2 ring-raspberry-500 bg-raspberry-50'
                : 'border-light-gray hover:border-horizon-300 bg-white'
            ]"
            @click="selectedPlan = plan.slug"
          >
            <!-- Most Popular Badge -->
            <div
              v-if="plan.slug === 'standard'"
              class="absolute -top-3 left-1/2 -translate-x-1/2"
            >
              <span class="inline-flex items-center px-3 py-0.5 rounded-full text-xs font-semibold bg-raspberry-600 text-white">
                Most Popular
              </span>
            </div>

            <h3 class="text-h4 font-semibold text-horizon-500 mt-1">{{ plan.name }}</h3>

            <!-- Price -->
            <div class="mt-3 mb-4">
              <span class="text-2xl font-bold text-horizon-500">{{ formatPrice(getPrice(plan)) }}</span>
              <span class="text-body-sm text-neutral-500">/{{ billingCycle === 'yearly' ? 'year' : 'month' }}</span>
              <div v-if="billingCycle === 'yearly'" class="mt-1">
                <span class="text-xs text-spring-600 font-medium">
                  Save {{ savingsPercentage(plan) }}% vs monthly
                </span>
              </div>
            </div>

            <!-- Features -->
            <ul v-if="plan.features && plan.features.length" class="space-y-2">
              <li
                v-for="(feature, index) in plan.features"
                :key="index"
                class="flex items-start gap-2 text-body-sm text-neutral-500"
              >
                <svg class="w-4 h-4 text-spring-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                {{ feature }}
              </li>
            </ul>
          </div>
        </div>

        <!-- Footer -->
        <div class="mt-6 flex justify-end gap-3">
          <button
            @click="$emit('close')"
            class="btn-secondary"
          >
            Cancel
          </button>
          <button
            @click="handleSelect"
            class="btn-primary"
            :disabled="!selectedPlan"
          >
            Continue
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import api from '@/services/api';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'PlanSelectionModal',

  mixins: [currencyMixin],

  emits: ['select', 'close'],

  data() {
    return {
      plans: [],
      loading: true,
      error: null,
      billingCycle: 'yearly',
      selectedPlan: 'standard',
    };
  },

  computed: {
    maxSavings() {
      if (!this.plans.length) return 0;
      return Math.max(...this.plans.map(p => this.savingsPercentage(p)));
    },
  },

  mounted() {
    this.fetchPlans();
  },

  methods: {
    async fetchPlans() {
      this.loading = true;
      this.error = null;
      try {
        const response = await api.get('/payment/plans');
        this.plans = response.data.plans || [];
      } catch {
        this.error = 'Failed to load plans. Please try again.';
      } finally {
        this.loading = false;
      }
    },

    getPrice(plan) {
      return this.billingCycle === 'monthly' ? plan.monthly_price : plan.yearly_price;
    },

    formatPrice(pence) {
      return this.formatCurrency(pence / 100);
    },

    savingsPercentage(plan) {
      if (!plan.monthly_price || !plan.yearly_price) return 0;
      return Math.round((1 - plan.yearly_price / (plan.monthly_price * 12)) * 100);
    },

    handleSelect() {
      if (!this.selectedPlan) return;
      this.$emit('select', {
        plan: this.selectedPlan,
        billingCycle: this.billingCycle,
      });
    },
  },
};
</script>
