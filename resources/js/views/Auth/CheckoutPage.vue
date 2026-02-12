<template>
  <AppLayout>
    <div class="max-w-2xl mx-auto py-8 px-4">
      <h1 class="text-2xl font-bold text-gray-900 mb-6">Complete Your Subscription</h1>

      <!-- Coming Soon State -->
      <div v-if="!paymentEnabled" class="bg-white rounded-xl border border-gray-200 p-8 text-center">
        <div class="mx-auto w-16 h-16 bg-blue-50 rounded-full flex items-center justify-center mb-4">
          <svg class="w-8 h-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <h2 class="text-lg font-semibold text-gray-900 mb-2">Payment Coming Soon</h2>
        <p class="text-gray-500 mb-4">
          We're currently setting up our payment system. You'll be able to upgrade your plan here shortly.
        </p>
        <p class="text-sm text-gray-400">
          Your trial will continue as normal. We'll notify you when payments are live.
        </p>
        <router-link
          to="/dashboard"
          class="inline-flex items-center mt-6 px-4 py-2 text-sm font-medium text-blue-600 hover:text-blue-700 transition-colors"
        >
          &larr; Back to Dashboard
        </router-link>
      </div>

      <!-- Checkout State -->
      <div v-else>
        <!-- Order Summary -->
        <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
          <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">Order Summary</h2>
          <div v-if="trialData" class="space-y-3">
            <div class="flex justify-between items-center">
              <span class="text-gray-700">{{ planName }} Plan</span>
              <span class="font-semibold text-gray-900">{{ formattedPrice }}</span>
            </div>
            <div class="flex justify-between items-center text-sm text-gray-500">
              <span>Billing cycle</span>
              <span>{{ trialData.billing_cycle === 'yearly' ? 'Yearly' : 'Monthly' }}</span>
            </div>
            <div class="border-t border-gray-100 pt-3 flex justify-between items-center">
              <span class="font-semibold text-gray-900">Total</span>
              <span class="text-lg font-bold text-gray-900">{{ formattedPrice }}</span>
            </div>
          </div>
          <div v-else class="animate-pulse space-y-3">
            <div class="h-4 bg-gray-200 rounded w-3/4"></div>
            <div class="h-4 bg-gray-200 rounded w-1/2"></div>
          </div>
        </div>

        <!-- Revolut Widget Container -->
        <div class="bg-white rounded-xl border border-gray-200 p-6">
          <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">Payment Details</h2>

          <div v-if="error" class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
            {{ error }}
            <button @click="initCheckout" class="ml-2 text-red-800 font-semibold hover:underline">Retry</button>
          </div>

          <div id="revolut-checkout" class="min-h-[200px]"></div>

          <div v-if="loading" class="flex items-center justify-center py-8">
            <svg class="animate-spin h-6 w-6 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            <span class="ml-2 text-sm text-gray-500">Loading payment form...</span>
          </div>
        </div>

        <div class="mt-4 text-center">
          <router-link
            to="/dashboard"
            class="text-sm text-gray-500 hover:text-gray-700 transition-colors"
          >
            &larr; Back to Dashboard
          </router-link>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';
import api from '@/services/api';

export default {
  name: 'CheckoutPage',

  components: {
    AppLayout,
  },

  data() {
    return {
      trialData: null,
      loading: false,
      error: null,
      paymentEnabled: false,
    };
  },

  computed: {
    planName() {
      if (!this.trialData) return '';
      return this.trialData.plan.charAt(0).toUpperCase() + this.trialData.plan.slice(1);
    },

    formattedPrice() {
      if (!this.trialData) return '';
      const amount = this.trialData.amount / 100;
      const period = this.trialData.billing_cycle === 'yearly' ? '/year' : '/month';
      return `£${amount.toFixed(2)}${period}`;
    },
  },

  async mounted() {
    await this.fetchTrialStatus();
    if (this.paymentEnabled && this.trialData) {
      this.initCheckout();
    }
  },

  methods: {
    async fetchTrialStatus() {
      try {
        const response = await api.get('/payment/trial-status');
        this.trialData = response.data;
        // Check if payment is enabled from meta or env
        this.paymentEnabled = response.data.payment_enabled ?? false;
      } catch {
        this.error = 'Failed to load subscription details.';
      }
    },

    async initCheckout() {
      this.loading = true;
      this.error = null;

      try {
        const response = await api.post('/payment/create-order');
        const { public_id } = response.data;

        if (!public_id) {
          this.error = 'Failed to create payment order. Please try again.';
          return;
        }

        // Load Revolut checkout widget
        if (typeof RevolutCheckout !== 'undefined') {
          const instance = await RevolutCheckout(public_id);
          instance.payWithPopup({
            onSuccess: () => {
              this.$router.push({ path: '/dashboard', query: { payment: 'success' } });
            },
            onError: (error) => {
              console.error('Payment error:', error);
              this.error = 'Payment failed. Please try again.';
            },
            onCancel: () => {
              // User cancelled — do nothing
            },
          });
        } else {
          this.error = 'Payment system is not available. Please try again later.';
        }
      } catch {
        this.error = 'Failed to initialise payment. Please try again.';
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>
