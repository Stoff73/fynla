<template>
  <div
    class="fixed inset-0 z-[70] overflow-y-auto"
    aria-labelledby="plan-modal-title"
    role="dialog"
    aria-modal="true"
  >
    <div class="flex items-center justify-center min-h-screen px-4 py-8">
      <div
        class="fixed inset-0 bg-black/50 backdrop-blur-sm transition-opacity"
        @click="dismissable ? $emit('close') : null"
      ></div>

      <div class="relative bg-white rounded-lg shadow-xl max-w-3xl w-full p-6 z-10">
        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
          <div>
            <h2 id="plan-modal-title" class="text-h3 font-semibold text-horizon-500">
              {{ headerTitle }}
            </h2>
            <p class="mt-1 text-body-sm text-neutral-500">{{ headerSubtitle }}</p>
          </div>
          <button
            v-if="dismissable"
            @click="$emit('close')"
            class="p-1 text-horizon-400 hover:text-neutral-500 transition-colors"
            aria-label="Close"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Limited Time Offer Banner -->
        <div class="flex justify-center mb-4">
          <span class="inline-block bg-raspberry-50 text-raspberry-500 text-base font-bold px-5 py-2 rounded-full">
            Limited Time Offer
          </span>
        </div>

        <!-- Billing Cycle Toggle (matches /pricing style) -->
        <div class="flex justify-center mb-6">
          <div class="inline-flex items-center gap-3 bg-white rounded-full p-1.5 border border-light-gray shadow-sm">
            <button
              @click="billingCycle = 'monthly'"
              :class="[
                'px-5 py-2 rounded-full text-sm font-medium transition-all',
                billingCycle === 'monthly'
                  ? 'bg-horizon-500 text-white shadow-md'
                  : 'text-horizon-400 hover:text-horizon-500'
              ]"
            >
              Monthly
            </button>
            <button
              @click="billingCycle = 'yearly'"
              :class="[
                'px-5 py-2 rounded-full text-sm font-medium transition-all',
                billingCycle === 'yearly'
                  ? 'bg-horizon-500 text-white shadow-md'
                  : 'text-horizon-400 hover:text-horizon-500'
              ]"
            >
              Yearly
              <span v-if="billingCycle === 'yearly'" class="ml-1 text-xs text-spring-500 font-semibold">Save up to {{ maxSavings }}%</span>
            </button>
          </div>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="flex justify-center py-12">
          <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
        </div>

        <!-- Error -->
        <div v-else-if="error" class="text-center py-8">
          <p class="text-body-sm text-raspberry-600 mb-4">{{ error }}</p>
          <button @click="fetchPlans" class="btn-secondary text-sm">Try Again</button>
        </div>

        <!-- Discount Code -->
        <div v-if="!loading && !error" class="mb-4">
          <div v-if="!showDiscountField" class="text-center">
            <button
              @click="showDiscountField = true"
              class="text-body-sm text-violet-500 hover:text-violet-700 transition-colors"
            >
              Have a discount code?
            </button>
          </div>
          <div v-else class="flex gap-2 max-w-sm mx-auto">
            <input
              v-model="discountCode"
              type="text"
              placeholder="Enter discount code"
              class="flex-1 px-3 py-1.5 border border-light-gray rounded-lg text-body-sm uppercase focus:outline-none focus:ring-2 focus:ring-violet-500/20 focus:border-violet-500"
            />
          </div>
        </div>

        <!-- Plan Cards -->
        <div v-if="!loading && !error" :class="gridClass">
          <div
            v-for="plan in filteredPlans"
            :key="plan.slug"
            :class="[
              'relative border rounded-lg p-5 transition-all flex flex-col',
              isCurrentPlan(plan)
                ? 'border-raspberry-500 ring-2 ring-raspberry-500 bg-raspberry-50'
                : 'border-light-gray hover:border-horizon-300 bg-white'
            ]"
          >
            <!-- Current Plan Badge (blue) -->
            <div
              v-if="isCurrentPlan(plan)"
              class="absolute -top-3 left-1/2 -translate-x-1/2"
            >
              <span class="inline-flex items-center px-3 py-0.5 rounded-full text-xs font-semibold bg-horizon-500 text-white whitespace-nowrap">
                Current Plan
              </span>
            </div>
            <!-- Most Popular Badge -->
            <div
              v-else-if="plan.slug === 'family'"
              class="absolute -top-3 left-1/2 -translate-x-1/2"
            >
              <span class="inline-flex items-center px-3 py-0.5 rounded-full text-xs font-semibold bg-spring-500 text-white whitespace-nowrap">
                Most Popular
              </span>
            </div>

            <h3 class="text-h4 font-semibold text-horizon-500 mt-1">{{ plan.name }}</h3>

            <!-- Price -->
            <div class="mt-3 mb-4">
              <div v-if="getLaunchPrice(plan)">
                <span class="text-neutral-400 line-through text-sm">{{ formatPrice(getOriginalPrice(plan)) }}</span>
              </div>
              <div class="flex items-baseline gap-1">
                <span class="text-2xl font-bold text-raspberry-500">{{ formatPrice(getDisplayPrice(plan)) }}</span>
                <span class="text-body-sm text-neutral-500">/{{ billingCycle === 'yearly' ? 'year' : 'month' }}</span>
              </div>
              <div v-if="billingCycle === 'yearly' && getLaunchPrice(plan)" class="mt-1">
                <p class="text-xs text-spring-600 font-medium">{{ formatPrice(Math.round(getLaunchPrice(plan) / 12)) }}/mo</p>
                <p class="text-xs text-spring-600 font-medium">Save {{ savingsPercentage(plan) }}%</p>
              </div>
              <div v-else-if="billingCycle === 'yearly'" class="mt-1">
                <p class="text-xs text-spring-600 font-medium">Save {{ savingsPercentage(plan) }}% vs monthly</p>
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

            <!-- Spacer to push button to bottom -->
            <div class="flex-1"></div>

            <!-- Per-card action button -->
            <button
              v-if="isCurrentPlan(plan)"
              class="mt-4 w-full py-2 px-4 rounded-lg text-sm font-medium bg-neutral-400 text-white cursor-not-allowed"
              disabled
            >
              Current Plan
            </button>
            <button
              v-else
              @click="selectAndContinue(plan.slug)"
              class="mt-4 w-full py-2 px-4 rounded-lg text-sm font-medium bg-raspberry-500 hover:bg-raspberry-600 text-white transition-colors"
            >
              Choose Plan
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import api from '@/services/api';
import { currencyMixin } from '@/mixins/currencyMixin';

