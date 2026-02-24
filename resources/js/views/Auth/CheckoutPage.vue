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

        <!-- Error Display -->
        <div v-if="error" class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">
          {{ error }}
          <button @click="initCheckout" class="ml-2 text-red-800 font-semibold hover:underline">Retry</button>
        </div>

        <!-- Payment Widget Container -->
        <div class="bg-white rounded-xl border border-gray-200 p-6">
          <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">Payment Details</h2>

          <!-- Embedded / Card Field target -->
          <div ref="revolutCheckout" id="revolut-checkout" class="min-h-[200px]"></div>

          <!-- Card field needs a separate submit button -->
          <button
            v-if="checkoutMode === 'card_field' && cardFieldReady && !submitting"
            @click="submitCardPayment"
            class="mt-4 w-full inline-flex items-center justify-center px-6 py-3 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
          >
            Pay {{ formattedPrice }}
          </button>

          <div v-if="submitting" class="mt-4 flex items-center justify-center py-3">
            <svg class="animate-spin h-5 w-5 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            <span class="ml-2 text-sm text-gray-500">Processing payment...</span>
          </div>

          <!-- Loading state -->
          <div v-if="loading" class="flex items-center justify-center py-8">
            <svg class="animate-spin h-6 w-6 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            <span class="ml-2 text-sm text-gray-500">Loading payment form...</span>
          </div>
        </div>

        <!-- Success Message -->
        <div v-if="paymentSuccess" class="mt-6 p-4 bg-green-50 border border-green-200 rounded-xl text-center">
          <svg class="mx-auto w-8 h-8 text-green-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
          <p class="text-sm font-semibold text-green-800">Payment successful! Redirecting to your dashboard...</p>
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
import { currencyMixin } from '@/mixins/currencyMixin';
import logger from '@/utils/logger';

export default {
  name: 'CheckoutPage',

  components: {
    AppLayout,
  },

  mixins: [currencyMixin],

  data() {
    return {
      trialData: null,
      loading: false,
      submitting: false,
      error: null,
      paymentEnabled: false,
      paymentSuccess: false,
      checkoutMode: 'popup',
      cardFieldReady: false,
      revolutInstance: null,
      cardField: null,
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
      return `${this.formatCurrency(amount)}${period}`;
    },
  },

  async mounted() {
    await this.fetchTrialStatus();
    if (this.paymentEnabled && this.trialData) {
      this.initCheckout();
    }
  },

  beforeUnmount() {
    this.destroyWidget();
  },

  methods: {
    async fetchTrialStatus() {
      try {
        const response = await api.get('/payment/trial-status');
        this.trialData = response.data;
        this.paymentEnabled = response.data.payment_enabled ?? false;
        this.checkoutMode = response.data.checkout?.mode ?? 'popup';
      } catch {
        this.error = 'Failed to load subscription details.';
      }
    },

    async initCheckout() {
      this.loading = true;
      this.error = null;
      this.destroyWidget();

      try {
        // Create subscription via backend (lazy customer creation + Revolut subscription)
        const response = await api.post('/payment/subscribe');
        const { setup_order_id } = response.data;

        if (!setup_order_id) {
          this.error = 'Failed to create subscription. Please try again.';
          return;
        }

        // Wait for the Revolut SDK to be available
        await this.waitForRevolutSDK();

        // Initialise the Revolut checkout instance
        this.revolutInstance = await window.RevolutCheckout(setup_order_id);

        // Mount the appropriate widget mode
        switch (this.checkoutMode) {
          case 'embedded':
            this.mountEmbeddedWidget();
            break;
          case 'card_field':
            this.mountCardField();
            break;
          case 'popup':
          default:
            this.openPopup();
            break;
        }
      } catch {
        this.error = 'Failed to initialise payment. Please try again.';
      } finally {
        this.loading = false;
      }
    },

    /**
     * Popup mode — Revolut-branded modal overlay.
     * Full payment form with card, Apple Pay, Google Pay.
     * Simplest to implement, least control over styling.
     */
    openPopup() {
      this.revolutInstance.payWithPopup({
        onSuccess: () => this.handleSuccess(),
        onError: (error) => this.handleError(error),
        onCancel: () => {},
      });
    },

    /**
     * Embedded mode — Full payment widget rendered inline.
     * Card + Apple Pay + Google Pay all in one embedded form.
     * Good balance of control and simplicity.
     */
    mountEmbeddedWidget() {
      this.revolutInstance.mount(this.$refs.revolutCheckout, {
        onSuccess: () => this.handleSuccess(),
        onError: (error) => this.handleError(error),
        onCancel: () => {},
      });
    },

    /**
     * Card field mode — Only card input fields embedded.
     * Maximum control over surrounding UI and layout.
     * Requires a separate submit button to trigger payment.
     * Does not include Apple Pay / Google Pay (card only).
     */
    mountCardField() {
      this.cardField = this.revolutInstance.createCardField({
        target: this.$refs.revolutCheckout,
        onSuccess: () => this.handleSuccess(),
        onError: (error) => this.handleError(error),
        onCancel: () => {},
        onStatusChange: (status) => {
          this.cardFieldReady = status === 'ready';
        },
      });
    },

    /**
     * Submit card payment — only used in card_field mode.
     * Called when user clicks the Pay button.
     */
    async submitCardPayment() {
      if (!this.cardField) return;
      this.submitting = true;
      this.error = null;
      try {
        await this.cardField.submit();
      } catch {
        this.error = 'Payment failed. Please check your card details and try again.';
      } finally {
        this.submitting = false;
      }
    },

    handleSuccess() {
      this.paymentSuccess = true;
      this.destroyWidget();
      setTimeout(() => {
        this.$router.push({ path: '/dashboard', query: { payment: 'success' } });
      }, 2000);
    },

    handleError(error) {
      logger.error('CheckoutPage', 'Payment error', { message: error?.message, code: error?.code });
      this.error = 'Payment failed. Please try again.';
    },

    destroyWidget() {
      if (this.cardField) {
        try { this.cardField.destroy(); } catch { /* already destroyed */ }
        this.cardField = null;
        this.cardFieldReady = false;
      }
      if (this.revolutInstance) {
        try { this.revolutInstance.destroy(); } catch { /* already destroyed */ }
        this.revolutInstance = null;
      }
    },

    /**
     * Wait for the Revolut SDK script to load.
     * The script is loaded with defer in app.blade.php,
     * so it may not be ready when the component mounts.
     */
    waitForRevolutSDK() {
      return new Promise((resolve, reject) => {
        if (typeof window.RevolutCheckout !== 'undefined') {
          resolve();
          return;
        }

        let attempts = 0;
        const maxAttempts = 50; // 5 seconds max
        const interval = setInterval(() => {
          attempts++;
          if (typeof window.RevolutCheckout !== 'undefined') {
            clearInterval(interval);
            resolve();
          } else if (attempts >= maxAttempts) {
            clearInterval(interval);
            reject(new Error('Revolut SDK failed to load'));
          }
        }, 100);
      });
    },
  },
};
</script>
