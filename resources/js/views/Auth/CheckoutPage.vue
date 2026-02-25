<template>
  <AppLayout>
    <div class="max-w-4xl mx-auto py-8 px-4">
      <!-- Back Button -->
      <button
        @click="goBack"
        class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 transition-colors mb-6"
      >
        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
        Back
      </button>

      <!-- Missing Plan/Cycle -->
      <div v-if="!plan || !billingCycle" class="bg-white rounded-xl border border-gray-200 p-8 text-center">
        <p class="text-body-base text-gray-600 mb-4">No plan selected. Please choose a plan first.</p>
        <router-link to="/dashboard" class="btn-primary">Go to Dashboard</router-link>
      </div>

      <!-- Checkout Content -->
      <div v-else>
        <h1 class="text-2xl font-bold text-gray-900 mb-6">Complete Your Subscription</h1>

        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
          <!-- Order Summary (left) -->
          <div class="lg:col-span-2">
            <div class="bg-white rounded-xl border border-gray-200 p-6 sticky top-24">
              <h2 class="text-h4 font-semibold text-gray-900 mb-4">Order Summary</h2>

              <div class="space-y-3">
                <div class="flex justify-between">
                  <span class="text-body-sm text-gray-600">Plan</span>
                  <span class="text-body-sm font-medium text-gray-900">{{ planDisplayName }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-body-sm text-gray-600">Billing</span>
                  <span class="text-body-sm font-medium text-gray-900 capitalize">{{ billingCycle }}</span>
                </div>
                <div class="border-t border-gray-200 pt-3">
                  <div class="flex justify-between">
                    <span class="text-body-base font-semibold text-gray-900">Total</span>
                    <span class="text-body-base font-semibold text-gray-900">
                      {{ planPrice }}/{{ billingCycle === 'yearly' ? 'year' : 'month' }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Checkout Widget (right) -->
          <div class="lg:col-span-3">
            <!-- Initialisation Error -->
            <div v-if="error" class="bg-error-100 border border-error-600/20 rounded-lg p-4 mb-4">
              <p class="text-body-sm text-error-600">{{ error }}</p>
              <button @click="initCheckout" class="mt-2 text-sm text-error-700 underline hover:no-underline">
                Try again
              </button>
            </div>

            <!-- Payment Error -->
            <div v-if="paymentError" class="bg-error-100 border border-error-600/20 rounded-lg p-4 mb-4">
              <p class="text-body-sm text-error-600">{{ paymentError }}</p>
            </div>

            <!-- Widget Container -->
            <div
              v-show="!paymentComplete && !error"
              class="bg-white rounded-xl border border-gray-200 p-6"
            >
              <h2 class="text-h4 font-semibold text-gray-900 mb-4">Payment Details</h2>
              <div ref="checkoutContainer" class="min-h-[300px]"></div>
            </div>

            <!-- Processing Overlay -->
            <div v-if="processing" class="bg-white rounded-xl border border-gray-200 p-8 text-center">
              <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-primary-600 mx-auto mb-4"></div>
              <p class="text-body-base text-gray-600">Confirming your payment...</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Success Modal -->
      <div
        v-if="paymentComplete"
        class="fixed inset-0 z-50 overflow-y-auto"
        role="dialog"
        aria-modal="true"
      >
        <div class="flex items-center justify-center min-h-screen px-4">
          <div class="fixed inset-0 bg-gray-500/75 transition-opacity"></div>

          <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-8 z-10 text-center">
            <div class="mx-auto w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mb-4">
              <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
              </svg>
            </div>
            <h2 class="text-h3 font-semibold text-gray-900 mb-2">Payment Successful</h2>
            <p class="text-body-sm text-gray-600 mb-6">
              Your {{ planDisplayName }} plan is now active. Enjoy full access to all features.
            </p>
            <button
              @click="goToDashboard"
              class="btn-primary w-full"
            >
              Go to Dashboard
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';
import api from '@/services/api';
import { currencyMixin } from '@/mixins/currencyMixin';
import logger from '@/utils/logger';

/**
 * Load the Revolut Merchant SDK from CDN.
 * The npm package (@revolut/checkout v1.1.24) does NOT expose embeddedCheckout
 * as a static method — it only exists on instances created with an order token.
 * Loading the CDN script directly gives us the documented static API:
 *   RevolutCheckout.embeddedCheckout({ publicToken, mode, ... })
 */
function loadRevolutSDK(sandbox) {
  const scriptId = 'revolut-checkout';

  // Return cached global if already loaded
  if (window.RevolutCheckout?.embeddedCheckout) {
    return Promise.resolve(window.RevolutCheckout);
  }

  // Return existing loading promise if script tag already injected
  const existing = document.getElementById(scriptId);
  if (existing && existing._loadPromise) {
    return existing._loadPromise;
  }

  const src = sandbox
    ? 'https://sandbox-merchant.revolut.com/embed.js'
    : 'https://merchant.revolut.com/embed.js';

  const script = document.createElement('script');
  script.id = scriptId;
  script.src = src;
  script.async = true;

  const promise = new Promise((resolve, reject) => {
    script.onload = () => {
      if (window.RevolutCheckout) {
        resolve(window.RevolutCheckout);
      } else {
        reject(new Error('RevolutCheckout not available after script load'));
      }
    };
    script.onerror = () => reject(new Error('Failed to load Revolut SDK'));
  });

  script._loadPromise = promise;
  document.head.appendChild(script);

  return promise;
}

export default {
  name: 'CheckoutPage',

  components: {
    AppLayout,
  },

  mixins: [currencyMixin],

  data() {
    return {
      error: null,
      paymentError: null,
      processing: false,
      paymentComplete: false,
      destroyWidget: null,
      revolutOrderId: null,
      planData: null,
    };
  },

  computed: {
    plan() {
      return this.$route.query.plan;
    },

    billingCycle() {
      return this.$route.query.cycle;
    },

    planDisplayName() {
      if (!this.plan) return '';
      return this.plan.charAt(0).toUpperCase() + this.plan.slice(1);
    },

    userEmail() {
      return this.$store.state.user?.email || '';
    },

    planPrice() {
      if (!this.planData) return '...';
      const pence = this.billingCycle === 'monthly'
        ? this.planData.monthly_price
        : this.planData.yearly_price;
      return this.formatCurrency(pence / 100);
    },
  },

  mounted() {
    if (this.plan && this.billingCycle) {
      this.fetchPlanData();
      this.initCheckout();
    }
  },

  beforeUnmount() {
    if (this.destroyWidget) {
      this.destroyWidget();
    }
  },

  methods: {
    async fetchPlanData() {
      try {
        const response = await api.get('/payment/plans');
        const plans = response.data.plans || [];
        this.planData = plans.find(p => p.slug === this.plan) || null;
      } catch {
        // Non-critical — price just shows "..."
      }
    },

    async initCheckout() {
      this.error = null;
      this.paymentError = null;

      // Wait for DOM to render the container
      await this.$nextTick();

      if (!this.$refs.checkoutContainer) {
        this.error = 'Failed to initialise checkout: container not found.';
        return;
      }

      try {
        const isSandbox = import.meta.env.VITE_REVOLUT_SANDBOX === 'true';
        const RevolutCheckout = await loadRevolutSDK(isSandbox);

        const { destroy } = await RevolutCheckout.embeddedCheckout({
          publicToken: import.meta.env.VITE_REVOLUT_PUBLIC_KEY,
          mode: isSandbox ? 'sandbox' : 'prod',
          locale: 'auto',
          target: this.$refs.checkoutContainer,
          createOrder: async () => {
            // Called by widget when user clicks Pay
            const response = await api.post('/payment/create-order', {
              plan: this.plan,
              billing_cycle: this.billingCycle,
            });
            // Store the internal UUID for confirmPayment call
            // CRITICAL: onSuccess's orderId is the TOKEN, not the UUID
            this.revolutOrderId = response.data.order_id;
            // Return token to widget as { publicId }
            return { publicId: response.data.token };
          },
          onSuccess: () => {
            // orderId in callback is the ORDER TOKEN (not UUID) per Revolut docs
            // We use this.revolutOrderId (the UUID) for the confirm call
            this.handlePaymentSuccess();
          },
          onError: ({ error }) => {
            this.paymentError = error.message || 'Payment failed. Please try again.';
          },
          onCancel: () => {
            // User cancelled — stay on page, no action needed
          },
          email: this.userEmail,
        });
        this.destroyWidget = destroy;
      } catch (err) {
        if (err.name === 'RevolutCheckout') {
          this.error = 'Failed to initialise checkout: ' + err.message;
        } else {
          this.error = 'Failed to initialise payment system. Please try again.';
        }
        logger.error('Checkout init failed', err);
      }
    },

    async handlePaymentSuccess() {
      this.processing = true;
      try {
        await api.post('/payment/confirm', { order_id: this.revolutOrderId });
        this.paymentComplete = true;
      } catch {
        // Webhook will handle as backup — still show success to user
        this.paymentComplete = true;
      } finally {
        this.processing = false;
      }
    },

    goToDashboard() {
      this.$router.push({ path: '/dashboard', query: { payment: 'success' } });
    },

    goBack() {
      this.$router.back();
    },
  },
};
</script>