const PLAN_ORDER = ['student', 'standard', 'family', 'pro'];

export default {
  name: 'PlanSelectionModal',

  mixins: [currencyMixin],

  emits: ['select', 'close'],

  props: {
    currentPlan: {
      type: String,
      default: null,
    },
    dismissable: {
      type: Boolean,
      default: true,
    },
    showAllPlans: {
      type: Boolean,
      default: false,
    },
  },

  data() {
    return {
      plans: [],
      loading: true,
      error: null,
      billingCycle: 'yearly',
      selectedPlan: null,
      showDiscountField: false,
      discountCode: '',
    };
  },

  computed: {
    filteredPlans() {
      if (!this.currentPlan) return this.plans;
      if (this.showAllPlans) return this.plans;
      const currentIndex = PLAN_ORDER.indexOf(this.currentPlan);
      if (currentIndex === -1) return this.plans;
      return this.plans.filter(p => PLAN_ORDER.indexOf(p.slug) > currentIndex);
    },

    gridClass() {
      const count = this.filteredPlans.length;
      if (count === 1) return 'grid grid-cols-1 max-w-sm mx-auto gap-4';
      if (count === 2) return 'grid grid-cols-1 md:grid-cols-2 max-w-2xl mx-auto gap-4';
      if (count === 3) return 'grid grid-cols-1 md:grid-cols-3 gap-4';
      return 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4';
    },

    maxSavings() {
      if (!this.filteredPlans.length) return 0;
      return Math.max(...this.filteredPlans.map(p => this.savingsPercentage(p)));
    },

    headerTitle() {
      if (!this.dismissable) return 'Your Trial Has Ended';
      if (this.showAllPlans) return 'Choose Your Plan';
      if (this.currentPlan) return 'Upgrade Your Plan';
      return 'Choose Your Plan';
    },

    headerSubtitle() {
      if (!this.dismissable) return 'Choose a plan to continue using Fynla';
      if (this.showAllPlans && this.currentPlan) return 'Your current plan is highlighted below';
      if (this.currentPlan) return 'Select a plan to upgrade to';
      return 'Select a plan that works for you';
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
        // Pre-select first available plan (skip current plan when showing all)
        this.$nextTick(() => {
          if (this.filteredPlans.length && !this.selectedPlan) {
            const selectable = this.showAllPlans
              ? this.filteredPlans.find(p => p.slug !== this.currentPlan)
              : this.filteredPlans[0];
            if (selectable) this.selectedPlan = selectable.slug;
          }
        });
      } catch {
        this.error = 'Failed to load plans. Please try again.';
      } finally {
        this.loading = false;
      }
    },

    getLaunchPrice(plan) {
      return this.billingCycle === 'monthly'
        ? plan.launch_monthly_price
        : plan.launch_yearly_price;
    },

    getOriginalPrice(plan) {
      return this.billingCycle === 'monthly' ? plan.monthly_price : plan.yearly_price;
    },

    getDisplayPrice(plan) {
      return this.getLaunchPrice(plan) || this.getOriginalPrice(plan);
    },

    formatPrice(pence) {
      return this.formatCurrency(pence / 100);
    },

    savingsPercentage(plan) {
      const monthlyPrice = plan.launch_monthly_price || plan.monthly_price;
      const yearlyPrice = plan.launch_yearly_price || plan.yearly_price;
      if (!monthlyPrice || !yearlyPrice) return 0;
      return Math.round((1 - yearlyPrice / (monthlyPrice * 12)) * 100);
    },

    isCurrentPlan(plan) {
      return this.showAllPlans && plan.slug === this.currentPlan;
    },

    selectAndContinue(slug) {
      this.$emit('select', {
        plan: slug,
        billingCycle: this.billingCycle,
        isUpgrade: !!this.currentPlan,
        discountCode: this.discountCode.trim() || null,
      });
    },

    handleSelect() {
      if (!this.selectedPlan) return;
      this.$emit('select', {
        plan: this.selectedPlan,
        billingCycle: this.billingCycle,
        isUpgrade: !!this.currentPlan,
        discountCode: this.discountCode.trim() || null,
      });
    },
  },
};
</script>
